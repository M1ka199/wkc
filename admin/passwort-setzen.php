<?php
/**
 * WKC – Passwort setzen (Reset & Einladung)
 */
require_once __DIR__ . '/../api/config.php';
header('Content-Type: text/html; charset=utf-8');
session_name(SESSION_NAME);
session_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort festlegen – WKC</title>
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
                <h1 id="pageTitle" class="text-2xl font-black text-gray-900 tracking-tight">Passwort festlegen</h1>
                <p id="pageSubtitle" class="text-sm text-gray-500 mt-2 font-medium">Bitte wählen Sie ein neues Passwort.</p>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="px-8 pb-8 text-center">
                <span class="material-symbols-outlined mx-auto text-4xl text-primary animate-pulse">hourglass_top</span>
                <p class="text-sm text-gray-500 mt-3">Link wird überprüft...</p>
            </div>

            <!-- Invalid Token State -->
            <div id="invalidState" class="hidden px-8 pb-8">
                <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-red-500 text-4xl mb-3 block">link_off</span>
                    <p class="text-sm text-red-700 font-bold mb-2">Link ungültig oder abgelaufen</p>
                    <p class="text-xs text-red-600">Bitte fordern Sie einen neuen Link an.</p>
                </div>
                <a href="passwort-vergessen.php" class="mt-4 block w-full text-center h-12 leading-[3rem] bg-primary hover:bg-primary-dark text-white font-bold rounded-lg shadow-lg shadow-primary/20 transition-all">
                    Neuen Link anfordern
                </a>
            </div>

            <!-- Alert Box -->
            <div id="alertBox" class="hidden mx-8 mb-4 p-3 rounded-lg text-sm font-medium flex items-center gap-2"></div>

            <!-- Set Password Form -->
            <form id="setPasswordForm" class="hidden px-8 pb-8 space-y-6">
                <!-- Welcome info for invitations -->
                <div id="invitationInfo" class="hidden bg-green-50 border border-green-200 rounded-xl p-4">
                    <p class="text-sm text-green-700 font-medium">
                        <span class="material-symbols-outlined text-green-500 text-lg align-middle mr-1">waving_hand</span>
                        Willkommen, <strong id="welcomeName"></strong>! Legen Sie jetzt Ihr Passwort fest.
                    </p>
                    <p class="text-xs text-green-600 mt-1">Benutzername: <strong id="welcomeUsername" class="font-mono"></strong></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="newPassword">Neues Passwort</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">lock</span>
                        <input class="w-full h-12 pl-10 pr-12 bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                            id="newPassword" name="new_password" placeholder="Min. 8 Zeichen" type="password" required minlength="8" autofocus>
                        <button type="button" id="togglePw1" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none">
                            <span class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="confirmPassword">Passwort bestätigen</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">lock_reset</span>
                        <input class="w-full h-12 pl-10 pr-12 bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                            id="confirmPassword" name="confirm_password" placeholder="Passwort wiederholen" type="password" required minlength="8">
                        <button type="button" id="togglePw2" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none">
                            <span class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Password strength indicator -->
                <div class="space-y-2">
                    <div class="flex gap-1">
                        <div id="str1" class="h-1 flex-1 rounded-full bg-gray-200 transition-colors"></div>
                        <div id="str2" class="h-1 flex-1 rounded-full bg-gray-200 transition-colors"></div>
                        <div id="str3" class="h-1 flex-1 rounded-full bg-gray-200 transition-colors"></div>
                        <div id="str4" class="h-1 flex-1 rounded-full bg-gray-200 transition-colors"></div>
                    </div>
                    <p id="strText" class="text-xs text-gray-400"></p>
                </div>

                <button type="submit" id="submitBtn" class="w-full h-12 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg shadow-lg shadow-primary/20 transform transition-all active:scale-[0.98] focus:outline-none flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">check</span>
                    Passwort festlegen
                </button>
            </form>

            <!-- Success State -->
            <div id="successState" class="hidden px-8 pb-8">
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-green-500 text-4xl mb-3 block">check_circle</span>
                    <p class="text-sm text-green-700 font-bold mb-2" id="successMessage">Passwort erfolgreich festgelegt!</p>
                    <p class="text-xs text-green-600">Sie werden zum Login weitergeleitet...</p>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 flex justify-between items-center">
                <a class="text-xs text-gray-500 hover:text-primary flex items-center gap-1 transition-colors" href="index.php">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    Zum Login
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
    const token = new URLSearchParams(window.location.search).get('token');

    // Password visibility toggles
    document.getElementById('togglePw1').addEventListener('click', () => {
        const inp = document.getElementById('newPassword');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        document.getElementById('togglePw1').querySelector('.material-symbols-outlined').textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
    });
    document.getElementById('togglePw2').addEventListener('click', () => {
        const inp = document.getElementById('confirmPassword');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        document.getElementById('togglePw2').querySelector('.material-symbols-outlined').textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
    });

    // Password strength indicator
    document.getElementById('newPassword').addEventListener('input', (e) => {
        const pw = e.target.value;
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;

        const colors = ['bg-gray-200', 'bg-red-400', 'bg-yellow-400', 'bg-green-400', 'bg-green-500'];
        const labels = ['', 'Schwach', 'Mittel', 'Stark', 'Sehr stark'];

        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('str' + i);
            el.className = 'h-1 flex-1 rounded-full transition-colors ' + (i <= score ? colors[score] : 'bg-gray-200');
        }
        document.getElementById('strText').textContent = labels[score] || '';
    });

    // Verify token on load
    (async function() {
        if (!token) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('invalidState').classList.remove('hidden');
            return;
        }

        try {
            const res = await fetch('../api/auth.php?action=verify_token&token=' + encodeURIComponent(token));
            const data = await res.json();

            document.getElementById('loadingState').classList.add('hidden');

            if (data.valid) {
                document.getElementById('setPasswordForm').classList.remove('hidden');

                if (data.type === 'invitation') {
                    document.getElementById('pageTitle').textContent = 'Willkommen bei WKC';
                    document.getElementById('pageSubtitle').textContent = 'Legen Sie Ihr persönliches Passwort fest.';
                    document.getElementById('invitationInfo').classList.remove('hidden');
                    document.getElementById('welcomeName').textContent = data.display_name;
                    document.getElementById('welcomeUsername').textContent = data.username;
                } else {
                    document.getElementById('pageTitle').textContent = 'Neues Passwort festlegen';
                    document.getElementById('pageSubtitle').textContent = 'Wählen Sie ein sicheres Passwort.';
                }
            } else {
                document.getElementById('invalidState').classList.remove('hidden');
            }
        } catch {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('invalidState').classList.remove('hidden');
        }
    })();

    // Handle form submission
    document.getElementById('setPasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const alertBox = document.getElementById('alertBox');
        alertBox.classList.add('hidden');

        const newPw = document.getElementById('newPassword').value;
        const confirmPw = document.getElementById('confirmPassword').value;

        if (newPw !== confirmPw) {
            alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
            alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> Passwörter stimmen nicht überein.';
            return;
        }
        if (newPw.length < 8) {
            alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
            alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> Passwort muss mindestens 8 Zeichen lang sein.';
            return;
        }

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Wird gespeichert...';

        try {
            const formData = new FormData();
            formData.append('token', token);
            formData.append('new_password', newPw);
            formData.append('confirm_password', confirmPw);

            const res = await fetch('../api/auth.php?action=set_password', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                document.getElementById('setPasswordForm').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');
                document.getElementById('successMessage').textContent = data.message;
                setTimeout(() => { window.location.href = 'index.php'; }, 2500);
            } else {
                alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
                alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> ' + (data.error || 'Fehler beim Speichern.');
            }
        } catch {
            alertBox.className = 'mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2';
            alertBox.innerHTML = '<span class="material-symbols-outlined text-lg">error</span> Verbindungsfehler.';
        }

        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">check</span> Passwort festlegen';
    });
    </script>
</body>
</html>
