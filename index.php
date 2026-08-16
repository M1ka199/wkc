<?php
require_once __DIR__ . '/api/config.php';
header('Content-Type: text/html; charset=utf-8');

getDB();
$db = getDB();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = trim($path, '/');
if ($path === 'index.php') {
    $path = '';
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeBool($value, bool $fallback = false): bool {
    if ($value === null) return $fallback;
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return (int) $value === 1;
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function appBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function canonicalForPath(string $path, ?string $custom = null): string {
    $custom = trim((string) ($custom ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    if ($path === '') {
        return appBaseUrl() . '/';
    }
    return appBaseUrl() . '/' . ltrim($path, '/');
}

function renderSeoHead(string $title, string $description, string $canonical, bool $noindex, bool $nofollow, string $ogImage = ''): string {
    $robots = ($noindex ? 'noindex' : 'index') . ', ' . ($nofollow ? 'nofollow' : 'follow');
    $html = '<title>' . h($title) . '</title>';
    if ($description !== '') {
        $html .= '<meta name="description" content="' . h($description) . '">';
    }
    $html .= '<meta name="robots" content="' . h($robots) . '">';
    $html .= '<link rel="canonical" href="' . h($canonical) . '">';
    $html .= '<meta property="og:title" content="' . h($title) . '">';
    if ($description !== '') {
        $html .= '<meta property="og:description" content="' . h($description) . '">';
    }
    $html .= '<meta property="og:type" content="website">';
    $html .= '<meta property="og:url" content="' . h($canonical) . '">';
    if ($ogImage !== '') {
        $html .= '<meta property="og:image" content="' . h($ogImage) . '">';
    }
    return $html;
}

function shouldHideMenuItem(array $item, array $features): bool {
    $politicsEnabled = normalizeBool($features['politicsEnabled'] ?? false, false);

    $label = strtolower((string) ($item['label'] ?? ''));
    $url = strtolower((string) ($item['url'] ?? ''));
    $haystack = $label . ' ' . $url;

    if (!$politicsEnabled && preg_match('~kommunal|wahl|politik|themen~', $haystack)) {
        return true;
    }
    return false;
}

function filterMenuItems(array $items, array $features): array {
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item) || shouldHideMenuItem($item, $features)) {
            continue;
        }
        $children = is_array($item['children'] ?? null) ? filterMenuItems($item['children'], $features) : [];
        if ($children) {
            $item['children'] = $children;
        } else {
            unset($item['children']);
        }
        $out[] = $item;
    }
    return $out;
}

function renderMenuItems(array $items, bool $isSub = false): string {
    $html = '';
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? '#'));
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        if ($label === '') continue;

        if ($children) {
            $html .= '<li style="position:relative;">';
            $html .= '<a href="' . h($url) . '" style="text-decoration:none; color:' . ($isSub ? '#111827' : '#374151') . '; font-weight:600;">' . h($label) . '</a>';
            $html .= '<ul style="list-style:none; margin:.5rem 0 0; padding:.6rem; background:#fff; border:1px solid #e5e7eb; border-radius:10px; min-width:220px;">';
            $html .= renderMenuItems($children, true);
            $html .= '</ul></li>';
            continue;
        }

        $html .= '<li><a href="' . h($url) . '" style="text-decoration:none; color:' . ($isSub ? '#111827' : '#374151') . '; font-weight:600;">' . h($label) . '</a></li>';
    }
    return $html;
}

function normalizeMenuUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '#';
    if (preg_match('~^(https?:)?//~i', $url) || stripos($url, 'mailto:') === 0 || stripos($url, 'tel:') === 0) {
        return $url;
    }

    $fragment = '';
    if (strpos($url, '#') !== false) {
        [$url, $fragment] = explode('#', $url, 2);
        $fragment = '#' . ltrim($fragment, '#');
    }

    $url = trim($url);
    if ($url === '') {
        return '/' . $fragment;
    }

    if ($url[0] === '#') {
        return '/' . $url;
    }

    if (preg_match('~\.html$~i', $url)) {
        $url = preg_replace('~\.html$~i', '', $url);
    }

    if (strcasecmp($url, 'index') === 0) {
        $url = '';
    }

    if ($url[0] === '/') {
        return rtrim($url, '/') . $fragment;
    }
    return '/' . ltrim($url, '/') . $fragment;
}

function normalizeLegacyHtmlLinks(string $html): string {
    return preg_replace_callback('~(href\s*=\s*["\'])([^"\']+)(["\'])~i', function ($m) {
        $prefix = $m[1];
        $url = $m[2];
        $suffix = $m[3];

        if (preg_match('~^(https?:)?//~i', $url) || stripos($url, 'mailto:') === 0 || stripos($url, 'tel:') === 0 || str_starts_with($url, '#')) {
            return $prefix . $url . $suffix;
        }

        if (preg_match('~\.html($|\?)~i', $url)) {
            $url = preg_replace('~\.html($|\?)~i', '$1', $url);
            if ($url === 'index' || $url === '/index') {
                $url = '/';
            }
            if (!str_starts_with($url, '/')) {
                $url = '/' . ltrim($url, '/');
            }
        }

        return $prefix . $url . $suffix;
    }, $html);
}

function renderDesktopMenuItems(array $items): string {
    $html = '';
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') continue;
        $url = normalizeMenuUrl((string) ($item['url'] ?? '#'));
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        if ($children) {
            $html .= '<div class="relative group">';
            $html .= '<button class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:text-primary hover:bg-primary/5 transition-all flex items-center gap-1">'
                . h($label)
                . '<span class="material-symbols-outlined text-base transition-transform group-hover:rotate-180">expand_more</span></button>';
            $html .= '<div class="absolute top-full right-0 pt-2 w-72 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">';
            $html .= '<div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-2">';
            foreach ($children as $child) {
                $childLabel = trim((string) ($child['label'] ?? ''));
                if ($childLabel === '') continue;
                $childUrl = normalizeMenuUrl((string) ($child['url'] ?? '#'));
                $html .= '<a href="' . h($childUrl) . '" class="flex items-start gap-3 p-3 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors">'
                    . '<span class="material-symbols-outlined text-primary mt-0.5">chevron_right</span>'
                    . '<div><div class="font-bold text-sm text-gray-900">' . h($childLabel) . '</div></div></a>';
            }
            $html .= '</div></div></div>';
            continue;
        }

        $html .= '<a href="' . h($url) . '" class="nav-link px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:text-primary hover:bg-primary/5 transition-all">' . h($label) . '</a>';
    }
    return $html;
}

function renderMobileMenuItems(array $items): string {
    $html = '';
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') continue;
        $url = normalizeMenuUrl((string) ($item['url'] ?? '#'));
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        if ($children) {
            $html .= '<details class="group">';
            $html .= '<summary class="mobile-nav-link flex items-center gap-4 px-4 py-3.5 rounded-xl text-gray-700 hover:bg-primary/5 hover:text-primary transition-all cursor-pointer list-none [&::-webkit-details-marker]:hidden">'
                . '<span class="material-symbols-outlined text-primary/60">menu</span>'
                . '<span class="font-semibold flex-1">' . h($label) . '</span>'
                . '<span class="material-symbols-outlined text-gray-400 transition-transform group-open:rotate-180">expand_more</span>'
                . '</summary>';
            $html .= '<div class="ml-10 mt-1 space-y-1 mb-1">';
            foreach ($children as $child) {
                $childLabel = trim((string) ($child['label'] ?? ''));
                if ($childLabel === '') continue;
                $childUrl = normalizeMenuUrl((string) ($child['url'] ?? '#'));
                $html .= '<a href="' . h($childUrl) . '" class="block px-4 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-primary/5 hover:text-primary transition-all">' . h($childLabel) . '</a>';
            }
            $html .= '</div></details>';
            continue;
        }

        $html .= '<a href="' . h($url) . '" class="mobile-nav-link flex items-center gap-4 px-4 py-3.5 rounded-xl text-gray-700 hover:bg-primary/5 hover:text-primary transition-all">'
            . '<span class="material-symbols-outlined text-primary/60">chevron_right</span>'
            . '<span class="font-semibold">' . h($label) . '</span></a>';
    }
    return $html;
}

function renderGalleryCards(array $galleries): string {
    if (!$galleries) {
        return '<p style="color:#6b7280;">Es sind noch keine Galerien veroeffentlicht.</p>';
    }
    $html = '<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem;">';
    foreach ($galleries as $g) {
        $html .= '<article style="border:1px solid #e5e7eb; border-radius:14px; padding:1rem; background:#fff;">';
        $html .= '<h3 style="margin:0 0 .4rem;">' . h((string) $g['title']) . '</h3>';
        $html .= '<p style="margin:0 0 .75rem; color:#6b7280;">' . h((string) ($g['description'] ?? '')) . '</p>';
        $html .= '<a href="/galerie/' . rawurlencode((string) $g['slug']) . '" style="text-decoration:none; color:var(--site-primary,#7c3aed); font-weight:700;">Galerie ansehen</a>';
        $html .= '</article>';
    }
    $html .= '</div>';
    return $html;
}

function renderGalleryEmbed(PDO $db, string $slug): string {
    $stmt = $db->prepare('SELECT id, title FROM galleries WHERE slug = :slug AND is_published = 1 LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $gallery = $stmt->fetch();
    if (!$gallery) {
        return '<p style="color:#ef4444;">Galerie ' . h($slug) . ' wurde nicht gefunden.</p>';
    }

    $imgStmt = $db->prepare('SELECT image_path, caption FROM gallery_images WHERE gallery_id = :id ORDER BY sort_order ASC, id ASC');
    $imgStmt->execute([':id' => $gallery['id']]);
    $images = $imgStmt->fetchAll();
    if (!$images) {
        return '<p style="color:#6b7280;">In dieser Galerie sind noch keine Bilder verfuegbar.</p>';
    }

    $html = '<section style="margin:1.25rem 0;"><h3 style="margin:0 0 .8rem;">' . h((string) $gallery['title']) . '</h3>';
    $html .= '<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.75rem;">';
    foreach ($images as $img) {
        $src = h((string) $img['image_path']);
        $cap = h((string) ($img['caption'] ?? ''));
        $html .= '<figure style="margin:0; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff;">';
        $html .= '<img src="' . $src . '" alt="' . $cap . '" style="display:block; width:100%; height:160px; object-fit:cover;">';
        if ($cap !== '') $html .= '<figcaption style="padding:.5rem .65rem; font-size:.85rem; color:#4b5563;">' . $cap . '</figcaption>';
        $html .= '</figure>';
    }
    $html .= '</div></section>';
    return $html;
}

function renderFormEmbed(string $slug): string {
    $safeSlug = preg_replace('/[^a-z0-9\-_]/i', '', trim($slug));
    if ($safeSlug === '') {
        return '<p style="color:#ef4444;">Ungültiger Formular-Slug.</p>';
    }

    return '<div data-wkc-form="' . h($safeSlug) . '" class="wkc-form-embed" style="margin:1.25rem 0;"></div>';
}

function appendTargetPathForms(PDO $db, string $path, string $content): string {
    $normalizedPath = strtolower(trim($path, '/'));
    if ($normalizedPath === '') {
        return $content;
    }

    $stmt = $db->prepare("SELECT slug FROM forms WHERE is_active = 1 AND target_path = :path ORDER BY updated_at DESC, id DESC");
    $stmt->execute([':path' => $normalizedPath]);
    $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!$slugs) {
        return $content;
    }

    $append = '';
    foreach ($slugs as $slugRaw) {
        $slug = preg_replace('/[^a-z0-9\-_]/i', '', (string) $slugRaw);
        if ($slug === '') {
            continue;
        }

        if (preg_match('/data-wkc-form="' . preg_quote($slug, '/') . '"/', $content)) {
            continue;
        }
        $append .= "\n" . renderFormEmbed($slug);
    }

    if ($append === '') {
        return $content;
    }
    return $content . $append;
}

function renderHomeNews(PDO $db): string {
    $rows = $db->query("SELECT title, slug, excerpt, published_at, created_at FROM articles WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 3")->fetchAll();
    if (!$rows) {
        return '<p style="color:#6b7280;">Noch keine Neuigkeiten verfuegbar.</p>';
    }
    $html = '<section data-home-news style="margin:2rem 0;"><h2 style="margin:0 0 .8rem;">Neuigkeiten</h2><div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">';
    foreach ($rows as $row) {
        $slug = rawurlencode((string) $row['slug']);
        $date = (string) ($row['published_at'] ?: $row['created_at']);
        $year = $date !== '' ? date('Y', strtotime($date)) : date('Y');
        $html .= '<article style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem; background:#fff;">';
        $html .= '<h3 style="margin:0 0 .35rem;">' . h((string) $row['title']) . '</h3>';
        $html .= '<p style="margin:0 0 .7rem; color:#6b7280;">' . h((string) ($row['excerpt'] ?? '')) . '</p>';
        $html .= '<a href="/' . $year . '/' . $slug . '" style="text-decoration:none; color:var(--site-primary,#7c3aed); font-weight:700;">Weiterlesen</a>';
        $html .= '</article>';
    }
    $html .= '</div></section>';
    return $html;
}

function renderHomeEvents(PDO $db): string {
    $rows = $db->query("SELECT title, event_date, event_time, location, description FROM events WHERE visibility = 'public' AND show_on_home = 1 ORDER BY event_date ASC LIMIT 4")->fetchAll();
    if (!$rows) {
        return '<p style="color:#6b7280;">Keine Termine freigegeben.</p>';
    }
    $html = '<section data-home-events style="margin:2rem 0;"><h2 style="margin:0 0 .8rem;">Termine</h2><div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">';
    foreach ($rows as $row) {
        $date = $row['event_date'] ? date('d.m.Y', strtotime((string) $row['event_date'])) : '';
        $html .= '<article style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem; background:#fff;">';
        $html .= '<h3 style="margin:0 0 .35rem;">' . h((string) $row['title']) . '</h3>';
        $html .= '<p style="margin:0 0 .35rem; color:#4b5563; font-weight:600;">' . h($date . (($row['event_time'] ?? '') ? ' - ' . (string) $row['event_time'] : '')) . '</p>';
        if (!empty($row['location'])) $html .= '<p style="margin:0 0 .35rem; color:#6b7280;">' . h((string) $row['location']) . '</p>';
        if (!empty($row['description'])) $html .= '<p style="margin:0; color:#6b7280;">' . h((string) $row['description']) . '</p>';
        $html .= '</article>';
    }
    $html .= '</div></section>';
    return $html;
}

function renderHomeGalleryPreview(PDO $db): string {
    $rows = $db->query('SELECT title, slug, description FROM galleries WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3')->fetchAll();
    return '<section data-home-gallery style="margin:2rem 0;"><h2 style="margin:0 0 .8rem;">Galerien</h2>' . renderGalleryCards($rows) . '</section>';
}

function buildDefaultHomeSliderItems(PDO $db): array {
    $rows = $db->query("SELECT g.id, g.title, g.slug, g.description,
        (SELECT image_path FROM gallery_images gi WHERE gi.gallery_id = g.id ORDER BY gi.sort_order ASC, gi.id ASC LIMIT 1) AS cover
        FROM galleries g WHERE g.is_published = 1 ORDER BY g.updated_at DESC LIMIT 5")->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => (string)($row['title'] ?? ''),
            'text' => (string)($row['description'] ?? ''),
            'image' => (string)($row['cover'] ?? ''),
            'buttonLabel' => 'Galerie öffnen',
            'buttonUrl' => '/galerie/' . rawurlencode((string)($row['slug'] ?? '')),
        ];
    }
    return $items;
}

function renderHomeSliderItems(array $items): string {
    if (!$items) return '';
    $slides = '';
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $title = trim((string)($item['title'] ?? ''));
        $text = trim((string)($item['text'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        $buttonLabel = trim((string)($item['buttonLabel'] ?? ''));
        $buttonUrl = trim((string)($item['buttonUrl'] ?? ''));
        if ($title === '' && $text === '' && $image === '' && $buttonLabel === '' && $buttonUrl === '') {
            continue;
        }
        $bg = $image !== ''
            ? 'background-image:url(' . h($image) . '); background-size:cover; background-position:center;'
            : 'background: linear-gradient(135deg, color-mix(in srgb, var(--site-primary) 18%, white), white);';

        $slides .= '<article class="home-slide" style="min-height:360px; border-radius:18px; overflow:hidden; border:1px solid #e5e7eb; position:relative; ' . $bg . '">';
        $slides .= '<div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(17,24,39,.78), rgba(17,24,39,.2));"></div>';
        $slides .= '<div style="position:relative; z-index:2; padding:2rem; display:flex; flex-direction:column; justify-content:flex-end; height:100%; color:#fff;">';
        if ($title !== '') {
            $slides .= '<h3 style="margin:0 0 .45rem; font-size:1.65rem; line-height:1.2; font-weight:800;">' . h($title) . '</h3>';
        }
        if ($text !== '') {
            $slides .= '<p style="margin:0 0 .9rem; color:#e5e7eb; max-width:62ch;">' . h($text) . '</p>';
        }
        if ($buttonLabel !== '' && $buttonUrl !== '') {
            $slides .= '<a href="' . h(normalizeMenuUrl($buttonUrl)) . '" style="display:inline-flex; align-items:center; gap:.35rem; color:#fff; font-weight:700; text-decoration:none; border:1px solid rgba(255,255,255,.4); border-radius:999px; padding:.55rem .9rem; width:max-content;">' . h($buttonLabel) . '</a>';
        }
        $slides .= '</div></article>';
    }

    if ($slides === '') return '';

    return '<section data-home-slider class="py-14 bg-white">'
        . '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">'
        . '<div class="flex items-center justify-between gap-4 mb-4">'
        . '<h2 class="text-3xl font-extrabold text-gray-900">Highlights</h2>'
        . '<div class="flex gap-2">'
        . '<button type="button" id="homeSlidePrev" class="border border-gray-300 bg-white rounded-lg px-3 py-2 text-sm font-bold">â†</button>'
        . '<button type="button" id="homeSlideNext" class="border border-gray-300 bg-white rounded-lg px-3 py-2 text-sm font-bold">â†’</button>'
        . '</div></div>'
        . '<div id="homeSliderTrack" class="grid grid-cols-1 gap-4">' . $slides . '</div>'
        . '</div></section>';
}

function renderStaticRouteWithCms(PDO $db, string $filePath, string $routePath, array $branding, array $mainMenu, array $footerMenu, array $seo): void {
    $html = @file_get_contents($filePath);
    if ($html === false) {
        http_response_code(500);
        echo 'Seite konnte nicht geladen werden.';
        return;
    }

    $content = '';
    if (preg_match('~<main[^>]*>(.*?)</main>~is', $html, $m)) {
        $content = $m[1];
    } elseif (preg_match('~<body[^>]*>(.*?)</body>~is', $html, $m)) {
        $content = $m[1];
    } else {
        $content = $html;
    }

    $content = preg_replace('~<(?:header|footer|nav)[^>]*>.*?</(?:header|footer|nav)>~is', '', $content);
    $content = preg_replace_callback('/\[form:([a-zA-Z0-9\-_]+)\]/', static function ($m) {
        return renderFormEmbed((string) $m[1]);
    }, $content);
    $content = appendTargetPathForms($db, $routePath, $content);
    $pageScripts = extractStaticRouteScripts($html);
    $titleBase = ucwords(str_replace(['-', '/'], ' ', trim($routePath)));
    $title = $titleBase !== '' ? $titleBase . ' - ' . (string) ($seo['defaultMetaTitle'] ?? SITE_NAME) : (string) ($seo['defaultMetaTitle'] ?? SITE_NAME);
    $description = (string) ($seo['defaultMetaDescription'] ?? '');
    $head = renderSeoHead($title, $description, canonicalForPath($routePath), false, false, (string) ($seo['defaultOgImage'] ?? ''));

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . $head
        . '<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>'
        . '<script>tailwind.config={theme:{extend:{colors:{primary:"#7c3aed","primary-dark":"#5b21b6","primary-light":"#a78bfa","bg-light":"#f8faf9","bg-section":"#f1f5f3",surface:"#ffffff"}}}};</script>'
        . '<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">'
        . '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="/src/css/style.css"></head><body>'
        . renderShellStart($branding, $mainMenu)
        . '<main class="pt-24" style="max-width:1100px; margin:0 auto; padding:3rem 1rem 5rem;"><article class="prose max-w-none">'
        . $content
        . '</article></main>'
        . renderShellEnd($branding, $footerMenu)
        . '<script src="/src/js/main.js"></script>'
        . $pageScripts
        . '</body></html>';
}

function extractStaticRouteScripts(string $html): string {
    preg_match_all('~<script\b[^>]*>.*?</script>~is', $html, $matches);
    $scripts = [];
    foreach ($matches[0] as $script) {
        if (preg_match('~<script[^>]+\bsrc\s*=\s*["\']([^"\']+)["\']~i', $script, $srcMatch)) {
            $src = $srcMatch[1];
            if (
                str_contains($src, 'cdn.tailwindcss.com')
                || str_contains($src, 'src/js/main.js')
                || str_contains($src, 'src/js/site-config.js')
            ) {
                continue;
            }
            if (!preg_match('~^(?:https?:)?//|^/~i', $src)) {
                $script = str_replace($srcMatch[0], str_replace($src, '/' . ltrim($src, '/'), $srcMatch[0]), $script);
            }
        } elseif (stripos($script, 'tailwind.config') !== false) {
            continue;
        }
        $scripts[] = $script;
    }
    return implode("\n", $scripts);
}

function renderShellStart(array $branding, array $mainMenu): string {
    $desktopMenu = renderDesktopMenuItems($mainMenu);
    $mobileMenu = renderMobileMenuItems($mainMenu);
    $headerLogo = h((string) ($branding['logoHeader'] ?? '/src/wkc-logo.svg'));
    $headerWordmark = 'WKC';
    return '<nav id="navbar" class="fixed w-full z-50 transition-all duration-300 bg-white/80 backdrop-blur-md shadow-sm">'
        . '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-between h-20 items-center">'
        . '<a href="/" class="flex items-center group"><img src="' . $headerLogo . '" data-brand-logo="header" alt="Logo" class="h-12 w-auto max-w-[6rem]"><span class="brand-wordmark hidden sm:inline">' . $headerWordmark . '</span></a>'
        . '<div class="hidden lg:flex items-center gap-1">' . $desktopMenu . '</div>'
        . '</div></div></nav>'
        . '<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[998] hidden opacity-0 transition-opacity duration-300 lg:hidden"></div>'
        . '<div id="mobile-menu" class="fixed bottom-0 left-0 right-0 z-[999] lg:hidden translate-y-full transition-transform duration-500 ease-out">'
        . '<div class="bg-white rounded-t-3xl shadow-2xl shadow-black/20 max-h-[85vh] overflow-y-auto">'
        . '<div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-gray-300 rounded-full"></div></div>'
        . '<div class="px-6 pb-4"><p class="text-xs font-bold text-primary uppercase tracking-widest mb-3">Navigation</p>'
        . '<div class="flex items-center gap-3"><img src="' . $headerLogo . '" data-brand-logo="header" alt="Logo" class="h-10 w-auto mb-4 max-w-[6rem]"><span class="brand-wordmark brand-wordmark-mobile mb-4">' . $headerWordmark . '</span></div></div>'
        . '<nav class="px-6 pb-8 space-y-1">' . $mobileMenu . '</nav></div></div>'
        . '<button id="mobile-menu-btn" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[1000] lg:hidden w-14 h-14 bg-primary text-white rounded-full shadow-xl shadow-primary/30 flex items-center justify-center hover:bg-primary-dark transition-all active:scale-95">'
        . '<span class="material-symbols-outlined text-2xl menu-icon transition-transform duration-300">menu</span>'
        . '<span class="material-symbols-outlined text-2xl close-icon absolute transition-transform duration-300 opacity-0 rotate-90">close</span>'
        . '</button>';
}

function renderShellEnd(array $branding, array $footerMenu): string {
    $menuLinks = '';
    foreach ($footerMenu as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') continue;
        $url = normalizeMenuUrl((string) ($item['url'] ?? '#'));
        $menuLinks .= '<li><a href="' . h($url) . '" class="text-gray-400 hover:text-primary transition-colors">' . h($label) . '</a></li>';
    }
    if ($menuLinks === '') {
        $menuLinks = '<li><a href="/" class="text-gray-400 hover:text-primary transition-colors">Startseite</a></li>'
            . '<li><a href="/neuigkeiten" class="text-gray-400 hover:text-primary transition-colors">Neuigkeiten</a></li>'
            . '<li><a href="/themen" class="text-gray-400 hover:text-primary transition-colors">Themen</a></li>'
            . '<li><a href="/#kontakt" class="text-gray-400 hover:text-primary transition-colors">Kontakt</a></li>';
    }

    return '<footer class="bg-gray-900 text-white pt-16 pb-24">'
        . '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">'
        . '<div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">'
        . '<div class="md:col-span-2">'
        . '<p class="text-gray-400 max-w-sm leading-relaxed text-sm">Die WKC ist ein engagierter Karnevalsverein für Gemeinschaft, Brauchtum und gelebte Tradition.</p>'
        . '</div>'
        . '<div><h4 class="font-bold mb-6 text-sm uppercase tracking-widest text-gray-400">Navigation</h4><ul class="space-y-3 text-sm">' . $menuLinks . '</ul></div>'
        . '<div><h4 class="font-bold mb-6 text-sm uppercase tracking-widest text-gray-400">Rechtliches</h4>'
        . '<ul class="space-y-3 text-sm">'
        . '<li><a href="/impressum" class="text-gray-400 hover:text-primary transition-colors">Impressum</a></li>'
        . '<li><a href="/datenschutz" class="text-gray-400 hover:text-primary transition-colors">Datenschutz</a></li>'
        . '</ul>'
        . '<div class="mt-6"><h4 class="font-bold mb-3 text-sm uppercase tracking-widest text-gray-400">Kontakt</h4>'
        . '<div class="flex items-center gap-2 text-sm text-gray-400"><span class="material-symbols-outlined text-primary text-base">mail</span>info@zukunft-wulften.de</div></div>'
        . '</div></div>'
        . '<div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">'
        . '<p>&copy; ' . date('Y') . ' <span data-site-name>' . h((string) ($branding['siteName'] ?? SITE_NAME)) . '</span>. Alle Rechte vorbehalten.</p>'
        . '<div class="flex items-center gap-4 mt-3 md:mt-0"><span>Gemeinsam für unsere Heimat.</span><a href="/admin/" class="hover:text-primary transition-colors">Login</a></div>'
        . '</div></div></footer><script src="/src/js/site-config.js"></script>';
}

$settings = getAppSettings();
$branding = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];
$menu = is_array($settings['menu'] ?? null) ? $settings['menu'] : [];
$seo = is_array($settings['seo'] ?? null) ? $settings['seo'] : [];
$features = is_array($settings['features'] ?? null) ? $settings['features'] : [];
$homepageSettings = is_array($settings['homepage'] ?? null) ? $settings['homepage'] : [];

$mainMenu = filterMenuItems(is_array($menu['main'] ?? null) ? $menu['main'] : [], $features);
$footerMenu = filterMenuItems(is_array($menu['footer'] ?? null) ? $menu['footer'] : [], $features);

$staticRoutes = [
    'neuigkeiten' => 'neuigkeiten.html',
    'themen' => 'themen.html',
    'ueber-uns' => 'ueber-uns.html',
    'vorstand' => 'vorstand.html',
    'kommunalwahl-2026' => 'kommunalwahl-2026.html',
    'kommunalwahlen-2021' => 'kommunalwahlen-2021.html',
    'so-funktioniert-waehlen' => 'so-funktioniert-waehlen.html',
    'termine' => 'termine.html',
    'garden' => 'garden.html',
];

if ($path === 'mitglied') {
    header('Location: /vorstand', true, 301);
    exit;
}

if (preg_match('~^mitglied/([^/]+)$~', $path, $m)) {
    header('Location: /vorstand', true, 301);
    exit;
}

if (preg_match('~^(\d{4})/([^/]+)$~', $path, $m)) {
    $slug = $m[2];
    header('Location: /artikel/' . rawurlencode($slug), true, 302);
    exit;
}

if (preg_match('~^artikel/([^/]+)$~', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/artikel.html';
    exit;
}

if (preg_match('~^vorstand/([^/]+)$~', $path, $m)) {
    header('Location: /vorstand', true, 301);
    exit;
}

if ($path === 'galerien') {
    $rows = $db->query("SELECT title, slug, description FROM galleries WHERE is_published = 1 ORDER BY created_at DESC")->fetchAll();
    $title = 'Galerien - ' . (string) ($seo['defaultMetaTitle'] ?? SITE_NAME);
    $desc = (string) ($seo['defaultMetaDescription'] ?? '');
    $head = renderSeoHead($title, $desc, canonicalForPath('galerien'), false, false, (string) ($seo['defaultOgImage'] ?? ''));
    ?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?= $head ?><script>tailwind={};tailwind.config={theme:{extend:{colors:{primary:'#7c3aed','primary-dark':'#5b21b6','primary-light':'#a78bfa','bg-light':'#f8faf9','bg-section':'#f1f5f3',surface:'#ffffff'}}}};</script><script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script><link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"><link rel="stylesheet" href="/src/css/style.css"></head>
    <body>
        <?= renderShellStart($branding, $mainMenu) ?>
        <main class="pt-24" style="max-width: 1100px; margin: 0 auto; padding: 3rem 1rem 5rem;"><h1 style="margin:0 0 .75rem;">Galerien</h1><?= renderGalleryCards($rows) ?></main>
        <?= renderShellEnd($branding, $footerMenu) ?>
        <script src="/src/js/main.js"></script>
    </body></html>
    <?php
    exit;
}

if (preg_match('~^galerie/([^/]+)$~', $path, $m)) {
    $slug = $m[1];
    $stmt = $db->prepare('SELECT id, title, description FROM galleries WHERE slug = :slug AND is_published = 1 LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $gallery = $stmt->fetch();
    if ($gallery) {
        $title = (string) $gallery['title'];
        $desc = (string) ($gallery['description'] ?? '');
        $head = renderSeoHead($title, $desc, canonicalForPath('galerie/' . $slug), false, false, (string) ($seo['defaultOgImage'] ?? ''));
        ?>
        <!DOCTYPE html>
        <html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?= $head ?><script>tailwind={};tailwind.config={theme:{extend:{colors:{primary:'#7c3aed','primary-dark':'#5b21b6','primary-light':'#a78bfa','bg-light':'#f8faf9','bg-section':'#f1f5f3',surface:'#ffffff'}}}};</script><script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script><link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"><link rel="stylesheet" href="/src/css/style.css"></head>
        <body>
            <?= renderShellStart($branding, $mainMenu) ?>
            <main class="pt-24" style="max-width: 1100px; margin: 0 auto; padding: 3rem 1rem 5rem;">
                <p style="margin:0 0 .35rem;"><a href="/galerien">Zur Galerie-Uebersicht</a></p>
                <h1 style="margin:0 0 .75rem;"><?= h((string) $gallery['title']) ?></h1>
                <p style="margin:0 0 1rem; color:#6b7280;"><?= h((string) ($gallery['description'] ?? '')) ?></p>
                <?= renderGalleryEmbed($db, $slug) ?>
            </main>
            <?= renderShellEnd($branding, $footerMenu) ?>
            <script src="/src/js/main.js"></script>
        </body></html>
        <?php
        exit;
    }
}

if (preg_match('~^formular/([a-z0-9\-_]+)$~i', $path, $m)) {
    $slug = strtolower((string) $m[1]);
    $stmt = $db->prepare('SELECT title, description, slug FROM forms WHERE slug = :slug AND is_active = 1 LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $formPage = $stmt->fetch();

    if (!$formPage) {
        http_response_code(404);
        $title = 'Formular nicht gefunden - ' . (string) ($seo['defaultMetaTitle'] ?? SITE_NAME);
        $desc = (string) ($seo['defaultMetaDescription'] ?? '');
        $head = renderSeoHead($title, $desc, canonicalForPath('formular/' . $slug), true, true, (string) ($seo['defaultOgImage'] ?? ''));
        ?>
        <!DOCTYPE html>
        <html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?= $head ?><link rel="stylesheet" href="/src/css/style.css"></head>
        <body>
            <main style="max-width: 760px; margin: 7rem auto; padding: 0 1rem;">
                <h1>404</h1><p>Das gewünschte Formular wurde nicht gefunden.</p><p><a href="/">Zur Startseite</a></p>
            </main>
            <script src="/src/js/site-config.js"></script>
        </body></html>
        <?php
        exit;
    }

    $title = trim((string) (($formPage['title'] ?? 'Formular') . ' - ' . ($seo['defaultMetaTitle'] ?? SITE_NAME)));
    $desc = trim((string) ($formPage['description'] ?? ($seo['defaultMetaDescription'] ?? '')));
    $head = renderSeoHead($title, $desc, canonicalForPath('formular/' . $slug), false, false, (string) ($seo['defaultOgImage'] ?? ''));
    ?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?= $head ?><script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script><script>tailwind={};tailwind.config={theme:{extend:{colors:{primary:'#7c3aed','primary-dark':'#5b21b6','primary-light':'#a78bfa','bg-light':'#f8faf9','bg-section':'#f1f5f3',surface:'#ffffff'}}}};</script><link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"><link rel="stylesheet" href="/src/css/style.css"></head>
    <body>
        <?= renderShellStart($branding, $mainMenu) ?>
        <main class="pt-24" style="max-width: 900px; margin: 0 auto; padding: 3rem 1rem 5rem;">
            <p style="margin:0 0 .8rem; color:#6b7280;"><a href="/" style="color:inherit;">Startseite</a> / <?= h((string) ($formPage['title'] ?? 'Formular')) ?></p>
            <h1 style="font-size: clamp(1.8rem, 2.4vw, 2.4rem); margin-bottom: .75rem;"><?= h((string) ($formPage['title'] ?? 'Formular')) ?></h1>
            <?php if (trim((string) ($formPage['description'] ?? '')) !== ''): ?>
                <p style="margin:0 0 1.25rem; color:#6b7280;"><?= h((string) $formPage['description']) ?></p>
            <?php endif; ?>
            <div data-wkc-form="<?= h($slug) ?>" class="wkc-form-embed"></div>
        </main>
        <?= renderShellEnd($branding, $footerMenu) ?>
        <script src="/src/js/main.js"></script>
    </body></html>
    <?php
    exit;
}

if (array_key_exists($path, $staticRoutes)) {
    $checkStmt = $db->prepare("SELECT id FROM site_pages WHERE path = :path AND status = 'published' LIMIT 1");
    $checkStmt->execute([':path' => $path]);
    $hasDynamicPage = (bool) $checkStmt->fetch();
    if (!$hasDynamicPage) {
        $file = __DIR__ . '/' . $staticRoutes[$path];
        if (is_file($file)) {
            renderStaticRouteWithCms($db, $file, $path, $branding, $mainMenu, $footerMenu, $seo);
            exit;
        }
    }
}

$stmt = $db->prepare("SELECT * FROM site_pages WHERE path = :path AND status = 'published' LIMIT 1");
$stmt->execute([':path' => $path]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $title = 'Seite nicht gefunden - ' . (string) ($seo['defaultMetaTitle'] ?? SITE_NAME);
    $desc = (string) ($seo['defaultMetaDescription'] ?? '');
    $head = renderSeoHead($title, $desc, canonicalForPath($path), true, true, (string) ($seo['defaultOgImage'] ?? ''));
    ?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?= $head ?><link rel="stylesheet" href="/src/css/style.css"></head>
    <body>
        <main style="max-width: 760px; margin: 7rem auto; padding: 0 1rem;">
            <h1>404</h1><p>Die gewuenschte Seite wurde nicht gefunden.</p><p><a href="/">Zur Startseite</a></p>
        </main>
        <script src="/src/js/site-config.js"></script>
    </body></html>
    <?php
    exit;
}

$title = trim((string) ($page['meta_title'] ?: $page['title'] ?: ($seo['defaultMetaTitle'] ?? SITE_NAME)));
$description = trim((string) ($page['meta_description'] ?: ($seo['defaultMetaDescription'] ?? '')));
$canonical = canonicalForPath((string) $page['path'], (string) ($page['canonical_url'] ?? ''));
$noindex = normalizeBool($page['noindex'] ?? 0, false);
$nofollow = normalizeBool($page['nofollow'] ?? 0, false);
$ogImage = trim((string) ($page['og_image'] ?: ($seo['defaultOgImage'] ?? '')));
$content = (string) ($page['content_html'] ?? '');
$blocks = json_decode((string) ($page['blocks_json'] ?? '{}'), true);
if (!is_array($blocks)) $blocks = [];

$content = preg_replace_callback('/\[gallery:([a-zA-Z0-9\-]+)\]/', function ($m) use ($db) {
    return renderGalleryEmbed($db, $m[1]);
}, $content);
$content = preg_replace_callback('/\[form:([a-zA-Z0-9\-_]+)\]/', static function ($m) {
    return renderFormEmbed((string) $m[1]);
}, $content);
$content = appendTargetPathForms($db, (string) ($page['path'] ?? ''), $content);
$content = normalizeLegacyHtmlLinks($content);

$isHomepage = ((string) $page['path'] === '');
$showHero = normalizeBool($blocks['heroEnabled'] ?? $homepageSettings['heroEnabled'] ?? true, true);
$showSlider = normalizeBool($blocks['sliderEnabled'] ?? true, true);
$showNews = normalizeBool($blocks['newsEnabled'] ?? $homepageSettings['newsEnabled'] ?? true, true);
$showEvents = normalizeBool($blocks['eventsEnabled'] ?? $homepageSettings['eventsEnabled'] ?? true, true);
$showGallery = normalizeBool($blocks['galleryPreviewEnabled'] ?? $homepageSettings['galleryPreviewEnabled'] ?? true, true);
$showTitleArea = normalizeBool($blocks['titleAreaEnabled'] ?? true, true);
$showGoals = false;
$sliderItems = [];
if (isset($blocks['sliderItems']) && is_array($blocks['sliderItems'])) {
    foreach ($blocks['sliderItems'] as $item) {
        if (!is_array($item)) continue;
        $sliderItems[] = [
            'title' => (string)($item['title'] ?? ''),
            'text' => (string)($item['text'] ?? ''),
            'image' => (string)($item['image'] ?? ''),
            'buttonLabel' => (string)($item['buttonLabel'] ?? ''),
            'buttonUrl' => (string)($item['buttonUrl'] ?? ''),
        ];
    }
}
if (!$sliderItems) {
    $sliderItems = buildDefaultHomeSliderItems($db);
}

$head = renderSeoHead($title, $description, $canonical, $noindex, $nofollow, $ogImage);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $head ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#7c3aed',
                        'primary-dark': '#5b21b6',
                        'primary-light': '#a78bfa',
                        'bg-light': '#f8faf9',
                        'bg-section': '#f1f5f3',
                        surface: '#ffffff',
                    },
                },
            },
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/src/css/style.css">
</head>
<body>
    <?= renderShellStart($branding, $mainMenu) ?>
    <?php if ($isHomepage): ?>
        <main class="pt-20">
            <?php if ($showHero): ?>
                <section id="home-hero" data-home-hero class="relative min-h-[76vh] flex items-center justify-center overflow-hidden">
                    <div class="absolute inset-0 bg-[url('/src/main_wkc.jpg')] bg-cover bg-center"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/65 to-black/80"></div>
                    <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-24">
                        <p class="text-white/80 font-bold uppercase tracking-[0.22em] text-xs sm:text-sm">Wulftener Karneval Club e.V.</p>
                        <h1 class="mt-5 text-white text-4xl sm:text-5xl md:text-6xl font-black leading-tight">
                            <?= h((string) ($branding['siteName'] ?? 'Wulftener Karneval Club e.V.')) ?>
                        </h1>
                        <p class="mt-4 text-white/90 text-sm sm:text-base font-semibold tracking-wide">Gegründet 1992</p>
                        <div class="mt-10">
                            <a href="/ueber-uns" class="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3 text-sm sm:text-base font-bold text-primary shadow-xl hover:shadow-2xl transition-all hover:-translate-y-0.5">
                                Unsere Geschichte
                                <span class="material-symbols-outlined text-base">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showSlider): ?>
                <?= renderHomeSliderItems($sliderItems) ?>
            <?php endif; ?>

            <section class="py-12 bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 prose max-w-none" style="line-height:1.75;">
                    <?= $content ?>
                </div>
            </section>

            <?php if ($showNews): ?>
                <section data-home-news id="neuigkeiten" class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-extrabold text-gray-900">Neuigkeiten</h2>
                        <div class="w-16 h-1 rounded-full mt-3 bg-primary"></div>
                        <div id="newsGrid" class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <p class="text-sm text-gray-400 md:col-span-3">Beiträge werden geladen...</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showEvents): ?>
                <section data-home-events id="termine" class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-extrabold text-gray-900">Öffentliche Termine</h2>
                        <div class="w-16 h-1 rounded-full mt-3 bg-primary"></div>
                        <div id="homeEventsList" class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-5">
                            <p class="text-sm text-gray-400 md:col-span-3">Termine werden geladen...</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showGallery): ?>
                <section data-home-gallery class="py-16 bg-white">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-extrabold text-gray-900">Galerie-Highlights</h2>
                        <div class="w-16 h-1 rounded-full mt-3 bg-primary"></div>
                        <div class="mt-6"><?php
                            $homeGalleryRows = $db->query('SELECT title, slug, description FROM galleries WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3')->fetchAll();
                            echo renderGalleryCards($homeGalleryRows);
                        ?></div>
                    </div>
                </section>
            <?php endif; ?>

            <section id="vorstand" class="py-16 bg-gray-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h2 class="text-3xl font-extrabold text-gray-900">Vorstand</h2>
                    <div class="w-16 h-1 rounded-full mt-3 bg-primary"></div>
                    <div id="vorstandGrid" class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        <p class="text-sm text-gray-400 sm:col-span-2 lg:col-span-4">Vorstand wird geladen...</p>
                    </div>
                </div>
            </section>

            <section id="mitglied-werden" class="py-24 bg-gradient-to-br from-primary to-primary-dark relative overflow-hidden">
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>

                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                        <div class="scroll-animate">
                            <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 backdrop-blur-sm text-white text-sm font-semibold mb-6 border border-white/20">
                                <span class="material-symbols-outlined text-base">group_add</span>
                                Mitmachen
                            </span>
                            <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4 leading-tight">Selbst aktiv werden und mitgestalten</h2>
                            <p class="text-white/80 text-lg mb-6 leading-relaxed">Willst du dem Verein beitreten und uns unterstützen? Dann werde jetzt Mitglied beim WKC.</p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-2xl scroll-animate animation-delay-100">
                            <h3 class="text-xl font-extrabold text-gray-900 mb-1">Jetzt Mitglied werden</h3>
                            <p class="text-sm text-gray-500 mb-6">Füllen Sie das Formular aus – wir melden uns bei Ihnen.</p>

                            <a href="/formular/beitrittserklaerung" class="inline-block mt-4 bg-primary text-white font-bold py-3 px-8 rounded-xl hover:bg-primary-dark transition-colors shadow-lg hover:shadow-xl hover:-translate-y-0.5">Zur Beitrittserklärung</a>
                        </div>
                    </div>
                </div>
            </section>

            </main>
    <?php else: ?>
        <main class="pt-24" style="max-width: 1100px; margin: 0 auto; padding: 3rem 1rem 5rem;">
            <?php if ($showTitleArea): ?>
                <p style="margin:0 0 .8rem; color:#6b7280;"><a href="/" style="color:inherit;">Startseite</a> / <?= h((string) $page['title']) ?></p>
                <h1 style="font-size: clamp(1.8rem, 2.4vw, 2.4rem); margin-bottom: 1rem;"><?= h((string) $page['title']) ?></h1>
            <?php endif; ?>
            <article class="prose max-w-none" style="line-height: 1.8;"><?= $content ?></article>
        </main>
    <?php endif; ?>
    <?= renderShellEnd($branding, $footerMenu) ?>
    <script src="/src/js/main.js"></script>
    <?php if ($isHomepage && $showSlider): ?>
        <script>
            (function initHomeSlider() {
                const track = document.getElementById('homeSliderTrack');
                const prev = document.getElementById('homeSlidePrev');
                const next = document.getElementById('homeSlideNext');
                if (!track || !prev || !next) return;

                const slides = Array.from(track.querySelectorAll('.home-slide'));
                if (slides.length < 2) return;

                let idx = 0;
                const show = (i) => {
                    idx = (i + slides.length) % slides.length;
                    slides.forEach((s, n) => { s.style.display = n === idx ? 'block' : 'none'; });
                };

                show(0);
                prev.addEventListener('click', () => show(idx - 1));
                next.addEventListener('click', () => show(idx + 1));
                setInterval(() => show(idx + 1), 6000);
            })();
        </script>
    <?php endif; ?>
</body>
</html>
