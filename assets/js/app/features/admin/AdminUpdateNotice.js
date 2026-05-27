const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

const SNOOZE_KEY = 'logeon.admin.updateNotice.snooze.v1';
const SESSION_DISMISS_KEY = 'logeon.admin.updateNotice.dismissed.v1';
const REMIND_LATER_MS = 24 * 60 * 60 * 1000;

const AdminUpdateNotice = {
    initialized: false,
    root: null,
    latestVersion: '',
    toastNode: null,
    toastInstance: null,

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

        var pageKey = String(this.root.getAttribute('data-admin-page') || '').trim().toLowerCase();
        if (pageKey === 'system-update') {
            return this;
        }

        this.checkUpdates();
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

    checkUpdates: function () {
        var self = this;
        this.post('/admin/system/update/status', {}, function (response) {
            var statusDataset = response && response.dataset ? response.dataset : {};
            var distribution = String(statusDataset.distribution || '').trim().toLowerCase();
            if (distribution !== 'ready' && distribution !== 'source-dev') {
                return;
            }

            self.post('/admin/system/update/check', {}, function (checkResponse) {
                var dataset = checkResponse && checkResponse.dataset ? checkResponse.dataset : {};
                if (dataset.update_available !== true) {
                    return;
                }

                var version = String(dataset.latest_version || (dataset.release && dataset.release.version) || '').trim();
                if (!self.shouldShow(version)) {
                    return;
                }

                self.latestVersion = version;
                self.showToast(version);
            }, function () {});
        }, function () {});
    },

    shouldShow: function (version) {
        var currentVersion = String(version || '').trim();
        if (!currentVersion) {
            return false;
        }

        var sessionDismiss = this.readJsonSafe(globalWindow.sessionStorage, SESSION_DISMISS_KEY);
        if (sessionDismiss && String(sessionDismiss.version || '').trim() === currentVersion) {
            return false;
        }

        var snooze = this.readJsonSafe(globalWindow.localStorage, SNOOZE_KEY);
        if (!snooze) {
            return true;
        }

        var snoozeVersion = String(snooze.version || '').trim();
        var until = Number(snooze.until || 0);
        if (!snoozeVersion || !Number.isFinite(until)) {
            return true;
        }

        if (snoozeVersion !== currentVersion) {
            return true;
        }

        return Date.now() >= until;
    },

    setSnooze: function (version) {
        this.writeJsonSafe(globalWindow.localStorage, SNOOZE_KEY, {
            version: String(version || '').trim(),
            until: Date.now() + REMIND_LATER_MS,
        });
        this.setSessionDismiss(version);
    },

    setSessionDismiss: function (version) {
        this.writeJsonSafe(globalWindow.sessionStorage, SESSION_DISMISS_KEY, {
            version: String(version || '').trim(),
            at: Date.now(),
        });
    },

    ensureToast: function () {
        if (this.toastNode && this.toastInstance) {
            return true;
        }

        if (typeof globalWindow.bootstrap === 'undefined' || !globalWindow.bootstrap.Toast) {
            return false;
        }

        var wrapper = document.getElementById('admin-update-toast-wrap');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.id = 'admin-update-toast-wrap';
            wrapper.className = 'toast-container position-fixed bottom-0 start-0 p-3';
            wrapper.style.zIndex = '1090';
            document.body.appendChild(wrapper);
        }

        var node = document.getElementById('admin-update-toast');
        if (!node) {
            node = document.createElement('div');
            node.id = 'admin-update-toast';
            node.className = 'toast border-0 admin-update-toast-solid';
            node.setAttribute('role', 'alert');
            node.setAttribute('aria-live', 'assertive');
            node.setAttribute('aria-atomic', 'true');

            var body = document.createElement('div');
            body.className = 'toast-body';
            node.appendChild(body);
            wrapper.appendChild(node);
        }

        this.toastNode = node;
        this.toastInstance = globalWindow.bootstrap.Toast.getOrCreateInstance(node, {
            autohide: false
        });
        this.bindToastActions();
        return true;
    },

    bindToastActions: function () {
        var self = this;
        if (!this.toastNode || this.toastNode.getAttribute('data-bound') === '1') {
            return;
        }

        this.toastNode.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) {
                return;
            }

            var action = String(trigger.getAttribute('data-action') || '').trim();
            if (action === 'admin-update-notice-remind') {
                event.preventDefault();
                self.setSnooze(self.latestVersion);
                self.hideToast();
                return;
            }

            if (action === 'admin-update-notice-go') {
                event.preventDefault();
                self.setSessionDismiss(self.latestVersion);
                self.hideToast();
                globalWindow.location.href = '/admin/system-update';
                return;
            }

            if (action === 'admin-update-notice-close') {
                event.preventDefault();
                self.setSessionDismiss(self.latestVersion);
                self.hideToast();
            }
        });

        this.toastNode.setAttribute('data-bound', '1');
    },

    showToast: function (version) {
        if (!this.ensureToast()) {
            return;
        }

        var safeVersion = this.escapeHtml(String(version || '').trim());
        var body = this.toastNode.querySelector('.toast-body');
        if (!body) {
            return;
        }

        body.innerHTML = ''
            + '<div class="d-flex justify-content-between align-items-start gap-2 mb-2">'
            + '  <div class="fw-semibold">Aggiornamento disponibile</div>'
            + '  <button type="button" class="btn-close" aria-label="Chiudi" data-action="admin-update-notice-close"></button>'
            + '</div>'
            + '<div class="small mb-3">E disponibile la versione <strong>' + safeVersion + '</strong>. Vuoi aprire adesso la sezione aggiornamenti?</div>'
            + '<div class="d-flex flex-wrap gap-2">'
            + '  <button type="button" class="btn btn-sm btn-outline-dark" data-action="admin-update-notice-remind">Ricordamelo piu tardi</button>'
            + '  <button type="button" class="btn btn-sm btn-dark" data-action="admin-update-notice-go">Aggiorna subito</button>'
            + '</div>';

        if (this.toastInstance && typeof this.toastInstance.show === 'function') {
            this.toastInstance.show();
        }
    },

    hideToast: function () {
        if (this.toastInstance && typeof this.toastInstance.hide === 'function') {
            this.toastInstance.hide();
        }
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

    escapeHtml: function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        AdminUpdateNotice.init();
    }, { once: true });
} else {
    AdminUpdateNotice.init();
}

globalWindow.AdminUpdateNotice = AdminUpdateNotice;
export { AdminUpdateNotice as AdminUpdateNotice };
export default AdminUpdateNotice;
