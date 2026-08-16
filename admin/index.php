<?php
/**
 * WKC – Admin Login
 */
require_once __DIR__ . '/../api/config.php';
header('Content-Type: text/html; charset=utf-8');
session_name(SESSION_NAME);
session_start();

// Already logged in? Redirect to dashboard
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
    <title>Login – WKC Backend</title>
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
        input[type="checkbox"]:checked { background-color: #7c3aed !important; border-color: #7c3aed !important; }
    </style>
</head>
<body class="bg-bg-light min-h-screen flex flex-col">
    <!-- Decorative gradient bar -->
    <div class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-primary/20 via-primary to-primary/20 z-50"></div>

    <!-- Main Content -->
    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-[440px] bg-white rounded-xl shadow-xl shadow-primary/5 overflow-hidden border border-primary/10">
            <!-- Header -->
            <div class="pt-10 pb-6 px-8 text-center">
                <div class="flex justify-center mb-6">
                    <img src="../src/wkc-logo.svg" alt="WKC Logo" class="h-16" onerror="this.style.display='none'">
                </div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight" style="display: none;">Mitglieder Login</h1>
                <p class="text-sm text-gray-500 mt-2 font-medium" style="display: none;">Anmelden, um WKC zu verwalten</p>
            </div>

            <!-- Error message -->
            <div id="loginError" class="hidden mx-8 mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">error</span>
                <span id="loginErrorText"></span>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="px-8 pb-10 space-y-6">
                <!-- Username -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">Benutzername</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">person</span>
                        <input
                            class="w-full h-12 pl-10 pr-4 bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                            id="username" name="username" placeholder="Benutzername eingeben" type="text" required autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Passwort</label>
                    <div class="relative flex items-center">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">lock</span>
                        <input
                            class="w-full h-12 pl-10 pr-12 bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                            id="password" name="password" placeholder="••••••••" type="password" required>
                        <button
                            type="button"
                            id="togglePassword"
                            class="absolute right-3 text-gray-400 hover:text-primary transition-colors focus:outline-none">
                            <span class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Remember -->
                <div class="flex items-center">
                    <input class="h-5 w-5 rounded border-gray-300 text-primary focus:ring-0 cursor-pointer" id="remember" type="checkbox">
                    <label class="ml-3 text-sm font-medium text-gray-600 cursor-pointer" for="remember">Angemeldet bleiben</label>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    id="loginBtn"
                    class="w-full h-12 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg shadow-lg shadow-primary/20 transform transition-all active:scale-[0.98] focus:outline-none flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">login</span>
                    Anmelden
                </button>

                <!-- Forgot Password -->
                <div class="text-center">
                    <a href="passwort-vergessen.php" class="text-sm text-gray-500 hover:text-primary transition-colors font-medium">
                        Passwort vergessen?
                    </a>
                </div>
            </form>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 flex justify-between items-center">
                <span class="text-xs text-gray-400 font-medium italic">WKC CMS</span>
                <a class="text-xs text-gray-500 hover:text-primary flex items-center gap-1 transition-colors" href="../">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    Zur Webseite
                </a>
            </div>
        </div>
    </div>

    <!-- Page Footer -->
    <footer class="py-6 px-4 text-center">
        <p class="text-sm text-gray-500 font-medium">
            &copy; 2025 WKC e.V.
        </p>
    </footer>

    <!-- Decorative blur elements -->
    <div class="fixed -bottom-24 -left-24 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed top-1/2 right-0 -translate-y-1/2 w-32 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

    <script src="js/admin-theme.js?v=20260816-2"></script>
    <script src="js/login.js"></script>
</body>
</html>
