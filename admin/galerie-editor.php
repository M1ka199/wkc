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

$galleryId = intval($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie-Editor - CMS Backend</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
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
    </style>
</head>
<body class="bg-bg-light text-gray-900">
<div class="flex min-h-screen">
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
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span><span class="sidebar-label">Dashboard</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="beitraege.php"><span class="material-symbols-outlined">article</span><span class="sidebar-label">Beiträge</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="seiten.php"><span class="material-symbols-outlined">web</span><span class="sidebar-label">Seiten</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="formulare.php"><span class="material-symbols-outlined">list_alt</span><span class="sidebar-label">Formulare</span></a>
                <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg" href="galerien.php"><span class="material-symbols-outlined">photo_library</span><span class="sidebar-label">Galerien</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="termine.php"><span class="material-symbols-outlined">event</span><span class="sidebar-label">Termine</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="dokumente.php"><span class="material-symbols-outlined">folder_open</span><span class="sidebar-label">Dokumente</span></a>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="nachrichten.php"><span class="material-symbols-outlined">mail</span><span class="sidebar-label">Nachrichten</span></a>
                <?php if ($isAdmin): ?>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="mitglieder.php"><span class="material-symbols-outlined">group</span><span class="sidebar-label">Mitglieder</span></a>
                <?php endif; ?>
                <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light" href="einstellungen.php"><span class="material-symbols-outlined">settings</span><span class="sidebar-label">Einstellungen</span></a>
            </nav>
        </div>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-10 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <main id="mainContent" class="flex-1 lg:ml-72 transition-all duration-300">
        <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-500 hover:bg-bg-light rounded-lg"><span class="material-symbols-outlined">menu</span></button>
                <a href="galerien.php" class="p-2 text-gray-500 hover:bg-bg-light rounded-lg"><span class="material-symbols-outlined">arrow_back</span></a>
                <h2 id="editorTitle" class="text-lg font-bold text-gray-900">Neue Galerie</h2>
            </div>
            <div class="flex items-center gap-2">
                <button id="deleteGalleryBtn" class="px-3 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-bold hidden">Löschen</button>
                <button id="saveGalleryBtn" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold">Speichern</button>
            </div>
        </header>

        <div class="p-4 lg:p-8 max-w-7xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-6">
            <section class="xl:col-span-2 space-y-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Titel *</label>
                            <input id="galleryTitle" type="text" class="w-full rounded-lg border-gray-300" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Slug *</label>
                            <input id="gallerySlug" type="text" class="w-full rounded-lg border-gray-300" placeholder="sommerfest-2026">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Beschreibung</label>
                        <textarea id="galleryDescription" rows="4" class="w-full rounded-lg border-gray-300" placeholder="Kurzbeschreibung für Teaser und SEO-Kontext"></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" id="galleryPublished" class="rounded border-gray-300 text-primary" checked>
                        Öffentlich sichtbar
                    </label>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 class="text-sm font-bold text-gray-900">Bilder & Reihenfolge</h3>
                    <div id="galleryDropZone" class="rounded-xl border-2 border-dashed border-gray-300 bg-bg-light/70 p-5 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition-all">
                        <span class="material-symbols-outlined text-3xl text-primary">cloud_upload</span>
                        <p class="mt-1 text-sm font-bold text-gray-900">Bilder hierher ziehen oder klicken</p>
                        <p class="text-xs text-gray-500">Mehrfachauswahl unterstuetzt (JPG, PNG, WebP)</p>
                        <input type="file" id="galleryUploadFile" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Bildunterschrift</label>
                            <input type="text" id="galleryUploadCaption" class="w-full rounded-lg border-gray-300" placeholder="Optional: gemeinsame Bildunterschrift fuer Batch-Upload">
                        </div>
                        <button type="button" id="uploadGalleryImageBtn" class="md:col-span-1 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-bold">Ausgewaehlte Bilder hochladen</button>
                    </div>
                    <div id="galleryUploadStatus" class="text-xs text-gray-500"></div>
                    <div id="galleryImagesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Einbettung</h3>
                    <p class="text-sm text-gray-600">Nutze diesen Platzhalter im Seiten- oder Beitragseditor:</p>
                    <pre id="galleryEmbedCode" class="bg-bg-light rounded-lg p-3 text-xs font-mono text-gray-800 break-all">[gallery:slug]</pre>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Hinweise</h3>
                    <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1">
                        <li>Cover-Bild ist automatisch das erste Bild der Reihenfolge.</li>
                        <li>Mit Pfeilen kannst du Bilder sortieren.</li>
                        <li>Änderungen werden sofort in der Galerieansicht nutzbar.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>
</div>

<script>const GALLERY_ID = <?= $galleryId ?>;</script>
<script src="js/admin-theme.js?v=20260816-2"></script>
<script src="js/shared.js?v=20260814-2"></script>
<script src="js/galerie-editor.js?v=20260815-1"></script>
</body>
</html>
