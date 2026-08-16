function execCmd(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('pageEditor').focus();
}

let sliderItemsState = [];

function escSlider(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function setSliderItems(items) {
    sliderItemsState = Array.isArray(items) ? items.map((it) => ({
        title: String(it?.title || ''),
        text: String(it?.text || ''),
        image: String(it?.image || ''),
        buttonLabel: String(it?.buttonLabel || ''),
        buttonUrl: String(it?.buttonUrl || ''),
    })) : [];
    renderSliderItems();
}

function renderSliderItems() {
    const list = document.getElementById('sliderItemsList');
    if (!list) return;
    if (!sliderItemsState.length) {
        list.innerHTML = '<p class="text-xs text-gray-400">Noch keine Slides angelegt.</p>';
        return;
    }

    list.innerHTML = sliderItemsState.map((item, idx) => `
        <article class="border border-gray-200 rounded-lg p-3 space-y-2 bg-gray-50/40">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-700">Slide ${idx + 1}</h4>
                <button type="button" data-slider-remove="${idx}" class="text-xs font-bold text-red-600">Entfernen</button>
            </div>
            <input data-slider-field="title" data-slider-idx="${idx}" type="text" value="${escSlider(item.title)}" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Titel">
            <textarea data-slider-field="text" data-slider-idx="${idx}" rows="2" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Text">${escSlider(item.text)}</textarea>
            <input data-slider-field="image" data-slider-idx="${idx}" type="text" value="${escSlider(item.image)}" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Bild URL oder /uploads/...">
            <div class="grid grid-cols-2 gap-2">
                <input data-slider-field="buttonLabel" data-slider-idx="${idx}" type="text" value="${escSlider(item.buttonLabel)}" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Button Text">
                <input data-slider-field="buttonUrl" data-slider-idx="${idx}" type="text" value="${escSlider(item.buttonUrl)}" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Button Link">
            </div>
        </article>
    `).join('');
}

function addSliderItem() {
    sliderItemsState.push({ title: '', text: '', image: '', buttonLabel: '', buttonUrl: '' });
    renderSliderItems();
}

function syncSliderFromDom() {
    const nodes = document.querySelectorAll('[data-slider-field]');
    nodes.forEach((node) => {
        const idx = Number(node.getAttribute('data-slider-idx'));
        const field = node.getAttribute('data-slider-field');
        if (!Number.isInteger(idx) || idx < 0 || !field || !sliderItemsState[idx]) return;
        sliderItemsState[idx][field] = node.value;
    });
}

function isHomepageChecked() {
    return !!document.getElementById('pageIsHomepage')?.checked;
}

function applyHomepageMode() {
    const isHomepage = isHomepageChecked();
    const pathInput = document.getElementById('pagePath');
    const help = document.getElementById('pagePathHelp');
    if (!pathInput) return;

    if (isHomepage) {
        pathInput.value = '';
        pathInput.readOnly = true;
        pathInput.placeholder = 'Startseite verwendet automatisch /';
        if (help) help.textContent = 'Startseite wird immer unter / ausgespielt (kein Slug erforderlich).';
    } else {
        pathInput.readOnly = false;
        pathInput.placeholder = 'z. B. verein/geschichte';
        if (help) help.textContent = 'Bei normaler Seite erforderlich. Fuer die Startseite leer lassen.';
    }
}

function insertLink() {
    const url = prompt('Link-URL eingeben:', 'https://');
    if (url) execCmd('createLink', url);
}

function insertHtmlEmbed() {
    const html = prompt('HTML/Embed-Code einfügen:');
    if (!html) return;
    execCmd('insertHTML', html);
}

function insertSectionTemplate(template) {
    const templates = {
        hero: `
            <section class="py-12">
                <div class="max-w-5xl mx-auto px-4">
                    <p class="text-sm uppercase tracking-wider text-violet-700 font-semibold">Kategorie</p>
                    <h2 class="text-4xl font-black mt-2">Starker Hero-Titel</h2>
                    <p class="text-lg text-gray-600 mt-4">Kurze Einfuehrung mit dem wichtigsten Nutzenversprechen fuer diese Seite.</p>
                    <p class="mt-6"><a href="#kontakt" class="inline-block px-5 py-3 rounded-lg bg-violet-700 text-white font-semibold">Jetzt Kontakt aufnehmen</a></p>
                </div>
            </section>
        `,
        'two-column': `
            <section class="py-10">
                <div class="max-w-5xl mx-auto px-4 grid md:grid-cols-2 gap-8">
                    <article>
                        <h3 class="text-2xl font-bold mb-3">Spalte 1</h3>
                        <p>Inhalt fuer die erste Spalte. Ideal fuer Fakten, Vorteile oder einen kurzen Einstiegstext.</p>
                    </article>
                    <article>
                        <h3 class="text-2xl font-bold mb-3">Spalte 2</h3>
                        <p>Inhalt fuer die zweite Spalte. Hier koennen Details, Links oder weitere Informationen stehen.</p>
                    </article>
                </div>
            </section>
        `,
        cta: `
            <section class="py-10">
                <div class="max-w-4xl mx-auto px-6 py-8 rounded-2xl bg-violet-50 border border-violet-100 text-center">
                    <h3 class="text-2xl font-black">Call to Action</h3>
                    <p class="text-gray-700 mt-3">Fordern Sie Besucher mit einer klaren Handlungsaufforderung zur naechsten Aktion auf.</p>
                    <p class="mt-5"><a href="/kontakt" class="inline-block px-5 py-3 rounded-lg bg-violet-700 text-white font-semibold">Jetzt starten</a></p>
                </div>
            </section>
        `,
    };

    const html = templates[template];
    if (!html) return;
    execCmd('insertHTML', html);
}

function triggerInlineImageUpload() {
    document.getElementById('inlineImageInput').click();
}

async function uploadInlineImage(file) {
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch('../api/pages.php?action=upload_media', {
        method: 'POST',
        body: fd,
        credentials: 'include',
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        throw new Error(data.error || 'Upload fehlgeschlagen.');
    }
    return data.path;
}

document.getElementById('inlineImageInput').addEventListener('change', async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const path = await uploadInlineImage(file);
        execCmd('insertHTML', `<img src="${path}" alt="">`);
    } catch (err) {
        alert(err.message || 'Bild konnte nicht eingefügt werden.');
    } finally {
        e.target.value = '';
    }
});

function setBlockCheckboxes(blocks) {
    const cfg = blocks || {};
    document.getElementById('blockHero').checked = !!cfg.heroEnabled;
    document.getElementById('blockSlider').checked = cfg.sliderEnabled !== false;
    document.getElementById('blockNews').checked = !!cfg.newsEnabled;
    document.getElementById('blockEvents').checked = !!cfg.eventsEnabled;
    document.getElementById('blockGallery').checked = !!cfg.galleryPreviewEnabled;
    document.getElementById('pageTitleAreaEnabled').checked = cfg.titleAreaEnabled !== false;
    setSliderItems(Array.isArray(cfg.sliderItems) ? cfg.sliderItems : []);
}

function collectPayload() {
    syncSliderFromDom();
    const payload = new URLSearchParams();
    if (PAGE_ID > 0) payload.append('id', String(PAGE_ID));
    payload.append('title', document.getElementById('pageTitle').value.trim());
    payload.append('is_homepage', isHomepageChecked() ? '1' : '0');
    payload.append('path', isHomepageChecked() ? '' : document.getElementById('pagePath').value.trim());
    payload.append('status', document.getElementById('pageStatus').value);
    payload.append('meta_title', document.getElementById('pageMetaTitle').value.trim());
    payload.append('meta_description', document.getElementById('pageMetaDescription').value.trim());
    payload.append('canonical_url', document.getElementById('pageCanonicalUrl').value.trim());
    payload.append('og_image', document.getElementById('pageOgImage').value.trim());
    payload.append('noindex', document.getElementById('pageNoindex').checked ? '1' : '0');
    payload.append('nofollow', document.getElementById('pageNofollow').checked ? '1' : '0');
    payload.append('title_area_enabled', document.getElementById('pageTitleAreaEnabled').checked ? '1' : '0');
    payload.append('hero_enabled', document.getElementById('blockHero').checked ? '1' : '0');
    payload.append('slider_enabled', document.getElementById('blockSlider').checked ? '1' : '0');
    payload.append('news_enabled', document.getElementById('blockNews').checked ? '1' : '0');
    payload.append('events_enabled', document.getElementById('blockEvents').checked ? '1' : '0');
    payload.append('gallery_preview_enabled', document.getElementById('blockGallery').checked ? '1' : '0');
    payload.append('slider_items_json', JSON.stringify(sliderItemsState));
    payload.append('content_html', document.getElementById('pageEditor').innerHTML);
    return payload;
}

async function loadPage() {
    if (!PAGE_ID) {
        document.getElementById('pageEditor').innerHTML = '<p><br></p>';
        const url = new URL(window.location.href);
        if (url.searchParams.get('home') === '1') {
            const homeCheckbox = document.getElementById('pageIsHomepage');
            if (homeCheckbox) homeCheckbox.checked = true;
        }
        applyHomepageMode();
        return;
    }

    const res = await fetch(`../api/pages.php?action=detail&id=${PAGE_ID}`, { credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.page) {
        alert(data.error || 'Seite konnte nicht geladen werden.');
        return;
    }

    const p = data.page;
    document.getElementById('editorTitle').textContent = 'Seite bearbeiten';
    document.getElementById('deletePageBtn').classList.remove('hidden');
    document.getElementById('pageTitle').value = p.title || '';
    document.getElementById('pagePath').value = p.path || '';
    const homeCheckbox = document.getElementById('pageIsHomepage');
    if (homeCheckbox) {
        homeCheckbox.checked = (p.path || '') === '';
    }
    applyHomepageMode();
    document.getElementById('pageStatus').value = p.status || 'draft';
    document.getElementById('pageMetaTitle').value = p.meta_title || '';
    document.getElementById('pageMetaDescription').value = p.meta_description || '';
    document.getElementById('pageCanonicalUrl').value = p.canonical_url || '';
    document.getElementById('pageOgImage').value = p.og_image || '';
    document.getElementById('pageNoindex').checked = Number(p.noindex || 0) === 1;
    document.getElementById('pageNofollow').checked = Number(p.nofollow || 0) === 1;
    document.getElementById('pageEditor').innerHTML = p.content_html || '<p><br></p>';
    document.getElementById('pageTitleAreaEnabled').checked = true;

    let blocks = {};
    try {
        blocks = JSON.parse(p.blocks_json || '{}');
    } catch (_) {
        blocks = {};
    }
    setBlockCheckboxes(blocks);
}

async function savePage() {
    const payload = collectPayload();
    const res = await fetch('../api/pages.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: payload.toString(),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        alert(data.error || 'Speichern fehlgeschlagen.');
        return;
    }
    if (!PAGE_ID && data.id) {
        window.location.href = `seiten-editor.php?id=${data.id}`;
        return;
    }
    alert('Seite gespeichert.');
}

async function deletePage() {
    if (!PAGE_ID) return;
    const ok = window.wkcConfirm ? await window.wkcConfirm('Seite wirklich löschen?') : confirm('Seite wirklich löschen?');
    if (!ok) return;

    const res = await fetch(`../api/pages.php?id=${PAGE_ID}`, { method: 'DELETE', credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.success) {
        alert(data.error || 'Löschen fehlgeschlagen.');
        return;
    }
    window.location.href = 'seiten.php';
}

document.addEventListener('DOMContentLoaded', async () => {
    const homeCheckbox = document.getElementById('pageIsHomepage');
    if (homeCheckbox) {
        homeCheckbox.addEventListener('change', applyHomepageMode);
    }
    const addSliderBtn = document.getElementById('addSliderItemBtn');
    if (addSliderBtn) {
        addSliderBtn.addEventListener('click', addSliderItem);
    }

    document.addEventListener('input', (e) => {
        const field = e.target.closest('[data-slider-field]');
        if (!field) return;
        syncSliderFromDom();
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-slider-remove]');
        if (!btn) return;
        const idx = Number(btn.getAttribute('data-slider-remove'));
        if (!Number.isInteger(idx) || idx < 0) return;
        sliderItemsState.splice(idx, 1);
        renderSliderItems();
    });

    await loadPage();
    document.getElementById('savePageBtn').addEventListener('click', savePage);
    document.getElementById('deletePageBtn').addEventListener('click', deletePage);
});
