import { Editor, Mark, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Image from '@tiptap/extension-image';

const globalWindow = (typeof window !== 'undefined') ? window : globalThis;
const editorInstances = new WeakMap();
let modalBindingsAttached = false;
const RICH_TEXT_SELECTOR = '.summernote, .richtext-editor';

const DEFAULT_OPTIONS = {
    height: 250,
    imageUploadTarget: 'richtext_image',
    allowedImageMime: {
        'image/jpeg': 'JPG',
        'image/png': 'PNG',
        'image/gif': 'GIF'
    }
};

const IMAGE_ALIGNMENTS = ['left', 'center', 'right'];

const Underline = Mark.create({
    name: 'underline',

    parseHTML() {
        return [
            { tag: 'u' },
            {
                style: 'text-decoration',
                getAttrs: function (value) {
                    return String(value || '').toLowerCase().indexOf('underline') !== -1 ? {} : false;
                }
            }
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return ['u', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            setUnderline: () => ({ commands }) => commands.setMark(this.name),
            toggleUnderline: () => ({ commands }) => commands.toggleMark(this.name),
            unsetUnderline: () => ({ commands }) => commands.unsetMark(this.name)
        };
    },

    addKeyboardShortcuts() {
        return {
            'Mod-u': () => this.editor.commands.toggleUnderline()
        };
    }
});

const LinkMark = Mark.create({
    name: 'link',
    inclusive: false,
    priority: 1000,
    keepOnSplit: false,

    addAttributes() {
        return {
            href: {
                default: null
            },
            target: {
                default: null
            },
            rel: {
                default: null
            },
            title: {
                default: null
            }
        };
    },

    parseHTML() {
        return [
            {
                tag: 'a[href]',
                getAttrs: function (node) {
                    const element = node && node.getAttribute ? node : null;
                    if (!element) {
                        return false;
                    }

                    const href = String(element.getAttribute('href') || '').trim();
                    if (href === '') {
                        return false;
                    }

                    return {
                        href: href,
                        target: element.getAttribute('target'),
                        rel: element.getAttribute('rel'),
                        title: element.getAttribute('title')
                    };
                }
            }
        ];
    },

    renderHTML({ HTMLAttributes }) {
        const attrs = Object.assign({}, HTMLAttributes || {});
        if (!attrs.href) {
            return ['span', 0];
        }

        return ['a', mergeAttributes(attrs), 0];
    },

    addCommands() {
        return {
            setLink: (attributes) => ({ commands }) => {
                const attrs = attributes && typeof attributes === 'object' ? attributes : {};
                const href = String(attrs.href || '').trim();
                if (href === '') {
                    return false;
                }

                return commands.setMark(this.name, {
                    href: href,
                    target: attrs.target || null,
                    rel: attrs.rel || null,
                    title: attrs.title || null
                });
            },
            unsetLink: () => ({ commands }) => commands.unsetMark(this.name, { extendEmptyMarkRange: true }),
            toggleLink: (attributes) => ({ commands }) => commands.toggleMark(this.name, attributes || {})
        };
    }
});

function parseStyleDeclarations(styleValue) {
    const declarations = {};
    const style = String(styleValue || '').trim();
    if (style === '') {
        return declarations;
    }

    const parts = style.split(';');
    for (let i = 0; i < parts.length; i += 1) {
        const part = String(parts[i] || '').trim();
        if (part === '' || part.indexOf(':') === -1) {
            continue;
        }

        const tokens = part.split(':');
        const property = String(tokens.shift() || '').trim().toLowerCase();
        const value = String(tokens.join(':') || '').trim();
        if (property !== '' && value !== '') {
            declarations[property] = value;
        }
    }

    return declarations;
}

function serializeStyleDeclarations(declarations) {
    const parts = [];
    const source = declarations && typeof declarations === 'object' ? declarations : {};
    const keys = Object.keys(source);
    for (let i = 0; i < keys.length; i += 1) {
        const key = String(keys[i] || '').trim().toLowerCase();
        const value = String(source[key] || '').trim();
        if (key !== '' && value !== '') {
            parts.push(key + ': ' + value);
        }
    }

    return parts.join('; ');
}

function normalizeImageAlign(value) {
    const align = String(value || '').trim().toLowerCase();
    return IMAGE_ALIGNMENTS.indexOf(align) !== -1 ? align : '';
}

function detectImageAlignment(element) {
    if (!element || typeof element.getAttribute !== 'function') {
        return '';
    }

    const explicitAlign = normalizeImageAlign(element.getAttribute('data-align'));
    if (explicitAlign !== '') {
        return explicitAlign;
    }

    const styles = parseStyleDeclarations(element.getAttribute('style'));
    const margin = String(styles.margin || '').trim().toLowerCase().replace(/\s+/g, ' ');
    const marginInline = String(styles['margin-inline'] || '').trim().toLowerCase().replace(/\s+/g, ' ');
    const marginLeft = String(styles['margin-left'] || '').trim().toLowerCase();
    const marginRight = String(styles['margin-right'] || '').trim().toLowerCase();
    const floatValue = String(styles.float || '').trim().toLowerCase();

    if (floatValue === 'left' || floatValue === 'right') {
        return floatValue;
    }

    if (marginInline === 'auto' || margin === '0 auto' || margin === 'auto') {
        return 'center';
    }

    if (marginLeft === 'auto' && marginRight === 'auto') {
        return 'center';
    }

    if (marginLeft === 'auto') {
        return 'right';
    }

    if (marginRight === 'auto') {
        return 'left';
    }

    return '';
}

function stripImageAlignmentStyle(styleValue) {
    const declarations = parseStyleDeclarations(styleValue);
    delete declarations.display;
    delete declarations.float;
    delete declarations.margin;
    delete declarations['margin-inline'];
    delete declarations['margin-left'];
    delete declarations['margin-right'];

    return serializeStyleDeclarations(declarations);
}

function buildImageStyle(styleValue, alignValue) {
    const declarations = parseStyleDeclarations(styleValue);
    const align = normalizeImageAlign(alignValue);

    delete declarations.display;
    delete declarations.float;
    delete declarations.margin;
    delete declarations['margin-inline'];
    delete declarations['margin-left'];
    delete declarations['margin-right'];

    declarations.display = 'block';

    if (align === 'left') {
        declarations['margin-left'] = '0';
        declarations['margin-right'] = 'auto';
    } else if (align === 'right') {
        declarations['margin-left'] = 'auto';
        declarations['margin-right'] = '0';
    } else {
        declarations['margin-left'] = 'auto';
        declarations['margin-right'] = 'auto';
    }

    return serializeStyleDeclarations(declarations);
}

const RichImage = Image.extend({
    addAttributes() {
        const parentAttributes = this.parent ? this.parent() : {};

        return Object.assign({}, parentAttributes, {
            align: {
                default: 'center',
                parseHTML: function (element) {
                    const align = detectImageAlignment(element);
                    return align !== '' ? align : 'center';
                },
                renderHTML: function () {
                    return {};
                }
            },
            style: {
                default: '',
                parseHTML: function (element) {
                    if (!element || typeof element.getAttribute !== 'function') {
                        return '';
                    }

                    return stripImageAlignmentStyle(element.getAttribute('style'));
                },
                renderHTML: function (attributes) {
                    const style = buildImageStyle(attributes && attributes.style ? attributes.style : '', attributes && attributes.align ? attributes.align : 'center');
                    return style !== '' ? { style: style } : {};
                }
            }
        });
    },

    addCommands() {
        const parentCommands = this.parent ? this.parent() : {};

        return Object.assign({}, parentCommands, {
            setImageAlignment: (align) => ({ commands }) => {
                const normalized = normalizeImageAlign(align);
                if (normalized === '') {
                    return false;
                }

                return commands.updateAttributes(this.name, { align: normalized });
            }
        });
    }
});

function hasJQuery() {
    return typeof globalWindow.$ === 'function';
}

function hasUploader() {
    return typeof globalWindow.Uploader === 'function';
}

function hasToast() {
    return !!(globalWindow.Toast && typeof globalWindow.Toast.show === 'function');
}

function notify(type, body) {
    if (hasToast()) {
        globalWindow.Toast.show({
            body: String(body || ''),
            type: String(type || 'info')
        });
        return;
    }

    if (typeof console !== 'undefined' && typeof console.warn === 'function') {
        console.warn('[TipTapEditor] ' + String(body || ''));
    }
}

function getRequestApi() {
    if (globalWindow.Request && globalWindow.Request.http && typeof globalWindow.Request.http.post === 'function') {
        return globalWindow.Request;
    }
    return null;
}

function normalizeHtml(value) {
    if (value == null) {
        return '';
    }

    let html = String(value).trim();
    if (html === '' || html === '<p></p>' || html === '<p><br></p>') {
        return '';
    }

    return html;
}

function getEmptyDocumentHtml() {
    return '<p></p>';
}

function mergeOptions(options) {
    const resolved = Object.assign({}, DEFAULT_OPTIONS, (options && typeof options === 'object') ? options : {});
    const parsedHeight = parseInt(resolved.height, 10);
    resolved.height = (!isNaN(parsedHeight) && parsedHeight > 0) ? parsedHeight : DEFAULT_OPTIONS.height;
    if (!resolved.imageUploadTarget) {
        resolved.imageUploadTarget = DEFAULT_OPTIONS.imageUploadTarget;
    }
    if (!resolved.allowedImageMime || typeof resolved.allowedImageMime !== 'object') {
        resolved.allowedImageMime = Object.assign({}, DEFAULT_OPTIONS.allowedImageMime);
    }
    return resolved;
}

function getToolbarButtons(toolbar) {
    return toolbar ? Array.from(toolbar.querySelectorAll('[data-editor-action]')) : [];
}

function syncTextareaValue(instance) {
    if (!instance || !instance.textarea) {
        return;
    }

    instance.textarea.value = normalizeHtml(instance.editor.getHTML());
}

function syncEditorFromTextarea(instance, force) {
    if (!instance || !instance.textarea || !instance.editor) {
        return;
    }

    const sourceHtml = normalizeHtml(instance.textarea.value);
    const editorHtml = normalizeHtml(instance.editor.getHTML());
    if (sourceHtml === editorHtml) {
        return;
    }

    if (force !== true && typeof instance.editor.isFocused === 'function' && instance.editor.isFocused === true) {
        return;
    }

    setContent(instance, sourceHtml);
}

function setButtonActive(button, isActive) {
    if (!button) {
        return;
    }
    button.classList.toggle('active', isActive === true);
    button.setAttribute('aria-pressed', isActive === true ? 'true' : 'false');
}

function setToolbarBusy(instance, isBusy) {
    if (!instance || !instance.toolbar) {
        return;
    }

    instance.toolbar.classList.toggle('is-busy', isBusy === true);
    getToolbarButtons(instance.toolbar).forEach(function (button) {
        if (button.getAttribute('data-editor-action') === 'image-upload') {
            button.disabled = isBusy === true || instance.disabled === true;
            button.classList.toggle('is-loading', isBusy === true);
            return;
        }
        button.disabled = instance.disabled === true;
    });
}

function updateToolbarState(instance) {
    if (!instance || !instance.toolbar || !instance.editor) {
        return;
    }

    const editor = instance.editor;
    const buttons = getToolbarButtons(instance.toolbar);
    for (let i = 0; i < buttons.length; i += 1) {
        const button = buttons[i];
        const action = String(button.getAttribute('data-editor-action') || '').trim();
        let active = false;

        if (action === 'bold') {
            active = editor.isActive('bold');
        } else if (action === 'italic') {
            active = editor.isActive('italic');
        } else if (action === 'underline') {
            active = editor.isActive('underline');
        } else if (action === 'bullet-list') {
            active = editor.isActive('bulletList');
        } else if (action === 'ordered-list') {
            active = editor.isActive('orderedList');
        } else if (action === 'link') {
            active = editor.isActive('link');
        } else if (action === 'align-left' || action === 'align-center' || action === 'align-right') {
            const align = action.replace('align-', '');
            if (editor.isActive('image')) {
                const imageAttrs = editor.getAttributes('image') || {};
                active = normalizeImageAlign(imageAttrs.align || 'center') === align;
            } else {
                active = editor.isActive({ textAlign: align });
            }
        }

        setButtonActive(button, active);
        if (action !== 'image-upload') {
            button.disabled = instance.disabled === true;
        }
    }

    if (instance.headingSelect) {
        if (editor.isActive('heading')) {
            const attrs = editor.getAttributes('heading') || {};
            instance.headingSelect.value = String(attrs.level || 'paragraph');
        } else {
            instance.headingSelect.value = 'paragraph';
        }
        instance.headingSelect.disabled = instance.disabled === true;
    }
}

function executeChain(editor, commandName, payload) {
    if (!editor || typeof editor.chain !== 'function') {
        return false;
    }

    const chain = editor.chain().focus();
    if (!chain || typeof chain[commandName] !== 'function') {
        return false;
    }

    if (typeof payload === 'undefined') {
        return chain[commandName]().run();
    }

    return chain[commandName](payload).run();
}

function resolveCurrentImageAlignment(editor) {
    if (!editor) {
        return 'center';
    }

    if (editor.isActive('image')) {
        const imageAttrs = editor.getAttributes('image') || {};
        const current = normalizeImageAlign(imageAttrs.align);
        return current !== '' ? current : 'center';
    }

    const textAlign = normalizeImageAlign((editor.getAttributes('paragraph') || {}).textAlign);
    return textAlign !== '' ? textAlign : 'center';
}

function applyAlignment(instance, align) {
    if (!instance || !instance.editor) {
        return;
    }

    const editor = instance.editor;
    if (editor.isActive('image') && typeof editor.commands.setImageAlignment === 'function') {
        editor.commands.setImageAlignment(align);
        return;
    }

    executeChain(editor, 'setTextAlign', align);
}

function setHeadingLevel(instance, value) {
    if (!instance || !instance.editor) {
        return;
    }

    const editor = instance.editor;
    if (value === 'paragraph') {
        executeChain(editor, 'setParagraph');
        return;
    }

    const level = parseInt(value, 10);
    if (isNaN(level) || level < 3 || level > 6) {
        return;
    }

    if (executeChain(editor, 'setHeading', { level: level })) {
        return;
    }

    executeChain(editor, 'toggleHeading', { level: level });
}

function promptForUrl(title, fallback) {
    if (typeof globalWindow.prompt !== 'function') {
        return '';
    }

    return String(globalWindow.prompt(String(title || ''), String(fallback || '')) || '').trim();
}

function setLink(instance) {
    if (!instance || !instance.editor) {
        return;
    }

    const editor = instance.editor;
    if (editor.isActive('link')) {
        executeChain(editor, 'unsetLink');
        return;
    }

    const url = promptForUrl('Inserisci URL link', 'https://');
    if (url === '') {
        return;
    }

    if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) {
        notify('warning', 'Inserisci un URL valido per il link.');
        return;
    }

    executeChain(editor, 'setLink', {
        href: url,
        target: '_blank',
        rel: 'noopener noreferrer'
    });
}

function insertImageFromUrl(instance) {
    if (!instance || !instance.editor) {
        return;
    }

    const url = promptForUrl('Inserisci URL immagine', 'https://');
    if (url === '') {
        return;
    }

    if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) {
        notify('warning', 'Inserisci un URL immagine valido.');
        return;
    }

    executeChain(instance.editor, 'setImage', {
        src: url,
        align: resolveCurrentImageAlignment(instance.editor)
    });
}

function finalizeUpload(instance, file) {
    const requestApi = getRequestApi();
    if (!instance || !file || !file.token || !requestApi) {
        notify('error', 'Upload immagine non disponibile.');
        setToolbarBusy(instance, false);
        return;
    }

    requestApi.http.post('/uploader?action=uploadFinalize&token=' + encodeURIComponent(file.token), {
        target: instance.options.imageUploadTarget
    }).then(function (response) {
        const dataset = response && response.dataset ? response.dataset : response;
        const url = dataset && dataset.url ? String(dataset.url).trim() : '';
        if (url === '') {
            notify('error', 'Upload completato ma URL immagine non disponibile.');
            setToolbarBusy(instance, false);
            return;
        }

        executeChain(instance.editor, 'setImage', {
            src: url,
            align: resolveCurrentImageAlignment(instance.editor)
        });
        setToolbarBusy(instance, false);
        notify('success', 'Immagine inserita.');
    }).catch(function (error) {
        const message = (requestApi && typeof requestApi.getErrorMessage === 'function')
            ? requestApi.getErrorMessage(error, 'Errore durante upload immagine.')
            : 'Errore durante upload immagine.';
        setToolbarBusy(instance, false);
        notify('error', message);
    });
}

function buildUploader(instance) {
    if (!instance) {
        return null;
    }

    if (instance.uploader) {
        return instance.uploader;
    }

    if (!hasUploader()) {
        return null;
    }

    instance.uploader = globalWindow.Uploader({
        url: '/uploader',
        multiple: false,
        autostart: true,
        target: instance.options.imageUploadTarget,
        allowed_mime: instance.options.allowedImageMime,
        newFile: function (file) {
            file.onProgress = function () {
                setToolbarBusy(instance, true);
            };
            file.onComplete = function () {
                finalizeUpload(instance, this);
            };
            file.onCancel = function () {
                setToolbarBusy(instance, false);
                notify('warning', 'Upload immagine annullato.');
            };
            file.onError = function () {
                setToolbarBusy(instance, false);
                notify('error', 'Errore durante upload immagine.');
            };
            return file;
        },
        onAddFile: function () {
            setToolbarBusy(instance, true);
        }
    });

    return instance.uploader;
}

function uploadImage(instance) {
    const uploader = buildUploader(instance);
    if (!uploader || typeof uploader.open !== 'function') {
        notify('warning', 'Uploader immagini non disponibile.');
        return;
    }
    uploader.open();
}

function createSeparator() {
    const separator = document.createElement('span');
    separator.className = 'tiptap-separator';
    separator.setAttribute('aria-hidden', 'true');
    return separator;
}

function createToolbarButton(instance, config) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary tiptap-toolbar-btn';
    button.setAttribute('data-editor-action', config.action);
    button.setAttribute('title', config.title);
    button.setAttribute('aria-label', config.title);
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = config.icon;
    button.addEventListener('click', function () {
        if (instance.disabled === true) {
            return;
        }
        config.onClick(instance);
        updateToolbarState(instance);
    });
    return button;
}

function buildToolbar(instance) {
    const toolbar = document.createElement('div');
    toolbar.className = 'tiptap-toolbar';

    const buttons = [
        {
            action: 'bold',
            title: 'Grassetto',
            icon: '<i class="bi bi-type-bold"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'toggleBold'); }
        },
        {
            action: 'italic',
            title: 'Corsivo',
            icon: '<i class="bi bi-type-italic"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'toggleItalic'); }
        },
        {
            action: 'underline',
            title: 'Sottolineato',
            icon: '<i class="bi bi-type-underline"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'toggleUnderline'); }
        },
        {
            action: 'link',
            title: 'Link',
            icon: '<i class="bi bi-link-45deg"></i>',
            onClick: setLink
        },
        {
            action: 'bullet-list',
            title: 'Elenco puntato',
            icon: '<i class="bi bi-list-ul"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'toggleBulletList'); }
        },
        {
            action: 'ordered-list',
            title: 'Elenco numerato',
            icon: '<i class="bi bi-list-ol"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'toggleOrderedList'); }
        },
        {
            action: 'divider',
            title: 'Separatore',
            icon: '<i class="bi bi-distribute-vertical"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'setHorizontalRule'); }
        },
        {
            action: 'align-left',
            title: 'Allinea a sinistra',
            icon: '<i class="bi bi-text-left"></i>',
            onClick: function (ctx) { applyAlignment(ctx, 'left'); }
        },
        {
            action: 'align-center',
            title: 'Allinea al centro',
            icon: '<i class="bi bi-text-center"></i>',
            onClick: function (ctx) { applyAlignment(ctx, 'center'); }
        },
        {
            action: 'align-right',
            title: 'Allinea a destra',
            icon: '<i class="bi bi-text-right"></i>',
            onClick: function (ctx) { applyAlignment(ctx, 'right'); }
        },
        {
            action: 'image-url',
            title: 'Immagine da URL',
            icon: '<i class="bi bi-image"></i>',
            onClick: insertImageFromUrl
        },
        {
            action: 'image-upload',
            title: 'Carica immagine',
            icon: '<i class="bi bi-upload"></i>',
            onClick: uploadImage
        },
        {
            action: 'undo',
            title: 'Annulla',
            icon: '<i class="bi bi-arrow-counterclockwise"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'undo'); }
        },
        {
            action: 'redo',
            title: 'Ripeti',
            icon: '<i class="bi bi-arrow-clockwise"></i>',
            onClick: function (ctx) { executeChain(ctx.editor, 'redo'); }
        }
    ];

    const groups = [
        buttons.slice(0, 4),
        buttons.slice(4, 7),
        buttons.slice(7, 10),
        buttons.slice(10, 12),
        buttons.slice(12)
    ];

    for (let i = 0; i < groups.length; i += 1) {
        if (i > 0) {
            toolbar.appendChild(createSeparator());
        }

        const group = groups[i];
        for (let j = 0; j < group.length; j += 1) {
            toolbar.appendChild(createToolbarButton(instance, group[j]));
        }

        if (i === 2) {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm tiptap-heading-select';
            select.setAttribute('aria-label', 'Livello intestazione');
            select.innerHTML = ''
                + '<option value="paragraph">Paragrafo</option>'
                + '<option value="3">H3</option>'
                + '<option value="4">H4</option>'
                + '<option value="5">H5</option>'
                + '<option value="6">H6</option>';
            select.addEventListener('change', function () {
                if (instance.disabled === true) {
                    return;
                }
                setHeadingLevel(instance, select.value);
                updateToolbarState(instance);
            });
            toolbar.appendChild(select);
            instance.headingSelect = select;
        }
    }

    return toolbar;
}

function createLayout(textarea, instance) {
    const wrapper = document.createElement('div');
    wrapper.className = 'note-editor ui-richtext tiptap-editor';
    wrapper.setAttribute('data-editor-wrapper', 'tiptap');

    const toolbar = buildToolbar(instance);
    const content = document.createElement('div');
    content.className = 'tiptap-editor__content';
    content.style.minHeight = String(instance.options.height) + 'px';

    wrapper.appendChild(toolbar);
    wrapper.appendChild(content);

    textarea.classList.add('tiptap-editor-source');
    textarea.style.display = 'none';
    textarea.insertAdjacentElement('afterend', wrapper);

    instance.wrapper = wrapper;
    instance.toolbar = toolbar;
    instance.content = content;
}

function buildEditorExtensions() {
    return [
        StarterKit.configure({
            heading: {
                levels: [3, 4, 5, 6]
            }
        }),
        Underline,
        LinkMark,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
            alignments: ['left', 'center', 'right']
        }),
        RichImage.configure({
            inline: false,
            allowBase64: false
        })
    ];
}

function attachEditor(textarea, options) {
    const instance = {
        textarea: textarea,
        options: mergeOptions(options),
        wrapper: null,
        toolbar: null,
        content: null,
        headingSelect: null,
        editor: null,
        uploader: null,
        disabled: textarea.disabled === true
    };

    createLayout(textarea, instance);

    instance.editor = new Editor({
        element: instance.content,
        extensions: buildEditorExtensions(),
        content: normalizeHtml(textarea.value) || getEmptyDocumentHtml(),
        editorProps: {
            attributes: {
                class: 'tiptap-editor__prose'
            }
        },
        onCreate: function () {
            syncTextareaValue(instance);
            updateToolbarState(instance);
            if (options && options.callbacks && typeof options.callbacks.onInit === 'function') {
                options.callbacks.onInit.call(textarea);
            }
        },
        onUpdate: function () {
            syncTextareaValue(instance);
            updateToolbarState(instance);
        },
        onSelectionUpdate: function () {
            updateToolbarState(instance);
        }
    });

    if (instance.disabled === true && typeof instance.editor.setEditable === 'function') {
        instance.editor.setEditable(false);
    }

    editorInstances.set(textarea, instance);
    if (hasJQuery()) {
        globalWindow.$(textarea).data('summernote', instance);
    }

    return instance;
}

function getInstance(textarea) {
    if (!textarea) {
        return null;
    }

    if (editorInstances.has(textarea)) {
        return editorInstances.get(textarea);
    }

    if (hasJQuery()) {
        return globalWindow.$(textarea).data('summernote') || null;
    }

    return null;
}

function ensureInstance(textarea, options) {
    let instance = getInstance(textarea);
    if (instance) {
        syncEditorFromTextarea(instance, true);
        return instance;
    }
    return attachEditor(textarea, options);
}

function setContent(instance, value) {
    if (!instance || !instance.editor) {
        return;
    }

    const html = normalizeHtml(value);
    instance.editor.commands.setContent(html || getEmptyDocumentHtml(), {
        emitUpdate: false
    });
    syncTextareaValue(instance);
    updateToolbarState(instance);
}

function pasteHtml(instance, value) {
    if (!instance || !instance.editor) {
        return;
    }

    const html = normalizeHtml(value);
    if (html === '') {
        return;
    }
    executeChain(instance.editor, 'insertContent', html);
}

function resetContent(instance) {
    if (!instance || !instance.editor) {
        return;
    }

    instance.editor.commands.setContent(getEmptyDocumentHtml(), {
        emitUpdate: false
    });
    syncTextareaValue(instance);
    updateToolbarState(instance);
}

function setDisabled(instance, disabled) {
    if (!instance || !instance.editor) {
        return;
    }

    instance.disabled = disabled === true;
    if (typeof instance.editor.setEditable === 'function') {
        instance.editor.setEditable(instance.disabled !== true);
    }
    if (instance.wrapper) {
        instance.wrapper.classList.toggle('is-disabled', instance.disabled === true);
    }
    updateToolbarState(instance);
}

function focusEditor(instance) {
    if (instance && instance.editor && typeof instance.editor.commands.focus === 'function') {
        instance.editor.commands.focus();
    }
}

function destroyInstance(textarea) {
    const instance = getInstance(textarea);
    if (!instance) {
        return;
    }

    if (instance.uploader && typeof instance.uploader.destroy === 'function') {
        instance.uploader.destroy();
    }

    if (instance.editor && typeof instance.editor.destroy === 'function') {
        instance.editor.destroy();
    }

    if (instance.wrapper && instance.wrapper.parentNode) {
        instance.wrapper.parentNode.removeChild(instance.wrapper);
    }

    textarea.style.display = '';
    textarea.classList.remove('tiptap-editor-source');
    editorInstances.delete(textarea);
    if (hasJQuery()) {
        globalWindow.$(textarea).removeData('summernote');
    }
}

function init(root, options) {
    const scope = root && root.querySelectorAll ? root : document;
    const nodes = scope.querySelectorAll(RICH_TEXT_SELECTOR);
    for (let i = 0; i < nodes.length; i += 1) {
        ensureInstance(nodes[i], options);
    }
    return nodes;
}

function attachModalBindings() {
    if (modalBindingsAttached === true || typeof document === 'undefined') {
        return;
    }

    document.addEventListener('show.bs.modal', function (event) {
        if (!event || !event.target) {
            return;
        }
        init(event.target);
    });

    modalBindingsAttached = true;
}

function installJqueryBridge() {
    if (!hasJQuery()) {
        return;
    }

    const jq = globalWindow.$;
    jq.summernote = jq.summernote || {};
    jq.summernote.options = jq.summernote.options || { modules: {} };
    jq.summernote.options.modules = jq.summernote.options.modules || {};

    jq.fn.summernote = function (command, value) {
        if (typeof command === 'object' || typeof command === 'undefined') {
            return this.each(function () {
                ensureInstance(this, command);
            });
        }

        if (typeof command !== 'string') {
            return this;
        }

        if (command === 'code') {
            if (typeof value === 'undefined') {
                const first = this.length ? getInstance(this[0]) : null;
                return first ? normalizeHtml(first.editor.getHTML()) : '';
            }

            return this.each(function () {
                setContent(ensureInstance(this), value);
            });
        }

        if (command === 'pasteHTML') {
            return this.each(function () {
                pasteHtml(ensureInstance(this), value);
            });
        }

        if (command === 'reset') {
            return this.each(function () {
                resetContent(ensureInstance(this));
            });
        }

        if (command === 'focus') {
            return this.each(function () {
                focusEditor(ensureInstance(this));
            });
        }

        if (command === 'disable') {
            return this.each(function () {
                setDisabled(ensureInstance(this), true);
            });
        }

        if (command === 'enable') {
            return this.each(function () {
                setDisabled(ensureInstance(this), false);
            });
        }

        if (command === 'destroy') {
            return this.each(function () {
                destroyInstance(this);
            });
        }

        return this;
    };
}

const TipTapEditor = {
    init: function (root, options) {
        attachModalBindings();
        return init(root || document, options || {});
    },
    getInstance: getInstance,
    ensureInstance: ensureInstance,
    destroy: destroyInstance,
    sync: function (root) {
        return init(root || document, {});
    }
};

installJqueryBridge();
attachModalBindings();

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            TipTapEditor.init(document);
        });
    } else {
        TipTapEditor.init(document);
    }
}

if (typeof window !== 'undefined') {
    globalWindow.TipTapEditor = TipTapEditor;
}

export { TipTapEditor as TipTapEditor };
export default TipTapEditor;
