/**
 * WKC – Einstellungen JavaScript
 */

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

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

function showAlert(message, type = 'success') {
    const box = document.getElementById('alertBox');
    box.className = `p-4 rounded-lg text-sm font-medium flex items-center gap-2 ${
        type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
    }`;
    const icon = type === 'success' ? 'check_circle' : 'error';
    box.innerHTML = `<span class="material-symbols-outlined text-base">${icon}</span> ${message}`;
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => box.classList.add('hidden'), 5000);
}

function escSettings(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

const settingsBadgeData = { Focus: [], Goals: [], Clubs: [] };
let cachedGlobalSettings = null;
const menuBuilderState = { main: [], footer: [] };
const formBuilderState = { contact: [], membership: [] };

function setInputValue(id, value, fallback = '') {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? fallback;
}

function setCheckboxValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = !!value;
}

function renderSettingsBadges(type) {
    const container = document.getElementById(`settings${type}Badges`);
    if (!container) return;
    container.innerHTML = settingsBadgeData[type].map((item, i) =>
        `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-medium">${escSettings(item)}
            <button type="button" onclick="removeSettingsBadge('${type}', ${i})" class="ml-0.5 hover:text-red-500 transition-colors">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </span>`).join('');
}

function addSettingsBadge(type) {
    const input = document.getElementById(`settings${type}Input`);
    const val = input.value.trim();
    if (!val) return;
    val.split(',').map((s) => s.trim()).filter(Boolean).forEach((item) => {
        if (!settingsBadgeData[type].includes(item)) settingsBadgeData[type].push(item);
    });
    input.value = '';
    renderSettingsBadges(type);
}

function removeSettingsBadge(type, index) {
    settingsBadgeData[type].splice(index, 1);
    renderSettingsBadges(type);
}

['Focus', 'Goals', 'Clubs'].forEach((type) => {
    const input = document.getElementById(`settings${type}Input`);
    if (input) input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSettingsBadge(type);
        }
    });
});

function activateSettingsTab(tab) {
    document.querySelectorAll('.settings-tab-btn').forEach((btn) => {
        const active = btn.dataset.settingsTab === tab;
        btn.classList.toggle('bg-primary', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('text-gray-600', !active);
    });
    document.querySelectorAll('.settings-tab-pane').forEach((pane) => {
        pane.classList.toggle('hidden', pane.dataset.settingsPane !== tab);
    });
}

function activateMainSettingsTab(tab) {
    const profileCard = document.getElementById('profileSettingsCard');
    const passwordCard = document.getElementById('passwordSettingsCard');
    const globalCard = document.getElementById('globalSettingsCard');
    const globalTitle = document.getElementById('globalSettingsCardTitle');

    document.querySelectorAll('.main-settings-tab-btn').forEach((btn) => {
        const active = btn.dataset.mainTab === tab;
        btn.classList.toggle('bg-primary', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('text-gray-600', !active);
    });

    const isProfile = tab === 'profile';
    if (profileCard) profileCard.classList.toggle('hidden', !isProfile);
    if (passwordCard) passwordCard.classList.toggle('hidden', !isProfile);
    if (globalCard) globalCard.classList.toggle('hidden', isProfile);

    if (!isProfile) {
        activateSettingsTab(tab);
        if (globalTitle) {
            const labels = {
                design: 'Darstellung',
                menu: 'Menüs',
                seo: 'SEO & Features',
                integrations: 'Integrationen',
                smtp: 'E-Mail (SMTP)',
            };
            globalTitle.textContent = labels[tab] || 'Globale Einstellungen';
        }
    }
}

function renderMenuBuilder(group) {
    const container = document.getElementById(group === 'main' ? 'menuMainBuilder' : 'menuFooterBuilder');
    if (!container) return;
    const list = menuBuilderState[group] || [];

    const renderLevel = (items, path = []) => items.map((item, idx) => {
        const currentPath = [...path, idx].join('.');
        const children = Array.isArray(item.children) ? item.children : [];
        return `
            <div class="menu-node space-y-2" data-menu-path="${currentPath}" data-menu-group="${group}">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <input class="col-span-5 menu-label rounded-lg border-gray-200 text-sm" value="${escSettings(item.label || '')}" placeholder="Label">
                    <input class="col-span-5 menu-url rounded-lg border-gray-200 text-sm" value="${escSettings(item.url || '')}" placeholder="/pfad oder #anker">
                    <button type="button" class="col-span-1 menu-add-child p-1.5 rounded hover:bg-bg-light text-gray-600" title="Unterpunkt hinzufügen"><span class="material-symbols-outlined text-base">subdirectory_arrow_right</span></button>
                    <button type="button" class="col-span-1 menu-remove p-1.5 rounded hover:bg-red-50 text-red-600" title="Eintrag löschen"><span class="material-symbols-outlined text-base">delete</span></button>
                </div>
                <div class="menu-children ml-5 border-l border-gray-200 pl-3 space-y-2">${children.length ? renderLevel(children, [...path, idx]) : ''}</div>
            </div>
        `;
    }).join('');

    container.innerHTML = list.length ? renderLevel(list) : '<p class="text-sm text-gray-400">Noch keine Einträge.</p>';
}

function syncMenuStateFromDom(group) {
    const container = document.getElementById(group === 'main' ? 'menuMainBuilder' : 'menuFooterBuilder');
    if (!container) return;

    const parseNodes = (parent) => {
        const nodes = Array.from(parent.children).filter((el) => el.classList.contains('menu-node'));
        return nodes.map((node) => {
            const label = node.querySelector(':scope > .grid .menu-label')?.value?.trim() || '';
            const url = node.querySelector(':scope > .grid .menu-url')?.value?.trim() || '#';
            const childContainer = node.querySelector(':scope > .menu-children');
            const children = childContainer ? parseNodes(childContainer) : [];
            const item = { label, url };
            if (children.length) item.children = children;
            return item;
        }).filter((entry) => entry.label !== '');
    };

    menuBuilderState[group] = parseNodes(container);
    setInputValue(group === 'main' ? 'menuMainJson' : 'menuFooterJson', JSON.stringify(menuBuilderState[group], null, 2));
}

function addMenuRow(group) {
    menuBuilderState[group].push({ label: 'Neuer Eintrag', url: '#' });
    renderMenuBuilder(group);
}

function getMenuNodeByPath(group, path) {
    let target = menuBuilderState[group];
    for (let i = 0; i < path.length; i += 1) {
        const idx = path[i];
        if (!Array.isArray(target) || !target[idx]) return null;
        if (i === path.length - 1) return target[idx];
        if (!Array.isArray(target[idx].children)) target[idx].children = [];
        target = target[idx].children;
    }
    return null;
}

function removeMenuNodeByPath(group, path) {
    if (!path.length) return;
    if (path.length === 1) {
        menuBuilderState[group].splice(path[0], 1);
        return;
    }
    const parent = getMenuNodeByPath(group, path.slice(0, -1));
    if (!parent || !Array.isArray(parent.children)) return;
    parent.children.splice(path[path.length - 1], 1);
    if (!parent.children.length) delete parent.children;
}

function renderFormBuilder(type) {
    const container = document.getElementById(type === 'contact' ? 'contactFieldsBuilder' : 'membershipFieldsBuilder');
    if (!container) return;
    const rows = formBuilderState[type] || [];
    container.innerHTML = rows.map((field, idx) => `
        <div class="grid grid-cols-12 gap-2 items-center" data-field-type="${type}" data-field-index="${idx}">
            <input class="col-span-4 field-name rounded-lg border-gray-200 text-sm" value="${escSettings(field.name || '')}" placeholder="name">
            <input class="col-span-5 field-label rounded-lg border-gray-200 text-sm" value="${escSettings(field.label || '')}" placeholder="Label">
            <label class="col-span-2 inline-flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" class="field-required rounded border-gray-300" ${field.required ? 'checked' : ''}>Pflicht</label>
            <button type="button" class="col-span-1 field-remove p-1.5 rounded hover:bg-red-50 text-red-600"><span class="material-symbols-outlined text-base">delete</span></button>
        </div>
    `).join('') || '<p class="text-sm text-gray-400">Keine Felder vorhanden.</p>';
}

function syncFormStateFromDom(type) {
    const container = document.getElementById(type === 'contact' ? 'contactFieldsBuilder' : 'membershipFieldsBuilder');
    if (!container) return;
    const rows = Array.from(container.querySelectorAll('[data-field-type]'));
    formBuilderState[type] = rows.map((row) => ({
        name: row.querySelector('.field-name')?.value?.trim(),
        label: row.querySelector('.field-label')?.value?.trim(),
        required: !!row.querySelector('.field-required')?.checked,
    })).filter((f) => f.name && f.label);
}

async function loadProfile() {
    try {
        const res = await fetch(`../api/members.php?action=profile&id=${USER_ID}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.member) return;

        const m = data.member;
        setInputValue('settingsDisplayName', m.display_name, '');
        setInputValue('settingsPosition', m.position, '');
        setInputValue('settingsBio', m.bio, '');
        setInputValue('settingsQuote', m.quote, '');
        setInputValue('settingsMemberSince', m.member_since, '');
        setInputValue('settingsAge', m.age, '');
        setInputValue('settingsFamilyStatus', m.family_status, '');
        setInputValue('settingsChildren', m.children, '');
        setInputValue('settingsGrandchildren', m.grandchildren, '');
        setInputValue('settingsOccupation', m.occupation, '');

        settingsBadgeData.Focus = Array.isArray(m.focus_areas) ? [...m.focus_areas] : [];
        settingsBadgeData.Goals = Array.isArray(m.personal_goals) ? [...m.personal_goals] : [];
        settingsBadgeData.Clubs = Array.isArray(m.clubs) ? [...m.clubs] : [];
        renderSettingsBadges('Focus');
        renderSettingsBadges('Goals');
        renderSettingsBadges('Clubs');

        if (m.profile_image) {
            document.getElementById('profilePreview').innerHTML = `<img src="../${m.profile_image}" alt="" class="w-16 h-16 rounded-full object-cover">`;
        }
    } catch (err) {
        console.error('loadProfile:', err);
    }
}

document.getElementById('profileImageInput').addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('profilePreview').innerHTML = `<img src="${e.target.result}" alt="" class="w-16 h-16 rounded-full object-cover">`;
    };
    reader.readAsDataURL(this.files[0]);
});

document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('id', USER_ID);
    formData.append('display_name', document.getElementById('settingsDisplayName').value);
    formData.append('position', document.getElementById('settingsPosition').value);
    formData.append('bio', document.getElementById('settingsBio').value);
    formData.append('quote', document.getElementById('settingsQuote').value);
    formData.append('member_since', document.getElementById('settingsMemberSince').value);
    formData.append('age', document.getElementById('settingsAge').value);
    formData.append('family_status', document.getElementById('settingsFamilyStatus').value);
    formData.append('children', document.getElementById('settingsChildren').value);
    formData.append('grandchildren', document.getElementById('settingsGrandchildren').value);
    formData.append('occupation', document.getElementById('settingsOccupation').value);
    formData.append('personal_goals', JSON.stringify(settingsBadgeData.Goals));
    formData.append('focus_areas', JSON.stringify(settingsBadgeData.Focus));
    formData.append('clubs', JSON.stringify(settingsBadgeData.Clubs));

    const imgFile = document.getElementById('profileImageInput').files[0];
    if (imgFile) formData.append('profile_image', imgFile);

    try {
        const res = await fetch('../api/members.php?action=self_update', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            showAlert('Profil erfolgreich gespeichert.', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.error || 'Fehler beim Speichern.', 'error');
        }
    } catch (err) {
        console.error('saveProfile:', err);
        showAlert('Netzwerkfehler beim Speichern.', 'error');
    }
});

document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const current = document.getElementById('currentPassword').value;
    const newPw = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    if (newPw !== confirm) return showAlert('Die neuen Passwörter stimmen nicht überein.', 'error');
    if (newPw.length < 8) return showAlert('Das neue Passwort muss mindestens 8 Zeichen lang sein.', 'error');

    const formData = new FormData();
    formData.append('current_password', current);
    formData.append('new_password', newPw);
    formData.append('confirm_password', confirm);
    try {
        const res = await fetch('../api/auth.php?action=password', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            showAlert('Passwort erfolgreich geändert.', 'success');
            document.getElementById('passwordForm').reset();
        } else {
            showAlert(data.error || 'Fehler beim Ändern des Passworts.', 'error');
        }
    } catch (err) {
        console.error('changePassword:', err);
        showAlert('Netzwerkfehler beim Ändern des Passworts.', 'error');
    }
});

async function loadGlobalSettings() {
    const form = document.getElementById('globalSettingsForm');
    if (!form) return;
    try {
        const res = await fetch('../api/settings.php?scope=admin', { credentials: 'include' });
        const data = await res.json();
        const settings = data.settings || {};
        cachedGlobalSettings = settings;

        const theme = settings.theme || {};
        setInputValue('themePrimary', theme.primary, '#7c3aed');
        setInputValue('themeSecondary', theme.secondary, '#5b21b6');
        setInputValue('themeAccent', theme.accent, '#f59e0b');
        setInputValue('themeBackground', theme.background, '#f8faf9');
        setInputValue('themeSurface', theme.surface, '#ffffff');
        setInputValue('themeText', theme.text, '#1f2937');

        const branding = settings.branding || {};
        setInputValue('brandingSiteName', branding.siteName, '');

        const typography = settings.typography || {};
        setInputValue('fontHeading', typography.headingFont, 'Luckiest Guy');
        setInputValue('fontBody', typography.bodyFont, 'Public Sans');

        const menu = settings.menu || {};
        menuBuilderState.main = Array.isArray(menu.main) ? [...menu.main] : [];
        menuBuilderState.footer = Array.isArray(menu.footer) ? [...menu.footer] : [];
        renderMenuBuilder('main');
        renderMenuBuilder('footer');
        setInputValue('menuMainJson', JSON.stringify(menuBuilderState.main, null, 2));
        setInputValue('menuFooterJson', JSON.stringify(menuBuilderState.footer, null, 2));

        const homepage = settings.homepage || {};
        setCheckboxValue('homeHeroEnabled', homepage.heroEnabled);
        setCheckboxValue('homeEventsEnabled', homepage.eventsEnabled);

        const integrations = settings.integrations || {};
        setInputValue('integrationTurnstile', integrations.cloudflareTurnstileSiteKey, '');
        setInputValue('integrationTurnstileSecret', integrations.cloudflareTurnstileSecret, '');
        setInputValue('integrationGa', integrations.googleAnalyticsId, '');
        setInputValue('integrationHead', integrations.customHeadCode, '');
        setInputValue('integrationBody', integrations.customBodyCode, '');

        const seo = settings.seo || {};
        setInputValue('seoDefaultTitle', seo.defaultMetaTitle, '');
        setInputValue('seoDefaultDescription', seo.defaultMetaDescription, '');
        setInputValue('seoDefaultOgImage', seo.defaultOgImage, '');

        const smtp = settings.smtp || {};
        setInputValue('smtpHost', smtp.host, '');
        setInputValue('smtpPort', smtp.port, '');
        setInputValue('smtpUser', smtp.user, '');
        setInputValue('smtpPass', smtp.pass, '');
        setInputValue('smtpFrom', smtp.from, '');
        setInputValue('smtpFromName', smtp.from_name, '');
        setInputValue('smtpContactRecipient', smtp.contact_recipient, '');
        setInputValue('smtpSecure', smtp.secure, 'tls');

        const features = settings.features || {};
        setCheckboxValue('featurePoliticsEnabled', features.politicsEnabled);
    } catch (err) {
        console.error('loadGlobalSettings:', err);
        showAlert('Globale Einstellungen konnten nicht geladen werden.', 'error');
    }
}

const globalSettingsForm = document.getElementById('globalSettingsForm');
if (globalSettingsForm) {
    globalSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            syncMenuStateFromDom('main');
            syncMenuStateFromDom('footer');

            const homeHeroToggle = document.getElementById('homeHeroEnabled');
            const homeEventsToggle = document.getElementById('homeEventsEnabled');
            const featurePoliticsToggle = document.getElementById('featurePoliticsEnabled');

            const settingsPayload = {
                theme: {
                    primary: document.getElementById('themePrimary').value,
                    secondary: document.getElementById('themeSecondary').value,
                    accent: document.getElementById('themeAccent').value,
                    background: document.getElementById('themeBackground').value,
                    surface: document.getElementById('themeSurface').value,
                    text: document.getElementById('themeText').value,
                },
                branding: {
                    ...(cachedGlobalSettings?.branding || {}),
                    siteName: document.getElementById('brandingSiteName').value.trim(),
                },
                typography: {
                    headingFont: document.getElementById('fontHeading').value,
                    bodyFont: document.getElementById('fontBody').value,
                },
                menu: {
                    main: menuBuilderState.main,
                    footer: menuBuilderState.footer,
                },
                integrations: {
                    cloudflareTurnstileSiteKey: document.getElementById('integrationTurnstile').value.trim(),
                    cloudflareTurnstileSecret: document.getElementById('integrationTurnstileSecret').value.trim(),
                    googleAnalyticsId: document.getElementById('integrationGa').value.trim(),
                    customHeadCode: document.getElementById('integrationHead').value,
                    customBodyCode: document.getElementById('integrationBody').value,
                },
                homepage: {
                    ...(cachedGlobalSettings?.homepage || {}),
                    heroEnabled: homeHeroToggle ? homeHeroToggle.checked : !!cachedGlobalSettings?.homepage?.heroEnabled,
                    eventsEnabled: homeEventsToggle ? homeEventsToggle.checked : !!cachedGlobalSettings?.homepage?.eventsEnabled,
                },
                seo: {
                    ...(cachedGlobalSettings?.seo || {}),
                    defaultMetaTitle: document.getElementById('seoDefaultTitle').value.trim(),
                    defaultMetaDescription: document.getElementById('seoDefaultDescription').value.trim(),
                    defaultOgImage: document.getElementById('seoDefaultOgImage').value.trim(),
                },
                smtp: {
                    host: document.getElementById('smtpHost').value.trim(),
                    port: document.getElementById('smtpPort').value.trim(),
                    user: document.getElementById('smtpUser').value.trim(),
                    pass: document.getElementById('smtpPass').value,
                    from: document.getElementById('smtpFrom').value.trim(),
                    from_name: document.getElementById('smtpFromName').value.trim(),
                    contact_recipient: document.getElementById('smtpContactRecipient').value.trim(),
                    secure: document.getElementById('smtpSecure').value || 'tls',
                },
                features: {
                    ...(cachedGlobalSettings?.features || {}),
                    politicsEnabled: featurePoliticsToggle ? featurePoliticsToggle.checked : !!cachedGlobalSettings?.features?.politicsEnabled,
                },
            };

            const formData = new FormData();
            formData.append('settings', JSON.stringify(settingsPayload));
            const logoHeader = document.getElementById('brandingLogoHeader')?.files?.[0];
            const logoFooter = document.getElementById('brandingLogoFooter')?.files?.[0];
            const favicon = document.getElementById('brandingFavicon')?.files?.[0];
            if (logoHeader) formData.append('logo_header', logoHeader);
            if (logoFooter) formData.append('logo_footer', logoFooter);
            if (favicon) formData.append('favicon', favicon);

            const res = await fetch('../api/settings.php', {
                method: 'POST',
                body: formData,
                credentials: 'include',
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Speichern fehlgeschlagen.');

            cachedGlobalSettings = data.settings || settingsPayload;
            showAlert('Globale Einstellungen wurden gespeichert.', 'success');
        } catch (err) {
            console.error('saveGlobalSettings:', err);
            showAlert(err.message || 'Globale Einstellungen konnten nicht gespeichert werden.', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadProfile();
    loadGlobalSettings();
    activateMainSettingsTab('profile');
    activateSettingsTab('design');

    document.querySelectorAll('.main-settings-tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => activateMainSettingsTab(btn.dataset.mainTab));
    });
    document.querySelectorAll('.settings-tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => activateSettingsTab(btn.dataset.settingsTab));
    });

    document.getElementById('menuMainAdd')?.addEventListener('click', () => {
        addMenuRow('main');
        syncMenuStateFromDom('main');
    });
    document.getElementById('menuFooterAdd')?.addEventListener('click', () => {
        addMenuRow('footer');
        syncMenuStateFromDom('footer');
    });
    document.getElementById('contactFieldAdd')?.addEventListener('click', () => {
        formBuilderState.contact.push({ name: '', label: '', required: false });
        renderFormBuilder('contact');
    });
    document.getElementById('membershipFieldAdd')?.addEventListener('click', () => {
        formBuilderState.membership.push({ name: '', label: '', required: false });
        renderFormBuilder('membership');
    });

    document.addEventListener('click', (e) => {
        const menuRemove = e.target.closest('.menu-remove');
        const menuAddChild = e.target.closest('.menu-add-child');

        if (menuAddChild) {
            const node = menuAddChild.closest('[data-menu-path]');
            const group = node?.dataset.menuGroup;
            const path = (node?.dataset.menuPath || '').split('.').filter(Boolean).map(Number);
            if (group && path.length) {
                const targetNode = getMenuNodeByPath(group, path);
                if (targetNode) {
                    if (!Array.isArray(targetNode.children)) targetNode.children = [];
                    targetNode.children.push({ label: 'Unterpunkt', url: '#' });
                    renderMenuBuilder(group);
                    syncMenuStateFromDom(group);
                }
            }
        }

        if (menuRemove) {
            const node = menuRemove.closest('[data-menu-path]');
            const group = node?.dataset.menuGroup;
            const path = (node?.dataset.menuPath || '').split('.').filter(Boolean).map(Number);
            if (group && path.length) {
                removeMenuNodeByPath(group, path);
                renderMenuBuilder(group);
                syncMenuStateFromDom(group);
            }
        }

        const fieldRemove = e.target.closest('.field-remove');
        if (fieldRemove) {
            const row = fieldRemove.closest('[data-field-type]');
            const type = row?.dataset.fieldType;
            const idx = Number(row?.dataset.fieldIndex || -1);
            if (type && idx >= 0) {
                formBuilderState[type].splice(idx, 1);
                renderFormBuilder(type);
            }
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.closest('#menuMainBuilder')) syncMenuStateFromDom('main');
        if (e.target.closest('#menuFooterBuilder')) syncMenuStateFromDom('footer');
    });
});
