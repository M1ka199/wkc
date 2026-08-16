<?php
/**
 * WKC – Einstellungen
 */
require_once __DIR__ . '/../api/config.php';
session_name(SESSION_NAME);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION;

$db = getDB();
$roleStmt = $db->prepare("SELECT role FROM users WHERE id = :id AND is_active = 1");
$roleStmt->execute([':id' => $_SESSION['user_id']]);
$currentRole = $roleStmt->fetchColumn();
if ($currentRole) {
    $_SESSION['user_role'] = $currentRole;
}

$userRole = $_SESSION['user_role'] ?? 'member';
$isAdmin = $userRole === 'admin';
$isEditor = $userRole === 'editor';
$canEditContent = $isAdmin || $isEditor;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen – WKC Backend</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#7c3aed",
                        "primary-dark": "#5b21b6",
                        "bg-light": "#f5f8f7",
                    },
                    fontFamily: {
                        display: ["Public Sans", "sans-serif"],
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: "Public Sans", sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; display: inline-block; vertical-align: middle; }
        .sidebar-link.active { background: rgba(124, 58, 237, 0.12); color: #7c3aed; font-weight: 700; }
        /* Collapsible sidebar */
        .sidebar-collapsed { width: 5rem !important; }
        .sidebar-collapsed .sidebar-label,
        .sidebar-collapsed .sidebar-user-info { display: none; }
        .sidebar-collapsed .sidebar-logo-link { display: none; }
        .sidebar-collapsed .sidebar-header { justify-content: center; }
        .sidebar-collapsed .sidebar-content { padding: 0.75rem; }
        .sidebar-collapsed .sidebar-nav a { justify-content: center; padding: 0.75rem; }
        .sidebar-collapsed .sidebar-user-footer { padding: 0.75rem; }
        .sidebar-collapsed .sidebar-user-footer > .flex { justify-content: center; gap: 0; padding: 0; }
        .sidebar-collapsed .sidebar-user-footer .flex-1,
        .sidebar-collapsed .sidebar-user-footer #logoutBtn { display: none; }
        .main-collapsed { margin-left: 5rem !important; }
    </style>
</head>
<body class="bg-bg-light text-gray-900">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-72 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20 transition-all duration-300 -translate-x-full lg:translate-x-0">
            <div class="p-6 sidebar-content">
                <!-- Logo + Collapse -->
                <div class="sidebar-header flex items-center justify-between mb-6">
                    <a href="dashboard.php" class="sidebar-logo-link">
                        <img src="../src/wkc-logo.svg" alt="WKC Logo" class="h-auto w-full max-w-[11rem]" onerror="this.style.display='none'">
                    </a>
                    <button id="collapseBtn" onclick="toggleCollapse()" class="p-1.5 rounded-lg text-gray-400 hover:bg-bg-light hover:text-gray-600 transition-colors flex-shrink-0" title="Seitenleiste einklappen">
                        <span class="material-symbols-outlined" id="collapseIcon">chevron_left</span>
                    </button>
                </div>
                <!-- Nav -->
                <nav class="sidebar-nav space-y-1">
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="dashboard.php" title="Dashboard">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span class="sidebar-label">Dashboard</span>
                    </a>
                    <?php if ($canEditContent): ?>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="beitraege.php" title="Beiträge">
                        <span class="material-symbols-outlined">article</span>
                        <span class="sidebar-label">Beiträge</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="seiten.php" title="Seiten">
                        <span class="material-symbols-outlined">web</span>
                        <span class="sidebar-label">Seiten</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="formulare.php" title="Formulare">
                        <span class="material-symbols-outlined">list_alt</span>
                        <span class="sidebar-label">Formulare</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="galerien.php" title="Galerien">
                        <span class="material-symbols-outlined">photo_library</span>
                        <span class="sidebar-label">Galerien</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="termine.php" title="Termine">
                        <span class="material-symbols-outlined">event</span>
                        <span class="sidebar-label">Termine</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="dokumente.php" title="Dokumente">
                        <span class="material-symbols-outlined">folder_open</span>
                        <span class="sidebar-label">Dokumente</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="nachrichten.php" title="Nachrichten">
                        <span class="material-symbols-outlined">mail</span>
                        <span class="sidebar-label">Nachrichten</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="mitglieder.php" title="Mitglieder">
                        <span class="material-symbols-outlined">group</span>
                        <span class="sidebar-label">Mitglieder</span>
                    </a>
                    <?php endif; ?>
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="einstellungen.php" title="Einstellungen">
                        <span class="material-symbols-outlined">settings</span>
                        <span class="sidebar-label">Einstellungen</span>
                    </a>
                </nav>
            </div>
            <!-- User footer -->
            <div class="mt-auto p-6 border-t border-gray-200 sidebar-user-footer">
                <div class="flex items-center gap-3 p-2 rounded-xl">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm flex-shrink-0">
                            <?= mb_substr($user['display_name'], 0, 1) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0 sidebar-user-info">
                        <p class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($user['display_name']) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['position'] ?? 'Benutzer') ?></p>
                    </div>
                    <a href="#" id="logoutBtn" class="text-gray-400 hover:text-red-500 transition-colors" title="Abmelden">
                        <span class="material-symbols-outlined">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-10 hidden lg:hidden" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
            <!-- Top Header -->
            <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">Einstellungen</h2>
                </div>
                <div class="flex items-center gap-3">
                    <a href="../" target="_blank" class="p-2 text-gray-400 hover:text-primary transition-colors rounded-lg hover:bg-bg-light flex items-center gap-2" title="Webseite anzeigen">
                        <span class="material-symbols-outlined">open_in_new</span>
                        <span class="text-sm font-medium hidden sm:inline">zur Website</span>
                    </a>
                </div>
            </header>

            <!-- Settings Content -->
            <div class="p-4 lg:p-8 max-w-3xl mx-auto space-y-8">

                <!-- Alert Box -->
                <div id="alertBox" class="hidden p-4 rounded-lg text-sm font-medium flex items-center gap-2"></div>

                <div class="bg-white rounded-xl border border-gray-200 p-3 shadow-sm">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold bg-primary text-white" data-main-tab="profile">Profil</button>
                        <?php if ($isAdmin): ?>
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-bg-light" data-main-tab="design">Darstellung</button>
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-bg-light" data-main-tab="menu">Menüs</button>
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-bg-light" data-main-tab="seo">SEO & Features</button>
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-bg-light" data-main-tab="integrations">Integrationen</button>
                        <button type="button" class="main-settings-tab-btn px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-bg-light" data-main-tab="smtp">E-Mail (SMTP)</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profil bearbeiten -->
                <div id="profileSettingsCard" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        <h3 class="font-bold text-lg text-gray-900">Profil bearbeiten</h3>
                    </div>
                    <form id="profileForm" class="p-6 space-y-5">
                        <!-- Profile Image -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Profilbild</label>
                            <div class="flex items-center gap-4">
                                <div id="profilePreview">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="" class="w-16 h-16 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xl">
                                            <?= mb_substr($user['display_name'], 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="profileImageInput" class="inline-flex items-center gap-2 px-4 py-2 bg-bg-light text-gray-700 rounded-lg text-sm font-medium cursor-pointer hover:bg-gray-200 transition-colors">
                                        <span class="material-symbols-outlined text-base">upload</span>
                                        Bild ändern
                                    </label>
                                    <input type="file" id="profileImageInput" accept="image/*" class="hidden">
                                    <p class="text-xs text-gray-400 mt-1">JPG, PNG oder WebP. Max. 2 MB.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Display Name -->
                            <div>
                                <label for="settingsDisplayName" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Anzeigename *</label>
                                <input type="text" id="settingsDisplayName" required class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5">
                            </div>

                            <!-- Position -->
                            <div>
                                <label for="settingsPosition" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Position</label>
                                <input type="text" id="settingsPosition" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="z.B. Vorsitzender">
                            </div>
                        </div>

                        <input type="hidden" id="settingsBio" value="">

                        <!-- Schwerpunkte -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Schwerpunkte</label>
                            <div id="settingsFocusBadges" class="flex flex-wrap gap-2 mb-2"></div>
                            <div class="flex gap-2">
                                <input type="text" id="settingsFocusInput" class="flex-1 rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2" placeholder="Neuen Schwerpunkt eingeben...">
                                <button type="button" onclick="addSettingsBadge('Focus')" class="px-3 py-2 bg-primary/10 text-primary rounded-lg text-sm font-bold hover:bg-primary/20 transition-colors">
                                    <span class="material-symbols-outlined text-base">add</span>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" id="settingsQuote" value="">

                        <!-- Mitglied seit -->
                        <div>
                            <label for="settingsMemberSince" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Mitglied seit</label>
                            <input type="date" id="settingsMemberSince" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5">
                        </div>

                        <!-- Optionale Angaben -->
                        <div class="border-t border-gray-100 pt-5">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Optionale Angaben</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="settingsAge" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Alter</label>
                                    <input type="number" id="settingsAge" min="0" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="z.B. 42">
                                </div>
                                <div>
                                    <label for="settingsFamilyStatus" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Familienstand</label>
                                    <select id="settingsFamilyStatus" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5">
                                        <option value="">– Bitte wählen –</option>
                                        <option value="ledig">Ledig</option>
                                        <option value="verheiratet">Verheiratet</option>
                                        <option value="geschieden">Geschieden</option>
                                        <option value="verwitwet">Verwitwet</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="settingsChildren" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Kinder</label>
                                    <input type="number" id="settingsChildren" min="0" max="30" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="Anzahl">
                                </div>
                                <div>
                                    <label for="settingsGrandchildren" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Enkelkinder</label>
                                    <input type="number" id="settingsGrandchildren" min="0" max="50" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="Anzahl">
                                </div>
                                <div>
                                    <label for="settingsOccupation" class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Beruf</label>
                                    <input type="text" id="settingsOccupation" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="z.B. Ingenieur">
                                </div>
                            </div>
                        </div>

                        <!-- Vereine -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Vereine</label>
                            <div id="settingsClubsBadges" class="flex flex-wrap gap-2 mb-2"></div>
                            <div class="flex gap-2">
                                <input type="text" id="settingsClubsInput" class="flex-1 rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2" placeholder="Neuen Verein eingeben...">
                                <button type="button" onclick="addSettingsBadge('Clubs')" class="px-3 py-2 bg-primary/10 text-primary rounded-lg text-sm font-bold hover:bg-primary/20 transition-colors">
                                    <span class="material-symbols-outlined text-base">add</span>
                                </button>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span>
                                Änderungen speichern
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Passwort ändern -->
                <div id="passwordSettingsCard" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary">lock</span>
                        <h3 class="font-bold text-lg text-gray-900">Passwort ändern</h3>
                    </div>
                    <form id="passwordForm" class="p-6 space-y-5">
                        <!-- Aktuelles Passwort -->
                        <div>
                            <label for="currentPassword" class="block text-sm font-bold text-gray-700 mb-1">Aktuelles Passwort *</label>
                            <input type="password" id="currentPassword" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        </div>

                        <!-- Neues Passwort -->
                        <div>
                            <label for="newPassword" class="block text-sm font-bold text-gray-700 mb-1">Neues Passwort *</label>
                            <input type="password" id="newPassword" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        </div>

                        <!-- Neues Passwort bestätigen -->
                        <div>
                            <label for="confirmPassword" class="block text-sm font-bold text-gray-700 mb-1">Neues Passwort bestätigen *</label>
                            <input type="password" id="confirmPassword" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        </div>

                        <!-- Submit -->
                        <div class="pt-2">
                            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20">Passwort ändern</button>
                        </div>
                    </form>
                </div>

                <?php if ($isAdmin): ?>
                <!-- Globale System-Einstellungen -->
                <div id="globalSettingsCard" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary">palette</span>
                        <h3 id="globalSettingsCardTitle" class="font-bold text-lg text-gray-900">Globale Einstellungen</h3>
                    </div>
                    <form id="globalSettingsForm" class="p-6 space-y-6">
                        <div class="settings-tab-pane" data-settings-pane="design">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Farbschema</h4>
                            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                                <label class="text-xs font-bold text-gray-700">Primary
                                    <input type="color" id="themePrimary" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                                <label class="text-xs font-bold text-gray-700">Secondary
                                    <input type="color" id="themeSecondary" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                                <label class="text-xs font-bold text-gray-700">Accent
                                    <input type="color" id="themeAccent" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                                <label class="text-xs font-bold text-gray-700">Background
                                    <input type="color" id="themeBackground" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                                <label class="text-xs font-bold text-gray-700">Surface
                                    <input type="color" id="themeSurface" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                                <label class="text-xs font-bold text-gray-700">Text
                                    <input type="color" id="themeText" class="mt-1 h-10 w-full rounded-lg border border-gray-200">
                                </label>
                            </div>
                        </div>

                        <div class="settings-tab-pane" data-settings-pane="design">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 mb-3">Branding</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label for="brandingSiteName" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Website-Name</label>
                                        <input type="text" id="brandingSiteName" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                    </div>
                                    <div>
                                        <label for="brandingLogoHeader" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Logo Header</label>
                                        <input type="file" id="brandingLogoHeader" accept="image/*,.svg,.ico" class="w-full text-sm">
                                    </div>
                                    <div>
                                        <label for="brandingLogoFooter" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Logo Footer</label>
                                        <input type="file" id="brandingLogoFooter" accept="image/*,.svg,.ico" class="w-full text-sm">
                                    </div>
                                    <div>
                                        <label for="brandingFavicon" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Favicon</label>
                                        <input type="file" id="brandingFavicon" accept="image/*,.svg,.ico" class="w-full text-sm">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-sm font-bold text-gray-900 mb-3">Typografie</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label for="fontHeading" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Titel-Schrift</label>
                                        <select id="fontHeading" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                            <option value="Forte">Forte</option>
                                            <option value="Luckiest Guy">Luckiest Guy</option>
                                            <option value="Public Sans">Public Sans</option>
                                            <option value="Montserrat">Montserrat</option>
                                            <option value="Bebas Neue">Bebas Neue</option>
                                            <option value="Playfair Display">Playfair Display</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fontBody" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Fließtext-Schrift</label>
                                        <select id="fontBody" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                            <option value="Forte">Forte</option>
                                            <option value="Public Sans">Public Sans</option>
                                            <option value="Roboto">Roboto</option>
                                            <option value="Lato">Lato</option>
                                            <option value="Open Sans">Open Sans</option>
                                            <option value="Merriweather">Merriweather</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="settings-tab-pane" data-settings-pane="menu">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Navigation</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-2">Hauptmenü (visuell)</label>
                                    <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                        <div id="menuMainBuilder" class="space-y-2"></div>
                                        <button type="button" id="menuMainAdd" class="mt-3 px-3 py-2 rounded-lg bg-primary/10 text-primary text-sm font-bold">Menüpunkt hinzufügen</button>
                                    </div>
                                    <textarea id="menuMainJson" rows="3" class="hidden"></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-2">Footer-Menü (visuell)</label>
                                    <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                        <div id="menuFooterBuilder" class="space-y-2"></div>
                                        <button type="button" id="menuFooterAdd" class="mt-3 px-3 py-2 rounded-lg bg-primary/10 text-primary text-sm font-bold">Footer-Link hinzufügen</button>
                                    </div>
                                    <textarea id="menuFooterJson" rows="3" class="hidden"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="settings-tab-pane" data-settings-pane="integrations">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Integrationen</h4>
                            <div class="space-y-3">
                                <div>
                                    <label for="integrationTurnstile" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Cloudflare Turnstile Site-Key</label>
                                    <input type="text" id="integrationTurnstile" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                </div>
                                <div>
                                    <label for="integrationTurnstileSecret" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Cloudflare Turnstile Secret-Key</label>
                                    <input type="password" id="integrationTurnstileSecret" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" autocomplete="off">
                                </div>
                                <div>
                                    <label for="integrationGa" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Google Analytics ID</label>
                                    <input type="text" id="integrationGa" placeholder="G-XXXXXXXXXX" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                </div>
                                <div>
                                    <label for="integrationHead" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Custom &lt;head&gt; Code</label>
                                    <textarea id="integrationHead" rows="3" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-xs font-mono"></textarea>
                                </div>
                                <div>
                                    <label for="integrationBody" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Custom &lt;body&gt; Code</label>
                                    <textarea id="integrationBody" rows="3" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-xs font-mono"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="settings-tab-pane" data-settings-pane="seo">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Globales SEO</h4>
                            <div class="space-y-3">
                                <div>
                                    <label for="seoDefaultTitle" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Standard Meta-Titel</label>
                                    <input type="text" id="seoDefaultTitle" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                </div>
                                <div>
                                    <label for="seoDefaultDescription" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Standard Meta-Beschreibung</label>
                                    <textarea id="seoDefaultDescription" rows="3" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm"></textarea>
                                </div>
                                <div>
                                    <label for="seoDefaultOgImage" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">OpenGraph Standardbild</label>
                                    <input type="text" id="seoDefaultOgImage" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="/uploads/... oder absolute URL">
                                </div>
                            </div>
                            <h4 class="text-sm font-bold text-gray-900 mt-5 mb-3">Startseite</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" id="homeHeroEnabled" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    Hero auf Startseite anzeigen
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" id="homeEventsEnabled" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    Termin-Teaser auf Startseite anzeigen
                                </label>
                            </div>
                            <h4 class="text-sm font-bold text-gray-900 mt-5 mb-3">Feature-Schalter</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" id="featurePoliticsEnabled" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    Themenbereich anzeigen
                                </label>
                            </div>
                        </div>

                        <div class="settings-tab-pane" data-settings-pane="smtp">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Globale SMTP-Konfiguration</h4>
                            <p class="text-xs text-gray-500 mb-4">Diese Einstellungen gelten systemweit für alle E-Mails. Formularspezifische SMTP-Daten überschreiben diese nur, wenn sie explizit gesetzt sind.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="smtpHost" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">SMTP Host</label>
                                    <input type="text" id="smtpHost" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="smtp.example.org">
                                </div>
                                <div>
                                    <label for="smtpPort" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">SMTP Port</label>
                                    <input type="number" id="smtpPort" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" min="1" max="65535" placeholder="587">
                                </div>
                                <div>
                                    <label for="smtpUser" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Benutzername</label>
                                    <input type="text" id="smtpUser" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" autocomplete="username">
                                </div>
                                <div>
                                    <label for="smtpPass" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Passwort</label>
                                    <input type="password" id="smtpPass" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" autocomplete="new-password">
                                </div>
                                <div>
                                    <label for="smtpSecure" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Verschlüsselung</label>
                                    <select id="smtpSecure" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="smtpFrom" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Absender-E-Mail</label>
                                    <input type="email" id="smtpFrom" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="noreply@example.org">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="smtpFromName" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Absender-Name</label>
                                    <input type="text" id="smtpFromName" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="WKC">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="smtpContactRecipient" class="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1">Kontakt-Formular Empfänger</label>
                                    <input type="email" id="smtpContactRecipient" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="kontakt@example.org">
                                    <p class="mt-1 text-xs text-gray-500">Wird für das Standard-Kontaktformular verwendet. Fallback bleibt die Server-Umgebungsvariable, falls leer.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span>
                                Globale Einstellungen speichern
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
    const CAN_EDIT_CONTENT = <?= $canEditContent ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const USER_ID = <?= $_SESSION['user_id'] ?>;
    </script>
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/einstellungen.js?v=20260816-3"></script>
</body>
</html>
