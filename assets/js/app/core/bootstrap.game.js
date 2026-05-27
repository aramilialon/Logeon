window.APP_BOOTSTRAP_ENABLED = true;
window.APP_BOOTSTRAP_RUNTIME = 'game';
var appBootToken = null;

if (window.AppBootOverlay && typeof window.AppBootOverlay.beginBoot === 'function') {
    appBootToken = window.AppBootOverlay.beginBoot('game');
}

function startRuntime() {
    if (window.GameRuntime && typeof window.GameRuntime.start === 'function') {
        window.GameRuntime.start();
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

if (window.GameFeatureLoader && typeof window.GameFeatureLoader.loadForCurrentPage === 'function') {
    window.GameFeatureLoader.loadForCurrentPage()
        .catch(function () {})
        .finally(function () {
            finishBootstrap();
        });
} else {
    finishBootstrap();
}
