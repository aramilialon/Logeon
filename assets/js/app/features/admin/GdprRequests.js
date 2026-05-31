const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var STATUS_LABELS = {
    pending: '<span class="badge text-bg-warning">In attesa</span>',
    in_review: '<span class="badge text-bg-info">In revisione</span>',
    completed: '<span class="badge text-bg-success">Completata</span>',
    rejected: '<span class="badge text-bg-danger">Rifiutata</span>',
    cancelled: '<span class="badge text-bg-secondary">Annullata</span>'
};

var TYPE_LABELS = {
    export_data: 'Esportazione dati',
    delete_account: 'Cancellazione account'
};

var AdminGdprRequests = {
    initialized: false,
    root: null,
    filtersForm: null,
    grid: null,
    rowsById: {},
    modalNode: null,
    modal: null,
    currentRequestId: 0,

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.querySelector('#admin-page [data-admin-page="gdpr-requests"]');
        if (!this.root) {
            return this;
        }

        this.filtersForm = this.root.querySelector('#admin-gdpr-requests-filters');
        this.modalNode = this.root.querySelector('#admin-gdpr-request-modal');

        if (!this.filtersForm || !document.getElementById('grid-admin-gdpr-requests')) {
            return this;
        }

        this.modal = (this.modalNode && globalWindow.bootstrap && globalWindow.bootstrap.Modal)
            ? globalWindow.bootstrap.Modal.getOrCreateInstance(this.modalNode)
            : null;

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

        this.root.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) {
                return;
            }

            var action = String(trigger.getAttribute('data-action') || '').trim();
            switch (action) {
                case 'admin-gdpr-requests-reload':
                    event.preventDefault();
                    self.loadGrid();
                    break;
                case 'admin-gdpr-retention-run':
                    event.preventDefault();
                    self.runRetention();
                    break;
                case 'admin-gdpr-requests-reset':
                    event.preventDefault();
                    self.resetFilters();
                    break;
                case 'admin-gdpr-request-manage':
                    event.preventDefault();
                    self.openManageModal(parseInt(trigger.getAttribute('data-id') || '0', 10) || 0);
                    break;
                case 'admin-gdpr-request-save':
                    event.preventDefault();
                    self.saveStatusUpdate();
                    break;
                case 'admin-gdpr-request-execute':
                    event.preventDefault();
                    self.executeCurrentRequest();
                    break;
                case 'admin-gdpr-request-download':
                    event.preventDefault();
                    self.handleDownloadAction(trigger);
                    break;
            }
        });
    },

    initGrid: function () {
        var self = this;

        this.grid = new Datagrid('grid-admin-gdpr-requests', {
            name: 'AdminGdprRequests',
            autoindex: 'id',
            orderable: true,
            thead: true,
            handler: { url: '/admin/gdpr/requests/list', action: 'list' },
            nav: { display: 'bottom', urlupdate: 0, results: 20, page: 1 },
            onGetDataSuccess: function (response) {
                var rows = response && Array.isArray(response.dataset) ? response.dataset : [];
                self.cacheRows(rows);
            },
            onGetDataError: function () {
                self.rowsById = {};
            },
            columns: [
                { label: 'ID', field: 'id', sortable: true, style: { width: '70px' } },
                {
                    label: 'Tipo',
                    field: 'request_type',
                    sortable: true,
                    format: function (row) {
                        var key = String(row.request_type || '').trim();
                        return self.escapeHtml(TYPE_LABELS[key] || key || '-');
                    }
                },
                {
                    label: 'Email',
                    field: 'email',
                    sortable: false,
                    format: function (row) {
                        return self.escapeHtml(row.email || '-');
                    }
                },
                {
                    label: 'Stato',
                    field: 'status',
                    sortable: true,
                    format: function (row) {
                        var key = String(row.status || '').trim();
                        return STATUS_LABELS[key] || ('<span class="badge text-bg-secondary">' + self.escapeHtml(key || '-') + '</span>');
                    }
                },
                {
                    label: 'Creata il',
                    field: 'created_at',
                    sortable: true,
                    format: function (row) {
                        return self.escapeHtml(self.formatDateTime(row.created_at));
                    }
                },
                {
                    label: 'Gestita il',
                    field: 'handled_at',
                    sortable: true,
                    format: function (row) {
                        return self.escapeHtml(self.formatDateTime(row.handled_at));
                    }
                },
                {
                    label: 'Azioni',
                    field: '_actions',
                    sortable: false,
                    format: function (row) {
                        var id = parseInt(row.id, 10) || 0;
                        var html = '<button type="button" class="btn btn-sm btn-outline-primary me-1" data-action="admin-gdpr-request-manage" data-id="' + self.escapeAttr(String(id)) + '">Gestisci</button>';
                        if (String(row.request_type || '') === 'export_data' && parseInt(row.payload_ready || 0, 10) === 1) {
                            html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="admin-gdpr-request-download" data-id="' + self.escapeAttr(String(id)) + '">Export</button>';
                        }
                        return html;
                    }
                }
            ]
        });
    },

    cacheRows: function (rows) {
        this.rowsById = {};
        for (var i = 0; i < rows.length; i += 1) {
            if (!rows[i] || rows[i].id == null) {
                continue;
            }
            this.rowsById[String(rows[i].id)] = rows[i];
        }
    },

    buildFiltersPayload: function () {
        var payload = {};
        if (!this.filtersForm) {
            return payload;
        }

        var statusEl = this.filtersForm.elements.status;
        var typeEl = this.filtersForm.elements.request_type;

        if (statusEl && statusEl.value) {
            payload.status = String(statusEl.value).trim();
        }
        if (typeEl && typeEl.value) {
            payload.request_type = String(typeEl.value).trim();
        }

        return payload;
    },

    loadGrid: function () {
        if (!this.grid || typeof this.grid.loadData !== 'function') {
            return;
        }

        this.grid.loadData(this.buildFiltersPayload(), 20, 1, 'id|DESC');
    },

    resetFilters: function () {
        if (!this.filtersForm) {
            return;
        }

        if (this.filtersForm.elements.status) {
            this.filtersForm.elements.status.value = 'all';
        }
        if (this.filtersForm.elements.request_type) {
            this.filtersForm.elements.request_type.value = '';
        }
        this.loadGrid();
    },

    openManageModal: function (requestId) {
        if (!requestId) {
            return;
        }

        var row = this.rowsById[String(requestId)] || null;
        if (!row) {
            this.showToast('Riga richiesta non disponibile. Aggiorna la tabella e riprova.', 'warning');
            return;
        }

        this.currentRequestId = requestId;

        this.setText('[data-role="gdpr-request-id"]', '#' + String(requestId));
        this.setText('[data-role="gdpr-request-user"]', String(row.email || ('Utente #' + String(row.user_id || '-'))));
        this.setText('[data-role="gdpr-request-type"]', TYPE_LABELS[row.request_type] || String(row.request_type || '-'));
        this.setHtml('[data-role="gdpr-request-status"]', STATUS_LABELS[row.status] || this.escapeHtml(String(row.status || '-')));
        this.setText('[data-role="gdpr-request-note"]', String(row.note || 'Nessuna nota.'));

        var statusSelect = this.root.querySelector('#admin-gdpr-new-status');
        var noteInput = this.root.querySelector('#admin-gdpr-resolution-note');
        if (statusSelect) {
            statusSelect.value = String(row.status || '');
        }
        if (noteInput) {
            noteInput.value = String(row.resolution_note || '');
        }

        this.updateModalActionButtons(row);

        if (this.modal && typeof this.modal.show === 'function') {
            this.modal.show();
        }
    },

    updateModalActionButtons: function (row) {
        var executeBtn = this.root ? this.root.querySelector('[data-action="admin-gdpr-request-execute"]') : null;
        var downloadBtn = this.root ? this.root.querySelector('#admin-gdpr-request-modal [data-action="admin-gdpr-request-download"]') : null;
        if (!row) {
            if (executeBtn) {
                executeBtn.classList.add('d-none');
            }
            if (downloadBtn) {
                downloadBtn.classList.add('d-none');
            }
            return;
        }

        var status = String(row.status || '').trim();
        var requestType = String(row.request_type || '').trim();
        var canExecute = (status === 'pending' || status === 'in_review');
        var canDownload = (requestType === 'export_data' && parseInt(row.payload_ready || 0, 10) === 1);

        if (executeBtn) {
            executeBtn.classList.toggle('d-none', !canExecute);
            executeBtn.textContent = requestType === 'delete_account'
                ? 'Esegui cancellazione'
                : 'Esegui esportazione';
        }
        if (downloadBtn) {
            downloadBtn.classList.toggle('d-none', !canDownload);
        }
    },

    saveStatusUpdate: function () {
        if (!this.currentRequestId) {
            return;
        }

        var statusSelect = this.root.querySelector('#admin-gdpr-new-status');
        var noteInput = this.root.querySelector('#admin-gdpr-resolution-note');
        var saveBtn = this.root.querySelector('[data-action="admin-gdpr-request-save"]');

        var nextStatus = statusSelect ? String(statusSelect.value || '').trim() : '';
        if (!nextStatus) {
            this.showToast('Seleziona uno stato prima di salvare.', 'warning');
            return;
        }

        if (saveBtn) {
            saveBtn.disabled = true;
        }

        var self = this;
        this.post('/admin/gdpr/requests/update-status', {
            id: this.currentRequestId,
            status: nextStatus,
            resolution_note: noteInput ? String(noteInput.value || '').trim() : ''
        }, function () {
            if (saveBtn) {
                saveBtn.disabled = false;
            }
            if (self.modal && typeof self.modal.hide === 'function') {
                self.modal.hide();
            }
            self.showToast('Stato richiesta aggiornato con successo.', 'success');
            self.loadGrid();
        }, function () {
            if (saveBtn) {
                saveBtn.disabled = false;
            }
        });
    },

    executeCurrentRequest: function () {
        if (!this.currentRequestId) {
            return;
        }

        var current = this.rowsById[String(this.currentRequestId)] || null;
        if (!current) {
            this.showToast('Richiesta non disponibile. Aggiorna la tabella e riprova.', 'warning');
            return;
        }

        var noteInput = this.root.querySelector('#admin-gdpr-resolution-note');
        var executeBtn = this.root.querySelector('[data-action="admin-gdpr-request-execute"]');
        var self = this;

        if (executeBtn) {
            executeBtn.disabled = true;
        }

        this.post('/admin/gdpr/requests/execute', {
            id: this.currentRequestId,
            resolution_note: noteInput ? String(noteInput.value || '').trim() : ''
        }, function (response) {
            if (executeBtn) {
                executeBtn.disabled = false;
            }

            var dataset = response && response.dataset ? response.dataset : null;
            if (dataset && dataset.request_type === 'export_data' && dataset.export_dataset) {
                self.downloadJson(dataset.export_dataset, 'gdpr-export-request-' + String(self.currentRequestId) + '.json');
            }

            self.showToast('Operazione eseguita con successo.', 'success');
            if (self.modal && typeof self.modal.hide === 'function') {
                self.modal.hide();
            }
            self.loadGrid();
        }, function () {
            if (executeBtn) {
                executeBtn.disabled = false;
            }
        });
    },

    handleDownloadAction: function (trigger) {
        var explicitId = trigger ? parseInt(trigger.getAttribute('data-id') || '0', 10) : 0;
        var requestId = explicitId || this.currentRequestId;
        if (!requestId) {
            return;
        }

        var self = this;
        this.post('/admin/gdpr/requests/export-payload', { id: requestId }, function (response) {
            var payload = response && response.dataset ? response.dataset : null;
            if (!payload || !payload.dataset) {
                self.showToast('Nessun payload export disponibile.', 'warning');
                return;
            }

            self.downloadJson(payload.dataset, 'gdpr-export-request-' + String(requestId) + '.json');
            self.showToast('Export scaricato.', 'success');
        });
    },

    downloadJson: function (data, filename) {
        var payload = JSON.stringify(data || {}, null, 2);
        var blob = new Blob([payload], { type: 'application/json;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = String(filename || 'export.json');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    },

    runRetention: function () {
        var self = this;
        this.post('/admin/gdpr/retention/run', { force: 1 }, function (response) {
            var dataset = response && response.dataset ? response.dataset : {};
            var deleted = dataset.deleted || {};
            var updated = dataset.updated || {};
            var summary = []
                .concat(
                    'Cookie: ' + String(deleted.cookie_consent_logs || 0),
                    'Mail consent: ' + String(deleted.mail_consent_logs || 0),
                    'Legal consent: ' + String(deleted.legal_consent_logs || 0),
                    'Richieste chiuse: ' + String(deleted.gdpr_requests_closed || 0),
                    'Payload scrub: ' + String(updated.gdpr_requests_payload_scrubbed || 0)
                )
                .join(' | ');

            self.showToast('Retention completata. ' + summary, 'success');
        });
    },

    post: function (url, payload, onSuccess, onError) {
        var self = this;
        if (!globalWindow.Request || !globalWindow.Request.http || typeof globalWindow.Request.http.post !== 'function') {
            self.showToast('Servizio HTTP non disponibile.', 'error');
            if (typeof onError === 'function') {
                onError(new Error('http_unavailable'));
            }
            return;
        }

        globalWindow.Request.http.post(url, payload || {})
            .then(function (response) {
                if (typeof onSuccess === 'function') {
                    onSuccess(response || null);
                }
            })
            .catch(function (error) {
                var message = 'Operazione non riuscita.';
                if (globalWindow.Request && typeof globalWindow.Request.getErrorMessage === 'function') {
                    message = globalWindow.Request.getErrorMessage(error, message);
                } else if (error && typeof error.message === 'string' && error.message.trim()) {
                    message = error.message.trim();
                }
                self.showToast(message, 'error');
                if (typeof onError === 'function') {
                    onError(error);
                }
            });
    },

    setText: function (selector, value) {
        var el = this.root ? this.root.querySelector(selector) : null;
        if (el) {
            el.textContent = String(value || '');
        }
    },

    setHtml: function (selector, value) {
        var el = this.root ? this.root.querySelector(selector) : null;
        if (el) {
            el.innerHTML = String(value || '');
        }
    },

    showToast: function (message, type) {
        if (globalWindow.Toast && typeof globalWindow.Toast.show === 'function') {
            globalWindow.Toast.show({ body: message, type: type || 'info' });
            return;
        }
        if (type === 'error' || type === 'warning') {
            AdminDialogs.alert({ type: 'info', title: 'Avviso', body: '<p>' + message + '</p>' });
        }
    },

    formatDateTime: function (value) {
        var raw = String(value || '').trim();
        if (!raw) {
            return '-';
        }
        var date = new Date(raw.replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return raw;
        }
        return date.toLocaleString('it-IT', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    escapeHtml: function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    escapeAttr: function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

globalWindow.AdminGdprRequests = AdminGdprRequests;
export { AdminGdprRequests as AdminGdprRequests };
export default AdminGdprRequests;
