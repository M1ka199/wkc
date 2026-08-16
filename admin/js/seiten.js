const pagesState = { pages: [] };

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function fmtDate(str) {
    if (!str) return '–';
    return new Date(str).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formBadge(count) {
    const value = Number(count || 0);
    if (value <= 0) {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Keine</span>';
    }
    if (value === 1) {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">1 Formular</span>';
    }
    return `<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">${value} Formulare</span>`;
}

async function duplicatePage(id, title) {
    if (!confirm(`Seite "${title}" wirklich duplizieren?`)) return;

    const formData = new FormData();
    formData.append('id', String(id));

    try {
        const res = await fetch('../api/pages.php?action=duplicate', {
            method: 'POST',
            credentials: 'include',
            body: formData,
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'Duplizieren fehlgeschlagen');
        }
        await loadPages();
        alert('Seite wurde als Entwurf dupliziert.');
    } catch (err) {
        console.error('duplicatePage:', err);
        alert(err.message || 'Fehler beim Duplizieren.');
    }
}

async function loadPages() {
    const tbody = document.getElementById('pagesTableBody');
    try {
        const res = await fetch('../api/pages.php?action=list', { credentials: 'include' });
        const data = await res.json();
        pagesState.pages = data.pages || [];

        if (!pagesState.pages.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Seiten vorhanden.</td></tr>';
            return;
        }

        tbody.innerHTML = pagesState.pages.map(p => {
            const isHomepage = (p.path || '') === '';
            const encodedTitle = encodeURIComponent(p.title || 'Seite');
            const status = p.status === 'published'
                ? '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Veröffentlicht</span>'
                : p.status === 'archived'
                    ? '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Archiviert</span>'
                    : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Entwurf</span>';
            return `
                <tr class="hover:bg-bg-light/40">
                    <td class="px-6 py-4 font-bold text-gray-900">${escapeHtml(p.title)}${isHomepage ? '<span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700">Startseite</span>' : ''}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">${isHomepage ? '/' : '/' + escapeHtml(p.path)}</td>
                    <td class="px-6 py-4">${status}</td>
                    <td class="px-6 py-4">${formBadge(p.forms_count)}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${fmtDate(p.updated_at)}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex items-center gap-3">
                            <a class="text-primary text-sm font-bold hover:underline" href="seiten-editor.php?id=${p.id}">Bearbeiten</a>
                            <button type="button" class="text-sm font-bold text-gray-600 hover:text-primary" data-action="duplicate" data-id="${p.id}" data-title="${encodedTitle}">Duplizieren</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('[data-action="duplicate"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-id') || 0);
                const title = decodeURIComponent(btn.getAttribute('data-title') || 'Seite');
                if (id > 0) duplicatePage(id, title);
            });
        });
    } catch (err) {
        console.error('loadPages:', err);
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden.</td></tr>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadPages();

    document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('../api/auth.php?action=logout');
        window.location.href = 'index.php';
    });
});
