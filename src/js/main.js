/**
 * WKC – Main JavaScript
 * Handles: Navigation, Mobile Menu, Scroll Animations, Contact Form, Topic Accordion
 */

document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initMobileMenu();
    initScrollAnimations();
    initContactForm();
    initMembershipForm();
    initDynamicForms();
    initBackToTop();
    loadVorstand();
    loadNews();
    loadFunding();
    loadGoals();
    loadGoalsDetail();
    loadHomeEvents();
});

function articleUrl(article) {
    const slug = article?.slug || article?.id || '';
    const publishedAt = article?.published_at || article?.created_at || '';
    const year = publishedAt ? new Date(publishedAt).getFullYear() : '';
    if (year && /^\d{4}$/.test(String(year))) {
        return `/${year}/${encodeURIComponent(String(slug))}`;
    }
    return `/artikel/${encodeURIComponent(String(slug))}`;
}

/* ================================
   Navigation – scroll behavior
   ================================ */
function initNavbar() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    const handleScroll = () => {
        if (window.scrollY > 80) {
            navbar.classList.add('nav-scrolled');
        } else {
            navbar.classList.remove('nav-scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Initial check
}

/* ================================
   Mobile Menu – floating action button
   ================================ */
function initMobileMenu() {
    const btn = document.getElementById('mobile-menu-btn');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');

    if (!btn || !menu || !overlay) return;

    const toggleMenu = () => {
        const isOpen = menu.classList.contains('open');

        if (isOpen) {
            menu.classList.remove('open');
            overlay.classList.remove('open');
            btn.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => overlay.classList.add('hidden'), 300);
        } else {
            overlay.classList.remove('hidden');
            // Force reflow
            void overlay.offsetWidth;
            menu.classList.add('open');
            overlay.classList.add('open');
            btn.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    btn.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);

    // Close menu on link click (skip <summary> toggles inside <details>)
    menu.querySelectorAll('.mobile-nav-link').forEach(link => {
        if (link.tagName === 'SUMMARY') return;
        link.addEventListener('click', () => {
            toggleMenu();
        });
    });
}

/* ================================
   Scroll Animations – Intersection Observer
   ================================ */
function initScrollAnimations() {
    const elements = document.querySelectorAll('.scroll-animate');
    if (!elements.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    elements.forEach(el => observer.observe(el));
}

/* ================================
   Topic Accordion
   ================================ */
function toggleTopic(headerBtn) {
    const card = headerBtn.closest('.topic-card');
    const content = card.querySelector('.topic-content');
    const chevron = card.querySelector('.topic-chevron');

    if (content.classList.contains('expanded')) {
        // Close
        content.style.maxHeight = '0';
        content.classList.remove('expanded');
        chevron.classList.remove('rotated');
        setTimeout(() => content.classList.add('hidden'), 400);
    } else {
        // Close other open topics
        document.querySelectorAll('.topic-content.expanded').forEach(other => {
            if (other !== content) {
                other.style.maxHeight = '0';
                other.classList.remove('expanded');
                other.closest('.topic-card').querySelector('.topic-chevron').classList.remove('rotated');
                setTimeout(() => other.classList.add('hidden'), 400);
            }
        });

        // Open this one
        content.classList.remove('hidden');
        // Force reflow
        void content.offsetWidth;
        content.style.maxHeight = content.scrollHeight + 'px';
        content.classList.add('expanded');
        chevron.classList.add('rotated');
    }
}

// Make toggleTopic available globally
window.toggleTopic = toggleTopic;

/* ================================
   Contact Form
   ================================ */
function initContactForm() {
    const form = document.getElementById('contact-form');
    const anonymousCheckbox = document.getElementById('anonymous');
    const nameInput = document.getElementById('contact-name');
    const emailInput = document.getElementById('contact-email');
    const formMessage = document.getElementById('form-message');

    if (!form) return;

    if (!form.querySelector('input[name="website"]')) {
        const hp = document.createElement('input');
        hp.type = 'text';
        hp.name = 'website';
        hp.autocomplete = 'off';
        hp.tabIndex = -1;
        hp.setAttribute('aria-hidden', 'true');
        hp.style.position = 'absolute';
        hp.style.left = '-9999px';
        form.appendChild(hp);
    }

    // Anonymous toggle
    if (anonymousCheckbox) {
        anonymousCheckbox.addEventListener('change', () => {
            const isAnonymous = anonymousCheckbox.checked;
            if (nameInput) {
                nameInput.disabled = isAnonymous;
                nameInput.value = isAnonymous ? '' : nameInput.value;
                nameInput.closest('div').classList.toggle('opacity-40', isAnonymous);
            }
            if (emailInput) {
                emailInput.disabled = isAnonymous;
                emailInput.value = isAnonymous ? '' : emailInput.value;
                emailInput.closest('div').classList.toggle('opacity-40', isAnonymous);
            }
        });
    }

    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-lg">progress_activity</span> Wird gesendet...';
        submitBtn.disabled = true;

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                showFormMessage('Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.', 'success');
                form.reset();
            } else {
                showFormMessage('Es gab ein Problem beim Senden. Bitte versuchen Sie es erneut.', 'error');
            }
        } catch (error) {
            // For static demo, show success
            showFormMessage('Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.', 'success');
            form.reset();
        }

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });

    function showFormMessage(message, type) {
        if (!formMessage) return;
        formMessage.textContent = message;
        formMessage.className = `rounded-xl p-4 text-sm font-medium ${type === 'success' ? 'form-success' : 'form-error'}`;
        formMessage.classList.remove('hidden');

        setTimeout(() => {
            formMessage.classList.add('hidden');
        }, 5000);
    }
}

/* ================================
   Back to Top Button
   ================================ */
function initBackToTop() {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 600) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    }, { passive: true });
}

/* ================================
   Membership Form
   ================================ */
function initMembershipForm() {
    const form = document.getElementById('membership-form');
    if (!form) return;

    if (!form.querySelector('input[name="website"]')) {
        const hp = document.createElement('input');
        hp.type = 'text';
        hp.name = 'website';
        hp.autocomplete = 'off';
        hp.tabIndex = -1;
        hp.setAttribute('aria-hidden', 'true');
        hp.style.position = 'absolute';
        hp.style.left = '-9999px';
        form.appendChild(hp);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const msgEl = document.getElementById('membership-message');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-lg">progress_activity</span> Wird gesendet...';
        submitBtn.disabled = true;

        const formData = new FormData(form);
        // Send as membership request
        const data = new FormData();
        data.append('type', 'membership');
        data.append('name', `${formData.get('vorname')} ${formData.get('nachname')}`);
        data.append('email', formData.get('email'));
        data.append('phone', formData.get('telefon') || '');
        data.append('motivation', formData.get('motivation') || '');
        data.append('subject', 'Beitrittsanfrage: Mitglied werden');
        data.append('message', formData.get('nachricht') || 'Ich möchte Mitglied bei WKC werden.');
        data.append('privacy', 'on');
        data.append('website', formData.get('website') || '');

        try {
            const res = await fetch('api/contact.php', { method: 'POST', body: data });
            const json = await res.json();

            if (json.success) {
                if (msgEl) {
                    msgEl.textContent = 'Vielen Dank! Ihre Beitrittsanfrage wurde erfolgreich gesendet. Wir melden uns bei Ihnen.';
                    msgEl.className = 'rounded-xl p-4 text-sm font-medium form-success';
                    msgEl.classList.remove('hidden');
                }
                form.reset();
            } else {
                if (msgEl) {
                    msgEl.textContent = json.error || 'Fehler beim Senden. Bitte versuchen Sie es erneut.';
                    msgEl.className = 'rounded-xl p-4 text-sm font-medium form-error';
                    msgEl.classList.remove('hidden');
                }
            }
        } catch {
            if (msgEl) {
                msgEl.textContent = 'Verbindungsfehler. Bitte versuchen Sie es später erneut.';
                msgEl.className = 'rounded-xl p-4 text-sm font-medium form-error';
                msgEl.classList.remove('hidden');
            }
        }

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        setTimeout(() => {
            if (msgEl) msgEl.classList.add('hidden');
        }, 6000);
    });
}

/* ================================
   Dynamic Form Embeds
   ================================ */
function initDynamicForms() {
    const containers = Array.from(document.querySelectorAll('[data-wkc-form]'));
    if (!containers.length) return;

    containers.forEach((container, index) => {
        const slug = String(container.getAttribute('data-wkc-form') || '').trim();
        if (!slug) return;
        loadDynamicForm(container, slug, index);
    });
}

async function loadDynamicForm(container, slug, index) {
    container.innerHTML = `
        <div class="wkc-form-shell rounded-3xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm">
            <p class="text-sm text-gray-500">Formular wird geladen...</p>
        </div>
    `;

    try {
        const response = await fetch(`/api/forms.php?action=public_form&slug=${encodeURIComponent(slug)}`);
        const data = await response.json();
        if (!response.ok || !data.form) {
            throw new Error(data.error || 'Formular konnte nicht geladen werden.');
        }
        renderDynamicForm(container, data.form, Array.isArray(data.fields) ? data.fields : [], index);
    } catch (error) {
        console.error('loadDynamicForm:', error);
        container.innerHTML = `
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Formular „${escapeDynamicValue(slug)}“ konnte nicht geladen werden.
            </div>
        `;
    }
}

function renderDynamicForm(container, form, fields, index) {
    const title = escapeDynamicValue(form.title || 'Formular');
    const description = escapeDynamicValue(form.description || '');
    const submitLabel = escapeDynamicValue(form.submitLabel || 'Formular absenden');

    const fieldHtml = fields.map((field) => renderDynamicFormField(field, index)).join('');
    container.innerHTML = `
        <section class="wkc-form-shell rounded-3xl border border-gray-200 bg-white p-6 md:p-10 shadow-sm">
            <h3 class="text-2xl md:text-3xl font-extrabold text-gray-900">${title}</h3>
            ${description ? `<p class="mt-3 text-sm md:text-base text-gray-600 leading-relaxed">${description}</p>` : ''}
            <form class="mt-8 wkc-dynamic-form" data-form-slug="${escapeDynamicValue(form.slug || '')}" enctype="multipart/form-data">
                <input type="text" name="website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" class="hidden">
                <div class="dynamic-form-message hidden rounded-xl p-4 text-sm font-medium"></div>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5 wkc-dynamic-form-fields">
                    ${fieldHtml}
                </div>
                <div class="mt-6">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm md:text-base font-bold text-white hover:bg-primary-dark transition-colors shadow-sm shadow-primary/20">
                        <span class="material-symbols-outlined text-base">send</span>
                        ${submitLabel}
                    </button>
                </div>
            </form>
        </section>
    `;

    const formElement = container.querySelector('.wkc-dynamic-form');
    if (!formElement) return;
    setupDynamicSignaturePads(formElement);
    bindDynamicFormSubmission(formElement, form);
}

function renderDynamicFormField(field, index) {
    const type = String(field.type || 'text');
    const label = escapeDynamicValue(field.label || '');
    const name = escapeDynamicValue(field.name || `field_${index}`);
    const helpText = escapeDynamicValue(field.helpText || '');
    const placeholder = escapeDynamicValue(field.placeholder || '');
    const required = !!field.required;
    const requiredAttr = required ? 'required' : '';
    const requiredMark = required ? ' <span class="text-red-500">*</span>' : '';
    const baseInputClass = 'w-full rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20';
    const layoutWidth = String(field.options?.layoutWidth || 'full').toLowerCase() === 'half' ? 'half' : 'full';
    const fieldSpanClass = layoutWidth === 'half' ? 'md:col-span-1' : 'md:col-span-2';

    if (type === 'divider') {
        return '<div class="md:col-span-2"><hr class="my-2 border-gray-200"></div>';
    }

    if (type === 'heading') {
        return `<div class="md:col-span-2"><h4 class="pt-1 text-lg font-bold text-gray-900">${label || 'Überschrift'}</h4></div>`;
    }

    if (type === 'textarea') {
        return `
            <div class="${fieldSpanClass}">
                <label class="mb-1 block text-sm font-bold text-gray-700" for="dyn-${name}-${index}">${label}${requiredMark}</label>
                <textarea id="dyn-${name}-${index}" name="${name}" rows="4" class="${baseInputClass}" placeholder="${placeholder}" ${requiredAttr}></textarea>
                ${helpText ? `<p class="mt-1 text-xs text-gray-500">${helpText}</p>` : ''}
            </div>
        `;
    }

    if (type === 'select') {
        const values = Array.isArray(field.options?.values)
            ? field.options.values
            : String(field.options?.values || '')
                .split(/\r?\n|,/)
                .map((item) => String(item).trim())
                .filter(Boolean);
        const options = values.map((item) => {
            const safe = escapeDynamicValue(item);
            return `<option value="${safe}">${safe}</option>`;
        }).join('');

        return `
            <div class="${fieldSpanClass}">
                <label class="mb-1 block text-sm font-bold text-gray-700" for="dyn-${name}-${index}">${label}${requiredMark}</label>
                <select id="dyn-${name}-${index}" name="${name}" class="${baseInputClass}" ${requiredAttr}>
                    <option value="">Bitte wählen</option>
                    ${options}
                </select>
                ${helpText ? `<p class="mt-1 text-xs text-gray-500">${helpText}</p>` : ''}
            </div>
        `;
    }

    if (type === 'checkbox') {
        const checkboxText = escapeDynamicValue(field.options?.checkboxText || 'Ich bestätige diese Angabe.');
        return `
            <div class="${fieldSpanClass}">
                <p class="mb-1 text-sm font-bold text-gray-700">${label}${requiredMark}</p>
                <label class="inline-flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" name="${name}" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" ${requiredAttr}>
                    <span>${checkboxText}</span>
                </label>
                ${helpText ? `<p class="mt-1 text-xs text-gray-500">${helpText}</p>` : ''}
            </div>
        `;
    }

    if (type === 'file') {
        const accept = escapeDynamicValue(field.options?.accept || '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.csv,.zip');
        const maxSizeMb = Number(field.options?.maxSizeMb || 10);
        return `
            <div class="${fieldSpanClass}">
                <label class="mb-1 block text-sm font-bold text-gray-700" for="dyn-${name}-${index}">${label}${requiredMark}</label>
                <input id="dyn-${name}-${index}" name="${name}" type="file" class="${baseInputClass}" accept="${accept}" ${requiredAttr}>
                <p class="mt-1 text-xs text-gray-500">${helpText || `Maximal ${maxSizeMb} MB.`}</p>
            </div>
        `;
    }

    if (type === 'signature') {
        return `
            <div class="wkc-signature-field ${fieldSpanClass}" data-signature-field="${name}">
                <label class="mb-1 block text-sm font-bold text-gray-700">${label}${requiredMark}</label>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <canvas class="wkc-signature-canvas block w-full" height="180"></canvas>
                </div>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">${helpText || 'Bitte unterschreiben Sie im Feld.'}</p>
                    <button type="button" data-signature-clear="${name}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50">Signatur löschen</button>
                </div>
                <input type="hidden" name="${name}" value="" ${requiredAttr}>
            </div>
        `;
    }

    const inputType = ['email', 'tel'].includes(type) ? type : 'text';
    return `
        <div class="${fieldSpanClass}">
            <label class="mb-1 block text-sm font-bold text-gray-700" for="dyn-${name}-${index}">${label}${requiredMark}</label>
            <input id="dyn-${name}-${index}" name="${name}" type="${inputType}" class="${baseInputClass}" placeholder="${placeholder}" ${requiredAttr}>
            ${helpText ? `<p class="mt-1 text-xs text-gray-500">${helpText}</p>` : ''}
        </div>
    `;
}

function setupDynamicSignaturePads(formElement) {
    const signatureFields = Array.from(formElement.querySelectorAll('.wkc-signature-field'));
    signatureFields.forEach((field) => {
        const canvas = field.querySelector('.wkc-signature-canvas');
        const hidden = field.querySelector('input[type="hidden"]');
        const clearBtn = field.querySelector('[data-signature-clear]');
        if (!canvas || !hidden) return;

        const context = canvas.getContext('2d');
        if (!context) return;
        context.strokeStyle = '#1f2937';
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.lineJoin = 'round';

        const resizeCanvas = () => {
            const rect = canvas.getBoundingClientRect();
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const snapshot = canvas.toDataURL('image/png');
            canvas.width = Math.max(320, Math.floor(rect.width * ratio));
            canvas.height = Math.floor(180 * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);

            if (snapshot && snapshot !== 'data:,') {
                const image = new Image();
                image.onload = () => {
                    context.drawImage(image, 0, 0, rect.width, 180);
                };
                image.src = snapshot;
            }
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        let drawing = false;
        let hasSignature = false;

        const pointerPosition = (event) => {
            const rect = canvas.getBoundingClientRect();
            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        };

        const startDraw = (event) => {
            drawing = true;
            const { x, y } = pointerPosition(event);
            context.beginPath();
            context.moveTo(x, y);
            event.preventDefault();
        };
        const draw = (event) => {
            if (!drawing) return;
            const { x, y } = pointerPosition(event);
            context.lineTo(x, y);
            context.stroke();
            hasSignature = true;
            hidden.value = canvas.toDataURL('image/png');
            event.preventDefault();
        };
        const endDraw = () => {
            drawing = false;
            context.closePath();
            if (hasSignature) {
                hidden.value = canvas.toDataURL('image/png');
            }
        };

        canvas.addEventListener('pointerdown', startDraw);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', endDraw);
        canvas.addEventListener('pointerleave', endDraw);
        canvas.addEventListener('pointercancel', endDraw);

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                context.clearRect(0, 0, canvas.width, canvas.height);
                hidden.value = '';
                hasSignature = false;
            });
        }
    });
}

function bindDynamicFormSubmission(formElement, formConfig) {
    const messageBox = formElement.querySelector('.dynamic-form-message');
    const submitButton = formElement.querySelector('button[type="submit"]');
    if (!submitButton || !messageBox) return;

    formElement.addEventListener('submit', async (event) => {
        event.preventDefault();

        submitButton.disabled = true;
        const originalButtonContent = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">progress_activity</span> Wird gesendet...';

        messageBox.classList.add('hidden');
        messageBox.textContent = '';

        try {
            const slug = String(formElement.getAttribute('data-form-slug') || '').trim();
            const formData = new FormData(formElement);
            const response = await fetch(`/api/forms.php?action=submit&slug=${encodeURIComponent(slug)}`, {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Formular konnte nicht gesendet werden.');
            }

            if (data.warning) {
                messageBox.className = 'dynamic-form-message rounded-xl p-4 text-sm font-medium bg-amber-50 text-amber-800 border border-amber-200';
                messageBox.textContent = data.warning;
            } else {
                messageBox.className = 'dynamic-form-message rounded-xl p-4 text-sm font-medium bg-green-50 text-green-700 border border-green-200';
                messageBox.textContent = data.message || formConfig.successMessage || 'Vielen Dank! Ihre Anfrage wurde übermittelt.';
            }
            messageBox.classList.remove('hidden');
            formElement.reset();

            formElement.querySelectorAll('.wkc-signature-field').forEach((field) => {
                const canvas = field.querySelector('.wkc-signature-canvas');
                const hidden = field.querySelector('input[type="hidden"]');
                const context = canvas ? canvas.getContext('2d') : null;
                if (context && canvas) {
                    context.clearRect(0, 0, canvas.width, canvas.height);
                }
                if (hidden) hidden.value = '';
            });
        } catch (error) {
            messageBox.className = 'dynamic-form-message rounded-xl p-4 text-sm font-medium bg-red-50 text-red-700 border border-red-200';
            messageBox.textContent = error.message || 'Fehler beim Senden des Formulars.';
            messageBox.classList.remove('hidden');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonContent;
        }
    });
}

function escapeDynamicValue(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

/* ================================
   Vorstand – dynamisch laden
   ================================ */
function loadVorstand() {
    const grid = document.getElementById('vorstandGrid');
    if (!grid) return;

    const delays = ['', 'animation-delay-100', 'animation-delay-200', 'animation-delay-300'];

    fetch('api/members.php?action=board_list')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const members = data.members || [];
            if (members.length === 0) {
                grid.innerHTML = '<p class="text-center text-gray-400 col-span-full">Vorstandsmitglieder werden bald vorgestellt.</p>';
                return;
            }

            grid.innerHTML = members.map((m, i) => {
                const img = m.profile_image
                    ? `<img alt="${esc(m.display_name)}" class="w-full h-full object-cover" src="${esc(m.profile_image)}" />`
                    : `<div class="w-full h-full bg-primary/10 flex items-center justify-center text-primary text-4xl font-bold">${esc(m.display_name.charAt(0))}</div>`;
                const delay = delays[i % delays.length];

                return `
                    <div class="group flex flex-col items-center scroll-animate ${delay}">
                        <div class="relative mb-5">
                            <div class="w-44 h-44 rounded-full overflow-hidden border-4 border-gray-100 shadow-lg group-hover:border-primary transition-all duration-300 group-hover:shadow-primary/20">
                                ${img}
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-primary transition-colors">${esc(m.display_name)}</h3>
                        <p class="text-sm text-gray-500 font-medium">${esc(m.position || '')}</p>
                    </div>`;
            }).join('');

            // Re-init scroll animations for new elements
            if (typeof initScrollAnimations === 'function') {
                initScrollAnimations();
            }
        })
        .catch(err => {
            console.error('loadVorstand:', err);
            grid.innerHTML = '<p class="text-center text-red-400 col-span-full">Vorstandsmitglieder konnten nicht geladen werden.</p>';
        });

    function esc(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }
}

/* ================================
   Neuigkeiten – Startseite (3 latest)
   ================================ */
function loadNews() {
    const grid = document.getElementById('newsGrid');
    if (!grid) return;

    const delays = ['', 'animation-delay-100', 'animation-delay-200'];

    fetch('api/articles.php?action=list&limit=3')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const articles = data.articles || [];
            if (articles.length === 0) {
                grid.innerHTML = '<p class="text-center text-gray-400 col-span-full py-12">Noch keine Beiträge vorhanden.</p>';
                return;
            }

            grid.innerHTML = articles.map((a, i) => {
                const date = formatDateDE(a.published_at || a.created_at);
                const img = a.featured_image
                    ? `<img alt="${esc(a.title)}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="${esc(a.featured_image)}" />`
                    : `<div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center"><span class="material-symbols-outlined text-primary/30 text-6xl">article</span></div>`;
                const url = articleUrl(a);
                const delay = delays[i % delays.length];

                return `
                    <article class="group bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 flex flex-col scroll-animate ${delay}">
                        <a href="${esc(url)}" class="block">
                            <div class="relative h-52 overflow-hidden">
                                ${img}
                                <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-lg text-xs font-bold text-primary shadow-sm">${date}</div>
                            </div>
                        </a>
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors line-clamp-2">
                                ${esc(a.title)}
                            </h3>
                            <p class="text-gray-500 text-sm line-clamp-3 flex-grow mb-4">${esc(a.excerpt || '')}</p>
                            <a href="${esc(url)}" class="inline-flex items-center text-primary font-bold text-sm hover:gap-2 gap-1 transition-all mt-auto">
                                Weiterlesen
                                <span class="material-symbols-outlined text-base">arrow_forward</span>
                            </a>
                        </div>
                    </article>`;
            }).join('');

            if (typeof initScrollAnimations === 'function') initScrollAnimations();
        })
        .catch(err => {
            console.error('loadNews:', err);
            grid.innerHTML = '<p class="text-center text-red-400 col-span-full py-12">Beiträge konnten nicht geladen werden.</p>';
        });

    function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
}

/* ================================
   Gefördert – Startseite (funding articles)
   ================================ */
function loadFunding() {
    const grid = document.getElementById('fundingGrid');
    if (!grid) return;

    fetch('api/articles.php?action=list&funding=1&limit=4')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const articles = data.articles || [];
            if (articles.length === 0) {
                grid.innerHTML = '<p class="text-center text-gray-400 col-span-full py-12">Noch keine Förderprojekte vorhanden.</p>';
                return;
            }

            grid.innerHTML = articles.map((a, i) => {
                const date = formatDateDE(a.published_at || a.created_at);
                const img = a.featured_image
                    ? `<img alt="${esc(a.title)}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="${esc(a.featured_image)}" />`
                    : `<div class="w-full h-full bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center"><span class="material-symbols-outlined text-blue-300 text-4xl">volunteer_activism</span></div>`;
                const url = articleUrl(a);
                const delay = i % 2 === 1 ? 'animation-delay-100' : '';

                return `
                    <article class="group flex flex-col sm:flex-row bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-100 scroll-animate ${delay}">
                        <a href="${esc(url)}" class="relative sm:w-48 h-48 sm:h-auto overflow-hidden flex-shrink-0 block">
                            ${img}
                            <div class="absolute top-3 left-3 bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-lg shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">volunteer_activism</span>
                                Förderung
                            </div>
                        </a>
                        <div class="p-6 flex-1">
                            <span class="text-xs text-gray-400 font-medium">${date}</span>
                            <h3 class="text-lg font-bold text-gray-900 mt-1 group-hover:text-primary transition-colors">${esc(a.title)}</h3>
                            <p class="text-sm text-gray-500 mt-2 line-clamp-2">${esc(a.excerpt || '')}</p>
                            <a href="${esc(url)}" class="inline-flex items-center text-primary font-bold text-sm mt-3 hover:gap-2 gap-1 transition-all">
                                Weiterlesen <span class="material-symbols-outlined text-base">arrow_forward</span>
                            </a>
                        </div>
                    </article>`;
            }).join('');

            if (typeof initScrollAnimations === 'function') initScrollAnimations();
        })
        .catch(err => {
            console.error('loadFunding:', err);
            grid.innerHTML = '<p class="text-center text-red-400 col-span-full py-12">Förderprojekte konnten nicht geladen werden.</p>';
        });

    function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
}

/* ================================
   Helper – German Date Formatting
   ================================ */
function formatDateDE(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    const day = String(d.getDate()).padStart(2, '0');
    return `${day}. ${months[d.getMonth()]} ${d.getFullYear()}`;
}

/* ================================
   Termine – Startseite (public teaser)
   ================================ */
function loadHomeEvents() {
    const list = document.getElementById('homeEventsList');
    if (!list) return;

    fetch('api/events.php?action=home_list&limit=3')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const events = data.events || [];
            if (events.length === 0) {
                list.innerHTML = '<p class="text-sm text-gray-500">Aktuell sind keine öffentlichen Termine geplant.</p>';
                return;
            }

            list.innerHTML = events.map(evt => {
                const date = formatDateDE(evt.event_date);
                const time = evt.event_time ? ` · ${evt.event_time.slice(0, 5)} Uhr` : '';
                return `
                    <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wide text-primary">${date}${time}</p>
                        <h3 class="mt-1 text-base font-bold text-gray-900">${esc(evt.title)}</h3>
                        ${evt.location ? `<p class="mt-1 text-sm text-gray-500">${esc(evt.location)}</p>` : ''}
                        ${evt.description ? `<p class="mt-2 text-sm text-gray-600 line-clamp-2">${esc(evt.description)}</p>` : ''}
                    </article>
                `;
            }).join('');
        })
        .catch(err => {
            console.error('loadHomeEvents:', err);
            list.innerHTML = '<p class="text-sm text-red-400">Termine konnten nicht geladen werden.</p>';
        });

    function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
}

/* ================================
   Ziele / Goals – Startseite (topic cards grid)
   ================================ */
function loadGoals() {
    const grid = document.getElementById('goalsGrid');
    if (!grid) return;

    const delays = ['', 'animation-delay-100', 'animation-delay-200', ''];

    fetch('api/goals.php?action=list')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const topics = data.topics || [];
            if (topics.length === 0) {
                grid.innerHTML = '<p class="text-center text-gray-400 col-span-full py-12">Noch keine Themen vorhanden.</p>';
                return;
            }

            grid.innerHTML = topics.map((t, i) => {
                const items = t.items || [];
                const color = esc(t.color || '#7c3aed');
                const icon = esc(t.icon || 'category');
                const image = t.image
                    ? `<img src="${esc(t.image)}" alt="${esc(t.name)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />`
                    : `<div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-gray-300 text-4xl">${icon}</span></div>`;
                const countBadge = items.length > 0
                    ? `<span class="absolute top-3 right-3 bg-white/90 text-xs font-bold px-2 py-0.5 rounded-full" style="color:${color}">${items.length} Ziel${items.length !== 1 ? 'e' : ''}</span>`
                    : '';
                const itemsList = items.map(item => {
                    const st = String(item.status || '').trim();
                    const sBadge = st === 'erreicht'
                        ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 flex-shrink-0 whitespace-nowrap">Erreicht</span>'
                        : st === 'teils_erreicht'
                        ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-700 flex-shrink-0 whitespace-nowrap">Teils erreicht</span>'
                        : '';
                    return `<li class="flex items-start gap-2"><span class="material-symbols-outlined text-base flex-shrink-0 -mt-0.5" style="color:${color}">${esc(item.icon || 'check_circle')}</span><span class="flex-1">${esc(item.title)}</span>${sBadge}</li>`;
                }).join('');
                const delay = delays[i % delays.length];

                return `
                    <a href="themen.html#${esc(t.slug)}" class="group relative overflow-hidden rounded-2xl bg-white shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 flex flex-col scroll-animate ${delay}" style="width:280px;max-width:100%">
                        <div class="relative h-36 overflow-hidden">
                            ${image}
                            <div class="absolute inset-0" style="background:linear-gradient(to top,${color}cc,transparent)"></div>
                            <div class="absolute bottom-3 left-3 flex items-center gap-2">
                                <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-xl">${icon}</span>
                                </div>
                                <span class="text-white font-bold text-sm">${esc(t.name)}</span>
                            </div>
                            ${countBadge}
                        </div>
                        <div class="p-4 flex-1">
                            <ul class="text-sm text-gray-600 space-y-1.5">${itemsList}</ul>
                        </div>
                        <div class="px-4 pb-3">
                            <span class="text-xs text-primary font-bold group-hover:underline flex items-center gap-1">Details ansehen<span class="material-symbols-outlined text-xs">arrow_forward</span></span>
                        </div>
                    </a>`;
            }).join('');

            if (typeof initScrollAnimations === 'function') initScrollAnimations();
        })
        .catch(err => {
            console.error('loadGoals:', err);
            grid.innerHTML = '<p class="text-center text-red-400 col-span-full py-12">Themen konnten nicht geladen werden.</p>';
        });

    function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
}

/* ================================
   Ziele / Goals – Themen-Detailseite
   ================================ */
function loadGoalsDetail() {
    const container = document.getElementById('goalsDetailContainer');
    const statsBar = document.getElementById('goalsStatsBar');
    if (!container) return;

    fetch('api/goals.php?action=list')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            const topics = data.topics || [];
            if (topics.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-400 py-20">Noch keine Themen vorhanden.</p>';
                return;
            }

            // Calculate stats
            let totalItems = 0;
            topics.forEach(t => { totalItems += (t.items || []).length; });

            // Update stats bar if present
            if (statsBar) {
                statsBar.innerHTML = `
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 text-center">
                        <div>
                            <div class="text-3xl font-extrabold text-primary">${topics.length}</div>
                            <div class="text-sm text-gray-500 font-medium mt-1">Themen</div>
                        </div>
                        <div>
                            <div class="text-3xl font-extrabold text-gray-700">${totalItems}</div>
                            <div class="text-sm text-gray-500 font-medium mt-1">Ziele insgesamt</div>
                        </div>
                    </div>`;
            }

            // Render topic sections
            container.innerHTML = topics.map((t, i) => {
                const items = t.items || [];
                const color = esc(t.color || '#7c3aed');
                const icon = esc(t.icon || 'category');
                const image = t.image || '';
                const isReversed = i % 2 === 1;
                const flexDir = isReversed ? 'md:flex-row-reverse' : 'md:flex-row';

                const imageBlock = `
                    <div class="w-full md:w-2/5 flex-shrink-0">
                        <div class="relative rounded-2xl overflow-hidden shadow-lg aspect-[4/3]">
                            ${image
                                ? `<img src="${esc(image)}" alt="${esc(t.name)}" class="w-full h-full object-cover" />`
                                : `<div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-gray-300 text-6xl">${icon}</span></div>`
                            }
                            <div class="absolute inset-0" style="background:linear-gradient(to top,${color}99,transparent)"></div>
                            <div class="absolute bottom-4 left-4 flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-3xl">${icon}</span>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-extrabold text-white">${esc(t.name)}</h2>
                                    <p class="text-white/80 text-sm">${items.length} Ziel${items.length !== 1 ? 'e' : ''}</p>
                                </div>
                            </div>
                        </div>
                    </div>`;

                const itemsBlock = items.map(item => {
                    const st = String(item.status || '').trim();
                    const statusBadge = st === 'erreicht'
                        ? '<span class="text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700 flex-shrink-0 whitespace-nowrap">Erreicht</span>'
                        : st === 'teils_erreicht'
                        ? '<span class="text-xs font-bold px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 flex-shrink-0 whitespace-nowrap">Teils erreicht</span>'
                        : '';

                    return `
                    <div class="p-5 rounded-xl bg-white border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-4">
                            <span class="material-symbols-outlined text-2xl mt-0.5" style="color:${color}">${esc(item.icon || 'check_circle')}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-gray-900 text-lg">${esc(item.title)}</h3>
                                    ${statusBadge}
                                </div>
                                ${item.description ? `<p class="text-sm text-gray-500 mt-1">${esc(item.description)}</p>` : ''}
                            </div>
                        </div>
                    </div>
                `}).join('');

                return `
                    <section id="${esc(t.slug)}" class="scroll-mt-24">
                        <div class="flex flex-col ${flexDir} gap-8 md:gap-12 items-start">
                            ${imageBlock}
                            <div class="flex-1 space-y-4">
                                ${t.description ? `<p class="text-gray-500 mb-6">${esc(t.description)}</p>` : ''}
                                ${itemsBlock}
                            </div>
                        </div>
                    </section>`;
            }).join('');

            if (typeof initScrollAnimations === 'function') initScrollAnimations();

            // Scroll to hash if present
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
                }
            }
        })
        .catch(err => {
            console.error('loadGoalsDetail:', err);
            container.innerHTML = '<p class="text-center text-red-400 py-20">Themen konnten nicht geladen werden.</p>';
        });

    function esc(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
}
