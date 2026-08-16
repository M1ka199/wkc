/**
 * WKC – Goals/Ziele Admin JavaScript
 * Overview page: shows topic tiles that link to themen-editor.php
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
// Expanded Material Icons List
// ============================
const MATERIAL_ICONS = [
    // Core / UI
    'flag', 'check_circle', 'check', 'done', 'done_all', 'task_alt', 'verified', 'new_releases',
    'star', 'stars', 'auto_awesome', 'emoji_events', 'military_tech', 'workspace_premium',
    'thumb_up', 'thumb_down', 'favorite', 'sentiment_satisfied', 'mood', 'celebration',
    'lightbulb', 'lightbulb_circle', 'tips_and_updates', 'psychology',
    'info', 'help', 'warning', 'error', 'report', 'feedback', 'contact_support',
    'add_circle', 'remove_circle', 'cancel', 'block', 'do_not_disturb',
    'arrow_forward', 'arrow_back', 'arrow_upward', 'arrow_downward', 'open_in_new', 'launch',
    'search', 'zoom_in', 'zoom_out', 'filter_alt', 'sort', 'tune', 'settings', 'build',

    // Government & Politics
    'account_balance', 'gavel', 'policy', 'how_to_vote', 'ballot', 'assured_workload',
    'balance', 'handshake', 'diversity_1', 'diversity_3', 'public', 'language',
    'shield', 'security', 'admin_panel_settings', 'lock', 'lock_open', 'vpn_key',
    'receipt_long', 'description', 'assignment', 'task', 'checklist', 'rule',

    // Infrastructure & Construction
    'construction', 'engineering', 'architecture', 'design_services', 'handyman',
    'home', 'house', 'cottage', 'apartment', 'location_city', 'domain', 'corporate_fare',
    'store', 'storefront', 'local_mall', 'shopping_cart', 'shopping_bag',
    'real_estate_agent', 'maps_home_work', 'other_houses', 'holiday_village', 'foundation',
    'roofing', 'fence', 'deck', 'door_front', 'window', 'stairs', 'elevator',
    'road', 'roundabout_left', 'fork_right', 'merge', 'traffic',
    'local_parking', 'garage', 'warehouse', 'factory', 'precision_manufacturing',

    // Nature & Environment
    'eco', 'nature', 'forest', 'park', 'yard', 'grass', 'spa', 'local_florist',
    'energy_savings_leaf', 'compost', 'recycling', 'delete_sweep',
    'water_drop', 'water', 'waves', 'pool', 'hot_tub',
    'air', 'wb_sunny', 'light_mode', 'dark_mode', 'cloud', 'thunderstorm', 'ac_unit',
    'terrain', 'landscape', 'hiking',
    'pets', 'cruelty_free', 'emoji_nature', 'bug_report', 'pest_control',
    'agriculture',

    // Energy & Sustainability
    'solar_power', 'wind_power', 'bolt', 'power', 'electrical_services', 'electric_bolt',
    'battery_charging_full', 'battery_saver', 'ev_station', 'electric_car', 'electric_bike',
    'propane', 'gas_meter', 'heat_pump', 'mode_heat', 'thermostat',
    'power_settings_new', 'outlet', 'cable',

    // Technology & Digital
    'wifi', 'wifi_find', 'signal_cellular_alt', 'cell_tower', 'router', 'dns', 'hub',
    'devices', 'computer', 'laptop', 'tablet', 'phone_android', 'smartphone', 'watch',
    'monitor', 'smart_display', 'tv', 'cast', 'speaker', 'headphones',
    'cloud_upload', 'cloud_download', 'download', 'upload', 'backup', 'sync',
    'memory', 'storage', 'sd_card', 'usb', 'print', 'scanner',
    'code', 'terminal', 'data_object', 'api', 'webhook', 'integration_instructions',
    'smart_toy', 'biotech', 'science',
    'rocket_launch', 'satellite_alt', 'sensors', 'developer_board',

    // Education & Culture
    'school', 'history_edu', 'auto_stories', 'menu_book', 'library_books', 'local_library',
    'child_care', 'child_friendly', 'family_restroom', 'escalator_warning',
    'calculate', 'functions', 'draw', 'brush',
    'palette', 'theater_comedy', 'music_note', 'piano', 'mic',
    'sports_soccer', 'sports_basketball', 'sports_tennis', 'sports_volleyball',
    'fitness_center', 'self_improvement', 'surfing',
    'museum', 'church', 'mosque', 'synagogue', 'temple_buddhist', 'temple_hindu',
    'photo_camera', 'videocam', 'movie', 'live_tv',

    // People & Community
    'person', 'people', 'groups', 'group', 'group_add', 'person_add', 'person_remove',
    'supervisor_account', 'support_agent', 'manage_accounts', 'badge', 'contact_page',
    'volunteer_activism', 'wc',
    'elderly', 'elderly_woman', 'pregnant_woman', 'wheelchair_pickup', 'accessible',
    'accessibility', 'accessibility_new',
    'face', 'face_2', 'face_3', 'face_4', 'face_5', 'face_6',
    'record_voice_over', 'voice_over_off', 'interpreter_mode',

    // Transport & Mobility
    'directions_bus', 'directions_bike', 'directions_walk', 'directions_car',
    'train', 'tram', 'subway', 'airport_shuttle', 'local_taxi',
    'electric_scooter', 'pedal_bike', 'two_wheeler',
    'flight', 'sailing', 'kayaking',
    'local_shipping',
    'speed',

    // Health & Safety
    'local_hospital', 'medical_services', 'health_and_safety', 'emergency',
    'vaccines', 'medication', 'healing', 'monitor_heart', 'bloodtype',
    'local_pharmacy', 'coronavirus',
    'local_fire_department',
    'crisis_alert', 'sos', 'safety_check',

    // Communication
    'email', 'mail', 'chat', 'chat_bubble', 'sms', 'message', 'textsms',
    'call', 'phone', 'contact_phone', 'phone_in_talk', 'video_call',
    'forum', 'question_answer', 'rate_review', 'reviews', 'comment',
    'share', 'link', 'qr_code', 'qr_code_scanner',
    'campaign', 'newspaper', 'article', 'edit_note', 'edit',
    'notifications', 'notification_important', 'mark_email_read',
    'web', 'translate', 'g_translate',

    // Business & Finance
    'business_center', 'work', 'work_history', 'cases',
    'domain_add', 'add_business', 'edit_location_alt',
    'savings', 'paid', 'euro', 'euro_symbol', 'payments', 'account_balance_wallet',
    'trending_up', 'trending_down', 'trending_flat', 'insights', 'analytics', 'monitoring',
    'assessment', 'leaderboard', 'bar_chart', 'pie_chart', 'show_chart', 'timeline',
    'inventory', 'inventory_2', 'package',

    // Location & Maps
    'map', 'explore', 'place', 'location_on', 'near_me', 'navigation',
    'travel_explore', 'my_location', 'edit_location', 'add_location',
    'where_to_vote', 'pin_drop', 'tour',
    'satellite', 'layers',

    // Time & Calendar
    'event', 'calendar_month', 'calendar_today', 'date_range',
    'schedule', 'timer', 'alarm', 'hourglass_top', 'hourglass_bottom',
    'update', 'history', 'access_time', 'watch_later', 'pending',

    // Food & Dining
    'restaurant', 'restaurant_menu', 'local_cafe', 'coffee', 'lunch_dining',
    'local_bar', 'wine_bar', 'liquor', 'local_pizza', 'bakery_dining',
    'local_grocery_store', 'shopping_basket', 'kitchen', 'microwave', 'blender',
    'egg', 'icecream', 'cake', 'cookie', 'fastfood',

    // Files & Documents
    'folder', 'folder_open', 'folder_shared', 'create_new_folder',
    'attach_file', 'attachment', 'file_present', 'file_copy',
    'picture_as_pdf', 'text_snippet', 'note', 'sticky_note_2',
    'photo', 'image', 'panorama', 'gif',

    // Miscellaneous
    'category', 'label', 'bookmark', 'bookmarks', 'loyalty',
    'extension', 'widgets', 'view_module', 'apps', 'grid_view',
    'brightness_low', 'brightness_high', 'contrast',
    'emoji_objects', 'emoji_food_beverage', 'emoji_transportation', 'emoji_symbols',
    'key', 'password', 'fingerprint',
    'visibility', 'visibility_off', 'preview', 'pageview',
    'add_shopping_cart', 'remove_shopping_cart',
    'local_atm', 'attach_money',
    'home_repair_service', 'plumbing',
    'sports_esports', 'videogame_asset',
    'token', 'interests',
    'nightlife', 'night_shelter',
    'festival', 'stadium', 'attractions',
];

// Deduplicate
const MATERIAL_ICONS_SET = [...new Set(MATERIAL_ICONS)];

// ============================
// Helpers
// ============================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

let searchDebounceTimer = null;

// ============================
// Init
// ============================
document.addEventListener('DOMContentLoaded', () => {
    if (window.AdminWysiwyg) {
        window.AdminWysiwyg.init('topicDescription', { minHeight: '120px', placeholder: 'Kurzbeschreibung des Themas...' });
    }
    loadGoals();
    initIconGrids();
    initColorSync();
    initImageUpload();
    initSearch();
});

// ============================
// Search
// ============================
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            loadGoals(searchInput.value.trim());
        }, 300);
    });
}

// ============================
// Load Goals – renders topic TILES (cards) that link to themen-editor.php
// ============================
async function loadGoals(search = '') {
    const container = document.getElementById('topicsList');
    try {
        let url = '../api/goals.php?action=admin_list';
        if (search) url += '&search=' + encodeURIComponent(search);

        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();

        // Update stats
        document.getElementById('statTopics').textContent = data.counts?.topics || 0;
        document.getElementById('statItems').textContent = data.counts?.items || 0;

        if (!data.topics || data.topics.length === 0) {
            container.innerHTML = `
                <div class="col-span-full bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">flag</span>
                    <p class="text-gray-500 font-medium">Noch keine Themen angelegt.</p>
                    <button onclick="openTopicModal()" class="mt-4 bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all">
                        <span class="material-symbols-outlined text-sm align-middle mr-1">add</span>Erstes Thema anlegen
                    </button>
                </div>`;
            return;
        }

        container.innerHTML = data.topics.map(topic => {
            const itemCount = (topic.items || []).length;
            const color = escapeHtml(topic.color || '#7c3aed');
            const icon = escapeHtml(topic.icon || 'flag');
            const imgSrc = topic.image
                ? (topic.image.startsWith('http') ? topic.image : '../' + topic.image)
                : '';
            const countBadge = itemCount > 0
                ? `<span class="absolute top-3 right-12 bg-white/90 text-xs font-bold px-2 py-0.5 rounded-full" style="color:${color}">${itemCount} Ziel${itemCount !== 1 ? 'e' : ''}</span>`
                : '';

            return `
                <a href="themen-editor.php?id=${topic.id}" class="topic-tile group relative overflow-hidden rounded-2xl bg-white shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 flex flex-col" style="width:280px;max-width:100%">
                    <div class="relative h-36 overflow-hidden">
                        ${imgSrc
                            ? `<img src="${escapeHtml(imgSrc)}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">`
                            : `<div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-gray-300 text-4xl">${icon}</span></div>`
                        }
                        <div class="absolute inset-0" style="background:linear-gradient(to top,${color}cc,transparent)"></div>
                        <div class="absolute bottom-3 left-3 flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-xl">${icon}</span>
                            </div>
                            <span class="text-white font-bold text-sm">${escapeHtml(topic.name)}</span>
                        </div>
                        ${countBadge}
                        <button onclick="event.preventDefault(); event.stopPropagation(); confirmDeleteTopic(${topic.id}, '${escapeHtml(topic.name).replace(/'/g, "\\'")}')" class="absolute top-3 right-3 w-8 h-8 rounded-lg bg-black/20 backdrop-blur-sm flex items-center justify-center text-white/70 hover:text-red-400 hover:bg-black/40 opacity-0 group-hover:opacity-100 transition-all" title="Löschen">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                    <div class="p-4 flex-1">
                        ${topic.description ? `<p class="text-xs text-gray-500 truncate">${escapeHtml(topic.description)}</p>` : ''}
                    </div>
                    <div class="px-4 pb-3">
                        <span class="text-xs text-primary font-bold group-hover:underline flex items-center gap-1">Bearbeiten<span class="material-symbols-outlined text-xs">arrow_forward</span></span>
                    </div>
                </a>
            `;
        }).join('');
    } catch (err) {
        console.error('loadGoals:', err);
        container.innerHTML = '<div class="col-span-full text-center py-12 text-red-400 text-sm">Fehler beim Laden der Themen.</div>';
    }
}

// Navigate to topic editor
function editTopic(id) {
    window.location.href = `themen-editor.php?id=${id}`;
}

// ============================
// Icon Grid
// ============================
function initIconGrids() {
    renderIconGrid('topicIconGrid', 'topicIcon', 'topicIconPreviewIcon', 'topicIconSearch');
}

function renderIconGrid(gridId, hiddenId, previewId, searchId, filter = '') {
    const grid = document.getElementById(gridId);
    if (!grid) return;

    const selectedIcon = document.getElementById(hiddenId)?.value || '';
    const filtered = filter
        ? MATERIAL_ICONS_SET.filter(icon => icon.toLowerCase().includes(filter.toLowerCase()))
        : MATERIAL_ICONS_SET;

    grid.innerHTML = filtered.map(icon => `
        <button type="button" onclick="selectIcon('${gridId}', '${hiddenId}', '${previewId}', '${icon}')" class="${icon === selectedIcon ? 'selected' : ''}" title="${icon}">
            <span class="material-symbols-outlined">${icon}</span>
        </button>
    `).join('');

    const searchInput = document.getElementById(searchId);
    if (searchInput && !searchInput._bound) {
        searchInput._bound = true;
        searchInput.addEventListener('input', () => {
            renderIconGrid(gridId, hiddenId, previewId, searchId, searchInput.value);
        });
    }
}

function selectIcon(gridId, hiddenId, previewId, icon) {
    document.getElementById(hiddenId).value = icon;
    document.getElementById(previewId).textContent = icon;
    const grid = document.getElementById(gridId);
    grid.querySelectorAll('button').forEach(btn => {
        btn.classList.toggle('selected', btn.title === icon);
    });
}

// ============================
// Color Sync
// ============================
function initColorSync() {
    const colorInput = document.getElementById('topicColor');
    const hexInput = document.getElementById('topicColorHex');
    const preview = document.getElementById('topicColorPreview');

    if (!colorInput || !hexInput) return;

    colorInput.addEventListener('input', () => {
        hexInput.value = colorInput.value;
        preview.style.background = colorInput.value;
    });

    hexInput.addEventListener('input', () => {
        const val = hexInput.value;
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            colorInput.value = val;
            preview.style.background = val;
        }
    });
}

// ============================
// Image Upload
// ============================
function initImageUpload() {
    const dropZone = document.getElementById('topicDropZone');
    const fileInput = document.getElementById('topicImageFile');

    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-primary/5'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-primary', 'bg-primary/5'); });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            previewLocalImage(e.dataTransfer.files[0]);
        }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) previewLocalImage(fileInput.files[0]);
    });
}

function previewLocalImage(file) {
    const reader = new FileReader();
    reader.onload = (e) => showImagePreview(e.target.result);
    reader.readAsDataURL(file);
}

function showImagePreview(src) {
    const preview = document.getElementById('topicImagePreview');
    const img = document.getElementById('topicImagePreviewImg');
    img.src = src;
    preview.classList.remove('hidden');
}

function clearTopicImage() {
    document.getElementById('topicImagePreview').classList.add('hidden');
    document.getElementById('topicImagePreviewImg').src = '';
    document.getElementById('topicImage').value = '';
    document.getElementById('topicImageFile').value = '';
    const urlInput = document.getElementById('topicImageUrl');
    if (urlInput) urlInput.value = '';
}

function switchImageTab(tab) {
    document.querySelectorAll('.image-tab').forEach(btn => {
        btn.classList.remove('bg-primary/10', 'text-primary');
        btn.classList.add('text-gray-500');
    });
    document.querySelector(`.image-tab[data-tab="${tab}"]`)?.classList.add('bg-primary/10', 'text-primary');
    document.querySelector(`.image-tab[data-tab="${tab}"]`)?.classList.remove('text-gray-500');

    document.querySelectorAll('.image-tab-content').forEach(el => el.classList.add('hidden'));
    if (tab === 'upload') document.getElementById('imageTabUpload')?.classList.remove('hidden');
    if (tab === 'unsplash') document.getElementById('imageTabUnsplash')?.classList.remove('hidden');
    if (tab === 'url') document.getElementById('imageTabUrl')?.classList.remove('hidden');
}

async function searchUnsplash() {
    const query = document.getElementById('unsplashSearch').value.trim();
    const container = document.getElementById('unsplashResults');
    if (!query) { container.innerHTML = '<p class="col-span-3 text-center text-xs text-gray-400 py-4">Suchbegriff eingeben</p>'; return; }
    container.innerHTML = '<p class="col-span-3 text-center text-xs text-gray-400 py-4">Suche...</p>';

    container.innerHTML = Array.from({ length: 9 }, (_, i) => {
        const thumbUrl = `https://source.unsplash.com/200x150/?${encodeURIComponent(query)}&sig=${i}`;
        const fullUrl = `https://source.unsplash.com/600x450/?${encodeURIComponent(query)}&sig=${i}`;
        return `<button type="button" onclick="selectUnsplashImage('${fullUrl}')" class="aspect-video rounded-lg overflow-hidden bg-gray-100 hover:ring-2 hover:ring-primary transition-all">
            <img src="${thumbUrl}" alt="${escapeHtml(query)}" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.style.display='none'">
        </button>`;
    }).join('');
}

function selectUnsplashImage(url) {
    document.getElementById('topicImage').value = url;
    showImagePreview(url);
}

// ============================
// TOPIC MODAL (for creating new topics)
// ============================
function openTopicModal() {
    const modal = document.getElementById('topicModal');
    const title = document.getElementById('topicModalTitle');

    document.getElementById('topicForm').reset();
    document.getElementById('topicId').value = '';
    if (window.AdminWysiwyg) {
        window.AdminWysiwyg.setValue('topicDescription', '');
    }
    clearTopicImage();

    title.textContent = 'Neues Thema';
    document.getElementById('topicIcon').value = 'flag';
    document.getElementById('topicIconPreviewIcon').textContent = 'flag';
    document.getElementById('topicColor').value = '#7c3aed';
    document.getElementById('topicColorHex').value = '#7c3aed';
    document.getElementById('topicColorPreview').style.background = '#7c3aed';

    renderIconGrid('topicIconGrid', 'topicIcon', 'topicIconPreviewIcon', 'topicIconSearch');
    document.getElementById('topicIconSearch').value = '';
    modal.classList.remove('hidden');
}

function closeTopicModal() {
    document.getElementById('topicModal').classList.add('hidden');
}

// Topic form submit
document.getElementById('topicForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData();
    const id = document.getElementById('topicId').value;
    if (id) formData.append('id', id);
    formData.append('name', document.getElementById('topicName').value);
    formData.append('description', window.AdminWysiwyg ? window.AdminWysiwyg.getValue('topicDescription') : document.getElementById('topicDescription').value);
    formData.append('color', document.getElementById('topicColorHex').value);
    formData.append('icon', document.getElementById('topicIcon').value);

    const imageFile = document.getElementById('topicImageFile').files[0];
    const imageUrl = document.getElementById('topicImageUrl')?.value || '';
    const existingImage = document.getElementById('topicImage').value;

    if (imageFile) {
        formData.append('image_file', imageFile);
    } else if (imageUrl) {
        formData.append('image', imageUrl);
    } else if (existingImage) {
        formData.append('image', existingImage);
    }

    try {
        const res = await fetch('../api/goals.php?action=save_topic', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            closeTopicModal();
            // If newly created, navigate to its editor
            if (!id && data.id) {
                window.location.href = `themen-editor.php?id=${data.id}`;
            } else {
                loadGoals();
            }
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveTopic:', err);
        alert('Netzwerkfehler beim Speichern.');
    }
});

// ============================
// DELETE
// ============================
let deleteCallback = null;

function confirmDeleteTopic(id, name) {
    document.getElementById('deleteModalTitle').textContent = 'Thema löschen?';
    document.getElementById('deleteModalText').textContent = `Möchten Sie „${name}" und alle zugehörigen Ziele wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`;
    deleteCallback = () => deleteTopic(id);
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteCallback = null;
}

document.getElementById('deleteConfirmBtn')?.addEventListener('click', () => {
    if (deleteCallback) deleteCallback();
});

async function deleteTopic(id) {
    try {
        const res = await fetch(`../api/goals.php?type=topic&id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
        });
        const data = await res.json();

        if (data.success) {
            closeDeleteModal();
            loadGoals();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteTopic:', err);
    }
}

