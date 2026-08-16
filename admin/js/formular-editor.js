const formBuilderState = {
    fields: [],
    slugTouched: false,
};

const FORM_TYPES = [
    { value: 'text', label: 'Textfeld' },
    { value: 'email', label: 'E-Mail' },
    { value: 'tel', label: 'Telefon' },
    { value: 'textarea', label: 'Textbereich' },
    { value: 'select', label: 'Dropdown' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'file', label: 'Datei-Upload' },
    { value: 'signature', label: 'Signatur-Pad' },
    { value: 'heading', label: 'Überschrift' },
    { value: 'divider', label: 'Trennelement' },
];

function formEscape(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function slugifyForm(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function fieldNameFromLabel(label, fallbackIndex) {
    const normalized = String(label || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s_]/g, '')
        .replace(/[\s-]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '');
    if (normalized) {
        return /^[a-z]/.test(normalized) ? normalized : `field_${normalized}`;
    }
    return `field_${fallbackIndex + 1}`;
}

function createField(type = 'text') {
    return {
        type,
        label: '',
        name: '',
        placeholder: '',
        helpText: '',
        required: false,
        layoutWidth: 'full',
        selectOptions: '',
        checkboxText: '',
        accept: '',
        maxSizeMb: 10,
    };
}

function setDefaultFields() {
    formBuilderState.fields = [
        { ...createField('heading'), label: 'Kontaktformular' },
        { ...createField('text'), label: 'Name', name: 'name', required: true },
        { ...createField('email'), label: 'E-Mail', name: 'email', required: true },
        { ...createField('textarea'), label: 'Nachricht', name: 'message', required: true, placeholder: 'Ihre Nachricht...' },
    ];
}

function renderFields() {
    const list = document.getElementById('fieldsList');
    if (!list) return;

    if (!formBuilderState.fields.length) {
        list.innerHTML = '<p class="text-sm text-gray-400">Noch keine Felder vorhanden.</p>';
        return;
    }

    list.innerHTML = formBuilderState.fields.map((field, index) => {
        const type = field.type || 'text';
        const layoutWidth = field.layoutWidth === 'half' ? 'half' : 'full';
        const isStructural = type === 'heading' || type === 'divider';
        const isSelect = type === 'select';
        const isCheckbox = type === 'checkbox';
        const isFile = type === 'file';
        const supportsPlaceholder = ['text', 'email', 'tel', 'textarea'].includes(type);
        const typeOptions = FORM_TYPES.map((option) => `<option value="${option.value}" ${option.value === type ? 'selected' : ''}>${option.label}</option>`).join('');

        return `
            <article class="border border-gray-200 rounded-xl p-4 bg-gray-50/30" data-field-card="${index}">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Feld ${index + 1}</div>
                    <div class="flex items-center gap-1">
                        <button type="button" data-field-move-up="${index}" class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-white" title="Nach oben">
                            <span class="material-symbols-outlined text-base">arrow_upward</span>
                        </button>
                        <button type="button" data-field-move-down="${index}" class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-white" title="Nach unten">
                            <span class="material-symbols-outlined text-base">arrow_downward</span>
                        </button>
                        <button type="button" data-field-delete="${index}" class="p-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50" title="Löschen">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Typ</label>
                        <select data-field-input="${index}" data-key="type" class="w-full rounded-lg border-gray-300 text-sm">
                            ${typeOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">${type === 'heading' ? 'Überschriftstext' : 'Label'}</label>
                        <input data-field-input="${index}" data-key="label" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.label)}" placeholder="${type === 'divider' ? 'Optionaler Trenner-Titel' : ''}">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 ${isStructural ? 'opacity-50' : ''}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Feldname</label>
                        <input data-field-input="${index}" data-key="name" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.name)}" placeholder="z. B. email" ${isStructural ? 'disabled' : ''}>
                    </div>
                    <div class="${supportsPlaceholder ? '' : 'hidden'}">
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Placeholder</label>
                        <input data-field-input="${index}" data-key="placeholder" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.placeholder)}">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 ${isStructural ? 'hidden' : ''}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Feldbreite</label>
                        <select data-field-input="${index}" data-key="layoutWidth" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="full" ${layoutWidth === 'full' ? 'selected' : ''}>Volle Breite</option>
                            <option value="half" ${layoutWidth === 'half' ? 'selected' : ''}>Halbe Breite</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Hilfetext</label>
                    <input data-field-input="${index}" data-key="helpText" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.helpText)}" placeholder="Optionaler Hinweis unter dem Feld">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 ${isCheckbox ? '' : 'hidden'}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Checkbox-Text</label>
                        <input data-field-input="${index}" data-key="checkboxText" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.checkboxText)}" placeholder="Ich stimme den Bedingungen zu.">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 mt-3 ${isSelect ? '' : 'hidden'}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Dropdown-Optionen</label>
                        <textarea data-field-input="${index}" data-key="selectOptions" rows="4" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Eine Option pro Zeile">${formEscape(field.selectOptions || '')}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Die erste Option erscheint zuerst. Leere Zeilen werden ignoriert.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 ${isFile ? '' : 'hidden'}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Erlaubte Dateitypen</label>
                        <input data-field-input="${index}" data-key="accept" type="text" class="w-full rounded-lg border-gray-300 text-sm" value="${formEscape(field.accept)}" placeholder=".pdf,.jpg,.png">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Max. Größe (MB)</label>
                        <input data-field-input="${index}" data-key="maxSizeMb" type="number" min="1" max="20" class="w-full rounded-lg border-gray-300 text-sm" value="${Number(field.maxSizeMb || 10)}">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 mt-3 text-sm text-gray-700 ${isStructural ? 'hidden' : ''}">
                    <input data-field-input="${index}" data-key="required" type="checkbox" class="rounded border-gray-300 text-primary" ${field.required ? 'checked' : ''}>
                    Pflichtfeld
                </label>
            </article>
        `;
    }).join('');
}

function updateEmbedHints() {
    const slugInput = document.getElementById('formSlug');
    const shortcodeInput = document.getElementById('embedShortcode');
    const htmlInput = document.getElementById('embedHtml');
    if (!slugInput || !shortcodeInput || !htmlInput) return;

    const slug = slugifyForm(slugInput.value);
    if (!slug) {
        shortcodeInput.value = '';
        htmlInput.value = '';
        return;
    }

    shortcodeInput.value = `[form:${slug}]`;
    htmlInput.value = `<div data-wkc-form="${slug}"></div>`;
}

function collectPayload() {
    const payload = new URLSearchParams();
    if (FORM_ID > 0) {
        payload.append('id', String(FORM_ID));
    }
    payload.append('title', document.getElementById('formTitle').value.trim());
    payload.append('slug', slugifyForm(document.getElementById('formSlug').value));
    payload.append('description', document.getElementById('formDescription').value.trim());
    payload.append('target_path', document.getElementById('formTargetPath').value.trim());
    payload.append('success_message', document.getElementById('formSuccessMessage').value.trim());
    payload.append('submit_label', document.getElementById('formSubmitLabel').value.trim());
    payload.append('is_active', document.getElementById('formIsActive').checked ? '1' : '0');
    payload.append('email_recipients', document.getElementById('emailRecipients').value.trim());
    payload.append('email_subject', document.getElementById('emailSubject').value.trim());
    payload.append('smtp_host', document.getElementById('smtpHost').value.trim());
    payload.append('smtp_port', document.getElementById('smtpPort').value.trim());
    payload.append('smtp_secure', document.getElementById('smtpSecure').value);
    payload.append('smtp_user', document.getElementById('smtpUser').value.trim());
    payload.append('smtp_pass', document.getElementById('smtpPass').value);
    payload.append('smtp_from', document.getElementById('smtpFrom').value.trim());
    payload.append('smtp_from_name', document.getElementById('smtpFromName').value.trim());
    payload.append('fields_json', JSON.stringify(formBuilderState.fields));
    return payload;
}

function mapFieldFromApi(field) {
    return {
        type: field.type || 'text',
        label: field.label || '',
        name: field.name || '',
        placeholder: field.placeholder || '',
        helpText: field.helpText || '',
        required: !!field.required,
        layoutWidth: field.layoutWidth === 'half' ? 'half' : 'full',
        selectOptions: field.selectOptions || '',
        checkboxText: field.checkboxText || '',
        accept: field.accept || '',
        maxSizeMb: Number(field.maxSizeMb || 10),
    };
}

async function loadForm() {
    if (!FORM_ID) {
        setDefaultFields();
        renderFields();
        return;
    }
    formBuilderState.slugTouched = true;

    const response = await fetch(`../api/forms.php?action=detail&id=${FORM_ID}`, { credentials: 'include' });
    const data = await response.json();
    if (!response.ok || !data.form) {
        throw new Error(data.error || 'Formular konnte nicht geladen werden.');
    }

    document.getElementById('editorTitle').textContent = 'Formular bearbeiten';
    document.getElementById('deleteFormBtn').classList.remove('hidden');

    const form = data.form;
    document.getElementById('formTitle').value = form.title || '';
    document.getElementById('formSlug').value = form.slug || '';
    document.getElementById('formDescription').value = form.description || '';
    document.getElementById('formTargetPath').value = form.target_path || '';
    document.getElementById('formSuccessMessage').value = form.success_message || '';
    document.getElementById('formSubmitLabel').value = form.submit_label || '';
    document.getElementById('formIsActive').checked = !!form.is_active;
    document.getElementById('emailRecipients').value = form.email_recipients || '';
    document.getElementById('emailSubject').value = form.email_subject || '';
    document.getElementById('smtpHost').value = form.smtp_host || '';
    document.getElementById('smtpPort').value = form.smtp_port || '';
    document.getElementById('smtpSecure').value = form.smtp_secure || 'tls';
    document.getElementById('smtpUser').value = form.smtp_user || '';
    document.getElementById('smtpPass').value = form.smtp_pass || '';
    document.getElementById('smtpFrom').value = form.smtp_from || '';
    document.getElementById('smtpFromName').value = form.smtp_from_name || '';

    formBuilderState.fields = Array.isArray(data.fields) ? data.fields.map(mapFieldFromApi) : [];
    if (!formBuilderState.fields.length) {
        setDefaultFields();
    }
    renderFields();
}

async function saveForm() {
    const saveBtn = document.getElementById('saveFormBtn');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Speichern...';
    saveBtn.disabled = true;

    try {
        const payload = collectPayload();
        const response = await fetch('../api/forms.php?action=save', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: payload.toString(),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Formular konnte nicht gespeichert werden.');
        }

        if (!FORM_ID && data.id) {
            window.location.href = `formular-editor.php?id=${data.id}`;
            return;
        }
        alert('Formular gespeichert.');
    } catch (error) {
        alert(error.message || 'Fehler beim Speichern.');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

async function deleteForm() {
    if (!FORM_ID) return;
    const ok = window.wkcConfirm ? await window.wkcConfirm('Formular wirklich löschen?', { title: 'Formular löschen' }) : confirm('Formular wirklich löschen?');
    if (!ok) return;

    const response = await fetch(`../api/forms.php?id=${FORM_ID}`, {
        method: 'DELETE',
        credentials: 'include',
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
        alert(data.error || 'Löschen fehlgeschlagen.');
        return;
    }
    window.location.href = 'formulare.php';
}

function handleFieldInput(eventTarget) {
    const index = Number(eventTarget.getAttribute('data-field-input'));
    const key = eventTarget.getAttribute('data-key');
    if (!Number.isInteger(index) || index < 0 || !key || !formBuilderState.fields[index]) {
        return;
    }

    const field = formBuilderState.fields[index];
    if (key === 'required') {
        field.required = !!eventTarget.checked;
    } else if (key === 'layoutWidth') {
        field.layoutWidth = eventTarget.value === 'half' ? 'half' : 'full';
    } else if (key === 'maxSizeMb') {
        field.maxSizeMb = Math.max(1, Math.min(20, Number(eventTarget.value || 10)));
    } else {
        field[key] = eventTarget.value;
    }

    if (key === 'label' && (!field.name || field.name.trim() === '')) {
        field.name = fieldNameFromLabel(field.label, index);
    }
    if (key === 'type') {
        if (field.type === 'heading' || field.type === 'divider') {
            field.required = false;
            field.name = '';
            field.layoutWidth = 'full';
        } else if (!field.name) {
            field.name = fieldNameFromLabel(field.label, index);
        }
        renderFields();
        return;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const titleInput = document.getElementById('formTitle');
    const slugInput = document.getElementById('formSlug');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const saveFormBtn = document.getElementById('saveFormBtn');
    const deleteFormBtn = document.getElementById('deleteFormBtn');

    titleInput.addEventListener('input', () => {
        if (formBuilderState.slugTouched) return;
        slugInput.value = slugifyForm(titleInput.value);
        updateEmbedHints();
    });

    slugInput.addEventListener('input', () => {
        formBuilderState.slugTouched = true;
        slugInput.value = slugifyForm(slugInput.value);
        updateEmbedHints();
    });

    addFieldBtn.addEventListener('click', () => {
        const field = createField('text');
        field.label = 'Neues Feld';
        field.name = fieldNameFromLabel(field.label, formBuilderState.fields.length);
        formBuilderState.fields.push(field);
        renderFields();
    });

    document.addEventListener('input', (event) => {
        const target = event.target.closest('[data-field-input]');
        if (!target) return;
        handleFieldInput(target);
    });

    document.addEventListener('change', (event) => {
        const target = event.target.closest('[data-field-input]');
        if (!target) return;
        handleFieldInput(target);
    });

    document.addEventListener('click', (event) => {
        const deleteBtn = event.target.closest('[data-field-delete]');
        if (deleteBtn) {
            const index = Number(deleteBtn.getAttribute('data-field-delete'));
            if (Number.isInteger(index) && index >= 0 && index < formBuilderState.fields.length) {
                formBuilderState.fields.splice(index, 1);
                renderFields();
            }
            return;
        }

        const upBtn = event.target.closest('[data-field-move-up]');
        if (upBtn) {
            const index = Number(upBtn.getAttribute('data-field-move-up'));
            if (index > 0 && index < formBuilderState.fields.length) {
                const [field] = formBuilderState.fields.splice(index, 1);
                formBuilderState.fields.splice(index - 1, 0, field);
                renderFields();
            }
            return;
        }

        const downBtn = event.target.closest('[data-field-move-down]');
        if (downBtn) {
            const index = Number(downBtn.getAttribute('data-field-move-down'));
            if (index >= 0 && index < formBuilderState.fields.length - 1) {
                const [field] = formBuilderState.fields.splice(index, 1);
                formBuilderState.fields.splice(index + 1, 0, field);
                renderFields();
            }
        }
    });

    saveFormBtn.addEventListener('click', saveForm);
    deleteFormBtn.addEventListener('click', deleteForm);

    try {
        await loadForm();
        updateEmbedHints();
    } catch (error) {
        console.error(error);
        alert(error.message || 'Formular konnte nicht geladen werden.');
    }
});
