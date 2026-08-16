/**
 * WKC – Login Page JavaScript
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const errorBox = document.getElementById('loginError');
    const errorText = document.getElementById('loginErrorText');
    const loginBtn = document.getElementById('loginBtn');
    const togglePw = document.getElementById('togglePassword');
    const pwInput = document.getElementById('password');

    // Toggle password visibility
    togglePw.addEventListener('click', () => {
        const isPassword = pwInput.type === 'password';
        pwInput.type = isPassword ? 'text' : 'password';
        togglePw.querySelector('.material-symbols-outlined').textContent = isPassword ? 'visibility_off' : 'visibility';
    });

    /**
     * Send login request with automatic retry on server errors.
     * OneDrive file-locking can cause intermittent PHP read failures.
     */
    async function attemptLogin(formData, retries = 2) {
        const res = await fetch('../api/auth.php?action=login', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });

        // Server-side read error (errno=11) â†’ retry
        if (res.status >= 500 && retries > 0) {
            await new Promise(r => setTimeout(r, 600));
            return attemptLogin(formData, retries - 1);
        }

        // Try to parse JSON; if the response isn't valid JSON show the raw text
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            throw new Error(
                res.status >= 500
                    ? 'Server-Fehler (' + res.status + '). Bitte Seite neu laden und erneut versuchen.'
                    : 'Ungültige Server-Antwort (' + res.status + ').'
            );
        }
    }

    // Handle login
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.classList.add('hidden');
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Anmelden...';

        const formData = new FormData(form);
        formData.append('action', 'login');

        try {
            const data = await attemptLogin(formData);

            if (data.success) {
                window.location.href = 'dashboard.php';
            } else {
                errorText.textContent = data.error || 'Anmeldung fehlgeschlagen.';
                errorBox.classList.remove('hidden');
            }
        } catch (err) {
            errorText.textContent = err.message || 'Verbindungsfehler. Bitte versuche es erneut.';
            errorBox.classList.remove('hidden');
        } finally {
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<span class="material-symbols-outlined">login</span> Anmelden';
        }
    });
});
