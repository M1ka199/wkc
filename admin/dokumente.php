<?php
/**
 * WKC – Dokumente verwalten
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
    <title>Dokumente – WKC Backend</title>
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
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="termine.php" title="Termine">
                        <span class="material-symbols-outlined">event</span>
                        <span class="sidebar-label">Termine</span>
                    </a>
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="dokumente.php" title="Dokumente">
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
                    <h2 class="text-lg font-bold text-gray-900">Dokumente</h2>
                </div>
                <div class="flex items-center gap-3">
                    <a href="../index.html" target="_blank" class="p-2 text-gray-400 hover:text-primary transition-colors rounded-lg hover:bg-bg-light flex items-center gap-2" title="Webseite anzeigen">
                        <span class="material-symbols-outlined">open_in_new</span>
                        <span class="text-sm font-medium hidden sm:inline">zur Website</span>
                    </a>
                </div>
            </header>

            <div class="p-4 lg:p-8 max-w-6xl mx-auto space-y-6">
                <!-- Header Row with Actions -->
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div>
                        <h2 class="text-2xl lg:text-3xl font-black text-gray-900 tracking-tight">Dokumente</h2>
                        <p class="text-gray-500 mt-1">Dateien und Dokumente verwalten.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="openTagManager()" class="bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-bg-light transition-all whitespace-nowrap">
                            <span class="material-symbols-outlined text-sm">label</span>
                            Tags verwalten
                        </button>
                        <button onclick="openDocumentModal()" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-primary-dark transition-all shadow-sm shadow-primary/20 whitespace-nowrap">
                            <span class="material-symbols-outlined text-sm">upload_file</span>
                            Dokument hochladen
                        </button>
                    </div>
                </div>

                <!-- Tag Filter -->
                <div id="tagFilters" class="flex flex-wrap gap-2 hidden">
                    <button class="tag-filter-btn active px-3 py-1.5 rounded-full text-xs font-bold bg-gray-900 text-white transition-all" data-tag="">Alle</button>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-bg-light/50">
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Dokument</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Tags</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Dateiname</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider hidden md:table-cell">Größe</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider hidden md:table-cell">Datum</th>
                                    <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="docsTableBody" class="divide-y divide-gray-100">
                                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Laden...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Document Upload Modal -->
    <div id="documentModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeDocumentModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative animate-in">
                <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-900">Dokument hochladen</h3>
                    <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form id="documentForm" class="p-6 space-y-4" enctype="multipart/form-data">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Titel *</label>
                        <input type="text" id="docTitle" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" placeholder="z.B. Protokoll Mitgliederversammlung">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Beschreibung</label>
                        <textarea id="docDescription" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm resize-none" placeholder="Optionale Beschreibung..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Tags</label>
                        <div id="docTagSelection" class="flex flex-wrap gap-2 min-h-[2rem]">
                            <span class="text-xs text-gray-400">Tags werden geladen...</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Datei * <span class="font-normal text-gray-400">(max. 10 MB)</span></label>
                        <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary/50 transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">cloud_upload</span>
                            <p class="text-sm text-gray-500">Datei hierher ziehen oder <span class="text-primary font-bold">durchsuchen</span></p>
                            <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel, Bilder, ZIP</p>
                            <input type="file" id="docFile" required class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.txt,.csv,.zip">
                        </div>
                        <p id="selectedFileName" class="text-sm text-gray-600 mt-2 hidden"></p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeDocumentModal()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</button>
                        <button type="submit" id="docSubmitBtn" class="flex-1 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20">Hochladen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tag Manager Modal -->
    <div id="tagManagerModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeTagManager()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative animate-in">
                <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-900">Tags verwalten</h3>
                    <button onclick="closeTagManager()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-5">
                    <!-- New Tag Form -->
                    <form id="tagForm" class="flex items-end gap-3">
                        <input type="hidden" id="tagEditId" value="">
                        <div class="flex-1">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Tag-Name</label>
                            <input type="text" id="tagName" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" placeholder="z.B. Protokoll">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Farbe</label>
                            <input type="color" id="tagColor" value="#7c3aed" class="w-12 h-[42px] border border-gray-300 rounded-lg cursor-pointer p-1">
                        </div>
                        <button type="submit" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shrink-0">
                            <span class="material-symbols-outlined text-sm" id="tagFormIcon">add</span>
                        </button>
                    </form>

                    <!-- Existing Tags -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-500 mb-3">Vorhandene Tags</h4>
                        <div id="tagsList" class="space-y-2">
                            <p class="text-sm text-gray-400">Noch keine Tags vorhanden.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Tag Assignment Modal -->
    <div id="tagAssignModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeTagAssign()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm relative animate-in">
                <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-900">Tags zuweisen</h3>
                    <button onclick="closeTagAssign()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="tagAssignDocId">
                    <div id="tagAssignList" class="space-y-2">
                        <p class="text-sm text-gray-400">Tags werden geladen...</p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeTagAssign()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</button>
                        <button type="button" onclick="saveTagAssignment()" class="flex-1 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-dark transition-all shadow-sm shadow-primary/20">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const CAN_EDIT_CONTENT = <?= $canEditContent ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js"></script>
    <script src="js/dokumente.js"></script>
</body>
</html>
