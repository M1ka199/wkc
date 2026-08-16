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

if (!$canEditContent) {
    header('Location: dashboard.php');
    exit;
}

$pageId = intval($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seiten-Editor – CMS Backend</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: '#7c3aed', 'primary-dark': '#5b21b6', 'bg-light': '#f5f8f7' } } } };
    </script>
    <style>
        body { font-family: "Public Sans", sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .sidebar-link.active { background: rgba(124, 58, 237, 0.12); color: #7c3aed; font-weight: 700; }
        .sidebar-collapsed { width: 5rem !important; }
        .sidebar-collapsed .sidebar-label,
        .sidebar-collapsed .sidebar-logo-link { display: none; }
        .sidebar-collapsed .sidebar-header { justify-content: center; }
        .sidebar-collapsed .sidebar-content { padding: 0.75rem; }
        .sidebar-collapsed .sidebar-nav a { justify-content: center; padding: 0.75rem; }
        .main-collapsed { margin-left: 5rem !important; }
        #pageEditor { min-height: 420px; }
        #pageEditor h2 { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 .5rem; }
        #pageEditor h3 { font-size: 1.2rem; font-weight: 700; margin: .8rem 0 .5rem; }
        #pageEditor p { margin-bottom: .8rem; }
        #pageEditor ul { list-style: disc; margin-left: 1.5rem; margin-bottom: .8rem; }
        #pageEditor ol { list-style: decimal; margin-left: 1.5rem; margin-bottom: .8rem; }
        .toolbar-btn.active { background: #ffffff; color: #7c3aed; }
    </style>
</head>
<body class="bg-bg-light text-gray-900">
<div class="flex min-h-screen">
    <aside id="sidebar" class="w-72 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20 transition-all duration-300 -translate-x-full lg:translate-x-0">
        <div class="p-6 sidebar-content">
            <div class="sidebar-header flex items-center justify-between mb-6">
                <a href="dashboard.php" class="sidebar-logo-link"><img src="../src/wkc-logo.svg" alt="Logo" class="h-auto w-full max-w-[10rem]"></a>
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
            </nav>
        </div>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-10 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
        <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg"><span class="material-symbols-outlined">menu</span></button>
                <a href="seiten.php" class="p-2 text-gray-500 hover:bg-bg-light rounded-lg"><span class="material-symbols-outlined">arrow_back</span></a>
                <h2 id="editorTitle" class="text-lg font-bold text-gray-900">Neue Seite</h2>
            </div>
            <div class="flex items-center gap-2">
                <button id="deletePageBtn" class="px-3 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-bold hidden">Löschen</button>
                <button id="savePageBtn" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold">Speichern</button>
            </div>
        </header>

        <div class="p-4 lg:p-8 max-w-7xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-6">
            <section class="xl:col-span-2 space-y-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Titel *</label>
                            <input id="pageTitle" type="text" class="w-full rounded-lg border-gray-300" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Pfad</label>
                            <input id="pagePath" type="text" class="w-full rounded-lg border-gray-300" placeholder="z. B. verein/geschichte">
                            <p id="pagePathHelp" class="mt-1 text-xs text-gray-500">Bei normaler Seite erforderlich. Fuer die Startseite leer lassen.</p>
                            <label class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input id="pageIsHomepage" type="checkbox" class="rounded border-gray-300 text-primary">
                                Diese Seite ist die Startseite (domain.de/)
                            </label>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex flex-wrap gap-1 p-2 bg-bg-light/40 border-b border-gray-200">
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="execCmd('bold')" title="Fett" type="button"><span class="material-symbols-outlined">format_bold</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="execCmd('italic')" title="Kursiv" type="button"><span class="material-symbols-outlined">format_italic</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="execCmd('underline')" title="Unterstrichen" type="button"><span class="material-symbols-outlined">format_underlined</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="execCmd('insertUnorderedList')" title="Liste" type="button"><span class="material-symbols-outlined">format_list_bulleted</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="execCmd('insertOrderedList')" title="Nummeriert" type="button"><span class="material-symbols-outlined">format_list_numbered</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="insertLink()" title="Link" type="button"><span class="material-symbols-outlined">link</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="triggerInlineImageUpload()" title="Bild" type="button"><span class="material-symbols-outlined">image</span></button>
                            <button class="toolbar-btn p-2 rounded hover:bg-white" onclick="insertHtmlEmbed()" title="Embed" type="button"><span class="material-symbols-outlined">code</span></button>
                            <span class="mx-2 hidden md:inline-block w-px bg-gray-300"></span>
                            <button class="toolbar-btn px-3 py-2 rounded hover:bg-white text-xs font-bold text-gray-700" onclick="insertSectionTemplate('hero')" title="Hero-Abschnitt" type="button">Hero</button>
                            <button class="toolbar-btn px-3 py-2 rounded hover:bg-white text-xs font-bold text-gray-700" onclick="insertSectionTemplate('two-column')" title="Zwei-Spalten-Abschnitt" type="button">2 Spalten</button>
                            <button class="toolbar-btn px-3 py-2 rounded hover:bg-white text-xs font-bold text-gray-700" onclick="insertSectionTemplate('cta')" title="Call-to-Action-Abschnitt" type="button">CTA</button>
                        </div>
                        <div id="pageEditor" contenteditable="true" class="p-5"></div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Status</label>
                        <select id="pageStatus" class="w-full rounded-lg border-gray-300">
                            <option value="draft">Entwurf</option>
                            <option value="published">Veröffentlicht</option>
                            <option value="archived">Archiviert</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Meta Titel</label>
                        <input id="pageMetaTitle" type="text" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Meta Beschreibung</label>
                        <textarea id="pageMetaDescription" rows="3" class="w-full rounded-lg border-gray-300"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Canonical URL</label>
                        <input id="pageCanonicalUrl" type="url" class="w-full rounded-lg border-gray-300" placeholder="https://example.org/pfad">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">OpenGraph Bild</label>
                        <input id="pageOgImage" type="text" class="w-full rounded-lg border-gray-300" placeholder="/uploads/... oder absolute URL">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700"><input id="pageNoindex" type="checkbox" class="rounded border-gray-300 text-primary">noindex</label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700"><input id="pageNofollow" type="checkbox" class="rounded border-gray-300 text-primary">nofollow</label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input id="pageTitleAreaEnabled" type="checkbox" class="rounded border-gray-300 text-primary" checked>
                        Titelbereich (Breadcrumb + H1) anzeigen
                    </label>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Startseiten-Bausteine</h3>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input id="blockHero" type="checkbox" class="rounded border-gray-300 text-primary">Hero anzeigen</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input id="blockSlider" type="checkbox" class="rounded border-gray-300 text-primary">Slider anzeigen</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input id="blockNews" type="checkbox" class="rounded border-gray-300 text-primary">Neuigkeiten anzeigen</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input id="blockEvents" type="checkbox" class="rounded border-gray-300 text-primary">Termine anzeigen</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input id="blockGallery" type="checkbox" class="rounded border-gray-300 text-primary">Galerie-Teaser anzeigen</label>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Startseiten-Slider</h3>
                        <button id="addSliderItemBtn" type="button" class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-primary text-white">Slide hinzufügen</button>
                    </div>
                    <p class="text-xs text-gray-500">Nur relevant für die Startseite. Reihenfolge entspricht der Anzeige im Slider.</p>
                    <div id="sliderItemsList" class="space-y-3"></div>
                </div>
            </aside>
        </div>
    </main>
</div>

<input type="file" id="inlineImageInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
<script>
const PAGE_ID = <?= $pageId ?>;
</script>
<script src="js/admin-theme.js?v=20260816-2"></script>
<script src="js/shared.js?v=20260814-2"></script>
<script src="js/seiten-editor.js?v=20260816-3"></script>
</body>
</html>
