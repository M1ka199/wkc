function escGallery(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

let currentGallery = null;

function currentEmbedCode(slug) {
    return `[gallery:${slug || 'slug'}]`;
}

function setEmbedCode(slug) {
    const el = document.getElementById('galleryEmbedCode');
    if (!el) return;
    el.textContent = currentEmbedCode(slug);
}

function setUploadStatus(message, tone = 'muted') {
    const el = document.getElementById('galleryUploadStatus');
    if (!el) return;
    el.textContent = message || '';
    el.classList.remove('text-gray-500', 'text-green-600', 'text-red-600');
    if (tone === 'success') el.classList.add('text-green-600');
    else if (tone === 'error') el.classList.add('text-red-600');
    else el.classList.add('text-gray-500');
}

function initUploadDropZone() {
    const dropZone = document.getElementById('galleryDropZone');
    const fileInput = document.getElementById('galleryUploadFile');
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary', 'bg-primary/10');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary', 'bg-primary/10');
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/10');
        if (e.dataTransfer?.files?.length) {
            fileInput.files = e.dataTransfer.files;
            setUploadStatus(`${e.dataTransfer.files.length} Datei(en) ausgewaehlt.`);
        }
    });

    fileInput.addEventListener('change', () => {
        const count = fileInput.files?.length || 0;
        setUploadStatus(count ? `${count} Datei(en) ausgewaehlt.` : '');
    });
}

function renderImages(images = []) {
    const grid = document.getElementById('galleryImagesGrid');
    if (!grid) return;

    if (!images.length) {
        grid.innerHTML = '<p class="text-sm text-gray-400 col-span-full">Noch keine Bilder hochgeladen.</p>';
        return;
    }

    grid.innerHTML = images.map((img, idx) => `
        <article class="border border-gray-200 rounded-xl overflow-hidden bg-white">
            <img src="..${escGallery(img.image_path)}" alt="" class="w-full h-44 object-cover">
            <div class="p-3 space-y-2">
                <input type="text" value="${escGallery(img.caption || '')}" data-caption-id="${img.id}" class="w-full rounded border-gray-300 text-sm" placeholder="Bildunterschrift">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <button type="button" class="img-order px-2 py-1 rounded border border-gray-300 text-xs" data-id="${img.id}" data-dir="up" ${idx === 0 ? 'disabled' : ''}>â†‘</button>
                        <button type="button" class="img-order px-2 py-1 rounded border border-gray-300 text-xs" data-id="${img.id}" data-dir="down" ${idx === images.length - 1 ? 'disabled' : ''}>â†“</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="img-save text-xs text-primary font-bold" data-id="${img.id}">Speichern</button>
                        <button type="button" class="img-delete text-xs text-red-600 font-bold" data-id="${img.id}">Löschen</button>
                    </div>
                </div>
            </div>
        </article>
    `).join('');
}

function buildPayload() {
    const params = new URLSearchParams();
    if (GALLERY_ID > 0) params.append('id', String(GALLERY_ID));
    params.append('title', document.getElementById('galleryTitle').value.trim());
    params.append('slug', document.getElementById('gallerySlug').value.trim());
    params.append('description', document.getElementById('galleryDescription').value.trim());
    params.append('is_published', document.getElementById('galleryPublished').checked ? '1' : '0');
    return params;
}

async function loadGallery() {
    if (!GALLERY_ID) {
        setEmbedCode('slug');
        return;
    }

    const res = await fetch(`../api/galleries.php?action=detail&id=${GALLERY_ID}`, { credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.gallery) {
        alert(data.error || 'Galerie konnte nicht geladen werden.');
        return;
    }

    currentGallery = data.gallery;
    const g = data.gallery;
    document.getElementById('editorTitle').textContent = 'Galerie bearbeiten';
    document.getElementById('galleryTitle').value = g.title || '';
    document.getElementById('gallerySlug').value = g.slug || '';
    document.getElementById('galleryDescription').value = g.description || '';
    document.getElementById('galleryPublished').checked = Number(g.is_published || 0) === 1;
    document.getElementById('deleteGalleryBtn').classList.remove('hidden');
    setEmbedCode(g.slug || 'slug');
    renderImages(g.images || []);
}

async function saveGallery() {
    const payload = buildPayload();
    const res = await fetch('../api/galleries.php?action=save', {
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

    if (!GALLERY_ID && data.id) {
        window.location.href = `galerie-editor.php?id=${data.id}`;
        return;
    }

    alert('Galerie gespeichert.');
    await loadGallery();
}

async function uploadImage() {
    if (!GALLERY_ID) {
        alert('Bitte Galerie zuerst speichern.');
        return;
    }

    const files = Array.from(document.getElementById('galleryUploadFile').files || []);
    if (!files.length) {
        alert('Bitte mindestens ein Bild auswaehlen.');
        return;
    }

    const fd = new FormData();
    fd.append('gallery_id', String(GALLERY_ID));
    fd.append('caption', document.getElementById('galleryUploadCaption').value.trim());
    files.forEach((file) => fd.append('images[]', file));

    setUploadStatus(`Upload laeuft (${files.length} Datei(en)) ...`);

    const res = await fetch('../api/galleries.php?action=upload_image', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.success) {
        setUploadStatus(data.error || 'Upload fehlgeschlagen.', 'error');
        alert(data.error || 'Upload fehlgeschlagen.');
        return;
    }

    document.getElementById('galleryUploadFile').value = '';
    document.getElementById('galleryUploadCaption').value = '';
    const uploadedCount = Array.isArray(data.images) ? data.images.length : 1;
    setUploadStatus(`${uploadedCount} Datei(en) erfolgreich hochgeladen.`, 'success');
    await loadGallery();
}

async function saveImageMeta(id, sortOrder = null) {
    const input = document.querySelector(`[data-caption-id="${id}"]`);
    const fd = new URLSearchParams();
    fd.append('id', String(id));
    fd.append('caption', input ? input.value.trim() : '');
    fd.append('sort_order', String(sortOrder ?? 0));

    const res = await fetch('../api/galleries.php?action=update_image', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: fd.toString(),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        alert(data.error || 'Bilddaten konnten nicht gespeichert werden.');
        return false;
    }
    return true;
}

async function moveImage(id, direction) {
    if (!currentGallery || !Array.isArray(currentGallery.images)) return;
    const images = currentGallery.images;
    const idx = images.findIndex((img) => Number(img.id) === Number(id));
    if (idx < 0) return;
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= images.length) return;

    const a = images[idx];
    const b = images[swapIdx];
    const sortA = Number(a.sort_order || idx + 1);
    const sortB = Number(b.sort_order || swapIdx + 1);

    const okA = await saveImageMeta(a.id, sortB);
    if (!okA) return;
    const okB = await saveImageMeta(b.id, sortA);
    if (!okB) return;

    await loadGallery();
}

async function deleteImage(id) {
    const ok = window.wkcConfirm ? await window.wkcConfirm('Bild wirklich löschen?') : confirm('Bild wirklich löschen?');
    if (!ok) return;

    const res = await fetch(`../api/galleries.php?action=image&id=${id}`, { method: 'DELETE', credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.success) {
        alert(data.error || 'Bild konnte nicht gelöscht werden.');
        return;
    }
    await loadGallery();
}

async function deleteGallery() {
    if (!GALLERY_ID) return;
    const ok = window.wkcConfirm ? await window.wkcConfirm('Galerie wirklich löschen?') : confirm('Galerie wirklich löschen?');
    if (!ok) return;

    const res = await fetch(`../api/galleries.php?id=${GALLERY_ID}`, { method: 'DELETE', credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.success) {
        alert(data.error || 'Galerie konnte nicht gelöscht werden.');
        return;
    }
    window.location.href = 'galerien.php';
}

document.addEventListener('DOMContentLoaded', async () => {
    initUploadDropZone();
    await loadGallery();

    document.getElementById('saveGalleryBtn').addEventListener('click', saveGallery);
    document.getElementById('deleteGalleryBtn').addEventListener('click', deleteGallery);
    document.getElementById('uploadGalleryImageBtn').addEventListener('click', uploadImage);
    document.getElementById('gallerySlug').addEventListener('input', (e) => setEmbedCode(e.target.value.trim()));

    document.addEventListener('click', async (e) => {
        const saveBtn = e.target.closest('.img-save');
        const deleteBtn = e.target.closest('.img-delete');
        const orderBtn = e.target.closest('.img-order');

        if (saveBtn) {
            await saveImageMeta(Number(saveBtn.dataset.id));
        }
        if (deleteBtn) {
            await deleteImage(Number(deleteBtn.dataset.id));
        }
        if (orderBtn) {
            await moveImage(Number(orderBtn.dataset.id), orderBtn.dataset.dir);
        }
    });
});
