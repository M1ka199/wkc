/**
 * Loads global CMS settings and applies theme, branding and integrations.
 */
(function initSiteConfig() {
    const GOOGLE_FONT_BASE = 'https://fonts.googleapis.com/css2?family=';

    function slugifyFont(fontName) {
        return String(fontName || '').trim().replace(/\s+/g, '+');
    }

    function ensureFontLoaded(fontName) {
        const font = String(fontName || '').trim();
        if (!font) return;
        if (font.toLowerCase() === 'forte') return; // local/system font

        const href = `${GOOGLE_FONT_BASE}${slugifyFont(font)}:wght@300;400;500;600;700;800;900&display=swap`;
        if (document.querySelector(`link[data-dynamic-font="${font}"]`)) return;

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute('data-dynamic-font', font);
        document.head.appendChild(link);
    }

    function applyTheme(theme) {
        if (!theme || typeof theme !== 'object') return;

        const root = document.documentElement;
        const map = {
            primary: '--site-primary',
            secondary: '--site-secondary',
            accent: '--site-accent',
            background: '--site-background',
            surface: '--site-surface',
            text: '--site-text',
        };

        Object.entries(map).forEach(([key, cssVar]) => {
            if (theme[key]) root.style.setProperty(cssVar, String(theme[key]));
        });
    }

    function applyTypography(typography) {
        if (!typography || typeof typography !== 'object') return;
        const root = document.documentElement;

        if (typography.headingFont) {
            const headingFont = String(typography.headingFont);
            ensureFontLoaded(headingFont);
            root.style.setProperty('--site-font-heading', `"${headingFont}", "Public Sans", sans-serif`);
        }
        if (typography.bodyFont) {
            const bodyFont = String(typography.bodyFont);
            ensureFontLoaded(bodyFont);
            root.style.setProperty('--site-font-body', `"${bodyFont}", "Public Sans", sans-serif`);
        }
    }

    function applyBrandLogos(branding) {
        const headerLogo = String(branding?.logoHeader || '/src/wkc-logo.svg').trim();
        const footerLogo = String(branding?.logoFooter || '/src/wkc-logo-white.svg').trim();

        document.querySelectorAll('[data-brand-logo="header"]').forEach((el) => {
            if (el.tagName === 'IMG' && headerLogo) {
                el.setAttribute('src', headerLogo);
            }
        });
        document.querySelectorAll('[data-brand-logo="footer"]').forEach((el) => {
            if (el.tagName === 'IMG' && footerLogo) {
                el.setAttribute('src', footerLogo);
            }
        });
    }

    function applyBranding(branding) {
        if (!branding || typeof branding !== 'object') return;

        if (branding.siteName) {
            document.querySelectorAll('[data-site-name]').forEach((el) => {
                el.textContent = String(branding.siteName);
            });
        }

        if (branding.favicon) {
            let favicon = document.querySelector('link[rel="icon"]');
            if (!favicon) {
                favicon = document.createElement('link');
                favicon.rel = 'icon';
                document.head.appendChild(favicon);
            }
            favicon.href = String(branding.favicon);
        }

        applyBrandLogos(branding);
    }

    function applyIntegrations(integrations) {
        if (!integrations || typeof integrations !== 'object') return;

        const gaId = String(integrations.googleAnalyticsId || '').trim();
        if (gaId && !document.querySelector('script[data-ga-managed="1"]')) {
            const gaScript = document.createElement('script');
            gaScript.async = true;
            gaScript.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`;
            gaScript.dataset.gaManaged = '1';
            document.head.appendChild(gaScript);

            const inline = document.createElement('script');
            inline.dataset.gaManaged = '1';
            inline.textContent = `window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', '${gaId}');`;
            document.head.appendChild(inline);
        }

        const customHeadCode = String(integrations.customHeadCode || '');
        if (customHeadCode && !document.getElementById('cms-custom-head')) {
            const container = document.createElement('div');
            container.id = 'cms-custom-head';
            container.innerHTML = customHeadCode;
            while (container.firstChild) {
                document.head.appendChild(container.firstChild);
            }
        }

        const customBodyCode = String(integrations.customBodyCode || '');
        if (customBodyCode && !document.getElementById('cms-custom-body')) {
            const wrapper = document.createElement('div');
            wrapper.id = 'cms-custom-body';
            wrapper.innerHTML = customBodyCode;
            document.body.appendChild(wrapper);
        }

        const siteKey = String(integrations.cloudflareTurnstileSiteKey || '').trim();
        if (siteKey && !document.querySelector('script[src*="turnstile"]')) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            const injectWidget = (form) => {
                if (!form || form.querySelector('.cf-turnstile')) return;
                const slot = document.createElement('div');
                slot.className = 'cf-turnstile mt-4';
                slot.setAttribute('data-sitekey', siteKey);
                form.appendChild(slot);
            };

            injectWidget(document.getElementById('contact-form'));
            injectWidget(document.getElementById('membership-form'));
        }
    }

    function applyFormToggles(forms) {
        if (!forms || typeof forms !== 'object') return;

        const applyFieldConfig = (form, fields) => {
            if (!form || !Array.isArray(fields)) return;
            fields.forEach((fieldCfg) => {
                const name = fieldCfg?.name;
                if (!name) return;
                const input = form.querySelector(`[name="${name}"]`);
                if (!input) return;

                const label = form.querySelector(`label[for="${input.id}"]`);
                if (label && fieldCfg.label) {
                    label.textContent = fieldCfg.label + (fieldCfg.required ? ' *' : '');
                }
                input.required = !!fieldCfg.required;
            });
        };

        applyFieldConfig(document.getElementById('contact-form'), forms.contact?.fields);
        applyFieldConfig(document.getElementById('membership-form'), forms.membership?.fields);

        if (forms.membership && forms.membership.enabled === false) {
            document.querySelectorAll('#mitglied-werden, #membership-form').forEach((el) => {
                const section = el.closest('section') || el;
                if (section) section.style.display = 'none';
            });
        }
        if (forms.contact && forms.contact.enabled === false) {
            document.querySelectorAll('#kontakt, #contact-form').forEach((el) => {
                const section = el.closest('section') || el;
                if (section) section.style.display = 'none';
            });
        }
    }

    function applyHomepageToggles(homepage) {
        if (!homepage || typeof homepage !== 'object') return;
        if (homepage.heroEnabled === false) {
            const hero = document.getElementById('home-hero');
            if (hero) hero.style.display = 'none';
        }
        if (homepage.newsEnabled === false) {
            const news = document.querySelector('[data-home-news]');
            if (news) news.style.display = 'none';
        }
        if (homepage.eventsEnabled === false) {
            const eventsSection = document.getElementById('termine') || document.querySelector('[data-home-events]');
            if (eventsSection) eventsSection.style.display = 'none';
        }
        if (homepage.galleryPreviewEnabled === false) {
            const gallery = document.querySelector('[data-home-gallery]');
            if (gallery) gallery.style.display = 'none';
        }
    }

    function applyFeatureToggles(features) {
        if (!features || typeof features !== 'object') return;

        if (features.politicsEnabled === false) {
            document.querySelectorAll('[data-feature="politics"]').forEach((el) => {
                el.style.display = 'none';
            });
            document.querySelectorAll('a[href*="kommunal"], a[href*="themen"], a[href*="wahl"], a[href*="politik"]').forEach((el) => {
                el.style.display = 'none';
            });
        }
    }

    async function loadSettings() {
        try {
            const res = await fetch('/api/settings.php');
            const data = await res.json();
            const settings = data.settings || {};

            applyTheme(settings.theme || {});
            applyTypography(settings.typography || {});
            applyBranding(settings.branding || {});
            applyIntegrations(settings.integrations || {});
            applyFormToggles(settings.forms || {});
            applyHomepageToggles(settings.homepage || {});
            applyFeatureToggles(settings.features || {});

            window.CMS_SETTINGS = settings;
            document.dispatchEvent(new CustomEvent('cms:settings-loaded', { detail: settings }));
        } catch (err) {
            console.warn('CMS settings could not be loaded:', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSettings);
    } else {
        loadSettings();
    }
})();
