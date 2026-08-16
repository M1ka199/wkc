/**
 * WKC – Nachrichten (Admin)
 * Messages management: tabs, list, detail, read/unread, delete
 */

let allMessages = [];
let currentTab = 'all';
let currentPage = 1;
let currentMessage = null;
const perPage = 20;

// ============================
// Init
// ============================
document.addEventListener('DOMContentLoaded', () => {
    loadMessages();
    loadUnreadCount();

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            currentTab = btn.dataset.tab;
            currentPage = 1;
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('tab-active');
                b.classList.add('tab-inactive');
            });
            btn.classList.remove('tab-inactive');
            btn.classList.add('tab-active');
            renderMessages();
        });
    });
});

// ============================
// Load Messages
// ============================
async function loadMessages() {
    try {
        const res = await fetch('../api/messages.php?action=list&limit=200');
        const data = await res.json();
        if (data.error) {
            showAlert(data.error, 'error');
            return;
        }
        allMessages = data.messages || [];
        // Build unread counts from the counts object
        const unread = {};
        if (data.counts) {
            for (const [type, info] of Object.entries(data.counts)) {
                unread[type] = info.unread || 0;
            }
        }
        updateCounts(unread);
        renderMessages();
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
}

// ============================
// Load Unread Count (for sidebar badge)
// ============================
async function loadUnreadCount() {
    try {
        const res = await fetch('../api/messages.php?action=unread_count');
        const data = await res.json();
        const badge = document.getElementById('sidebarBadge');
        if (badge) {
            const count = data.unread || 0;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    } catch (err) { /* silent */ }
}

// ============================
// Update tab counts
// ============================
function updateCounts(unread) {
    const total = (unread.contact || 0) + (unread.membership || 0) + (unread.message || 0);
    document.getElementById('countAll').textContent = total;
    document.getElementById('countContact').textContent = unread.contact || 0;
    document.getElementById('countMembership').textContent = unread.membership || 0;

    // Highlight counts > 0
    ['countAll', 'countContact', 'countMembership'].forEach(id => {
        const el = document.getElementById(id);
        const count = parseInt(el.textContent);
        if (count > 0) {
            el.className = 'ml-1.5 text-xs bg-primary/10 text-primary px-1.5 py-0.5 rounded-full font-bold';
        } else {
            el.className = 'ml-1.5 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full';
        }
    });
}

// ============================
// Render Messages
// ============================
function renderMessages() {
    const list = document.getElementById('messagesList');
    let filtered = allMessages;

    if (currentTab !== 'all') {
        filtered = allMessages.filter(m => m.type === currentTab);
    }

    if (filtered.length === 0) {
        const emptyIcon = currentTab === 'membership' ? 'person_add' : currentTab === 'contact' ? 'chat' : 'mail';
        const emptyText = currentTab === 'membership' ? 'Keine Beitrittsanfragen' : currentTab === 'contact' ? 'Keine Kontaktanfragen' : 'Keine Nachrichten';
        list.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                <span class="material-symbols-outlined text-5xl mb-3">${emptyIcon}</span>
                <p class="text-sm font-medium">${emptyText}</p>
            </div>`;
        document.getElementById('pagination').classList.add('hidden');
        return;
    }

    // Pagination
    const totalPages = Math.ceil(filtered.length / perPage);
    const start = (currentPage - 1) * perPage;
    const paged = filtered.slice(start, start + perPage);

    const typeIcons = { contact: 'chat', membership: 'person_add', message: 'mail' };
    const typeLabels = { contact: 'Kontakt', membership: 'Beitritt', message: 'Nachricht' };
    const typeColors = { contact: 'bg-blue-50 text-blue-600', membership: 'bg-amber-50 text-amber-600', message: 'bg-gray-100 text-gray-600' };

    list.innerHTML = paged.map(m => {
        const isUnread = !m.is_read;
        const icon = typeIcons[m.type] || 'mail';
        const label = typeLabels[m.type] || 'Nachricht';
        const color = typeColors[m.type] || typeColors.message;
        const date = formatDate(m.created_at);
        const preview = (m.message || '').substring(0, 120).replace(/\n/g, ' ') + ((m.message || '').length > 120 ? '…' : '');

        return `
            <div class="bg-white rounded-xl border border-gray-200 hover:border-gray-300 transition-all cursor-pointer ${isUnread ? 'message-unread' : ''}" onclick="openMessage(${m.id})">
                <div class="p-4 flex items-start gap-4">
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="w-10 h-10 rounded-full ${isUnread ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-400'} flex items-center justify-center">
                            <span class="material-symbols-outlined text-xl">${icon}</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm font-bold ${isUnread ? 'text-gray-900' : 'text-gray-700'} truncate">${esc(m.name || 'Unbekannt')}</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold ${color}">${label}</span>
                                ${m.is_anonymous ? '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-50 text-orange-500">Anonym</span>' : ''}
                            </div>
                            <span class="text-xs text-gray-400 flex-shrink-0">${date}</span>
                        </div>
                        <p class="text-sm ${isUnread ? 'font-semibold text-gray-800' : 'text-gray-600'} truncate">${esc(m.subject || 'Kein Betreff')}</p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">${esc(preview)}</p>
                    </div>
                    ${isUnread ? '<div class="w-2 h-2 rounded-full bg-primary flex-shrink-0 mt-2"></div>' : ''}
                </div>
            </div>`;
    }).join('');

    // Pagination
    const pag = document.getElementById('pagination');
    if (totalPages > 1) {
        pag.classList.remove('hidden');
        document.getElementById('paginationInfo').textContent = `Seite ${currentPage} von ${totalPages} (${filtered.length} Nachrichten)`;
        document.getElementById('btnPrev').disabled = currentPage <= 1;
        document.getElementById('btnNext').disabled = currentPage >= totalPages;
    } else {
        pag.classList.add('hidden');
    }
}

function changePage(delta) {
    currentPage += delta;
    renderMessages();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ============================
// Open Message Detail
// ============================
async function openMessage(id) {
    try {
        const res = await fetch(`../api/messages.php?action=get&id=${id}`);
        const data = await res.json();
        if (data.error || !data.message) {
            showAlert(data.error || 'Fehler.', 'error');
            return;
        }
        currentMessage = data.message;
        const m = currentMessage;

        // Update read state in local data
        const idx = allMessages.findIndex(msg => msg.id === id);
        if (idx !== -1) {
            allMessages[idx].is_read = 1;
            renderMessages();
            loadUnreadCount();
        }

        // Type badge
        const typeLabels = { contact: 'Kontakt', membership: 'Beitritt', message: 'Nachricht' };
        const typeColors = { contact: 'bg-blue-50 text-blue-600', membership: 'bg-amber-50 text-amber-600', message: 'bg-gray-100 text-gray-600' };
        document.getElementById('modalTypeBadge').textContent = typeLabels[m.type] || 'Nachricht';
        document.getElementById('modalTypeBadge').className = `px-2 py-0.5 rounded-full text-xs font-bold ${typeColors[m.type] || typeColors.message}`;

        // Read badge
        updateModalReadBadge(m.is_read);

        document.getElementById('modalSubject').textContent = m.subject || 'Kein Betreff';
        document.getElementById('modalName').textContent = m.is_anonymous ? `${m.name} (anonym)` : (m.name || 'Unbekannt');
        document.getElementById('modalEmail').textContent = m.email || 'Nicht angegeben';
        document.getElementById('modalDate').textContent = formatDate(m.created_at, true);
        document.getElementById('modalMessage').textContent = m.message || '';

        // Reply button
        const replyBtn = document.getElementById('btnReplyEmail');
        if (m.email && !m.is_anonymous) {
            replyBtn.href = `mailto:${encodeURIComponent(m.email)}?subject=Re: ${encodeURIComponent(m.subject || '')}`;
            replyBtn.classList.remove('hidden');
        } else {
            replyBtn.classList.add('hidden');
        }

        document.getElementById('messageModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentMessage = null;
}

function updateModalReadBadge(isRead) {
    const badge = document.getElementById('modalReadBadge');
    if (isRead) {
        badge.textContent = 'Gelesen';
        badge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-green-50 text-green-600';
        document.getElementById('toggleReadIcon').textContent = 'mark_email_unread';
        document.getElementById('toggleReadLabel').textContent = 'Als ungelesen';
    } else {
        badge.textContent = 'Ungelesen';
        badge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary';
        document.getElementById('toggleReadIcon').textContent = 'mark_email_read';
        document.getElementById('toggleReadLabel').textContent = 'Als gelesen';
    }
}

// ============================
// Toggle Read
// ============================
async function toggleReadFromModal() {
    if (!currentMessage) return;
    try {
        const formData = new FormData();
        formData.append('id', currentMessage.id);

        const res = await fetch('../api/messages.php?action=toggle_read', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            currentMessage.is_read = data.is_read;
            updateModalReadBadge(data.is_read);

            // Update in local list
            const idx = allMessages.findIndex(m => m.id === currentMessage.id);
            if (idx !== -1) {
                allMessages[idx].is_read = data.is_read;
                renderMessages();
                loadUnreadCount();
            }
        }
    } catch (err) { /* silent */ }
}

// ============================
// Mark All Read
// ============================
async function markAllRead() {
    try {
        const formData = new FormData();
        if (currentTab !== 'all') {
            formData.append('type', currentTab);
        }

        const res = await fetch('../api/messages.php?action=mark_all_read', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message || 'Alle als gelesen markiert.', 'success');
            loadMessages();
            loadUnreadCount();
        }
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
}

// ============================
// Delete Message
// ============================
async function deleteFromModal() {
    if (!currentMessage) return;
    if (!await wkcConfirm('Nachricht wirklich löschen?')) return;

    try {
        const formData = new FormData();
        formData.append('id', currentMessage.id);

        const res = await fetch('../api/messages.php?action=delete', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            const deletedId = currentMessage.id;
            closeMessageModal();
            showAlert('Nachricht gelöscht.', 'success');
            allMessages = allMessages.filter(m => m.id !== deletedId);
            renderMessages();
            loadUnreadCount();
        } else {
            showAlert(data.error || 'Fehler beim Löschen.', 'error');
        }
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
}

// ============================
// Helpers
// ============================
function esc(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function formatDate(dateStr, full = false) {
    if (!dateStr) return '–';
    const d = new Date(dateStr);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();

    if (full) {
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    if (isToday) {
        return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Gestern';
    }

    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function showAlert(message, type) {
    const box = document.getElementById('alertBox');
    box.className = `p-4 rounded-lg text-sm font-medium flex items-center gap-2 ${
        type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700'
    }`;
    const icon = type === 'error' ? 'error' : 'check_circle';
    box.innerHTML = `<span class="material-symbols-outlined text-lg">${icon}</span> ${message}`;
    box.classList.remove('hidden');
    if (type === 'success') setTimeout(() => box.classList.add('hidden'), 4000);
}
