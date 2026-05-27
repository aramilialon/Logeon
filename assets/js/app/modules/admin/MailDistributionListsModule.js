const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createAdminMailDistributionListsModule() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMailDistributionLists !== 'undefined'
                && globalWindow.AdminMailDistributionLists
                && typeof globalWindow.AdminMailDistributionLists.init === 'function') {
                globalWindow.AdminMailDistributionLists.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMailDistributionListsModuleFactory = createAdminMailDistributionListsModule;
export { createAdminMailDistributionListsModule as AdminMailDistributionListsModuleFactory };
export default createAdminMailDistributionListsModule;
