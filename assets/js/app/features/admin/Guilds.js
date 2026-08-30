const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminGuilds = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,
    modalNode: null,
    modal: null,
    modalForm: null,
    roleFormWrap: null,
    roleForm: null,
    rolesTbody: null,
    rolesSection: null,
    membersTbody: null,
    membersSection: null,
    memberSearchInput: null,
    memberCharacterId: null,
    memberSuggestionsBox: null,
    memberRoleSelect: null,
    memberSearchTimer: null,
    rows: [],
    rowsById: {},
    alignments: [],
    editingRow: null,
    editingGuildId: 0,

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.querySelector('#admin-page [data-admin-page="guilds"]');
        if (!this.root) {
            return this;
        }

        this.filtersForm  = this.root.querySelector('#admin-guilds-filters');
        this.modalNode    = this.root.querySelector('#admin-guilds-modal');
        this.modalForm    = this.root.querySelector('#admin-guilds-form');
        this.roleFormWrap = this.root.querySelector('[data-role="admin-guilds-role-form-wrap"]');
        this.roleForm     = this.root.querySelector('#admin-guilds-role-form');
        this.rolesTbody   = this.root.querySelector('#admin-guilds-roles-tbody');
        this.rolesSection = this.root.querySelector('[data-role="admin-guilds-roles-section"]');
        this.membersTbody       = this.root.querySelector('#admin-guilds-members-tbody');
        this.membersSection     = this.root.querySelector('[data-role="admin-guilds-members-section"]');
        this.memberSearchInput  = this.root.querySelector('#admin-guilds-member-search');
        this.memberCharacterId  = this.root.querySelector('#admin-guilds-member-character-id');
        this.memberSuggestionsBox = this.root.querySelector('#admin-guilds-member-suggestions');
        this.memberRoleSelect   = this.root.querySelector('#admin-guilds-member-role');

        if (!this.filtersForm || !this.modalNode || !this.modalForm) {
            return this;
        }

        this.modal = new bootstrap.Modal(this.modalNode);
        this.bind();
        this.bindIconPreview();
        this.bindMemberSearch();
        this.initGrid();
        this.loadAlignments(function () {
            this.loadGrid();
        }.bind(this));

        this.initialized = true;
        return this;
    },

    bind: function () {
        var self = this;

        this.filtersForm.addEventListener('submit', function (event) {
            event.preventDefault();
            self.loadGrid();
        });

        this.root.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) { return; }
            var action = String(trigger.getAttribute('data-action') || '').trim();

            if (action === 'admin-guilds-reload') {
                event.preventDefault();
                self.loadGrid();
            } else if (action === 'admin-guilds-create') {
                event.preventDefault();
                self.openCreate();
            } else if (action === 'admin-guilds-edit') {
                event.preventDefault();
                var id = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.openEdit(id);
            } else if (action === 'admin-guilds-save') {
                event.preventDefault();
                self.save();
            } else if (action === 'admin-guilds-delete') {
                event.preventDefault();
                self.remove();
            } else if (action === 'admin-guilds-role-new') {
                event.preventDefault();
                self.openRoleForm(null);
            } else if (action === 'admin-guilds-role-edit') {
                event.preventDefault();
                var roleId = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.openRoleFormById(roleId);
            } else if (action === 'admin-guilds-role-save') {
                event.preventDefault();
                self.saveRole();
            } else if (action === 'admin-guilds-role-form-cancel') {
                event.preventDefault();
                self.hideRoleForm();
            } else if (action === 'admin-guilds-role-delete') {
                event.preventDefault();
                var rid = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.removeRole(rid);
            } else if (action === 'admin-guilds-member-add') {
                event.preventDefault();
                self.addMember();
            } else if (action === 'admin-guilds-member-remove') {
                event.preventDefault();
                var rmCharId = parseInt(trigger.getAttribute('data-character-id') || '0', 10);
                self.removeMember(rmCharId);
            }
        });
    },

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-guilds', {
            name: 'AdminGuilds',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/guilds/admin-list', action: 'list' },
            nav: { display: 'bottom', urlupdate: 0, results: 30, page: 1 },
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
                    style: { textAlign: 'left', width: '200px' },
                    format: function (row) {
                        return self.escapeHtml(row.name || '-');
                    }
                },
                {
                    label: 'Allineamento',
                    field: 'alignment_name',
                    sortable: true,
                    style: { textAlign: 'left', width: '150px' },
                    format: function (row) {
                        return row.alignment_name
                            ? self.escapeHtml(row.alignment_name)
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    label: 'Visibile',
                    field: 'is_visible',
                    sortable: true,
                    style: { textAlign: 'center', width: '90px' },
                    format: function (row) {
                        return parseInt(row.is_visible || 0, 10) === 1
                            ? '<span class="badge text-bg-success">Sì</span>'
                            : '<span class="badge text-bg-secondary">No</span>';
                    }
                },
                {
                    label: 'Sito web',
                    field: 'website_url',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        if (!row.website_url) { return '<span class="text-muted">—</span>'; }
                        return '<a href="' + self.escapeAttr(row.website_url) + '" target="_blank" rel="noopener" class="small">' + self.escapeHtml(row.website_url) + '</a>';
                    }
                },
                {
                    label: 'Azioni',
                    sortable: false,
                    style: { textAlign: 'center', width: '80px' },
                    format: function (row) {
                        var id = self.escapeAttr(String(row.id || ''));
                        return '<button type="button" class="btn btn-sm btn-outline-secondary"'
                            + ' data-action="admin-guilds-edit" data-id="' + id + '">'
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
        this.grid.loadData(this.buildFiltersPayload(), 30, 1, 'name|ASC');
        return this;
    },

    buildFiltersPayload: function () {
        var q = {};
        if (this.filtersForm) {
            var name        = (this.filtersForm.querySelector('[name="name"]') || {}).value || '';
            var alignmentId = (this.filtersForm.querySelector('[name="alignment_id"]') || {}).value || '';
            var isVisible   = (this.filtersForm.querySelector('[name="is_visible"]') || {}).value || '';
            if (name)        { q.name = name; }
            if (alignmentId) { q.alignment_id = alignmentId; }
            if (isVisible !== '') { q.is_visible = isVisible; }
        }
        return q;
    },

    // ── Alignments ────────────────────────────────────────────────────────

    loadAlignments: function (cb) {
        var self = this;
        this.post('/admin/guild-alignments/list', { query: {}, page: 1, results: 200 }, function (res) {
            self.alignments = (res && Array.isArray(res.dataset)) ? res.dataset : [];
            self.fillAlignmentSelects();
            if (typeof cb === 'function') { cb(); }
        }, function () {
            if (typeof cb === 'function') { cb(); }
        });
    },

    fillAlignmentSelects: function () {
        var selects = [
            this.filtersForm ? this.filtersForm.querySelector('[name="alignment_id"]') : null,
            this.modalForm   ? this.modalForm.querySelector('[name="alignment_id"]')   : null
        ];

        for (var s = 0; s < selects.length; s++) {
            var sel = selects[s];
            if (!sel) { continue; }
            var currentVal = sel.value;
            while (sel.options.length > 1) { sel.remove(1); }
            for (var i = 0; i < this.alignments.length; i++) {
                var a = this.alignments[i];
                var opt = document.createElement('option');
                opt.value = String(a.id);
                opt.textContent = a.name || String(a.id);
                sel.appendChild(opt);
            }
            if (currentVal) { sel.value = currentVal; }
        }
    },

    // ── Modal ─────────────────────────────────────────────────────────────

    openCreate: function () {
        this.editingRow    = null;
        this.editingGuildId = 0;
        if (this.modalForm) { this.modalForm.reset(); }
        this.setField('id', '');
        this.setField('is_visible', '0');
        this.toggleDelete(false);
        this.hideRoleForm();
        if (this.rolesSection) { this.rolesSection.classList.add('d-none'); }
        if (this.membersSection) { this.membersSection.classList.add('d-none'); }
        this.fillAlignmentSelects();
        this.modal.show();
    },

    openEdit: function (id) {
        var row = this.rowsById[id] || null;
        if (!row) { return; }
        this.editingRow     = row;
        this.editingGuildId = parseInt(row.id, 10) || 0;
        this.setField('id', String(row.id));
        this.setField('name', row.name || '');
        this.setField('alignment_id', String(row.alignment_id || ''));
        this.setField('is_visible', String(row.is_visible || 0));
        this.setField('image', row.image || '');
        this.setField('icon', row.icon || '');
        this.setField('website_url', row.website_url || '');
        this.updateIconPreview('admin-guilds-icon-preview', row.icon || '');
        this.toggleDelete(true);
        this.hideRoleForm();
        if (this.rolesSection) { this.rolesSection.classList.remove('d-none'); }
        if (this.membersSection) { this.membersSection.classList.remove('d-none'); }
        this.fillAlignmentSelects();
        this.loadRoles(this.editingGuildId);
        this.loadMembers(this.editingGuildId);
        this.modal.show();
    },

    toggleDelete: function (show) {
        if (!this.modalNode) { return; }
        var btn = this.modalNode.querySelector('[data-action="admin-guilds-delete"]');
        if (btn) { btn.classList.toggle('d-none', !show); }
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

    collectPayload: function () {
        return {
            id:           parseInt(this.getField('id'), 10) || 0,
            name:         this.getField('name').trim(),
            alignment_id: parseInt(this.getField('alignment_id'), 10) || 0,
            is_visible:   parseInt(this.getField('is_visible'), 10) || 0,
            image:        this.getField('image').trim(),
            icon:         this.getField('icon').trim(),
            website_url:  this.getField('website_url').trim()
        };
    },

    save: function () {
        var payload = this.collectPayload();
        if (!payload.name) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Il nome è obbligatorio.', type: 'error' }); }
            return;
        }

        var isNew = !payload.id;
        var url   = isNew ? '/admin/guilds/admin-create' : '/admin/guilds/admin-update';
        var self  = this;

        this.post(url, payload, function (res) {
            if (isNew && res && res.id) {
                self.editingGuildId = parseInt(res.id, 10) || 0;
                self.setField('id', String(self.editingGuildId));
                self.toggleDelete(true);
                if (self.rolesSection) { self.rolesSection.classList.remove('d-none'); }
                self.loadRoles(self.editingGuildId);
            } else {
                self.modal.hide();
            }
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: isNew ? 'Gilda creata.' : 'Gilda aggiornata.', type: 'success' });
            }
            self.loadGrid();
        });
    },

    remove: async function () {
        var payload = this.collectPayload();
        if (!payload.id) { return; }
        if (!(await AdminDialogs.confirmPromise({ type: 'danger', title: 'Conferma eliminazione', body: '<p>' + 'Eliminare questa gilda e tutti i suoi ruoli? L\'operazione non può essere annullata.' + '</p>', confirmLabel: 'Elimina' }))) { return; }

        var self = this;
        this.post('/admin/guilds/admin-delete', { id: payload.id }, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Gilda eliminata.', type: 'success' }); }
            self.loadGrid();
        });
    },

    // ── Roles ──────────────────────────────────────────────────────────────

    loadRoles: function (guildId) {
        var self = this;
        this.post('/admin/guilds/roles-list', { guild_id: guildId }, function (res) {
            self.renderRoles(Array.isArray(res && res.roles) ? res.roles : []);
        }, function () {
            self.renderRoles([]);
        });
    },

    renderRoles: function (roles) {
        if (!this.rolesTbody) { return; }
        var self = this;
        this.rolesTbody.innerHTML = '';
        this.renderMemberRoleOptions(Array.isArray(roles) ? roles : []);

        if (!roles || !roles.length) {
            var emptyRow = document.createElement('tr');
            emptyRow.setAttribute('data-role', 'admin-guilds-roles-empty');
            emptyRow.innerHTML = '<td colspan="6" class="text-muted text-center">Nessun ruolo.</td>';
            this.rolesTbody.appendChild(emptyRow);
            return;
        }

        for (var i = 0; i < roles.length; i++) {
            var role = roles[i];
            var tr   = document.createElement('tr');
            tr.setAttribute('data-role-id', String(role.id));
            tr.setAttribute('data-role-data', JSON.stringify(role));

            var salary = parseInt(role.monthly_salary || 0, 10);
            var leaderBadge  = parseInt(role.is_leader  || 0, 10) === 1 ? '<span class="badge text-bg-warning text-dark">Sì</span>'  : '<span class="text-muted">—</span>';
            var officerBadge = parseInt(role.is_officer || 0, 10) === 1 ? '<span class="badge text-bg-info">Sì</span>'    : '<span class="text-muted">—</span>';
            var defaultBadge = parseInt(role.is_default || 0, 10) === 1 ? '<span class="badge text-bg-primary">Sì</span>' : '<span class="text-muted">—</span>';

            var rid = self.escapeAttr(String(role.id));
            tr.innerHTML =
                '<td>' + self.escapeHtml(role.name || '-') + '</td>'
                + '<td class="text-center">' + salary + '</td>'
                + '<td class="text-center">' + leaderBadge + '</td>'
                + '<td class="text-center">' + officerBadge + '</td>'
                + '<td class="text-center">' + defaultBadge + '</td>'
                + '<td class="text-center">'
                +   '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-action="admin-guilds-role-edit" data-id="' + rid + '"><i class="bi bi-pencil"></i></button>'
                +   '<button type="button" class="btn btn-sm btn-outline-danger" data-action="admin-guilds-role-delete" data-id="' + rid + '"><i class="bi bi-trash"></i></button>'
                + '</td>';

            this.rolesTbody.appendChild(tr);
        }
    },

    openRoleForm: function (roleData) {
        if (!this.roleForm || !this.roleFormWrap) { return; }
        this.roleForm.reset();
        this.setRoleField('role_id', '');
        this.setRoleField('role_guild_id', String(this.editingGuildId));
        this.setRoleField('role_name', '');
        this.setRoleField('role_monthly_salary', '0');
        this.setRoleField('role_image', '');
        this.setRoleCheckbox('role_is_leader', false);
        this.setRoleCheckbox('role_is_officer', false);
        this.setRoleCheckbox('role_is_default', false);

        var title = this.roleFormWrap.querySelector('[data-role="admin-guilds-role-form-title"]');
        if (title) { title.textContent = roleData ? 'Modifica ruolo' : 'Nuovo ruolo'; }

        if (roleData) {
            this.setRoleField('role_id', String(roleData.id));
            this.setRoleField('role_name', roleData.name || '');
            this.setRoleField('role_monthly_salary', String(roleData.monthly_salary || 0));
            this.setRoleField('role_image', roleData.image || '');
            this.setRoleCheckbox('role_is_leader', parseInt(roleData.is_leader || 0, 10) === 1);
            this.setRoleCheckbox('role_is_officer', parseInt(roleData.is_officer || 0, 10) === 1);
            this.setRoleCheckbox('role_is_default', parseInt(roleData.is_default || 0, 10) === 1);
        }

        this.roleFormWrap.classList.remove('d-none');
    },

    openRoleFormById: function (id) {
        if (!this.rolesTbody) { return; }
        var row = this.rolesTbody.querySelector('[data-role-id="' + id + '"]');
        if (!row) { return; }
        try {
            var data = JSON.parse(row.getAttribute('data-role-data') || '{}');
            this.openRoleForm(data);
        } catch (e) {}
    },

    hideRoleForm: function () {
        if (this.roleFormWrap) { this.roleFormWrap.classList.add('d-none'); }
        if (this.roleForm) { this.roleForm.reset(); }
    },

    setRoleField: function (name, value) {
        if (!this.roleForm) { return; }
        var el = this.roleForm.querySelector('[name="' + name + '"]');
        if (el) { el.value = value; }
    },

    getRoleField: function (name) {
        if (!this.roleForm) { return ''; }
        var el = this.roleForm.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    },

    setRoleCheckbox: function (name, checked) {
        if (!this.roleForm) { return; }
        var el = this.roleForm.querySelector('[name="' + name + '"]');
        if (el) { el.checked = checked; }
    },

    getRoleCheckbox: function (name) {
        if (!this.roleForm) { return false; }
        var el = this.roleForm.querySelector('[name="' + name + '"]');
        return el ? el.checked : false;
    },

    collectRolePayload: function () {
        return {
            id:             parseInt(this.getRoleField('role_id'), 10) || 0,
            guild_id:       parseInt(this.getRoleField('role_guild_id'), 10) || this.editingGuildId,
            name:           this.getRoleField('role_name').trim(),
            image:          this.getRoleField('role_image').trim(),
            monthly_salary: parseInt(this.getRoleField('role_monthly_salary'), 10) || 0,
            is_leader:      this.getRoleCheckbox('role_is_leader') ? 1 : 0,
            is_officer:     this.getRoleCheckbox('role_is_officer') ? 1 : 0,
            is_default:     this.getRoleCheckbox('role_is_default') ? 1 : 0
        };
    },

    saveRole: function () {
        var payload = this.collectRolePayload();
        if (!payload.name) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Il nome del ruolo è obbligatorio.', type: 'error' }); }
            return;
        }
        if (!payload.guild_id) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Salva prima la gilda.', type: 'error' }); }
            return;
        }

        var isNew = !payload.id;
        var url   = isNew ? '/admin/guilds/roles-create' : '/admin/guilds/roles-update';
        var self  = this;

        this.post(url, payload, function () {
            self.hideRoleForm();
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: isNew ? 'Ruolo creato.' : 'Ruolo aggiornato.', type: 'success' });
            }
            self.loadRoles(self.editingGuildId);
        });
    },

    removeRole: async function (id) {
        if (!id) { return; }
        if (!(await AdminDialogs.confirmPromise({ type: 'danger', title: 'Conferma eliminazione', body: '<p>' + 'Eliminare questo ruolo?' + '</p>', confirmLabel: 'Elimina' }))) { return; }
        var self = this;
        this.post('/admin/guilds/roles-delete', { id: id }, function () {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Ruolo eliminato.', type: 'success' }); }
            self.loadRoles(self.editingGuildId);
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
    },

    updateIconPreview: function (role, src) {
        if (!this.modalNode) { return; }
        var img = this.modalNode.querySelector('[data-role="' + role + '"]');
        if (!img) { return; }
        if (src) {
            img.src = src;
            img.style.display = '';
        } else {
            img.src = '';
            img.style.display = 'none';
        }
    },

    bindIconPreview: function () {
        var self = this;
        if (!this.modalNode) { return; }
        var iconInput = this.modalNode.querySelector('[name="icon"]');
        if (!iconInput) { return; }
        iconInput.addEventListener('input', function () {
            self.updateIconPreview('admin-guilds-icon-preview', iconInput.value.trim());
        });
    },

    // ── Members ────────────────────────────────────────────────────────────

    loadMembers: function (guildId) {
        var self = this;
        if (!this.membersTbody) { return; }
        this.membersTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center small">Caricamento...</td></tr>';
        this.post('/admin/guilds/admin-members', { guild_id: guildId }, function (res) {
            self.renderMembers(res && res.members ? res.members : []);
        }, function () {
            if (self.membersTbody) {
                self.membersTbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center small">Errore nel caricamento dei membri.</td></tr>';
            }
        });
    },

    renderMembers: function (members) {
        if (!this.membersTbody) { return; }
        if (!members || members.length === 0) {
            this.membersTbody.innerHTML = '<tr data-role="admin-guilds-members-empty"><td colspan="4" class="text-muted text-center">Nessun membro.</td></tr>';
            return;
        }
        var self = this;
        var html = '';
        for (var i = 0; i < members.length; i++) {
            var m = members[i];
            var fullName = self.escapeHtml((m.name || '') + (m.surname ? ' ' + m.surname : ''));
            var roleName = self.escapeHtml(m.role_name || '—');
            var dateJoined = m.date_joined ? m.date_joined.substring(0, 10) : '—';
            html += '<tr>'
                + '<td>' + fullName + '</td>'
                + '<td>' + roleName + (m.is_leader ? ' <span class="badge bg-warning text-dark ms-1">Leader</span>' : '') + (m.is_officer ? ' <span class="badge bg-secondary ms-1">Officer</span>' : '') + '</td>'
                + '<td class="text-center small text-muted">' + self.escapeHtml(dateJoined) + '</td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-action="admin-guilds-member-remove" data-character-id="' + self.escapeAttr(String(m.character_id)) + '" title="Rimuovi"><i class="bi bi-person-dash"></i></button></td>'
                + '</tr>';
        }
        this.membersTbody.innerHTML = html;
    },

    renderMemberRoleOptions: function (roles) {
        if (!this.memberRoleSelect) { return; }
        var html = '<option value="">— predefinito —</option>';
        for (var i = 0; i < roles.length; i++) {
            var r = roles[i];
            html += '<option value="' + this.escapeAttr(String(r.id)) + '">' + this.escapeHtml(r.name || '') + '</option>';
        }
        this.memberRoleSelect.innerHTML = html;
    },

    bindMemberSearch: function () {
        var self = this;
        if (!this.memberSearchInput) { return; }
        this.memberSearchInput.addEventListener('input', function () {
            var query = (self.memberSearchInput.value || '').trim();
            if (self.memberCharacterId) { self.memberCharacterId.value = ''; }
            if (self.memberSearchTimer) { clearTimeout(self.memberSearchTimer); }
            if (query.length < 2) {
                if (self.memberSuggestionsBox) { self.memberSuggestionsBox.style.display = 'none'; }
                return;
            }
            self.memberSearchTimer = setTimeout(function () {
                self.post('/admin/characters/list', { query: { name: query }, page: 1, results: 10 }, function (res) {
                    var rows = (res && res.dataset) ? res.dataset : [];
                    self.showMemberSuggestions(rows);
                });
            }, 300);
        });

        if (this.memberSearchInput) {
            this.memberSearchInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (self.memberSuggestionsBox) { self.memberSuggestionsBox.style.display = 'none'; }
                }, 200);
            });
        }
    },

    showMemberSuggestions: function (rows) {
        if (!this.memberSuggestionsBox) { return; }
        if (!rows || rows.length === 0) {
            this.memberSuggestionsBox.style.display = 'none';
            return;
        }
        var self = this;
        var html = '';
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var fullName = (r.name || '') + (r.surname ? ' ' + r.surname : '');
            html += '<button type="button" class="list-group-item list-group-item-action list-group-item-dark py-1 px-2 small"'
                + ' data-character-id="' + self.escapeAttr(String(r.id)) + '"'
                + ' data-character-name="' + self.escapeAttr(fullName) + '">'
                + self.escapeHtml(fullName)
                + '</button>';
        }
        this.memberSuggestionsBox.innerHTML = html;
        this.memberSuggestionsBox.style.display = '';

        this.memberSuggestionsBox.querySelectorAll('[data-character-id]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var charId = btn.getAttribute('data-character-id');
                var charName = btn.getAttribute('data-character-name');
                if (self.memberSearchInput) { self.memberSearchInput.value = charName; }
                if (self.memberCharacterId) { self.memberCharacterId.value = charId; }
                if (self.memberSuggestionsBox) { self.memberSuggestionsBox.style.display = 'none'; }
            });
        });
    },

    addMember: function () {
        var self = this;
        var characterId = this.memberCharacterId ? parseInt(this.memberCharacterId.value || '0', 10) : 0;
        var roleId = this.memberRoleSelect ? parseInt(this.memberRoleSelect.value || '0', 10) : 0;
        if (!characterId) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Seleziona un personaggio dalla lista.', type: 'warning' }); }
            return;
        }
        this.post('/admin/guilds/admin-add-member', { guild_id: this.editingGuildId, character_id: characterId, role_id: roleId }, function () {
            if (self.memberSearchInput) { self.memberSearchInput.value = ''; }
            if (self.memberCharacterId) { self.memberCharacterId.value = ''; }
            self.loadMembers(self.editingGuildId);
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Membro aggiunto.', type: 'success' }); }
        });
    },

    removeMember: function (characterId) {
        var self = this;
        if (!characterId) { return; }
        if (!confirm('Rimuovere questo membro dalla gilda?')) { return; }
        this.post('/admin/guilds/admin-remove-member', { guild_id: this.editingGuildId, character_id: characterId }, function () {
            self.loadMembers(self.editingGuildId);
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Membro rimosso.', type: 'success' }); }
        });
    }
};

globalWindow.AdminGuilds = AdminGuilds;
export { AdminGuilds as AdminGuilds };
export default AdminGuilds;

