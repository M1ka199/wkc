/**
 * WKC – Mitglieder (Member Management) JavaScript
 */

// ============================
// Sidebar + Logout
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
// Load Members
// ============================
document.addEventListener('DOMContentLoaded', async () => {
    await loadMembers();
    // Auto-open edit modal if ?edit= parameter exists
    const params = new URLSearchParams(window.location.search);
    const editId = params.get('edit');
    if (editId) {
        editMember(parseInt(editId));
        // Clean URL
        window.history.replaceState({}, '', 'mitglieder.php');
    }
});

let allMembers = [];

async function loadMembers() {
    try {
        const res = await fetch('../api/members.php?action=admin_list', { credentials: 'include' });
        if (!res.ok) {
            console.error('loadMembers: HTTP', res.status);
            document.getElementById('membersList').innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-red-400 text-sm">Fehler beim Laden der Mitglieder.</td></tr>';
            return;
        }
        const data = await res.json();
        allMembers = data.members || [];
        renderMembers(allMembers);
    } catch (err) {
        console.error('loadMembers:', err);
        document.getElementById('membersList').innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-red-400 text-sm">Verbindungsfehler.</td></tr>';
    }
}

function renderMembers(members) {
    const tbody = document.getElementById('membersList');
    const countEl = document.getElementById('memberCount');

    if (!members || members.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">Keine Mitglieder gefunden.</td></tr>';
        countEl.textContent = '0 Mitglieder';
        return;
    }

    countEl.textContent = members.length === 1 ? '1 Mitglied' : `${members.length} Mitglieder`;

    const roleLabels = { admin: 'Admin', editor: 'Redakteur', member: 'Mitglied' };
    const roleColors = { admin: 'bg-red-50 text-red-600', editor: 'bg-blue-50 text-blue-600', member: 'bg-gray-100 text-gray-600' };

    tbody.innerHTML = members.map(m => {
            const img = m.profile_image
                ? `<img src="../${esc(m.profile_image)}" alt="" class="w-10 h-10 rounded-full object-cover">`
                : `<div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">${esc(m.display_name?.charAt(0) || '?')}</div>`;

            const roleBadge = `<span class="px-2 py-0.5 rounded-full text-xs font-bold ${roleColors[m.role] || roleColors.member}">${roleLabels[m.role] || 'Mitglied'}</span>`;
            const boardBadge = m.is_board_member ? '<span class="material-symbols-outlined text-primary text-lg">check_circle</span>' : '<span class="text-gray-300">–</span>';

            let invitationBadge = '';
            if (m.must_set_password && m.invitation_sent) {
                invitationBadge = '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-600">Einladung ausstehend</span>';
            } else if (m.invitation_sent && !m.must_set_password) {
                invitationBadge = '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-50 text-green-600">Einladung angenommen</span>';
            }

            return `
                <tr class="hover:bg-bg-light/30 transition-colors">
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-bold text-gray-400">${m.board_order || '–'}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            ${img}
                            <div>
                                <p class="text-sm font-bold text-gray-900">${esc(m.display_name)}</p>
                                <p class="text-xs text-gray-400">@${esc(m.username)}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">${roleBadge} ${invitationBadge}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${esc(m.position || '–')}</td>
                    <td class="px-6 py-4 text-center">${boardBadge}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${esc(m.member_since || '–')}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="editMember(${m.id})" class="p-1.5 text-gray-400 hover:text-primary transition-colors rounded hover:bg-bg-light" title="Bearbeiten">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button onclick="deleteMember(${m.id}, '${esc(m.display_name)}')" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors rounded hover:bg-red-50" title="Deaktivieren">
                                <span class="material-symbols-outlined text-lg">person_off</span>
                            </button>
                        </div>
                    </td>
                </tr>`;
    }).join('');
}

// ============================
// Search / Filter
// ============================
document.getElementById('memberSearch').addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    if (!q) {
        renderMembers(allMembers);
        return;
    }
    const filtered = allMembers.filter(m => {
        return (m.display_name || '').toLowerCase().includes(q)
            || (m.username || '').toLowerCase().includes(q)
            || (m.position || '').toLowerCase().includes(q)
            || (m.role || '').toLowerCase().includes(q)
            || (m.member_since || '').toLowerCase().includes(q);
    });
    renderMembers(filtered);
});

// ============================
// Modal
// ============================
function openModal(title = 'Neues Mitglied') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('memberModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('memberModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('memberForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('memberImg').classList.add('hidden');
    document.getElementById('memberImageIcon').classList.remove('hidden');
    document.getElementById('pwHint').textContent = '(min. 8 Zeichen)';
    document.getElementById('memberPassword').required = true;
    document.getElementById('memberRole').value = 'member';
    document.getElementById('memberIsBoardMember').checked = false;
    document.getElementById('memberEmail').value = '';
    document.getElementById('invitationToggle').classList.remove('hidden');
    document.getElementById('memberSendInvitation').checked = false;
    document.getElementById('passwordLinkSection').classList.add('hidden');
    document.getElementById('passwordLinkStatus').classList.add('hidden');
    toggleInvitationMode();
}

// New member button
document.getElementById('btnAddMember').addEventListener('click', () => {
    closeModal(); // Reset first
    document.getElementById('memberPassword').required = true;
    document.getElementById('invitationToggle').classList.remove('hidden');
    document.getElementById('passwordLinkSection').classList.add('hidden');
    openModal('Neues Mitglied');
});

// Invitation toggle: when checked, hide password field
function toggleInvitationMode() {
    const checked = document.getElementById('memberSendInvitation').checked;
    const pwField = document.getElementById('memberPassword');
    const pwContainer = pwField.closest('div');
    if (checked) {
        pwContainer.classList.add('hidden');
        pwField.required = false;
        pwField.value = '';
    } else {
        pwContainer.classList.remove('hidden');
        // Only require PW for new members
        if (!document.getElementById('memberId').value) {
            pwField.required = true;
        }
    }
}
document.getElementById('memberSendInvitation').addEventListener('change', toggleInvitationMode);

// Edit member
async function editMember(id) {
    try {
        const res = await fetch(`../api/members.php?action=profile&id=${id}`);
        const data = await res.json();
        if (!data.member) return;

        const m = data.member;
        document.getElementById('memberId').value = m.id;
        document.getElementById('memberUsername').value = m.username || '';
        document.getElementById('memberUsername').readOnly = true;
        document.getElementById('memberDisplayName').value = m.display_name || '';
        document.getElementById('memberPosition').value = m.position || '';
        document.getElementById('memberOrder').value = m.board_order || 99;
        document.getElementById('memberSince').value = m.member_since || '';
        document.getElementById('memberBio').value = m.bio || '';
        document.getElementById('memberQuote').value = m.quote || '';
        document.getElementById('memberRole').value = m.role || 'member';
        document.getElementById('memberIsBoardMember').checked = !!m.is_board_member;

        document.getElementById('memberEmail').value = m.email || '';

        // Password not required for editing
        document.getElementById('memberPassword').required = false;
        document.getElementById('pwHint').textContent = '(leer lassen = unverändert)';

        // Hide invitation toggle in edit mode, show password link section always
        document.getElementById('invitationToggle').classList.add('hidden');
        document.getElementById('memberSendInvitation').checked = false;
        document.getElementById('passwordLinkSection').classList.remove('hidden');

        // Show invitation status if applicable
        const statusEl = document.getElementById('passwordLinkStatus');
        if (m.invitation_sent && m.must_set_password) {
            statusEl.textContent = 'Einladung gesendet – Passwort noch nicht gesetzt.';
            statusEl.className = 'text-xs text-amber-600 mt-2 font-medium';
        } else if (m.invitation_sent && !m.must_set_password) {
            statusEl.textContent = 'Einladung angenommen – Passwort wurde gesetzt.';
            statusEl.className = 'text-xs text-green-600 mt-2 font-medium';
        } else {
            statusEl.textContent = '';
            statusEl.classList.add('hidden');
        }

        // Profile Image
        if (m.profile_image) {
            document.getElementById('memberImg').src = '../' + m.profile_image;
            document.getElementById('memberImg').classList.remove('hidden');
            document.getElementById('memberImageIcon').classList.add('hidden');
        }

        openModal('Mitglied bearbeiten');
    } catch (err) {
        console.error('editMember:', err);
    }
}

// Handle image preview in modal
document.getElementById('memberImageInput').addEventListener('change', (e) => {
    if (e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('memberImg').src = ev.target.result;
            document.getElementById('memberImg').classList.remove('hidden');
            document.getElementById('memberImageIcon').classList.add('hidden');
        };
        reader.readAsDataURL(e.target.files[0]);
    }
});

// ============================
// Save member
// ============================
document.getElementById('memberForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('memberId').value;
    const isEditing = !!id;

    const formData = new FormData();
    formData.append('username', document.getElementById('memberUsername').value.trim());
    formData.append('display_name', document.getElementById('memberDisplayName').value.trim());
    formData.append('position', document.getElementById('memberPosition').value.trim());
    formData.append('board_order', document.getElementById('memberOrder').value);
    formData.append('member_since', document.getElementById('memberSince').value.trim());
    formData.append('bio', document.getElementById('memberBio').value.trim());
    formData.append('quote', document.getElementById('memberQuote').value.trim());
    formData.append('role', document.getElementById('memberRole').value);
    formData.append('is_board_member', document.getElementById('memberIsBoardMember').checked ? '1' : '0');
    formData.append('email', document.getElementById('memberEmail').value.trim());

    // Password
    const pw = document.getElementById('memberPassword').value;
    if (pw) formData.append('password', pw);

    // Image
    const imgFile = document.getElementById('memberImageInput').files[0];
    if (imgFile) formData.append('profile_image', imgFile);

    try {
        let res;
        if (isEditing) {
            formData.append('id', id);
            res = await fetch('../api/members.php', { method: 'PUT', body: new URLSearchParams(formData) });
        } else {
            // Check if invitation should be sent
            if (document.getElementById('memberSendInvitation').checked) {
                formData.append('send_invitation', '1');
            }
            if (!document.getElementById('memberSendInvitation').checked && (!pw || pw.length < 8)) {
                showAlert('Passwort muss mindestens 8 Zeichen lang sein.', 'error');
                return;
            }
            res = await fetch('../api/members.php', { method: 'POST', body: formData });
        }
        const data = await res.json();

        if (data.success) {
            closeModal();
            showAlert(data.message || 'Gespeichert.', 'success');
            loadMembers();
        } else {
            showAlert(data.error || 'Fehler beim Speichern.', 'error');
        }
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
});

// ============================
// Send Password Link (invitation / reset)
// ============================
async function sendPasswordLink() {
    const memberId = document.getElementById('memberId').value;
    const email = document.getElementById('memberEmail').value.trim();

    if (!memberId) {
        showAlert('Mitglied muss zuerst gespeichert werden.', 'error');
        return;
    }
    if (!email) {
        showAlert('Bitte zuerst eine E-Mail-Adresse angeben und speichern.', 'error');
        return;
    }

    const btn = document.getElementById('btnSendPasswordLink');
    const label = document.getElementById('btnSendPasswordLinkLabel');
    const origText = label.textContent;
    btn.disabled = true;
    label.textContent = 'Wird gesendet…';

    try {
        const formData = new FormData();
        formData.append('action', 'send_invitation');
        formData.append('member_id', memberId);

        const res = await fetch('../api/members.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message || 'Passwort-Link gesendet.', 'success');
            const statusEl = document.getElementById('passwordLinkStatus');
            statusEl.textContent = 'Einladung gesendet – Passwort noch nicht gesetzt.';
            statusEl.className = 'text-xs text-amber-600 mt-2 font-medium';
            loadMembers();
        } else {
            showAlert(data.error || 'Fehler beim Senden.', 'error');
        }
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    } finally {
        btn.disabled = false;
        label.textContent = origText;
    }
}

// Wire up password link button
document.getElementById('btnSendPasswordLink').addEventListener('click', sendPasswordLink);

// ============================
// Delete / Deactivate member
// ============================
async function deleteMember(id, name) {
    if (!await wkcConfirm(`„${name}" wirklich deaktivieren?`, { title: 'Mitglied deaktivieren', confirmText: 'Deaktivieren', icon: 'person_off' })) return;
    try {
        const res = await fetch(`../api/members.php?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            loadMembers();
        } else {
            showAlert(data.error || 'Fehler.', 'error');
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
