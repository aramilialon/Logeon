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

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isValidHexColor(value) {
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(String(value || '').trim());
}

function toInt(value, fallback) {
    var num = parseInt(value, 10);
    if (isNaN(num)) {
        return fallback;
    }
    return num;
}

function buildItemNarrativeBadges(item) {
    var badges = [];
    if (toInt(item && item.usable, 0) === 1) {
        badges.push('<span class="badge text-bg-success">Uso</span>');
    }
    var cooldown = toInt(item && item.cooldown, 0);
    if (cooldown > 0) {
        badges.push('<span class="badge text-bg-secondary">CD ' + escapeHtml(String(cooldown)) + 's</span>');
    }
    var appliesState = String((item && item.applies_state_name) || '').trim();
    var removesState = String((item && item.removes_state_name) || '').trim();
    if (appliesState !== '') {
        badges.push('<span class="badge text-bg-info">Applica: ' + escapeHtml(appliesState) + '</span>');
    }
    if (removesState !== '') {
        badges.push('<span class="badge text-bg-warning">Rimuove: ' + escapeHtml(removesState) + '</span>');
    }
    return badges.join(' ');
}

function getInventoryErrorInfo(error, fallback) {
    var fb = fallback || 'Operazione non riuscita.';
    if (globalWindow.GameFeatureError && typeof globalWindow.GameFeatureError.info === 'function') {
        return globalWindow.GameFeatureError.info(error, fb);
    }
    if (globalWindow.GameFeatureError && typeof globalWindow.GameFeatureError.normalize === 'function') {
        return {
            message: globalWindow.GameFeatureError.normalize(error, fb),
            errorCode: '',
            raw: error
        };
    }
    if (globalWindow.Request && typeof globalWindow.Request.getErrorInfo === 'function') {
        return globalWindow.Request.getErrorInfo(error, fb);
    }
    if (globalWindow.Request && typeof globalWindow.Request.getErrorMessage === 'function') {
        return {
            message: globalWindow.Request.getErrorMessage(error, fb),
            errorCode: '',
            raw: error
        };
    }
    if (typeof error === 'string' && error.trim() !== '') {
        return { message: error.trim(), errorCode: '', raw: error };
    }
    if (error && typeof error.message === 'string' && error.message.trim() !== '') {
        return { message: error.message.trim(), errorCode: '', raw: error };
    }
    return { message: fb, errorCode: '', raw: error };
}

function showInventoryError(error, fallback) {
    if (globalWindow.GameFeatureError && typeof globalWindow.GameFeatureError.toastMapped === 'function') {
        return globalWindow.GameFeatureError.toastMapped(error, fallback, {
            map: {
                character_invalid: 'Personaggio non valido.',
                item_invalid: 'Oggetto non valido.',
                item_not_found: 'Oggetto non trovato.',
                item_not_equippable: 'Questo oggetto non puo essere equipaggiato.',
                item_not_usable: 'Questo oggetto non puo essere usato.',
                item_cooldown_active: 'Oggetto in cooldown: attendi prima di riutilizzarlo.',
                ammo_required: 'L\'arma da tiro non ha una munizione configurata.',
                ammo_not_enough: 'Munizioni insufficienti per usare questa arma.',
                ammo_reload_required: 'Arma scarica: ricarica prima di usarla.',
                ammo_reload_not_supported: 'Ricarica non supportata per questo oggetto.',
                ammo_reload_disabled_in_narrative: 'Ricarica disponibile solo con conflitto casuale attivo.',
                ammo_magazine_full: 'Caricatore gia pieno.',
                item_needs_maintenance: 'Questo equipaggiamento richiede manutenzione.',
                item_jammed: 'L\'arma si e inceppata.',
                item_maintenance_not_supported: 'Manutenzione non disponibile per questo oggetto.',
                item_equipped: 'Questo oggetto risulta gia equipaggiato.',
                item_is_equipped: 'Non puoi recapitare o distruggere un oggetto equipaggiato.',
                item_not_sellable: 'Questo oggetto non e vendibile.',
                slot_required: 'Devi selezionare uno slot.',
                slot_invalid: 'Lo slot selezionato non e valido.',
                slot_unavailable: 'Lo slot selezionato non e disponibile.',
                slot_group_limit_reached: 'Hai raggiunto il limite equipaggiabile per questo gruppo di slot.',
                equipment_requirement_not_met: 'Requisiti di equipaggiamento non soddisfatti.',
                equipment_schema_missing: 'Schema equipaggiamento mancante: applica le patch database della fase equipment.',
                swap_source_empty: 'Nessun oggetto equipaggiato nello slot di origine.',
                swap_target_incompatible: 'L\'oggetto selezionato non e compatibile con lo slot destinazione.',
                swap_source_incompatible: 'L\'oggetto nello slot destinazione non e compatibile con lo slot origine.',
                quantity_invalid: 'Quantita non valida.',
                quantity_unavailable: 'Quantita non disponibile.',
                recipient_invalid: 'Destinatario non valido.',
                recipient_not_found: 'Destinatario non trovato.',
                recipient_same_character: 'Non puoi recapitare un oggetto a te stesso.',
                sell_price_invalid: 'Prezzo di vendita non valido.',
                inventory_capacity_reached: 'Inventario pieno: libera spazio prima di aggiungere nuovi oggetti.',
                inventory_stack_limit_reached: 'Hai raggiunto la quantita massima trasportabile per questo oggetto.'
            },
            validationCodes: [
                'character_invalid',
                'item_invalid',
                'item_not_found',
                'item_not_equippable',
                'item_not_usable',
                'item_cooldown_active',
                'ammo_required',
                'ammo_not_enough',
                'ammo_reload_required',
                'ammo_reload_not_supported',
                'ammo_reload_disabled_in_narrative',
                'ammo_magazine_full',
                'item_needs_maintenance',
                'item_jammed',
                'item_maintenance_not_supported',
                'item_equipped',
                'item_is_equipped',
                'item_not_sellable',
                'slot_required',
                'slot_invalid',
                'slot_unavailable',
                'slot_group_limit_reached',
                'equipment_requirement_not_met',
                'equipment_schema_missing',
                'swap_source_empty',
                'swap_target_incompatible',
                'swap_source_incompatible',
                'quantity_invalid',
                'quantity_unavailable',
                'recipient_invalid',
                'recipient_not_found',
                'recipient_same_character',
                'sell_price_invalid',
                'inventory_capacity_reached',
                'inventory_stack_limit_reached'
            ],
            validationType: 'warning',
            defaultType: 'error',
            preferServerMessageCodes: ['equipment_requirement_not_met']
        });
    }

    var errorInfo = getInventoryErrorInfo(error, fallback);
    Toast.show({
        body: errorInfo.message || fallback || 'Operazione non riuscita.',
        type: 'error'
    });
    return errorInfo;
}


function GameBagPage(char_id, extension) {
        let page = {
            dataset: null,
            dg_threads: {},
            dg_bag_config: null,
            slots: [],
            slotIndex: {},
            capacity: null,
            selected_item_key: '',
            inventoryModule: null,
            getInventoryModule: function () {
                if (this.inventoryModule) {
                    return this.inventoryModule;
                }
                if (typeof resolveModule !== 'function') {
                    return null;
                }

                this.inventoryModule = resolveModule('game.inventory');
                return this.inventoryModule;
            },
            callInventory: function (method, payload, action, onSuccess, onError) {
                var mod = this.getInventoryModule();
                var fn = String(method || '').trim();
                if (!mod || fn === '' || typeof mod[fn] !== 'function') {
                    if (typeof onError === 'function') {
                        onError(new Error('Inventory module not available: ' + fn));
                    }
                    return false;
                }

                var request = null;
                if (typeof action === 'string' && action.trim() !== '') {
                    request = mod[fn](payload, action);
                } else {
                    request = mod[fn](payload);
                }

                Promise.resolve(request).then(function (response) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(response);
                    }
                }).catch(function (error) {
                    if (typeof onError === 'function') {
                        onError(error);
                    }
                });

                return true;
            },
            getBagDatagridConfig: function () {
                let mod = this.getInventoryModule();
                if (mod && typeof mod.bagGridConfig === 'function') {
                    try {
                        return mod.bagGridConfig();
                    } catch (e) {
                        console.warn('[Bag] module bagGridConfig failed:', e);
                    }
                }
                return null;
            },
            init: function () {
                if (null == char_id) {
                    Dialog('danger', {title: 'Errore', body: '<p>Riferimento al personaggio mancante.</p>'}).show();

                    return;
                }

                var self = this;
                this.bindReorganize();
                this.loadSlots(function () {
                    self.get();
                });
                return this;
            },
            sync: function () {
                this.init();
            },
            get: function () {
                var self = this;
                this.callInventory('getBag', { id: char_id }, null, function (response) {
                    self.dataset = response.dataset;

                    if (null != self.dataset) {
                        self.build();
                    }
                }, function (error) {
                    showInventoryError(error, 'Impossibile caricare la borsa.');
                });
            },
            build: function () {
                this.buildDatagrid();
            },
            legacySlots: function () {
                return [
                    { key: 'amulet', name: 'Ciondolo', group_key: 'amulet', sort_order: 10 },
                    { key: 'helm', name: 'Elmo', group_key: 'helm', sort_order: 20 },
                    { key: 'weapon_1', name: 'Arma 1', group_key: 'weapon', sort_order: 30 },
                    { key: 'gloves', name: 'Guanti', group_key: 'gloves', sort_order: 40 },
                    { key: 'armor', name: 'Armatura', group_key: 'armor', sort_order: 50 },
                    { key: 'weapon_2', name: 'Arma 2', group_key: 'weapon', sort_order: 60 },
                    { key: 'ring_1', name: 'Anello 1', group_key: 'ring', sort_order: 70 },
                    { key: 'boots', name: 'Stivali', group_key: 'boots', sort_order: 80 },
                    { key: 'ring_2', name: 'Anello 2', group_key: 'ring', sort_order: 90 }
                ];
            },
            normalizeSlots: function (rows, useLegacyFallback) {
                var source = Array.isArray(rows) ? rows : [];
                if (!source.length && useLegacyFallback === true) {
                    source = this.legacySlots();
                }

                var normalized = [];
                for (var i = 0; i < source.length; i++) {
                    var slot = source[i] || {};
                    var key = (slot.key || '').toString().trim();
                    if (!key) {
                        continue;
                    }

                    var sortOrder = parseInt(slot.sort_order, 10);
                    if (isNaN(sortOrder)) {
                        sortOrder = 9999;
                    }

                    normalized.push({
                        id: slot.id || 0,
                        key: key,
                        name: (slot.name || key).toString(),
                        group_key: (slot.group_key || key).toString(),
                        sort_order: sortOrder
                    });
                }

                normalized.sort(function (a, b) {
                    if (a.sort_order !== b.sort_order) {
                        return a.sort_order - b.sort_order;
                    }
                    return a.key.localeCompare(b.key);
                });

                return normalized;
            },
            rebuildSlotIndex: function () {
                this.slotIndex = {};
                for (var i = 0; i < this.slots.length; i++) {
                    var slot = this.slots[i];
                    this.slotIndex[slot.key] = slot;
                }
            },
            parseCsv: function (value) {
                var raw = (value || '').toString().trim();
                if (!raw) {
                    return [];
                }

                var chunks = raw.split(',');
                var out = [];
                for (var i = 0; i < chunks.length; i++) {
                    var part = (chunks[i] || '').toString().trim();
                    if (!part) {
                        continue;
                    }
                    if (out.indexOf(part) === -1) {
                        out.push(part);
                    }
                }
                return out;
            },
            loadSlots: function (onComplete) {
                var self = this;
                this.callInventory('slots', null, null, function (response) {
                    self.slots = self.normalizeSlots((response && response.slots) ? response.slots : [], true);
                    self.rebuildSlotIndex();
                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                }, function () {
                    self.slots = self.normalizeSlots([], true);
                    self.rebuildSlotIndex();
                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                });
            },
            bindReorganize: function () {
                var self = this;
                let scope = $('#bag-page');
                if (!scope.length) {
                    return;
                }

                scope.off('click', '[data-action="bag-reorganize"]');
                scope.on('click', '[data-action="bag-reorganize"]', function (e) {
                    e.preventDefault();
                    self.reorganizeBag();
                });
            },
            buildDatagrid: function () {
                var self = this;
                this.dg_bag_config = this.getBagDatagridConfig();
                if (!this.dg_bag_config) {
                    console.warn('[Bag] datagrid config not available.');
                    return;
                }

                if (!this.dg_bag_config.nav) {
                    this.dg_bag_config.nav = {};
                }
                if (typeof this.dg_bag_config.nav.results === 'undefined') {
                    this.dg_bag_config.nav.results = 10;
                }

                this.dg_bag = new Datagrid('grid-bag', this.dg_bag_config);
                this.dg_bag.onGetDataSuccess = function (response) {
                    if (self.syncBagResults(response)) {
                        return;
                    }
                    self.capacity = (response && response.capacity) ? response.capacity : null;
                    self.updateCapacitySummary();
                    self.reorganizeBag();
                    self.renderBagEmptySlots();
                    self.bindBagSelection();
                    self.syncBagSelection();
                    self.bindDropActions();
                    self.bindDestroyActions();
                    self.bindUseActions();
                    self.bindTransferActions();
                };
                this.applyFilters();
            },
            buildOrderBy: function () {
                return 'item_name|ASC';
            },
            reorganizeBag: function () {
                if (!this.dg_bag || !Array.isArray(this.dg_bag.dataset) || !this.dg_bag.dataset.length) {
                    return;
                }

                let grid = $('#grid-bag');
                let tbody = grid.find('tbody');
                if (!tbody.length) {
                    return;
                }

                let rows = this.dg_bag.dataset.slice();
                rows.sort(function (left, right) {
                    let leftEquipped = toInt(left && left.is_equipped, 0);
                    let rightEquipped = toInt(right && right.is_equipped, 0);
                    if (leftEquipped !== rightEquipped) {
                        return rightEquipped - leftEquipped;
                    }

                    let leftUsable = toInt(left && left.usable, 0);
                    let rightUsable = toInt(right && right.usable, 0);
                    if (leftUsable !== rightUsable) {
                        return rightUsable - leftUsable;
                    }

                    let leftRarity = toInt(left && left.rarity_sort_order, 0);
                    let rightRarity = toInt(right && right.rarity_sort_order, 0);
                    if (leftRarity !== rightRarity) {
                        return rightRarity - leftRarity;
                    }

                    let leftQty = Math.max(1, toInt(left && left.quantity, 1));
                    let rightQty = Math.max(1, toInt(right && right.quantity, 1));
                    if (leftQty !== rightQty) {
                        return rightQty - leftQty;
                    }

                    let leftName = String(left && left.item_name ? left.item_name : '').toLocaleLowerCase('it-IT');
                    let rightName = String(right && right.item_name ? right.item_name : '').toLocaleLowerCase('it-IT');
                    if (leftName < rightName) {
                        return -1;
                    }
                    if (leftName > rightName) {
                        return 1;
                    }
                    return 0;
                });

                this.dg_bag.dataset = rows;

                let rowMap = {};
                let unknownRows = [];
                tbody.children('tr').each(function () {
                    let row = $(this);
                    let key = (row.find('[data-bag-item-key]').first().data('bag-item-key') || '').toString();
                    if (key) {
                        rowMap[key] = this;
                    } else {
                        unknownRows.push(this);
                    }
                });

                let fragment = document.createDocumentFragment();
                for (let i = 0; i < rows.length; i++) {
                    let key = this.bagItemKey(rows[i]);
                    if (key && rowMap[key]) {
                        fragment.appendChild(rowMap[key]);
                    }
                }
                for (let j = 0; j < unknownRows.length; j++) {
                    fragment.appendChild(unknownRows[j]);
                }

                tbody[0].appendChild(fragment);
            },
            bagItemKey: function (row) {
                if (!row) {
                    return '';
                }

                let instanceId = parseInt(row.character_item_instance_id, 10);
                let stackId = parseInt(row.character_item_id, 10);
                if (instanceId > 0) {
                    return 'instance-' + instanceId;
                }
                return stackId > 0 ? ('stack-' + stackId) : '';
            },
            findBagItemByKey: function (key) {
                let target = String(key || '').trim();
                if (!target || !this.dg_bag || !Array.isArray(this.dg_bag.dataset)) {
                    return null;
                }

                for (let i = 0; i < this.dg_bag.dataset.length; i++) {
                    let row = this.dg_bag.dataset[i];
                    if (this.bagItemKey(row) === target) {
                        return row;
                    }
                }

                return null;
            },
            slotLabel: function (slot) {
                var slotKey = (slot || '').toString().trim();
                if (slotKey && this.slotIndex[slotKey] && this.slotIndex[slotKey].name) {
                    return this.slotIndex[slotKey].name;
                }
                return slotKey || 'Slot';
            },
            getEquipSlots: function (equipSlot) {
                var slot = (equipSlot || '').toString().trim();
                if (slot === '') {
                    return [];
                }

                if (this.slotIndex[slot]) {
                    return [slot];
                }

                var byGroup = [];
                for (var i = 0; i < this.slots.length; i++) {
                    if (this.slots[i].group_key === slot) {
                        byGroup.push(this.slots[i].key);
                    }
                }
                if (byGroup.length) {
                    return byGroup;
                }

                if (slot === 'weapon') {
                    return ['weapon_1', 'weapon_2'];
                }
                if (slot === 'ring') {
                    return ['ring_1', 'ring_2'];
                }
                return [slot];
            },
            syncBagSelection: function () {
                let activeKey = this.selected_item_key;
                let selected = this.findBagItemByKey(activeKey);

                if (!selected) {
                    activeKey = '';
                }

                this.selected_item_key = activeKey || '';
                this.renderBagDetail();
                this.updateSelectedBagCard();
            },
            updateSelectedBagCard: function () {
                let grid = $('#grid-bag');
                if (!grid.length) {
                    return;
                }

                grid.find('[data-bag-item-key]').removeClass('is-selected').attr('aria-pressed', 'false');
                if (!this.selected_item_key) {
                    return;
                }

                grid.find('[data-bag-item-key="' + this.selected_item_key + '"]').addClass('is-selected').attr('aria-pressed', 'true');
            },
            bindBagSelection: function () {
                let grid = $('#grid-bag');
                if (!grid.length) {
                    return;
                }

                let self = this;
                grid.off('click', '[data-bag-item-key]');
                grid.on('click', '[data-bag-item-key]', function (e) {
                    if ($(e.target).closest('button,a,input,select,textarea,label').length) {
                        return;
                    }
                    self.selected_item_key = ($(this).data('bag-item-key') || '').toString();
                    self.renderBagDetail();
                    self.updateSelectedBagCard();
                });

                grid.off('keydown', '[data-bag-item-key]');
                grid.on('keydown', '[data-bag-item-key]', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') {
                        return;
                    }
                    e.preventDefault();
                    self.selected_item_key = ($(this).data('bag-item-key') || '').toString();
                    self.renderBagDetail();
                    self.updateSelectedBagCard();
                });
            },
            renderBagDetail: function () {
                let panel = $('[data-role="bag-detail-panel"]');
                if (!panel.length) {
                    return;
                }

                let row = this.findBagItemByKey(this.selected_item_key);
                if (!row) {
                    panel.html('<p class="text-muted small mb-0">Seleziona uno slot dell\'inventario per vedere dettagli e azioni disponibili.</p>');
                    if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
                        document.dispatchEvent(new CustomEvent('game:bag-detail:rendered', {
                            detail: {
                                item: null,
                                panel: panel[0] || null
                            }
                        }));
                    }
                    return;
                }

                let image = (row.item_image && row.item_image !== '') ? row.item_image : '/assets/imgs/defaults-images/default-location.png';
                let name = escapeHtml(row.item_name || 'Senza nome');
                let description = escapeHtml(row.item_description || 'Nessuna descrizione disponibile.');
                let qty = (row.quantity != null) ? parseInt(row.quantity, 10) : 1;
                let rarityName = String(row.rarity_name || '').trim();
                let rarityColor = String(row.rarity_color || '').trim();
                let metaBits = [];
                let badges = buildItemNarrativeBadges(row);
                let actions = [];
                let equipped = parseInt(row.is_equipped, 10) === 1;
                let usable = parseInt(row.usable, 10) === 1;
                let statusLabel = equipped ? 'Equipaggiato' : 'Disponibile in inventario';
                let interactionLabel = usable ? 'Usabile' : 'Non usabile';

                if (isNaN(qty) || qty < 1) {
                    qty = 1;
                }

                if (rarityName !== '') {
                    if (isValidHexColor(rarityColor)) {
                        metaBits.push('<span class="badge" style="background-color:' + rarityColor + ';border:1px solid ' + rarityColor + ';color:#fff;">' + escapeHtml(rarityName) + '</span>');
                    } else {
                        metaBits.push('<span class="badge text-bg-secondary">' + escapeHtml(rarityName) + '</span>');
                    }
                }
                if (parseInt(row.is_equipped, 10) === 1) {
                    metaBits.push('<span class="badge text-bg-success">Equipaggiato</span>');
                }

                if (parseInt(row.usable, 10) === 1 && parseInt(row.character_item_id, 10) > 0) {
                    actions.push('<button type="button" class="btn btn-sm btn-outline-success" data-action="use-bag" data-inventory-item-id="' + parseInt(row.character_item_id, 10) + '">Usa</button>');
                }
                if (String(row.source || '') === 'instance' && parseInt(row.character_item_instance_id, 10) > 0) {
                    actions.push('<button type="button" class="btn btn-sm btn-outline-primary" data-action="transfer" data-source="instance" data-instance-id="' + parseInt(row.character_item_instance_id, 10) + '" data-item-name="' + name + '">Recapita</button>');
                } else if (parseInt(row.character_item_id, 10) > 0) {
                    actions.push('<button type="button" class="btn btn-sm btn-outline-primary" data-action="transfer" data-source="stack" data-character-item-id="' + parseInt(row.character_item_id, 10) + '" data-quantity="' + qty + '" data-item-name="' + name + '">Recapita</button>');
                }
                if (parseInt(row.droppable, 10) === 1) {
                    if (String(row.source || '') === 'instance' && parseInt(row.character_item_instance_id, 10) > 0) {
                        actions.push('<button type="button" class="btn btn-sm btn-outline-danger" data-action="drop" data-source="instance" data-instance-id="' + parseInt(row.character_item_instance_id, 10) + '" data-item-name="' + name + '">Lascia</button>');
                    } else if (parseInt(row.character_item_id, 10) > 0) {
                        actions.push('<button type="button" class="btn btn-sm btn-outline-danger" data-action="drop" data-source="stack" data-character-item-id="' + parseInt(row.character_item_id, 10) + '" data-quantity="' + qty + '" data-item-name="' + name + '">Lascia</button>');
                    }
                }
                if (String(row.source || '') === 'instance' && parseInt(row.character_item_instance_id, 10) > 0) {
                    actions.push('<button type="button" class="btn btn-sm btn-outline-dark" data-action="destroy" data-source="instance" data-instance-id="' + parseInt(row.character_item_instance_id, 10) + '" data-item-name="' + name + '" data-quantity="1">Distruggi</button>');
                } else if (parseInt(row.character_item_id, 10) > 0) {
                    actions.push('<button type="button" class="btn btn-sm btn-outline-dark" data-action="destroy" data-source="stack" data-character-item-id="' + parseInt(row.character_item_id, 10) + '" data-quantity="' + qty + '" data-item-name="' + name + '">Distruggi</button>');
                }

                panel.html(
                    '<div class="bag-detail">'
                    + '  <div class="bag-detail__eyebrow">Oggetto selezionato</div>'
                    + '  <div class="bag-detail__head">'
                    + '    <img class="bag-detail__image" src="' + image + '" alt="">'
                    + '    <div class="bag-detail__identity">'
                    + '      <div class="bag-detail__name">' + name + '</div>'
                    + '      <div class="bag-detail__meta">' + metaBits.join(' ') + '</div>'
                    + '    </div>'
                    + '  </div>'
                    + '  <div class="bag-detail__facts">'
                    + '    <div class="bag-detail__fact"><span class="bag-detail__fact-label">Quantità</span><span class="bag-detail__fact-value">x' + qty + '</span></div>'
                    + '    <div class="bag-detail__fact"><span class="bag-detail__fact-label">Stato</span><span class="bag-detail__fact-value">' + escapeHtml(statusLabel) + '</span></div>'
                    + '    <div class="bag-detail__fact"><span class="bag-detail__fact-label">Interazione</span><span class="bag-detail__fact-value">' + escapeHtml(interactionLabel) + '</span></div>'
                    + '  </div>'
                    + '  <div class="bag-detail__desc">' + description + '</div>'
                    + (badges !== '' ? '<div class="bag-detail__badges">' + badges + '</div>' : '')
                    + (actions.length ? '<div class="bag-detail__actions">' + actions.join('') + '</div>' : '<div class="bag-detail__empty text-muted small">Nessuna azione disponibile per questo oggetto.</div>')
                    + '</div>'
                );

                if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
                    document.dispatchEvent(new CustomEvent('game:bag-detail:rendered', {
                        detail: {
                            item: row,
                            panel: panel[0] || null
                        }
                    }));
                }
            },
            showEquipDialog: function (instanceId, allowedSlots, itemName, legacyEquipSlot) {
                var self = this;
                var slots = Array.isArray(allowedSlots) ? allowedSlots.slice() : [];
                if (!slots.length) {
                    slots = this.getEquipSlots(legacyEquipSlot);
                }
                if (!slots.length) {
                    Toast.show({ body: 'Slot non disponibile.', type: 'error' });
                    return;
                }
                if (slots.length === 1) {
                    self.equip(instanceId, slots[0]);
                    return;
                }

                var optionHtml = '';
                for (var i = 0; i < slots.length; i++) {
                    optionHtml += '<option value="' + slots[i] + '">' + this.slotLabel(slots[i]) + '</option>';
                }

                var body = '<div class="text-start text-body">';
                body += '<p>Seleziona lo slot per <b>' + itemName + '</b></p>';
                body += '<label class="form-label">Slot</label>';
                body += '<div><select class="form-select" name="equip-slot"><option value="">Seleziona...</option>' + optionHtml + '</select></div>';
                body += '</div>';

                var dialog = Dialog('default', {
                    title: 'Equipaggia',
                    body: body
                }, function () {
                    var confirmModal = getGeneralConfirmModal();
                    if (!confirmModal) {
                        Toast.show({ body: 'Dialog di conferma non disponibile.', type: 'error' });
                        return;
                    }
                    var selected = confirmModal.find('[name="equip-slot"]').val();
                    if (!selected) {
                        Toast.show({ body: 'Seleziona uno slot.', type: 'error' });
                        return;
                    }
                    hideGeneralConfirmDialog();
                    self.equip(instanceId, selected);
                });
                dialog.show();
            },
            equip: function (instanceId, slot) {
                var self = this;
                this.callInventory('equip', {
                    character_item_instance_id: instanceId,
                    slot: slot
                }, null, function () {
                    Toast.show({ body: 'Oggetto equipaggiato.', type: 'success' });
                    if (self.dg_bag) {
                        self.dg_bag.reloadData();
                    }
                    if (globalWindow.Equips && typeof globalWindow.Equips.reload === 'function') {
                        globalWindow.Equips.reload();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante equipaggiamento.');
                });
            },
            isFullInventoryView: function () {
                return true;
            },
            syncBagResults: function (response) {
                if (!this.dg_bag || !this.dg_bag_config || !this.dg_bag_config.nav) {
                    return false;
                }

                let total = 0;
                if (response && response.properties && response.properties.tot && typeof response.properties.tot.count !== 'undefined') {
                    total = parseInt(response.properties.tot.count, 10);
                }
                if (isNaN(total) || total < 1) {
                    total = 1;
                }

                let current = parseInt(this.dg_bag_config.nav.results, 10);
                if (isNaN(current) || current < 1) {
                    current = 10;
                }

                if (current === total) {
                    return false;
                }

                this.dg_bag_config.nav.results = total;
                this.dg_bag.loadData(
                    this.dg_bag_config.nav.query || {},
                    total,
                    1,
                    [
                        this.buildOrderBy(),
                    ]
                );

                return true;
            },
            updateCapacitySummary: function () {
                let block = $('[data-role="bag-capacity-summary"]');
                if (!block.length) {
                    return;
                }

                let count = block.find('.bag-capacity-summary__count');
                let meta = block.find('.bag-capacity-summary__meta');
                let max = this.capacity && typeof this.capacity.max !== 'undefined' ? parseInt(this.capacity.max, 10) : 0;
                let used = this.capacity && typeof this.capacity.used !== 'undefined' ? parseInt(this.capacity.used, 10) : 0;
                let free = this.capacity && typeof this.capacity.free !== 'undefined' ? parseInt(this.capacity.free, 10) : 0;

                if (isNaN(max) || max < 0) {
                    max = 0;
                }
                if (isNaN(used) || used < 0) {
                    used = 0;
                }
                if (isNaN(free) || free < 0) {
                    free = 0;
                }

                count.text(used + ' / ' + max + ' slot');
                meta.text(free + ' liberi');
            },
            renderBagEmptySlots: function () {
                let grid = $('#grid-bag');
                if (!grid.length) {
                    return;
                }

                let tbody = grid.find('tbody');
                if (!tbody.length) {
                    return;
                }

                if (!this.isFullInventoryView()) {
                    return;
                }

                let max = this.capacity && typeof this.capacity.max !== 'undefined' ? parseInt(this.capacity.max, 10) : 0;
                let used = this.capacity && typeof this.capacity.used !== 'undefined' ? parseInt(this.capacity.used, 10) : 0;
                if (isNaN(max) || max < 1) {
                    return;
                }
                if (isNaN(used) || used < 0) {
                    used = 0;
                }

                let emptySlots = max - used;
                if (emptySlots < 0) {
                    emptySlots = 0;
                }

                if (used === 0 && emptySlots > 0) {
                    tbody.empty();
                }

                for (let i = 0; i < emptySlots; i++) {
                    let row = document.createElement('tr');
                    let cell = document.createElement('td');
                    cell.innerHTML = ''
                        + '<div class="bag-card-item bag-card-item--empty" aria-hidden="true">'
                        + '  <div class="bag-card-item__body">'
                        + '    <div class="bag-card-item__media-wrap">'
                        + '      <div class="bag-card-item__image-placeholder">Vuoto</div>'
                        + '    </div>'
                        + '    <div class="bag-card-item__content">'
                        + '      <div class="bag-card-item__topline">'
                        + '        <h6 class="mb-0 bag-card-item__name">Slot libero</h6>'
                        + '        <span class="badge text-bg-secondary bag-card-item__qty">--</span>'
                        + '      </div>'
                        + '      <div class="bag-card-item__badges">'
                        + '        <span class="badge text-bg-secondary">Disponibile</span>'
                        + '      </div>'
                        + '    </div>'
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                    row.appendChild(cell);
                    tbody[0].appendChild(row);
                }
            },
            applyFilters: function () {
                if (!this.dg_bag || !this.dg_bag_config || !this.dg_bag_config.nav) {
                    return;
                }

                let query = {
                    char_id: char_id
                };

                if (this.dg_bag && this.dg_bag.lang) {
                    this.dg_bag.lang.no_results = 'Nessun risultato';
                }

                this.dg_bag_config.nav.query = query;
                this.dg_bag.loadData(
                    this.dg_bag_config.nav.query,
                    this.dg_bag_config.nav.results,
                    1,
                    [
                        this.buildOrderBy(),
                    ]
                );
            },
            searchTransferRecipients: function (query, onSuccess, onError) {
                this.callInventory('charactersSearch', {
                    query: query,
                    include_self: false
                }, null, function (response) {
                    var dataset = (response && Array.isArray(response.dataset)) ? response.dataset : [];
                    if (typeof onSuccess === 'function') {
                        onSuccess(dataset);
                    }
                }, function (error) {
                    if (typeof onError === 'function') {
                        onError(error);
                        return;
                    }
                    showInventoryError(error, 'Impossibile cercare i personaggi.');
                });
            },
            renderTransferRecipientResults: function (modal, dataset) {
                if (!modal || !modal.length) {
                    return;
                }
                var list = modal.find('[data-role="transfer-recipient-results"]');
                if (!list.length) {
                    return;
                }

                var rows = Array.isArray(dataset) ? dataset : [];
                if (!rows.length) {
                    list.html('<div class="small text-muted p-2">Nessun personaggio trovato.</div>');
                    return;
                }

                var html = '<div class="list-group list-group-flush">';
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i] || {};
                    var id = parseInt(row.id, 10);
                    if (!id) {
                        continue;
                    }
                    var fullName = String((row.name || '') + ' ' + (row.surname || '')).trim();
                    if (fullName === '') {
                        fullName = 'Personaggio #' + id;
                    }
                    var avatar = String(row.avatar || '').trim();
                    if (avatar === '') {
                        avatar = '/assets/imgs/defaults-images/default-avatar.png';
                    }
                    html += ''
                        + '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2"'
                        + ' data-action="select-transfer-recipient"'
                        + ' data-recipient-id="' + id + '"'
                        + ' data-recipient-name="' + escapeHtml(fullName) + '">'
                        + '  <img src="' + avatar + '" alt="" width="28" height="28" class="rounded-circle">'
                        + '  <span class="text-truncate">' + escapeHtml(fullName) + '</span>'
                        + '</button>';
                }
                html += '</div>';
                list.html(html);
            },
            bindTransferRecipientSearch: function (modal) {
                if (!modal || !modal.length) {
                    return;
                }

                var self = this;
                var debounceTimer = null;

                modal.off('input.inventoryTransfer', '[name="transfer-recipient-query"]');
                modal.off('click.inventoryTransfer', '[data-action="select-transfer-recipient"]');

                modal.on('input.inventoryTransfer', '[name="transfer-recipient-query"]', function () {
                    var query = ($(this).val() || '').toString().trim();
                    modal.find('[name="transfer-recipient-id"]').val('');
                    modal.find('[name="transfer-recipient-name"]').val('');
                    modal.find('[data-role="transfer-recipient-selected"]').text('Nessun destinatario selezionato.');

                    if (debounceTimer) {
                        clearTimeout(debounceTimer);
                    }

                    if (query.length < 2) {
                        modal.find('[data-role="transfer-recipient-results"]').html('<div class="small text-muted p-2">Scrivi almeno 2 caratteri per cercare.</div>');
                        return;
                    }

                    debounceTimer = setTimeout(function () {
                        self.searchTransferRecipients(query, function (dataset) {
                            self.renderTransferRecipientResults(modal, dataset);
                        }, function (error) {
                            showInventoryError(error, 'Impossibile cercare i personaggi.');
                        });
                    }, 220);
                });

                modal.on('click.inventoryTransfer', '[data-action="select-transfer-recipient"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var recipientId = parseInt(btn.data('recipient-id'), 10);
                    var recipientName = (btn.data('recipient-name') || '').toString().trim();
                    if (!recipientId) {
                        return;
                    }

                    modal.find('[name="transfer-recipient-id"]').val(recipientId);
                    modal.find('[name="transfer-recipient-name"]').val(recipientName);
                    modal.find('[data-role="transfer-recipient-selected"]').text(recipientName || ('Personaggio #' + recipientId));

                    modal.find('[data-action="select-transfer-recipient"]').removeClass('active');
                    btn.addClass('active');
                });
            },
            showTransferDialog: function (options) {
                var self = this;
                var source = (options && options.source ? options.source : '').toString().trim();
                var itemName = (options && options.itemName ? options.itemName : 'Oggetto').toString();
                var instanceId = parseInt(options && options.instanceId ? options.instanceId : 0, 10);
                var characterItemId = parseInt(options && options.characterItemId ? options.characterItemId : 0, 10);
                var maxQty = parseInt(options && options.maxQty ? options.maxQty : 1, 10);
                if (isNaN(maxQty) || maxQty < 1) {
                    maxQty = 1;
                }

                var body = '<div class="text-start text-body">';
                body += '<p class="mb-2">Recapita <b>' + escapeHtml(itemName) + '</b> a un altro personaggio, anche se non e presente in location.</p>';
                if (source === 'stack' && maxQty > 1) {
                    body += '<label class="form-label">Quantita da recapitare</label>';
                    body += '<input type="number" class="form-control mb-2" name="transfer-quantity" min="1" max="' + maxQty + '" value="1">';
                }
                body += '<label class="form-label">Cerca personaggio</label>';
                body += '<input type="text" class="form-control mb-2" name="transfer-recipient-query" placeholder="Nome o cognome">';
                body += '<input type="hidden" name="transfer-recipient-id" value="">';
                body += '<input type="hidden" name="transfer-recipient-name" value="">';
                body += '<div class="small text-muted mb-2" data-role="transfer-recipient-selected">Nessun destinatario selezionato.</div>';
                body += '<div class="border rounded overflow-auto" style="max-height: 220px;" data-role="transfer-recipient-results"><div class="small text-muted p-2">Scrivi almeno 2 caratteri per cercare.</div></div>';
                body += '</div>';

                var dialog = Dialog('default', {
                    title: 'Recapita oggetto',
                    body: body
                }, function () {
                    var confirmModal = getGeneralConfirmModal();
                    if (!confirmModal) {
                        Toast.show({ body: 'Dialog di conferma non disponibile.', type: 'error' });
                        return;
                    }

                    var recipientId = parseInt(confirmModal.find('[name="transfer-recipient-id"]').val(), 10);
                    var recipientName = (confirmModal.find('[name="transfer-recipient-name"]').val() || '').toString().trim();
                    if (!recipientId) {
                        Toast.show({ body: 'Seleziona il destinatario dalla lista.', type: 'warning' });
                        return;
                    }

                    var sendQty = 1;
                    if (source === 'stack' && maxQty > 1) {
                        sendQty = parseInt(confirmModal.find('[name="transfer-quantity"]').val(), 10);
                        if (isNaN(sendQty) || sendQty < 1) {
                            Toast.show({ body: 'Quantita non valida.', type: 'warning' });
                            return;
                        }
                        if (sendQty > maxQty) {
                            sendQty = maxQty;
                        }
                    }

                    var payload = {
                        recipient_character_id: recipientId
                    };
                    if (source === 'instance') {
                        if (!instanceId) {
                            Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                            return;
                        }
                        payload.character_item_instance_id = instanceId;
                    } else {
                        if (!characterItemId) {
                            Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                            return;
                        }
                        payload.character_item_id = characterItemId;
                        payload.quantity = sendQty;
                    }

                    hideGeneralConfirmDialog();
                    self.transferItem(payload, itemName, recipientName, sendQty);
                });

                dialog.show();
                this.bindTransferRecipientSearch(getGeneralConfirmModal());
            },
            bindTransferActions: function () {
                var self = this;
                var scope = $('#bag-page');
                if (!scope.length) {
                    return;
                }

                scope.off('click', '[data-action="transfer"]');
                scope.on('click', '[data-action="transfer"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var source = (btn.data('source') || '').toString().trim();
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    var maxQty = parseInt(btn.data('quantity'), 10);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var characterItemId = parseInt(btn.data('character-item-id'), 10);

                    if (source === 'instance' && !instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    if (source === 'stack' && !characterItemId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }

                    self.showTransferDialog({
                        source: source,
                        itemName: itemName,
                        instanceId: instanceId,
                        characterItemId: characterItemId,
                        maxQty: maxQty
                    });
                });
            },
            transferItem: function (payload, itemName, recipientName, quantity) {
                var self = this;
                this.callInventory('transfer', payload, null, function (response) {
                    var transferredQty = parseInt(response && response.transferred_quantity ? response.transferred_quantity : quantity, 10);
                    if (isNaN(transferredQty) || transferredQty < 1) {
                        transferredQty = 1;
                    }
                    var who = (response && response.recipient_name ? response.recipient_name : recipientName) || 'destinatario';
                    var label = 'Oggetto recapitato';
                    if (itemName) {
                        label += ': ' + itemName;
                    }
                    if (transferredQty > 1) {
                        label += ' x' + transferredQty;
                    }
                    label += ' a ' + who + '.';
                    Toast.show({ body: label, type: 'success' });
                    if (self.dg_bag) {
                        self.dg_bag.reloadData();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante il recapito.');
                });
            },
            bindDropActions: function () {
                var self = this;
                let scope = $('#bag-page');
                if (!scope.length) {
                    return;
                }
                scope.off('click', '[data-action="drop"]');

                scope.on('click', '[data-action="drop"]', function (e) {
                    e.preventDefault();
                    let btn = $(this);
                    let source = (btn.data('source') || '').toString();
                    let itemName = (btn.data('item-name') || 'Oggetto').toString();
                    let characterItemId = parseInt(btn.data('character-item-id'), 10);
                    let instanceId = parseInt(btn.data('instance-id'), 10);
                    let qty = parseInt(btn.data('quantity'), 10);
                    if (isNaN(qty) || qty < 1) {
                        qty = 1;
                    }

                    if (source === 'instance' && !instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    if (source === 'stack' && !characterItemId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }

                    let body = '<div class="text-start text-body">';
                    body += '<p>Vuoi lasciare <b>' + itemName + '</b> nella location?</p>';
                    if (source === 'stack' && qty > 1) {
                        body += '<label class="form-label">Quantita</label>';
                        body += '<input type="number" class="form-control" name="drop-quantity" min="1" max="' + qty + '" value="1">';
                    }
                    body += '</div>';

                    let dialog = Dialog('warning', {
                        title: 'Lascia oggetto',
                        body: body
                    }, function () {
                        let dropQty = 1;
                        if (source === 'stack' && qty > 1) {
                            let confirmModal = getGeneralConfirmModal();
                            if (!confirmModal) {
                                Toast.show({ body: 'Dialog di conferma non disponibile.', type: 'error' });
                                return;
                            }
                            dropQty = parseInt(confirmModal.find('[name="drop-quantity"]').val(), 10);
                            if (isNaN(dropQty) || dropQty < 1) {
                                Toast.show({ body: 'Quantita non valida.', type: 'error' });
                                return;
                            }
                            if (dropQty > qty) {
                                dropQty = qty;
                            }
                        }
                        hideGeneralConfirmDialog();
                        if (source === 'instance') {
                            self.drop({
                                character_item_instance_id: instanceId
                            });
                        } else {
                            self.drop({
                                character_item_id: characterItemId,
                                quantity: dropQty
                            });
                        }
                    });
                    dialog.show();
                });
            },
            drop: function (payload) {
                var self = this;
                this.callInventory('drop', payload, null, function () {
                    Toast.show({ body: 'Oggetto lasciato.', type: 'success' });
                    if (self.dg_bag) {
                        self.dg_bag.reloadData();
                    }
                    if (globalWindow.LocationDrops && typeof globalWindow.LocationDrops.reload === 'function') {
                        globalWindow.LocationDrops.reload();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante il rilascio.');
                });
            },
            bindDestroyActions: function () {
                var self = this;
                let scope = $('#bag-page');
                if (!scope.length) { return; }
                scope.off('click', '[data-action="destroy"]');
                scope.on('click', '[data-action="destroy"]', function (e) {
                    e.preventDefault();
                    let btn = $(this);
                    let source = (btn.data('source') || '').toString();
                    let itemName = (btn.data('item-name') || 'Oggetto').toString();
                    let characterItemId = parseInt(btn.data('character-item-id'), 10);
                    let instanceId = parseInt(btn.data('instance-id'), 10);
                    let qty = parseInt(btn.data('quantity'), 10);
                    if (isNaN(qty) || qty < 1) { qty = 1; }

                    let body = '<div class="text-start text-body">';
                    body += '<p class="text-danger fw-bold mb-1">Operazione irreversibile!</p>';
                    body += '<p>Distruggere definitivamente <b>' + itemName + '</b>?</p>';
                    if (source === 'stack' && qty > 1) {
                        body += '<label class="form-label">Quantità da distruggere</label>';
                        body += '<input type="number" class="form-control" name="destroy-quantity" min="1" max="' + qty + '" value="1">';
                    }
                    body += '</div>';

                    let dialog = Dialog('danger', { title: 'Distruggi oggetto', body: body }, function () {
                        let destroyQty = 1;
                        if (source === 'stack' && qty > 1) {
                            let confirmModal = getGeneralConfirmModal();
                            if (!confirmModal) { return; }
                            destroyQty = parseInt(confirmModal.find('[name="destroy-quantity"]').val(), 10);
                            if (isNaN(destroyQty) || destroyQty < 1) {
                                Toast.show({ body: 'Quantità non valida.', type: 'error' });
                                return;
                            }
                            if (destroyQty > qty) { destroyQty = qty; }
                        }
                        hideGeneralConfirmDialog();
                        if (source === 'instance') {
                            self.destroyItem({ character_item_instance_id: instanceId });
                        } else {
                            self.destroyItem({ character_item_id: characterItemId, quantity: destroyQty });
                        }
                    });
                    dialog.show();
                });
            },
            destroyItem: function (payload) {
                var self = this;
                this.callInventory('destroy', payload, null, function () {
                    Toast.show({ body: 'Oggetto distrutto.', type: 'success' });
                    if (self.dg_bag) { self.dg_bag.reloadData(); }
                }, function (error) {
                    showInventoryError(error, 'Errore durante la distruzione.');
                });
            },
            useItem: function (payload) {
                var self = this;
                this.callInventory('useItem', payload, null, function () {
                    Toast.show({ body: 'Oggetto usato.', type: 'success' });
                    if (self.dg_bag) {
                        self.dg_bag.reloadData();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante l\'uso dell\'oggetto.');
                });
            },
            bindUseActions: function () {
                var self = this;
                let scope = $('#bag-page');
                if (!scope.length) {
                    return;
                }

                scope.off('click', '[data-action="use-bag"]');
                scope.on('click', '[data-action="use-bag"]', function (e) {
                    e.preventDefault();
                    let inventoryItemId = parseInt($(this).data('inventory-item-id'), 10);
                    if (!inventoryItemId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.useItem({
                        inventory_item_id: inventoryItemId
                    });
                });
            }
        };

        let bag = Object.assign({}, page, extension);
        return bag.init();
    }

function GameEquipsPage(extension) {
        let page = {
            items: [],
            available: [],
            slots: [],
            slotIndex: {},
            groups: [],
            inventoryModule: null,
            getInventoryModule: function () {
                if (this.inventoryModule) {
                    return this.inventoryModule;
                }
                if (typeof resolveModule !== 'function') {
                    return null;
                }

                this.inventoryModule = resolveModule('game.inventory');
                return this.inventoryModule;
            },
            callInventory: function (method, payload, action, onSuccess, onError) {
                var mod = this.getInventoryModule();
                var fn = String(method || '').trim();
                if (!mod || fn === '' || typeof mod[fn] !== 'function') {
                    if (typeof onError === 'function') {
                        onError(new Error('Inventory module not available: ' + fn));
                    }
                    return false;
                }

                var request = null;
                if (typeof action === 'string' && action.trim() !== '') {
                    request = mod[fn](payload, action);
                } else {
                    request = mod[fn](payload);
                }

                Promise.resolve(request).then(function (response) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(response);
                    }
                }).catch(function (error) {
                    if (typeof onError === 'function') {
                        onError(error);
                    }
                });

                return true;
            },
            init: function () {
                if (!$('#equips-page').length) {
                    return this;
                }
                this.load();
                return this;
            },
            reload: function () {
                this.load();
            },
            legacySlots: function () {
                return [
                    { key: 'amulet', name: 'Ciondolo', group_key: 'amulet', sort_order: 10 },
                    { key: 'helm', name: 'Elmo', group_key: 'helm', sort_order: 20 },
                    { key: 'weapon_1', name: 'Arma 1', group_key: 'weapon', sort_order: 30 },
                    { key: 'gloves', name: 'Guanti', group_key: 'gloves', sort_order: 40 },
                    { key: 'armor', name: 'Armatura', group_key: 'armor', sort_order: 50 },
                    { key: 'weapon_2', name: 'Arma 2', group_key: 'weapon', sort_order: 60 },
                    { key: 'ring_1', name: 'Anello 1', group_key: 'ring', sort_order: 70 },
                    { key: 'boots', name: 'Stivali', group_key: 'boots', sort_order: 80 },
                    { key: 'ring_2', name: 'Anello 2', group_key: 'ring', sort_order: 90 }
                ];
            },
            normalizeSlots: function (rows, useLegacyFallback) {
                var source = Array.isArray(rows) ? rows : [];
                if (!source.length && useLegacyFallback === true) {
                    source = this.legacySlots();
                }

                var normalized = [];
                for (var i = 0; i < source.length; i++) {
                    var slot = source[i] || {};
                    var key = (slot.key || '').toString().trim();
                    if (!key) {
                        continue;
                    }

                    var sortOrder = parseInt(slot.sort_order, 10);
                    if (isNaN(sortOrder)) {
                        sortOrder = 9999;
                    }

                    normalized.push({
                        id: slot.id || 0,
                        key: key,
                        name: (slot.name || key).toString(),
                        group_key: (slot.group_key || key).toString(),
                        sort_order: sortOrder
                    });
                }

                normalized.sort(function (a, b) {
                    if (a.sort_order !== b.sort_order) {
                        return a.sort_order - b.sort_order;
                    }
                    return a.key.localeCompare(b.key);
                });

                return normalized;
            },
            parseCsv: function (value) {
                var raw = (value || '').toString().trim();
                if (!raw) {
                    return [];
                }

                var chunks = raw.split(',');
                var out = [];
                for (var i = 0; i < chunks.length; i++) {
                    var part = (chunks[i] || '').toString().trim();
                    if (!part) {
                        continue;
                    }
                    if (out.indexOf(part) === -1) {
                        out.push(part);
                    }
                }
                return out;
            },
            rebuildSlotIndex: function () {
                this.slotIndex = {};
                this.groups = [];
                for (var i = 0; i < this.slots.length; i++) {
                    var slot = this.slots[i];
                    this.slotIndex[slot.key] = slot;
                    if (this.groups.indexOf(slot.group_key) === -1) {
                        this.groups.push(slot.group_key);
                    }
                }
            },
            load: function () {
                var self = this;
                this.callInventory('slots', null, null, function (response) {
                    self.slots = self.normalizeSlots((response && response.slots) ? response.slots : [], false);
                    self.rebuildSlotIndex();
                    self.buildSlotSkeleton();
                    self.buildTabsSkeleton();
                    self.loadEquipped();
                }, function () {
                    self.slots = self.normalizeSlots([], false);
                    self.rebuildSlotIndex();
                    self.buildSlotSkeleton();
                    self.buildTabsSkeleton();
                    self.loadEquipped();
                });
            },
            loadEquipped: function () {
                var self = this;
                this.callInventory('equipped', null, null, function (response) {
                    self.items = (response && response.items) ? response.items : [];
                    self.build();
                    self.loadAvailable();
                }, function () {
                    self.items = [];
                    self.build();
                    self.loadAvailable();
                });
            },
            loadAvailable: function () {
                var self = this;
                this.callInventory('available', null, null, function (response) {
                    self.available = (response && response.items) ? response.items : [];
                    self.buildLists();
                }, function () {
                    self.available = [];
                    self.buildLists();
                });
            },
            groupLabel: function (groupKey) {
                var key = (groupKey || '').toString().trim();
                var map = {
                    weapon: 'Armi',
                    ring: 'Anelli',
                    helm: 'Elmi',
                    armor: 'Armature',
                    gloves: 'Guanti',
                    boots: 'Stivali',
                    amulet: 'Ciondoli',
                    accessory: 'Accessori'
                };

                if (map[key]) {
                    return map[key];
                }

                if (!key) {
                    return 'Altro';
                }

                return key.charAt(0).toUpperCase() + key.slice(1);
            },
            buildSlotSkeleton: function () {
                var grid = $('[data-role="equip-slots-grid"]');
                if (!grid.length) {
                    return;
                }

                grid.empty();
                if (!this.slots.length) {
                    grid.html('<div class="text-muted small">Nessuno slot disponibile.</div>');
                    return;
                }

                for (var i = 0; i < this.slots.length; i++) {
                    var slot = this.slots[i];
                    grid.append('<div data-equip-slot="' + slot.key + '"></div>');
                }
            },
            buildTabsSkeleton: function () {
                var tabs = $('[data-role="equip-tabs"]');
                var content = $('[data-role="equip-tab-content"]');
                if (!tabs.length || !content.length) {
                    return;
                }

                tabs.empty();
                content.empty();

                var groups = this.groups.slice();
                if (!groups.length) {
                    groups = ['equipment'];
                }

                for (var i = 0; i < groups.length; i++) {
                    var groupKey = groups[i];
                    var safeGroup = groupKey.replace(/[^a-zA-Z0-9_-]/g, '-');
                    var tabId = 'equips-group-' + safeGroup + '-tab';
                    var paneId = 'equips-group-' + safeGroup + '-pane';
                    var activeClass = (i === 0) ? ' active' : '';
                    var activeSelected = (i === 0) ? 'true' : 'false';
                    var activePane = (i === 0) ? ' show active' : '';

                    tabs.append(
                        '<li class="nav-item" role="presentation">'
                        + '<button class="nav-link' + activeClass + '" id="' + tabId + '" data-bs-toggle="tab" data-bs-target="#' + paneId + '" type="button" role="tab" aria-controls="' + paneId + '" aria-selected="' + activeSelected + '">'
                        + this.groupLabel(groupKey)
                        + '</button>'
                        + '</li>'
                    );

                    content.append(
                        '<div class="tab-pane fade' + activePane + '" id="' + paneId + '" role="tabpanel" aria-labelledby="' + tabId + '" tabindex="0">'
                        + '  <div class="table-responsive">'
                        + '    <table class="table table-striped table-hover align-middle mb-0">'
                        + '      <thead>'
                        + '        <tr>'
                        + '          <th style="width: 120px;">Oggetto</th>'
                        + '          <th>Nome</th>'
                        + '          <th style="width: 140px;">Azione</th>'
                        + '        </tr>'
                        + '      </thead>'
                        + '      <tbody data-equip-list="' + groupKey + '"></tbody>'
                        + '    </table>'
                        + '  </div>'
                        + '</div>'
                    );
                }
            },
            slotLabel: function (slot) {
                var slotKey = (slot || '').toString().trim();
                if (slotKey && this.slotIndex[slotKey] && this.slotIndex[slotKey].name) {
                    return this.slotIndex[slotKey].name;
                }
                return slotKey || 'Slot';
            },
            slotGroupByKey: function (slotKey) {
                var key = (slotKey || '').toString().trim();
                if (!key) {
                    return '';
                }
                if (this.slotIndex[key] && this.slotIndex[key].group_key) {
                    return this.slotIndex[key].group_key;
                }
                return '';
            },
            mapLegacyEquipSlotToGroup: function (equipSlot) {
                var slot = (equipSlot || '').toString().trim();
                if (!slot) {
                    return '';
                }
                if (slot === 'weapon' || slot === 'ring') {
                    return slot;
                }
                var fromKey = this.slotGroupByKey(slot);
                if (fromKey) {
                    return fromKey;
                }
                return slot;
            },
            getEquipSlots: function (equipSlot) {
                var slot = (equipSlot || '').toString().trim();
                if (slot === '') {
                    return [];
                }

                if (this.slotIndex[slot]) {
                    return [slot];
                }

                var byGroup = [];
                for (var i = 0; i < this.slots.length; i++) {
                    if (this.slots[i].group_key === slot) {
                        byGroup.push(this.slots[i].key);
                    }
                }
                if (byGroup.length) {
                    return byGroup;
                }

                if (slot === 'weapon') {
                    return ['weapon_1', 'weapon_2'];
                }
                if (slot === 'ring') {
                    return ['ring_1', 'ring_2'];
                }
                return [slot];
            },
            chooseTargetGroupForItem: function (item, listsByGroup) {
                var allowedGroups = this.parseCsv(item && item.allowed_slot_groups);
                var allowedSlots = this.parseCsv(item && item.allowed_slot_keys);

                if (!allowedGroups.length && allowedSlots.length) {
                    for (var i = 0; i < allowedSlots.length; i++) {
                        var g = this.slotGroupByKey(allowedSlots[i]);
                        if (g && allowedGroups.indexOf(g) === -1) {
                            allowedGroups.push(g);
                        }
                    }
                }

                if (!allowedGroups.length) {
                    var fallbackGroup = this.mapLegacyEquipSlotToGroup(item && item.equip_slot);
                    if (fallbackGroup) {
                        allowedGroups.push(fallbackGroup);
                    }
                }

                for (var j = 0; j < allowedGroups.length; j++) {
                    if (listsByGroup[allowedGroups[j]]) {
                        return allowedGroups[j];
                    }
                }

                var keys = Object.keys(listsByGroup);
                return keys.length ? keys[0] : '';
            },
            build: function () {
                if ($('#equips-slots').length) {
                    this.buildGridSlots();
                    return;
                }
                this.buildSlotMap();
            },
            buildLists: function () {
                var lists = $('[data-equip-list]');
                if (!lists.length) {
                    return;
                }

                var listsByGroup = {};
                lists.each(function () {
                    var body = $(this);
                    var groupKey = (body.data('equip-list') || '').toString();
                    body.empty();
                    if (groupKey) {
                        listsByGroup[groupKey] = body;
                    }
                });

                if (!this.available || this.available.length === 0) {
                    lists.each(function () {
                        $(this).html('<tr><td colspan="3" class="text-muted">Nessun oggetto disponibile.</td></tr>');
                    });
                    return;
                }

                for (var i = 0; i < this.available.length; i++) {
                    var item = this.available[i] || {};
                    var targetGroup = this.chooseTargetGroupForItem(item, listsByGroup);
                    if (!targetGroup || !listsByGroup[targetGroup]) {
                        continue;
                    }

                    var allowedSlots = this.parseCsv(item.allowed_slot_keys);
                    if (!allowedSlots.length) {
                        allowedSlots = this.getEquipSlots(item.equip_slot || '');
                    }

                    var image = (item.image && item.image !== '') ? item.image : '/assets/imgs/defaults-images/default-location.png';
                    var name = item.name || 'Senza nome';
                    var safeName = name.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    var allowedSlotsAttr = allowedSlots.join(',');
                    var narrativeBadges = buildItemNarrativeBadges(item);
                    var qualityInfo = this.getQualityInfo(item);
                    var detailHtml = '<div>' + escapeHtml(name) + '</div>';
                    if (narrativeBadges !== '') {
                        detailHtml += '<div class="d-flex flex-wrap gap-1 mt-1">' + narrativeBadges + '</div>';
                    }
                    if (qualityInfo && qualityInfo.label) {
                        detailHtml += '<div class="d-flex flex-wrap gap-1 mt-1"><span class="badge ' + qualityInfo.badgeClass + '">' + escapeHtml(qualityInfo.label) + '</span></div>';
                    }

                    var row = ''
                        + '<tr>'
                        + '  <td><img class="img-fluid" width="120" src="' + image + '" alt=""></td>'
                        + '  <td>' + detailHtml + '</td>'
                        + '  <td class="equip-list-actions">'
                        + '    <button type="button" class="btn btn-sm btn-outline-success equip-list-action-btn equip-action-btn" style="display:inline-block;width:auto;height:auto;" data-action="equip" data-instance-id="' + item.character_item_instance_id + '" data-equip-slot="' + (item.equip_slot || '') + '" data-allowed-slots="' + allowedSlotsAttr + '" data-item-name="' + safeName + '">Equipaggia</button>'
                        + (qualityInfo && qualityInfo.canMaintain
                            ? '    <button type="button" class="btn btn-sm btn-outline-primary ms-1" data-action="maintain-item" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + safeName + '">Manut.</button>'
                            : '')
                        + '    <button type="button" class="btn btn-sm btn-outline-dark ms-1" data-action="destroy-equip" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + safeName + '">Distruggi</button>'
                        + '  </td>'
                        + '</tr>';
                    listsByGroup[targetGroup].append(row);
                }

                lists.each(function () {
                    var body = $(this);
                    if (!body.children().length) {
                        body.html('<tr><td colspan="3" class="text-muted">Nessun oggetto disponibile per questa categoria.</td></tr>');
                    }
                });

                this.bindEquipListActions();
            },
            bindEquipListActions: function () {
                var self = this;
                var block = $('#equips-page');
                block.off('click', '[data-action="equip"]');
                block.on('click', '[data-action="equip"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var equipSlot = (btn.data('equip-slot') || '').toString();
                    var allowedSlots = self.parseCsv(btn.data('allowed-slots'));
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.showEquipDialog(instanceId, allowedSlots, itemName, equipSlot);
                });
                block.off('click', '[data-action="destroy-equip"]');
                block.on('click', '[data-action="destroy-equip"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    var body = '<div class="text-start text-body">';
                    body += '<p class="text-danger fw-bold mb-1">Operazione irreversibile!</p>';
                    body += '<p>Distruggere definitivamente <b>' + itemName + '</b>?</p>';
                    body += '</div>';
                    var dialog = Dialog('danger', { title: 'Distruggi oggetto', body: body }, function () {
                        hideGeneralConfirmDialog();
                        self.destroyEquipItem({ character_item_instance_id: instanceId });
                    });
                    dialog.show();
                });
                block.off('click', '[data-action="maintain-item"]');
                block.on('click', '[data-action="maintain-item"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.maintainItem(instanceId, itemName);
                });
            },
            destroyEquipItem: function (payload) {
                var self = this;
                this.callInventory('destroy', payload, null, function () {
                    Toast.show({ body: 'Oggetto distrutto.', type: 'success' });
                    self.loadAvailable();
                }, function (error) {
                    showInventoryError(error, 'Errore durante la distruzione.');
                });
            },
            getEquipSlotLabel: function (slot) {
                return this.slotLabel(slot);
            },
            showEquipDialog: function (instanceId, allowedSlots, itemName, legacyEquipSlot) {
                var self = this;
                var slots = Array.isArray(allowedSlots) ? allowedSlots.slice() : [];
                if (!slots.length) {
                    slots = this.getEquipSlots(legacyEquipSlot);
                }
                if (!slots.length) {
                    Toast.show({ body: 'Slot non disponibile.', type: 'error' });
                    return;
                }
                if (slots.length === 1) {
                    self.equip(instanceId, slots[0]);
                    return;
                }

                var optionHtml = '';
                for (var i = 0; i < slots.length; i++) {
                    optionHtml += '<option value="' + slots[i] + '">' + this.getEquipSlotLabel(slots[i]) + '</option>';
                }

                var body = '<div class="text-start text-body">';
                body += '<p>Seleziona lo slot per <b>' + itemName + '</b></p>';
                body += '<label class="form-label">Slot</label>';
                body += '<div><select class="form-select" name="equip-slot"><option value="">Seleziona...</option>' + optionHtml + '</select></div>';
                body += '</div>';

                var dialog = Dialog('default', {
                    title: 'Equipaggia',
                    body: body
                }, function () {
                    var confirmModal = getGeneralConfirmModal();
                    if (!confirmModal) {
                        Toast.show({ body: 'Dialog di conferma non disponibile.', type: 'error' });
                        return;
                    }
                    var selected = confirmModal.find('[name="equip-slot"]').val();
                    if (!selected) {
                        Toast.show({ body: 'Seleziona uno slot.', type: 'error' });
                        return;
                    }
                    hideGeneralConfirmDialog();
                    self.equip(instanceId, selected);
                });
                dialog.show();
            },
            equip: function (instanceId, slot) {
                var self = this;
                this.callInventory('equip', {
                    character_item_instance_id: instanceId,
                    slot: slot
                }, null, function () {
                    Toast.show({ body: 'Oggetto equipaggiato.', type: 'success' });
                    self.reload();
                    if (globalWindow.Bag && typeof globalWindow.Bag.sync === 'function') {
                        globalWindow.Bag.sync();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante equipaggiamento.');
                });
            },
            getSwapTargets: function (currentSlot, item) {
                var slotKey = (currentSlot || '').toString().trim();
                if (!slotKey) {
                    return [];
                }

                var allowed = this.parseCsv(item && item.allowed_slot_keys);
                if (!allowed.length) {
                    allowed = this.getEquipSlots(item && item.equip_slot ? item.equip_slot : slotKey);
                }

                var targets = [];
                for (var i = 0; i < allowed.length; i++) {
                    var target = (allowed[i] || '').toString().trim();
                    if (!target || target === slotKey) {
                        continue;
                    }
                    if (targets.indexOf(target) === -1) {
                        targets.push(target);
                    }
                }

                if (!targets.length) {
                    var groupKey = this.slotGroupByKey(slotKey);
                    if (groupKey) {
                        for (var s = 0; s < this.slots.length; s++) {
                            var slotRow = this.slots[s] || {};
                            if ((slotRow.group_key || '') === groupKey && slotRow.key !== slotKey) {
                                targets.push(slotRow.key);
                            }
                        }
                    }
                }

                return targets;
            },
            getAmmoInfo: function (item) {
                if (!item || toInt(item.requires_ammo, 0) !== 1) {
                    return null;
                }

                var ammoName = String(item.ammo_item_name || 'Munizioni');
                var ammoPerUse = toInt(item.ammo_per_use, 1);
                if (ammoPerUse < 1) {
                    ammoPerUse = 1;
                }
                var magazineSize = toInt(item.ammo_magazine_size, 0);
                if (magazineSize < 0) {
                    magazineSize = 0;
                }
                var loaded = toInt(item.ammo_loaded, 0);
                if (loaded < 0) {
                    loaded = 0;
                }
                if (magazineSize > 0 && loaded > magazineSize) {
                    loaded = magazineSize;
                }

                if (magazineSize > 0) {
                    return {
                        label: 'Caricatore ' + loaded + '/' + magazineSize + ' - ' + ammoName + ' (x' + ammoPerUse + ')',
                        canReload: loaded < magazineSize
                    };
                }

                return {
                    label: 'Munizioni dirette - ' + ammoName + ' (x' + ammoPerUse + ')',
                    canReload: false
                };
            },
            getQualityInfo: function (item) {
                if (!item || toInt(item.quality_enabled, 0) !== 1) {
                    return null;
                }

                var current = toInt(item.quality_current, 0);
                var max = toInt(item.quality_max, 100);
                if (max < 1) {
                    max = 100;
                }
                if (current < 0) {
                    current = 0;
                }
                if (current > max) {
                    current = max;
                }
                var percent = toInt(item.quality_percent, 0);
                if (percent < 0 || percent > 100) {
                    percent = Math.round((current / max) * 100);
                }
                if (percent < 0) {
                    percent = 0;
                }
                if (percent > 100) {
                    percent = 100;
                }

                var badgeClass = 'text-bg-success';
                if (percent <= 25) {
                    badgeClass = 'text-bg-danger';
                } else if (percent <= 50) {
                    badgeClass = 'text-bg-warning';
                } else if (percent <= 75) {
                    badgeClass = 'text-bg-info';
                }

                return {
                    label: 'Qualita ' + current + '/' + max + ' (' + percent + '%)',
                    badgeClass: badgeClass,
                    canMaintain: current < max,
                    needsMaintenance: toInt(item.needs_maintenance, 0) === 1 || current <= 0
                };
            },
            reloadAmmo: function (instanceId, itemName) {
                var self = this;
                this.callInventory('reloadItem', {
                    character_item_instance_id: instanceId
                }, null, function (response) {
                    var reloaded = toInt(response && response.ammo ? response.ammo.reloaded : 0, 0);
                    var body = reloaded > 0
                        ? ('Ricarica completata: +' + reloaded + ' colpi (' + itemName + ').')
                        : ('Ricarica completata (' + itemName + ').');
                    Toast.show({ body: body, type: 'success' });
                    self.reload();
                    if (globalWindow.Bag && typeof globalWindow.Bag.sync === 'function') {
                        globalWindow.Bag.sync();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante la ricarica.');
                });
            },
            maintainItem: function (instanceId, itemName) {
                var self = this;
                this.callInventory('maintainItem', {
                    character_item_instance_id: instanceId
                }, null, function (response) {
                    var qualityAfter = response && response.quality && response.quality.after ? response.quality.after : null;
                    var label = qualityAfter
                        ? ('Manutenzione completata: ' + qualityAfter.quality_current + '/' + qualityAfter.quality_max + ' (' + itemName + ').')
                        : ('Manutenzione completata (' + itemName + ').');
                    Toast.show({ body: label, type: 'success' });
                    self.reload();
                    if (globalWindow.Bag && typeof globalWindow.Bag.sync === 'function') {
                        globalWindow.Bag.sync();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante la manutenzione.');
                });
            },
            showSwapDialog: function (fromSlot, itemName, targets) {
                var self = this;
                var slot = (fromSlot || '').toString().trim();
                if (!slot) {
                    Toast.show({ body: 'Slot non valido.', type: 'error' });
                    return;
                }
                if (!targets || !targets.length) {
                    Toast.show({ body: 'Nessuno slot compatibile disponibile per lo scambio.', type: 'warning' });
                    return;
                }

                var options = '';
                for (var i = 0; i < targets.length; i++) {
                    var t = targets[i];
                    options += '<option value="' + escapeHtml(t) + '">' + escapeHtml(this.getEquipSlotLabel(t)) + '</option>';
                }

                var body = '<div class="text-start text-body">';
                body += '<p>Sposta <b>' + escapeHtml(itemName || 'Oggetto') + '</b> in un altro slot.</p>';
                body += '<label class="form-label">Slot destinazione</label>';
                body += '<div><select class="form-select" name="swap-slot-target"><option value="">Seleziona...</option>' + options + '</select></div>';
                body += '</div>';

                var dialog = Dialog('default', {
                    title: 'Sposta equip',
                    body: body
                }, function () {
                    var confirmModal = getGeneralConfirmModal();
                    if (!confirmModal) {
                        Toast.show({ body: 'Dialog di conferma non disponibile.', type: 'error' });
                        return;
                    }
                    var targetSlot = (confirmModal.find('[name="swap-slot-target"]').val() || '').toString().trim();
                    if (!targetSlot) {
                        Toast.show({ body: 'Seleziona uno slot destinazione.', type: 'error' });
                        return;
                    }
                    hideGeneralConfirmDialog();
                    self.swapSlots(slot, targetSlot);
                });

                dialog.show();
            },
            swapSlots: function (fromSlot, toSlot) {
                var self = this;
                this.callInventory('swap', {
                    from_slot: fromSlot,
                    to_slot: toSlot
                }, null, function () {
                    Toast.show({ body: 'Equipaggiamento aggiornato.', type: 'success' });
                    self.reload();
                    if (globalWindow.Bag && typeof globalWindow.Bag.sync === 'function') {
                        globalWindow.Bag.sync();
                    }
                }, function (error) {
                    showInventoryError(error, 'Errore durante scambio slot.');
                });
            },
            buildGridSlots: function () {
                var block = $('#equips-slots').empty();
                if (!block.length) {
                    return;
                }

                var map = {};
                for (var i = 0; i < this.items.length; i++) {
                    var equipped = this.items[i];
                    if (equipped && equipped.slot) {
                        map[equipped.slot] = equipped;
                    }
                }

                for (var s = 0; s < this.slots.length; s++) {
                    var slot = this.slots[s];
                    var key = slot.key;
                    var item = map[key] || null;
                    var label = this.slotLabel(key);
                    var image = (item && item.image) ? item.image : '/assets/imgs/defaults-images/default-location.png';
                    var name = (item && item.name) ? item.name : 'Vuoto';
                    var swapTargets = this.getSwapTargets(key, item);
                    var swapTargetsAttr = swapTargets.join(',');
                    var hasSwapTargets = swapTargets.length > 0;
                    var narrativeBadges = item ? buildItemNarrativeBadges(item) : '';
                    var narrativeBlock = '';
                    var ammoInfo = item ? this.getAmmoInfo(item) : null;
                    var qualityInfo = item ? this.getQualityInfo(item) : null;
                    if (narrativeBadges !== '') {
                        narrativeBlock = '<div class="d-flex flex-wrap gap-1 mt-1">' + narrativeBadges + '</div>';
                    }
                    if (ammoInfo && ammoInfo.label) {
                        narrativeBlock += '<div class="d-flex flex-wrap gap-1 mt-1"><span class="badge text-bg-dark">' + escapeHtml(ammoInfo.label) + '</span></div>';
                    }
                    if (qualityInfo && qualityInfo.label) {
                        narrativeBlock += '<div class="d-flex flex-wrap gap-1 mt-1"><span class="badge ' + qualityInfo.badgeClass + '">' + escapeHtml(qualityInfo.label) + '</span></div>';
                    }
                    var body = ''
                        + '<div class="card h-100 border-secondary">'
                        + '  <div class="card-body d-flex gap-3 align-items-center">'
                        + '    <img class="rounded" width="48" height="48" src="' + image + '" alt="">'
                        + '    <div class="flex-grow-1">'
                        + '      <div class="fw-semibold">' + escapeHtml(label) + '</div>'
                        + '      <div class="text-muted small">' + escapeHtml(name) + '</div>'
                        +        narrativeBlock
                        + '    </div>';
                    if (item && item.character_item_instance_id) {
                        body += '    <div>';
                        if (hasSwapTargets) {
                            body += '      <button type="button" class="btn btn-sm btn-outline-info me-1" data-action="swap-slot" data-slot="' + key + '" data-item-name="' + escapeHtml(name) + '" data-allowed-slots="' + swapTargetsAttr + '">Sposta</button>';
                        }
                        if (ammoInfo && ammoInfo.canReload) {
                            body += '      <button type="button" class="btn btn-sm btn-outline-primary me-1" data-action="reload-ammo" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + escapeHtml(name) + '">Ricarica</button>';
                        }
                        if (qualityInfo && qualityInfo.canMaintain) {
                            body += '      <button type="button" class="btn btn-sm btn-outline-primary me-1" data-action="maintain-item" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + escapeHtml(name) + '">Manut.</button>';
                        }
                        body += '      <button type="button" class="btn btn-sm btn-warning" data-action="unequip" data-instance-id="' + item.character_item_instance_id + '">Rimuovi</button>'
                            + '    </div>';
                    }
                    body += '  </div>'
                        + '</div>';

                    var col = $('<div class="col-12 col-md-6 col-lg-4"></div>');
                    col.html(body);
                    col.appendTo(block);
                }

                this.bindActions('#equips-slots');
            },
            buildSlotMap: function () {
                var slots = $('[data-equip-slot]');
                if (!slots.length) {
                    this.buildSlotSkeleton();
                    slots = $('[data-equip-slot]');
                }
                if (!slots.length) {
                    return;
                }

                var map = {};
                for (var i = 0; i < this.items.length; i++) {
                    var item = this.items[i];
                    if (item && item.slot) {
                        map[item.slot] = item;
                    }
                }

                var self = this;
                slots.each(function () {
                    var el = $(this);
                    var slot = (el.data('equip-slot') || '').toString();
                    var item = map[slot] || null;
                    var label = self.slotLabel(slot);
                    var image = (item && item.image) ? item.image : '/assets/imgs/defaults-images/default-location.png';
                    var name = (item && item.name) ? item.name : 'Vuoto';
                    var swapTargets = self.getSwapTargets(slot, item);
                    var swapTargetsAttr = swapTargets.join(',');
                    var hasSwapTargets = swapTargets.length > 0;
                    var narrativeBadges = item ? buildItemNarrativeBadges(item) : '';
                    var ammoInfo = item ? self.getAmmoInfo(item) : null;
                    var qualityInfo = item ? self.getQualityInfo(item) : null;
                    var narrativeRow = '';
                    if (narrativeBadges !== '') {
                        narrativeRow = '<div class="equip-slot-narrative mt-1 d-flex flex-wrap gap-1">' + narrativeBadges + '</div>';
                    }
                    if (ammoInfo && ammoInfo.label) {
                        narrativeRow += '<div class="equip-slot-narrative mt-1 d-flex flex-wrap gap-1"><span class="badge text-bg-dark">' + escapeHtml(ammoInfo.label) + '</span></div>';
                    }
                    if (qualityInfo && qualityInfo.label) {
                        narrativeRow += '<div class="equip-slot-narrative mt-1 d-flex flex-wrap gap-1"><span class="badge ' + qualityInfo.badgeClass + '">' + escapeHtml(qualityInfo.label) + '</span></div>';
                    }
                    var html = ''
                        + '<div class="equip-slot-box' + ((item && item.character_item_instance_id) ? ' is-filled' : '') + '">'
                        + '  <div class="equip-slot-label">' + escapeHtml(label) + '</div>';
                    if (item && item.character_item_instance_id) {
                        html += '  <img class="equip-slot-image" src="' + image + '" alt="">'
                            + '  <div class="equip-slot-name">' + escapeHtml(name) + narrativeRow + '</div>';
                        if (hasSwapTargets) {
                            html += '  <button type="button" class="btn btn-sm btn-outline-info equip-slot-swap me-2" data-action="swap-slot" data-slot="' + slot + '" data-item-name="' + escapeHtml(name) + '" data-allowed-slots="' + swapTargetsAttr + '">Sposta</button>';
                        }
                        if (ammoInfo && ammoInfo.canReload) {
                            html += '  <button type="button" class="btn btn-sm btn-outline-primary equip-slot-swap me-2" data-action="reload-ammo" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + escapeHtml(name) + '">Ricarica</button>';
                        }
                        if (qualityInfo && qualityInfo.canMaintain) {
                            html += '  <button type="button" class="btn btn-sm btn-outline-primary equip-slot-swap me-2" data-action="maintain-item" data-instance-id="' + item.character_item_instance_id + '" data-item-name="' + escapeHtml(name) + '">Manut.</button>';
                        }
                        html += '  <button type="button" class="btn btn-sm btn-warning equip-slot-remove" data-action="unequip" data-instance-id="' + item.character_item_instance_id + '">Rimuovi</button>';
                    } else {
                        html += '  <div class="equip-slot-empty">Vuoto</div>';
                    }
                    html += '</div>';
                    el.html(html);
                });

                this.bindActions('#equips-page');
            },
            bindActions: function (scopeSelector) {
                var self = this;
                var block = scopeSelector ? $(scopeSelector) : $('#equips-slots');
                if (!block.length) {
                    block = $('#equips-page');
                }
                block.off('click', '[data-action="unequip"]');
                block.off('click', '[data-action="swap-slot"]');
                block.off('click', '[data-action="reload-ammo"]');
                block.off('click', '[data-action="maintain-item"]');
                block.on('click', '[data-action="unequip"]', function (e) {
                    e.preventDefault();
                    var instanceId = parseInt($(this).data('instance-id'), 10);
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.callInventory('unequip', {
                        character_item_instance_id: instanceId
                    }, null, function () {
                        Toast.show({ body: 'Oggetto rimosso.', type: 'success' });
                        self.reload();
                        if (globalWindow.Bag && typeof globalWindow.Bag.sync === 'function') {
                            globalWindow.Bag.sync();
                        }
                    }, function (error) {
                        showInventoryError(error, 'Errore durante rimozione.');
                    });
                });

                block.on('click', '[data-action="swap-slot"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var fromSlot = (btn.data('slot') || '').toString().trim();
                    if (!fromSlot) {
                        Toast.show({ body: 'Slot non valido.', type: 'error' });
                        return;
                    }
                    var targets = self.parseCsv(btn.data('allowed-slots'));
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    self.showSwapDialog(fromSlot, itemName, targets);
                });

                block.on('click', '[data-action="reload-ammo"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.reloadAmmo(instanceId, itemName);
                });

                block.on('click', '[data-action="maintain-item"]', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var instanceId = parseInt(btn.data('instance-id'), 10);
                    var itemName = (btn.data('item-name') || 'Oggetto').toString();
                    if (!instanceId) {
                        Toast.show({ body: 'Oggetto non valido.', type: 'error' });
                        return;
                    }
                    self.maintainItem(instanceId, itemName);
                });
            }
        };

        let equips = Object.assign({}, page, extension);
        return equips.init();
    }

globalWindow.GameBagPage = GameBagPage;
globalWindow.GameEquipsPage = GameEquipsPage;
export { GameBagPage as GameBagPage };
export { GameEquipsPage as GameEquipsPage };
export default GameEquipsPage;
