const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createOverlayController() {
    const state = {
        bootPhase: false,
        visible: false,
        shownAt: 0,
        tokenCounter: 0,
        tokens: {},
        showTimer: null,
        hideTimer: null,
        idleTimer: null,
        safetyTimer: null,
        config: {
            showDelayMs: 1000,
            minVisibleMs: 260,
            idleGraceMs: 320,
            safetyTimeoutMs: 12000
        }
    };

    function getOverlay() {
        if (typeof document === 'undefined') {
            return null;
        }

        return document.getElementById('app-boot-overlay');
    }

    function clearTimer(name) {
        if (state[name]) {
            globalWindow.clearTimeout(state[name]);
            state[name] = null;
        }
    }

    function hasTokens() {
        return Object.keys(state.tokens).length > 0;
    }

    function syncBodyState() {
        if (typeof document === 'undefined' || !document.body) {
            return;
        }

        document.body.classList.toggle('app-booting', state.visible || state.bootPhase || hasTokens());
    }

    function showOverlay() {
        clearTimer('showTimer');

        if (state.visible || (!state.bootPhase && !hasTokens())) {
            return;
        }

        const overlay = getOverlay();
        if (!overlay) {
            return;
        }

        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
        state.visible = true;
        state.shownAt = Date.now();
        syncBodyState();
    }

    function hideOverlay() {
        clearTimer('showTimer');
        clearTimer('hideTimer');

        const overlay = getOverlay();
        if (overlay) {
            overlay.classList.remove('is-active');
            overlay.setAttribute('aria-hidden', 'true');
        }

        state.visible = false;
        state.shownAt = 0;
        syncBodyState();
    }

    function scheduleShow() {
        clearTimer('hideTimer');

        if (state.visible || state.showTimer || (!state.bootPhase && !hasTokens())) {
            return;
        }

        state.showTimer = globalWindow.setTimeout(showOverlay, state.config.showDelayMs);
    }

    function scheduleHide() {
        let wait = 0;

        clearTimer('showTimer');

        if (state.bootPhase || hasTokens()) {
            return;
        }

        if (state.visible && state.shownAt > 0) {
            wait = Math.max(0, state.config.minVisibleMs - (Date.now() - state.shownAt));
        }

        clearTimer('hideTimer');
        state.hideTimer = globalWindow.setTimeout(hideOverlay, wait);
    }

    function armIdleClose() {
        clearTimer('idleTimer');

        if (!state.bootPhase || hasTokens()) {
            return;
        }

        state.idleTimer = globalWindow.setTimeout(function () {
            if (hasTokens()) {
                return;
            }

            state.bootPhase = false;
            clearTimer('safetyTimer');
            scheduleHide();
            syncBodyState();
        }, state.config.idleGraceMs);
    }

    function retain(prefix) {
        const token = String(prefix || 'token') + ':' + String(++state.tokenCounter);

        state.tokens[token] = true;
        clearTimer('idleTimer');
        scheduleShow();
        syncBodyState();

        return token;
    }

    function release(token) {
        if (!token || !state.tokens[token]) {
            return;
        }

        delete state.tokens[token];

        if (!hasTokens()) {
            armIdleClose();
            scheduleHide();
        }

        syncBodyState();
    }

    return {
        beginBoot: function (label) {
            state.bootPhase = true;
            clearTimer('idleTimer');
            clearTimer('safetyTimer');
            state.safetyTimer = globalWindow.setTimeout(function () {
                state.bootPhase = false;
                state.tokens = {};
                hideOverlay();
            }, state.config.safetyTimeoutMs);

            return retain(label || 'boot');
        },
        endBoot: function (token) {
            release(token);

            if (!hasTokens()) {
                armIdleClose();
            }
        },
        beginRequest: function () {
            if (!state.bootPhase) {
                return null;
            }

            return retain('request');
        },
        endRequest: function (token) {
            release(token);
        },
        forceFinish: function () {
            clearTimer('idleTimer');
            clearTimer('safetyTimer');
            state.bootPhase = false;
            state.tokens = {};
            hideOverlay();
        },
        isBootPhase: function () {
            return state.bootPhase;
        }
    };
}

globalWindow.AppBootOverlay = globalWindow.AppBootOverlay || createOverlayController();
