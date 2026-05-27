const COOKIE_NAME = 'lf_cookie_consent';
const COOKIE_DAYS = 365;

const cookies = Cookies();

const CookieBanner = {
    root: null,
    panel: null,
    initialized: false,

    init: function () {
        if (this.initialized) {
            return this;
        }

        this.root = document.getElementById('cookie-banner');
        if (!this.root) {
            return this;
        }

        this.panel = this.root.querySelector('[data-role="cookie-preferences-panel"]');
        this.bind();
        this.renderInitialState();

        this.initialized = true;
        return this;
    },

    bind: function () {
        const self = this;

        this.root.addEventListener('click', function (event) {
            const trigger = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
            if (!trigger) {
                return;
            }

            const action = String(trigger.getAttribute('data-action') || '').trim();
            if (action === 'cookie-accept-all') {
                event.preventDefault();
                self.applyConsent('accept_all', { preferences: 1, analytics: 1, marketing: 1 });
                return;
            }
            if (action === 'cookie-reject-optional') {
                event.preventDefault();
                self.applyConsent('reject_optional', { preferences: 0, analytics: 0, marketing: 0 });
                return;
            }
            if (action === 'cookie-open-preferences') {
                event.preventDefault();
                self.togglePreferencesPanel();
                return;
            }
            if (action === 'cookie-save-preferences') {
                event.preventDefault();
                self.applyConsent('custom', self.readPreferencesFromPanel());
            }
        });
    },

    renderInitialState: function () {
        if (this.hasConsentCookie()) {
            return;
        }
        this.root.classList.remove('d-none');
    },

    hasConsentCookie: function () {
        const raw = cookies.getCookie(COOKIE_NAME);
        if (raw === false || raw === null || String(raw).trim() === '') {
            return false;
        }

        // Compat: valore storico "1" da vecchio banner.
        if (String(raw).trim() === '1') {
            return true;
        }

        try {
            const parsed = JSON.parse(String(raw));
            return parsed && typeof parsed === 'object';
        } catch (error) {
            return false;
        }
    },

    togglePreferencesPanel: function () {
        if (!this.panel) {
            return;
        }
        this.panel.classList.toggle('d-none');
    },

    readPreferencesFromPanel: function () {
        const read = (selector) => {
            const el = this.root.querySelector(selector);
            return !!(el && el.checked);
        };
        return {
            preferences: read('[data-role="cookie-pref-preferences"]') ? 1 : 0,
            analytics: read('[data-role="cookie-pref-analytics"]') ? 1 : 0,
            marketing: read('[data-role="cookie-pref-marketing"]') ? 1 : 0
        };
    },

    applyConsent: function (choice, preferences) {
        const payload = this.buildCookiePayload(choice, preferences);
        cookies.setCookie(COOKIE_NAME, JSON.stringify(payload), COOKIE_DAYS);

        this.sendConsentToServer(choice, preferences)
            .finally(() => {
                this.root.classList.add('d-none');
            });
    },

    buildCookiePayload: function (choice, preferences) {
        return {
            version: this.resolvePolicyVersion(),
            choice: choice,
            preferences: {
                necessary: 1,
                preferences: preferences && preferences.preferences ? 1 : 0,
                analytics: preferences && preferences.analytics ? 1 : 0,
                marketing: preferences && preferences.marketing ? 1 : 0
            },
            at: new Date().toISOString()
        };
    },

    resolvePolicyVersion: function () {
        if (!this.root) {
            return '1.0.0';
        }
        const value = String(this.root.getAttribute('data-cookie-policy-version') || '').trim();
        return value || '1.0.0';
    },

    sendConsentToServer: function (choice, preferences) {
        const payload = {
            choice: String(choice || '').trim(),
            preferences: {
                preferences: preferences && preferences.preferences ? 1 : 0,
                analytics: preferences && preferences.analytics ? 1 : 0,
                marketing: preferences && preferences.marketing ? 1 : 0
            }
        };

        if (window.Request && window.Request.http && typeof window.Request.http.post === 'function') {
            return window.Request.http.post('/privacy/cookie/consent', payload).catch(function () {});
        }

        if (typeof window.fetch === 'function') {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = meta ? (meta.getAttribute('content') || '') : '';
            const body = new URLSearchParams();
            body.set('data', JSON.stringify(payload));

            return window.fetch('/api/privacy/cookie/consent', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: body.toString()
            }).catch(function () {});
        }

        return Promise.resolve();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        CookieBanner.init();
    });
} else {
    CookieBanner.init();
}
