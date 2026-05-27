const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function resolveModule(name) {
    if (!globalWindow.RuntimeBootstrap || typeof globalWindow.RuntimeBootstrap.resolveAppModule !== 'function') {
        return null;
    }
    try {
        return globalWindow.RuntimeBootstrap.resolveAppModule(name);
    } catch (error) {
        return null;
    }
}


function normalizeNewsError(error, fallback) {
    if (globalWindow.GameFeatureError && typeof globalWindow.GameFeatureError.normalize === 'function') {
        return globalWindow.GameFeatureError.normalize(error, fallback || 'Operazione non riuscita.');
    }
    if (typeof error === 'string' && error.trim() !== '') {
        return error.trim();
    }
    if (error && typeof error.message === 'string' && error.message.trim() !== '') {
        return error.message.trim();
    }
    if (error && typeof error.error === 'string' && error.error.trim() !== '') {
        return error.error.trim();
    }
    return fallback || 'Operazione non riuscita.';
}

function callGameModule(moduleName, method, payload, onSuccess, onError) {
    if (typeof resolveModule !== 'function') {
        if (typeof onError === 'function') {
            onError(new Error('Module resolver not available: ' + moduleName + '.' + method));
        }
        return false;
    }

    var mod = resolveModule(moduleName);
    if (!mod || typeof mod[method] !== 'function') {
        if (typeof onError === 'function') {
            onError(new Error('Module method not available: ' + moduleName + '.' + method));
        }
        return false;
    }

    mod[method](payload || {}).then(function (response) {
        if (typeof onSuccess === 'function') {
            onSuccess(response);
        }
    }).catch(function (error) {
        if (typeof onError === 'function') {
            onError(error);
        }
    });

    return true;
}

function normalizeNewsRows(response) {
    if (!response) {
        return [];
    }

    if (Array.isArray(response.dataset)) {
        return response.dataset;
    }

    if (response.dataset && Array.isArray(response.dataset.rows)) {
        return response.dataset.rows;
    }

    if (Array.isArray(response.rows)) {
        return response.rows;
    }

    return [];
}

function GameNewsPage(extension) {
        let widget = {
            dataset: [],
            type: null,

            init: function() {
                this.get();

                return this;
            },
            renderMessage: function(message) {
                let block = $('#news-modal-body').empty();
                $('<div class="text-muted text-center"></div>').text(message).appendTo(block);
            },
            showLoading: function() {
                this.renderMessage('Caricamento...');
            },
            showEmpty: function() {
                this.renderMessage('Non ci sono news al momento.');
            },
            get: function() {
                var self = this;
                self.showLoading();
                let payload = {
                    cache: true,
                    cache_ttl: 120
                };
                if (self.type !== null && typeof self.type !== 'undefined') {
                    payload.query = {
                        type: self.type
                    };
                }
                callGameModule('game.news', 'list', payload, function (response) {
                    self.dataset = normalizeNewsRows(response);
                    self.build();
                }, function (error) {
                    self.dataset = [];
                    self.showEmpty();
                    Toast.show({
                        body: normalizeNewsError(error, 'Errore durante caricamento news'),
                        type: 'error'
                    });
                });
            },
            build: function() {
                let block = $('#news-modal-body').empty();
                let rows = Array.isArray(this.dataset) ? this.dataset : [];
                if (rows.length === 0) {
                    this.showEmpty();
                    return;
                }

                let templateHtml = $('template[name="template_news_list"]').html();
                if (typeof templateHtml !== 'string' || templateHtml.trim() === '') {
                    this.showEmpty();
                    return;
                }

                for (var i = 0; i < rows.length; i += 1) {
                    let row = rows[i] || {};
                    let template = $(templateHtml);
                    let image = (row.image && row.image !== '') ? row.image : '/assets/imgs/defaults-images/default-location.png';
                    let date = row.date_published || row.date_publish || row.date_created;
                    let dateLabel = '';
                    if (date) {
                        dateLabel = (typeof Dates === 'function')
                            ? ('Pubblicato il: ' + Dates().formatHumanDateTime(date))
                            : ('Pubblicato il: ' + date);
                    }

                    template.find('[name="image"]').attr('src', image);
                    template.find('[name="title"]').text(row.title || '');
                    template.find('[name="date_create"]').html(dateLabel);
                    template.find('[name="body"]').html(row.body || '');

                    if (row.is_pinned && parseInt(row.is_pinned, 10) === 1) {
                        template.find('[name="title"]').prepend('<span class="badge text-bg-dark me-2">In evidenza</span>');
                    }

                    block.append(template);
                    if (i < rows.length - 1) {
                        block.append('<hr class="w-25 mx-auto" />');
                    }
                }
            }
        };

        let news = Object.assign({}, widget, extension);
        return news.init();
    }

function GameAvailabilityObserverPage(extension) {
        let observer = {
            idleMinutes: 20,
            pollIntervalMs: 30000,
            activityThrottleMs: 60000,
            lastActivityKey: 'availability_last_activity',
            lastSyncKey: 'availability_last_sync',
            autoIdleKey: 'availability_auto_idle',
            pollKey: 'availability.observer',
            pollTimer: null,
            isBound: false,
            _offConfigChanged: null,
            _configChangedEventName: 'config:changed',
            activityHandler: null,
            visibilityHandler: null,
            init: function () {
                if (!Storage().get('characterId')) {
                    return this;
                }
                this.idleMinutes = this.resolveIdleMinutes();

                this.bindConfigEvents();
                this.bind();
                this.bootstrap();
                this.start();

                return this;
            },
            resolveIdleMinutes: function () {
                if (typeof ConfigStore === 'function') {
                    let cfgValue = ConfigStore().getInt('availability_idle_minutes', this.idleMinutes);
                    if (!isNaN(cfgValue) && cfgValue > 0) {
                        return cfgValue;
                    }
                }
                if (typeof globalWindow.APP_CONFIG !== 'undefined' && globalWindow.APP_CONFIG.availability_idle_minutes) {
                    let value = parseInt(globalWindow.APP_CONFIG.availability_idle_minutes, 10);
                    if (!isNaN(value) && value > 0) {
                        return value;
                    }
                }
                return this.idleMinutes;
            },
            getEventBus: function () {
                if (typeof globalWindow.AppEvents !== 'undefined' && globalWindow.AppEvents && typeof globalWindow.AppEvents.on === 'function') {
                    return globalWindow.AppEvents;
                }
                if (typeof globalWindow.EventBus === 'function') {
                    return globalWindow.EventBus();
                }
                return null;
            },
            isRelevantConfigChange: function (payload) {
                if (!payload || typeof payload !== 'object') {
                    return false;
                }

                var change = payload.change;
                if (change && (change.key === 'availability_idle_minutes' || change.path === 'availability_idle_minutes' || change.path === 'feature.availability.idle_minutes')) {
                    return true;
                }

                if (!Array.isArray(payload.changes)) {
                    return false;
                }

                for (var i = 0; i < payload.changes.length; i++) {
                    var item = payload.changes[i];
                    if (!item || typeof item !== 'object') {
                        continue;
                    }
                    if (item.key === 'availability_idle_minutes' || item.path === 'availability_idle_minutes' || item.path === 'feature.availability.idle_minutes') {
                        return true;
                    }
                }

                return false;
            },
            bindConfigEvents: function () {
                this.unbindConfigEvents();

                var bus = this.getEventBus();
                if (!bus || typeof bus.on !== 'function') {
                    return this;
                }

                var self = this;
                this._offConfigChanged = bus.on(this._configChangedEventName, function (payload) {
                    if (!self.isRelevantConfigChange(payload)) {
                        return;
                    }
                    self.idleMinutes = self.resolveIdleMinutes();
                });

                return this;
            },
            unbindConfigEvents: function () {
                if (typeof this._offConfigChanged === 'function') {
                    this._offConfigChanged();
                }
                this._offConfigChanged = null;
                return this;
            },
            bootstrap: function () {
                if (Storage().get(this.lastActivityKey) === null) {
                    Storage().set(this.lastActivityKey, Date.now());
                }
            },
            bind: function () {
                if (this.isBound === true) {
                    return;
                }

                var self = this;
                this.activityHandler = function () {
                    self.onActivity();
                };
                ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
                    globalWindow.addEventListener(evt, self.activityHandler, { passive: true });
                });
                this.visibilityHandler = function () {
                    if (!document.hidden) {
                        self.onActivity();
                    }
                };
                document.addEventListener('visibilitychange', this.visibilityHandler);
                globalWindow.addEventListener('focus', this.activityHandler);
                this.isBound = true;
            },
            unbind: function () {
                if (this.isBound !== true) {
                    return;
                }

                var self = this;
                ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
                    if (self.activityHandler) {
                        globalWindow.removeEventListener(evt, self.activityHandler);
                    }
                });
                if (this.visibilityHandler) {
                    document.removeEventListener('visibilitychange', this.visibilityHandler);
                }
                if (this.activityHandler) {
                    globalWindow.removeEventListener('focus', this.activityHandler);
                }

                this.activityHandler = null;
                this.visibilityHandler = null;
                this.isBound = false;
            },
            start: function () {
                var self = this;
                this.stop();

                if (typeof PollManager === 'function') {
                    this.pollTimer = PollManager().start(this.pollKey, function () {
                        self.checkIdle();
                    }, this.pollIntervalMs);
                    return;
                }
                this.pollTimer = setInterval(function () {
                    self.checkIdle();
                }, this.pollIntervalMs);
            },
            stop: function () {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
                if (typeof PollManager === 'function') {
                    PollManager().stop(this.pollKey);
                }
            },
            onActivity: function () {
                var now = Date.now();
                Storage().set(this.lastActivityKey, now);

                let current = Storage().get('characterAvailability');
                if (current === null || current === undefined || current === '') {
                    return;
                }

                let autoIdle = Storage().get(this.autoIdleKey);
                if (autoIdle && parseInt(current, 10) === 2) {
                    Storage().unset(this.autoIdleKey);
                    this.setAvailability(1);
                    return;
                }

                if (parseInt(current, 10) === 1) {
                    let lastSync = Storage().get(this.lastSyncKey);
                    if (!lastSync || (now - lastSync) > this.activityThrottleMs) {
                        this.setAvailability(1, true);
                    }
                }
            },
            checkIdle: function () {
                let current = Storage().get('characterAvailability');
                if (current === null || current === undefined || current === '') {
                    return;
                }
                if (parseInt(current, 10) !== 1) {
                    return;
                }

                let last = Storage().get(this.lastActivityKey);
                if (!last) {
                    Storage().set(this.lastActivityKey, Date.now());
                    return;
                }

                let idleMs = this.idleMinutes * 60 * 1000;
                if ((Date.now() - last) >= idleMs) {
                    Storage().set(this.autoIdleKey, true);
                    this.setAvailability(2);
                }
            },
            setAvailability: function (value, silent) {
                let current = Storage().get('characterAvailability');
                let lastSync = Storage().get(this.lastSyncKey);
                if (current !== null && current !== undefined && current !== '') {
                    if (parseInt(current, 10) === parseInt(value, 10) && lastSync && (Date.now() - lastSync) < this.activityThrottleMs) {
                        return;
                    }
                }
                Storage().set('characterAvailability', value);
                Storage().set(this.lastSyncKey, Date.now());

                if (
                    typeof globalWindow.AvailabilityNavControl !== 'undefined'
                    && globalWindow.AvailabilityNavControl
                    && globalWindow.AvailabilityNavControl.input
                    && typeof globalWindow.AvailabilityNavControl.input.val === 'function'
                ) {
                    globalWindow.AvailabilityNavControl.input.val(value).change();
                }
                if (typeof globalWindow.updateAvailabilityIndicator === 'function') {
                    globalWindow.updateAvailabilityIndicator(value);
                }

                var payload = { availability: value };
                callGameModule('game.presence', 'setAvailability', payload, function () {}, function (error) {
                    if (silent === true) {
                        console.warn('[AvailabilityObserver] sync failed', error);
                    }
                });
            },
            destroy: function () {
                this.stop();
                this.unbindConfigEvents();
                this.unbind();
                return this;
            },
            unmount: function () {
                return this.destroy();
            }
        };

        let ctrl = Object.assign({}, observer, extension);
        return ctrl.init();
    }
globalWindow.GameNewsPage = GameNewsPage;
globalWindow.GameAvailabilityObserverPage = GameAvailabilityObserverPage;
export { GameNewsPage as GameNewsPage };
export { GameAvailabilityObserverPage as GameAvailabilityObserverPage };
export default GameAvailabilityObserverPage;

