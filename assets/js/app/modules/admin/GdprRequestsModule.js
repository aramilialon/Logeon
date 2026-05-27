const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function AdminGdprRequestsModuleFactory() {
    return {
        mount: function () {
            if (
                typeof globalWindow.AdminGdprRequests !== 'undefined' &&
                globalWindow.AdminGdprRequests &&
                typeof globalWindow.AdminGdprRequests.init === 'function'
            ) {
                globalWindow.AdminGdprRequests.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminGdprRequestsModuleFactory = AdminGdprRequestsModuleFactory;
export { AdminGdprRequestsModuleFactory as AdminGdprRequestsModuleFactory };
export default AdminGdprRequestsModuleFactory;
