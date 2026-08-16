/**
 * WKC – Dashboard JavaScript
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
// Load Dashboard Data
// ============================
document.addEventListener('DOMContentLoaded', () => {
    if (typeof CAN_EDIT_CONTENT !== 'undefined' && CAN_EDIT_CONTENT) {
        loadArticles();
    }
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
        loadMembers();
        loadMessages();
        loadAdminEvents();
        loadAdminDocuments();
        loadMailStatus(false);
    }
    // Member dashboard: load events + documents
    if (typeof CAN_EDIT_CONTENT !== 'undefined' && !CAN_EDIT_CONTENT) {
        loadMemberEvents();
        loadMemberDocuments();
    }

    // Setup drop zone for document upload
    setupDropZone();

    const probeBtn = document.getElementById('mailStatusProbeBtn');
    if (probeBtn) {
        probeBtn.addEventListener('click', () => {
            loadMailStatus(true);
        });
    }
});

// ============================
// Helper: escapeHtml
// ============================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ============================
// Helper: formatFileSize
// ============================
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// ============================
// Helper: formatDate
// ============================
function formatDate(dateStr) {
    if (!dateStr) return '–';
    return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// ============================
// Helper: formatTime
// ============================
function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    return parts[0] + ':' + parts[1] + ' Uhr';
}

// ============================
// Helper: getFileIcon
// ============================
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
// Unread Messages Count
// ============================
async function loadUnreadMessagesCount() {
    try {
        const res = await fetch('../api/contact.php?action=list', { credentials: 'include' });
        const data = await res.json();
        const messages = data.messages || [];
        const unread = messages.filter(m => m.is_read == 0 || m.is_read === false).length;
        const el = document.getElementById('statUnreadMessages');
        if (el) el.textContent = unread;
    } catch (err) {
        console.error('loadUnreadMessagesCount:', err);
    }
}

function formatDateTimeCompact(iso) {
    if (!iso) return '–';
    const parsed = new Date(iso);
    if (Number.isNaN(parsed.getTime())) return iso;
    return parsed.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function loadMailStatus(runProbe) {
    const badge = document.getElementById('mailStatusBadge');
    const hint = document.getElementById('mailStatusHint');
    const probeBtn = document.getElementById('mailStatusProbeBtn');
    if (!badge || !hint) return;

    if (probeBtn) probeBtn.disabled = true;
    badge.className = 'text-sm font-bold text-gray-900';
    badge.textContent = runProbe ? 'Teste Verbindung...' : 'Prüfung läuft...';
    hint.className = 'mt-3 text-xs text-gray-500 leading-relaxed';
    hint.textContent = runProbe ? 'SMTP-Verbindung wird geprüft...' : 'Lade Mail-Status...';

    try {
        const probeParam = runProbe ? '&probe=1' : '';
        const res = await fetch(`../api/settings.php?scope=admin&action=mail_status${probeParam}`, { credentials: 'include' });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'Mail-Status konnte nicht geladen werden.');
        }

        const mail = data.mail || {};
        const configError = mail.config_error || '';
        const delivery = mail.delivery || {};
        const lastError = delivery.last_error && typeof delivery.last_error === 'object' ? delivery.last_error : null;
        const probe = mail.probe && typeof mail.probe === 'object' ? mail.probe : null;

        if (configError) {
            badge.className = 'text-sm font-bold text-red-700';
            badge.textContent = 'Konfiguration fehlerhaft';
            hint.className = 'mt-3 text-xs text-red-700 leading-relaxed';
            hint.textContent = configError;
            return;
        }

        if (probe) {
            if (probe.ok) {
                badge.className = 'text-sm font-bold text-green-700';
                badge.textContent = 'SMTP-Verbindung ok';
            } else {
                badge.className = 'text-sm font-bold text-red-700';
                badge.textContent = 'SMTP-Verbindung fehlgeschlagen';
            }
        } else if (delivery.last_success === true) {
            badge.className = 'text-sm font-bold text-green-700';
            badge.textContent = 'Letzter Versand erfolgreich';
        } else if (lastError) {
            badge.className = 'text-sm font-bold text-amber-700';
            badge.textContent = 'Letzter Versand fehlgeschlagen';
        } else {
            badge.className = 'text-sm font-bold text-gray-700';
            badge.textContent = 'Noch kein Versand protokolliert';
        }

        const parts = [];
        if (probe) {
            parts.push(probe.ok ? 'SMTP-Test erfolgreich.' : `SMTP-Testfehler: ${probe.error || 'Unbekannter Fehler.'}`);
        }
        if (lastError && lastError.message) {
            parts.push(`Letzter Fehler (${formatDateTimeCompact(lastError.at || '')}): ${lastError.message}`);
        } else if (delivery.last_success_at) {
            parts.push(`Letzter erfolgreicher Versand: ${formatDateTimeCompact(delivery.last_success_at)}`);
        } else if (delivery.last_attempt_at) {
            parts.push(`Letzter Versandversuch: ${formatDateTimeCompact(delivery.last_attempt_at)}`);
        }
        if (delivery.last_context) {
            parts.push(`Kontext: ${delivery.last_context}`);
        }

        hint.className = 'mt-3 text-xs text-gray-600 leading-relaxed';
        hint.textContent = parts.length ? parts.join(' ') : 'Keine weiteren Diagnosedaten vorhanden.';
    } catch (err) {
        badge.className = 'text-sm font-bold text-red-700';
        badge.textContent = 'Status nicht verfügbar';
        hint.className = 'mt-3 text-xs text-red-700 leading-relaxed';
        hint.textContent = err.message || 'Mail-Status konnte nicht geladen werden.';
    } finally {
        if (probeBtn) probeBtn.disabled = false;
    }
}

// ============================
// Articles (admin/editor dashboard)
// ============================
async function loadArticles() {
    try {
        const res = await fetch('../api/articles.php?action=admin_list&limit=5', { credentials: 'include' });
        const data = await res.json();
        const tbody = document.getElementById('articlesTableBody');
        const stat = document.getElementById('statArticles');

        stat.textContent = data.counts?.all || data.total || 0;

        // Load unread messages count
        loadUnreadMessagesCount();

        if (!data.articles || data.articles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Beiträge vorhanden.</td></tr>';
            return;
        }

        tbody.innerHTML = data.articles.map(a => {
            const date = new Date(a.published_at || a.created_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            let statusBadge;
            if (a.status === 'draft') {
                statusBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-50 text-orange-600">Entwurf</span>';
            } else {
                statusBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary">Veröffentlicht</span>';
            }

            const imgSrc = a.featured_image
                ? `<img src="../${escapeHtml(a.featured_image)}" alt="" class="w-12 h-12 object-cover rounded-lg">`
                : `<div class="w-12 h-12 bg-bg-light rounded-lg flex items-center justify-center"><span class="material-symbols-outlined text-gray-300">image</span></div>`;

            return `
                <tr class="hover:bg-bg-light/30 transition-colors group cursor-pointer" onclick="window.location.href='editor.php?id=${a.id}'">
                    <td class="px-4 py-4">${imgSrc}</td>
                    <td class="px-2 py-4">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-primary transition-colors line-clamp-2">${escapeHtml(a.title)}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${date}</td>
                    <td class="px-6 py-4 text-right">${statusBadge}</td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('loadArticles:', err);
    }
}

// ============================
// Members (admin dashboard)
// ============================
async function loadMembers() {
    try {
        const res = await fetch('../api/members.php?action=admin_list', { credentials: 'include' });
        const data = await res.json();
        const tbody = document.getElementById('membersTableBody');
        const stat = document.getElementById('statMembers');

        if (!res.ok) {
            console.error('loadMembers: API error', data);
            return;
        }

        stat.textContent = data.members?.length || 0;

        if (!data.members || data.members.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Mitglieder vorhanden.</td></tr>';
            return;
        }

        tbody.innerHTML = data.members.map(m => {
            const img = m.profile_image
                ? `<img src="../${escapeHtml(m.profile_image)}" alt="" class="w-8 h-8 rounded-full object-cover">`
                : `<div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">${escapeHtml(m.display_name?.charAt(0) || '?')}</div>`;

            return `
                <tr class="hover:bg-bg-light/30 transition-colors cursor-pointer group" onclick="window.location.href='mitglieder.php?edit=${m.id}'">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            ${img}
                            <p class="text-sm font-bold text-gray-900 group-hover:text-primary transition-colors">${escapeHtml(m.display_name)}</p>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${escapeHtml(m.position || '–')}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="mitglieder.php?edit=${m.id}" class="text-gray-400 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('loadMembers:', err);
    }
}

// ============================
// Messages (admin dashboard)
// ============================
async function loadMessages() {
    const tbody = document.getElementById('messagesTableBody');
    try {
        const res = await fetch('../api/contact.php?action=list', { credentials: 'include' });
        if (!res.ok) throw new Error('API error');
        const data = await res.json();

        if (!data.messages || data.messages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Keine Kontaktnachrichten vorhanden.</td></tr>';
            return;
        }

        tbody.innerHTML = data.messages.slice(0, 5).map(m => {
            const date = formatDate(m.created_at);
            const typeBadge = m.is_anonymous == 1
                ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-50 text-orange-600">Anonym</span>'
                : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Kontakt</span>';
            const unreadClass = (m.is_read == 0 || m.is_read === false) ? 'bg-primary/5 font-semibold' : '';

            return `
                <tr class="hover:bg-bg-light/30 transition-colors cursor-pointer group ${unreadClass}" onclick="window.location.href='nachrichten.php?id=${m.id}'">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 group-hover:text-primary transition-colors">${escapeHtml(m.name)}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${escapeHtml(m.subject || '–')}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${date}</td>
                    <td class="px-6 py-4 text-right">${typeBadge}</td>
                </tr>`;
        }).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Kontaktnachrichten konnten nicht geladen werden.</td></tr>';
    }
}

// ============================
// EVENTS – Admin Management
// ============================
async function loadAdminEvents() {
    const tbody = document.getElementById('adminEventsTableBody');
    if (!tbody) return;
    try {
        const res = await fetch('../api/events.php?action=list&past=1', { credentials: 'include' });
        const data = await res.json();

        if (!data.events || data.events.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Termine angelegt.</td></tr>';
            return;
        }

        tbody.innerHTML = data.events.map(e => {
            const date = formatDate(e.event_date);
            const time = e.event_time ? formatTime(e.event_time) : '';
            const dateTime = time ? `${date}, ${time}` : date;
            const isPast = new Date(e.event_date) < new Date(new Date().toDateString());
            const pastClass = isPast ? 'opacity-50' : '';

            return `
                <tr class="hover:bg-bg-light/30 transition-colors ${pastClass}">
                    <td class="px-6 py-4">
                        <p class="text-sm font-bold text-gray-900">${escapeHtml(e.title)}</p>
                        ${e.description ? `<p class="text-xs text-gray-400 mt-0.5 line-clamp-1">${escapeHtml(e.description)}</p>` : ''}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                        ${dateTime}
                        ${isPast ? '<span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">Vergangen</span>' : ''}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${escapeHtml(e.location || '–')}</td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <button onclick="editEvent(${e.id})" class="text-gray-400 hover:text-primary transition-colors p-1" title="Bearbeiten">
                            <span class="material-symbols-outlined text-xl">edit</span>
                        </button>
                        <button onclick="deleteEvent(${e.id}, '${escapeHtml(e.title).replace(/'/g, "\\'")}')" class="text-gray-400 hover:text-red-500 transition-colors p-1 ml-1" title="Löschen">
                            <span class="material-symbols-outlined text-xl">delete</span>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('loadAdminEvents:', err);
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Termine.</td></tr>';
    }
}

// ============================
// EVENTS – Modal Handling
// ============================
function openEventModal(event = null) {
    const modal = document.getElementById('eventModal');
    const title = document.getElementById('eventModalTitle');

    document.getElementById('eventId').value = event ? event.id : '';
    document.getElementById('eventTitle').value = event ? event.title : '';
    document.getElementById('eventDate').value = event ? event.event_date : '';
    document.getElementById('eventTime').value = event ? (event.event_time || '') : '';
    document.getElementById('eventLocation').value = event ? (event.location || '') : '';
    document.getElementById('eventDescription').value = event ? (event.description || '') : '';

    title.textContent = event ? 'Termin bearbeiten' : 'Neuer Termin';
    modal.classList.remove('hidden');
}

function closeEventModal() {
    document.getElementById('eventModal').classList.add('hidden');
}

async function editEvent(id) {
    try {
        const res = await fetch(`../api/events.php?action=detail&id=${id}`, { credentials: 'include' });
        const data = await res.json();
        if (data.event) {
            openEventModal(data.event);
        }
    } catch (err) {
        console.error('editEvent:', err);
    }
}

// Event form submit
document.getElementById('eventForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('eventId').value;
    const isEdit = !!id;
    const action = isEdit ? 'update' : 'create';

    const formData = new FormData();
    if (isEdit) formData.append('id', id);
    formData.append('title', document.getElementById('eventTitle').value);
    formData.append('event_date', document.getElementById('eventDate').value);
    formData.append('event_time', document.getElementById('eventTime').value);
    formData.append('location', document.getElementById('eventLocation').value);
    formData.append('description', document.getElementById('eventDescription').value);

    try {
        const res = await fetch(`../api/events.php?action=${action}`, {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            closeEventModal();
            loadAdminEvents();
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveEvent:', err);
        alert('Netzwerkfehler beim Speichern.');
    }
});

async function deleteEvent(id, title) {
    if (!await wkcConfirm(`Termin \u201E${title}\u201C wirklich löschen?`)) return;

    try {
        const res = await fetch(`../api/events.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            loadAdminEvents();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteEvent:', err);
    }
}

// ============================
// DOCUMENTS – Admin Management
// ============================
async function loadAdminDocuments() {
    const tbody = document.getElementById('adminDocsTableBody');
    if (!tbody) return;
    try {
        const res = await fetch('../api/documents.php?action=list', { credentials: 'include' });
        const data = await res.json();

        if (!data.documents || data.documents.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Dokumente hochgeladen.</td></tr>';
            return;
        }

        tbody.innerHTML = data.documents.map(d => {
            const icon = getFileIcon(d.file_name);
            const size = formatFileSize(d.file_size);
            const date = formatDate(d.created_at);

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
                    <td class="px-6 py-4 text-sm text-gray-500">${escapeHtml(d.file_name)}</td>
                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">${size}</td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <a href="../api/documents.php?action=download&id=${d.id}" class="text-gray-400 hover:text-primary transition-colors p-1" title="Herunterladen">
                            <span class="material-symbols-outlined text-xl">download</span>
                        </a>
                        <button onclick="deleteDocument(${d.id}, '${escapeHtml(d.title).replace(/'/g, "\\'")}')" class="text-gray-400 hover:text-red-500 transition-colors p-1 ml-1" title="Löschen">
                            <span class="material-symbols-outlined text-xl">delete</span>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('loadAdminDocuments:', err);
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Dokumente.</td></tr>';
    }
}

// ============================
// DOCUMENTS – Modal Handling
// ============================
function openDocumentModal() {
    const modal = document.getElementById('documentModal');
    document.getElementById('documentForm').reset();
    document.getElementById('selectedFileName').classList.add('hidden');
    modal.classList.remove('hidden');
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
document.getElementById('documentForm')?.addEventListener('submit', async (e) => {
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

        if (data.success) {
            closeDocumentModal();
            loadAdminDocuments();
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

async function deleteDocument(id, title) {
    if (!await wkcConfirm(`Dokument \u201E${title}\u201C wirklich löschen?`)) return;

    try {
        const res = await fetch(`../api/documents.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            loadAdminDocuments();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteDocument:', err);
    }
}

// ============================
// MEMBER DASHBOARD – Events
// ============================
async function loadMemberEvents() {
    const container = document.getElementById('eventsContainer');
    if (!container) return;

    try {
        const res = await fetch('../api/events.php?action=list', { credentials: 'include' });
        const data = await res.json();

        if (!data.events || data.events.length === 0) {
            container.innerHTML = `
                <div class="flex items-start gap-4 p-4 bg-bg-light rounded-xl">
                    <div class="bg-primary/10 p-3 rounded-lg text-primary flex-shrink-0 text-center min-w-[56px]">
                        <span class="text-xs font-bold uppercase block">Kein</span>
                        <span class="text-lg font-black block leading-none">–</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Aktuell sind keine Termine geplant.</p>
                        <p class="text-xs text-gray-400 mt-1">Neue Termine werden hier automatisch angezeigt.</p>
                    </div>
                </div>`;
            return;
        }

        const months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

        container.innerHTML = data.events.map(e => {
            const d = new Date(e.event_date);
            const day = d.getDate();
            const month = months[d.getMonth()];
            const time = e.event_time ? formatTime(e.event_time) : '';
            const fullDate = formatDate(e.event_date);
            const locationHtml = e.location ? `<span class="inline-flex items-center gap-1 text-xs text-gray-400"><span class="material-symbols-outlined text-xs">location_on</span>${escapeHtml(e.location)}</span>` : '';

            return `
                <div class="flex items-start gap-4 p-4 bg-bg-light rounded-xl hover:bg-primary/5 transition-colors">
                    <div class="bg-primary/10 p-3 rounded-lg text-primary flex-shrink-0 text-center min-w-[56px]">
                        <span class="text-xs font-bold uppercase block">${month}</span>
                        <span class="text-lg font-black block leading-none">${day}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900">${escapeHtml(e.title)}</p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                            <span class="text-xs text-gray-500">${fullDate}${time ? ', ' + time : ''}</span>
                            ${locationHtml}
                        </div>
                        ${e.description ? `<p class="text-xs text-gray-500 mt-2">${escapeHtml(e.description)}</p>` : ''}
                    </div>
                </div>`;
        }).join('');
    } catch (err) {
        console.error('loadMemberEvents:', err);
    }
}

// ============================
// MEMBER DASHBOARD – Documents
// ============================
async function loadMemberDocuments() {
    const container = document.getElementById('downloadsContainer');
    if (!container) return;

    try {
        const res = await fetch('../api/documents.php?action=list', { credentials: 'include' });
        const data = await res.json();

        if (!data.documents || data.documents.length === 0) {
            container.innerHTML = `
                <div class="flex items-center gap-4 p-4 bg-bg-light rounded-xl">
                    <div class="bg-gray-200/50 p-2.5 rounded-lg text-gray-400 flex-shrink-0">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500">Noch keine Dokumente vorhanden.</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dokumente werden vom Vorstand hier bereitgestellt.</p>
                    </div>
                </div>`;
            return;
        }

        container.innerHTML = data.documents.map(d => {
            const icon = getFileIcon(d.file_name);
            const size = formatFileSize(d.file_size);
            const date = formatDate(d.created_at);

            return `
                <a href="../api/documents.php?action=download&id=${d.id}" class="flex items-center gap-4 p-4 bg-bg-light rounded-xl hover:bg-primary/5 transition-colors group">
                    <div class="bg-primary/10 p-2.5 rounded-lg text-primary flex-shrink-0">
                        <span class="material-symbols-outlined">${icon}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-primary transition-colors">${escapeHtml(d.title)}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-gray-400">${escapeHtml(d.file_name)}</span>
                            <span class="text-xs text-gray-300">·</span>
                            <span class="text-xs text-gray-400">${size}</span>
                            <span class="text-xs text-gray-300">·</span>
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        ${d.description ? `<p class="text-xs text-gray-500 mt-1">${escapeHtml(d.description)}</p>` : ''}
                    </div>
                    <span class="material-symbols-outlined text-gray-300 group-hover:text-primary transition-colors flex-shrink-0">download</span>
                </a>`;
        }).join('');
    } catch (err) {
        console.error('loadMemberDocuments:', err);
    }
}
