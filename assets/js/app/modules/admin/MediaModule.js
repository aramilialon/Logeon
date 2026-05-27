const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function AdminMediaModuleFactory() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMedia !== 'undefined' && globalWindow.AdminMedia && typeof globalWindow.AdminMedia.init === 'function') {
                globalWindow.AdminMedia.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMediaModuleFactory = AdminMediaModuleFactory;
export { AdminMediaModuleFactory as AdminMediaModuleFactory };
export default AdminMediaModuleFactory;
