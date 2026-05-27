const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminMailDistributionLists = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,
    membersGrid: null,
    membersGridInit: false,
    modalNode: null,
    modal: null,
    modalForm: null,
    memberForm: null,
    membersSection: null,
    membersPanel: null,
    segmentRow: null,
    userPickerNode: null,
    userSearchInput: null,
    userResultsNode: null,
    rows: [],
    rowsById: {},
    editingRow: null,

    init: function () {
        if (this.initialized) { return this; }

        this.root = document.querySelector('#admin-page [data-admin-page="mail-distribution-lists"]');
        if (!this.root) { return this; }

        this.filtersForm    = this.root.querySelector('#admin-mail-lists-filters');
        this.modalNode      = this.root.querySelector('#admin-mail-list-modal');
        this.modalForm      = this.root.querySelector('#admin-mail-list-form');
        this.memberForm     = this.root.querySelector('#admin-mail-list-member-form');
        this.membersSection = this.root.querySelector('#admin-mail-list-members-section');
        this.membersPanel   = this.root.querySelector('#admin-mail-list-members-panel');
        this.segmentRow     = this.root.querySelector('#admin-mail-list-segment-row');
        this.userPickerNode = this.root.querySelector('#admin-mail-list-user-picker');
        this.userSearchInput = this.root.querySelector('#admin-mail-list-user-search');
        this.userResultsNode = this.root.querySelector('#admin-mail-list-user-results');

        if (!this.filtersForm || !this.modalNode || !this.modalForm) {
            return this;
        }

        this.modal = new bootstrap.Modal(this.modalNode);

        this.bind();
        this.initGrid();
        this.loadGrid();

        this.initialized = true;
        return this;
    },

    bind: function () {
        var self = this;

        this.filtersForm.addEventListener('submit', function (event) {
            event.preventDefault();
            self.loadGrid();
        });

        this.modalNode.addEventListener('change', function (event) {
            var trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            if (trigger.getAttribute('data-action') === 'admin-mail-list-type-change') {
                self.onTypeChange(trigger.value);
            }
        });

        if (this.userSearchInput) {
            this.userSearchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    self.searchUsers(self.userSearchInput.value.trim());
                }
            });
        }

        this.root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            var action = String(trigger.getAttribute('data-action') || '').trim();

            if (action === 'admin-mail-lists-reload') {
                event.preventDefault();
                self.loadGrid();
            } else if (action === 'admin-mail-lists-create') {
                event.preventDefault();
                self.openCreate();
            } else if (action === 'admin-mail-list-edit') {
                event.preventDefault();
                var id = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.openEdit(id);
            } else if (action === 'admin-mail-list-save') {
                event.preventDefault();
                self.save();
            } else if (action === 'admin-mail-list-delete') {
                event.preventDefault();
                self.remove();
            } else if (action === 'admin-mail-list-member-add-open') {
                event.preventDefault();
                self.showUserPicker(false);
                self.showMemberForm(true);
            } else if (action === 'admin-mail-list-member-add-cancel') {
                event.preventDefault();
                self.showMemberForm(false);
            } else if (action === 'admin-mail-list-member-add') {
                event.preventDefault();
                self.addMember();
            } else if (action === 'admin-mail-list-member-remove') {
                event.preventDefault();
                var memberId = parseInt(trigger.getAttribute('data-member-id') || '0', 10);
                if (memberId) { self.removeMember(memberId); }
            } else if (action === 'admin-mail-list-user-picker-open') {
                event.preventDefault();
                self.showMemberForm(false);
                self.showUserPicker(true);
            } else if (action === 'admin-mail-list-user-picker-cancel') {
                event.preventDefault();
                self.showUserPicker(false);
            } else if (action === 'admin-mail-list-user-search') {
                event.preventDefault();
                if (self.userSearchInput) { self.searchUsers(self.userSearchInput.value.trim()); }
            } else if (action === 'admin-mail-list-user-picker-add') {
                event.preventDefault();
                self.addSelectedUsers();
            }
        });
    },

    // ── Main grid ─────────────────────────────────────────────────────────

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-mail-lists', {
            name: 'AdminMailLists',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/mail-lists/list', action: 'list' },
            nav: { display: 'bottom', urlupdate: 0, results: 20, page: 1 },
            onGetDataSuccess: function (response) {
                self.setRows(response && Array.isArray(response.dataset) ? response.dataset : []);
            },
            onGetDataError: function () {
                self.setRows([]);
            },
            columns: [
                {
                    label: 'ID',
                    field: 'id',
                    sortable: true,
                    style: { textAlign: 'center', width: '60px' }
                },
                {
                    label: 'Nome',
                    field: 'name',
                    sortable: true,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.escapeHtml(row.name || '-');
                    }
                },
                {
                    label: 'Tipo',
                    field: 'type',
                    sortable: true,
                    style: { textAlign: 'center', width: '110px' },
                    format: function (row) {
                        return row.type === 'dynamic' ? 'Dinamica' : 'Manuale';
                    }
                },
                {
                    label: 'Destinatari',
                    field: 'member_count',
                    sortable: false,
                    style: { textAlign: 'center', width: '110px' },
                    format: function (row) {
                        if (row.type === 'dynamic') { return '<span class="text-muted">dinamico</span>'; }
                        return self.escapeHtml(String(row.member_count || 0));
                    }
                },
                {
                    label: 'Azioni',
                    sortable: false,
                    style: { textAlign: 'center', width: '80px' },
                    format: function (row) {
                        var id = self.escapeAttr(String(row.id || ''));
                        return '<button type="button" class="btn btn-sm btn-outline-secondary"'
                            + ' data-action="admin-mail-list-edit" data-id="' + id + '">'
                            + '<i class="bi bi-pencil"></i></button>';
                    }
                }
            ]
        });
    },

    setRows: function (rows) {
        this.rows     = rows || [];
        this.rowsById = {};
        for (var i = 0; i < this.rows.length; i++) {
            var r = this.rows[i];
            if (r && r.id) { this.rowsById[r.id] = r; }
        }
    },

    loadGrid: function () {
        if (!this.grid || typeof this.grid.loadData !== 'function') { return this; }
        this.grid.loadData(this.buildFiltersPayload(), 20, 1, 'id|DESC');
        return this;
    },

    buildFiltersPayload: function () {
        var q = {};
        if (this.filtersForm) {
            var query = (this.filtersForm.querySelector('[name="query"]') || {}).value || '';
            if (query) { q.query = query; }
        }
        return q;
    },

    // ── Members grid ──────────────────────────────────────────────────────

    initMembersGrid: function () {
        if (this.membersGridInit) { return; }
        this.membersGridInit = true;

        var self = this;

        this.membersGrid = new Datagrid('grid-admin-mail-list-members', {
            name: 'AdminMailListMembers',
            autoindex: 'id',
            orderable: false,
            thead: true,
            handler: { url: '/admin/mail-lists/members/list', action: 'list' },
            nav: { display: 'bottom', urlupdate: 0, results: 50, page: 1 },
            columns: [
                {
                    label: 'Email',
                    field: 'email',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.escapeHtml(row.email || '-');
                    }
                },
                {
                    label: 'Nome visualizzato',
                    field: 'display_name',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.escapeHtml(row.display_name || '');
                    }
                },
                {
                    label: 'Azioni',
                    sortable: false,
                    style: { textAlign: 'center', width: '80px' },
                    format: function (row) {
                        var memberId = self.escapeAttr(String(row.id || ''));
                        return '<button type="button" class="btn btn-sm btn-outline-danger"'
                            + ' data-action="admin-mail-list-member-remove" data-member-id="' + memberId + '">'
                            + '<i class="bi bi-trash"></i></button>';
                    }
                }
            ]
        });
    },

    loadMembers: function (listId) {
        this.initMembersGrid();
        if (!this.membersGrid || typeof this.membersGrid.loadData !== 'function') { return; }
        this.membersGrid.loadData({ list_id: listId }, 50, 1, '');
    },

    // ── User picker ───────────────────────────────────────────────────────

    showUserPicker: function (show) {
        if (this.userPickerNode) { this.userPickerNode.classList.toggle('d-none', !show); }
        if (show) {
            if (this.userSearchInput) { this.userSearchInput.value = ''; }
            if (this.userResultsNode) {
                this.userResultsNode.innerHTML = '<div class="text-muted small text-center py-2">Digita per cercare utenti.</div>';
            }
            if (this.userSearchInput) { this.userSearchInput.focus(); }
        }
    },

    searchUsers: function (q) {
        var self = this;
        this.post('/admin/mail-lists/users/search', { q: q, limit: 30 }, function (res) {
            self.renderUserResults((res && Array.isArray(res.dataset)) ? res.dataset : []);
        });
    },

    renderUserResults: function (users) {
        if (!this.userResultsNode) { return; }
        if (!users || users.length === 0) {
            this.userResultsNode.innerHTML = '<div class="text-muted small text-center py-2">Nessun utente trovato.</div>';
            return;
        }
        var self = this;
        var html = '';
        users.forEach(function (u) {
            var id = String(u.id || '');
            var email = self.escapeHtml(String(u.email || ''));
            var characterName = self.escapeHtml(String(u.character_name || ''));
            html += '<div class="form-check py-2 border-bottom">'
                + '<input class="form-check-input" type="checkbox" value="' + id + '" id="upick-' + id + '">'
                + '<label class="form-check-label" for="upick-' + id + '">'
                + '<strong class="d-block">' + email + '</strong>'
                + (characterName !== '' ? '<span class="text-muted small d-block">Personaggio: ' + characterName + '</span>' : '')
                + '</label></div>';
        });
        this.userResultsNode.innerHTML = html;
    },

    addSelectedUsers: function () {
        if (!this.editingRow || !this.editingRow.id) { return; }
        if (!this.userResultsNode) { return; }

        var checkboxes = this.userResultsNode.querySelectorAll('input[type="checkbox"]:checked');
        var userIds = [];
        checkboxes.forEach(function (cb) {
            var id = parseInt(cb.value, 10);
            if (id > 0) { userIds.push(id); }
        });

        if (userIds.length === 0) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Seleziona almeno un utente.', type: 'warning' }); }
            return;
        }

        var self   = this;
        var listId = this.editingRow.id;

        this.post('/admin/mail-lists/members/add-bulk', { list_id: listId, user_ids: userIds }, function (res) {
            var added = res && res.added ? res.added : 0;
            self.showUserPicker(false);
            self.loadMembers(listId);
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: added + (added === 1 ? ' utente aggiunto.' : ' utenti aggiunti.'), type: 'success' });
            }
        });
    },

    // ── Modal ─────────────────────────────────────────────────────────────

    openCreate: function () {
        this.editingRow = null;
        if (this.modalForm) { this.modalForm.reset(); }
        this.setField('id', '');
        this.setField('type', 'manual');
        this.onTypeChange('manual');
        this.toggleDelete(false);
        this.toggleMembersPanel(false);
        this.showMemberForm(false);
        this.showUserPicker(false);
        this.modal.show();
    },

    openEdit: function (id) {
        var row = this.rowsById[id] || null;
        if (!row) { return; }
        this.editingRow = row;
        if (this.modalForm) { this.modalForm.reset(); }
        this.setField('id', String(row.id));
        this.setField('name', row.name || '');
        this.setField('type', row.type || 'manual');
        this.setField('description', row.description || '');
        this.setField('segment_key', row.segment_key || '');
        this.onTypeChange(row.type || 'manual');
        this.toggleDelete(true);
        var isManual = (row.type || 'manual') === 'manual';
        this.toggleMembersPanel(isManual);
        if (isManual) { this.loadMembers(row.id); }
        this.showMemberForm(false);
        this.showUserPicker(false);
        this.modal.show();
    },

    onTypeChange: function (type) {
        if (!this.segmentRow) { return; }
        var isDynamic = (type === 'dynamic');
        this.segmentRow.classList.toggle('d-none', !isDynamic);
        if (!isDynamic) { this.setField('segment_key', ''); }
    },

    toggleDelete: function (show) {
        if (!this.modalNode) { return; }
        var btn = this.modalNode.querySelector('[data-action="admin-mail-list-delete"]');
        if (btn) { btn.classList.toggle('d-none', !show); }
    },

    toggleMembersPanel: function (show) {
        if (this.membersSection) { this.membersSection.classList.toggle('d-none', !show); }
        if (this.membersPanel)   { this.membersPanel.classList.toggle('d-none', !show); }
    },

    showMemberForm: function (show) {
        if (!this.memberForm) { return; }
        this.memberForm.classList.toggle('d-none', !show);
        if (show) { this.memberForm.reset(); }
    },

    setField: function (name, value) {
        if (!this.modalForm) { return; }
        var el = this.modalForm.querySelector('[name="' + name + '"]');
        if (el) { el.value = value; }
    },

    getField: function (name) {
        if (!this.modalForm) { return ''; }
        var el = this.modalForm.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    },

    getMemberField: function (name) {
        if (!this.memberForm) { return ''; }
        var el = this.memberForm.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    },

    collectPayload: function () {
        return {
            id:          parseInt(this.getField('id'), 10) || 0,
            name:        this.getField('name').trim(),
            type:        this.getField('type'),
            description: this.getField('description').trim(),
            segment_key: this.getField('segment_key')
        };
    },

    save: function () {
        var payload = this.collectPayload();
        if (!payload.name) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Il nome è obbligatorio.', type: 'error' }); }
            return;
        }

        var isNew = !payload.id;
        var url   = isNew ? '/admin/mail-lists/create' : '/admin/mail-lists/update';
        var self  = this;

        this.post(url, payload, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: isNew ? 'Lista creata.' : 'Lista aggiornata.', type: 'success' });
            }
            self.loadGrid();
        });
    },

    remove: function () {
        var payload = this.collectPayload();
        if (!payload.id) { return; }
        if (!confirm("Eliminare questa lista? L'operazione non può essere annullata.")) { return; }

        var self = this;
        this.post('/admin/mail-lists/delete', { id: payload.id }, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Lista eliminata.', type: 'success' }); }
            self.loadGrid();
        });
    },

    addMember: function () {
        if (!this.editingRow || !this.editingRow.id) { return; }

        var email       = this.getMemberField('email').trim();
        var displayName = this.getMemberField('display_name').trim();

        if (!email) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "L'email è obbligatoria.", type: 'error' }); }
            return;
        }

        var self   = this;
        var listId = this.editingRow.id;

        this.post('/admin/mail-lists/members/add', { list_id: listId, email: email, display_name: displayName }, function () {
            self.showMemberForm(false);
            self.loadMembers(listId);
        });
    },

    removeMember: function (memberId) {
        if (!confirm('Rimuovere questo destinatario?')) { return; }
        var self   = this;
        var listId = this.editingRow ? this.editingRow.id : 0;

        this.post('/admin/mail-lists/members/remove', { member_id: memberId }, function () {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Destinatario rimosso.', type: 'success' }); }
            if (listId) { self.loadMembers(listId); }
        });
    },

    // ── HTTP helper ────────────────────────────────────────────────────────

    post: function (url, payload, onSuccess, onError) {
        if (typeof Request !== 'function' || !Request.http || typeof Request.http.post !== 'function') {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Servizio non disponibile.', type: 'error' }); }
            return this;
        }
        Request.http.post(url, payload || {}).then(function (response) {
            if (typeof onSuccess === 'function') { onSuccess(response || null); }
        }).catch(function (error) {
            if (typeof onError === 'function') {
                onError(error);
            } else if (typeof Toast !== 'undefined') {
                var msg = (error && error.message) ? error.message : 'Errore di rete.';
                Toast.show({ body: msg, type: 'error' });
            }
        });
    },

    // ── Utils ──────────────────────────────────────────────────────────────

    escapeHtml: function (str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    escapeAttr: function (str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
};

globalWindow.AdminMailDistributionLists = AdminMailDistributionLists;
export { AdminMailDistributionLists };
export default AdminMailDistributionLists;
