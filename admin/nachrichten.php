<?php
/**
 * WKC – Admin Nachrichten
 * Zeigt Kontaktanfragen, Beitrittsanfragen und Nachrichten an.
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
    <title>Nachrichten – WKC Backend</title>
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
        .tab-active { border-color: #7c3aed; color: #7c3aed; font-weight: 700; }
        .tab-inactive { border-color: transparent; color: #6b7280; }
        .tab-inactive:hover { color: #374151; border-color: #d1d5db; }
        .message-unread { background: #f0fdf4; border-left: 3px solid #7c3aed; }
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
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="termine.php" title="Termine">
                        <span class="material-symbols-outlined">event</span>
                        <span class="sidebar-label">Termine</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="dokumente.php" title="Dokumente">
                        <span class="material-symbols-outlined">folder_open</span>
                        <span class="sidebar-label">Dokumente</span>
                    </a>
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="nachrichten.php" title="Nachrichten">
                        <span class="material-symbols-outlined">mail</span>
                        <span class="sidebar-label">Nachrichten</span>
                        <span id="sidebarBadge" class="sidebar-label ml-auto bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
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

        <!-- Main Content -->
        <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
            <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">Nachrichten</h2>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="markAllRead()" class="text-sm text-gray-500 hover:text-primary font-medium transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg">done_all</span>
                        <span class="hidden sm:inline">Alle gelesen</span>
                    </button>
                </div>
            </header>

            <div class="p-4 lg:p-8 max-w-5xl mx-auto space-y-6">
                <div>
                    <h2 class="text-2xl lg:text-3xl font-black text-gray-900 tracking-tight">Nachrichten</h2>
                    <p class="text-gray-500 mt-1">Kontaktanfragen, Beitrittsanfragen und Nachrichten verwalten.</p>
                </div>

                <!-- Alert -->
                <div id="alertBox" class="hidden"></div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex gap-6" role="tablist">
                        <button class="tab-btn tab-active pb-3 border-b-2 text-sm font-medium transition-colors flex items-center" data-tab="all" role="tab">
                            Alle
                            <span id="countAll" class="ml-1.5 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">0</span>
                        </button>
                        <button class="tab-btn tab-inactive pb-3 border-b-2 text-sm font-medium transition-colors flex items-center" data-tab="contact" role="tab">
                            <span class="material-symbols-outlined text-base mr-1">chat</span>
                            Kontakt
                            <span id="countContact" class="ml-1.5 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">0</span>
                        </button>
                        <button class="tab-btn tab-inactive pb-3 border-b-2 text-sm font-medium transition-colors flex items-center" data-tab="membership" role="tab">
                            <span class="material-symbols-outlined text-base mr-1">person_add</span>
                            Beitritt
                            <span id="countMembership" class="ml-1.5 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">0</span>
                        </button>
                    </nav>
                </div>

                <!-- Messages List -->
                <div id="messagesList" class="space-y-2">
                    <div class="flex items-center justify-center py-16 text-gray-400">
                        <span class="material-symbols-outlined text-4xl animate-pulse">hourglass_top</span>
                    </div>
                </div>

                <!-- Pagination -->
                <div id="pagination" class="hidden flex items-center justify-between pt-4">
                    <p id="paginationInfo" class="text-sm text-gray-500"></p>
                    <div class="flex gap-2">
                        <button id="btnPrev" onclick="changePage(-1)" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-bg-light transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <span class="material-symbols-outlined text-base">chevron_left</span>
                        </button>
                        <button id="btnNext" onclick="changePage(1)" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-bg-light transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <span class="material-symbols-outlined text-base">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Message Detail Modal -->
    <div id="messageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeMessageModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b border-gray-200 flex items-start justify-between">
                <div class="flex-1 min-w-0 pr-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span id="modalTypeBadge" class="px-2 py-0.5 rounded-full text-xs font-bold"></span>
                        <span id="modalReadBadge" class="px-2 py-0.5 rounded-full text-xs font-bold"></span>
                    </div>
                    <h3 id="modalSubject" class="text-lg font-bold text-gray-900 truncate"></h3>
                </div>
                <button onclick="closeMessageModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-bg-light rounded-lg transition-colors flex-shrink-0">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <div class="flex flex-wrap gap-4 text-sm">
                    <div>
                        <span class="text-gray-400">Von:</span>
                        <span id="modalName" class="font-medium text-gray-900 ml-1"></span>
                    </div>
                    <div>
                        <span class="text-gray-400">E-Mail:</span>
                        <span id="modalEmail" class="font-medium text-gray-900 ml-1"></span>
                    </div>
                    <div>
                        <span class="text-gray-400">Datum:</span>
                        <span id="modalDate" class="font-medium text-gray-900 ml-1"></span>
                    </div>
                </div>
                <hr class="border-gray-100">
                <div id="modalMessage" class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap"></div>
            </div>
            <div class="p-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-3">
                <div class="flex gap-2">
                    <button id="btnToggleRead" onclick="toggleReadFromModal()" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-bg-light transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base" id="toggleReadIcon">mark_email_unread</span>
                        <span id="toggleReadLabel">Als ungelesen</span>
                    </button>
                    <a id="btnReplyEmail" href="" class="px-3 py-1.5 text-sm font-medium text-primary bg-primary/5 border border-primary/20 rounded-lg hover:bg-primary/10 transition-colors flex items-center gap-1.5 hidden">
                        <span class="material-symbols-outlined text-base">reply</span>
                        Antworten
                    </a>
                </div>
                <?php if ($isAdmin): ?>
                <button id="btnDeleteMsg" onclick="deleteFromModal()" class="px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">delete</span>
                    Löschen
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // ============================
    // Sidebar
    // ============================
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sidebarOverlay');
        sb.classList.toggle('-translate-x-full');
        ov.classList.toggle('hidden');
    }
    function toggleCollapse() {
        const sb = document.getElementById('sidebar');
        const mn = document.getElementById('mainContent');
        const icon = document.getElementById('collapseIcon');
        sb.classList.toggle('sidebar-collapsed');
        mn.classList.toggle('main-collapsed');
        mn.classList.toggle('lg:ml-72');
        icon.textContent = sb.classList.contains('sidebar-collapsed') ? 'chevron_right' : 'chevron_left';
        localStorage.setItem('wkc_sidebar_collapsed', sb.classList.contains('sidebar-collapsed'));
    }
    (function() {
        if (localStorage.getItem('wkc_sidebar_collapsed') === 'true') {
            const sb = document.getElementById('sidebar');
            const mn = document.getElementById('mainContent');
            sb.classList.add('sidebar-collapsed');
            mn.classList.add('main-collapsed');
            mn.classList.remove('lg:ml-72');
            document.getElementById('collapseIcon').textContent = 'chevron_right';
        }
    })();
    document.getElementById('logoutBtn').addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('../api/auth.php?action=logout', { method: 'POST' });
        window.location.href = 'index.php';
    });

    </script>
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js"></script>
    <script src="js/nachrichten.js"></script>
</body>
</html>
