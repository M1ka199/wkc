/**
 * WKC – Article Editor JavaScript
 * Extracted from editor.php to avoid OneDrive file-locking issues (errno=11).
 */

// Globals passed from inline PHP config
// ARTICLE_ID, IS_EDITING, CURRENT_USER_ID are set in the HTML page

let currentArticleId = ARTICLE_ID;
let slugManuallyEdited = false;
let allMembers = [];
let seoTitleManuallyEdited = false;
let seoDescManuallyEdited = false;

// ============================
// WYSIWYG helpers
// ============================
function execCmd(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('editorContent').focus();
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

function triggerInlineImageUpload() {
    const input = document.getElementById('inlineImageInput');
    if (!input) return;
    input.click();
}

async function uploadInlineImage(file) {
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch('../api/articles.php?action=upload_media', {
        method: 'POST',
        body: fd,
        credentials: 'include',
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        throw new Error(data.error || 'Bild-Upload fehlgeschlagen.');
    }
    return data.path;
}

document.getElementById('inlineImageInput')?.addEventListener('change', async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const path = await uploadInlineImage(file);
        execCmd('insertHTML', `<img src="${path}" alt="">`);
    } catch (err) {
        showAlert(err.message || 'Bild konnte nicht eingefügt werden.', 'error');
    } finally {
        e.target.value = '';
    }
});

// ============================
// Slug auto-generation
// ============================
function titleToSlug(title) {
    let slug = title.toLowerCase().trim();
    slug = slug.replace(/[äÄ]/g, 'ae').replace(/[öÖ]/g, 'oe').replace(/[üÜ]/g, 'ue').replace(/ß/g, 'ss');
    slug = slug.replace(/[^a-z0-9\s-]/g, '');
    slug = slug.replace(/[\s-]+/g, '-');
    slug = slug.replace(/^-+|-+$/g, '');
    return slug;
}

document.getElementById('articleTitle').addEventListener('input', (e) => {
    // Auto-generate slug
    if (!slugManuallyEdited && !currentArticleId) {
        document.getElementById('articleSlug').value = titleToSlug(e.target.value);
    }
    // Auto-fill SEO title
    if (!seoTitleManuallyEdited) {
        document.getElementById('metaTitle').value = e.target.value.trim();
    }
});

document.getElementById('articleSlug').addEventListener('input', () => {
    slugManuallyEdited = true;
});

document.getElementById('articleSlug').addEventListener('blur', (e) => {
    e.target.value = titleToSlug(e.target.value);
    if (!e.target.value) slugManuallyEdited = false;
});

// ============================
// SEO auto-fill from excerpt
// ============================
document.getElementById('articleExcerpt').addEventListener('input', (e) => {
    if (!seoDescManuallyEdited) {
        document.getElementById('metaDescription').value = e.target.value.trim();
    }
});

// Track manual SEO edits
document.getElementById('metaTitle').addEventListener('input', () => {
    seoTitleManuallyEdited = true;
});
document.getElementById('metaDescription').addEventListener('input', () => {
    seoDescManuallyEdited = true;
});

// Auto-resize title textarea
const titleEl = document.getElementById('articleTitle');
function autoResizeTitle() {
    titleEl.style.height = 'auto';
    titleEl.style.height = titleEl.scrollHeight + 'px';
}
titleEl.addEventListener('input', autoResizeTitle);
setTimeout(autoResizeTitle, 100);

// ============================
// Image Upload
// ============================
const dropZone = document.getElementById('imageDropZone');
const fileInput = document.getElementById('featuredImageInput');
const imagePreview = document.getElementById('imagePreview');

dropZone.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', (e) => {
    if (e.target.files[0]) previewImageFile(e.target.files[0]);
});

dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-primary'); });
dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-primary'); });
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-primary');
    if (e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        previewImageFile(e.dataTransfer.files[0]);
    }
});

function previewImageFile(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.src = e.target.result;
        imagePreview.classList.remove('hidden');
        document.getElementById('imageUploadHint').classList.add('opacity-0');
    };
    reader.readAsDataURL(file);
}

// ============================
// Author Dropdown
// ============================
const authorToggle = document.getElementById('authorToggle');
const authorListEl = document.getElementById('authorList');

authorToggle.addEventListener('click', () => {
    authorListEl.classList.toggle('hidden');
});

document.addEventListener('click', (e) => {
    if (!document.getElementById('authorDropdown').contains(e.target)) {
        authorListEl.classList.add('hidden');
    }
});

function selectAuthor(member) {
    document.getElementById('authorId').value = member.id;
    document.getElementById('authorName').textContent = member.display_name;
    document.getElementById('authorPosition').textContent = member.position || 'Mitglied';

    const avatarEl = document.getElementById('authorAvatar');
    if (member.profile_image) {
        avatarEl.innerHTML = `<img src="../${member.profile_image}" class="w-8 h-8 rounded-full object-cover">`;
    } else {
        avatarEl.innerHTML = member.display_name.charAt(0);
    }

    document.querySelectorAll('.author-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.id == member.id);
    });

    authorListEl.classList.add('hidden');
}

async function loadMembers() {
    try {
        const res = await fetch('../api/members.php?action=admin_list', { credentials: 'include' });
        const data = await res.json();
        allMembers = data.members || [];
        const container = document.getElementById('authorOptions');

        container.innerHTML = allMembers.map(m => {
            const img = m.profile_image
                ? `<img src="../${m.profile_image}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">`
                : `<div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs flex-shrink-0">${m.display_name.charAt(0)}</div>`;
            const selected = m.id == CURRENT_USER_ID ? 'selected' : '';
            return `
                <div class="author-option ${selected} flex items-center gap-3 px-3 py-2.5 cursor-pointer border-l-2 border-transparent" data-id="${m.id}" onclick='selectAuthor(${JSON.stringify({id: m.id, display_name: m.display_name, position: m.position, profile_image: m.profile_image})})'>
                    ${img}
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate">${m.display_name}</p>
                        <p class="text-xs text-gray-400 truncate">${m.position || 'Mitglied'}</p>
                    </div>
                </div>`;
        }).join('');

        // If editing and author was set, update the author display now that members are loaded
        const authorId = document.getElementById('authorId').value;
        if (authorId) {
            const m = allMembers.find(m => m.id == authorId);
            if (m) selectAuthor(m);
        }
    } catch (err) {
        console.error('loadMembers:', err);
    }
}

// ============================
// Load existing article
// ============================
if (IS_EDITING) {
    loadArticle(ARTICLE_ID);
} else {
    document.getElementById('articleDate').valueAsDate = new Date();
}

async function loadArticle(id) {
    try {
        const res = await fetch(`../api/articles.php?action=admin_detail&id=${id}`, { credentials: 'include' });
        const data = await res.json();
        const article = data.article;

        if (!article) {
            showAlert('Artikel nicht gefunden.', 'error');
            return;
        }

        document.getElementById('articleTitle').value = article.title || '';
        autoResizeTitle();
        document.getElementById('articleSlug').value = article.slug || '';
        document.getElementById('articleExcerpt').value = article.excerpt || '';
        document.getElementById('editorContent').innerHTML = article.content || '<p><br></p>';
        document.getElementById('articleTags').value = article.tags || '';
        document.getElementById('metaTitle').value = article.meta_title || '';
        document.getElementById('metaDescription').value = article.meta_description || '';
        document.getElementById('canonicalUrl').value = article.canonical_url || '';
        document.getElementById('seoNoindex').checked = Number(article.noindex || 0) === 1;
        document.getElementById('seoNofollow').checked = Number(article.nofollow || 0) === 1;

        if (article.slug) slugManuallyEdited = true;
        // If SEO fields were already filled, mark them as manually edited
        if (article.meta_title) seoTitleManuallyEdited = true;
        if (article.meta_description) seoDescManuallyEdited = true;

        if (article.published_at) {
            document.getElementById('articleDate').value = article.published_at.substring(0, 10);
        } else if (article.created_at) {
            document.getElementById('articleDate').value = article.created_at.substring(0, 10);
        }

        if (article.featured_image) {
            imagePreview.src = '../' + article.featured_image;
            imagePreview.classList.remove('hidden');
            document.getElementById('imageUploadHint').classList.add('opacity-0');
        }

        // Select author
        if (article.author_id) {
            document.getElementById('authorId').value = article.author_id;
            const updateAuthorDisplay = () => {
                const m = allMembers.find(m => m.id == article.author_id);
                if (m) selectAuthor(m);
            };
            if (allMembers.length) updateAuthorDisplay();
            else setTimeout(updateAuthorDisplay, 800);
        }

        // Status dropdown
        if (article.status) {
            document.getElementById('articleStatus').value = article.status;
        }

        document.getElementById('deleteSection').classList.remove('hidden');
    } catch (err) {
        console.error('loadArticle:', err);
        showAlert('Fehler beim Laden des Artikels.', 'error');
    }
}

// ============================
// Save / Publish
// ============================
document.getElementById('btnSaveDraft').addEventListener('click', () => {
    const status = document.getElementById('articleStatus').value;
    saveArticle(status);
});

async function saveArticle(status) {
    const title = document.getElementById('articleTitle').value.trim();
    if (!title) {
        showAlert('Bitte einen Titel eingeben.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('slug', document.getElementById('articleSlug').value.trim());
    formData.append('excerpt', document.getElementById('articleExcerpt').value.trim());
    formData.append('content', document.getElementById('editorContent').innerHTML);
    formData.append('status', status);
    formData.append('tags', document.getElementById('articleTags').value.trim());
    formData.append('meta_title', document.getElementById('metaTitle').value.trim());
    formData.append('meta_description', document.getElementById('metaDescription').value.trim());
    formData.append('canonical_url', document.getElementById('canonicalUrl').value.trim());
    formData.append('noindex', document.getElementById('seoNoindex').checked ? '1' : '0');
    formData.append('nofollow', document.getElementById('seoNofollow').checked ? '1' : '0');
    formData.append('author_id', document.getElementById('authorId').value);
    formData.append('published_at', document.getElementById('articleDate').value || '');

    const imgFile = document.getElementById('featuredImageInput').files[0];
    if (imgFile) formData.append('featured_image', imgFile);

    const saveStatusEl = document.getElementById('saveStatus');
    saveStatusEl.textContent = 'Speichert...';

    try {
        if (currentArticleId) {
            formData.append('id', currentArticleId);
        }
        const res = await fetch('../api/articles.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            saveStatusEl.textContent = 'Gespeichert âœ“';
            showAlert(data.message || 'Beitrag gespeichert.', 'success');

            if (!currentArticleId && data.id) {
                currentArticleId = data.id;
                history.replaceState(null, '', `editor.php?id=${data.id}`);
                document.getElementById('deleteSection').classList.remove('hidden');
                if (data.slug) {
                    document.getElementById('articleSlug').value = data.slug;
                    slugManuallyEdited = true;
                }
            }

            // Keep dropdown in sync
            document.getElementById('articleStatus').value = status;
        } else {
            saveStatusEl.textContent = 'Fehler';
            showAlert(data.error || 'Speichern fehlgeschlagen.', 'error');
        }
    } catch (err) {
        saveStatusEl.textContent = 'Fehler';
        showAlert('Verbindungsfehler. Bitte erneut versuchen.', 'error');
    }

    setTimeout(() => { saveStatusEl.textContent = ''; }, 3000);
}

// ============================
// Preview
// ============================
document.getElementById('btnPreview').addEventListener('click', openPreview);

function openPreview() {
    const title = document.getElementById('articleTitle').value.trim() || 'Kein Titel';
    const excerpt = document.getElementById('articleExcerpt').value.trim();
    const content = document.getElementById('editorContent').innerHTML;
    const dateVal = document.getElementById('articleDate').value;
    const tags = document.getElementById('articleTags').value.trim();
    const isFunding = document.getElementById('isFunding').checked;
    const authorNameText = document.getElementById('authorName').textContent;

    document.getElementById('previewTitle').textContent = title;
    document.getElementById('previewExcerpt').textContent = excerpt || '';
    document.getElementById('previewExcerpt').classList.toggle('hidden', !excerpt);
    document.getElementById('previewBody').innerHTML = content;
    document.getElementById('previewAuthorName').textContent = authorNameText;

    if (dateVal) {
        const d = new Date(dateVal);
        document.getElementById('previewDate').textContent = d.toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        document.getElementById('previewDate').textContent = new Date().toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    const avatarEl = document.getElementById('authorAvatar');
    const previewAvatar = document.getElementById('previewAuthorAvatar');
    const avatarImg = avatarEl.querySelector('img');
    if (avatarImg) {
        previewAvatar.innerHTML = `<img src="${avatarImg.src}" class="w-5 h-5 rounded-full object-cover">`;
    } else {
        previewAvatar.innerHTML = `<div class="w-5 h-5 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold" style="font-size:8px">${authorNameText.charAt(0)}</div>`;
    }

    document.getElementById('previewFundingBadge').classList.toggle('hidden', !isFunding);

    const imgSrc = imagePreview.src;
    const previewImgSection = document.getElementById('previewImage');
    if (imgSrc && !imagePreview.classList.contains('hidden')) {
        document.getElementById('previewImageSrc').src = imgSrc;
        previewImgSection.classList.remove('hidden');
    } else {
        previewImgSection.classList.add('hidden');
    }

    const tagsContainer = document.getElementById('previewTags');
    if (tags) {
        tagsContainer.innerHTML = tags.split(',').map(t => `<span class="bg-bg-light text-gray-600 px-3 py-1 rounded-full text-xs font-medium">${t.trim()}</span>`).join('');
        tagsContainer.classList.remove('hidden');
    } else {
        tagsContainer.classList.add('hidden');
    }

    document.getElementById('previewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    document.getElementById('previewModal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePreview();
});

// ============================
// Delete article
// ============================
document.getElementById('btnDelete')?.addEventListener('click', async () => {
    if (!await wkcConfirm('Beitrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;

    try {
        const res = await fetch(`../api/articles.php?id=${currentArticleId}`, { method: 'DELETE', credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'beitraege.php';
        } else {
            showAlert(data.error || 'Löschen fehlgeschlagen.', 'error');
        }
    } catch (err) {
        showAlert('Verbindungsfehler.', 'error');
    }
});

// ============================
// Alert helper
// ============================
function showAlert(message, type) {
    const box = document.getElementById('alertBox');
    box.className = `mb-6 p-4 rounded-lg text-sm font-medium flex items-center gap-2 ${
        type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700'
    }`;
    const icon = type === 'error' ? 'error' : 'check_circle';
    box.innerHTML = `<span class="material-symbols-outlined text-lg">${icon}</span> ${message}`;
    box.classList.remove('hidden');
    if (type === 'success') {
        setTimeout(() => box.classList.add('hidden'), 4000);
    }
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadMembers();
});

