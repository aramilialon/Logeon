(function (globalWindow) {
    'use strict';

    function stripHtml(value) {
        return String(value || '').replace(/<[^>]+>/g, '');
    }

    function showToast(message, type) {
        if (globalWindow.Toast && typeof globalWindow.Toast.show === 'function') {
            globalWindow.Toast.show({ body: String(message || ''), type: type || 'info' });
            return true;
        }
        return false;
    }

    function ensureDialog(type, options, onConfirm) {
        if (typeof globalWindow.Dialog !== 'function') {
            return null;
        }
        return globalWindow.Dialog(type || 'info', {
            title: options.title || 'Conferma',
            body: options.body || '',
            confirmLabel: options.confirmLabel || options.confirmText || 'Confermo',
            cancelLabel: options.cancelLabel || options.cancelText || 'Annulla'
        }, onConfirm);
    }

    function confirm(options, onConfirm, onCancel) {
        options = (typeof options === 'string') ? { body: options } : (options || {});
        var title = options.title || 'Conferma';
        var body = options.body || '';
        var dialogType = options.type || 'warning';
        var resolved = false;
        var dialog = ensureDialog(dialogType, {
            title: title,
            body: body,
            confirmLabel: options.confirmLabel || 'Confermo',
            cancelLabel: options.cancelLabel || 'Annulla'
        }, function () {
            resolved = true;
            if (dialog && typeof dialog.hide === 'function') {
                dialog.hide();
            }
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        if (dialog && typeof dialog.show === 'function') {
            var modalEl = document.getElementById('generic-confirm');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    if (!resolved && typeof onCancel === 'function') {
                        onCancel();
                    }
                }, { once: true });
            }
            dialog.show();
            return;
        }

        if (globalWindow.console && typeof globalWindow.console.warn === 'function') {
            globalWindow.console.warn('[AdminDialogs] Dialog component unavailable:', stripHtml(title + ' ' + body));
        }
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }

    function confirmPromise(options) {
        return new Promise(function (resolve) {
            confirm(options, function () { resolve(true); }, function () { resolve(false); });
        });
    }

    function alertDialog(options, onClose) {
        options = (typeof options === 'string') ? { body: options } : (options || {});
        var title = options.title || 'Avviso';
        var body = options.body || '';
        var dialog = ensureDialog(options.type || 'info', {
            title: title,
            body: body,
            confirmLabel: options.confirmLabel || 'OK',
            cancelLabel: options.cancelLabel || 'Chiudi'
        }, function () {
            if (dialog && typeof dialog.hide === 'function') {
                dialog.hide();
            }
            if (typeof onClose === 'function') {
                onClose();
            }
        });

        if (dialog && typeof dialog.show === 'function') {
            dialog.show();
            return;
        }

        if (!showToast(stripHtml(body || title), options.toastType || 'info') && globalWindow.console && typeof globalWindow.console.warn === 'function') {
            globalWindow.console.warn('[AdminDialogs] Alert:', stripHtml(title + ' ' + body));
        }
        if (typeof onClose === 'function') {
            onClose();
        }
    }

    globalWindow.AdminDialogs = globalWindow.AdminDialogs || {};
    globalWindow.AdminDialogs.confirm = confirm;
    globalWindow.AdminDialogs.confirmPromise = confirmPromise;
    globalWindow.AdminDialogs.alert = alertDialog;
})(window);
