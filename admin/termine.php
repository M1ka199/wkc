<?php
/**
 * WKC – Termine verwalten
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

if (!$canEditContent) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termine – WKC Backend</title>
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
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="termine.php" title="Termine">
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
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['position'] ?? 'Benutzer') ?></p>
                    </div>
                    <a href="#" id="logoutBtn" class="text-gray-400 hover:text-red-500 transition-colors" title="Abmelden">
                        <span class="material-symbols-outlined">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-10 hidden lg:hidden" onclick="toggleSidebar()"></div>

        <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
            <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">Termine</h2>
                </div>
                <div class="flex items-center gap-3">
                    <a href="../" target="_blank" class="p-2 text-gray-400 hover:text-primary transition-colors rounded-lg hover:bg-bg-light flex items-center gap-2" title="Webseite anzeigen">
                        <span class="material-symbols-outlined">open_in_new</span>
                        <span class="text-sm font-medium hidden sm:inline">zur Website</span>
                    </a>
                </div>
            </header>

            <div class="p-4 lg:p-8 max-w-5xl mx-auto space-y-6">
                <!-- Header Row with Action -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl lg:text-3xl font-black text-gray-900 tracking-tight">Termine</h2>
                        <p class="text-gray-500 mt-1">Veranstaltungen und Termine verwalten.</p>
                    </div>
                    <button onclick="openEventModal()" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 whitespace-nowrap">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Neuer Termin
                    </button>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-200 flex items-center gap-4">
                        <div class="bg-primary/10 p-3 rounded-lg text-primary">
                            <span class="material-symbols-outlined text-2xl">event</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Gesamt</p>
                            <p class="text-2xl font-black text-gray-900" id="statTotal">–</p>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-200 flex items-center gap-4">
                        <div class="bg-green-500/10 p-3 rounded-lg text-green-500">
                            <span class="material-symbols-outlined text-2xl">upcoming</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Anstehend</p>
                            <p class="text-2xl font-black text-gray-900" id="statUpcoming">–</p>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-200 flex items-center gap-4">
                        <div class="bg-gray-400/10 p-3 rounded-lg text-gray-400">
                            <span class="material-symbols-outlined text-2xl">history</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Vergangen</p>
                            <p class="text-2xl font-black text-gray-900" id="statPast">–</p>
                        </div>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-bg-light/50">
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Termin</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Datum & Uhrzeit</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Ort</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="eventsTableBody" class="divide-y divide-gray-100">
                                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Laden...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Event Modal -->
    <div id="eventModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeEventModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative animate-in">
                <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-900" id="eventModalTitle">Neuer Termin</h3>
                    <button onclick="closeEventModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form id="eventForm" class="p-6 space-y-4">
                    <input type="hidden" id="eventId">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Titel *</label>
                        <input type="text" id="eventTitle" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" placeholder="z.B. Mitgliederversammlung">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Datum *</label>
                            <input type="date" id="eventDate" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Uhrzeit</label>
                            <input type="time" id="eventTime" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Ort</label>
                        <input type="text" id="eventLocation" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" placeholder="z.B. Gemeindezentrum Wulften">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Beschreibung</label>
                        <textarea id="eventDescription" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm resize-none" placeholder="Optionale Beschreibung..."></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Sichtbarkeit</label>
                            <select id="eventVisibility" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                                <option value="public">Öffentlich</option>
                                <option value="internal">Intern (nur Mitglieder)</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" id="eventShowOnHome" checked class="rounded border-gray-300 text-primary focus:ring-primary">
                                Auf Startseite anteasern
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeEventModal()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</button>
                        <button type="submit" class="flex-1 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const CAN_EDIT_CONTENT = <?= $canEditContent ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js"></script>
    <script src="js/termine.js"></script>
</body>
</html>
