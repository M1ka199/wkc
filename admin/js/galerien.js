function escGalleryList(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

async function loadGalleries() {
    const container = document.getElementById('galleryList');
    try {
        const res = await fetch('../api/galleries.php', { credentials: 'include' });
        const data = await res.json();
        const galleries = data.galleries || [];

        if (!galleries.length) {
            container.innerHTML = '<div class="text-sm text-gray-400">Noch keine Galerien vorhanden.</div>';
            return;
        }

        container.innerHTML = galleries.map((g) => {
            const status = Number(g.is_published || 0) === 1
                ? '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Öffentlich</span>'
                : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Entwurf</span>';
            return `
                <article class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-lg text-gray-900 line-clamp-1">${escGalleryList(g.title)}</h3>
                        ${status}
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Slug: <code>${escGalleryList(g.slug)}</code></p>
                    <p class="text-sm text-gray-600 mt-3 line-clamp-2">${escGalleryList(g.description || '')}</p>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <span class="text-xs text-gray-400 break-all">Embed: [gallery:${escGalleryList(g.slug)}]</span>
                        <a href="galerie-editor.php?id=${Number(g.id)}" class="text-primary text-sm font-bold hover:underline">Bearbeiten</a>
                    </div>
                </article>
            `;
        }).join('');
    } catch (err) {
        console.error('loadGalleries:', err);
        container.innerHTML = '<div class="text-sm text-red-400">Galerien konnten nicht geladen werden.</div>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadGalleries();

    document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('../api/auth.php?action=logout');
        window.location.href = 'index.php';
    });
});
