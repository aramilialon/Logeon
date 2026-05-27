const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createAdminMailLogsModule() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMailLogs !== 'undefined'
                && globalWindow.AdminMailLogs
                && typeof globalWindow.AdminMailLogs.init === 'function') {
                globalWindow.AdminMailLogs.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMailLogsModuleFactory = createAdminMailLogsModule;
export { createAdminMailLogsModule as AdminMailLogsModuleFactory };
export default createAdminMailLogsModule;
