<?php
/**
 * WKC – Article Editor (Admin)
 * Create / Edit articles with WYSIWYG editor, featured image, tags, SEO, is_funding flag.
 */
require_once __DIR__ . '/../api/config.php';
session_name(SESSION_NAME);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION;
$userRole = $user['user_role'] ?? 'member';
$isAdmin = $userRole === 'admin';
$isEditor = $userRole === 'editor';
$canEditContent = $isAdmin || $isEditor;
if (!$canEditContent) {
    header('Location: dashboard.php');
    exit;
}
$articleId = intval($_GET['id'] ?? 0);
$isEditing = $articleId > 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEditing ? 'Beitrag bearbeiten' : 'Neuer Beitrag' ?> – WKC</title>
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
        #editorContent { min-height: 400px; outline: none; }
        #editorContent:focus { outline: none; }
        #editorContent h2 { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 0.5rem; }
        #editorContent h3 { font-size: 1.25rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
        #editorContent p { margin-bottom: 0.75rem; }
        #editorContent ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        #editorContent ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        #editorContent li { margin-bottom: 0.25rem; }
        #editorContent blockquote { border-left: 3px solid #7c3aed; padding-left: 1rem; margin: 1rem 0; color: #555; font-style: italic; }
        #editorContent a { color: #7c3aed; text-decoration: underline; }
        .toolbar-btn.active { background: white; color: #7c3aed; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .preview-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .preview-content h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5rem 0 0.75rem; color: #111; }
        .preview-content h3 { font-size: 1.25rem; font-weight: 600; margin: 1rem 0 0.5rem; color: #111; }
        .preview-content p { margin-bottom: 0.75rem; line-height: 1.75; color: #374151; }
        .preview-content ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .preview-content ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .preview-content li { margin-bottom: 0.25rem; color: #374151; }
        .preview-content blockquote { border-left: 3px solid #7c3aed; padding-left: 1rem; margin: 1rem 0; color: #555; font-style: italic; }
        .preview-content a { color: #7c3aed; text-decoration: underline; }
        .preview-content img { max-width: 100%; border-radius: 0.5rem; margin: 1rem 0; }
        .author-option { transition: background 0.15s; }
        .author-option:hover { background: #f5f8f7; }
        .author-option.selected { background: rgba(0,140,90,0.08); border-color: #7c3aed; }
    </style>
</head>
<body class="bg-bg-light text-gray-900 min-h-screen">
    <!-- Top Nav -->
    <header class="sticky top-0 z-50 bg-white border-b border-gray-200 px-4 lg:px-10 py-3 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="beitraege.php" class="flex items-center gap-2 text-gray-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined">arrow_back</span>
                <span class="text-sm font-medium hidden sm:inline">Beiträge</span>
            </a>
            <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>
            <a href="dashboard.php" class="flex items-center gap-2">
                <img src="../src/wkc-logo.svg" alt="WKC Logo" class="h-8" onerror="this.style.display='none'">
            </a>
        </div>
        <div class="flex items-center gap-3">
            <span id="saveStatus" class="text-xs text-gray-400 hidden sm:inline"></span>
            <button id="btnPreview" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-bold text-sm hover:bg-bg-light transition-colors flex items-center gap-2" title="Vorschau">
                <span class="material-symbols-outlined text-lg">visibility</span>
                <span class="hidden sm:inline">Vorschau</span>
            </button>
            <button id="btnSaveDraft" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-bold text-sm hover:bg-bg-light transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">save</span>
                <span class="hidden sm:inline">Speichern</span>
            </button>
        </div>
    </header>

    <main class="max-w-[1200px] mx-auto w-full px-4 lg:px-10 py-6 lg:py-8">
        <!-- Breadcrumbs -->
        <div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
            <a class="text-gray-400 font-medium hover:text-primary" href="dashboard.php">Dashboard</a>
            <span class="material-symbols-outlined text-sm text-gray-300">chevron_right</span>
            <a class="text-gray-400 font-medium hover:text-primary" href="beitraege.php">Beiträge</a>
            <span class="material-symbols-outlined text-sm text-gray-300">chevron_right</span>
            <span class="text-gray-900 font-semibold"><?= $isEditing ? 'Beitrag bearbeiten' : 'Neuer Beitrag' ?></span>
        </div>

        <!-- Alert box for errors/success -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm font-medium flex items-center gap-2"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Main Editor Column -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                <!-- Featured Image (above title) -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div id="imageDropZone" class="relative min-h-[180px] flex flex-col items-center justify-center gap-2 hover:bg-bg-light/50 transition-colors cursor-pointer group overflow-hidden">
                        <img id="imagePreview" src="" alt="" class="hidden absolute inset-0 w-full h-full object-cover">
                        <div id="imageUploadHint" class="flex flex-col items-center gap-2 p-6 relative z-10">
                            <span class="material-symbols-outlined text-4xl text-gray-300 group-hover:text-primary transition-colors" id="imageIcon">add_photo_alternate</span>
                            <p class="text-sm font-medium text-gray-400 group-hover:text-gray-600" id="imageLabel">Beitragsbild hochladen oder hierher ziehen</p>
                            <p class="text-xs text-gray-300">JPG, PNG, WebP (max. 5 MB)</p>
                        </div>
                        <input type="file" id="featuredImageInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
                    </div>
                </div>

                <!-- Title (multiline textarea) -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <label class="flex flex-col gap-2 w-full">
                        <span class="text-gray-900 text-xs font-bold uppercase tracking-wider">Titel</span>
                        <textarea
                            id="articleTitle"
                            class="form-textarea w-full border-none focus:ring-0 p-0 text-2xl lg:text-3xl font-bold placeholder:text-gray-300 text-gray-900 resize-none overflow-hidden"
                            placeholder="Titel des Beitrags eingeben..."
                            rows="1"></textarea>
                    </label>
                </div>

                <!-- Slug / URL -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <label class="flex flex-col gap-2 w-full">
                        <span class="text-gray-900 text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-primary">link</span>
                            URL / Pfad
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-400 text-sm whitespace-nowrap">/neuigkeiten/</span>
                            <input id="articleSlug" class="form-input w-full border-none focus:ring-0 p-0 text-gray-700 placeholder:text-gray-300 font-mono text-sm" placeholder="wird-automatisch-generiert" type="text">
                        </div>
                        <p class="text-xs text-gray-400">Wird beim ersten Speichern automatisch aus dem Titel generiert. Kann manuell angepasst werden.</p>
                    </label>
                </div>

                <!-- Excerpt -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <label class="flex flex-col gap-2 w-full">
                        <span class="text-gray-900 text-xs font-bold uppercase tracking-wider">Kurzfassung / Teaser</span>
                        <textarea
                            id="articleExcerpt"
                            class="form-textarea w-full border-none focus:ring-0 p-0 text-gray-700 placeholder:text-gray-300 resize-none"
                            placeholder="Kurze Zusammenfassung für die Vorschau..."
                            rows="2"></textarea>
                    </label>
                </div>

                <!-- WYSIWYG Editor -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col overflow-hidden">
                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center gap-1 p-2 border-b border-gray-200 bg-bg-light/30">
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('bold')" title="Fett">
                            <span class="material-symbols-outlined">format_bold</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('italic')" title="Kursiv">
                            <span class="material-symbols-outlined">format_italic</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('underline')" title="Unterstrichen">
                            <span class="material-symbols-outlined">format_underlined</span>
                        </button>
                        <div class="w-px h-6 bg-gray-200 mx-1"></div>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('formatBlock', '<h2>')" title="Überschrift 2">
                            <span class="text-sm font-bold">H2</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('formatBlock', '<h3>')" title="Überschrift 3">
                            <span class="text-sm font-bold">H3</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('formatBlock', '<p>')" title="Absatz">
                            <span class="material-symbols-outlined">notes</span>
                        </button>
                        <div class="w-px h-6 bg-gray-200 mx-1"></div>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('insertUnorderedList')" title="Aufzählung">
                            <span class="material-symbols-outlined">format_list_bulleted</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('insertOrderedList')" title="Nummerierte Liste">
                            <span class="material-symbols-outlined">format_list_numbered</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('formatBlock', '<blockquote>')" title="Zitat">
                            <span class="material-symbols-outlined">format_quote</span>
                        </button>
                        <div class="w-px h-6 bg-gray-200 mx-1"></div>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="insertLink()" title="Link einfügen">
                            <span class="material-symbols-outlined">link</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="triggerInlineImageUpload()" title="Bild einfügen">
                            <span class="material-symbols-outlined">image</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="insertHtmlEmbed()" title="HTML/Embed einfügen">
                            <span class="material-symbols-outlined">code</span>
                        </button>
                        <button class="toolbar-btn p-2 hover:bg-white rounded text-gray-700 hover:shadow-sm transition-all" onclick="execCmd('removeFormat')" title="Formatierung entfernen">
                            <span class="material-symbols-outlined">format_clear</span>
                        </button>
                    </div>
                    <!-- Editor Body -->
                    <div id="editorContent" class="p-6 lg:p-8 flex-1 text-gray-900 leading-relaxed text-base lg:text-lg" contenteditable="true">
                        <p><br></p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 flex flex-col gap-6">
                <!-- Status Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm sticky top-20">
                    <div class="p-5 border-b border-gray-200">
                        <h3 class="text-gray-900 text-xs font-bold uppercase tracking-wider">Veröffentlichung</h3>
                    </div>
                    <div class="p-5 flex flex-col gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm text-primary">info</span>
                                Status
                            </label>
                            <select id="articleStatus" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2 font-medium">
                                <option value="draft">Entwurf</option>
                                <option value="published">Veröffentlicht</option>
                                <option value="archived">Archiviert</option>
                            </select>
                        </div>

                        <!-- Date in sidebar -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm text-primary">calendar_today</span>
                                Datum
                            </label>
                            <input id="articleDate" type="date" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2">
                        </div>

                        <hr class="border-gray-100">

                        <!-- Author Selection -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Autor</label>
                            <div id="authorDropdown" class="relative">
                                <button type="button" id="authorToggle" class="w-full flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 hover:border-primary transition-colors text-left">
                                    <div id="authorAvatar" class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs flex-shrink-0">
                                        <?= mb_substr($user['display_name'], 0, 1) ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p id="authorName" class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($user['display_name']) ?></p>
                                        <p id="authorPosition" class="text-xs text-gray-400 truncate"><?= htmlspecialchars($user['position'] ?? 'Mitglied') ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-gray-400 text-lg">expand_more</span>
                                </button>
                                <div id="authorList" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-10 max-h-60 overflow-y-auto">
                                    <p class="text-xs text-gray-400 px-3 py-2 border-b border-gray-100">Autor auswählen</p>
                                    <div id="authorOptions"></div>
                                </div>
                            </div>
                            <input type="hidden" id="authorId" value="<?= intval($user['user_id']) ?>">
                        </div>

                        <hr class="border-gray-100">

                        <!-- Funding toggle -->
                        <div class="flex items-center justify-between">
                            <label class="text-sm text-gray-700 font-medium flex items-center gap-2 cursor-pointer" for="isFunding">
                                <span class="material-symbols-outlined text-base text-blue-500">volunteer_activism</span>
                                Förderung
                            </label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="isFunding" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 -mt-2">Markiert diesen Beitrag als geförderte Maßnahme.</p>

                        <hr class="border-gray-100">

                        <!-- Tags -->
                        <div>
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-wider block mb-2">Schlagwörter</label>
                            <input id="articleTags" type="text" placeholder="z.B. spielplatz, förderung" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2">
                            <p class="text-xs text-gray-400 mt-1">Kommagetrennt eingeben</p>
                        </div>

                        <hr class="border-gray-100">

                        <!-- SEO Section -->
                        <details class="group">
                            <summary class="text-xs font-bold text-gray-900 uppercase tracking-wider cursor-pointer flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm transition-transform group-open:rotate-90">chevron_right</span>
                                SEO-Einstellungen
                            </summary>
                            <div class="mt-3 flex flex-col gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 font-medium mb-1 block">Meta-Titel</label>
                                    <input id="metaTitle" type="text" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2" placeholder="SEO-Titel">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-medium mb-1 block">Meta-Beschreibung</label>
                                    <textarea id="metaDescription" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2 resize-none" rows="3" placeholder="Beschreibung für Suchmaschinen..."></textarea>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-medium mb-1 block">Canonical URL</label>
                                    <input id="canonicalUrl" type="url" class="w-full rounded-lg border-gray-200 focus:border-primary focus:ring-1 focus:ring-primary text-sm py-2" placeholder="https://example.org/pfad">
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600">
                                    <input id="seoNoindex" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    noindex
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600">
                                    <input id="seoNofollow" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    nofollow
                                </label>
                            </div>
                        </details>
                    </div>
                    <!-- Delete -->
                    <div class="p-4 bg-bg-light/50 border-t border-gray-100 flex justify-center <?= $isEditing ? '' : 'hidden' ?>" id="deleteSection">
                        <button id="btnDelete" class="text-red-500 text-xs font-bold hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">delete</span>
                            Beitrag löschen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Preview Modal -->
    <div id="previewModal" class="hidden fixed inset-0 z-[100]">
        <div class="preview-overlay absolute inset-0" onclick="closePreview()"></div>
        <div class="relative z-10 max-w-3xl mx-auto my-6 lg:my-10 bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[calc(100vh-3rem)] lg:max-h-[calc(100vh-5rem)] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-bg-light/50 flex-shrink-0">
                <div class="flex items-center gap-2 text-sm font-bold text-gray-700">
                    <span class="material-symbols-outlined text-primary">visibility</span>
                    Vorschau
                </div>
                <button onclick="closePreview()" class="p-1.5 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <div id="previewImage" class="hidden w-full h-56 lg:h-72 bg-gray-100 overflow-hidden">
                    <img id="previewImageSrc" src="" alt="" class="w-full h-full object-cover">
                </div>
                <div class="px-6 lg:px-10 py-6 lg:py-8">
                    <div class="flex flex-wrap items-center gap-3 mb-4 text-xs text-gray-400">
                        <span id="previewDate"></span>
                        <span>&middot;</span>
                        <div class="flex items-center gap-2">
                            <div id="previewAuthorAvatar" class="w-5 h-5 rounded-full bg-primary/10"></div>
                            <span id="previewAuthorName"></span>
                        </div>
                        <span id="previewFundingBadge" class="hidden bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-bold">Förderung</span>
                    </div>
                    <h1 id="previewTitle" class="text-2xl lg:text-3xl font-black text-gray-900 mb-3 leading-tight"></h1>
                    <p id="previewExcerpt" class="text-gray-500 text-base lg:text-lg mb-6 leading-relaxed"></p>
                    <hr class="border-gray-100 mb-6">
                    <div id="previewBody" class="preview-content text-base leading-relaxed"></div>
                    <div id="previewTags" class="hidden mt-8 pt-6 border-t border-gray-100 flex flex-wrap gap-2"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const ARTICLE_ID = <?= $articleId ?>;
    const IS_EDITING = <?= $isEditing ? 'true' : 'false' ?>;
    const CURRENT_USER_ID = <?= intval($user['user_id']) ?>;
    </script>
    <input type="file" id="inlineImageInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js"></script>
    <script src="js/editor.js"></script>
</body>
</html>

