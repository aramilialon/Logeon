const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var STATUS_WEIGHT = {
    error: 0,
    detected: 1,
    inactive: 2,
    installed: 3,
    active: 4
};

var AdminModules = {
    initialized: false,
    root: null,
    grid: null,
    rawDataset: [],
    rowsById: {},
    capabilityRows: [],
    summary: null,
    statusFilter: null,
    searchInput: null,
    switchSyncLocks: {},
    filtersForm: null,
    capabilityList: null,
    capabilityEmpty: null,
    capabilitySummary: null,
    docsModalNode: null,
    docsModal: null,
    docsModuleId: '',
    docsListNode: null,
    docsEmptyNode: null,
    docsTitleNode: null,
    docsSubtitleNode: null,
    docsViewTitleNode: null,
    docsViewMetaNode: null,
    docsViewBodyNode: null,
    docsActivePath: '',
    docsRows: [],

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.querySelector('#admin-page [data-admin-page="modules"]');
        if (!this.root || !document.getElementById('grid-admin-modules')) {
            return this;
        }

        this.summary = {
            active: this.root.querySelector('[data-role="modules-summary-active"]'),
            inactive: this.root.querySelector('[data-role="modules-summary-inactive"]'),
            detected: this.root.querySelector('[data-role="modules-summary-detected"]'),
            issues: this.root.querySelector('[data-role="modules-summary-issues"]')
        };
        this.statusFilter = this.root.querySelector('[data-role="admin-modules-filter-status"]');
        this.searchInput = this.root.querySelector('[data-role="admin-modules-filter-query"]');
        this.filtersForm = this.root.querySelector('[data-role="admin-modules-filters"]');
        this.capabilityList = this.root.querySelector('[data-role="modules-capabilities-list"]');
        this.capabilityEmpty = this.root.querySelector('[data-role="modules-capabilities-empty"]');
        this.capabilitySummary = {
            available: this.root.querySelector('[data-role="modules-capabilities-available"]'),
            unavailable: this.root.querySelector('[data-role="modules-capabilities-unavailable"]'),
            total: this.root.querySelector('[data-role="modules-capabilities-total"]')
        };
        this.docsModalNode = document.getElementById('admin-module-docs-modal');
        this.docsListNode = this.root.querySelector('[data-role="admin-module-docs-list"]');
        this.docsEmptyNode = this.root.querySelector('[data-role="admin-module-docs-empty"]');
        this.docsTitleNode = this.root.querySelector('[data-role="admin-module-docs-title"]');
        this.docsSubtitleNode = this.root.querySelector('[data-role="admin-module-docs-subtitle"]');
        this.docsViewTitleNode = this.root.querySelector('[data-role="admin-module-doc-view-title"]');
        this.docsViewMetaNode = this.root.querySelector('[data-role="admin-module-doc-view-meta"]');
        this.docsViewBodyNode = this.root.querySelector('[data-role="admin-module-doc-view-body"]');
        if (this.docsModalNode && globalWindow.bootstrap && typeof globalWindow.bootstrap.Modal === 'function') {
            this.docsModal = globalWindow.bootstrap.Modal.getOrCreateInstance(this.docsModalNode);
        }

        this.bindEvents();
        this.initGrid();
        this.loadAll();

        this.initialized = true;
        return this;
    },

    bindEvents: function () {
        var self = this;

        if (this.statusFilter) {
            this.statusFilter.addEventListener('change', function () {
                self.refreshGridData({ resetPage: true });
            });
        }
        if (this.searchInput) {
            this.searchInput.addEventListener('input', function () {
                self.refreshGridData({ resetPage: true });
            });
        }
        if (this.filtersForm) {
            this.filtersForm.addEventListener('submit', function (event) {
                event.preventDefault();
            });
        }

        this.root.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) {
                return;
            }

            var action = String(trigger.getAttribute('data-action') || '').trim();
            if (action === 'admin-modules-reload') {
                event.preventDefault();
                self.loadAll();
                return;
            }

            if (action === 'admin-modules-audit') {
                event.preventDefault();
                self.runAudit();
                return;
            }

            if (action === 'admin-modules-reset-filters') {
                event.preventDefault();
                if (self.searchInput) {
                    self.searchInput.value = '';
                }
                if (self.statusFilter) {
                    self.statusFilter.value = 'all';
                }
                self.refreshGridData({ resetPage: true });
                return;
            }

            if (action === 'admin-modules-capabilities-reload') {
                event.preventDefault();
                self.loadCapabilities();
                return;
            }

            if (action === 'admin-module-docs-open') {
                event.preventDefault();
                self.openModuleDocs(self.findRowByTrigger(trigger));
                return;
            }

            if (action === 'admin-module-docs-reload') {
                event.preventDefault();
                self.reloadModuleDocs();
                return;
            }

            if (action === 'admin-module-doc-open') {
                event.preventDefault();
                var docPath = String(trigger.getAttribute('data-path') || '').trim();
                if (self.docsModuleId !== '' && docPath !== '') {
                    self.loadModuleDoc(self.docsModuleId, docPath);
                }
                return;
            }

            if (action === 'admin-module-activate') {
                event.preventDefault();
                self.activateModule(self.findRowByTrigger(trigger));
                return;
            }

            if (action === 'admin-module-deactivate') {
                event.preventDefault();
                self.deactivateModule(self.findRowByTrigger(trigger));
                return;
            }

            if (action === 'admin-module-uninstall-safe') {
                event.preventDefault();
                self.uninstallModule(self.findRowByTrigger(trigger), false);
                return;
            }

            if (action === 'admin-module-uninstall-purge') {
                event.preventDefault();
                self.uninstallModule(self.findRowByTrigger(trigger), true);
            }
        });
    },

    initGrid: function () {
        var self = this;
        this.grid = new Datagrid('grid-admin-modules', {
            name: 'AdminModules',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: {
                url: '/admin/modules/list',
                action: 'list'
            },
            nav: {
                display: 'bottom',
                urlupdate: 0,
                results: 20,
                page: 1
            },
            onGetDataSuccess: function (response) {
                self.onGridSuccess(response);
            },
            onGetDataError: function () {
                self.rawDataset = [];
                self.rowsById = {};
                self.updateSummary([]);
                self.refreshGridData();
            },
            columns: [
                { label: 'ID', field: 'id', sortable: true, style: { textAlign: 'left' } },
                {
                    label: 'Modulo',
                    field: 'name',
                    sortable: true,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        var name = self.escapeHtml(row.name || row.id || '-');
                        var id = self.escapeHtml(row.id || '-');
                        var vendor = self.escapeHtml(row.vendor || '-');
                        var version = self.escapeHtml(row.version || '-');
                        return ''
                            + '<div><b>' + name + '</b></div>'
                            + '<div class="small text-muted">' + id + '</div>'
                            + '<div class="small text-muted">Vendor: ' + vendor + ' - v' + version + '</div>';
                    }
                },
                {
                    label: 'Attivo',
                    field: 'status',
                    sortable: true,
                    format: function (row) {
                        return self.statusBadge(row.status || 'detected');
                    }
                },
                {
                    label: 'Compatibilita core',
                    field: 'core_compatible',
                    sortable: true,
                    format: function (row) {
                        var ok = parseInt(row.core_compatible, 10) === 1;
                        var min = self.escapeHtml(row.core_min || '-');
                        var max = self.escapeHtml(row.core_max || '-');
                        return ''
                            + (ok
                                ? '<span class="badge text-bg-success">Compatibile</span>'
                                : '<span class="badge text-bg-danger">Non compatibile</span>')
                            + '<div class="small text-muted mt-1">Core min: ' + min + ' - max: ' + max + '</div>';
                    }
                },
                {
                    label: 'Dipendenze',
                    field: 'dependencies_required',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.renderDependencies(row);
                    }
                },
                {
                    label: 'Note',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.renderNotes(row);
                    }
                },
                {
                    label: 'Governance',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.renderGovernance(row);
                    }
                },
                {
                    label: 'Stato',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        var moduleId = String(row.id || '').trim();
                        if (moduleId !== '') {
                            self.rowsById[moduleId] = row;
                        }

                        var active = parseInt(row.is_active, 10) === 1;
                        var blockers = self.activationBlockers(row);
                        var deactivateDependents = self.deactivationDependents(row);
                        var activateTitle = blockers.length > 0
                            ? self.escapeHtml(blockers.join(' | '))
                            : 'Attiva modulo';
                        var deactivateTitle = deactivateDependents.length > 0
                            ? self.escapeHtml('Disattivando questo modulo verranno disattivati anche: ' + deactivateDependents.join(', '))
                            : 'Disattiva modulo';
                        var title = active ? deactivateTitle : activateTitle;

                        return ''
                            + '<input type="hidden"'
                            + ' data-role="admin-module-status-switch"'
                            + ' data-id="' + self.escapeHtml(moduleId) + '"'
                            + ' value="' + (active ? '1' : '0') + '"'
                            + ' title="' + title + '">';
                    }
                }
            ]
        });
    },

    loadGrid: function () {
        if (!this.grid) {
            return this;
        }

        this.rawDataset = [];
        this.rowsById = {};
        this.grid.loadData({}, 20, 1, '__default__|ASC');
        return this;
    },

    loadAll: function () {
        this.loadGrid();
        this.loadCapabilities();
        return this;
    },

    onGridSuccess: function (response) {
        var dataset = this.extractDataset(response);
        this.rawDataset = Array.isArray(dataset) ? dataset.slice() : [];
        this.rowsById = this.indexById(this.rawDataset);
        this.updateSummary(this.rawDataset);
        this.refreshGridData();
    },

    extractDataset: function (response) {
        if (response && Array.isArray(response.dataset)) {
            return response.dataset;
        }
        if (this.grid && Array.isArray(this.grid.dataset)) {
            return this.grid.dataset;
        }
        return [];
    },

    indexById: function (dataset) {
        var map = {};
        for (var i = 0; i < dataset.length; i++) {
            var row = dataset[i] || {};
            var id = String(row.id || '').trim();
            if (id !== '') {
                map[id] = row;
            }
        }
        return map;
    },

    updateSummary: function (dataset) {
        var active = 0;
        var inactive = 0;
        var detected = 0;
        var issues = 0;

        for (var i = 0; i < dataset.length; i++) {
            var row = dataset[i] || {};
            var status = String(row.status || '').trim().toLowerCase();

            if (status === 'active') {
                active += 1;
            } else if (status === 'inactive' || status === 'installed') {
                inactive += 1;
            } else if (status === 'detected') {
                detected += 1;
            }

            if (this.rowHasIssues(row)) {
                issues += 1;
            }
        }

        if (this.summary && this.summary.active) {
            this.summary.active.textContent = String(active);
        }
        if (this.summary && this.summary.inactive) {
            this.summary.inactive.textContent = String(inactive);
        }
        if (this.summary && this.summary.detected) {
            this.summary.detected.textContent = String(detected);
        }
        if (this.summary && this.summary.issues) {
            this.summary.issues.textContent = String(issues);
        }
    },

    loadCapabilities: function () {
        var self = this;

        this.capabilityRows = [];
        this.renderCapabilities('Caricamento capability...');

        this.requestPost('/admin/modules/capabilities', {}, function (response) {
            self.capabilityRows = response && Array.isArray(response.dataset) ? response.dataset.slice() : [];
            self.renderCapabilities();
        }, function () {
            self.capabilityRows = [];
            self.renderCapabilities('Impossibile caricare il registro capability.');
            return true;
        });

        return this;
    },

    renderCapabilities: function (message) {
        if (!this.capabilityList || !this.capabilityEmpty) {
            return this;
        }

        var rows = Array.isArray(this.capabilityRows) ? this.capabilityRows : [];
        var available = 0;
        var html = [];

        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i] || {};
            var name = String(row.name || '').trim();
            if (name === '') {
                continue;
            }

            var isAvailable = parseInt(row.available || '0', 10) === 1;
            if (isAvailable) {
                available += 1;
            }

            html.push(
                '<div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">'
                + '<span class="small font-monospace">' + this.escapeHtml(name) + '</span>'
                + (isAvailable
                    ? '<span class="badge text-bg-success">Disponibile</span>'
                    : '<span class="badge text-bg-secondary">Assente</span>')
                + '</div>'
            );
        }

        this.capabilityList.innerHTML = html.join('');

        var unavailable = rows.length - available;
        if (this.capabilitySummary) {
            if (this.capabilitySummary.available) {
                this.capabilitySummary.available.textContent = String(available);
            }
            if (this.capabilitySummary.unavailable) {
                this.capabilitySummary.unavailable.textContent = String(unavailable < 0 ? 0 : unavailable);
            }
            if (this.capabilitySummary.total) {
                this.capabilitySummary.total.textContent = String(rows.length);
            }
        }

        if (html.length > 0) {
            this.capabilityEmpty.classList.add('d-none');
            this.capabilityEmpty.textContent = 'Nessuna capability registrata.';
            return this;
        }

        this.capabilityEmpty.textContent = message || 'Nessuna capability registrata.';
        this.capabilityEmpty.classList.remove('d-none');
        return this;
    },

    refreshGridData: function (options) {
        if (!this.grid) {
            return this;
        }
        options = options || {};

        var dataset = Array.isArray(this.rawDataset) ? this.rawDataset.slice() : [];
        dataset = this.sortDataset(dataset, this.getCurrentOrderBy());
        dataset = this.filterDataset(dataset);

        var pagedDataset = this.paginateDataset(dataset, options.resetPage === true);

        this.grid.dataset = pagedDataset;
        this.grid.rebuildIndex();
        this.grid.updateTable();
        this.mountStatusSwitches();

        return this;
    },

    getCurrentOrderBy: function () {
        if (this.grid && this.grid.paginator && this.grid.paginator.nav) {
            var orderBy = String(this.grid.paginator.nav.orderBy || '').trim();
            if (orderBy !== '') {
                return orderBy;
            }
        }
        return '__default__|ASC';
    },

    paginateDataset: function (dataset, resetPage) {
        var rows = Array.isArray(dataset) ? dataset : [];
        var paginator = this.grid && this.grid.paginator ? this.grid.paginator : null;
        if (!paginator || !paginator.nav) {
            return rows;
        }

        var nav = paginator.nav;
        var results = paginator.toPositiveInt(nav.results, 20);
        var page = resetPage === true ? 1 : paginator.toPositiveInt(nav.page, 1);
        var total = rows.length;
        var totalPages = total > 0 ? Math.ceil(total / results) : 0;

        if (totalPages > 0 && page > totalPages) {
            page = totalPages;
        }
        if (page < 1) {
            page = 1;
        }

        paginator.setNav({
            query: (nav.query && typeof nav.query === 'object') ? nav.query : {},
            orderBy: this.getCurrentOrderBy(),
            page: page,
            results: results,
            tot: { count: total }
        });

        if (total === 0) {
            return [];
        }

        var start = (page - 1) * results;
        return rows.slice(start, start + results);
    },

    parseOrderBy: function (orderBy) {
        var raw = String(orderBy || '').trim();
        if (raw === '') {
            return { field: '__default__', direction: 'ASC' };
        }

        var chunk = raw.split(',')[0] || '';
        var parts = chunk.split('|');
        var field = String(parts[0] || '').trim();
        var direction = String(parts[1] || 'ASC').trim().toUpperCase();

        if (field === '') {
            field = '__default__';
        }
        if (direction !== 'DESC') {
            direction = 'ASC';
        }

        return { field: field, direction: direction };
    },

    sortDataset: function (dataset, orderBy) {
        var parsed = this.parseOrderBy(orderBy);
        var field = parsed.field;
        var direction = parsed.direction;
        var factor = direction === 'DESC' ? -1 : 1;
        var self = this;

        dataset.sort(function (a, b) {
            var result = self.compareRows(a || {}, b || {}, field);
            if (result !== 0) {
                return result * factor;
            }

            return String(a && a.id ? a.id : '').localeCompare(String(b && b.id ? b.id : ''), 'it', { sensitivity: 'base', numeric: true });
        });

        return dataset;
    },

    compareRows: function (a, b, field) {
        if (field === '__default__') {
            return this.compareDefaultOrder(a, b);
        }

        var valueA = this.resolveSortValue(a, field);
        var valueB = this.resolveSortValue(b, field);

        if (typeof valueA === 'number' && typeof valueB === 'number') {
            if (valueA === valueB) {
                return 0;
            }
            return valueA < valueB ? -1 : 1;
        }

        var textA = String(valueA == null ? '' : valueA);
        var textB = String(valueB == null ? '' : valueB);
        return textA.localeCompare(textB, 'it', { sensitivity: 'base', numeric: true });
    },

    compareDefaultOrder: function (a, b) {
        var issueA = this.rowHasIssues(a) ? 0 : 1;
        var issueB = this.rowHasIssues(b) ? 0 : 1;
        if (issueA !== issueB) {
            return issueA < issueB ? -1 : 1;
        }

        var statusA = this.statusWeight(String(a.status || 'detected'));
        var statusB = this.statusWeight(String(b.status || 'detected'));
        if (statusA !== statusB) {
            return statusA < statusB ? -1 : 1;
        }

        var nameA = String(a.name || a.id || '').toLowerCase();
        var nameB = String(b.name || b.id || '').toLowerCase();
        return nameA.localeCompare(nameB, 'it', { sensitivity: 'base', numeric: true });
    },

    resolveSortValue: function (row, field) {
        if (field === 'status') {
            return this.statusWeight(String(row.status || 'detected'));
        }
        if (field === 'core_compatible') {
            return parseInt(row.core_compatible, 10) === 1 ? 1 : 0;
        }
        if (field === 'dependencies_required') {
            return Array.isArray(row.dependencies_required) ? row.dependencies_required.length : 0;
        }
        if (field === 'is_active') {
            return parseInt(row.is_active, 10) === 1 ? 1 : 0;
        }
        if (field === 'name' || field === 'id' || field === 'vendor' || field === 'version') {
            return String(row[field] || '').toLowerCase();
        }

        return row[field];
    },

    filterDataset: function (dataset) {
        var mode = this.statusFilter ? String(this.statusFilter.value || 'all').trim().toLowerCase() : 'all';
        var query = this.searchInput ? String(this.searchInput.value || '').trim().toLowerCase() : '';
        if (mode === '' || mode === 'all') {
            return this.filterByQuery(dataset, query);
        }

        var self = this;
        var byStatus = dataset.filter(function (row) {
            var status = String((row && row.status) || '').trim().toLowerCase();
            var isActive = parseInt(row && row.is_active, 10) === 1;

            if (mode === 'active') {
                return isActive || status === 'active';
            }
            if (mode === 'inactive') {
                return status === 'inactive' || status === 'installed' || (!isActive && status !== 'detected');
            }
            if (mode === 'detected') {
                return status === 'detected';
            }
            if (mode === 'issues') {
                return self.rowHasIssues(row || {});
            }

            return true;
        });
        return this.filterByQuery(byStatus, query);
    },

    filterByQuery: function (dataset, query) {
        var q = String(query || '').trim().toLowerCase();
        if (q === '') {
            return dataset;
        }

        return dataset.filter(function (row) {
            var privacyCategories = Array.isArray(row && row.privacy_data_categories) ? row.privacy_data_categories.join(' ') : '';
            var privacyPurposes = Array.isArray(row && row.privacy_purposes) ? row.privacy_purposes.join(' ') : '';
            var privacyRetention = String((row && row.privacy_retention) || '');
            var fields = [
                String((row && row.id) || ''),
                String((row && row.name) || ''),
                String((row && row.vendor) || ''),
                String((row && row.version) || ''),
                String((row && row.description) || ''),
                privacyCategories,
                privacyPurposes,
                privacyRetention
            ];
            var haystack = fields.join(' ').toLowerCase();
            return haystack.indexOf(q) !== -1;
        });
    },

    statusWeight: function (status) {
        var key = String(status || '').trim().toLowerCase();
        if (Object.prototype.hasOwnProperty.call(STATUS_WEIGHT, key)) {
            return STATUS_WEIGHT[key];
        }
        return 9;
    },

    findRowByTrigger: function (trigger) {
        var id = String(trigger.getAttribute('data-id') || '').trim();
        if (!id) {
            return null;
        }
        return this.rowsById[id] || null;
    },

    openModuleDocs: function (row) {
        if (!row || !row.id) {
            Toast.show({ body: 'Modulo non valido.', type: 'warning' });
            return;
        }
        this.docsModuleId = String(row.id || '').trim();
        if (this.docsModuleId === '') {
            Toast.show({ body: 'Modulo non valido.', type: 'warning' });
            return;
        }
        this.docsActivePath = '';
        this.docsRows = [];

        if (this.docsTitleNode) {
            this.docsTitleNode.textContent = 'Documentazione modulo: ' + String(row.name || this.docsModuleId);
        }
        if (this.docsSubtitleNode) {
            this.docsSubtitleNode.textContent = 'Guide e tutorial disponibili per ' + this.docsModuleId + '.';
        }

        this.renderModuleDocPlaceholder('Caricamento documentazione...');
        this.renderModuleDocsList([]);

        if (this.docsModal && typeof this.docsModal.show === 'function') {
            this.docsModal.show();
        }

        this.loadModuleDocsList(this.docsModuleId);
    },

    reloadModuleDocs: function () {
        if (this.docsModuleId === '') {
            return;
        }
        this.loadModuleDocsList(this.docsModuleId);
    },

    loadModuleDocsList: function (moduleId) {
        var self = this;
        this.requestPost('/admin/modules/docs/list', { module_id: moduleId }, function (response) {
            var rows = response && Array.isArray(response.dataset) ? response.dataset : [];
            self.docsRows = rows.slice();
            self.renderModuleDocsList(rows);
            if (rows.length > 0) {
                var selectedPath = '';
                if (self.docsActivePath !== '') {
                    for (var i = 0; i < rows.length; i += 1) {
                        if (String(rows[i] && rows[i].path ? rows[i].path : '').trim() === self.docsActivePath) {
                            selectedPath = self.docsActivePath;
                            break;
                        }
                    }
                }
                if (selectedPath === '') {
                    selectedPath = String(rows[0].path || '');
                }
                self.loadModuleDoc(moduleId, selectedPath);
            } else {
                self.renderModuleDocPlaceholder('Nessun documento disponibile per questo modulo.');
            }
        }, function () {
            self.renderModuleDocPlaceholder('Errore nel caricamento della lista documenti.');
            return false;
        });
    },

    renderModuleDocsList: function (rows) {
        if (!this.docsListNode) {
            return;
        }
        var html = '';
        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i] || {};
            var path = String(row.path || '').trim();
            if (path === '') {
                continue;
            }
            var title = String(row.title || row.filename || path).trim();
            var updated = String(row.updated_at || '').trim();
            var isActive = this.docsActivePath !== '' && path === this.docsActivePath;
            html += ''
                + '<button type="button" class="list-group-item list-group-item-action'
                + (isActive ? ' active' : '')
                + '" data-action="admin-module-doc-open" data-path="' + this.escapeHtml(path) + '">'
                + '<div class="fw-semibold">' + this.escapeHtml(title) + '</div>'
                + '<div class="small text-muted">' + this.escapeHtml(path) + (updated !== '' ? (' • ' + this.escapeHtml(updated)) : '') + '</div>'
                + '</button>';
        }
        this.docsListNode.innerHTML = html;
        if (this.docsEmptyNode) {
            this.docsEmptyNode.classList.toggle('d-none', html !== '');
        }
    },

    loadModuleDoc: function (moduleId, path) {
        var self = this;
        var safePath = String(path || '').trim();
        if (safePath === '') {
            this.renderModuleDocPlaceholder('Documento non selezionato.');
            return;
        }
        this.docsActivePath = safePath;
        this.renderModuleDocsList(this.docsRows);

        this.requestPost('/admin/modules/docs/get', { module_id: moduleId, path: safePath }, function (response) {
            var row = response && response.dataset ? response.dataset : {};
            self.renderModuleDoc(row);
        }, function () {
            self.renderModuleDocPlaceholder('Errore nel caricamento del documento.');
            return false;
        });
    },

    renderModuleDocPlaceholder: function (message) {
        if (this.docsViewTitleNode) {
            this.docsViewTitleNode.textContent = 'Anteprima documento';
        }
        if (this.docsViewMetaNode) {
            this.docsViewMetaNode.textContent = '';
        }
        if (this.docsViewBodyNode) {
            this.docsViewBodyNode.innerHTML = '<div class="admin-markdown-reader"><div class="text-muted">' + this.escapeHtml(message || 'Nessun documento selezionato.') + '</div></div>';
        }
    },

    renderModuleDoc: function (row) {
        var title = String((row && row.title) || 'Documento').trim();
        var path = String((row && row.path) || '').trim();
        var updated = String((row && row.updated_at) || '').trim();
        var sizeBytes = parseInt((row && row.size_bytes) || '0', 10) || 0;
        var markdown = String((row && row.body_markdown) || '');
        if (path !== '') {
            this.docsActivePath = path;
        }

        if (this.docsViewTitleNode) {
            this.docsViewTitleNode.textContent = title;
        }
        if (this.docsViewMetaNode) {
            var meta = path;
            if (sizeBytes > 0) {
                meta += (meta !== '' ? ' • ' : '') + this.formatBytes(sizeBytes);
            }
            if (updated !== '') {
                meta += (meta !== '' ? ' • ' : '') + updated;
            }
            this.docsViewMetaNode.textContent = meta;
        }
        if (this.docsViewBodyNode) {
            this.docsViewBodyNode.innerHTML = '<div class="admin-markdown-reader">' + this.markdownToHtml(markdown) + '</div>';
        }
    },

    markdownToHtml: function (markdown) {
        var text = String(markdown || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var lines = text.split('\n');
        var html = [];
        var listType = '';
        var inCode = false;
        var codeLines = [];
        var paragraph = [];
        var quoteLines = [];

        var closeParagraph = function () {
            if (paragraph.length > 0) {
                html.push('<p>' + AdminModules.markdownInline(paragraph.join(' ')) + '</p>');
                paragraph = [];
            }
        };

        var closeList = function () {
            if (listType !== '') {
                html.push('</' + listType + '>');
                listType = '';
            }
        };

        var closeQuote = function () {
            if (quoteLines.length > 0) {
                var quoteHtml = quoteLines.map(function (line) {
                    return '<p>' + AdminModules.markdownInline(line) + '</p>';
                }).join('');
                html.push('<blockquote>' + quoteHtml + '</blockquote>');
                quoteLines = [];
            }
        };

        var closeCode = function () {
            if (inCode) {
                html.push('<pre><code>' + AdminModules.escapeHtml(codeLines.join('\n')) + '</code></pre>');
                inCode = false;
                codeLines = [];
            }
        };

        var closeAllBlocks = function () {
            closeParagraph();
            closeQuote();
            closeList();
        };

        var isTableSeparator = function (line) {
            var normalized = String(line || '').trim();
            return /^[:\-\|\s]+$/.test(normalized) && normalized.indexOf('-') !== -1;
        };

        var splitTableRow = function (line) {
            return String(line || '')
                .trim()
                .replace(/^\|/, '')
                .replace(/\|$/, '')
                .split('|')
                .map(function (cell) {
                    return cell.trim();
                });
        };

        var buildTable = function (headerLine, startIndex) {
            var headerCells = splitTableRow(headerLine);
            var rowHtml = [];
            var idx = startIndex;
            while (idx < lines.length) {
                var rowLine = String(lines[idx] || '');
                if (rowLine.trim() === '' || rowLine.indexOf('|') === -1) {
                    break;
                }
                var cells = splitTableRow(rowLine);
                var tds = cells.map(function (cell) {
                    return '<td>' + AdminModules.markdownInline(cell) + '</td>';
                }).join('');
                rowHtml.push('<tr>' + tds + '</tr>');
                idx += 1;
            }

            var ths = headerCells.map(function (cell) {
                return '<th>' + AdminModules.markdownInline(cell) + '</th>';
            }).join('');
            var tableHtml = '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr>' + ths + '</tr></thead>';
            if (rowHtml.length > 0) {
                tableHtml += '<tbody>' + rowHtml.join('') + '</tbody>';
            }
            tableHtml += '</table></div>';
            return {
                html: tableHtml,
                nextIndex: idx
            };
        };

        for (var i = 0; i < lines.length; i += 1) {
            var raw = String(lines[i] || '');
            var trimmed = raw.trim();

            if (trimmed.indexOf('```') === 0) {
                closeParagraph();
                closeQuote();
                closeList();
                if (inCode) {
                    closeCode();
                } else {
                    inCode = true;
                }
                continue;
            }

            if (inCode) {
                codeLines.push(raw);
                continue;
            }

            if (trimmed === '') {
                closeAllBlocks();
                continue;
            }

            if (trimmed.indexOf('>') === 0) {
                closeParagraph();
                closeList();
                quoteLines.push(trimmed.replace(/^>\s?/, ''));
                continue;
            }
            closeQuote();

            if (/^(-{3,}|\*{3,}|_{3,})$/.test(trimmed)) {
                closeAllBlocks();
                html.push('<hr>');
                continue;
            }

            if (trimmed.indexOf('|') !== -1 && (i + 1) < lines.length && isTableSeparator(lines[i + 1])) {
                closeAllBlocks();
                var table = buildTable(trimmed, i + 2);
                html.push(table.html);
                i = table.nextIndex - 1;
                continue;
            }

            var headingMatch = trimmed.match(/^(#{1,6})\s+(.+)$/);
            if (headingMatch) {
                closeAllBlocks();
                var level = Math.min(6, headingMatch[1].length);
                html.push('<h' + level + '>' + this.markdownInline(headingMatch[2]) + '</h' + level + '>');
                continue;
            }

            var orderedMatch = trimmed.match(/^\d+[.)]\s+(.+)$/);
            if (orderedMatch) {
                closeParagraph();
                if (listType !== 'ol') {
                    closeList();
                    listType = 'ol';
                    html.push('<ol>');
                }
                html.push('<li>' + this.markdownInline(orderedMatch[1]) + '</li>');
                continue;
            }

            var unorderedMatch = trimmed.match(/^[-*+]\s+(.+)$/);
            if (unorderedMatch) {
                closeParagraph();
                if (listType !== 'ul') {
                    closeList();
                    listType = 'ul';
                    html.push('<ul>');
                }
                html.push('<li>' + this.markdownInline(unorderedMatch[1]) + '</li>');
                continue;
            }

            closeList();
            paragraph.push(trimmed);
        }

        closeCode();
        closeAllBlocks();

        return html.length > 0 ? html.join('') : '<div class="text-muted">Documento vuoto.</div>';
    },

    markdownInline: function (value) {
        var out = this.escapeHtml(value || '');
        out = out.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1" loading="lazy">');
        out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
        out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        out = out.replace(/~~([^~]+)~~/g, '<del>$1</del>');
        out = out.replace(/`([^`]+)`/g, '<code>$1</code>');
        return out;
    },

    formatBytes: function (bytes) {
        var size = parseInt(bytes || '0', 10) || 0;
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1048576) {
            return (size / 1024).toFixed(1).replace(/\.0$/, '') + ' KB';
        }
        return (size / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
    },

    activateModule: function (row, options) {
        if (!row || !row.id) {
            return;
        }
        options = options || {};

        var blockers = this.activationBlockers(row);
        if (blockers.length > 0) {
            Toast.show({ body: blockers.join(' | '), type: 'warning' });
            if (typeof options.onCancelled === 'function') {
                options.onCancelled();
            }
            return;
        }

        var self = this;
        this.confirmAction(
            'Attivazione modulo',
            'Confermi l\'attivazione del modulo <b>' + this.escapeHtml(row.name || row.id) + '</b>?',
            function () {
                self.requestPost('/admin/modules/activate', { module_id: row.id }, function () {
                    Toast.show({ body: 'Modulo attivato: ' + row.id, type: 'success' });
                    self.loadAll();
                });
            },
            options.onCancelled
        );
    },

    deactivateModule: function (row, options) {
        if (!row || !row.id) {
            return;
        }
        options = options || {};
        var dependents = this.deactivationDependents(row);

        var self = this;
        var confirmBody = 'Confermi la disattivazione del modulo <b>' + this.escapeHtml(row.name || row.id) + '</b>?';
        if (dependents.length > 0) {
            confirmBody += '<br><br><span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Verranno disattivati anche: <b>' + this.escapeHtml(dependents.join(', ')) + '</b></span>';
        }

        this.confirmAction(
            'Disattivazione modulo',
            confirmBody,
            function () {
                self.requestPost(
                    '/admin/modules/deactivate',
                    { module_id: row.id, cascade: dependents.length > 0 ? 1 : 0 },
                    function (response) {
                        var dataset = response && response.dataset ? response.dataset : {};
                        var deactivated = Array.isArray(dataset.deactivated_modules) ? dataset.deactivated_modules : [];
                        if (deactivated.length > 0) {
                            Toast.show({ body: 'Moduli disattivati: ' + deactivated.join(', '), type: 'success' });
                        } else {
                            Toast.show({ body: 'Modulo disattivato: ' + row.id, type: 'success' });
                        }
                        self.loadAll();
                    },
                    function (xhr) {
                        var error = self.parseErrorResponse(xhr);
                        if (error.code !== 'module_deactivation_requires_confirmation') {
                            return false;
                        }

                        var fallbackDependents = dependents.slice();
                        var serverDependents = Array.isArray(error.payload.dependents) ? error.payload.dependents : fallbackDependents;
                        self.confirmAction(
                            'Conferma disattivazione dipendenze',
                            'Per continuare verranno disattivati anche: <b>' + self.escapeHtml(serverDependents.join(', ')) + '</b>.',
                            function () {
                                self.requestPost('/admin/modules/deactivate', { module_id: row.id, cascade: 1 }, function (response) {
                                    var dataset = response && response.dataset ? response.dataset : {};
                                    var deactivated = Array.isArray(dataset.deactivated_modules) ? dataset.deactivated_modules : [];
                                    if (deactivated.length > 0) {
                                        Toast.show({ body: 'Moduli disattivati: ' + deactivated.join(', '), type: 'success' });
                                    } else {
                                        Toast.show({ body: 'Modulo disattivato: ' + row.id, type: 'success' });
                                    }
                                    self.loadAll();
                                });
                            },
                            options.onCancelled
                        );
                        return true;
                    }
                );
            },
            options.onCancelled
        );
    },

    uninstallModule: function (row, purge, options) {
        if (!row || !row.id) {
            return;
        }
        options = options || {};
        purge = purge === true;
        var isBundled = parseInt(row.is_bundled, 10) === 1;
        var isInstalled = parseInt(row.is_installed, 10) === 1;

        if (parseInt(row.is_active, 10) === 1) {
            Toast.show({ body: 'Disattiva prima il modulo per procedere con la disinstallazione.', type: 'warning' });
            if (typeof options.onCancelled === 'function') {
                options.onCancelled();
            }
            return;
        }

        if (!purge && !isInstalled) {
            Toast.show({ body: 'La disinstallazione safe e disponibile solo per moduli installati.', type: 'warning' });
            if (typeof options.onCancelled === 'function') {
                options.onCancelled();
            }
            return;
        }

        if (!purge && isBundled) {
            Toast.show({ body: 'I moduli bundled supportano solo il purge dati quando sono inattivi.', type: 'warning' });
            if (typeof options.onCancelled === 'function') {
                options.onCancelled();
            }
            return;
        }

        var self = this;
        var title = purge ? 'Purge dati modulo' : 'Disinstallazione safe modulo';
        var body = purge
            ? 'Confermi il <b>purge dati</b> del modulo <b>' + this.escapeHtml(row.name || row.id) + '</b>?<br><span class="small text-danger">L&apos;operazione esegue anche le migrazioni di uninstall quando presenti e rimuove i metadati di installazione.</span>'
            : 'Confermi la <b>disinstallazione safe</b> del modulo <b>' + this.escapeHtml(row.name || row.id) + '</b>?';

        this.confirmAction(title, body, function () {
            self.requestPost(
                '/admin/modules/uninstall',
                { module_id: row.id, purge: purge ? 1 : 0 },
                function (response) {
                    var dataset = response && response.dataset ? response.dataset : {};
                    var mode = String(dataset.uninstall_mode || (purge ? 'purge' : 'safe'));
                    var message = mode === 'purge'
                        ? 'Purge dati completato: ' + row.id
                        : 'Modulo disinstallato (safe): ' + row.id;
                    Toast.show({ body: message, type: 'success' });
                    self.loadAll();
                },
                function () {
                    return false;
                }
            );
        }, options.onCancelled);
    },

    runAudit: function () {
        var self = this;
        this.requestPost('/admin/modules/audit', {}, function (response) {
            var dataset = response && response.dataset ? response.dataset : {};
            var summary = dataset.summary || {};
            var orphanRows = parseInt(summary.orphan_installed_rows, 10) || 0;
            var orphanArtifacts = parseInt(summary.orphan_artifacts, 10) || 0;
            var activeWithoutArtifacts = parseInt(summary.active_without_artifacts, 10) || 0;
            var privacyMissing = parseInt(summary.privacy_missing_declaration, 10) || 0;
            var privacyIncomplete = parseInt(summary.privacy_incomplete_declaration, 10) || 0;

            var body = ''
                + '<div class="small">'
                + '<div><b>Moduli rilevati:</b> ' + self.escapeHtml(String(summary.discovered_modules || 0)) + '</div>'
                + '<div><b>Moduli installati:</b> ' + self.escapeHtml(String(summary.installed_modules || 0)) + '</div>'
                + '<div><b>Artifact tracciati:</b> ' + self.escapeHtml(String(summary.artifacts_tracked || 0)) + '</div>'
                + '<hr class="my-2">'
                + '<div><b>Orfani installazione:</b> ' + self.escapeHtml(String(orphanRows)) + '</div>'
                + '<div><b>Artifact orfani:</b> ' + self.escapeHtml(String(orphanArtifacts)) + '</div>'
                + '<div><b>Attivi senza artifact:</b> ' + self.escapeHtml(String(activeWithoutArtifacts)) + '</div>'
                + '<hr class="my-2">'
                + '<div><b>Privacy non dichiarata:</b> ' + self.escapeHtml(String(privacyMissing)) + '</div>'
                + '<div><b>Privacy incompleta/incoerente:</b> ' + self.escapeHtml(String(privacyIncomplete)) + '</div>'
                + '</div>';

            if (orphanRows > 0 || orphanArtifacts > 0 || activeWithoutArtifacts > 0 || privacyMissing > 0 || privacyIncomplete > 0) {
                if (typeof Dialog === 'function') {
                    Dialog('warning', { title: 'Audit governance moduli', body: body }, function () {}).show();
                } else {
                    Toast.show({ body: 'Audit completato con criticita. Apri i log audit.', type: 'warning' });
                }
            } else {
                Toast.show({ body: 'Audit moduli completato: nessuna criticita rilevata.', type: 'success' });
            }
        });
    },

    confirmAction: function (title, body, onConfirm, onCancel) {
        if (typeof Dialog === 'function') {
            var dialogRef = null;
            var confirmed = false;
            var dialogModal = null;
            var hiddenNs = '.admin-modules-confirm';

            if (globalWindow.SystemDialogs && typeof globalWindow.SystemDialogs.getGeneralConfirmModal === 'function') {
                dialogModal = globalWindow.SystemDialogs.getGeneralConfirmModal();
            }
            if (dialogModal && dialogModal.length) {
                dialogModal.off('hidden.bs.modal' + hiddenNs).on('hidden.bs.modal' + hiddenNs, function () {
                    dialogModal.off('hidden.bs.modal' + hiddenNs);
                    if (!confirmed && typeof onCancel === 'function') {
                        onCancel();
                    }
                });
            }

            dialogRef = Dialog('warning', { title: title, body: '<p>' + body + '</p>' }, function () {
                confirmed = true;
                if (dialogRef && typeof dialogRef.hide === 'function') {
                    dialogRef.hide();
                }
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
            dialogRef.show();
            return;
        }

        if (typeof onCancel === 'function') {
            onCancel();
        }
    },

    mountStatusSwitches: function () {
        if (!this.root || typeof globalWindow.$ !== 'function' || typeof globalWindow.SwitchGroup !== 'function') {
            return this;
        }

        var self = this;
        var inputs = globalWindow.$(this.root).find('input[data-role="admin-module-status-switch"]');
        inputs.each(function () {
            var field = globalWindow.$(this);
            var moduleId = String(field.attr('data-id') || '').trim();
            if (moduleId === '') {
                return;
            }

            var row = self.rowsById[moduleId] || null;
            var active = row ? (parseInt(row.is_active, 10) === 1) : (String(field.val()) === '1');
            var value = active ? '1' : '0';

            self.switchSyncLocks[moduleId] = true;
            field.val(value);
            self.switchSyncLocks[moduleId] = false;

            var switchInstance = field.data('__switchGroupInstance');
            if (!switchInstance || typeof switchInstance.refresh !== 'function') {
                switchInstance = globalWindow.SwitchGroup(field, {
                    preset: 'activeinactive',
                    trueValue: '1',
                    falseValue: '0'
                });
            } else {
                switchInstance.refresh();
            }

            field.off('change.admin-modules-switch').on('change.admin-modules-switch', function () {
                if (self.switchSyncLocks[moduleId] === true) {
                    return;
                }

                var currentRow = self.rowsById[moduleId] || row;
                if (!currentRow) {
                    return;
                }

                var currentActive = parseInt(currentRow.is_active, 10) === 1;
                var nextActive = String(field.val()) === '1';
                if (currentActive === nextActive) {
                    return;
                }

                var rollback = function () {
                    self.switchSyncLocks[moduleId] = true;
                    field.val(currentActive ? '1' : '0').change();
                    self.switchSyncLocks[moduleId] = false;

                    var currentSwitch = field.data('__switchGroupInstance');
                    if (currentSwitch && typeof currentSwitch.refresh === 'function') {
                        currentSwitch.refresh();
                    }
                };

                if (nextActive) {
                    self.activateModule(currentRow, { onCancelled: rollback });
                    return;
                }

                self.deactivateModule(currentRow, { onCancelled: rollback });
            });
        });

        return this;
    },

    dependencyState: function (dependencyId) {
        var row = this.rowsById[String(dependencyId)] || null;
        if (!row) {
            return 'missing';
        }

        if (parseInt(row.is_active, 10) === 1) {
            return 'active';
        }

        return 'inactive';
    },

    renderDependencies: function (row) {
        var required = Array.isArray(row.dependencies_required) ? row.dependencies_required : [];
        var optional = Array.isArray(row.dependencies_optional) ? row.dependencies_optional : [];
        var parts = [];

        if (!required.length && !optional.length) {
            return '<span class="text-muted">Nessuna</span>';
        }

        var self = this;
        if (required.length) {
            var requiredHtml = required.map(function (dep) {
                var depId = dep && dep.id ? String(dep.id) : '';
                var state = self.dependencyState(depId);
                var badgeClass = 'text-bg-secondary';
                var suffix = ' (inattivo)';
                if (state === 'active') {
                    badgeClass = 'text-bg-success';
                    suffix = '';
                } else if (state === 'missing') {
                    badgeClass = 'text-bg-danger';
                    suffix = ' (mancante)';
                }
                return '<span class="badge ' + badgeClass + ' me-1">' + self.escapeHtml(depId + suffix) + '</span>';
            }).join('');
            parts.push('<div class="small mb-1"><b>Richieste:</b></div><div class="mb-1">' + requiredHtml + '</div>');
        }

        if (optional.length) {
            var optionalHtml = optional.map(function (dep) {
                var depId = dep && dep.id ? String(dep.id) : '';
                var state = self.dependencyState(depId);
                var badgeClass = (state === 'active') ? 'text-bg-info' : 'text-bg-secondary';
                return '<span class="badge ' + badgeClass + ' me-1">' + self.escapeHtml(depId) + '</span>';
            }).join('');
            parts.push('<div class="small mb-1"><b>Opzionali:</b></div><div>' + optionalHtml + '</div>');
        }

        return parts.join('');
    },

    activationBlockers: function (row) {
        var blockers = [];
        if (!row) {
            return blockers;
        }

        if (parseInt(row.core_compatible, 10) !== 1) {
            blockers.push('Compatibilita core non valida');
        }

        var required = Array.isArray(row.dependencies_required) ? row.dependencies_required : [];
        for (var i = 0; i < required.length; i++) {
            var dep = required[i] || {};
            var depId = String(dep.id || '').trim();
            if (!depId) {
                continue;
            }
            var depState = this.dependencyState(depId);
            if (depState === 'missing') {
                blockers.push('Dipendenza mancante: ' + depId);
            } else if (depState === 'inactive') {
                blockers.push('Dipendenza non attiva: ' + depId);
            }
        }

        return blockers;
    },

    deactivationDependents: function (row) {
        var dependents = [];
        if (!row) {
            return dependents;
        }
        var moduleId = String(row.id || '').trim();
        if (moduleId === '') {
            return dependents;
        }
        if (parseInt(row.is_active, 10) !== 1) {
            return dependents;
        }

        var dataset = Array.isArray(this.rawDataset) ? this.rawDataset : [];
        for (var i = 0; i < dataset.length; i += 1) {
            var candidate = dataset[i] || {};
            if (parseInt(candidate.is_active, 10) !== 1) {
                continue;
            }
            var candidateId = String(candidate.id || '').trim();
            if (candidateId === '' || candidateId === moduleId) {
                continue;
            }
            var required = Array.isArray(candidate.dependencies_required) ? candidate.dependencies_required : [];
            for (var j = 0; j < required.length; j += 1) {
                var dep = required[j] || {};
                var depId = String(dep.id || '').trim();
                if (depId !== '' && depId === moduleId) {
                    dependents.push(candidateId);
                    break;
                }
            }
        }

        return dependents;
    },

    rowHasIssues: function (row) {
        if (!row) {
            return false;
        }
        if (String(row.status || '').toLowerCase() === 'error') {
            return true;
        }
        if (parseInt(row.core_compatible, 10) !== 1) {
            return true;
        }
        if (this.privacyIssues(row).length > 0) {
            return true;
        }
        return this.activationBlockers(row).length > 0;
    },

    renderNotes: function (row) {
        var notes = [];
        var blockers = this.activationBlockers(row);
        for (var i = 0; i < blockers.length; i++) {
            notes.push('<div class="small text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + this.escapeHtml(blockers[i]) + '</div>');
        }

        var isBundled = parseInt(row.is_bundled, 10) === 1;
        var isInstalled = parseInt(row.is_installed, 10) === 1;
        var isActive = parseInt(row.is_active, 10) === 1;
        if (isBundled) {
            notes.push('<div class="small text-muted"><i class="bi bi-box-seam me-1"></i>Modulo bundled: niente disinstallazione safe, purge dati disponibile quando inattivo.</div>');
        } else if (!isInstalled) {
            notes.push('<div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Modulo rilevato ma non installato: puoi usare purge per riallineare schema e metadati.</div>');
        } else if (!isActive) {
            notes.push('<div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Da inattivo puoi scegliere disinstallazione safe o purge dati.</div>');
        }

        var status = String(row.status || '').toLowerCase();
        if (status === 'error') {
            var lastError = String(row.last_error || '').trim();
            if (lastError !== '') {
                notes.push('<div class="small text-danger"><i class="bi bi-x-octagon-fill me-1"></i>' + this.escapeHtml(lastError) + '</div>');
            } else {
                notes.push('<div class="small text-danger"><i class="bi bi-x-octagon-fill me-1"></i>Modulo in stato errore</div>');
            }
        }

        var privacyIssues = this.privacyIssues(row);
        for (var j = 0; j < privacyIssues.length; j += 1) {
            var issue = privacyIssues[j] || {};
            var issueSeverity = String(issue.severity || 'warning').toLowerCase();
            var issueMessage = String(issue.message || '').trim();
            if (issueMessage === '') {
                continue;
            }
            if (issueSeverity === 'error') {
                notes.push('<div class="small text-danger"><i class="bi bi-shield-exclamation me-1"></i>' + this.escapeHtml(issueMessage) + '</div>');
            } else {
                notes.push('<div class="small text-warning"><i class="bi bi-shield-exclamation me-1"></i>' + this.escapeHtml(issueMessage) + '</div>');
            }
        }

        if (!notes.length) {
            return '<span class="text-muted small">Nessuna criticita</span>';
        }

        return notes.join('');
    },

    renderGovernance: function (row) {
        if (!row || !row.id) {
            return '<span class="text-muted small">-</span>';
        }

        var moduleId = this.escapeHtml(String(row.id || '').trim());
        var isInstalled = parseInt(row.is_installed, 10) === 1;
        var isActive = parseInt(row.is_active, 10) === 1;
        var isBundled = parseInt(row.is_bundled, 10) === 1;
        var canSafeUninstall = parseInt(row.can_uninstall_safe, 10) === 1;
        var canPurge = parseInt(row.can_purge, 10) === 1;
        var docsCount = parseInt(row.docs_count, 10) || 0;
        var privacyHtml = this.renderPrivacyGovernance(row);
        var docsHtml = docsCount > 0
            ? '<div class="mb-2"><button type="button" class="btn btn-sm btn-outline-primary" data-action="admin-module-docs-open" data-id="' + moduleId + '"><i class="bi bi-journal-text me-1"></i>Guide (' + docsCount + ')</button></div>'
            : '';

        if (isActive) {
            return ''
                + privacyHtml
                + docsHtml
                + '<span class="text-muted small d-block mb-1">Disattiva il modulo prima della disinstallazione.</span>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary" data-id="' + moduleId + '" disabled>'
                + (isBundled ? 'Purge dati' : 'Disinstalla')
                + '</button>';
        }

        if (!canSafeUninstall && !canPurge) {
            return privacyHtml + (isInstalled
                ? '<span class="text-muted small">Nessuna azione disponibile</span>'
                : '<span class="text-muted small">Non installato</span>');
        }

        var buttons = [];
        if (canSafeUninstall) {
            buttons.push('<button type="button" class="btn btn-outline-warning" data-action="admin-module-uninstall-safe" data-id="' + moduleId + '">Disinstalla</button>');
        }
        if (canPurge) {
            buttons.push('<button type="button" class="btn btn-outline-danger" data-action="admin-module-uninstall-purge" data-id="' + moduleId + '">' + (isBundled ? 'Purge dati' : 'Purge') + '</button>');
        }

        return ''
            + privacyHtml
            + docsHtml
            + '<div class="btn-group btn-group-sm" role="group">'
            + buttons.join('')
            + '</div>';
    },

    privacyIssues: function (row) {
        if (!row || !Array.isArray(row.privacy_validation_issues)) {
            return [];
        }
        return row.privacy_validation_issues.filter(function (issue) {
            return issue && typeof issue === 'object';
        });
    },

    renderPrivacyGovernance: function (row) {
        if (!row) {
            return '<span class="text-muted small d-block mb-2">Privacy non dichiarata</span>';
        }

        var declared = parseInt(row.privacy_declared, 10) === 1;
        var personalData = parseInt(row.privacy_personal_data, 10) === 1;
        var requiresConsent = parseInt(row.privacy_requires_consent, 10) === 1;
        var exportsData = parseInt(row.privacy_exports_user_data, 10) === 1;
        var supportsPurge = parseInt(row.privacy_supports_purge, 10) === 1;
        var issues = this.privacyIssues(row);
        var hasErrors = issues.some(function (issue) {
            return String(issue && issue.severity ? issue.severity : '').toLowerCase() === 'error';
        });
        var hasWarnings = issues.length > 0 && !hasErrors;

        var parts = [];
        if (!declared) {
            parts.push('<span class="badge text-bg-secondary me-1">Privacy non dichiarata</span>');
        } else if (hasErrors) {
            parts.push('<span class="badge text-bg-danger me-1">Privacy incompleta</span>');
        } else if (hasWarnings) {
            parts.push('<span class="badge text-bg-warning me-1">Privacy da verificare</span>');
        } else {
            parts.push('<span class="badge text-bg-success me-1">Privacy dichiarata</span>');
        }

        parts.push(personalData
            ? '<span class="badge text-bg-info me-1">Dati personali</span>'
            : '<span class="badge text-bg-light text-dark border me-1">No dati personali</span>');

        if (requiresConsent) {
            parts.push('<span class="badge text-bg-primary me-1">Consenso richiesto</span>');
        }
        if (exportsData) {
            parts.push('<span class="badge text-bg-secondary me-1">Export utente</span>');
        }
        if (supportsPurge) {
            parts.push('<span class="badge text-bg-dark me-1">Purge supportato</span>');
        }

        var retention = String(row.privacy_retention || '').trim();
        var retentionLine = retention !== ''
            ? '<div class="small text-muted mt-1">Retention: ' + this.escapeHtml(retention) + '</div>'
            : '';

        return '<div class="mb-2">' + parts.join('') + retentionLine + '</div>';
    },

    errorMessageByCode: function (code) {
        var key = String(code || '').trim();
        if (key === 'module_not_found') {
            return 'Modulo non trovato.';
        }
        if (key === 'module_not_installed') {
            return 'Modulo non installato.';
        }
        if (key === 'module_dependency_missing') {
            return 'Operazione bloccata: attiva prima le dipendenze richieste.';
        }
        if (key === 'module_deactivation_requires_confirmation') {
            return 'Disattivazione con dipendenze: conferma necessaria.';
        }
        if (key === 'module_incompatible_core') {
            return 'Versione core incompatibile con il modulo.';
        }
        if (key === 'module_activation_failed') {
            return 'Attivazione modulo non riuscita.';
        }
        if (key === 'module_deactivation_failed') {
            return 'Disattivazione modulo non riuscita.';
        }
        if (key === 'module_uninstall_requires_inactive') {
            return 'Disattiva prima il modulo per poterlo disinstallare.';
        }
        if (key === 'module_bundled_safe_uninstall_blocked') {
            return 'I moduli bundled non supportano la disinstallazione safe: usa purge dati quando sono inattivi.';
        }
        if (key === 'module_uninstall_failed') {
            return 'Disinstallazione modulo non riuscita.';
        }
        if (key === 'module_docs_not_available') {
            return 'Questo modulo non espone documentazione interna.';
        }
        if (key === 'module_doc_not_found') {
            return 'Documento non trovato.';
        }
        if (key === 'module_doc_too_large') {
            return 'Documento troppo grande per l\'anteprima interna.';
        }
        if (key === 'csrf_invalid') {
            return 'Sessione scaduta: aggiorna la pagina e riprova.';
        }
        return '';
    },

    parseErrorResponse: function (xhr) {
        var response = null;
        if (xhr && xhr.responseJSON && typeof xhr.responseJSON === 'object') {
            response = xhr.responseJSON;
        } else if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
            var raw = String(xhr.responseText || '');
            try {
                response = JSON.parse(raw);
            } catch (e) {
                var splitMatch = raw.split(/\}\s*\{/);
                if (splitMatch.length > 1) {
                    try {
                        response = JSON.parse(splitMatch[0] + '}');
                    } catch (ignore) {
                        response = null;
                    }
                }
            }
        }

        var code = response && typeof response === 'object'
            ? String(response.error_code || '').trim()
            : '';
        var message = '';
        if (response && typeof response === 'object') {
            message = String(response.error || response.message || '').trim();
        }

        return {
            response: response && typeof response === 'object' ? response : {},
            code: code,
            message: message,
            payload: response && typeof response === 'object' && response.payload && typeof response.payload === 'object'
                ? response.payload
                : {}
        };
    },

    requestErrorMessage: function (xhr) {
        var parsed = this.parseErrorResponse(xhr);
        var code = parsed.code;
        var byCode = this.errorMessageByCode(code);
        if (byCode !== '') {
            return byCode;
        }

        if (parsed.message !== '') {
            return parsed.message;
        }

        return 'Operazione non riuscita.';
    },

    requestPost: function (url, payload, onSuccess, onFail) {
        var self = this;
        var csrfToken = this.getCsrfToken();
        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        };
        if (csrfToken !== '') {
            headers['X-CSRF-Token'] = csrfToken;
        }

        if (typeof window.fetch !== 'function') {
            if (typeof onFail === 'function') {
                onFail({ statusText: 'HTTP client non disponibile.' });
            }
            Toast.show({ body: 'HTTP client non disponibile.', type: 'danger' });
            self.loadAll();
            return;
        }

        var body = new URLSearchParams();
        body.set('action', 'list');
        body.set('_csrf', csrfToken);
        body.set('data', JSON.stringify(payload || {}));

        window.fetch(url, {
            method: 'POST',
            headers: headers,
            body: body.toString()
        }).then(function (response) {
            return response.text().then(function (text) {
                var parsed = {};
                try { parsed = text ? JSON.parse(text) : {}; } catch (error) { parsed = {}; }
                if (!response.ok) {
                    throw {
                        responseJSON: parsed,
                        responseText: text,
                        statusText: response.statusText
                    };
                }
                return parsed;
            });
        }).then(function (response) {
            if (typeof onSuccess === 'function') {
                onSuccess(response || {});
            }
        }).catch(function (xhr) {
            if (typeof onFail === 'function') {
                var handled = onFail(xhr);
                if (handled === true) {
                    return;
                }
            }
            Toast.show({ body: self.requestErrorMessage(xhr), type: 'danger' });
            self.loadAll();
        });
    },

    getCsrfToken: function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            return '';
        }
        return String(meta.getAttribute('content') || '').trim();
    },

    statusBadge: function (status) {
        var key = String(status || '').trim().toLowerCase();
        if (key === 'active') {
            return '<span class="badge text-bg-success">Attivo</span>';
        }
        if (key === 'inactive') {
            return '<span class="badge text-bg-secondary">Inattivo</span>';
        }
        if (key === 'installed') {
            return '<span class="badge text-bg-info">Installato</span>';
        }
        if (key === 'error') {
            return '<span class="badge text-bg-danger">Errore</span>';
        }
        return '<span class="badge text-bg-warning">Rilevato</span>';
    },

    escapeHtml: function (value) {
        return $('<div/>').text(value || '').html();
    }
};

globalWindow.AdminModules = AdminModules;
export { AdminModules as AdminModules };
export default AdminModules;



