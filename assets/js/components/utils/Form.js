/**
 * Utility per la gestione di form jQuery: lettura, scrittura, reset e validazione.
 * Gestisce input standard, select (singolo e multiplo), checkbox, radio, file e campi rich text.
 * Dipende da jQuery (`$`).
 *
 * Uso tipico:
 * ```js
 * var f = Form();
 * var fields = f.getFields('#my-form');   // { name: 'Alice', role: 'admin', ... }
 * f.setFields('#my-form', { name: 'Bob', role: 'user' });
 * f.resetField('#my-form');
 * ```
 *
 * @returns {Object} Istanza Form con metodi getFields/setFields/checkForm/resetField/setFieldsInDiv.
 */
function Form() {
    var base = {
        form: null,
        richTextSelector: '.summernote, .richtext-editor',
        passwordToggleSelector: '[data-form-password-toggle]',
        passwordToggleIdPrefix: 'form-password-input-',
        passwordConfirmErrorMessage: 'La password di conferma non corrisponde.',
        passwordToggleRightDefault: '0.45rem',
        passwordToggleRightFloating: '0.65rem',
        passwordToggleRightInvalid: '2.35rem',
        isRichTextInput: function (input) {
            return !!(input && input.length && (input.hasClass('summernote') || input.hasClass('richtext-editor')));
        },

        ensurePasswordInputId: function (input) {
            if (!input || !input.length) {
                return '';
            }

            var id = String(input.attr('id') || '').trim();
            if (id !== '') {
                return id;
            }

            var generated = this.passwordToggleIdPrefix + Math.random().toString(36).slice(2, 10);
            input.attr('id', generated);
            return generated;
        },

        createPasswordToggleButton: function (targetId) {
            var button = $('<button type="button" class="btn btn-sm lf-password-toggle-btn" data-form-password-toggle aria-label="Mostra password" title="Mostra password"></button>');
            button.attr('data-target', targetId);
            button.append('<i class="bi bi-eye" aria-hidden="true"></i>');
            return button;
        },

        resolvePasswordFieldWrapper: function (input) {
            if (!input || !input.length) {
                return null;
            }

            var floating = input.closest('.form-floating');
            if (floating.length) {
                floating.addClass('lf-password-field');
                return floating;
            }

            var group = input.closest('.input-group');
            if (group.length) {
                group.addClass('lf-password-field');
                return group;
            }

            var wrapper = input.parent('.lf-password-field');
            if (wrapper.length) {
                return wrapper;
            }

            input.wrap('<div class="lf-password-field"></div>');
            return input.parent('.lf-password-field');
        },

        enhancePasswordInput: function (input) {
            if (!input || !input.length) {
                return;
            }

            if (String(input.attr('type') || '').toLowerCase() !== 'password') {
                return;
            }

            if (String(input.attr('data-password-toggle-disabled') || '') === '1') {
                return;
            }

            if (String(input.attr('data-password-toggle-bound') || '') === '1') {
                return;
            }

            var targetId = this.ensurePasswordInputId(input);
            if (targetId === '') {
                return;
            }

            var wrapper = this.resolvePasswordFieldWrapper(input);
            if (!wrapper || !wrapper.length) {
                return;
            }

            if (wrapper.find(this.passwordToggleSelector + '[data-target="' + targetId + '"]').length > 0) {
                input.attr('data-password-toggle-bound', '1');
                this.positionPasswordToggle(input);
                return;
            }

            var button = this.createPasswordToggleButton(targetId);
            input.addClass('lf-password-toggle-input');
            wrapper.append(button);

            input.attr('data-password-toggle-bound', '1');
            this.positionPasswordToggle(input);
        },

        positionPasswordToggle: function (input) {
            if (!input || !input.length) {
                return;
            }

            var targetId = String(input.attr('id') || '').trim();
            if (targetId === '') {
                return;
            }

            var wrapper = this.resolvePasswordFieldWrapper(input);
            if (!wrapper || !wrapper.length) {
                return;
            }

            var button = wrapper.find(this.passwordToggleSelector + '[data-target="' + targetId + '"]');
            if (!button.length) {
                return;
            }

            var isInvalid = input.hasClass('is-invalid')
                || String(input.attr('aria-invalid') || '').toLowerCase() === 'true';
            var rightValue = wrapper.hasClass('form-floating')
                ? this.passwordToggleRightFloating
                : this.passwordToggleRightDefault;
            if (isInvalid) {
                rightValue = this.passwordToggleRightInvalid;
            }
            button.css('right', rightValue);

            if (wrapper.hasClass('form-floating')) {
                button.css('top', '');
                return;
            }

            var top = input.position().top + (input.outerHeight() / 2);
            if (isFinite(top)) {
                button.css('top', top + 'px');
            }
        },

        initPasswordToggles: function (scope) {
            var root = null;
            if (scope && scope.jquery) {
                root = scope;
            } else if (scope) {
                root = $(scope);
            } else {
                root = $(document);
            }

            if (!root || !root.length) {
                root = $(document);
            }

            var self = this;
            root.find('input[type="password"]').each(function () {
                self.enhancePasswordInput($(this));
            });

            $(window).off('resize.form-password-toggle').on('resize.form-password-toggle', function () {
                $('[data-password-toggle-bound="1"]').each(function () {
                    self.positionPasswordToggle($(this));
                });
            });

            $(document).off('input.form-password-toggle-position change.form-password-toggle-position blur.form-password-toggle-position', 'input[type="password"]');
            $(document).on('input.form-password-toggle-position change.form-password-toggle-position blur.form-password-toggle-position', 'input[type="password"]', function () {
                self.positionPasswordToggle($(this));
            });

            $(document).off('click.form-password-toggle', self.passwordToggleSelector);
            $(document).on('click.form-password-toggle', self.passwordToggleSelector, function (event) {
                event.preventDefault();

                var toggle = $(this);
                var targetId = String(toggle.attr('data-target') || '').trim();
                if (targetId === '') {
                    return;
                }

                var input = $('#' + targetId);
                if (!input.length) {
                    return;
                }

                var isPassword = String(input.attr('type') || '').toLowerCase() === 'password';
                input.attr('type', isPassword ? 'text' : 'password');

                var icon = toggle.find('i');
                if (icon.length) {
                    icon.removeClass('bi-eye bi-eye-slash');
                    icon.addClass(isPassword ? 'bi-eye-slash' : 'bi-eye');
                }

                var nextLabel = isPassword ? 'Nascondi password' : 'Mostra password';
                toggle.attr('aria-label', nextLabel);
                toggle.attr('title', nextLabel);
                self.positionPasswordToggle(input);
            });

            return this;
        },

        isPasswordConfirmInput: function (input) {
            if (!input || !input.length) {
                return false;
            }

            var name = String(input.attr('name') || '').toLowerCase();
            var id = String(input.attr('id') || '').toLowerCase();
            var marker = String(input.attr('data-password-confirm') || '').toLowerCase();
            if (marker === '1' || marker === 'true' || marker === 'yes') {
                return true;
            }

            return /(confirm|rewrite|repeat|ripeti|conferma)/.test(name) || /(confirm|rewrite|repeat|ripeti|conferma)/.test(id);
        },

        resolvePasswordConfirmPair: function (form) {
            if (!form || !form.length) {
                return null;
            }

            var inputs = form.find('input[type="password"]').filter(function () {
                return !$(this).is(':disabled');
            });
            if (inputs.length < 2) {
                return null;
            }

            var confirmInput = null;
            var baseInput = null;
            var confirmIndex = -1;

            inputs.each(function () {
                var current = $(this);
                if (!confirmInput && base.isPasswordConfirmInput(current)) {
                    confirmInput = current;
                }
            });

            if (!confirmInput) {
                return null;
            }

            for (var i = 0; i < inputs.length; i += 1) {
                if (inputs.eq(i).get(0) === confirmInput.get(0)) {
                    confirmIndex = i;
                    break;
                }
            }
            if (confirmIndex < 0) {
                return null;
            }

            // Priorita: il campo password immediatamente precedente alla conferma.
            for (var prev = confirmIndex - 1; prev >= 0; prev -= 1) {
                var prevInput = inputs.eq(prev);
                if (!base.isPasswordConfirmInput(prevInput)) {
                    baseInput = prevInput;
                    break;
                }
            }

            // Fallback: primo campo password non-marked come conferma.
            if (!baseInput) {
                inputs.each(function () {
                    var current = $(this);
                    if (current.get(0) === confirmInput.get(0)) {
                        return;
                    }
                    if (!base.isPasswordConfirmInput(current) && !baseInput) {
                        baseInput = current;
                    }
                });
            }

            if (!baseInput || !confirmInput) {
                return null;
            }

            return {
                base: baseInput,
                confirm: confirmInput
            };
        },

        ensurePasswordConfirmFeedback: function (confirmInput) {
            if (!confirmInput || !confirmInput.length) {
                return null;
            }

            var feedback = confirmInput.siblings('.lf-password-confirm-feedback');
            if (feedback.length) {
                return feedback;
            }

            feedback = $('<div class="invalid-feedback lf-password-confirm-feedback"></div>');
            feedback.text(this.passwordConfirmErrorMessage);
            confirmInput.after(feedback);
            return feedback;
        },

        validatePasswordConfirmPair: function (baseInput, confirmInput, forceShow) {
            if (!baseInput || !baseInput.length || !confirmInput || !confirmInput.length) {
                return true;
            }

            var baseValue = String(baseInput.val() || '');
            var confirmValue = String(confirmInput.val() || '');
            var shouldValidate = forceShow === true || confirmValue !== '';
            var isValid = !shouldValidate || baseValue === confirmValue;

            var feedback = this.ensurePasswordConfirmFeedback(confirmInput);
            if (!isValid) {
                confirmInput.addClass('is-invalid');
                confirmInput.attr('aria-invalid', 'true');
                confirmInput.get(0).setCustomValidity(this.passwordConfirmErrorMessage);
                if (feedback && feedback.length) {
                    feedback.text(this.passwordConfirmErrorMessage);
                }
            } else {
                confirmInput.removeClass('is-invalid');
                confirmInput.removeAttr('aria-invalid');
                confirmInput.get(0).setCustomValidity('');
            }

            this.positionPasswordToggle(confirmInput);

            return isValid;
        },

        enhancePasswordConfirmInForm: function (form) {
            if (!form || !form.length) {
                return;
            }

            var pair = this.resolvePasswordConfirmPair(form);
            if (!pair) {
                return;
            }

            pair.base.attr('data-password-confirm-source', '1');
            pair.confirm.attr('data-password-confirm-target', '1');
            this.ensurePasswordConfirmFeedback(pair.confirm);
            this.validatePasswordConfirmPair(pair.base, pair.confirm, false);
        },

        initPasswordConfirmValidation: function (scope) {
            var root = null;
            if (scope && scope.jquery) {
                root = scope;
            } else if (scope) {
                root = $(scope);
            } else {
                root = $(document);
            }

            if (!root || !root.length) {
                root = $(document);
            }

            var self = this;

            root.find('form').each(function () {
                self.enhancePasswordConfirmInForm($(this));
            });

            $(document).off('input.form-password-confirm change.form-password-confirm', 'input[type="password"]');
            $(document).on('input.form-password-confirm change.form-password-confirm', 'input[type="password"]', function () {
                var field = $(this);
                var form = field.closest('form');
                if (!form.length) {
                    return;
                }

                self.enhancePasswordConfirmInForm(form);
                var pair = self.resolvePasswordConfirmPair(form);
                if (!pair) {
                    return;
                }
                self.validatePasswordConfirmPair(pair.base, pair.confirm, false);
            });

            $(document).off('submit.form-password-confirm');
            $(document).on('submit.form-password-confirm', 'form', function (event) {
                var form = $(this);
                self.enhancePasswordConfirmInForm(form);
                var pair = self.resolvePasswordConfirmPair(form);
                if (!pair) {
                    return;
                }

                var isValid = self.validatePasswordConfirmPair(pair.base, pair.confirm, true);
                if (isValid) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                pair.confirm.trigger('focus');

                if (typeof window !== 'undefined' && window.Toast && typeof window.Toast.show === 'function') {
                    window.Toast.show({
                        body: self.passwordConfirmErrorMessage,
                        type: 'warning'
                    });
                }
            });

            return this;
        },

        /**
         * Legge tutti i valori degli input del form come oggetto chiave-valore.
         * @param {string|jQuery|HTMLElement} form - Selettore, jQuery o elemento DOM.
         * @returns {Object.<string, string|number|boolean|string[]|null>} Mappa nome → valore.
         */
        getFields: function (form) {
            if (!this.checkForm(form)) {
                return {};
            }
            return this.getFormInputs();
        },

        /**
         * Imposta i valori degli input del form dal dataset fornito.
         * @param {string|jQuery|HTMLElement} form
         * @param {Object.<string, *>} dataset - Mappa nome → valore da applicare agli input.
         * @returns {Object} this
         */
        setFields: function (form, dataset) {
            if (!this.checkForm(form)) {
                return this;
            }
            var self = this;

            if (dataset == null || typeof dataset !== 'object') {
                dataset = {};
            }

            this.form.find(':input').each(function () {
                var input = $(this);
                var name = input.attr('name');
                if (!name) {
                    return;
                }

                var hasValue = Object.prototype.hasOwnProperty.call(dataset, name);
                var value = hasValue ? dataset[name] : null;

                if (input.is('select') && input.prop('multiple')) {
                    if (Array.isArray(value)) {
                        input.val(value.map(function (item) { return String(item); }));
                    } else if (value == null || value === '') {
                        input.val([]);
                    } else {
                        input.val([String(value)]);
                    }
                    return;
                }

                if (input.is('select') && !input.prop('multiple')) {
                    if (value == null || value === '') {
                        input.find('option:first').prop('selected', true);
                    } else {
                        input.val(value);
                    }
                    return;
                }

                if (input.is(':checkbox')) {
                    input.prop('checked', value == 1 || value === true || value === '1' || value === 'true' || value === 'on');
                    return;
                }

                if (input.is(':radio')) {
                    input.prop('checked', value != null && String(value) === String(input.val()));
                    return;
                }

                if (self.isRichTextInput(input)) {
                    if (typeof input.summernote === 'function') {
                        input.summernote('code', value != null ? String(value) : '');
                    } else {
                        input.val(value != null ? value : '');
                    }
                    return;
                }

                input.val(value != null ? value : '');
            }).trigger('change');

            return this;
        },

        setFieldsInDiv: function (container, dataset) {
            if (!container || !dataset || typeof dataset !== 'object') {
                return this;
            }

            for (var key in dataset) {
                if (!Object.prototype.hasOwnProperty.call(dataset, key)) {
                    continue;
                }

                var value = dataset[key];
                if (value === null || typeof value === 'undefined' || value === '') {
                    value = '-';
                }

                container.find('[name="' + key + '"]').text(value);
            }

            return this;
        },

        /**
         * Resetta il form: svuota input, deseleziona checkbox/radio, ripristina select al primo option.
         * Non tocca i campi `_csrf` e `csrf_token`.
         * @param {string|jQuery|HTMLElement} form
         * @returns {Object} this
         */
        resetField: function (form) {
            if (!this.checkForm(form)) {
                return this;
            }

            if (this.form.length && this.form[0] && typeof this.form[0].reset === 'function') {
                this.form[0].reset();
            }

            this.form.find(':checkbox, :radio').prop('checked', false);
            this.form.find('input[type="hidden"]').each(function () {
                var input = $(this);
                var name = String(input.attr('name') || '').trim();
                if (name === '_csrf' || name === 'csrf_token') {
                    return;
                }
                input.val('');
            });

            this.form.find('select').each(function () {
                var select = $(this);
                if (select.prop('multiple')) {
                    select.val([]);
                } else {
                    select.find('option:first').prop('selected', true);
                }
            });

            this.form.find(this.richTextSelector).each(function () {
                var editor = $(this);
                if (typeof editor.summernote === 'function') {
                    editor.summernote('code', '');
                } else {
                    editor.val('');
                }
            });

            this.form.find('input[type="hidden"]').trigger('change');
            return this;
        },

        getFormInputs: function () {
            var fields = {};
            var self = this;

            this.form.find(':input').not(':button,:submit,:reset').each(function () {
                var input = $(this);
                if (input.is(':disabled')) {
                    return;
                }

                if (input.is(':file')) {
                    return;
                }

                if (!input.attr('id') && !input.attr('name')) {
                    return;
                }

                var name = input.attr('name');
                if (name == null || String(name).trim() === '') {
                    var idAttr = input.attr('id');
                    if (!idAttr) {
                        return;
                    }
                    name = idAttr.split('_').pop();
                }
                if (!name) {
                    return;
                }

                if (input.is(':checkbox')) {
                    fields[name] = input.prop('checked') ? 1 : 0;
                    return;
                }

                if (input.is(':radio')) {
                    if (input.prop('checked')) {
                        fields[name] = input.val();
                    } else if (typeof fields[name] === 'undefined') {
                        fields[name] = null;
                    }
                    return;
                }

                if (input.is('select') && input.prop('multiple')) {
                    var values = input.val();
                    fields[name] = Array.isArray(values) ? values : [];
                    return;
                }

                if (self.isRichTextInput(input) && typeof input.summernote === 'function') {
                    var html = input.summernote('code');
                    fields[name] = (html === '<p><br></p>') ? '' : html;
                    return;
                }

                fields[name] = input.val();
            });

            return fields;
        },

        /**
         * Risolve e valida il riferimento al form; imposta `this.form` se valido.
         * @param {string|jQuery|HTMLElement} form
         * @returns {jQuery|false} L'oggetto jQuery del form, o false se non trovato.
         */
        checkForm: function (form) {
            if (form == null) {
                this._showError('Form non assegnata', 'Non e stata assegnata nessuna form.');
                return false;
            }

            var jForm = null;
            if (typeof window.$ !== 'undefined' && form && form.jquery) {
                jForm = form;
            } else if (typeof HTMLElement !== 'undefined' && form instanceof HTMLElement) {
                jForm = $(form);
            } else {
                var selector = String(form).trim();
                if (selector === '') {
                    this._showError('Form non assegnata', 'Non e stata assegnata nessuna form.');
                    return false;
                }
                if (selector.charAt(0) !== '#') {
                    selector = '#' + selector;
                }
                jForm = $(selector);
            }

            if (!jForm || jForm.length === 0) {
                this._showError('Form non trovata', 'Non e stata trovata la form assegnata.');
                return false;
            }

            this.form = jForm;
            return this.form;
        },

        _showError: function (title, body) {
            if (typeof window !== 'undefined' && typeof window.Dialog === 'function') {
                window.Dialog('danger', {
                    title: title,
                    body: body
                }).show();
                return;
            }

            if (typeof console !== 'undefined' && typeof console.error === 'function') {
                console.error('[Form] ' + title + ': ' + body);
            }
        }
    };

    return base;
}

if (typeof window !== 'undefined') {
    window.Form = Form;
    if (typeof window.$ !== 'undefined') {
        $(function () {
            Form().initPasswordToggles(document);
            Form().initPasswordConfirmValidation(document);
        });
    }
}
