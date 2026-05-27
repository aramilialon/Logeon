const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

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

function buildNarrativeBadges(response) {
    var badges = [];
    if (toInt(response.usable, 0) === 1) {
        badges.push('<span class="badge text-bg-success">Uso</span>');
    }
    var cooldown = toInt(response.cooldown, 0);
    if (cooldown > 0) {
        badges.push('<span class="badge text-bg-secondary">CD ' + escapeHtml(String(cooldown)) + 's</span>');
    }
    var appliesState = String(response.applies_state_name || '').trim();
    var removesState = String(response.removes_state_name || '').trim();
    if (appliesState !== '') {
        badges.push('<span class="badge text-bg-info">Applica: ' + escapeHtml(appliesState) + '</span>');
    }
    if (removesState !== '') {
        badges.push('<span class="badge text-bg-warning">Rimuove: ' + escapeHtml(removesState) + '</span>');
    }
    return badges;
}

function buildBagGridConfig() {
    return {
        name: 'Bags',
        autoindex: 'id',
        orderable: false,
        thead: false,
        handler: {
            url: '/list/profile/bag',
            action: 'list'
        },
        nav: {
            display: 'bottom',
            urlupdate: 1,
            results: 10,
            page: 1
        },
        columns: [
            {
                label: '',
                sortable: false,
                style: {
                    textAlign: 'left'
                },
                format: function (response) {
                    var image = (response.item_image && response.item_image !== '') ? response.item_image : '/assets/imgs/defaults-images/default-location.png';
                    var name = response.item_name || 'Senza nome';
                    var qty = (response.quantity != null) ? response.quantity : 1;
                    var equipped = (parseInt(response.is_equipped, 10) === 1) ? '<span class="badge text-bg-success">Equipaggiato</span>' : '';
                    var instanceId = response.character_item_instance_id || null;
                    var stackId = response.character_item_id || null;
                    var rarityName = (response.rarity_name || '').toString().trim();
                    var rarityColor = (response.rarity_color || '').toString().trim();
                    var rarityBadge = '';
                    var narrativeBadges = buildNarrativeBadges(response);
                    var narrativeHtml = '';
                    var itemKey = '';

                    var normalizedInstanceId = parseInt(instanceId, 10);
                    var normalizedStackId = parseInt(stackId, 10);
                    if (!isNaN(normalizedInstanceId) && normalizedInstanceId > 0) {
                        itemKey = 'instance-' + normalizedInstanceId;
                    } else if (!isNaN(normalizedStackId) && normalizedStackId > 0) {
                        itemKey = 'stack-' + normalizedStackId;
                    }

                    if (rarityName !== '') {
                        var badgeStyle = '';
                        if (isValidHexColor(rarityColor)) {
                            badgeStyle = ' style="background-color:' + rarityColor + ';border:1px solid ' + rarityColor + ';color:#fff;"';
                        }
                        rarityBadge = '<span class="badge ms-2"' + badgeStyle + '>' + escapeHtml(rarityName) + '</span>';
                    }
                    if (narrativeBadges.length) {
                        narrativeHtml = '<div class="bag-card-item__narrative">' + narrativeBadges.join(' ') + '</div>';
                    }
                    return ''
                        + '<div class="bag-card-item" data-bag-item-key="' + escapeHtml(itemKey) + '" tabindex="0" role="button" aria-pressed="false">'
                        + '  <div class="bag-card-item__body">'
                        + '    <div class="bag-card-item__media-wrap">'
                        + '      <img class="bag-card-item__image" src="' + image + '" alt="">'
                        + '    </div>'
                        + '    <div class="bag-card-item__content">'
                        + '      <div class="bag-card-item__topline">'
                        + '        <h6 class="mb-0 bag-card-item__name">' + escapeHtml(name) + '</h6>'
                        + '        <span class="badge text-bg-dark bag-card-item__qty">x' + qty + '</span>'
                        + '      </div>'
                        + '      <div class="bag-card-item__badges">'
                        +          equipped
                        +          rarityBadge
                        + '      </div>'
                        +        narrativeHtml
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                }
            }
        ]
    };
}

function createInventoryModule() {
    return {
        ctx: null,
        options: {},

        mount: function (ctx, options) {
            this.ctx = ctx || null;
            this.options = options || {};
            return this;
        },

        unmount: function () {},

        getBag: function (payload) {
            return this.request('/get/bag', 'getBag', payload || {});
        },

        categories: function (payload, action) {
            return this.request('/inventory/categories', action || 'getInventoryCategories', payload || null);
        },

        slots: function (payload, action) {
            return this.request('/inventory/slots', action || 'getInventorySlots', payload || null);
        },

        bagItems: function (payload, action) {
            return this.request('/list/profile/bag', action || 'getBagItems', payload || {});
        },

        bagGridConfig: function () {
            return buildBagGridConfig();
        },

        equip: function (payload) {
            return this.request('/inventory/equip', 'equipItem', payload || {});
        },

        unequip: function (payload) {
            return this.request('/inventory/unequip', 'unequipItem', payload || {});
        },

        swap: function (payload) {
            return this.request('/inventory/swap', 'swapEquipment', payload || {});
        },

        drop: function (payload) {
            return this.request('/location/drops/drop', 'dropItem', payload || {});
        },

        charactersSearch: function (payload) {
            return this.request('/list/characters/search', 'searchCharacters', payload || {});
        },

        transfer: function (payload) {
            return this.request('/inventory/transfer', 'transferInventoryItem', payload || {});
        },

        useItem: function (payload) {
            return this.request('/items/use', 'useInventoryItem', payload || {});
        },

        reloadItem: function (payload) {
            return this.request('/items/reload', 'reloadInventoryItem', payload || {});
        },

        maintainItem: function (payload) {
            return this.request('/inventory/maintenance', 'maintainInventoryItem', payload || {});
        },

        destroy: function (payload) {
            return this.request('/inventory/destroy', 'destroyItem', payload || {});
        },

        equipped: function (payload, action) {
            return this.request('/inventory/equipped', action || 'getEquipped', payload || null);
        },

        available: function (payload, action) {
            return this.request('/inventory/available', action || 'getAvailable', payload || null);
        },

        request: function (url, action, payload) {
            if (!this.ctx || !this.ctx.services || !this.ctx.services.http) {
                return Promise.reject(new Error('HTTP service not available.'));
            }

            return this.ctx.services.http.request({
                url: url,
                action: action,
                payload: payload || {}
            });
        }
    };
}

globalWindow.GameInventoryModuleFactory = createInventoryModule;
export { createInventoryModule as GameInventoryModuleFactory };
export default createInventoryModule;

