/**
 * WKC – Dokumente JavaScript
 */

// ============================
// Sidebar Toggle (mobile)
// ============================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
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

// ============================
// Logout
// ============================
document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch('../api/auth.php?action=logout');
    window.location.href = 'index.php';
});

// ============================
// Helpers
// ============================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '–';
    return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function getFileIcon(filename) {
    const ext = (filename || '').split('.').pop().toLowerCase();
    const icons = {
        pdf: 'picture_as_pdf',
        doc: 'description', docx: 'description',
        xls: 'table_chart', xlsx: 'table_chart',
        ppt: 'slideshow', pptx: 'slideshow',
        jpg: 'image', jpeg: 'image', png: 'image', webp: 'image',
        zip: 'folder_zip',
        txt: 'text_snippet', csv: 'table_chart',
    };
    return icons[ext] || 'draft';
}

// ============================
// State
// ============================
let allTags = [];
let allDocuments = [];
let activeFilterTag = '';
let uploadSelectedTags = [];

// ============================
// Load Documents
// ============================
async function loadDocuments() {
    const tbody = document.getElementById('docsTableBody');
    try {
        const res = await fetch('../api/documents.php?action=list', { credentials: 'include' });
        const data = await res.json();

        allDocuments = data.documents || [];

        if (allDocuments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Dokumente hochgeladen.</td></tr>';
            return;
        }

        renderDocuments();
    } catch (err) {
        console.error('loadDocuments:', err);
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Dokumente.</td></tr>';
    }
}

function renderDocuments() {
    const tbody = document.getElementById('docsTableBody');
    let filtered = allDocuments;

    if (activeFilterTag) {
        filtered = allDocuments.filter(d =>
            d.tags && d.tags.some(t => t.id == activeFilterTag)
        );
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Keine Dokumente mit diesem Tag gefunden.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(d => {
        const icon = getFileIcon(d.file_name);
        const size = formatFileSize(d.file_size);
        const date = formatDate(d.created_at);

        const tagsHtml = (d.tags || []).map(t =>
            `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold text-white" style="background-color:${escapeHtml(t.color)}">${escapeHtml(t.name)}</span>`
        ).join(' ');

        return `
            <tr class="hover:bg-bg-light/30 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-gray-400">${icon}</span>
                        <div>
                            <p class="text-sm font-bold text-gray-900">${escapeHtml(d.title)}</p>
                            ${d.description ? `<p class="text-xs text-gray-400 mt-0.5 line-clamp-1">${escapeHtml(d.description)}</p>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1">
                        ${tagsHtml || '<span class="text-xs text-gray-300">–</span>'}
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 hidden sm:table-cell">${escapeHtml(d.file_name)}</td>
                <td class="px-6 py-4 text-sm text-gray-500 hidden md:table-cell whitespace-nowrap">${size}</td>
                <td class="px-6 py-4 text-sm text-gray-500 hidden md:table-cell whitespace-nowrap">${date}</td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <button onclick="openTagAssign(${d.id})" class="text-gray-400 hover:text-primary transition-colors p-1" title="Tags zuweisen">
                        <span class="material-symbols-outlined text-xl">label</span>
                    </button>
                    <a href="../api/documents.php?action=download&id=${d.id}" class="text-gray-400 hover:text-primary transition-colors p-1" title="Herunterladen">
                        <span class="material-symbols-outlined text-xl">download</span>
                    </a>
                    <button onclick="deleteDocument(${d.id}, '${escapeHtml(d.title).replace(/'/g, "\\'")}')" class="text-gray-400 hover:text-red-500 transition-colors p-1 ml-1" title="Löschen">
                        <span class="material-symbols-outlined text-xl">delete</span>
                    </button>
                </td>
            </tr>`;
    }).join('');
}

// ============================
// Load Tags
// ============================
async function loadTags() {
    try {
        const res = await fetch('../api/documents.php?action=tags', { credentials: 'include' });
        const data = await res.json();
        allTags = data.tags || [];
        renderTagFilters();
        renderUploadTagSelection();
    } catch (err) {
        console.error('loadTags:', err);
    }
}

function renderTagFilters() {
    const container = document.getElementById('tagFilters');
    if (allTags.length === 0) {
        container.classList.add('hidden');
        return;
    }
    container.classList.remove('hidden');

    const allBtn = `<button class="tag-filter-btn ${activeFilterTag === '' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-bg-light'} px-3 py-1.5 rounded-full text-xs font-bold transition-all" data-tag="" onclick="filterByTag('')">Alle</button>`;

    const tagBtns = allTags.map(t => {
        const isActive = activeFilterTag == t.id;
        return `<button class="tag-filter-btn px-3 py-1.5 rounded-full text-xs font-bold transition-all ${isActive ? 'text-white' : 'text-gray-600 border border-gray-200 hover:bg-bg-light'}" style="${isActive ? 'background-color:' + escapeHtml(t.color) : ''}" data-tag="${t.id}" onclick="filterByTag(${t.id})">
            <span class="inline-block w-2 h-2 rounded-full mr-1" style="background-color:${escapeHtml(t.color)}"></span>${escapeHtml(t.name)}
        </button>`;
    }).join('');

    container.innerHTML = allBtn + tagBtns;
}

function filterByTag(tagId) {
    activeFilterTag = tagId === '' ? '' : tagId;
    renderTagFilters();
    renderDocuments();
}

function renderUploadTagSelection() {
    const container = document.getElementById('docTagSelection');
    if (allTags.length === 0) {
        container.innerHTML = '<span class="text-xs text-gray-400">Keine Tags vorhanden. Tags können unter "Tags verwalten" erstellt werden.</span>';
        return;
    }

    container.innerHTML = allTags.map(t => {
        const isSelected = uploadSelectedTags.includes(t.id);
        return `<button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold transition-all cursor-pointer ${isSelected ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}" style="${isSelected ? 'background-color:' + escapeHtml(t.color) : ''}" onclick="toggleUploadTag(${t.id})">
            ${escapeHtml(t.name)}
        </button>`;
    }).join('');
}

function toggleUploadTag(tagId) {
    const idx = uploadSelectedTags.indexOf(tagId);
    if (idx > -1) {
        uploadSelectedTags.splice(idx, 1);
    } else {
        uploadSelectedTags.push(tagId);
    }
    renderUploadTagSelection();
}

// ============================
// Document Upload Modal
// ============================
function openDocumentModal() {
    document.getElementById('documentForm').reset();
    document.getElementById('selectedFileName').classList.add('hidden');
    uploadSelectedTags = [];
    renderUploadTagSelection();
    document.getElementById('documentModal').classList.remove('hidden');
}

function closeDocumentModal() {
    document.getElementById('documentModal').classList.add('hidden');
}

function setupDropZone() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('docFile');
    const fileNameDisplay = document.getElementById('selectedFileName');
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary', 'bg-primary/5');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary', 'bg-primary/5');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            showSelectedFile(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            showSelectedFile(fileInput.files[0]);
        }
    });

    function showSelectedFile(file) {
        fileNameDisplay.innerHTML = `<span class="material-symbols-outlined text-primary text-sm align-middle mr-1">attach_file</span> ${escapeHtml(file.name)} <span class="text-gray-400">(${formatFileSize(file.size)})</span>`;
        fileNameDisplay.classList.remove('hidden');
    }
}

// Document form submit
document.getElementById('documentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('docSubmitBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Wird hochgeladen...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('title', document.getElementById('docTitle').value);
    formData.append('description', document.getElementById('docDescription').value);
    formData.append('file', document.getElementById('docFile').files[0]);

    try {
        const res = await fetch('../api/documents.php?action=upload', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success && data.id) {
            // Assign tags if selected
            if (uploadSelectedTags.length > 0) {
                const tagFormData = new FormData();
                tagFormData.append('document_id', data.id);
                tagFormData.append('tag_ids', JSON.stringify(uploadSelectedTags));
                await fetch('../api/documents.php?action=set_tags', {
                    method: 'POST',
                    body: tagFormData,
                    credentials: 'include',
                });
            }
            closeDocumentModal();
            loadDocuments();
        } else {
            alert(data.error || 'Fehler beim Hochladen.');
        }
    } catch (err) {
        console.error('uploadDocument:', err);
        alert('Netzwerkfehler beim Hochladen.');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
});

// ============================
// Delete Document
// ============================
async function deleteDocument(id, title) {
    if (!await wkcConfirm(`Dokument \u201E${title}\u201C wirklich löschen?`)) return;

    try {
        const res = await fetch(`../api/documents.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            loadDocuments();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteDocument:', err);
    }
}

// ============================
// Tag Manager Modal
// ============================
function openTagManager() {
    document.getElementById('tagEditId').value = '';
    document.getElementById('tagName').value = '';
    document.getElementById('tagColor').value = '#7c3aed';
    document.getElementById('tagFormIcon').textContent = 'add';
    renderTagManagerList();
    document.getElementById('tagManagerModal').classList.remove('hidden');
}

function closeTagManager() {
    document.getElementById('tagManagerModal').classList.add('hidden');
}

function renderTagManagerList() {
    const container = document.getElementById('tagsList');

    if (allTags.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-400">Noch keine Tags vorhanden.</p>';
        return;
    }

    container.innerHTML = allTags.map(t => `
        <div class="flex items-center justify-between p-3 bg-bg-light rounded-lg">
            <div class="flex items-center gap-3">
                <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color:${escapeHtml(t.color)}"></span>
                <span class="text-sm font-bold text-gray-900">${escapeHtml(t.name)}</span>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="editTag(${t.id}, '${escapeHtml(t.name).replace(/'/g, "\\'")}', '${escapeHtml(t.color)}')" class="text-gray-400 hover:text-primary transition-colors p-1" title="Bearbeiten">
                    <span class="material-symbols-outlined text-lg">edit</span>
                </button>
                <button onclick="deleteTag(${t.id}, '${escapeHtml(t.name).replace(/'/g, "\\'")}' )" class="text-gray-400 hover:text-red-500 transition-colors p-1" title="Löschen">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            </div>
        </div>
    `).join('');
}

function editTag(id, name, color) {
    document.getElementById('tagEditId').value = id;
    document.getElementById('tagName').value = name;
    document.getElementById('tagColor').value = color;
    document.getElementById('tagFormIcon').textContent = 'check';
}

// Save tag (create or update)
document.getElementById('tagForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('tagEditId').value;
    const name = document.getElementById('tagName').value.trim();
    const color = document.getElementById('tagColor').value;

    if (!name) return;

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('name', name);
    formData.append('color', color);

    try {
        const res = await fetch('../api/documents.php?action=save_tag', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('tagEditId').value = '';
            document.getElementById('tagName').value = '';
            document.getElementById('tagColor').value = '#7c3aed';
            document.getElementById('tagFormIcon').textContent = 'add';
            await loadTags();
            renderTagManagerList();
            loadDocuments(); // refresh tag displays
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveTag:', err);
    }
});

async function deleteTag(id, name) {
    if (!await wkcConfirm(`Tag \u201E${name}\u201C wirklich löschen?`, { title: 'Tag löschen' })) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const res = await fetch('../api/documents.php?action=delete_tag', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            await loadTags();
            renderTagManagerList();
            loadDocuments();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteTag:', err);
    }
}

// ============================
// Tag Assignment Modal
// ============================
function openTagAssign(docId) {
    document.getElementById('tagAssignDocId').value = docId;
    const doc = allDocuments.find(d => d.id == docId);
    const assignedIds = (doc?.tags || []).map(t => t.id);

    const container = document.getElementById('tagAssignList');
    if (allTags.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-400">Noch keine Tags vorhanden. Erstelle zuerst Tags unter "Tags verwalten".</p>';
    } else {
        container.innerHTML = allTags.map(t => {
            const checked = assignedIds.includes(t.id) ? 'checked' : '';
            return `
                <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-bg-light cursor-pointer transition-colors">
                    <input type="checkbox" class="tag-assign-cb rounded border-gray-300 text-primary focus:ring-primary" value="${t.id}" ${checked}>
                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color:${escapeHtml(t.color)}"></span>
                    <span class="text-sm font-medium text-gray-900">${escapeHtml(t.name)}</span>
                </label>`;
        }).join('');
    }

    document.getElementById('tagAssignModal').classList.remove('hidden');
}

function closeTagAssign() {
    document.getElementById('tagAssignModal').classList.add('hidden');
}

async function saveTagAssignment() {
    const docId = document.getElementById('tagAssignDocId').value;
    const checkboxes = document.querySelectorAll('.tag-assign-cb:checked');
    const tagIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

    const formData = new FormData();
    formData.append('document_id', docId);
    formData.append('tag_ids', JSON.stringify(tagIds));

    try {
        const res = await fetch('../api/documents.php?action=set_tags', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            closeTagAssign();
            loadDocuments();
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveTagAssignment:', err);
    }
}

// ============================
// Init
// ============================
document.addEventListener('DOMContentLoaded', () => {
    loadTags();
    loadDocuments();
    setupDropZone();
});

