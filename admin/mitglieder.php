<?php
/**
 * WKC – Member Management (Admin)
 * List, create, edit, deactivate board members / users.
 */
require_once __DIR__ . '/../api/config.php';
session_name(SESSION_NAME);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
// Ensure role is current from database
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

// Only admins can manage members
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}
$user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitglieder verwalten – WKC</title>
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
        <!-- Sidebar (same as dashboard) -->
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
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="mitglieder.php" title="Mitglieder">
                        <span class="material-symbols-outlined">group</span>
                        <span class="sidebar-label">Mitglieder</span>
                    </a>
                    <?php endif; ?>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="einstellungen.php" title="Einstellungen">
                        <span class="material-symbols-outlined">settings</span>
                        <span class="sidebar-label">Einstellungen</span>
                    </a>
                </nav>
            </div>
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
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['position'] ?? '') ?></p>
                    </div>
                    <a href="#" id="logoutBtn" class="text-gray-400 hover:text-red-500 transition-colors" title="Abmelden">
                        <span class="material-symbols-outlined">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-10 hidden lg:hidden" onclick="toggleSidebar()"></div>

        <!-- Main -->
        <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
            <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">Mitglieder verwalten</h2>
                </div>
                <div class="flex items-center gap-3">
                    <a href="../index.html" target="_blank" class="p-2 text-gray-400 hover:text-primary transition-colors rounded-lg hover:bg-bg-light flex items-center gap-2" title="Webseite anzeigen">
                        <span class="material-symbols-outlined">open_in_new</span>
                        <span class="text-sm font-medium hidden sm:inline">zur Website</span>
                    </a>
                </div>
            </header>

            <div class="p-4 lg:p-8 max-w-[90rem] mx-auto space-y-6">
                <!-- Alert -->
                <div id="alertBox" class="hidden p-4 rounded-lg text-sm font-medium flex items-center gap-2"></div>

                <!-- Search Bar + Action -->
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="flex items-center gap-4 flex-1">
                        <div class="relative flex-1 max-w-md">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                            <input id="memberSearch" type="text" placeholder="Mitglieder durchsuchen..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary text-sm placeholder:text-gray-400">
                        </div>
                        <p id="memberCount" class="text-sm text-gray-500 whitespace-nowrap"></p>
                    </div>
                    <button id="btnAddMember" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 whitespace-nowrap">
                        <span class="material-symbols-outlined text-sm">person_add</span>
                        Neues Mitglied
                    </button>
                </div>

                <!-- Members List -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-bg-light/50">
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Reihenfolge</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Mitglied</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Rolle</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Position</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Vorstand</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Mitglied seit</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="membersList" class="divide-y divide-gray-100">
                                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">Laden...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================
         Member Edit/Create Modal
         ============================ -->
    <div id="memberModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
        <div class="absolute inset-4 md:inset-8 lg:inset-y-8 lg:left-1/2 lg:-translate-x-1/2 lg:max-w-3xl lg:w-full bg-white shadow-2xl rounded-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
                <h3 id="modalTitle" class="text-lg font-bold text-gray-900">Neues Mitglied</h3>
                <button onclick="closeModal()" class="p-2 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-bg-light">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form id="memberForm" class="p-6 space-y-5">
                <input type="hidden" id="memberId" value="">

                <!-- Profile Image -->
                <div>
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Profilbild</label>
                    <div class="flex items-center gap-4">
                        <div id="memberImagePreview" class="w-16 h-16 rounded-full bg-bg-light border-2 border-dashed border-gray-200 flex items-center justify-center text-gray-400 overflow-hidden cursor-pointer" onclick="document.getElementById('memberImageInput').click()">
                            <span class="material-symbols-outlined text-2xl" id="memberImageIcon">add_a_photo</span>
                            <img id="memberImg" src="" alt="" class="hidden w-full h-full object-cover">
                        </div>
                        <input type="file" id="memberImageInput" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <div>
                            <p class="text-sm text-gray-500">JPG, PNG oder WebP</p>
                            <p class="text-xs text-gray-400">Max. 5 MB</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Benutzername</label>
                        <input id="memberUsername" type="text" required class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="benutzername">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Anzeigename</label>
                        <input id="memberDisplayName" type="text" required class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="Max Mustermann">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">E-Mail</label>
                    <input id="memberEmail" type="email" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="max@beispiel.de">
                    <p class="text-xs text-gray-400 mt-1">Für Einladungen und Passwort-Zurücksetzung erforderlich.</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Passwort <span id="pwHint" class="text-gray-400 normal-case font-normal">(min. 8 Zeichen)</span></label>
                    <input id="memberPassword" type="password" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="••••••••">
                </div>

                <!-- Invitation toggle (only for new members) -->
                <div id="invitationToggle" class="flex items-center gap-3 p-4 bg-primary/5 rounded-xl border border-primary/10">
                    <input type="checkbox" id="memberSendInvitation" class="h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary cursor-pointer">
                    <label for="memberSendInvitation" class="text-sm text-gray-700 cursor-pointer select-none">
                        <span class="font-semibold text-gray-900">Einladung per E-Mail senden</span> – Mitglied setzt Passwort selbst.
                    </label>
                </div>

                <!-- Send password link (always visible in edit mode) -->
                <div id="passwordLinkSection" class="hidden p-4 bg-blue-50/60 rounded-xl border border-blue-100">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-blue-500 mt-0.5">forward_to_inbox</span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900">Passwort-Link senden</p>
                            <p class="text-xs text-gray-500 mt-0.5">Sendet eine E-Mail mit einem Link zum Erstellen oder Zurücksetzen des Passworts.</p>
                            <button type="button" id="btnSendPasswordLink" class="mt-3 px-4 py-2 rounded-lg border-2 border-primary text-primary font-bold text-sm hover:bg-primary hover:text-white transition-all flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">send</span>
                                <span id="btnSendPasswordLinkLabel">Passwort-Link senden</span>
                            </button>
                            <p id="passwordLinkStatus" class="text-xs text-gray-400 mt-2 hidden"></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Benutzerrolle</label>
                    <select id="memberRole" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5">
                        <option value="member">Mitglied – Nur Dashboard mit Terminen & Downloads</option>
                        <option value="editor">Redakteur – Kann Beiträge verwalten</option>
                        <option value="admin">Administrator – Vollzugriff</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Bestimmt die Berechtigungen im Backend.</p>
                </div>

                <div class="flex items-center gap-3 p-4 bg-bg-light rounded-xl">
                    <input type="checkbox" id="memberIsBoardMember" class="h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary cursor-pointer">
                    <label for="memberIsBoardMember" class="text-sm text-gray-700 cursor-pointer select-none">
                        <span class="font-semibold text-gray-900">Vorstandsmitglied</span> – Wird auf der Startseite angezeigt.
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Position / Amt</label>
                        <input id="memberPosition" type="text" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="z.B. 1. Vorsitzender">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Reihenfolge</label>
                        <input id="memberOrder" type="number" min="1" max="99" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" value="99">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-1">Mitglied seit</label>
                    <input id="memberSince" type="text" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2.5" placeholder="z.B. 2019">
                </div>

                <input type="hidden" id="memberBio" value="">

                <input type="hidden" id="memberQuote" value="">

                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeModal()" class="flex-1 py-3 rounded-lg border border-gray-200 text-gray-700 font-bold text-sm hover:bg-bg-light transition-colors">
                        Abbrechen
                    </button>
                    <button type="submit" class="flex-1 py-3 rounded-lg bg-primary text-white font-bold text-sm hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg">save</span>
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js"></script>
    <script src="js/mitglieder.js"></script>
</body>
</html>
