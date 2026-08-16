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
if ($currentRole) {
    $_SESSION['user_role'] = $currentRole;
}

$user = $_SESSION;
$isAdmin = ($_SESSION['user_role'] ?? 'member') === 'admin';
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

$formId = (int) ($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formular-Editor – WKC Backend</title>
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
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="beitraege.php" title="Beiträge">
                        <span class="material-symbols-outlined">article</span>
                        <span class="sidebar-label">Beiträge</span>
                    </a>
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="seiten.php" title="Seiten">
                        <span class="material-symbols-outlined">web</span>
                        <span class="sidebar-label">Seiten</span>
                    </a>
                    <a class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg transition-colors" href="formulare.php" title="Formulare">
                        <span class="material-symbols-outlined">dynamic_form</span>
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
                    <a class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-500 hover:bg-bg-light transition-colors font-medium" href="mitglieder.php" title="Mitglieder">
                        <span class="material-symbols-outlined">group</span>
                        <span class="sidebar-label">Mitglieder</span>
                    </a>
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
                    <a href="formulare.php" class="p-2 text-gray-500 hover:bg-bg-light rounded-lg">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </a>
                    <h2 id="editorTitle" class="text-lg font-bold text-gray-900">Neues Formular</h2>
                </div>
                <div class="flex items-center gap-2">
                    <button id="deleteFormBtn" class="px-3 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-bold hidden">Löschen</button>
                    <button id="saveFormBtn" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold">Speichern</button>
                </div>
            </header>

            <div class="p-4 lg:p-8 max-w-7xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-6">
                <section class="xl:col-span-2 space-y-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">Grunddaten</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Titel *</label>
                                <input id="formTitle" type="text" class="w-full rounded-lg border-gray-300" placeholder="z. B. Mitgliedsantrag" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Slug *</label>
                                <input id="formSlug" type="text" class="w-full rounded-lg border-gray-300" placeholder="mitgliedsantrag" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Zielseite (optional)</label>
                                <input id="formTargetPath" type="text" class="w-full rounded-lg border-gray-300" placeholder="z. B. mitmachen">
                                <p class="mt-1 text-xs text-gray-500">Wenn gesetzt, wird das Formular auf dieser Route automatisch eingebunden (zusätzlich zu manuellen Shortcodes).</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Button-Beschriftung</label>
                                <input id="formSubmitLabel" type="text" class="w-full rounded-lg border-gray-300" placeholder="Formular absenden">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Beschreibung</label>
                            <textarea id="formDescription" rows="2" class="w-full rounded-lg border-gray-300" placeholder="Kurzer erklärender Text oberhalb des Formulars"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Erfolgsmeldung</label>
                            <textarea id="formSuccessMessage" rows="2" class="w-full rounded-lg border-gray-300" placeholder="Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt."></textarea>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input id="formIsActive" type="checkbox" class="rounded border-gray-300 text-primary" checked>
                            Formular aktiv
                        </label>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">Formularfelder</h3>
                            <button type="button" id="addFieldBtn" class="px-3 py-2 rounded-lg bg-primary text-white text-xs font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Feld hinzufügen
                            </button>
                        </div>
                        <div id="fieldsList" class="space-y-3"></div>
                        <p class="text-xs text-gray-500">Unterstützte Typen: Text, E-Mail, Telefon, Textbereich, Checkbox, Datei-Upload, Signatur-Pad, Überschrift, Trennelement. Pro Feld kann zudem volle oder halbe Breite gewählt werden.</p>
                    </div>
                </section>

                <aside class="space-y-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">E-Mail-Konfiguration</h3>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Empfänger *</label>
                            <textarea id="emailRecipients" rows="2" class="w-full rounded-lg border-gray-300" placeholder="mail@beispiel.de, vorstand@beispiel.de"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Betreff *</label>
                            <input id="emailSubject" type="text" class="w-full rounded-lg border-gray-300" placeholder="Neue Einsendung: {form_title}">
                            <p class="mt-1 text-xs text-gray-500">Platzhalter: {form_title}, {form_slug}, {date}, {time}</p>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">SMTP (optional pro Formular)</h3>
                        <p class="text-xs text-gray-500">Leer lassen, um die globale SMTP-Konfiguration zu verwenden.</p>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Host</label>
                            <input id="smtpHost" type="text" class="w-full rounded-lg border-gray-300" placeholder="smtp.example.org">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Port</label>
                                <input id="smtpPort" type="number" class="w-full rounded-lg border-gray-300" placeholder="587">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Sicherheit</label>
                                <select id="smtpSecure" class="w-full rounded-lg border-gray-300">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Benutzer</label>
                            <input id="smtpUser" type="text" class="w-full rounded-lg border-gray-300" placeholder="smtp-user">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Passwort</label>
                            <input id="smtpPass" type="password" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Absender-E-Mail</label>
                            <input id="smtpFrom" type="email" class="w-full rounded-lg border-gray-300" placeholder="noreply@example.org">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Absendername</label>
                            <input id="smtpFromName" type="text" class="w-full rounded-lg border-gray-300" placeholder="WKC Formulare">
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">Einbindung</h3>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Shortcode (CMS-Inhalt)</label>
                        <input id="embedShortcode" type="text" class="w-full rounded-lg border-gray-300 bg-gray-50" readonly>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">HTML-Container</label>
                        <textarea id="embedHtml" rows="3" class="w-full rounded-lg border-gray-300 bg-gray-50 text-xs" readonly></textarea>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
    const FORM_ID = <?= $formId ?>;
    </script>
    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/shared.js?v=20260814-2"></script>
    <script src="js/formular-editor.js?v=20260816-1"></script>
</body>
</html>
