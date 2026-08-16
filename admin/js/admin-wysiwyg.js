/**
 * Lightweight admin WYSIWYG for textarea fields.
 * Stores HTML content back into the bound textarea value.
 */
(function initAdminWysiwygGlobal() {
    function escAttr(text) {
        return String(text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    function exec(editor, command, value = null) {
        editor.focus();
        document.execCommand(command, false, value);
    }

    function createButton(icon, title, onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'px-2 py-1 rounded border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50';
        btn.title = title;
        btn.innerHTML = icon;
        btn.addEventListener('click', onClick);
        return btn;
    }

    function createEditor(textarea, options = {}) {
        const wrapper = document.createElement('div');
        wrapper.className = 'border border-gray-300 rounded-lg overflow-hidden bg-white';

        const toolbar = document.createElement('div');
        toolbar.className = 'flex flex-wrap items-center gap-1 p-2 border-b border-gray-200 bg-gray-50';

        const editor = document.createElement('div');
        editor.className = 'px-3 py-2.5 text-sm leading-relaxed focus:outline-none';
        editor.contentEditable = 'true';
        editor.style.minHeight = options.minHeight || '110px';
        editor.dataset.placeholder = options.placeholder || textarea.placeholder || '';
        editor.innerHTML = textarea.value || '';

        const syncToTextarea = () => {
            textarea.value = editor.innerHTML.trim();
        };

        toolbar.appendChild(createButton('<b>B</b>', 'Fett', () => exec(editor, 'bold')));
        toolbar.appendChild(createButton('<i>I</i>', 'Kursiv', () => exec(editor, 'italic')));
        toolbar.appendChild(createButton('H2', 'Zwischenueberschrift', () => exec(editor, 'formatBlock', 'H2')));
        toolbar.appendChild(createButton('Liste', 'Aufzaehlung', () => exec(editor, 'insertUnorderedList')));
        toolbar.appendChild(createButton('1.', 'Nummeriert', () => exec(editor, 'insertOrderedList')));
        toolbar.appendChild(createButton('Link', 'Link einfuegen', () => {
            const url = window.prompt('Link-URL eingeben (https://...)', 'https://');
            if (!url) return;
            exec(editor, 'createLink', url);
        }));
        toolbar.appendChild(createButton('CLR', 'Formatierung entfernen', () => exec(editor, 'removeFormat')));

        editor.addEventListener('input', syncToTextarea);
        editor.addEventListener('blur', syncToTextarea);
        editor.addEventListener('paste', () => {
            setTimeout(syncToTextarea, 0);
        });

        wrapper.appendChild(toolbar);
        wrapper.appendChild(editor);

        textarea.classList.add('hidden');
        textarea.setAttribute('data-wysiwyg-bound', '1');
        textarea.insertAdjacentElement('afterend', wrapper);

        return {
            setValue(value) {
                editor.innerHTML = value || '';
                textarea.value = value || '';
            },
            getValue() {
                syncToTextarea();
                return textarea.value;
            },
        };
    }

    const instances = new Map();

    window.AdminWysiwyg = {
        init(textareaId, options = {}) {
            const textarea = document.getElementById(textareaId);
            if (!textarea) return null;
            if (instances.has(textareaId)) return instances.get(textareaId);
            const instance = createEditor(textarea, options);
            instances.set(textareaId, instance);
            return instance;
        },
        setValue(textareaId, value) {
            if (!instances.has(textareaId)) {
                this.init(textareaId);
            }
            const instance = instances.get(textareaId);
            if (instance) instance.setValue(value);
        },
        getValue(textareaId) {
            const instance = instances.get(textareaId);
            if (instance) return instance.getValue();
            const textarea = document.getElementById(textareaId);
            return textarea ? textarea.value : '';
        },
    };
})();