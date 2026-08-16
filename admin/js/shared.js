/**
 * WKC – Shared Admin Utilities
 * Must be loaded before page-specific JS files.
 */

// ============================
// Shared Sidebar Controls
// ============================
window.toggleSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
};

window.toggleCollapse = function () {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const icon = document.getElementById('collapseIcon');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-collapsed', collapsed);
    if (icon) icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
    localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
};

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebarCollapsed') !== '1') return;
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const icon = document.getElementById('collapseIcon');
    if (!sidebar) return;
    sidebar.classList.add('sidebar-collapsed');
    if (main) main.classList.add('main-collapsed');
    if (icon) icon.textContent = 'chevron_right';
});

// ============================
// Styled Confirmation Modal
// ============================
(function () {
    // Create modal DOM once
    const modalHTML = `
        <div id="wkcConfirmModal" class="fixed inset-0 z-[9999] hidden">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="wkcConfirmOverlay"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all" id="wkcConfirmBox">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div id="wkcConfirmIconWrap" class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-red-600 text-2xl" id="wkcConfirmIcon">warning</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 id="wkcConfirmTitle" class="text-lg font-bold text-gray-900 mb-1">Löschen bestätigen</h3>
                                <p id="wkcConfirmMessage" class="text-sm text-gray-500 leading-relaxed"></p>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 px-6 pb-6">
                        <button id="wkcConfirmCancel" class="flex-1 py-2.5 rounded-lg border border-gray-200 text-gray-700 font-bold text-sm hover:bg-gray-50 transition-colors">
                            Abbrechen
                        </button>
                        <button id="wkcConfirmOk" class="flex-1 py-2.5 rounded-lg bg-red-600 text-white font-bold text-sm hover:bg-red-700 transition-colors">
                            Löschen
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    document.addEventListener('DOMContentLoaded', () => {
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    });

    /**
     * Show a styled confirmation dialog.
     * @param {string} message - The confirmation message.
     * @param {object} [options] - Optional settings.
     * @param {string} [options.title='Löschen bestätigen'] - Dialog title.
     * @param {string} [options.confirmText='Löschen'] - Confirm button text.
     * @param {string} [options.confirmClass] - Tailwind classes for confirm button.
     * @param {string} [options.icon='warning'] - Material icon name.
     * @param {string} [options.iconBg='bg-red-100'] - Icon circle bg class.
     * @param {string} [options.iconColor='text-red-600'] - Icon color class.
     * @returns {Promise<boolean>} Resolves true if confirmed, false if cancelled.
     */
    window.wkcConfirm = function (message, options = {}) {
        return new Promise((resolve) => {
            const modal = document.getElementById('wkcConfirmModal');
            const overlay = document.getElementById('wkcConfirmOverlay');
            const title = document.getElementById('wkcConfirmTitle');
            const msg = document.getElementById('wkcConfirmMessage');
            const okBtn = document.getElementById('wkcConfirmOk');
            const cancelBtn = document.getElementById('wkcConfirmCancel');
            const iconWrap = document.getElementById('wkcConfirmIconWrap');
            const icon = document.getElementById('wkcConfirmIcon');

            title.textContent = options.title || 'Löschen bestätigen';
            msg.textContent = message;
            okBtn.textContent = options.confirmText || 'Löschen';
            icon.textContent = options.icon || 'warning';

            // Reset classes
            okBtn.className = options.confirmClass || 'flex-1 py-2.5 rounded-lg bg-red-600 text-white font-bold text-sm hover:bg-red-700 transition-colors';
            iconWrap.className = `w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 ${options.iconBg || 'bg-red-100'}`;
            icon.className = `material-symbols-outlined text-2xl ${options.iconColor || 'text-red-600'}`;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            function cleanup() {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                overlay.removeEventListener('click', onCancel);
                document.removeEventListener('keydown', onKey);
            }

            function onOk() { cleanup(); resolve(true); }
            function onCancel() { cleanup(); resolve(false); }
            function onKey(e) { if (e.key === 'Escape') onCancel(); }

            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
            overlay.addEventListener('click', onCancel);
            document.addEventListener('keydown', onKey);

            // Focus the cancel button for keyboard accessibility
            setTimeout(() => cancelBtn.focus(), 50);
        });
    };
})();
