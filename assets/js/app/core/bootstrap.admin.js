window.APP_BOOTSTRAP_ENABLED = true;
window.APP_BOOTSTRAP_RUNTIME = 'admin';
var appBootToken = null;

if (window.AppBootOverlay && typeof window.AppBootOverlay.beginBoot === 'function') {
    appBootToken = window.AppBootOverlay.beginBoot('admin');
}

function startRuntime() {
    if (window.AdminRuntime && typeof window.AdminRuntime.start === 'function') {
        window.AdminRuntime.start();
    }
}

function finishBootstrap() {
    try {
        startRuntime();
    } finally {
        if (window.AppBootOverlay && typeof window.AppBootOverlay.endBoot === 'function') {
            window.AppBootOverlay.endBoot(appBootToken);
        }
    }
}

if (window.AdminFeatureLoader && typeof window.AdminFeatureLoader.loadForCurrentPage === 'function') {
    window.AdminFeatureLoader.loadForCurrentPage()
        .catch(function () {})
        .finally(function () {
            finishBootstrap();
        });
} else {
    finishBootstrap();
}
