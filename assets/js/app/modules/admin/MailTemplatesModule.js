const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createAdminMailTemplatesModule() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMailTemplates !== 'undefined'
                && globalWindow.AdminMailTemplates
                && typeof globalWindow.AdminMailTemplates.init === 'function') {
                globalWindow.AdminMailTemplates.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMailTemplatesModuleFactory = createAdminMailTemplatesModule;
export { createAdminMailTemplatesModule as AdminMailTemplatesModuleFactory };
export default createAdminMailTemplatesModule;
