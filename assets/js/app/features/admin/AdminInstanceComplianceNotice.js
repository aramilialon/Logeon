const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

const SESSION_DISMISS_KEY = 'logeon.admin.instanceComplianceNotice.dismissed.v1';

const AdminInstanceComplianceNotice = {
    initialized: false,
    root: null,
    modalNode: null,
    modalInstance: null,
    noticeVersion: '',

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.querySelector('#admin-page [data-admin-page]');
        if (!this.root) {
            return this;
        }

        if (!this.isCreator(this.root)) {
            return this;
        }

        this.modalNode = document.getElementById('admin-instance-compliance-modal');
        if (!this.modalNode || typeof globalWindow.bootstrap === 'undefined' || !globalWindow.bootstrap.Modal) {
            return this;
        }

        this.modalInstance = globalWindow.bootstrap.Modal.getOrCreateInstance(this.modalNode, {
            backdrop: 'static',
            keyboard: true,
        });
        this.bind();
        this.loadStatus();
        this.initialized = true;
        return this;
    },

    isCreator: function (node) {
        if (!node || typeof node.getAttribute !== 'function') {
            return false;
        }
        var raw = String(node.getAttribute('data-current-user-is-superuser-creator') || '').trim().toLowerCase();
        return raw === '1' || raw === 'true';
    },

    bind: function () {
        var self = this;
        if (!this.modalNode || this.modalNode.getAttribute('data-bound') === '1') {
            return;
        }

        var checkbox = document.getElementById('admin-instance-compliance-ack');
        var confirmBtn = this.modalNode.querySelector('[data-action="admin-instance-compliance-confirm"]');

        if (checkbox && confirmBtn) {
            checkbox.addEventListener('change', function () {
                confirmBtn.disabled = !checkbox.checked;
            });
            confirmBtn.addEventListener('click', function (event) {
                event.preventDefault();
                self.confirmAcknowledge();
            });
        }

        this.modalNode.addEventListener('hidden.bs.modal', function () {
            if (self.noticeVersion) {
                self.setSessionDismiss(self.noticeVersion);
            }
            if (checkbox) {
                checkbox.checked = false;
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.removeAttribute('data-loading');
            }
        });

        this.modalNode.setAttribute('data-bound', '1');
    },

    loadStatus: function () {
        var self = this;
        this.post('/admin/privacy/instance-compliance-notice/status', {}, function (response) {
            var dataset = response && response.dataset ? response.dataset : {};
            var noticeVersion = String(dataset.notice_version || '').trim();
            self.noticeVersion = noticeVersion;
            if (!noticeVersion) {
                return;
            }
            if (self.isSessionDismissed(noticeVersion)) {
                return;
            }
            if (dataset.should_show !== true) {
                return;
            }
            self.showModal(noticeVersion);
        }, function () {});
    },

    confirmAcknowledge: function () {
        var self = this;
        if (!this.noticeVersion) {
            return;
        }

        var checkbox = document.getElementById('admin-instance-compliance-ack');
        var confirmBtn = this.modalNode ? this.modalNode.querySelector('[data-action="admin-instance-compliance-confirm"]') : null;
        if (!checkbox || checkbox.checked !== true || !confirmBtn) {
            return;
        }
        if (confirmBtn.getAttribute('data-loading') === '1') {
            return;
        }

        confirmBtn.setAttribute('data-loading', '1');
        confirmBtn.disabled = true;

        this.post('/admin/privacy/instance-compliance-notice/acknowledge', { accepted: true }, function () {
            self.setSessionDismiss(self.noticeVersion);
            if (self.modalInstance && typeof self.modalInstance.hide === 'function') {
                self.modalInstance.hide();
            }
            if (globalWindow.Toast && typeof globalWindow.Toast.show === 'function') {
                globalWindow.Toast.show({
                    body: 'Conferma registrata. Questo avviso non verra piu mostrato.',
                    type: 'success',
                });
            }
        }, function () {
            confirmBtn.removeAttribute('data-loading');
            confirmBtn.disabled = false;
            if (globalWindow.Toast && typeof globalWindow.Toast.show === 'function') {
                globalWindow.Toast.show({
                    body: 'Impossibile registrare la conferma. Riprova.',
                    type: 'error',
                });
            }
        });
    },

    showModal: function (noticeVersion) {
        if (!this.modalNode || !this.modalInstance) {
            return;
        }

        var versionNode = this.modalNode.querySelector('[data-role="admin-instance-compliance-version"]');
        if (versionNode) {
            versionNode.textContent = String(noticeVersion || '-');
        }

        var checkbox = document.getElementById('admin-instance-compliance-ack');
        var confirmBtn = this.modalNode.querySelector('[data-action="admin-instance-compliance-confirm"]');
        if (checkbox) {
            checkbox.checked = false;
        }
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.removeAttribute('data-loading');
        }

        this.modalInstance.show();
    },

    setSessionDismiss: function (version) {
        this.writeJsonSafe(globalWindow.sessionStorage, SESSION_DISMISS_KEY, {
            version: String(version || '').trim(),
            at: Date.now(),
        });
    },

    isSessionDismissed: function (version) {
        var stored = this.readJsonSafe(globalWindow.sessionStorage, SESSION_DISMISS_KEY);
        if (!stored) {
            return false;
        }
        return String(stored.version || '').trim() === String(version || '').trim();
    },

    post: function (url, payload, ok, fail) {
        var data = (payload && typeof payload === 'object') ? payload : {};

        if (globalWindow.Request && globalWindow.Request.http && typeof globalWindow.Request.http.post === 'function') {
            globalWindow.Request.http.post(url, data)
                .then(function (response) {
                    if (typeof ok === 'function') {
                        ok(response || {});
                    }
                })
                .catch(function (error) {
                    if (typeof fail === 'function') {
                        fail(error || {});
                    }
                });
            return;
        }

        if (typeof globalWindow.fetch !== 'function') {
            if (typeof fail === 'function') {
                fail({ message: 'Client HTTP non disponibile' });
            }
            return;
        }

        globalWindow.fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var parsed = {};
                    try {
                        parsed = text ? JSON.parse(text) : {};
                    } catch (error) {
                        parsed = {};
                    }
                    if (!response.ok) {
                        throw parsed;
                    }
                    return parsed;
                });
            })
            .then(function (response) {
                if (typeof ok === 'function') {
                    ok(response || {});
                }
            })
            .catch(function (error) {
                if (typeof fail === 'function') {
                    fail(error || {});
                }
            });
    },

    readJsonSafe: function (storage, key) {
        if (!storage || typeof storage.getItem !== 'function') {
            return null;
        }
        try {
            var raw = String(storage.getItem(key) || '').trim();
            if (!raw) {
                return null;
            }
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    },

    writeJsonSafe: function (storage, key, value) {
        if (!storage || typeof storage.setItem !== 'function') {
            return;
        }
        try {
            storage.setItem(key, JSON.stringify(value || {}));
        } catch (error) {}
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        AdminInstanceComplianceNotice.init();
    }, { once: true });
} else {
    AdminInstanceComplianceNotice.init();
}

globalWindow.AdminInstanceComplianceNotice = AdminInstanceComplianceNotice;
export { AdminInstanceComplianceNotice as AdminInstanceComplianceNotice };
export default AdminInstanceComplianceNotice;
