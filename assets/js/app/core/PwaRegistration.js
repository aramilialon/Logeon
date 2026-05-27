function readJsonScript(id, fallback) {
    var node = document.getElementById(id);
    if (!node) {
        return fallback;
    }

    var raw = String(node.textContent || node.innerText || '').trim();
    if (raw === '') {
        return fallback;
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        return fallback;
    }
}

function isSecureContextForPwa() {
    return window.location.protocol === 'https:'
        || window.location.hostname === 'localhost'
        || window.location.hostname === '127.0.0.1';
}

function registerServiceWorker() {
    if (typeof window === 'undefined' || typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    var pwa = readJsonScript('app-pwa-config', {});
    if (!pwa || pwa.enabled !== true || !pwa.service_worker_url) {
        return;
    }

    if (!isSecureContextForPwa()) {
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register(pwa.service_worker_url, {
            scope: pwa.scope || '/'
        }).catch(function (error) {
            console.warn('[Logeon][PWA] Service worker registration failed.', error);
        });
    });
}

registerServiceWorker();

window.PwaRegistration = window.PwaRegistration || {};
window.PwaRegistration.registerServiceWorker = registerServiceWorker;
