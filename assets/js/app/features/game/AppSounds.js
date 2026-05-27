const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var KEYS = {
    chat:          'lf_sound_chat',
    dm:            'lf_sound_dm',
    notifications: 'lf_sound_notifications',
    whispers:      'lf_sound_whispers',
    global:        'lf_sound_global'
};

var REMINDER_TYPES = {
    dm: true,
    notifications: true,
    whispers: true,
    global: true
};

var REMINDER_BASE_TYPES = ['dm', 'notifications', 'whispers'];

var activeAlerts = {
    dm: false,
    notifications: false,
    whispers: false,
    global: false
};

var lastPlayedAt = {};
var reminderTimers = {};
var REPEATABLE_COOLDOWN_MS = 750;
var REMINDER_INTERVAL_MS = 120000;
var batchPending = {};
var batchTimer = null;
var BATCH_DEBOUNCE_MS = 300;
var MAX_PLAYBACK_MS = 5000;

function isBadgeActive(selector) {
    var nodes = document.querySelectorAll(selector);
    if (!nodes || nodes.length === 0) {
        return false;
    }

    for (var i = 0; i < nodes.length; i++) {
        var node = nodes[i];
        if (!node || node.classList.contains('d-none')) {
            continue;
        }
        var value = parseInt(String(node.textContent || '0').trim(), 10);
        if (!isNaN(value) && value > 0) {
            return true;
        }
    }

    return false;
}

function isAttentionActive(type) {
    if (type === 'dm') {
        return isBadgeActive('[data-feed-badge="messages"]');
    }
    if (type === 'notifications') {
        return isBadgeActive('[data-feed-badge="notifications"]');
    }
    if (type === 'whispers') {
        return isBadgeActive('[data-role="whispers-unread-badge"]');
    }
    if (type === 'global') {
        var activeBase = 0;
        for (var i = 0; i < REMINDER_BASE_TYPES.length; i++) {
            if (isAttentionActive(REMINDER_BASE_TYPES[i])) {
                activeBase++;
            }
        }
        return activeBase >= 2;
    }
    return false;
}

function hasGlobalConfigured() {
    return getUrl('global') !== '';
}

function getUrl(type) {
    if (!KEYS[type]) { return ''; }
    try {
        return (globalWindow.localStorage.getItem(KEYS[type]) || '').trim();
    } catch (e) {
        return '';
    }
}

function setUrl(type, url) {
    if (!KEYS[type]) { return; }
    try {
        var trimmed = (url || '').trim();
        if (trimmed !== '') {
            globalWindow.localStorage.setItem(KEYS[type], trimmed);
        } else {
            globalWindow.localStorage.removeItem(KEYS[type]);
        }
    } catch (e) {}
}

function playUrl(url) {
    if (!url || url === '') { return; }
    try {
        var audio = new Audio(url);
        var stopTimer = null;
        var stopPlayback = function () {
            if (stopTimer) {
                clearTimeout(stopTimer);
                stopTimer = null;
            }
            try {
                audio.pause();
                audio.currentTime = 0;
            } catch (e) {}
        };

        audio.addEventListener('ended', function () {
            if (stopTimer) {
                clearTimeout(stopTimer);
                stopTimer = null;
            }
        }, { once: true });

        stopTimer = setTimeout(stopPlayback, MAX_PLAYBACK_MS);
        var p = audio.play();
        if (p && typeof p.catch === 'function') {
            p.catch(function () {});
        }
    } catch (e) {}
}

function playType(type) {
    var now = Date.now();
    var last = parseInt(lastPlayedAt[type] || 0, 10);
    if (last > 0 && (now - last) < REPEATABLE_COOLDOWN_MS) {
        return;
    }
    lastPlayedAt[type] = now;
    playUrl(getUrl(type));
}

function stopReminder(type) {
    if (reminderTimers[type]) {
        clearInterval(reminderTimers[type]);
        reminderTimers[type] = null;
    }
}

function ensureReminder(type) {
    if (!REMINDER_TYPES[type]) {
        return;
    }
    if (reminderTimers[type]) {
        return;
    }

    reminderTimers[type] = setInterval(function () {
        if (!activeAlerts[type] || !isAttentionActive(type)) {
            activeAlerts[type] = false;
            stopReminder(type);
            return;
        }

        if (type !== 'global' && hasGlobalConfigured() && isAttentionActive('global')) {
            return;
        }

        playType(type);
    }, REMINDER_INTERVAL_MS);
}

function flushBatchPending() {
    batchTimer = null;
    var pendingTypes = Object.keys(batchPending).filter(function (t) { return batchPending[t]; });
    batchPending = {};

    if (pendingTypes.length === 0) { return; }

    var globalEnabled = hasGlobalConfigured();
    if (pendingTypes.length >= 2 && globalEnabled) {
        activeAlerts.global = true;
        ensureReminder('global');
        playType('global');

        for (var i = 0; i < pendingTypes.length; i++) {
            ensureReminder(pendingTypes[i]);
        }
        return;
    }

    for (var j = 0; j < pendingTypes.length; j++) {
        var type = pendingTypes[j];
        playType(type);
        ensureReminder(type);
    }
}

var AppSounds = {
    /**
     * Trigger a sound for the given type (chat | dm | notifications | whispers).
     * chat plays one-shot on each event (anti-spam cooldown), never in loop.
     * dm/notifications/whispers/global use a soft reminder loop while unread badges remain active.
     */
    play: function (type) {
        if (!KEYS[type] || type === 'global') {
            return;
        }

        if (type === 'chat') {
            playType('chat');
            return;
        }

        activeAlerts[type] = true;
        ensureReminder(type);
        batchPending[type] = true;

        if (batchTimer) { clearTimeout(batchTimer); }
        batchTimer = setTimeout(flushBatchPending, BATCH_DEBOUNCE_MS);
    },

    /** Preview a sound by type from current localStorage value. */
    preview: function (type) {
        playUrl(getUrl(type));
    },

    /** Preview an arbitrary URL (for test button before saving). */
    previewUrl: function (url) {
        playUrl((url || '').trim());
    },

    get: function (type) {
        return getUrl(type);
    },

    set: function (type, url) {
        setUrl(type, url);
    },

    clear: function (type) {
        activeAlerts[type] = false;
        stopReminder(type);
        setUrl(type, '');
    },

    stop: function (type) {
        if (!type || !KEYS[type]) {
            return;
        }
        activeAlerts[type] = false;
        stopReminder(type);
    },

    types: function () {
        return Object.keys(KEYS);
    }
};

globalWindow.AppSounds = AppSounds;
export { AppSounds as AppSounds };
export default AppSounds;

