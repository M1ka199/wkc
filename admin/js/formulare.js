function escapeHtmlFormList(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function formatDateTimeFormList(value) {
    if (!value) return '–';
    return new Date(value).toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function loadForms() {
    const tbody = document.getElementById('formsTableBody');
    if (!tbody) return;

    try {
        const response = await fetch('../api/forms.php?action=list', { credentials: 'include' });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Formulare konnten nicht geladen werden.');
        }

        const forms = Array.isArray(data.forms) ? data.forms : [];
        if (!forms.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Formulare angelegt.</td></tr>';
            return;
        }

        tbody.innerHTML = forms.map((form) => {
            const active = Number(form.is_active) === 1;
            const statusBadge = active
                ? '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Aktiv</span>'
                : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Inaktiv</span>';
            const submissions = Number(form.submissions_count || 0);
            const targetPath = form.target_path ? ` <span class="text-xs text-gray-400">(/${escapeHtmlFormList(form.target_path)})</span>` : '';
            const publicUrl = `/formular/${encodeURIComponent(form.slug || '')}`;
            return `
                <tr class="hover:bg-bg-light/30 transition-colors">
                    <td class="px-6 py-4">
                        <p class="text-sm font-bold text-gray-900">${escapeHtmlFormList(form.title)}</p>
                        <p class="text-xs text-gray-500 mt-1">ID ${form.id}${targetPath}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 font-mono">${escapeHtmlFormList(form.slug)}</td>
                    <td class="px-6 py-4 text-sm">
                        <a href="${publicUrl}" target="_blank" rel="noopener noreferrer" class="text-primary font-semibold hover:underline">${escapeHtmlFormList(publicUrl)}</a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 font-bold">${submissions}</td>
                    <td class="px-6 py-4">${statusBadge}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${formatDateTimeFormList(form.updated_at)}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="formular-editor.php?id=${form.id}" class="text-primary text-sm font-bold hover:underline">Bearbeiten</a>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('loadForms:', error);
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Formulare.</td></tr>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadForms();
});
