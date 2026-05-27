const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

var AdminMedia = {
    initialized: false,
    root: null,
    currentPath: '',
    folders: [],
    files: [],
    filesById: {},
    selectedIds: {},
    permissions: {},
    limits: {},
    uploadQueue: [],
    uploadInFlight: false,
    confirmHandler: null,
    folderModal: null,
    folderForm: null,
    fileRenameModal: null,
    fileRenameForm: null,
    infoModal: null,
    confirmModal: null,

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.querySelector('#admin-page [data-admin-page="media"]');
        if (!this.root) {
            return this;
        }

        this.folderForm = document.getElementById('media-folder-form');
        this.fileRenameForm = document.getElementById('media-file-rename-form');
        if (!this.folderForm || !this.fileRenameForm || !document.getElementById('media-confirm-modal')) {
            return this;
        }

        if (globalWindow.bootstrap && typeof globalWindow.bootstrap.Modal === 'function') {
            this.folderModal = new globalWindow.bootstrap.Modal(document.getElementById('media-folder-modal'));
            this.fileRenameModal = new globalWindow.bootstrap.Modal(document.getElementById('media-file-rename-modal'));
            this.infoModal = new globalWindow.bootstrap.Modal(document.getElementById('media-file-info-modal'));
            this.confirmModal = new globalWindow.bootstrap.Modal(document.getElementById('media-confirm-modal'));
        }

        this.bind();
        this.loadList('');
        this.initialized = true;
        return this;
    },

    bind: function () {
        var self = this;
        var fileInput = document.getElementById('media-file-input');
        var dropzone = document.getElementById('media-dropzone');
        var confirmBtn = document.getElementById('media-confirm-btn');

        this.root.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) {
                return;
            }

            var action = String(trigger.getAttribute('data-action') || '').trim();
            if (!action) {
                return;
            }

            if (action === 'media-reload') { event.preventDefault(); self.loadList(self.currentPath); return; }
            if (action === 'media-upload-trigger') { event.preventDefault(); self.openFilePicker(); return; }
            if (action === 'media-navigate-root') { event.preventDefault(); self.loadList(''); return; }
            if (action === 'media-breadcrumb' || action === 'media-open-folder') { event.preventDefault(); self.loadList(trigger.getAttribute('data-path') || ''); return; }
            if (action === 'media-create-folder') { event.preventDefault(); self.openFolderModal('create'); return; }
            if (action === 'media-rename-folder') { event.preventDefault(); self.openFolderModal('rename'); return; }
            if (action === 'media-delete-folder') { event.preventDefault(); self.confirmFolderDelete(); return; }
            if (action === 'media-file-info') { event.preventDefault(); self.openInfoModal(parseInt(trigger.getAttribute('data-file-id') || '0', 10) || 0); return; }
            if (action === 'media-file-rename') { event.preventDefault(); self.openFileRenameModal(parseInt(trigger.getAttribute('data-file-id') || '0', 10) || 0); return; }
            if (action === 'media-file-delete') { event.preventDefault(); self.confirmFileDelete(parseInt(trigger.getAttribute('data-file-id') || '0', 10) || 0); return; }
            if (action === 'media-bulk-delete') { event.preventDefault(); self.confirmBulkDelete(); }
        });

        this.root.addEventListener('change', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }
            if (target.id === 'media-file-select-all') {
                self.toggleAllSelections(target.checked === true);
                return;
            }
            if (target.matches('[data-role="media-file-select"]')) {
                self.toggleSelection(parseInt(target.getAttribute('data-file-id') || '0', 10) || 0, target.checked === true);
            }
        });

        this.folderForm.addEventListener('submit', function (event) { event.preventDefault(); self.submitFolderForm(); });
        this.fileRenameForm.addEventListener('submit', function (event) { event.preventDefault(); self.submitFileRename(); });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (typeof self.confirmHandler === 'function') {
                    self.confirmHandler();
                }
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var files = fileInput.files ? Array.prototype.slice.call(fileInput.files) : [];
                self.enqueueUploads(files);
                fileInput.value = '';
            });
        }

        if (dropzone) {
            dropzone.addEventListener('click', function () { self.openFilePicker(); });
            ['dragenter', 'dragover'].forEach(function (type) {
                dropzone.addEventListener(type, function (event) {
                    event.preventDefault();
                    dropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'dragend', 'drop'].forEach(function (type) {
                dropzone.addEventListener(type, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragover');
                });
            });
            dropzone.addEventListener('drop', function (event) {
                var files = event.dataTransfer && event.dataTransfer.files ? Array.prototype.slice.call(event.dataTransfer.files) : [];
                self.enqueueUploads(files);
            });
        }
    },

    loadList: function (path) {
        var self = this;
        this.post('/admin/media/list', { path: String(path || '') }, function (response) {
            var dataset = response && response.dataset ? response.dataset : {};
            self.currentPath = String(dataset.path || '');
            self.permissions = dataset.permissions || {};
            self.limits = dataset.limits || {};
            self.folders = Array.isArray(dataset.folders) ? dataset.folders : [];
            self.files = Array.isArray(dataset.files) ? dataset.files : [];
            self.filesById = {};
            self.selectedIds = {};

            for (var i = 0; i < self.files.length; i += 1) {
                var row = self.files[i] || {};
                var id = parseInt(row.id || '0', 10) || 0;
                if (id > 0) {
                    self.filesById[id] = row;
                }
            }

            self.renderBreadcrumbs(Array.isArray(dataset.breadcrumbs) ? dataset.breadcrumbs : []);
            self.renderCurrentFolder(dataset.current_folder || {});
            self.renderLimits();
            self.renderFolders();
            self.renderFiles();
            self.syncSelectionUi();
        }, function (error) {
            self.showToast('error', self.requestErrorMessage(error, 'Errore nel caricamento dei media.'));
        });
    },

    renderBreadcrumbs: function (items) {
        var node = document.getElementById('media-breadcrumb');
        if (!node) {
            return;
        }
        if (!items.length) {
            items = [{ label: 'Media', path: '', active: true }];
        }

        var html = [];
        for (var i = 0; i < items.length; i += 1) {
            var item = items[i] || {};
            if (item.active) {
                html.push('<li class="breadcrumb-item active" aria-current="page">' + this.escapeHtml(item.label || 'Media') + '</li>');
            } else {
                html.push('<li class="breadcrumb-item"><a href="#" class="text-decoration-none" data-action="media-breadcrumb" data-path="' + this.escapeHtml(item.path || '') + '">' + this.escapeHtml(item.label || 'Media') + '</a></li>');
            }
        }
        node.innerHTML = html.join('');
    },

    renderCurrentFolder: function (folder) {
        this.setText('media-current-folder-label', folder && folder.name ? String(folder.name) : 'Media');
        var createBtn = this.root ? this.root.querySelector('[data-action="media-create-folder"]') : null;
        var renameBtn = document.getElementById('media-rename-folder-btn');
        var deleteBtn = document.getElementById('media-delete-folder-btn');
        var uploadBtn = this.root ? this.root.querySelector('[data-action="media-upload-trigger"]') : null;
        var dropzone = document.getElementById('media-dropzone');
        var canCreateChildren = !!(folder && folder.can_create_children);
        var canUploadHere = !(this.permissions && this.permissions.upload_here === false) && !(this.permissions && this.permissions.upload === false);

        if (createBtn) { createBtn.classList.toggle('d-none', !canCreateChildren); }
        if (renameBtn) { renameBtn.classList.toggle('d-none', !(folder && folder.can_rename)); }
        if (deleteBtn) { deleteBtn.classList.toggle('d-none', !(folder && folder.can_delete)); }
        if (uploadBtn) {
            uploadBtn.disabled = !canUploadHere;
            uploadBtn.classList.toggle('disabled', !canUploadHere);
            uploadBtn.setAttribute('aria-disabled', canUploadHere ? 'false' : 'true');
        }
        if (dropzone) {
            dropzone.classList.toggle('opacity-50', !canUploadHere);
            dropzone.classList.toggle('pe-none', !canUploadHere);
            dropzone.setAttribute('aria-disabled', canUploadHere ? 'false' : 'true');
        }
    },

    renderLimits: function () {
        var node = document.getElementById('media-limit-note');
        if (!node) {
            return;
        }
        var maxMb = this.limits && this.limits.max_file_size_mb ? this.limits.max_file_size_mb : 5;
        var html = 'Formati consentiti: <code>jpg</code>, <code>jpeg</code>, <code>png</code>, <code>webp</code>, <code>gif</code>, <code>txt</code>, <code>md</code>. Dimensione massima: <b>' + this.escapeHtml(String(maxMb)) + ' MB</b>.';
        if (this.permissions && this.permissions.scope_root) {
            html += '<br><span class="d-inline-block mt-1">Per il tuo ruolo i caricamenti e la gestione cartelle sono consentiti solo in <code>' + this.escapeHtml(String(this.permissions.scope_root)) + '</code>.</span>';
        }
        node.innerHTML = html;
    },

    renderFolders: function () {
        var container = document.getElementById('media-folders-container');
        var empty = document.getElementById('media-folders-empty');
        if (!container || !empty) {
            return;
        }
        if (!this.folders.length) {
            container.innerHTML = '';
            empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');
        var html = [];
        for (var i = 0; i < this.folders.length; i += 1) {
            var folder = this.folders[i] || {};
            html.push('<div class="col-12 col-md-6 col-xl-4"><button type="button" class="card w-100 text-start admin-media-folder-card" data-action="media-open-folder" data-path="' + this.escapeHtml(folder.path || '') + '"><div class="card-body d-flex align-items-center gap-3"><i class="bi bi-folder2-open fs-3 text-warning"></i><div class="min-w-0"><div class="fw-semibold text-truncate">' + this.escapeHtml(folder.name || 'Cartella') + '</div><div class="small text-muted text-truncate">' + this.escapeHtml(folder.path || '') + '</div></div></div></button></div>');
        }
        container.innerHTML = html.join('');
    },
    renderFiles: function () {
        var body = document.getElementById('media-files-table-body');
        var empty = document.getElementById('media-files-empty');
        var selectAll = document.getElementById('media-file-select-all');
        if (!body || !empty) {
            return;
        }
        if (selectAll) {
            selectAll.checked = false;
        }
        if (!this.files.length) {
            body.innerHTML = '';
            empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');
        var html = [];
        for (var i = 0; i < this.files.length; i += 1) {
            var file = this.files[i] || {};
            var fileId = parseInt(file.id || '0', 10) || 0;
            var typeLabel = String(file.extension || '').toUpperCase() || '-';
            var preview = file.is_image
                ? '<img class="admin-media-thumb" src="' + this.escapeHtml(file.url || '') + '" alt="">'
                : '<div class="admin-media-thumb admin-media-thumb--icon d-flex align-items-center justify-content-center"><i class="bi ' + this.iconForExtension(file.extension || '') + ' fs-4 text-muted"></i></div>';
            var actions = '<div class="d-inline-flex align-items-center gap-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-action="media-file-info" data-file-id="' + fileId + '" title="Info"><i class="bi bi-eye"></i></button>';
            if (file.can_rename) {
                actions += '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="media-file-rename" data-file-id="' + fileId + '" title="Rinomina"><i class="bi bi-pencil"></i></button>';
            }
            if (file.can_delete) {
                actions += '<button type="button" class="btn btn-sm btn-outline-danger" data-action="media-file-delete" data-file-id="' + fileId + '" title="Elimina"><i class="bi bi-trash"></i></button>';
            }
            actions += '</div>';
            html.push('<tr>'
                + '<td class="text-center"><div class="form-check form-check-inline justify-content-center mb-0 admin-media-check"><input type="checkbox" class="form-check-input" data-role="media-file-select" data-file-id="' + fileId + '"></div></td>'
                + '<td><div class="d-flex align-items-center gap-3">' + preview + '<div class="min-w-0"><div class="fw-semibold text-break">' + this.escapeHtml(file.name || 'File') + '</div><div class="small text-muted text-break">' + this.escapeHtml(file.path || 'root') + '</div></div></div></td>'
                + '<td><span class="badge text-bg-secondary">' + this.escapeHtml(typeLabel) + '</span></td>'
                + '<td>' + this.escapeHtml(file.size_label || '-') + '</td>'
                + '<td>' + this.escapeHtml(this.formatDateTime(file.created_at || '')) + '</td>'
                + '<td>' + this.escapeHtml(file.author_label || '-') + '</td>'
                + '<td class="text-end">' + actions + '</td>'
                + '</tr>');
        }
        body.innerHTML = html.join('');
    },

    openFilePicker: function () {
        if (this.permissions && (this.permissions.upload === false || this.permissions.upload_here === false)) {
            return;
        }
        var input = document.getElementById('media-file-input');
        if (input) {
            input.click();
        }
    },

    enqueueUploads: function (files) {
        if (!Array.isArray(files) || !files.length) {
            return;
        }
        for (var i = 0; i < files.length; i += 1) {
            this.uploadQueue.push(files[i]);
        }
        if (!this.uploadInFlight) {
            this.uploadNext();
        }
    },

    uploadNext: function () {
        var self = this;
        if (!this.uploadQueue.length) {
            this.uploadInFlight = false;
            this.hideUploadProgressSoon();
            this.loadList(this.currentPath);
            return;
        }
        var file = this.uploadQueue.shift();
        if (!file) {
            this.uploadNext();
            return;
        }
        this.uploadInFlight = true;
        this.showUploadProgress(file.name || 'Upload in corso...', 0);
        this.uploadFile(file, function () {
            self.uploadNext();
        }, function (errorMessage) {
            self.showToast('error', errorMessage || 'Upload non riuscito.');
            self.uploadNext();
        });
    },

    uploadFile: function (file, onSuccess, onError) {
        var xhr = new XMLHttpRequest();
        var formData = new FormData();
        var self = this;
        formData.append('file', file);
        formData.append('path', this.currentPath || '');
        formData.append('data', JSON.stringify({ path: this.currentPath || '' }));
        xhr.open('POST', '/admin/media/upload', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var csrf = this.csrfToken();
        if (csrf) {
            xhr.setRequestHeader('X-CSRF-Token', csrf);
        }
        xhr.upload.addEventListener('progress', function (event) {
            if (!event.lengthComputable) {
                return;
            }
            var percent = Math.max(0, Math.min(100, Math.round(event.loaded / event.total * 100)));
            self.showUploadProgress(file.name || 'Upload in corso...', percent);
        });
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            var payload = self.parseResponsePayload(xhr.responseText || '');
            if (xhr.status >= 200 && xhr.status < 300 && payload && payload.success !== false) {
                self.showUploadProgress(file.name || 'Upload completato', 100);
                if (typeof onSuccess === 'function') {
                    onSuccess(payload);
                }
                return;
            }
            var message = self.extractUploadError(payload, 'Errore durante il caricamento del file.', xhr.responseText || '');
            if (typeof onError === 'function') {
                onError(message);
            }
        };
        xhr.onerror = function () {
            if (typeof onError === 'function') {
                onError('Errore di rete durante il caricamento del file.');
            }
        };
        xhr.send(formData);
    },

    showUploadProgress: function (label, percent) {
        var wrap = document.getElementById('media-upload-progress');
        var status = document.getElementById('media-upload-status');
        var percentNode = document.getElementById('media-upload-percent');
        var bar = document.getElementById('media-upload-bar');
        if (!wrap || !status || !percentNode || !bar) {
            return;
        }
        wrap.classList.remove('d-none');
        status.textContent = String(label || 'Upload in corso...');
        percentNode.textContent = String(percent) + '%';
        bar.style.width = String(percent) + '%';
        bar.setAttribute('aria-valuenow', String(percent));
        bar.textContent = String(percent) + '%';
    },

    hideUploadProgressSoon: function () {
        var wrap = document.getElementById('media-upload-progress');
        var bar = document.getElementById('media-upload-bar');
        var status = document.getElementById('media-upload-status');
        var percentNode = document.getElementById('media-upload-percent');
        if (!wrap || !bar || !status || !percentNode) {
            return;
        }
        globalWindow.setTimeout(function () {
            wrap.classList.add('d-none');
            status.textContent = 'Upload in corso...';
            percentNode.textContent = '0%';
            bar.style.width = '0%';
            bar.setAttribute('aria-valuenow', '0');
            bar.textContent = '';
        }, 400);
    },

    openFolderModal: function (mode) {
        if (!this.folderModal || !this.folderForm) {
            return;
        }
        var titleNode = document.getElementById('media-folder-modal-title');
        var submitNode = document.getElementById('media-folder-submit-btn');
        var modeField = this.folderForm.querySelector('[name="mode"]');
        var nameField = this.folderForm.querySelector('[name="folder_name"]');
        if (modeField) { modeField.value = mode === 'rename' ? 'rename' : 'create'; }
        if (titleNode) { titleNode.textContent = mode === 'rename' ? 'Rinomina cartella' : 'Nuova cartella'; }
        if (submitNode) { submitNode.textContent = mode === 'rename' ? 'Rinomina' : 'Crea'; }
        if (nameField) { nameField.value = mode === 'rename' ? this.currentFolderName() : ''; }
        this.folderModal.show();
    },

    submitFolderForm: function () {
        var modeField = this.folderForm.querySelector('[name="mode"]');
        var nameField = this.folderForm.querySelector('[name="folder_name"]');
        var mode = modeField ? String(modeField.value || 'create') : 'create';
        var name = nameField ? String(nameField.value || '').trim() : '';
        if (!name) {
            this.showToast('warning', 'Inserisci un nome cartella valido.');
            return;
        }
        var self = this;
        var url = mode === 'rename' ? '/admin/media/folder/rename' : '/admin/media/folder/create';
        this.post(url, { path: this.currentPath, folder_name: name, new_name: name }, function (response) {
            var dataset = response && response.dataset ? response.dataset : {};
            self.folderModal.hide();
            self.loadList(mode === 'rename' ? String(dataset.path || self.parentPath(self.currentPath)) : self.currentPath);
            self.showToast('success', mode === 'rename' ? 'Cartella rinominata.' : 'Cartella creata.');
        }, function (error) {
            self.showToast('error', self.requestErrorMessage(error, 'Operazione cartella non riuscita.'));
        });
    },

    confirmFolderDelete: function () {
        var self = this;
        var folderName = this.currentFolderName();
        this.openConfirm('Elimina cartella', 'Vuoi eliminare la cartella <b>' + this.escapeHtml(folderName) + '</b>? L\'operazione e consentita solo se la cartella e vuota.', function () {
            self.post('/admin/media/folder/delete', { path: self.currentPath }, function (response) {
                var dataset = response && response.dataset ? response.dataset : {};
                self.closeConfirm();
                self.loadList(String(dataset.parent_path || self.parentPath(self.currentPath)));
                self.showToast('success', 'Cartella eliminata.');
            }, function (error) {
                self.closeConfirm();
                self.showToast('error', self.requestErrorMessage(error, 'Eliminazione cartella non riuscita.'));
            });
        });
    },
    openFileRenameModal: function (fileId) {
        var file = this.filesById[fileId] || null;
        if (!file || !this.fileRenameModal || !this.fileRenameForm) {
            return;
        }
        this.fileRenameForm.querySelector('[name="file_id"]').value = String(fileId);
        this.fileRenameForm.querySelector('[name="new_name"]').value = this.baseName(file.name || '', file.extension || '');
        this.fileRenameModal.show();
    },

    submitFileRename: function () {
        var self = this;
        var fileId = parseInt(this.fileRenameForm.querySelector('[name="file_id"]').value || '0', 10) || 0;
        var newName = String(this.fileRenameForm.querySelector('[name="new_name"]').value || '').trim();
        if (fileId <= 0 || !newName) {
            this.showToast('warning', 'Inserisci un nuovo nome valido.');
            return;
        }
        this.post('/admin/media/file/rename', { file_id: fileId, new_name: newName }, function () {
            self.fileRenameModal.hide();
            self.loadList(self.currentPath);
            self.showToast('success', 'File rinominato.');
        }, function (error) {
            self.showToast('error', self.requestErrorMessage(error, 'Rinomina file non riuscita.'));
        });
    },

    confirmFileDelete: function (fileId) {
        var self = this;
        var file = this.filesById[fileId] || null;
        if (!file) {
            return;
        }
        this.openConfirm('Elimina file', 'Vuoi eliminare il file <b>' + this.escapeHtml(file.name || ('#' + fileId)) + '</b>? Questa operazione non puo essere annullata.', function () {
            self.post('/admin/media/file/delete', { file_id: fileId }, function () {
                self.closeConfirm();
                self.loadList(self.currentPath);
                self.showToast('success', 'File eliminato.');
            }, function (error) {
                self.closeConfirm();
                self.showToast('error', self.requestErrorMessage(error, 'Eliminazione file non riuscita.'));
            });
        });
    },

    confirmBulkDelete: function () {
        var self = this;
        var ids = this.selectedFileIds();
        if (!ids.length) {
            this.showToast('warning', 'Seleziona almeno un file da eliminare.');
            return;
        }
        this.openConfirm('Elimina selezionati', 'Vuoi eliminare i <b>' + this.escapeHtml(String(ids.length)) + '</b> file selezionati? Questa operazione non puo essere annullata.', function () {
            self.post('/admin/media/file/bulk-delete', { file_ids: ids }, function () {
                self.closeConfirm();
                self.loadList(self.currentPath);
                self.showToast('success', 'File selezionati eliminati.');
            }, function (error) {
                self.closeConfirm();
                self.showToast('error', self.requestErrorMessage(error, 'Eliminazione multipla non riuscita.'));
            });
        });
    },

    openInfoModal: function (fileId) {
        var file = this.filesById[fileId] || null;
        if (!file || !this.infoModal) {
            return;
        }
        this.setText('media-file-info-name', file.name || '-');
        this.setText('media-file-info-ext', String(file.extension || '').toUpperCase() || '-');
        this.setText('media-file-info-size', file.size_label || '-');
        this.setText('media-file-info-date', this.formatDateTime(file.created_at || '') || '-');
        this.setText('media-file-info-user', file.author_label || '-');
        this.setText('media-file-info-path', file.path || '');

        var link = document.getElementById('media-file-info-url');
        if (link) {
            var fileUrl = String(file.url || '#');
            var fileUrlText = this.displayUrl(fileUrl);
            link.href = fileUrl;
            link.textContent = fileUrlText || 'Apri file';
            link.title = fileUrlText || 'Apri file';
        }

        var previewWrap = document.getElementById('media-file-preview-wrap');
        var preview = document.getElementById('media-file-preview');
        var previewEmpty = document.getElementById('media-file-preview-empty');
        var isImage = file.is_image === true || file.is_image === 1 || file.is_image === '1';
        if (previewWrap && preview && previewEmpty) {
            previewWrap.classList.toggle('d-none', !isImage);
            previewEmpty.classList.toggle('d-none', isImage);
            if (isImage) {
                preview.src = String(file.url || '');
                preview.alt = file.name || 'Anteprima file';
            } else {
                preview.removeAttribute('src');
            }
        }

        this.infoModal.show();
    },

    toggleSelection: function (fileId, selected) {
        if (fileId <= 0) {
            return;
        }
        if (selected) {
            this.selectedIds[fileId] = true;
        } else {
            delete this.selectedIds[fileId];
        }
        this.syncSelectionUi();
    },

    toggleAllSelections: function (selected) {
        this.selectedIds = {};
        if (selected) {
            for (var i = 0; i < this.files.length; i += 1) {
                var file = this.files[i] || {};
                var fileId = parseInt(file.id || '0', 10) || 0;
                if (fileId > 0 && file.can_delete) {
                    this.selectedIds[fileId] = true;
                }
            }
        }
        this.syncSelectionUi();
    },

    syncSelectionUi: function () {
        var inputs = this.root ? this.root.querySelectorAll('[data-role="media-file-select"]') : [];
        var selectableCount = 0;
        var selectedCount = 0;

        for (var i = 0; i < inputs.length; i += 1) {
            var input = inputs[i];
            var fileId = parseInt(input.getAttribute('data-file-id') || '0', 10) || 0;
            var file = this.filesById[fileId] || null;
            var canDelete = !!(file && file.can_delete);
            input.disabled = !canDelete;
            input.checked = canDelete && this.selectedIds[fileId] === true;
            if (canDelete) {
                selectableCount += 1;
                if (input.checked) {
                    selectedCount += 1;
                }
            }
        }

        var selectAll = document.getElementById('media-file-select-all');
        if (selectAll) {
            selectAll.checked = selectableCount > 0 && selectedCount === selectableCount;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < selectableCount;
        }

        var bulk = document.getElementById('media-bulk-actions');
        if (bulk) {
            bulk.classList.toggle('d-none', selectedCount <= 0);
        }
        this.setText('media-selected-count', String(selectedCount));
    },

    selectedFileIds: function () {
        var ids = [];
        var keys = Object.keys(this.selectedIds || {});
        for (var i = 0; i < keys.length; i += 1) {
            var fileId = parseInt(keys[i] || '0', 10) || 0;
            if (fileId > 0 && this.selectedIds[fileId] === true) {
                ids.push(fileId);
            }
        }
        return ids;
    },

    openConfirm: function (title, message, onConfirm) {
        this.confirmHandler = typeof onConfirm === 'function' ? onConfirm : null;
        this.setText('media-confirm-title', title || 'Conferma');
        var body = document.getElementById('media-confirm-message');
        if (body) {
            body.innerHTML = String(message || '');
        }
        if (this.confirmModal) {
            this.confirmModal.show();
        }
    },

    closeConfirm: function () {
        this.confirmHandler = null;
        if (this.confirmModal) {
            this.confirmModal.hide();
        }
    },
    post: function (url, payload, ok, fail) {
        var self = this;
        var data = (payload && typeof payload === 'object') ? payload : {};

        if (typeof globalWindow.Request !== 'undefined' && globalWindow.Request && globalWindow.Request.http && typeof globalWindow.Request.http.post === 'function') {
            globalWindow.Request.http.post(url, data)
                .then(function (response) {
                    if (typeof ok === 'function') {
                        ok(response || {});
                    }
                })
                .catch(function (error) {
                    if (typeof fail === 'function') {
                        fail(error);
                        return;
                    }
                    self.showToast('error', self.requestErrorMessage(error, 'Operazione non riuscita.'));
                });
            return;
        }

        if (typeof globalWindow.fetch === 'function') {
            globalWindow.fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfToken()
                },
                body: JSON.stringify(data)
            }).then(function (response) {
                return response.text().then(function (text) {
                    var parsed = {};
                    try {
                        parsed = text ? JSON.parse(text) : {};
                    } catch (error) {
                        parsed = { message: response.statusText || 'Operazione non riuscita.' };
                    }
                    if (!response.ok) {
                        throw parsed;
                    }
                    return parsed;
                });
            }).then(function (response) {
                if (typeof ok === 'function') {
                    ok(response || {});
                }
            }).catch(function (error) {
                if (typeof fail === 'function') {
                    fail(error);
                    return;
                }
                self.showToast('error', self.requestErrorMessage(error, 'Operazione non riuscita.'));
            });
            return;
        }

        self.showToast('error', 'Servizio comunicazione non disponibile.');
    },

    parseResponsePayload: function (responseText) {
        var text = String(responseText || '').trim();
        if (!text) {
            return null;
        }

        var attempts = [text];
        var compactObjects = text.split(/\}\s*\{/);
        if (compactObjects.length > 1) {
            attempts.push(compactObjects[0] + '}');
        }

        var firstObject = text.indexOf('{');
        var lastObject = text.lastIndexOf('}');
        if (firstObject !== -1 && lastObject > firstObject) {
            attempts.push(text.slice(firstObject, lastObject + 1));
        }

        var firstArray = text.indexOf('[');
        var lastArray = text.lastIndexOf(']');
        if (firstArray !== -1 && lastArray > firstArray) {
            attempts.push(text.slice(firstArray, lastArray + 1));
        }

        for (var i = 0; i < attempts.length; i += 1) {
            var candidate = String(attempts[i] || '').trim();
            if (!candidate) {
                continue;
            }
            try {
                return JSON.parse(candidate);
            } catch (error) {}
        }

        return null;
    },

    extractUploadError: function (payload, fallback, responseText) {
        if (payload && typeof payload.error === 'string' && payload.error.trim() !== '') {
            return payload.error.trim();
        }
        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }
        if (typeof globalWindow.Request !== 'undefined'
            && globalWindow.Request
            && typeof globalWindow.Request.getErrorMessage === 'function'
            && payload) {
            return globalWindow.Request.getErrorMessage(payload, fallback || 'Upload non riuscito.');
        }
        var plainText = this.extractPlainErrorText(responseText || '');
        if (plainText !== '') {
            return plainText;
        }
        return fallback || 'Upload non riuscito.';
    },

    extractPlainErrorText: function (responseText) {
        var text = String(responseText || '')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        if (!text) {
            return '';
        }

        var markers = [
            'Token CSRF non valido',
            'Upload non riuscito',
            'Errore durante il caricamento del file',
            'Estensione non consentita',
            'Tipo file non consentito',
            'Il file supera la dimensione massima consentita',
            'Il file e vuoto',
            'Nessun file ricevuto',
            'Impossibile salvare il file',
            'File temporaneo non trovato',
            'Permesso upload non disponibile',
            'Per il tuo ruolo puoi caricare file solo nella tua cartella staff'
        ];

        for (var i = 0; i < markers.length; i += 1) {
            if (text.indexOf(markers[i]) !== -1) {
                return markers[i];
            }
        }

        return '';
    },

    requestErrorMessage: function (error, fallback) {
        if (typeof globalWindow.Request !== 'undefined' && globalWindow.Request && typeof globalWindow.Request.getErrorMessage === 'function') {
            return globalWindow.Request.getErrorMessage(error, fallback || 'Operazione non riuscita.');
        }
        if (typeof error === 'string' && error.trim() !== '') {
            return error.trim();
        }
        if (error && typeof error.message === 'string' && error.message.trim() !== '') {
            return error.message.trim();
        }
        return fallback || 'Operazione non riuscita.';
    },

    csrfToken: function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    },

    currentFolderName: function () {
        if (!this.currentPath) {
            return 'media';
        }
        var parts = String(this.currentPath).split('/');
        return parts.length ? String(parts[parts.length - 1] || 'media') : 'media';
    },

    parentPath: function (path) {
        var value = String(path || '').trim();
        if (!value) {
            return '';
        }
        var parts = value.split('/');
        parts.pop();
        return parts.join('/');
    },

    baseName: function (name, extension) {
        var value = String(name || '');
        var ext = String(extension || '').trim();
        if (!value || !ext) {
            return value;
        }
        var suffix = '.' + ext;
        if (value.toLowerCase().slice(-suffix.length) === suffix.toLowerCase()) {
            return value.slice(0, value.length - suffix.length);
        }
        return value;
    },

    iconForExtension: function (extension) {
        var ext = String(extension || '').toLowerCase();
        if (ext === 'txt' || ext === 'md') {
            return 'bi-file-text';
        }
        return 'bi-file-earmark';
    },

    formatDateTime: function (value) {
        var text = String(value || '').trim();
        if (!text) {
            return '';
        }
        var date = new Date(text.replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return text;
        }
        return String(date.getDate()).padStart(2, '0')
            + '/' + String(date.getMonth() + 1).padStart(2, '0')
            + '/' + date.getFullYear()
            + ' ' + String(date.getHours()).padStart(2, '0')
            + ':' + String(date.getMinutes()).padStart(2, '0');
    },

    displayUrl: function (value) {
        var text = String(value || '').trim();
        if (!text) {
            return '';
        }
        try {
            return decodeURIComponent(text);
        } catch (error) {
            return text;
        }
    },

    setText: function (id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = String(value || '');
        }
    },

    showToast: function (type, message) {
        var toastApi = null;
        if (typeof globalWindow.Toast !== 'undefined' && globalWindow.Toast && typeof globalWindow.Toast.show === 'function') {
            toastApi = globalWindow.Toast;
        } else if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
            toastApi = Toast;
        }
        if (!toastApi) {
            return;
        }
        var normalized = String(type || 'info').toLowerCase();
        if (normalized === 'danger') {
            normalized = 'error';
        }
        toastApi.show({ type: normalized, body: String(message || '') });
    },

    escapeHtml: function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
};

globalWindow.AdminMedia = AdminMedia;
export { AdminMedia as AdminMedia };
export default AdminMedia;


