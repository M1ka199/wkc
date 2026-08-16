/**
 * WKC – Themen-Editor JavaScript
 * Manages a single topic and its goals/items.
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
    'smart_toy', 'precision_manufacturing', 'biotech', 'science',
    'rocket_launch', 'satellite_alt', 'sensors', 'developer_board',

    // Education & Culture
    'school', 'history_edu', 'auto_stories', 'menu_book', 'library_books', 'local_library',
    'child_care', 'child_friendly', 'family_restroom', 'escalator_warning',
    'science', 'biotech', 'calculate', 'functions', 'draw', 'brush',
    'palette', 'theater_comedy', 'music_note', 'piano', 'headphones', 'mic',
    'sports_soccer', 'sports_basketball', 'sports_tennis', 'sports_volleyball',
    'fitness_center', 'self_improvement', 'hiking', 'pool', 'surfing',
    'museum', 'church', 'mosque', 'synagogue', 'temple_buddhist', 'temple_hindu',
    'photo_camera', 'videocam', 'movie', 'live_tv',

    // People & Community
    'person', 'people', 'groups', 'group', 'group_add', 'person_add', 'person_remove',
    'supervisor_account', 'support_agent', 'manage_accounts', 'badge', 'contact_page',
    'volunteer_activism', 'handshake', 'diversity_1', 'diversity_3', 'wc',
    'elderly', 'elderly_woman', 'pregnant_woman', 'wheelchair_pickup', 'accessible',
    'accessibility', 'accessibility_new',
    'face', 'face_2', 'face_3', 'face_4', 'face_5', 'face_6',
    'record_voice_over', 'voice_over_off', 'interpreter_mode',

    // Transport & Mobility
    'directions_bus', 'directions_bike', 'directions_walk', 'directions_car',
    'train', 'tram', 'subway', 'airport_shuttle', 'local_taxi',
    'electric_car', 'electric_bike', 'electric_scooter', 'pedal_bike', 'two_wheeler',
    'flight', 'sailing', 'kayaking',
    'local_shipping',
    'traffic', 'speed', 'construction',

    // Health & Safety
    'local_hospital', 'medical_services', 'health_and_safety', 'emergency',
    'vaccines', 'medication', 'healing', 'monitor_heart', 'bloodtype',
    'local_pharmacy', 'biotech', 'coronavirus',
    'local_fire_department',
    'crisis_alert', 'sos', 'safety_check',

    // Communication
    'email', 'mail', 'chat', 'chat_bubble', 'sms', 'message', 'textsms',
    'call', 'phone', 'contact_phone', 'phone_in_talk', 'video_call',
    'forum', 'question_answer', 'rate_review', 'reviews', 'comment',
    'share', 'link', 'qr_code', 'qr_code_scanner',
    'campaign', 'newspaper', 'article', 'edit_note', 'draw', 'edit',
    'notifications', 'notification_important', 'mark_email_read',
    'web', 'language', 'translate', 'g_translate',

    // Business & Finance
    'business_center', 'work', 'work_history', 'cases',
    'domain_add', 'add_business', 'edit_location_alt',
    'savings', 'paid', 'euro', 'euro_symbol', 'payments', 'account_balance_wallet',
    'trending_up', 'trending_down', 'trending_flat', 'insights', 'analytics', 'monitoring',
    'assessment', 'leaderboard', 'bar_chart', 'pie_chart', 'show_chart', 'timeline',
    'inventory', 'inventory_2', 'package', 'local_shipping',
    'receipt', 'request_quote', 'calculate',

    // Location & Maps
    'map', 'explore', 'place', 'location_on', 'near_me', 'navigation',
    'travel_explore', 'my_location', 'edit_location', 'add_location',
    'where_to_vote', 'pin_drop', 'flag', 'tour',
    'satellite', 'layers', 'terrain',

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
    'brightness_low', 'brightness_high', 'contrast', 'palette',
    'emoji_objects', 'emoji_food_beverage', 'emoji_transportation', 'emoji_symbols',
    'key', 'vpn_key', 'password', 'fingerprint',
    'visibility', 'visibility_off', 'preview', 'pageview',
    'shopping_cart', 'add_shopping_cart', 'remove_shopping_cart',
    'local_atm', 'attach_money',
    'home_repair_service', 'plumbing',
    'sports_esports', 'videogame_asset',
    'token', 'interests',
    'nightlife', 'night_shelter', 'other_houses',
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

// ============================
// State
// ============================
let topicData = null;
let deleteCallback = null;

// ============================
// Init
// ============================
document.addEventListener('DOMContentLoaded', () => {
    if (window.AdminWysiwyg) {
        window.AdminWysiwyg.init('topicDescription', { minHeight: '120px', placeholder: 'Kurzbeschreibung des Themas...' });
        window.AdminWysiwyg.init('itemDescription', { minHeight: '120px', placeholder: 'Detailbeschreibung des Ziels...' });
    }
    loadTopic();
    initColorSync();
    initImageUpload();
    initIconGrids();
    initDragAndDrop();
});

// ============================
// Load Topic
// ============================
async function loadTopic() {
    try {
        const res = await fetch(`../api/goals.php?action=detail&id=${TOPIC_ID}`, { credentials: 'include' });
        const data = await res.json();

        if (!data.topic) {
            window.location.href = 'ziele.php';
            return;
        }

        topicData = data.topic;
        populateTopicForm(topicData);
        renderItems(topicData.items || []);
    } catch (err) {
        console.error('loadTopic:', err);
    }
}

function populateTopicForm(t) {
    document.getElementById('topicName').value = t.name || '';
    if (window.AdminWysiwyg) {
        window.AdminWysiwyg.setValue('topicDescription', t.description || '');
    } else {
        document.getElementById('topicDescription').value = t.description || '';
    }
    document.getElementById('topicColor').value = t.color || '#7c3aed';
    document.getElementById('topicColorHex').value = t.color || '#7c3aed';
    document.getElementById('topicColorPreview').style.background = t.color || '#7c3aed';
    document.getElementById('topicIcon').value = t.icon || 'flag';
    document.getElementById('topicIconPreviewIcon').textContent = t.icon || 'flag';
    document.getElementById('topicImage').value = t.image || '';

    // Banner
    document.getElementById('topicBannerName').textContent = t.name || 'Thema';
    document.getElementById('topicBannerIconText').textContent = t.icon || 'flag';
    document.getElementById('topicBannerItemCount').textContent = `${(t.items || []).length} Ziel${(t.items || []).length !== 1 ? 'e' : ''}`;

    const bannerEl = document.getElementById('topicBanner');
    const bannerImg = document.getElementById('topicBannerImg');
    const bannerOverlay = document.getElementById('topicBannerOverlay');

    if (t.image) {
        const imgSrc = t.image.startsWith('http') ? t.image : '../' + t.image;
        bannerImg.src = imgSrc;
        bannerImg.classList.remove('hidden');
        bannerOverlay.style.background = `linear-gradient(to top, ${t.color || '#7c3aed'}cc, transparent)`;
    } else {
        bannerImg.classList.add('hidden');
        bannerEl.style.background = `linear-gradient(135deg, ${t.color || '#7c3aed'}, ${t.color || '#7c3aed'}dd)`;
    }

    // Image preview
    if (t.image) {
        const imgSrc = t.image.startsWith('http') ? t.image : '../' + t.image;
        showImagePreview(imgSrc);
    }

    // Render icon grid with current selection
    renderIconGrid('topicIconGrid', 'topicIcon', 'topicIconPreviewIcon', 'topicIconSearch');
    document.title = `${t.name} bearbeiten – WKC Backend`;
}

// ============================
// Render Items List
// ============================
function renderItems(items) {
    const container = document.getElementById('itemsList');
    const color = topicData?.color || '#7c3aed';

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center">
                <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">flag</span>
                <p class="text-gray-500 text-sm font-medium">Noch keine Ziele für dieses Thema.</p>
                <button onclick="openItemModal()" class="mt-3 text-primary font-bold text-sm hover:underline">+ Erstes Ziel anlegen</button>
            </div>`;
        return;
    }

    container.innerHTML = items.map((item, i) => {
        const st = String(item.status || '').trim();
        const statusBadge = st === 'erreicht'
            ? '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Erreicht</span>'
            : st === 'teils_erreicht'
            ? '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">Teils erreicht</span>'
            : '';

        return `
        <div class="item-row flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-200 group" data-item-id="${item.id}">
            <span class="material-symbols-outlined drag-handle text-gray-300 group-hover:text-gray-400 text-lg" title="Reihenfolge ändern">drag_indicator</span>
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: ${escapeHtml(color)}12">
                <span class="material-symbols-outlined text-xl" style="color: ${escapeHtml(color)}">${escapeHtml(item.icon || 'check_circle')}</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="font-bold text-gray-900 text-sm truncate">${escapeHtml(item.title)}</p>
                    ${statusBadge}
                </div>
                ${item.description ? `<p class="text-xs text-gray-500 truncate mt-0.5">${escapeHtml(item.description)}</p>` : ''}
            </div>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                <button onclick="editItem(${item.id})" class="p-2 text-gray-400 hover:text-primary transition-colors rounded-lg hover:bg-bg-light" title="Bearbeiten">
                    <span class="material-symbols-outlined text-lg">edit</span>
                </button>
                <button onclick="confirmDeleteItem(${item.id}, '${escapeHtml(item.title).replace(/'/g, "\\'")}')" class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-bg-light" title="Löschen">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            </div>
        </div>
    `}).join('');
}

// ============================
// Save Topic
// ============================
async function saveTopic() {
    const btn = document.getElementById('saveTopicBtn');
    const status = document.getElementById('saveStatus');

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Speichern...';

    const formData = new FormData();
    formData.append('id', TOPIC_ID);
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
            status.textContent = 'Gespeichert';
            status.classList.remove('text-red-500');
            status.classList.add('text-primary');
            setTimeout(() => { status.textContent = ''; }, 3000);
            loadTopic(); // Refresh banner
        } else {
            status.textContent = data.error || 'Fehler';
            status.classList.add('text-red-500');
        }
    } catch (err) {
        console.error('saveTopic:', err);
        status.textContent = 'Netzwerkfehler';
        status.classList.add('text-red-500');
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Speichern';
}

// ============================
// Icon Grid
// ============================
function initIconGrids() {
    renderIconGrid('topicIconGrid', 'topicIcon', 'topicIconPreviewIcon', 'topicIconSearch');
    renderIconGrid('itemIconGrid', 'itemIcon', 'itemIconPreviewIcon', 'itemIconSearch');
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
    // Hide upload drop zone when image is set
    const dropZone = document.getElementById('topicDropZone');
    if (dropZone) dropZone.classList.add('hidden');
}

function clearTopicImage() {
    document.getElementById('topicImagePreview').classList.add('hidden');
    document.getElementById('topicImagePreviewImg').src = '';
    document.getElementById('topicImage').value = '';
    document.getElementById('topicImageFile').value = '';
    const urlInput = document.getElementById('topicImageUrl');
    if (urlInput) urlInput.value = '';
    // Show upload drop zone again
    const dropZone = document.getElementById('topicDropZone');
    if (dropZone) dropZone.classList.remove('hidden');
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
// ITEM MODAL
// ============================
function openItemModal(itemData = null) {
    const modal = document.getElementById('itemModal');
    const title = document.getElementById('itemModalTitle');

    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('itemStatus').value = '';

    if (itemData) {
        title.textContent = 'Ziel bearbeiten';
        document.getElementById('itemId').value = itemData.id;
        document.getElementById('itemTitle').value = itemData.title || '';
        if (window.AdminWysiwyg) {
            window.AdminWysiwyg.setValue('itemDescription', itemData.description || '');
        } else {
            document.getElementById('itemDescription').value = itemData.description || '';
        }
        document.getElementById('itemIcon').value = itemData.icon || 'check_circle';
        document.getElementById('itemIconPreviewIcon').textContent = itemData.icon || 'check_circle';
        document.getElementById('itemStatus').value = itemData.status || '';
    } else {
        title.textContent = 'Neues Ziel';
        if (window.AdminWysiwyg) {
            window.AdminWysiwyg.setValue('itemDescription', '');
        }
        document.getElementById('itemIcon').value = 'check_circle';
        document.getElementById('itemIconPreviewIcon').textContent = 'check_circle';
    }

    renderIconGrid('itemIconGrid', 'itemIcon', 'itemIconPreviewIcon', 'itemIconSearch');
    document.getElementById('itemIconSearch').value = '';
    modal.classList.remove('hidden');
}

function closeItemModal() {
    document.getElementById('itemModal').classList.add('hidden');
}

async function editItem(itemId) {
    const items = topicData?.items || [];
    const item = items.find(i => i.id === itemId);
    if (item) {
        openItemModal(item);
    }
}

// Item form submit
document.getElementById('itemForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData();
    const id = document.getElementById('itemId').value;
    if (id) formData.append('id', id);
    formData.append('topic_id', TOPIC_ID);
    formData.append('title', document.getElementById('itemTitle').value);
    formData.append('description', window.AdminWysiwyg ? window.AdminWysiwyg.getValue('itemDescription') : document.getElementById('itemDescription').value);
    formData.append('icon', document.getElementById('itemIcon').value);
    formData.append('status', document.getElementById('itemStatus').value);

    try {
        const res = await fetch('../api/goals.php?action=save_item', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            closeItemModal();
            loadTopic();
        } else {
            alert(data.error || 'Fehler beim Speichern.');
        }
    } catch (err) {
        console.error('saveItem:', err);
        alert('Netzwerkfehler beim Speichern.');
    }
});

// ============================
// DELETE
// ============================
function confirmDeleteItem(id, title) {
    document.getElementById('deleteModalTitle').textContent = 'Ziel löschen?';
    document.getElementById('deleteModalText').textContent = `Möchten Sie „${title}" wirklich löschen?`;
    deleteCallback = () => deleteItem(id);
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteCallback = null;
}

document.getElementById('deleteConfirmBtn')?.addEventListener('click', () => {
    if (deleteCallback) deleteCallback();
});

async function deleteItem(id) {
    try {
        const res = await fetch(`../api/goals.php?type=item&id=${id}`, { method: 'DELETE', credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            closeDeleteModal();
            loadTopic();
        } else {
            alert(data.error || 'Fehler beim Löschen.');
        }
    } catch (err) {
        console.error('deleteItem:', err);
    }
}

// ============================
// Drag & Drop Reorder
// ============================
function initDragAndDrop() {
    const container = document.getElementById('itemsList');
    if (!container) return;

    let dragEl = null;
    let placeholder = null;

    container.addEventListener('mousedown', (e) => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;

        const row = handle.closest('.item-row');
        if (!row) return;

        e.preventDefault();
        dragEl = row;
        dragEl.style.opacity = '0.5';
        dragEl.style.zIndex = '50';

        // Create placeholder
        placeholder = document.createElement('div');
        placeholder.className = 'h-[60px] rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 transition-all';
        placeholder.style.marginBottom = '0.5rem';

        const onMouseMove = (ev) => {
            const siblings = [...container.querySelectorAll('.item-row')];
            const afterEl = getDragAfterElement(container, ev.clientY);

            if (afterEl) {
                container.insertBefore(placeholder, afterEl);
                container.insertBefore(dragEl, placeholder);
            } else {
                container.appendChild(placeholder);
                container.appendChild(dragEl);
            }
        };

        const onMouseUp = async () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.removeChild(placeholder);
            }
            placeholder = null;

            if (dragEl) {
                dragEl.style.opacity = '';
                dragEl.style.zIndex = '';
            }

            // Collect new order
            const rows = container.querySelectorAll('.item-row');
            const ids = [...rows].map(r => parseInt(r.dataset.itemId)).filter(Boolean);

            if (ids.length > 0) {
                try {
                    const res = await fetch('../api/goals.php?action=reorder_items', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids }),
                        credentials: 'include',
                    });
                    const data = await res.json();
                    if (!data.success) console.error('Reorder failed:', data.error);
                } catch (err) {
                    console.error('reorder:', err);
                }
            }

            dragEl = null;
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Touch support for mobile
    container.addEventListener('touchstart', (e) => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;

        const row = handle.closest('.item-row');
        if (!row) return;

        dragEl = row;
        dragEl.style.opacity = '0.5';

        placeholder = document.createElement('div');
        placeholder.className = 'h-[60px] rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 transition-all';
        placeholder.style.marginBottom = '0.5rem';

        const onTouchMove = (ev) => {
            ev.preventDefault();
            const touch = ev.touches[0];
            const afterEl = getDragAfterElement(container, touch.clientY);

            if (afterEl) {
                container.insertBefore(placeholder, afterEl);
                container.insertBefore(dragEl, placeholder);
            } else {
                container.appendChild(placeholder);
                container.appendChild(dragEl);
            }
        };

        const onTouchEnd = async () => {
            container.removeEventListener('touchmove', onTouchMove);
            container.removeEventListener('touchend', onTouchEnd);

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.removeChild(placeholder);
            }
            placeholder = null;

            if (dragEl) {
                dragEl.style.opacity = '';
            }

            const rows = container.querySelectorAll('.item-row');
            const ids = [...rows].map(r => parseInt(r.dataset.itemId)).filter(Boolean);

            if (ids.length > 0) {
                try {
                    const res = await fetch('../api/goals.php?action=reorder_items', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids }),
                        credentials: 'include',
                    });
                    const data = await res.json();
                    if (!data.success) console.error('Reorder failed:', data.error);
                } catch (err) {
                    console.error('reorder:', err);
                }
            }

            dragEl = null;
        };

        container.addEventListener('touchmove', onTouchMove, { passive: false });
        container.addEventListener('touchend', onTouchEnd);
    }, { passive: true });
}

function getDragAfterElement(container, y) {
    const elements = [...container.querySelectorAll('.item-row:not([style*="opacity: 0.5"])')];

    return elements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

