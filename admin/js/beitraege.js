/**
 * WKC – Beiträge (Article Listing) JavaScript
 */

let currentPage = 1;
let currentStatus = '';
let currentSearch = '';
let searchTimeout = null;

// ============================
// Sidebar
// ============================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}

// ============================
// Sidebar Collapse (desktop)
// ============================
function toggleCollapse() {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const icon = document.getElementById('collapseIcon');
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-collapsed', collapsed);
    icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
    localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
}
(function restoreCollapse() {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('mainContent');
        const icon = document.getElementById('collapseIcon');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main) main.classList.add('main-collapsed');
        if (icon) icon.textContent = 'chevron_right';
    }
})();

document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch('../api/auth.php?action=logout');
    window.location.href = 'index.php';
});

// ============================
// Filter Tabs
// ============================
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentStatus = tab.dataset.status;
        currentPage = 1;
        loadArticles();
    });
});

// ============================
// Search
// ============================
document.getElementById('searchInput').addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentSearch = e.target.value.trim();
        currentPage = 1;
        loadArticles();
    }, 300);
});

// ============================
// Load Articles
// ============================
async function loadArticles() {
    const params = new URLSearchParams({ action: 'admin_list', limit: 15, page: currentPage });
    if (currentStatus) params.set('status', currentStatus);
    if (currentSearch) params.set('search', currentSearch);

    try {
        const res = await fetch('../api/articles.php?' + params);
        const data = await res.json();
        const tbody = document.getElementById('articlesBody');

        // Update filter counts
        if (data.counts) {
            document.getElementById('countAll').textContent = data.counts.all;
            document.getElementById('countPublished').textContent = data.counts.published;
            document.getElementById('countDraft').textContent = data.counts.draft;
            document.getElementById('countArchived').textContent = data.counts.archived;
        }

        if (!data.articles || data.articles.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">
                ${currentSearch ? 'Keine Ergebnisse für "' + escapeHtml(currentSearch) + '".' : 'Noch keine Beiträge vorhanden.'}
            </td></tr>`;
            document.getElementById('pagination').classList.add('hidden');
            return;
        }

        tbody.innerHTML = data.articles.map(a => {
            const date = new Date(a.published_at || a.created_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            let badge;
            if (a.status === 'draft') {
                badge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-50 text-orange-600">Entwurf</span>';
            } else if (a.status === 'archived') {
                badge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">Archiviert</span>';
            } else {
                badge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary">Veröffentlicht</span>';
            }

            const img = a.featured_image
                ? `<img src="../${escapeHtml(a.featured_image)}" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">`
                : `<div class="w-10 h-10 rounded-lg bg-bg-light flex items-center justify-center flex-shrink-0"><span class="material-symbols-outlined text-gray-300">article</span></div>`;

            return `
                <tr class="hover:bg-bg-light/30 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            ${img}
                            <div class="min-w-0 max-w-[280px] lg:max-w-xs xl:max-w-sm">
                                <a href="editor.php?id=${a.id}" class="text-sm font-bold text-gray-900 group-hover:text-primary transition-colors block truncate" title="${escapeHtml(a.title)}">${escapeHtml(a.title)}</a>
                                <p class="text-xs text-gray-400 truncate" title="/neuigkeiten/${escapeHtml(a.slug)}">/neuigkeiten/${escapeHtml(a.slug)}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 hidden md:table-cell">${escapeHtml(a.author_name || '–')}</td>
                    <td class="px-6 py-4 text-sm text-gray-500 hidden sm:table-cell">${date}</td>
                    <td class="px-6 py-4">${badge}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="editor.php?id=${a.id}" class="p-1.5 text-gray-400 hover:text-primary rounded-lg hover:bg-primary/5 transition-all" title="Bearbeiten">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </a>
                            <button onclick="deleteArticle(${a.id}, '${escapeHtml(a.title).replace(/'/g, "\\'")}')" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition-all" title="Löschen">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        // Pagination
        const pag = document.getElementById('pagination');
        if (data.pages > 1) {
            pag.classList.remove('hidden');
            document.getElementById('pagInfo').textContent = `Seite ${data.page} von ${data.pages} (${data.total} Beiträge)`;
            document.getElementById('prevPage').disabled = data.page <= 1;
            document.getElementById('nextPage').disabled = data.page >= data.pages;
        } else {
            pag.classList.add('hidden');
        }
    } catch (err) {
        console.error('loadArticles:', err);
    }
}

// ============================
// Pagination
// ============================
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadArticles(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadArticles(); });

// ============================
// Delete Article
// ============================
async function deleteArticle(id, title) {
    if (!await wkcConfirm(`\u201E${title}\u201C wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`)) return;
    try {
        const res = await fetch(`../api/articles.php?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success) loadArticles();
    } catch (err) {
        alert('Fehler beim Löschen.');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Init
document.addEventListener('DOMContentLoaded', loadArticles);
