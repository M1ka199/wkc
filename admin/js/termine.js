/**
 * WKC – Termine JavaScript
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

function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    return parts[0] + ':' + parts[1] + ' Uhr';
}

// ============================
// Load Events
// ============================
async function loadEvents() {
    const tbody = document.getElementById('eventsTableBody');
    try {
        const res = await fetch('../api/events.php?action=list&past=1', { credentials: 'include' });
        const data = await res.json();

        if (!data.events || data.events.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Noch keine Termine angelegt.</td></tr>';
            updateStats(0, 0, 0);
            return;
        }

        const today = new Date(new Date().toDateString());
        let upcoming = 0, past = 0;

        tbody.innerHTML = data.events.map(e => {
            const date = formatDate(e.event_date);
            const time = e.event_time ? formatTime(e.event_time) : '';
            const dateTime = time ? `${date}, ${time}` : date;
            const isPast = new Date(e.event_date) < today;
            const pastClass = isPast ? 'opacity-50' : '';
            const visibilityBadge = e.visibility === 'internal'
                ? '<span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Intern</span>'
                : '<span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">Öffentlich</span>';
            const homeBadge = e.show_on_home == 1
                ? '<span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Startseite</span>'
                : '';

            if (isPast) past++;
            else upcoming++;

            return `
                <tr class="hover:bg-bg-light/30 transition-colors ${pastClass}">
                    <td class="px-6 py-4">
                        <p class="text-sm font-bold text-gray-900">${escapeHtml(e.title)}</p>
                        ${e.description ? `<p class="text-xs text-gray-400 mt-0.5 line-clamp-1">${escapeHtml(e.description)}</p>` : ''}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                        ${dateTime}
                        ${isPast ? '<span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">Vergangen</span>' : ''}
                        ${visibilityBadge}
                        ${homeBadge}
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

        updateStats(data.events.length, upcoming, past);
    } catch (err) {
        console.error('loadEvents:', err);
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Termine.</td></tr>';
    }
}

function updateStats(total, upcoming, past) {
    document.getElementById('statTotal').textContent = total;
    document.getElementById('statUpcoming').textContent = upcoming;
    document.getElementById('statPast').textContent = past;
}

// ============================
// Modal Handling
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
    document.getElementById('eventVisibility').value = event ? (event.visibility || 'public') : 'public';
    document.getElementById('eventShowOnHome').checked = event ? Number(event.show_on_home || 0) === 1 : true;

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

// ============================
// Save Event
// ============================
document.getElementById('eventForm').addEventListener('submit', async (e) => {
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
    formData.append('visibility', document.getElementById('eventVisibility').value);
    formData.append('show_on_home', document.getElementById('eventShowOnHome').checked ? '1' : '0');

    try {
        const res = await fetch(`../api/events.php?action=${action}`, {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            closeEventModal();
            loadEvents();
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveEvent:', err);
        alert('Netzwerkfehler beim Speichern.');
    }
});

// ============================
// Delete Event
// ============================
async function deleteEvent(id, title) {
    if (!await wkcConfirm(`Termin \u201E${title}\u201C wirklich löschen?`)) return;

    try {
        const res = await fetch(`../api/events.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            loadEvents();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteEvent:', err);
    }
}

// ============================
// Init
// ============================
document.addEventListener('DOMContentLoaded', () => {
    loadEvents();
});
