<?php
/**
 * WKC – Passwort vergessen
 */
require_once __DIR__ . '/../api/config.php';
header('Content-Type: text/html; charset=utf-8');
session_name(SESSION_NAME);
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen – WKC</title>
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
        input:focus { border-color: #7c3aed !important; }
    </style>
</head>
<body class="bg-bg-light min-h-screen flex flex-col">
    <div class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-primary/20 via-primary to-primary/20 z-50"></div>

    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-[440px] bg-white rounded-xl shadow-xl shadow-primary/5 overflow-hidden border border-primary/10">
            <div class="pt-10 pb-6 px-8 text-center">
                <div class="flex justify-center mb-6">
                    <img src="../src/wkc-logo.svg" alt="WKC Logo" class="h-16" onerror="this.style.display='none'">
                </div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Passwort vergessen?</h1>
                <p class="text-sm text-gray-500 mt-2 font-medium">Geben Sie Ihren Benutzernamen oder Ihre E-Mail ein.</p>
            </div>

            <!-- Alert Box -->
            <div id="alertBox" class="hidden mx-8 mb-4 p-3 rounded-lg text-sm font-medium flex items-center gap-2"></div>

            <!-- Request Form -->
            <form id="resetRequestForm" class="px-8 pb-8 space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="identifier">Benutzername oder E-Mail</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">person_search</span>
                        <input class="w-full h-12 pl-10 pr-4 bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                            id="identifier" name="identifier" placeholder="Benutzername oder E-Mail" type="text" required autofocus>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full h-12 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg shadow-lg shadow-primary/20 transform transition-all active:scale-[0.98] focus:outline-none flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">mail</span>
                    Link zum Zurücksetzen senden
                </button>
            </form>

            <!-- Success State (hidden initially) -->
            <div id="successState" class="hidden px-8 pb-8">
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-green-500 text-4xl mb-3 block">mark_email_read</span>
                    <p class="text-sm text-green-700 font-medium">Falls ein Konto mit diesen Daten existiert, wurde eine E-Mail mit einem Link zum Zurücksetzen des Passworts versendet.</p>
                    <p class="text-xs text-green-600 mt-3">Bitte überprüfen Sie auch Ihren Spam-Ordner.</p>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 flex justify-between items-center">
                <a class="text-xs text-gray-500 hover:text-primary flex items-center gap-1 transition-colors" href="index.php">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    Zurück zum Login
                </a>
                <a class="text-xs text-gray-500 hover:text-primary flex items-center gap-1 transition-colors" href="../index.html">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    Zur Webseite
                </a>
            </div>
        </div>
    </div>

    <footer class="py-6 px-4 text-center">
        <p class="text-sm text-gray-500 font-medium">&copy; 2025 WKC e.V.</p>
    </footer>

    <div class="fixed -bottom-24 -left-24 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed top-1/2 right-0 -translate-y-1/2 w-32 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

    <script>
    document.getElementById('resetRequestForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const alertBox = document.getElementById('alertBox');
        alertBox.classList.add('hidden');

        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Wird gesendet...';

        try {
            const formData = new FormData();
            formData.append('identifier', document.getElementById('identifier').value.trim());

            const res = await fetch('../api/auth.php?action=request_reset', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                document.getElementById('resetRequestForm').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');
            } else {
                alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
                alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> ' + (data.error || 'Fehler beim Senden.');
            }
        } catch (err) {
            alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
            alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> Verbindungsfehler. Bitte versuchen Sie es erneut.';
        }

        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">mail</span> Link zum Zurücksetzen senden';
    });
    </script>
</body>
</html>
