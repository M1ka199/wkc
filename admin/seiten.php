<?php
require_once __DIR__ . '/../api/config.php';
session_name(SESSION_NAME);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$roleStmt = $db->prepare("SELECT role FROM users WHERE id = :id AND is_active = 1");
$roleStmt->execute([':id' => $_SESSION['user_id']]);
$currentRole = $roleStmt->fetchColumn();
if ($currentRole) $_SESSION['user_role'] = $currentRole;

$userRole = $_SESSION['user_role'] ?? 'member';
$isAdmin = $userRole === 'admin';
$isEditor = $userRole === 'editor';
$canEditContent = $isAdmin || $isEditor;
$user = $_SESSION;

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
    <title>Seiten – CMS Backend</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: "#7c3aed", "primary-dark": "#5b21b6", "bg-light": "#f5f8f7" } } } };
    </script>
    <style>
        body { font-family: "Public Sans", sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
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
    <aside id="sidebar" class="w-72 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20 transition-all duration-300 -translate-x-full lg:translate-x-0">
        <div class="p-6 sidebar-content">
            <div class="sidebar-header flex items-center justify-between mb-6">
                <a href="dashboard.php" class="sidebar-logo-link">
                    <img src="../src/wkc-logo.svg" alt="Logo" class="h-auto w-full max-w-[10rem]" data-admin-brand-logo="header">
                </a>
                <button id="collapseBtn" onclick="toggleCollapse()" class="p-1.5 rounded-lg text-gray-400 hover:bg-bg-light hover:text-gray-600 transition-colors flex-shrink-0" title="Seitenleiste einklappen">
                    <span class="material-symbols-outlined" id="collapseIcon">chevron_left</span>
                </button>
            </div>
            <nav class="sidebar-nav space-y-1">
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span><span class="sidebar-label">Dashboard</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="beitraege.php"><span class="material-symbols-outlined">article</span><span class="sidebar-label">Beiträge</span></a>
                <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg" href="seiten.php"><span class="material-symbols-outlined">web</span><span class="sidebar-label">Seiten</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="formulare.php"><span class="material-symbols-outlined">list_alt</span><span class="sidebar-label">Formulare</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="galerien.php"><span class="material-symbols-outlined">photo_library</span><span class="sidebar-label">Galerien</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="termine.php"><span class="material-symbols-outlined">event</span><span class="sidebar-label">Termine</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="dokumente.php"><span class="material-symbols-outlined">folder_open</span><span class="sidebar-label">Dokumente</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="nachrichten.php"><span class="material-symbols-outlined">mail</span><span class="sidebar-label">Nachrichten</span></a>
                <?php if ($isAdmin): ?>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="mitglieder.php"><span class="material-symbols-outlined">group</span><span class="sidebar-label">Mitglieder</span></a>
                <?php endif; ?>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="einstellungen.php"><span class="material-symbols-outlined">settings</span><span class="sidebar-label">Einstellungen</span></a>
            </nav>
        </div>
        <div class="mt-auto p-6 border-t border-gray-200 sidebar-user-footer">
            <div class="flex items-center gap-3 p-2 rounded-xl">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm flex-shrink-0">
                        <?= mb_substr($user['display_name'] ?? 'U', 0, 1) ?>
                    </div>
                <?php endif; ?>
                <div class="flex-1 min-w-0 sidebar-user-info">
                    <p class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($user['display_name'] ?? 'Benutzer') ?></p>
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
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg"><span class="material-symbols-outlined">menu</span></button>
                <h2 class="text-lg font-bold text-gray-900">Seitenverwaltung</h2>
            </div>
            <a href="../" target="_blank" class="p-2 text-gray-400 hover:text-primary rounded-lg hover:bg-bg-light"><span class="material-symbols-outlined">open_in_new</span></a>
        </header>

        <div class="p-4 lg:p-8 max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-black">Seiten</h1>
                    <p class="text-gray-500">Zentrale Verwaltung mit vollwertigem Editor, SEO-Feldern und Startseiten-Bausteinen.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="seiten-editor.php?home=1" class="px-4 py-2.5 rounded-lg text-sm font-bold border border-primary text-primary flex items-center gap-2"><span class="material-symbols-outlined text-sm">home</span>Startseite bearbeiten</a>
                    <a href="seiten-editor.php" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined text-sm">add</span>Neue Seite</a>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="bg-bg-light/60">
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Titel</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Pfad</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Formulare</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Geändert</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="pagesTableBody" class="divide-y divide-gray-100">
                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="js/admin-theme.js?v=20260816-3"></script>
<script src="js/shared.js?v=20260816-3"></script>
<script src="js/seiten.js?v=20260816-2"></script>
</body>
</html>
