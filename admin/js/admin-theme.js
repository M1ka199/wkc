/**
 * Shared admin theme loader.
 * Applies CMS design settings (theme, typography, branding) across admin pages.
 */
(function initAdminTheme() {
    const GOOGLE_FONT_BASE = 'https://fonts.googleapis.com/css2?family=';

    function slugifyFont(fontName) {
        return String(fontName || '').trim().replace(/\s+/g, '+');
    }

    function ensureFontLoaded(fontName) {
        const font = String(fontName || '').trim();
        if (!font) return;
        if (font.toLowerCase() === 'forte') return;
        if (document.querySelector(`link[data-admin-font="${font}"]`)) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `${GOOGLE_FONT_BASE}${slugifyFont(font)}:wght@300;400;500;600;700;800;900&display=swap`;
        link.setAttribute('data-admin-font', font);
        document.head.appendChild(link);
    }

    function injectThemeCssOnce() {
        if (document.getElementById('admin-theme-runtime-css')) return;
        const style = document.createElement('style');
        style.id = 'admin-theme-runtime-css';
        style.textContent = `
            :root {
                --admin-primary: #7c3aed;
                --admin-secondary: #5b21b6;
                --admin-accent: #a78bfa;
                --admin-bg: #f5f8f7;
                --admin-surface: #ffffff;
                --admin-text: #111827;
                --admin-font-heading: "Public Sans", sans-serif;
                --admin-font-body: "Public Sans", sans-serif;
            }

            body { font-family: var(--admin-font-body); background-color: var(--admin-bg); color: var(--admin-text); }
            h1, h2, h3, h4, h5, h6 { font-family: var(--admin-font-heading); }

            .bg-primary { background-color: var(--admin-primary) !important; }
            .text-primary { color: var(--admin-primary) !important; }
            .border-primary { border-color: var(--admin-primary) !important; }
            .hover\\:bg-primary:hover { background-color: var(--admin-primary) !important; }
            .hover\\:text-primary:hover { color: var(--admin-primary) !important; }

            .bg-primary\\/10 { background-color: color-mix(in srgb, var(--admin-primary) 10%, transparent) !important; }
            .bg-primary\\/15 { background-color: color-mix(in srgb, var(--admin-primary) 15%, transparent) !important; }
            .bg-primary\\/20 { background-color: color-mix(in srgb, var(--admin-primary) 20%, transparent) !important; }

            .from-primary { --tw-gradient-from: var(--admin-primary) var(--tw-gradient-from-position) !important; }
            .to-primary-dark { --tw-gradient-to: var(--admin-secondary) var(--tw-gradient-to-position) !important; }

            .sidebar-link.active {
                background: color-mix(in srgb, var(--admin-primary) 12%, transparent) !important;
                color: var(--admin-primary) !important;
            }

            .sidebar-header {
                position: relative;
                justify-content: center !important;
            }

            .sidebar-header #collapseBtn {
                position: absolute;
                right: 0;
            }

            .sidebar-logo-link {
                display: block;
                margin-inline: auto;
                text-align: center;
            }

            .sidebar-logo-link img {
                max-width: 6rem !important;
                margin-inline: auto;
            }
        `;
        document.head.appendChild(style);
    }

    function applyTheme(theme) {
        if (!theme) return;
        const root = document.documentElement;
        if (theme.primary) root.style.setProperty('--admin-primary', theme.primary);
        if (theme.secondary) root.style.setProperty('--admin-secondary', theme.secondary);
        if (theme.accent) root.style.setProperty('--admin-accent', theme.accent);
        if (theme.background) root.style.setProperty('--admin-bg', theme.background);
        if (theme.surface) root.style.setProperty('--admin-surface', theme.surface);
        if (theme.text) root.style.setProperty('--admin-text', theme.text);
    }

    function applyTypography(typography) {
        if (!typography) return;
        const root = document.documentElement;
        if (typography.headingFont) {
            ensureFontLoaded(typography.headingFont);
            root.style.setProperty('--admin-font-heading', `"${typography.headingFont}", sans-serif`);
        }
        if (typography.bodyFont) {
            ensureFontLoaded(typography.bodyFont);
            root.style.setProperty('--admin-font-body', `"${typography.bodyFont}", sans-serif`);
        }
    }

    function applyAdminBrandingLogos(branding) {
        const logoHeader = String(branding?.logoHeader || '../src/wkc-logo.svg').trim();
        const logoFooter = String(branding?.logoFooter || '../src/wkc-logo-white.svg').trim();

        document.querySelectorAll('[data-admin-brand-logo="header"], [data-brand-logo="header"], .sidebar-logo-link img').forEach((el) => {
            if (el.tagName === 'IMG' && logoHeader) {
                el.setAttribute('src', logoHeader);
            }
        });
        document.querySelectorAll('[data-brand-logo="footer"]').forEach((el) => {
            if (el.tagName === 'IMG' && logoFooter) {
                el.setAttribute('src', logoFooter);
            }
        });
    }

    function applyBranding(branding) {
        if (!branding) return;
        if (branding.siteName) {
            document.querySelectorAll('[data-admin-site-name]').forEach((el) => {
                el.textContent = branding.siteName;
            });
        }
        if (branding.favicon) {
            let favicon = document.querySelector('link[rel="icon"]');
            if (!favicon) {
                favicon = document.createElement('link');
                favicon.rel = 'icon';
                document.head.appendChild(favicon);
            }
            favicon.href = branding.favicon;
        }
        applyAdminBrandingLogos(branding);
    }

    async function loadAdminSettings() {
        injectThemeCssOnce();
        try {
            const res = await fetch('../api/settings.php?scope=admin', { credentials: 'include' });
            if (!res.ok) return;
            const data = await res.json();
            const settings = data.settings || {};
            applyTheme(settings.theme || {});
            applyTypography(settings.typography || {});
            applyBranding(settings.branding || {});
            window.CMS_ADMIN_SETTINGS = settings;
            document.dispatchEvent(new CustomEvent('cms:admin-settings-loaded', { detail: settings }));
        } catch (err) {
            console.warn('Admin settings could not be loaded:', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAdminSettings);
    } else {
        loadAdminSettings();
    }
})();
