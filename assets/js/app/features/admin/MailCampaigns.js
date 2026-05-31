const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminMailCampaigns = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,
    modalNode: null,
    modal: null,
    modalForm: null,
    bodyTextarea: null,
    testSection: null,
    testEmailInput: null,
    rows: [],
    rowsById: {},
    editingRow: null,

    init: function () {
        if (this.initialized) { return this; }

        this.root = document.querySelector('#admin-page [data-admin-page="mail-campaigns"]');
        if (!this.root) { return this; }

        this.filtersForm  = this.root.querySelector('#admin-mail-campaigns-filters');
        this.modalNode    = this.root.querySelector('#admin-mail-campaign-modal');
        this.modalForm    = this.root.querySelector('#admin-mail-campaign-form');
        this.bodyTextarea = this.root.querySelector('#mail-campaign-body-textarea');
        this.testSection  = this.root.querySelector('#admin-mail-campaign-test-section');
        this.testEmailInput = this.root.querySelector('#admin-mail-campaign-test-email');

        if (!this.filtersForm || !this.modalNode || !this.modalForm) { return this; }

        this.modal = new bootstrap.Modal(this.modalNode);

        this.bind();
        this.initGrid();
        this.loadGrid();
        this.loadTemplateOptions();
        this.loadListOptions();
        this.runQueueTick();

        this.initialized = true;
        return this;
    },

    bind: function () {
        var self = this;

        this.filtersForm.addEventListener('submit', function (event) {
            event.preventDefault();
            self.loadGrid();
        });

        this.modalNode.addEventListener('shown.bs.modal', function () {
            self.syncEditorContent();
        });

        this.root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            var action = String(trigger.getAttribute('data-action') || '').trim();

            if (action === 'admin-mail-campaigns-reload') {
                event.preventDefault();
                self.loadGrid();
            } else if (action === 'admin-mail-campaigns-create') {
                event.preventDefault();
                self.openCreate();
            } else if (action === 'admin-mail-campaign-edit') {
                event.preventDefault();
                var id = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.openEdit(id);
            } else if (action === 'admin-mail-campaign-save') {
                event.preventDefault();
                self.save();
            } else if (action === 'admin-mail-campaign-delete') {
                event.preventDefault();
                self.remove();
            } else if (action === 'admin-mail-campaign-schedule') {
                event.preventDefault();
                self.schedule();
            } else if (action === 'admin-mail-campaign-send-now') {
                event.preventDefault();
                self.sendNow();
            } else if (action === 'admin-mail-campaign-cancel') {
                event.preventDefault();
                self.cancelCampaign();
            } else if (action === 'admin-mail-campaign-test-open') {
                event.preventDefault();
                self.showTestSection(true);
            } else if (action === 'admin-mail-campaign-test-cancel') {
                event.preventDefault();
                self.showTestSection(false);
            } else if (action === 'admin-mail-campaign-send-test-confirm') {
                event.preventDefault();
                self.sendTest();
            } else if (action === 'admin-mail-campaign-load-template') {
                event.preventDefault();
                self.loadTemplate();
            }
        });
    },

    // ── Grid ─────────────────────────────────────────────────────────────

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-mail-campaigns', {
            name: 'AdminMailCampaigns',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/mail-campaigns/list', action: 'list' },
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
                    label: 'Oggetto',
                    field: 'subject',
                    sortable: true,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        return self.escapeHtml(row.subject || '-');
                    }
                },
                {
                    label: 'Stato',
                    field: 'status',
                    sortable: true,
                    style: { textAlign: 'center', width: '120px' },
                    format: function (row) {
                        return self.statusBadge(row.status || '');
                    }
                },
                {
                    label: 'Destinatari',
                    field: 'total_recipients',
                    sortable: false,
                    style: { textAlign: 'center', width: '110px' },
                    format: function (row) {
                        return self.formatRecipients(row);
                    }
                },
                {
                    label: 'Pianificato',
                    field: 'scheduled_at',
                    sortable: true,
                    style: { textAlign: 'center', width: '120px' },
                    format: function (row) {
                        return self.formatDate(row.scheduled_at || '');
                    }
                },
                {
                    label: 'Azioni',
                    sortable: false,
                    style: { textAlign: 'center', width: '80px' },
                    format: function (row) {
                        var id  = self.escapeAttr(String(row.id || ''));
                        return '<button type="button" class="btn btn-sm btn-outline-secondary"'
                            + ' data-action="admin-mail-campaign-edit" data-id="' + id + '">'
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
            var query  = (this.filtersForm.querySelector('[name="query"]') || {}).value || '';
            var status = (this.filtersForm.querySelector('[name="status"]') || {}).value || '';
            if (query)  { q.query  = query; }
            if (status) { q.status = status; }
        }
        return q;
    },

    // ── Options loaders ───────────────────────────────────────────────────

    loadTemplateOptions: function () {
        var self = this;
        this.post('/admin/mail-templates/list', { results: 100, page: 1, orderBy: 'name|ASC' }, function (response) {
            var select = self.modalForm ? self.modalForm.querySelector('[name="template_id"]') : null;
            if (!select) { return; }
            var rows = response && Array.isArray(response.dataset) ? response.dataset : [];
            var opts = '<option value="">— nessun template —</option>';
            rows.forEach(function (r) {
                opts += '<option value="' + self.escapeAttr(String(r.id || '')) + '">'
                    + self.escapeHtml(r.name || '') + '</option>';
            });
            select.innerHTML = opts;
        });
    },

    loadListOptions: function () {
        var self = this;
        this.post('/admin/mail-lists/list', { results: 100, page: 1, orderBy: 'name|ASC' }, function (response) {
            var select = self.modalForm ? self.modalForm.querySelector('[name="distribution_list_id"]') : null;
            if (!select) { return; }
            var rows = response && Array.isArray(response.dataset) ? response.dataset : [];
            var opts = '<option value="">— seleziona lista —</option>';
            rows.forEach(function (r) {
                opts += '<option value="' + self.escapeAttr(String(r.id || '')) + '">'
                    + self.escapeHtml(r.name || '') + '</option>';
            });
            select.innerHTML = opts;
        });
    },

    loadTemplate: function () {
        var self     = this;
        var select   = this.modalForm ? this.modalForm.querySelector('[name="template_id"]') : null;
        var templateId = select ? parseInt(select.value || '0', 10) : 0;
        if (!templateId) { return; }

        this.post('/admin/mail-templates/get', { id: templateId }, function (response) {
            var tpl = response && response.template ? response.template : null;
            if (!tpl) { return; }
            self.setField('subject', tpl.subject || '');
            var textarea = self.getBodyTextarea();
            if (textarea) { textarea.value = tpl.body_html || ''; }
            self.syncEditorContent();
        });
    },

    // ── Queue tick ────────────────────────────────────────────────────────

    runQueueTick: function () {
        this.post('/admin/mail-campaigns/queue-tick', { limit: 5 }, null, null);
    },

    // ── TipTap ────────────────────────────────────────────────────────────

    getBodyTextarea: function () {
        return this.bodyTextarea
            || (this.root ? this.root.querySelector('#mail-campaign-body-textarea') : null);
    },

    getTipTapInstance: function () {
        var textarea = this.getBodyTextarea();
        if (!textarea) { return null; }
        if (globalWindow.TipTapEditor && typeof globalWindow.TipTapEditor.getInstance === 'function') {
            return globalWindow.TipTapEditor.getInstance(textarea);
        }
        return null;
    },

    syncEditorContent: function () {
        var instance = this.getTipTapInstance();
        if (!instance || !instance.editor) { return; }
        var textarea = this.getBodyTextarea();
        if (!textarea) { return; }
        instance.editor.commands.setContent(textarea.value || '');
    },

    // ── Modal ─────────────────────────────────────────────────────────────

    openCreate: function () {
        this.editingRow = null;
        if (this.modalForm) { this.modalForm.reset(); }
        this.setField('id', '');
        var textarea = this.getBodyTextarea();
        if (textarea) { textarea.value = ''; }
        this.showTestSection(false);
        this.updateButtons(null);
        this.modal.show();
    },

    openEdit: function (id) {
        var self = this;
        if (!id) { return; }

        this.post('/admin/mail-campaigns/get', { id: id }, function (response) {
            var row = response && response.campaign ? response.campaign : null;
            if (!row) { return; }
            self.editingRow = row;
            if (self.modalForm) { self.modalForm.reset(); }
            self.setField('id', String(row.id || ''));
            self.setField('subject', row.subject || '');
            self.setField('category', row.category || 'announcement');
            self.setField('distribution_list_id', String(row.distribution_list_id || ''));
            self.setField('template_id', String(row.template_id || ''));
            self.setField('scheduled_at', self.formatDatetimeLocal(row.scheduled_at || ''));

            var textarea = self.getBodyTextarea();
            if (textarea) { textarea.value = row.body_html || ''; }

            self.showTestSection(false);
            self.updateButtons(row);
            self.modal.show();
        });
    },

    updateButtons: function (row) {
        if (!this.modalNode) { return; }
        var status    = row ? String(row.status || '') : '';
        var isDraft   = status === 'draft' || status === '';
        var isSched   = status === 'scheduled';
        var isEdit    = !!row;
        var isDeletable = ['draft', 'scheduled', 'cancelled', 'failed'].indexOf(status) !== -1;
        var isSendable  = ['draft', 'scheduled'].indexOf(status) !== -1;
        var isCancellable = ['scheduled', 'queued'].indexOf(status) !== -1;

        this.toggleBtn('admin-mail-campaign-delete',   isDeletable && isEdit);
        this.toggleBtn('admin-mail-campaign-test-open', isEdit);
        this.toggleBtn('admin-mail-campaign-cancel',   isCancellable);
        this.toggleBtn('admin-mail-campaign-schedule', isSendable);
        this.toggleBtn('admin-mail-campaign-send-now', isSendable);
        this.toggleBtn('admin-mail-campaign-save',     isDraft);
    },

    toggleBtn: function (action, show) {
        var btn = this.modalNode ? this.modalNode.querySelector('[data-action="' + action + '"]') : null;
        if (btn) { btn.classList.toggle('d-none', !show); }
    },

    showTestSection: function (show) {
        if (this.testSection) { this.testSection.classList.toggle('d-none', !show); }
        if (!show && this.testEmailInput) { this.testEmailInput.value = ''; }
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
        var textarea = this.getBodyTextarea();
        return {
            id:                   parseInt(this.getField('id'), 10) || 0,
            subject:              this.getField('subject').trim(),
            category:             this.getField('category'),
            distribution_list_id: parseInt(this.getField('distribution_list_id'), 10) || 0,
            template_id:          parseInt(this.getField('template_id'), 10) || 0,
            body_html:            textarea ? textarea.value : '',
            scheduled_at:         this.getField('scheduled_at')
        };
    },

    // ── Actions ───────────────────────────────────────────────────────────

    save: function () {
        var payload = this.collectPayload();
        if (!payload.subject) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "L'oggetto è obbligatorio.", type: 'error' }); }
            return;
        }

        var isNew = !payload.id;
        var url   = isNew ? '/admin/mail-campaigns/create' : '/admin/mail-campaigns/update';
        var self  = this;

        this.post(url, payload, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: isNew ? 'Bozza creata.' : 'Bozza aggiornata.', type: 'success' });
            }
            self.loadGrid();
        });
    },

    schedule: function () {
        var payload = this.collectPayload();
        if (!payload.id && !payload.subject) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "Salva prima la bozza.", type: 'error' }); }
            return;
        }
        if (!payload.scheduled_at) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "Seleziona una data di pianificazione.", type: 'error' }); }
            return;
        }

        var self = this;

        if (!payload.id) {
            this.post('/admin/mail-campaigns/create', payload, function (res) {
                var newId = res && res.id ? res.id : 0;
                if (!newId) { return; }
                self.post('/admin/mail-campaigns/schedule', { id: newId, scheduled_at: payload.scheduled_at }, function () {
                    self.modal.hide();
                    if (typeof Toast !== 'undefined') { Toast.show({ body: 'Campagna pianificata.', type: 'success' }); }
                    self.loadGrid();
                });
            });
        } else {
            this.post('/admin/mail-campaigns/schedule', { id: payload.id, scheduled_at: payload.scheduled_at }, function () {
                self.modal.hide();
                if (typeof Toast !== 'undefined') { Toast.show({ body: 'Campagna pianificata.', type: 'success' }); }
                self.loadGrid();
            });
        }
    },

    sendNow: async function () {
        var payload = this.collectPayload();
        if (!payload.id) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "Salva prima la bozza.", type: 'error' }); }
            return;
        }
        if (!(await AdminDialogs.confirmPromise({ type: 'warning', title: 'Conferma operazione', body: '<p>' + "Inviare la campagna ora? I destinatari verranno accodati per l'invio immediato." + '</p>', confirmLabel: 'Confermo' }))) { return; }

        var self = this;
        this.post('/admin/mail-campaigns/send-now', { id: payload.id }, function (response) {
            self.modal.hide();
            var n = response && response.recipients ? response.recipients : 0;
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: n + ' destinatari accodati.', type: 'success' });
            }
            self.loadGrid();
            self.runQueueTick();
        });
    },

    cancelCampaign: async function () {
        var payload = this.collectPayload();
        if (!payload.id) { return; }
        if (!(await AdminDialogs.confirmPromise({ type: 'warning', title: 'Conferma operazione', body: '<p>' + "Annullare l'invio di questa campagna?" + '</p>', confirmLabel: 'Confermo' }))) { return; }

        var self = this;
        this.post('/admin/mail-campaigns/cancel', { id: payload.id }, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Invio annullato.', type: 'success' }); }
            self.loadGrid();
        });
    },

    remove: async function () {
        var payload = this.collectPayload();
        if (!payload.id) { return; }
        if (!(await AdminDialogs.confirmPromise({ type: 'danger', title: 'Conferma eliminazione', body: '<p>' + "Eliminare questa campagna? L'operazione non può essere annullata." + '</p>', confirmLabel: 'Elimina' }))) { return; }

        var self = this;
        this.post('/admin/mail-campaigns/delete', { id: payload.id }, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Campagna eliminata.', type: 'success' }); }
            self.loadGrid();
        });
    },

    sendTest: function () {
        var payload    = this.collectPayload();
        var testEmail  = this.testEmailInput ? this.testEmailInput.value.trim() : '';

        if (!payload.id) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "Salva prima la bozza.", type: 'error' }); }
            return;
        }
        if (!testEmail) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "Inserisci un indirizzo email.", type: 'error' }); }
            return;
        }

        var self = this;
        this.post('/admin/mail-campaigns/send-test', { id: payload.id, test_email: testEmail }, function () {
            self.showTestSection(false);
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: 'Email di test inviata a ' + testEmail + '.', type: 'success' });
            }
        });
    },

    // ── HTTP helper ────────────────────────────────────────────────────────

    post: function (url, payload, onSuccess, onError) {
        if (typeof Request !== 'function' || !Request.http || typeof Request.http.post !== 'function') {
            if (onError) { onError(new Error('Servizio non disponibile.')); }
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

    statusBadge: function (status) {
        var map = {
            'draft':      ['secondary', 'Bozza'],
            'scheduled':  ['info',      'Pianificata'],
            'queued':     ['warning',   'In coda'],
            'sending':    ['primary',   'Invio in corso'],
            'sent':       ['success',   'Inviata'],
            'failed':     ['danger',    'Fallita'],
            'cancelled':  ['dark',      'Annullata']
        };
        var entry = map[status] || ['secondary', this.escapeHtml(status)];
        return '<span class="badge bg-' + entry[0] + '">' + entry[1] + '</span>';
    },

    formatRecipients: function (row) {
        var status = String(row.status || '');
        var total  = parseInt(row.total_recipients || 0, 10);
        var sent   = parseInt(row.sent_count || 0, 10);
        var failed = parseInt(row.failed_count || 0, 10);

        if (status === 'draft' || status === 'scheduled' || total === 0) {
            return '—';
        }
        if (status === 'sent' || status === 'sending') {
            return sent + ' / ' + total;
        }
        if (status === 'failed' && failed > 0) {
            return '<span class="text-danger">' + failed + ' falliti</span>';
        }
        return String(total);
    },

    formatDate: function (str) {
        if (!str) { return '—'; }
        return String(str).substring(0, 16).replace('T', ' ');
    },

    formatDatetimeLocal: function (str) {
        if (!str) { return ''; }
        return String(str).substring(0, 16).replace(' ', 'T');
    },

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

globalWindow.AdminMailCampaigns = AdminMailCampaigns;
export { AdminMailCampaigns };
export default AdminMailCampaigns;
