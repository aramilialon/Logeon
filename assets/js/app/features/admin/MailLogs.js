const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminMailLogs = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,

    init: function () {
        if (this.initialized) { return this; }

        this.root = document.querySelector('#admin-page [data-admin-page="mail-logs"]');
        if (!this.root) { return this; }

        this.filtersForm = this.root.querySelector('#admin-mail-logs-filters');
        if (!this.filtersForm) { return this; }

        this.bind();
        this.initGrid();
        this.loadCampaignOptions();
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

        this.root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            if (String(trigger.getAttribute('data-action') || '') === 'admin-mail-logs-reload') {
                event.preventDefault();
                self.loadGrid();
            }
        });
    },

    // ── Grid ─────────────────────────────────────────────────────────────

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-mail-logs', {
            name: 'AdminMailLogs',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/mail-queue/list', action: 'list' },
            nav: { display: 'bottom', urlupdate: 0, results: 25, page: 1 },
            columns: [
                {
                    label: 'ID',
                    field: 'id',
                    sortable: true,
                    style: { textAlign: 'center', width: '60px' }
                },
                {
                    label: 'Campagna',
                    field: 'message_id',
                    sortable: true,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        var subject = row.message_subject
                            ? self.escapeHtml(String(row.message_subject))
                            : '—';
                        return '#' + String(row.message_id || '') + ' · ' + subject;
                    }
                },
                {
                    label: 'Destinatario',
                    field: 'recipient_email',
                    sortable: true,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        var name  = row.recipient_name ? self.escapeHtml(String(row.recipient_name)) : '';
                        var email = self.escapeHtml(String(row.recipient_email || ''));
                        return name ? name + ' &lt;' + email + '&gt;' : email;
                    }
                },
                {
                    label: 'Stato',
                    field: 'status',
                    sortable: true,
                    style: { textAlign: 'center', width: '110px' },
                    format: function (row) {
                        return self.statusBadge(String(row.status || ''));
                    }
                },
                {
                    label: 'Tentativi',
                    field: 'attempts',
                    sortable: false,
                    style: { textAlign: 'center', width: '90px' },
                    format: function (row) {
                        return String(row.attempts || 0);
                    }
                },
                {
                    label: 'Errore',
                    field: 'last_error',
                    sortable: false,
                    style: { textAlign: 'left' },
                    format: function (row) {
                        if (!row.last_error) { return '—'; }
                        var msg = String(row.last_error);
                        var truncated = self.escapeHtml(msg.substring(0, 80));
                        return '<span class="text-danger small">' + truncated + (msg.length > 80 ? '…' : '') + '</span>';
                    }
                },
                {
                    label: 'Inviata il',
                    field: 'sent_at',
                    sortable: true,
                    style: { textAlign: 'center', width: '140px' },
                    format: function (row) {
                        return row.sent_at ? String(row.sent_at) : '—';
                    }
                }
            ]
        });
    },

    loadGrid: function () {
        if (!this.grid || typeof this.grid.loadData !== 'function') { return this; }
        this.grid.loadData(this.buildFiltersPayload(), 25, 1, 'id|DESC');
        return this;
    },

    buildFiltersPayload: function () {
        var q = {};
        if (!this.filtersForm) { return q; }
        var msgIdEl = this.filtersForm.querySelector('[name="message_id"]');
        var statusEl = this.filtersForm.querySelector('[name="status"]');
        var msgId  = msgIdEl  ? parseInt(msgIdEl.value  || '0', 10) : 0;
        var status = statusEl ? String(statusEl.value || '').trim()  : '';
        if (msgId > 0) { q.message_id = msgId; }
        if (status)    { q.status     = status; }
        return q;
    },

    // ── Campaign options ──────────────────────────────────────────────────

    loadCampaignOptions: function () {
        var self   = this;
        var select = this.filtersForm ? this.filtersForm.querySelector('#admin-mail-logs-campaign') : null;
        if (!select) { return; }
        if (typeof Request !== 'function' || !Request.http || typeof Request.http.post !== 'function') { return; }

        Request.http.post('/admin/mail-campaigns/list', { results: 100, page: 1, orderBy: 'id|DESC' })
            .then(function (res) {
                var rows = (res && Array.isArray(res.dataset)) ? res.dataset : [];
                var opts = '<option value="">Tutte</option>';
                rows.forEach(function (r) {
                    var id      = String(r.id || '');
                    var subject = self.escapeHtml(String(r.subject || '(senza oggetto)'));
                    opts += '<option value="' + id + '">#' + id + ' · ' + subject + '</option>';
                });
                select.innerHTML = opts;
            })
            .catch(function () {});
    },

    // ── Utils ─────────────────────────────────────────────────────────────

    statusBadge: function (status) {
        var map = {
            'pending': ['secondary', 'In attesa'],
            'sending': ['primary',   'In invio'],
            'sent':    ['success',   'Inviata'],
            'failed':  ['danger',    'Fallita']
        };
        var entry = map[status] || ['secondary', this.escapeHtml(status)];
        return '<span class="badge text-bg-' + entry[0] + '">' + entry[1] + '</span>';
    },

    escapeHtml: function (str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
};

globalWindow.AdminMailLogs = AdminMailLogs;
export { AdminMailLogs };
export default AdminMailLogs;
