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

function toNumber(value, fallback) {
    var n = parseFloat(value);
    if (isNaN(n)) {
        return fallback;
    }
    return n;
}

function toInt(value, fallback) {
    var n = parseInt(value, 10);
    if (isNaN(n)) {
        return fallback;
    }
    return n;
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function formatNumber(value, decimals) {
    var n = toNumber(value, NaN);
    if (isNaN(n)) {
        return '-';
    }

    var precision = (typeof decimals === 'number') ? decimals : 0;
    try {
        return new Intl.NumberFormat('it-IT', {
            minimumFractionDigits: precision,
            maximumFractionDigits: precision,
        }).format(n);
    } catch (error) {
        return String(n);
    }
}

function normalizePhasePayload(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    if (data.phase && typeof data.phase === 'object') {
        return data;
    }

    var phaseId = parseInt(data.phase_id || data.to_phase_id || '0', 10) || 0;
    var phaseName = String(data.phase_name || data.to_phase_name || data.name || '').trim();
    var phaseCode = String(data.phase_code || data.to_phase_code || data.code || '').trim();
    var phaseDescription = String(data.phase_description || '').trim();

    if (phaseId <= 0 && phaseName === '' && phaseCode === '' && phaseDescription === '') {
        return data;
    }

    return Object.assign({}, data, {
        phase: {
            id: phaseId > 0 ? phaseId : null,
            code: phaseCode,
            name: phaseName,
            description: phaseDescription,
        },
    });
}

function GameHomePage(extension) {
    var page = {
        root: null,
        characterId: 0,
        profileModule: null,
        lifecycleModule: null,
        inventoryModule: null,

        init: function () {
            this.root = document.querySelector('#page-content [data-home-dashboard]');
            if (!this.root) {
                return this;
            }

            this.characterId = toInt(this.root.getAttribute('data-character-id') || '0', 0);
            this.profileModule = resolveModule('game.profile');
            this.lifecycleModule = resolveModule('game.lifecycle');
            this.inventoryModule = resolveModule('game.inventory');

            this.loadAll();
            return this;
        },

        sync: function () {
            this.loadAll();
            return this;
        },

        loadAll: function () {
            this.loadProfileSummary();
            this.loadLifecycleSummary();
            this.loadInventorySummary();
        },

        setText: function (role, value) {
            if (!this.root) {
                return;
            }
            var node = this.root.querySelector('[data-role="' + role + '"]');
            if (!node) {
                return;
            }
            node.textContent = String(value == null ? '' : value);
        },

        setBar: function (role, percent) {
            if (!this.root) {
                return;
            }
            var node = this.root.querySelector('[data-role="' + role + '"]');
            if (!node) {
                return;
            }
            var value = clamp(toInt(percent, 0), 0, 100);
            node.style.width = value + '%';
            node.setAttribute('aria-valuenow', String(value));
        },

        loadProfileSummary: function () {
            var self = this;
            if (!this.profileModule || typeof this.profileModule.getProfile !== 'function' || this.characterId <= 0) {
                return;
            }

            this.profileModule.getProfile(this.characterId).then(function (response) {
                var dataset = response && response.dataset ? response.dataset : null;
                self.renderProfileSummary(dataset || {});
            }).catch(function () {});
        },

        renderProfileSummary: function (dataset) {
            var health = toNumber(dataset.health, 0);
            if (health < 0) {
                health = 0;
            }
            var healthMax = toNumber(dataset.health_max || dataset.hp_max || dataset.max_health, 100);
            if (!(healthMax > 0)) {
                healthMax = 100;
            }
            var healthPercent = clamp(Math.round((health / healthMax) * 100), 0, 100);

            var healthState = 'Ottimo';
            if (healthPercent <= 25) {
                healthState = 'Critico';
            } else if (healthPercent <= 50) {
                healthState = 'Ferito';
            } else if (healthPercent <= 80) {
                healthState = 'Stabile';
            }

            this.setBar('home-health-bar', healthPercent);
            this.setText('home-health-label', formatNumber(health, 0) + ' / ' + formatNumber(healthMax, 0));
            this.setText('home-health-state', healthState);

            var totalExperience = toNumber(dataset.experience, 0);
            if (totalExperience < 0) {
                totalExperience = 0;
            }
            var threshold = toNumber(dataset.threshold_next_level, NaN);
            var expCurrent = 0;
            var expMax = 100;
            if (!isNaN(threshold) && threshold > 0) {
                expCurrent = clamp(totalExperience, 0, threshold);
                expMax = threshold;
            } else {
                expCurrent = totalExperience % 100;
            }
            var expPercent = clamp(Math.round((expCurrent / expMax) * 100), 0, 100);
            this.setBar('home-experience-bar', expPercent);
            this.setText('home-experience-label', formatNumber(expCurrent, 0) + ' / ' + formatNumber(expMax, 0));
            this.setText('home-experience-percent', expPercent + '%');

            var rank = toInt(dataset.rank, 1);
            if (rank < 1) {
                rank = 1;
            }

            this.setText('home-rank', String(rank));
            this.setText('home-next-rank', String(rank + 1));

            var currency = '';
            if (dataset.currency_default && typeof dataset.currency_default === 'object') {
                currency = String(dataset.currency_default.code || dataset.currency_default.name || '').trim();
            }
            var currencySuffix = currency !== '' ? (' ' + currency) : '';

            this.setText('home-money', formatNumber(dataset.money, 0) + currencySuffix);
            this.setText('home-bank', formatNumber(dataset.bank, 0) + currencySuffix);

            var guilds = Array.isArray(dataset.guilds) ? dataset.guilds : [];
            var primaryGuild = null;
            for (var i = 0; i < guilds.length; i++) {
                var row = guilds[i] || {};
                if (toInt(row.is_primary, 0) === 1) {
                    primaryGuild = row;
                    break;
                }
            }
            if (!primaryGuild && guilds.length > 0) {
                primaryGuild = guilds[0];
            }

            this.setText('home-primary-guild', primaryGuild ? String(primaryGuild.guild_name || '-') : 'Nessuna');

            var guildRole = '-';
            if (primaryGuild) {
                if (toInt(primaryGuild.is_leader, 0) === 1) {
                    guildRole = 'Capo';
                } else if (toInt(primaryGuild.is_officer, 0) === 1) {
                    guildRole = 'Vice';
                } else if (String(primaryGuild.role_name || '').trim() !== '') {
                    guildRole = String(primaryGuild.role_name || '').trim();
                } else {
                    guildRole = 'Membro';
                }
            }
            this.setText('home-guild-role', guildRole);
        },

        loadLifecycleSummary: function () {
            var self = this;
            if (!this.lifecycleModule || typeof this.lifecycleModule.currentPhase !== 'function') {
                return;
            }

            this.lifecycleModule.currentPhase({}).then(function (response) {
                var raw = response && response.dataset ? response.dataset : null;
                var data = normalizePhasePayload(raw);
                self.renderLifecycleSummary(data || {});
            }).catch(function () {
                self.setText('home-lifecycle-name', '-');
                self.setText('home-lifecycle-code', '-');
                self.setText('home-lifecycle-description', 'Nessuna fase attiva.');
            });
        },

        renderLifecycleSummary: function (data) {
            var phase = data && data.phase ? data.phase : null;
            if (!phase) {
                this.setText('home-lifecycle-name', '-');
                this.setText('home-lifecycle-code', '-');
                this.setText('home-lifecycle-description', 'Nessuna fase attiva.');
                return;
            }

            this.setText('home-lifecycle-name', String(phase.name || '-'));
            this.setText('home-lifecycle-code', String(phase.code || '-'));
            this.setText('home-lifecycle-description', String(phase.description || 'Fase corrente attiva.'));
        },

        loadInventorySummary: function () {
            var self = this;
            if (!this.inventoryModule) {
                return;
            }

            var slotsPromise = (typeof this.inventoryModule.slots === 'function')
                ? this.inventoryModule.slots({})
                : Promise.resolve({});
            var equippedPromise = (typeof this.inventoryModule.equipped === 'function')
                ? this.inventoryModule.equipped({})
                : Promise.resolve({});
            var bagPromise = (typeof this.inventoryModule.bagItems === 'function')
                ? this.inventoryModule.bagItems({ page: 1, results: 1, orderBy: 'item_name|ASC' })
                : Promise.resolve({});

            Promise.allSettled([slotsPromise, equippedPromise, bagPromise]).then(function (results) {
                var slotsRes = (results[0] && results[0].status === 'fulfilled') ? results[0].value : {};
                var equippedRes = (results[1] && results[1].status === 'fulfilled') ? results[1].value : {};
                var bagRes = (results[2] && results[2].status === 'fulfilled') ? results[2].value : {};

                var slots = Array.isArray(slotsRes && slotsRes.slots) ? slotsRes.slots : [];
                var equipped = Array.isArray(equippedRes && equippedRes.items) ? equippedRes.items : [];

                var capacity = (bagRes && bagRes.capacity && typeof bagRes.capacity === 'object') ? bagRes.capacity : {};
                var used = toInt(capacity.used, 0);
                var max = toInt(capacity.max, 0);
                var free = toInt(capacity.free, (max > 0 ? Math.max(0, max - used) : 0));

                var totalItems = 0;
                if (bagRes && bagRes.properties && bagRes.properties.tot && typeof bagRes.properties.tot.count !== 'undefined') {
                    totalItems = toInt(bagRes.properties.tot.count, 0);
                }

                self.setText('home-inventory-used', used > 0 ? String(used) : '0');
                self.setText('home-inventory-max', max > 0 ? String(max) : '-');
                self.setText('home-inventory-free', max > 0 ? String(free) : '-');
                self.setText('home-inventory-total-items', totalItems > 0 ? String(totalItems) : '0');
                self.setText('home-equipped-items', String(equipped.length));
                self.setText('home-equip-slots-total', slots.length > 0 ? String(slots.length) : '-');
            }).catch(function () {});
        },
    };

    var home = Object.assign({}, page, extension || {});
    return home.init();
}

globalWindow.GameHomePage = GameHomePage;
export { GameHomePage as GameHomePage };
export default GameHomePage;
