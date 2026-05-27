const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createAdminMailCampaignsModule() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMailCampaigns !== 'undefined'
                && globalWindow.AdminMailCampaigns
                && typeof globalWindow.AdminMailCampaigns.init === 'function') {
                globalWindow.AdminMailCampaigns.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMailCampaignsModuleFactory = createAdminMailCampaignsModule;
export { createAdminMailCampaignsModule as AdminMailCampaignsModuleFactory };
export default createAdminMailCampaignsModule;
