const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminMailTemplates = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,
    modalNode: null,
    modal: null,
    modalForm: null,
    previewModalNode: null,
    previewModal: null,
    bodyTextarea: null,
    rows: [],
    rowsById: {},
    editingRow: null,

    init: function () {
        if (this.initialized) { return this; }

        this.root = document.querySelector('#admin-page [data-admin-page="mail-templates"]');
        if (!this.root) { return this; }

        this.filtersForm      = this.root.querySelector('#admin-mail-templates-filters');
        this.modalNode        = this.root.querySelector('#admin-mail-template-modal');
        this.modalForm        = this.root.querySelector('#admin-mail-template-form');
        this.previewModalNode = this.root.querySelector('#admin-mail-template-preview-modal');
        this.bodyTextarea     = this.root.querySelector('#mail-template-body-textarea');

        if (!this.filtersForm || !this.modalNode || !this.modalForm || !this.previewModalNode) {
            return this;
        }

        this.modal        = new bootstrap.Modal(this.modalNode);
        this.previewModal = new bootstrap.Modal(this.previewModalNode);

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

        this.modalNode.addEventListener('shown.bs.modal', function () {
            self.syncEditorContent();
        });

        this.root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            var action = String(trigger.getAttribute('data-action') || '').trim();

            if (action === 'admin-mail-templates-reload') {
                event.preventDefault();
                self.loadGrid();
            } else if (action === 'admin-mail-templates-create') {
                event.preventDefault();
                self.openCreate();
            } else if (action === 'admin-mail-template-edit') {
                event.preventDefault();
                var id = parseInt(trigger.getAttribute('data-id') || '0', 10);
                self.openEdit(id);
            } else if (action === 'admin-mail-template-save') {
                event.preventDefault();
                self.save();
            } else if (action === 'admin-mail-template-delete') {
                event.preventDefault();
                self.remove();
            } else if (action === 'admin-mail-template-preview') {
                event.preventDefault();
                self.preview();
            } else if (action === 'admin-mail-template-insert-var') {
                event.preventDefault();
                var varName = String(trigger.getAttribute('data-var') || '').trim();
                if (varName) { self.insertVariable(varName); }
            }
        });
    },

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-mail-templates', {
            name: 'AdminMailTemplates',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/mail-templates/list', action: 'list' },
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
                    label: 'Categoria',
                    field: 'category',
                    sortable: true,
                    style: { textAlign: 'center', width: '130px' },
                    format: function (row) {
                        return self.formatCategory(row.category || '');
                    }
                },
                {
                    label: 'Aggiornato',
                    field: 'updated_at',
                    sortable: true,
                    style: { textAlign: 'center', width: '120px' },
                    format: function (row) {
                        return self.formatDate(row.updated_at || '');
                    }
                },
                {
                    label: 'Azioni',
                    sortable: false,
                    style: { textAlign: 'center', width: '80px' },
                    format: function (row) {
                        var id = self.escapeAttr(String(row.id || ''));
                        return '<button type="button" class="btn btn-sm btn-outline-secondary"'
                            + ' data-action="admin-mail-template-edit" data-id="' + id + '">'
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

    // ── TipTap ────────────────────────────────────────────────────────────

    getBodyTextarea: function () {
        return this.bodyTextarea
            || (this.root ? this.root.querySelector('#mail-template-body-textarea') : null);
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

    insertVariable: function (varName) {
        var instance = this.getTipTapInstance();
        if (instance && instance.editor) {
            instance.editor.commands.insertContent('{{ ' + varName + ' }}');
        }
    },

    // ── Modal ─────────────────────────────────────────────────────────────

    openCreate: function () {
        this.editingRow = null;
        if (this.modalForm) { this.modalForm.reset(); }
        this.setField('id', '');
        var textarea = this.getBodyTextarea();
        if (textarea) { textarea.value = ''; }
        this.toggleDelete(false);
        this.togglePreview(false);
        this.modal.show();
    },

    openEdit: function (id) {
        var self = this;
        if (!id) { return; }

        this.post('/admin/mail-templates/get', { id: id }, function (response) {
            var row = response && response.template ? response.template : null;
            if (!row) { return; }
            self.editingRow = row;
            if (self.modalForm) { self.modalForm.reset(); }
            self.setField('id', String(row.id || ''));
            self.setField('name', row.name || '');
            self.setField('category', row.category || 'transactional');
            self.setField('description', row.description || '');
            self.setField('subject', row.subject || '');
            var textarea = self.getBodyTextarea();
            if (textarea) { textarea.value = row.body_html || ''; }
            self.toggleDelete(true);
            self.togglePreview(true);
            self.modal.show();
        });
    },

    toggleDelete: function (show) {
        if (!this.modalNode) { return; }
        var btn = this.modalNode.querySelector('[data-action="admin-mail-template-delete"]');
        if (btn) { btn.classList.toggle('d-none', !show); }
    },

    togglePreview: function (show) {
        if (!this.modalNode) { return; }
        var btn = this.modalNode.querySelector('[data-action="admin-mail-template-preview"]');
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
        var textarea = this.getBodyTextarea();
        return {
            id:          parseInt(this.getField('id'), 10) || 0,
            name:        this.getField('name').trim(),
            category:    this.getField('category'),
            description: this.getField('description').trim(),
            subject:     this.getField('subject').trim(),
            body_html:   textarea ? textarea.value : ''
        };
    },

    save: function () {
        var payload = this.collectPayload();
        if (!payload.name) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Il nome è obbligatorio.', type: 'error' }); }
            return;
        }
        if (!payload.subject) {
            if (typeof Toast !== 'undefined') { Toast.show({ body: "L'oggetto email è obbligatorio.", type: 'error' }); }
            return;
        }

        var isNew = !payload.id;
        var url   = isNew ? '/admin/mail-templates/create' : '/admin/mail-templates/update';
        var self  = this;

        this.post(url, payload, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') {
                Toast.show({ body: isNew ? 'Template creato.' : 'Template aggiornato.', type: 'success' });
            }
            self.loadGrid();
        });
    },

    remove: function () {
        var payload = this.collectPayload();
        if (!payload.id) { return; }
        if (!confirm("Eliminare questo template? L'operazione non può essere annullata.")) { return; }

        var self = this;
        this.post('/admin/mail-templates/delete', { id: payload.id }, function () {
            self.modal.hide();
            if (typeof Toast !== 'undefined') { Toast.show({ body: 'Template eliminato.', type: 'success' }); }
            self.loadGrid();
        });
    },

    preview: function () {
        if (!this.editingRow || !this.editingRow.id) { return; }
        var self = this;

        this.post('/admin/mail-templates/preview', { id: this.editingRow.id }, function (response) {
            var subjectEl = document.querySelector('#mail-template-preview-subject');
            var bodyEl    = document.querySelector('#mail-template-preview-body');
            if (subjectEl) { subjectEl.textContent = (response && response.subject) ? response.subject : ''; }
            if (bodyEl)    { bodyEl.innerHTML      = (response && response.body_html) ? response.body_html : ''; }
            if (self.previewModal) { self.previewModal.show(); }
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

    formatCategory: function (cat) {
        var map = {
            'transactional': 'Transazionale',
            'system':        'Sistema',
            'announcement':  'Annuncio',
            'newsletter':    'Newsletter'
        };
        return map[cat] || this.escapeHtml(cat);
    },

    formatDate: function (str) {
        if (!str) { return '-'; }
        return String(str).substring(0, 10);
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

globalWindow.AdminMailTemplates = AdminMailTemplates;
export { AdminMailTemplates };
export default AdminMailTemplates;
