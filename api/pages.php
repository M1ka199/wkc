<?php
/**
 * CMS pages API for hierarchical routes.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

function uploadPageMedia(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload fehlgeschlagen.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Nur JPG, PNG, WebP und GIF sind erlaubt.');
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Datei ist zu groß.');
    }

    $dir = __DIR__ . '/../uploads/pages';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
    }

    $ext = $allowed[$mime];
    $name = 'page-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    return '/uploads/pages/' . $name;
}

function normalizePath(string $path): string {
    $path = trim($path);
    $path = trim($path, '/');
    if ($path === '' || $path === 'index.php') {
        return '';
    }
    $path = preg_replace('~[^a-zA-Z0-9\-_/]~', '-', $path);
    $path = preg_replace('~/{2,}~', '/', $path);
    return strtolower((string)$path);
}

if ($method === 'GET') {
    if ($action === 'resolve') {
        $path = normalizePath($_GET['path'] ?? '');

        $stmt = $db->prepare("SELECT * FROM site_pages WHERE path = :path AND status = 'published' LIMIT 1");
        $stmt->execute([':path' => $path]);
        $page = $stmt->fetch();
        if (!$page) jsonResponse(['error' => 'Seite nicht gefunden'], 404);

        jsonResponse(['page' => $page]);
    }

    if ($action === 'detail') {
        $user = requireAuth();
        if (!in_array($user['role'], ['admin', 'editor'], true)) {
            jsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) jsonResponse(['error' => 'ID erforderlich'], 400);
        $stmt = $db->prepare("SELECT * FROM site_pages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $page = $stmt->fetch();
        if (!$page) jsonResponse(['error' => 'Seite nicht gefunden'], 404);
        jsonResponse(['page' => $page]);
    }

    // list
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    $search = trim($_GET['search'] ?? '');
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = "WHERE p.title LIKE :search OR p.path LIKE :search2";
        $params[':search'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.title,
            p.path,
            p.status,
            p.updated_at,
            (
                SELECT COUNT(*)
                FROM forms f
                WHERE f.target_path = p.path
            ) AS forms_count
        FROM site_pages p
        {$where}
        ORDER BY p.updated_at DESC
    ");
    $stmt->execute($params);
    jsonResponse(['pages' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    if ($action === 'upload_media') {
        if (empty($_FILES['image'])) {
            jsonResponse(['error' => 'Bild erforderlich'], 400);
        }
        try {
            $path = uploadPageMedia($_FILES['image']);
        } catch (RuntimeException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
        jsonResponse(['success' => true, 'path' => $path], 201);
    }

    if ($action === 'duplicate') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'ID erforderlich'], 400);
        }

        $sourceStmt = $db->prepare("SELECT * FROM site_pages WHERE id = :id LIMIT 1");
        $sourceStmt->execute([':id' => $id]);
        $source = $sourceStmt->fetch();
        if (!$source) {
            jsonResponse(['error' => 'Seite nicht gefunden'], 404);
        }

        $baseTitle = trim((string) ($source['title'] ?? 'Seite'));
        $copyTitle = $baseTitle . ' (Kopie)';
        $basePathRaw = trim((string) ($source['path'] ?? ''));
        $basePath = $basePathRaw !== '' ? normalizePath($basePathRaw . '-kopie') : normalizePath($baseTitle . '-kopie');
        if ($basePath === '') {
            $basePath = 'seite-kopie';
        }

        $newPath = $basePath;
        $suffix = 2;
        $existsPathStmt = $db->prepare("SELECT 1 FROM site_pages WHERE path = :path LIMIT 1");
        while (true) {
            $existsPathStmt->execute([':path' => $newPath]);
            if (!$existsPathStmt->fetchColumn()) {
                break;
            }
            $newPath = $basePath . '-' . $suffix;
            $suffix++;
        }

        $insertStmt = $db->prepare("INSERT INTO site_pages (
            title, path, meta_title, meta_description, canonical_url, noindex, nofollow, og_image, content_html, blocks_json, status, created_at, updated_at
        ) VALUES (
            :title, :path, :meta_title, :meta_description, :canonical_url, :noindex, :nofollow, :og_image, :content_html, :blocks_json, 'draft', datetime('now'), datetime('now')
        )");
        $insertStmt->execute([
            ':title' => $copyTitle,
            ':path' => $newPath,
            ':meta_title' => $source['meta_title'],
            ':meta_description' => $source['meta_description'],
            ':canonical_url' => null,
            ':noindex' => 1,
            ':nofollow' => 0,
            ':og_image' => $source['og_image'],
            ':content_html' => $source['content_html'],
            ':blocks_json' => $source['blocks_json'] ?: '{}',
        ]);

        jsonResponse([
            'success' => true,
            'id' => (int) $db->lastInsertId(),
            'message' => 'Seite wurde dupliziert.',
        ], 201);
    }

    $title = trim($_POST['title'] ?? '');
    $isHomepage = !empty($_POST['is_homepage']);
    $path = $isHomepage ? '' : normalizePath($_POST['path'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $canonicalUrl = trim($_POST['canonical_url'] ?? '');
    $noindex = !empty($_POST['noindex']) ? 1 : 0;
    $nofollow = !empty($_POST['nofollow']) ? 1 : 0;
    $ogImage = trim($_POST['og_image'] ?? '');
    $contentHtml = (string)($_POST['content_html'] ?? '');
    $blocksJson = (string)($_POST['blocks_json'] ?? '{}');

    if (isset($_POST['hero_enabled']) || isset($_POST['news_enabled']) || isset($_POST['events_enabled']) || isset($_POST['gallery_preview_enabled'])) {
        $sliderItemsRaw = trim((string)($_POST['slider_items_json'] ?? '[]'));
        $sliderItems = json_decode($sliderItemsRaw, true);
        if (!is_array($sliderItems)) {
            $sliderItems = [];
        }

        $cleanSliderItems = [];
        foreach ($sliderItems as $item) {
            if (!is_array($item)) continue;
            $title = trim((string)($item['title'] ?? ''));
            $text = trim((string)($item['text'] ?? ''));
            $image = trim((string)($item['image'] ?? ''));
            $buttonLabel = trim((string)($item['buttonLabel'] ?? ''));
            $buttonUrl = trim((string)($item['buttonUrl'] ?? ''));
            if ($title === '' && $text === '' && $image === '' && $buttonLabel === '' && $buttonUrl === '') {
                continue;
            }
            $cleanSliderItems[] = [
                'title' => $title,
                'text' => $text,
                'image' => $image,
                'buttonLabel' => $buttonLabel,
                'buttonUrl' => $buttonUrl,
            ];
        }

        $blocksJson = json_encode([
            'heroEnabled' => !empty($_POST['hero_enabled']),
            'newsEnabled' => !empty($_POST['news_enabled']),
            'eventsEnabled' => !empty($_POST['events_enabled']),
            'galleryPreviewEnabled' => !empty($_POST['gallery_preview_enabled']),
            'sliderEnabled' => !empty($_POST['slider_enabled']),
            'titleAreaEnabled' => !empty($_POST['title_area_enabled']),
            'sliderItems' => $cleanSliderItems,
        ], JSON_UNESCAPED_UNICODE);
    }

    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'draft';
    }

    if ($title === '') {
        jsonResponse(['error' => 'Titel ist ein Pflichtfeld.'], 400);
    }

    if (!$isHomepage && $path === '') {
        jsonResponse(['error' => 'Pfad ist ein Pflichtfeld (außer bei Startseite).'], 400);
    }

    // Validate blocks JSON early to avoid broken records.
    json_decode($blocksJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'blocks_json ist kein gültiges JSON.'], 400);
    }

    $id = intval($_POST['id'] ?? 0);

    $dupParams = [':path' => $path];
    $dupSql = 'SELECT id FROM site_pages WHERE path = :path';
    if ($id > 0) {
        $dupSql .= ' AND id != :id';
        $dupParams[':id'] = $id;
    }
    $dupSql .= ' LIMIT 1';
    $dupStmt = $db->prepare($dupSql);
    $dupStmt->execute($dupParams);
    if ($dupStmt->fetch()) {
        if ($path === '') {
            jsonResponse(['error' => 'Es existiert bereits eine Startseite.'], 409);
        }
        jsonResponse(['error' => 'Der Pfad ist bereits vergeben.'], 409);
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE site_pages
            SET title = :title,
                path = :path,
                meta_title = :meta_title,
                meta_description = :meta_description,
                canonical_url = :canonical_url,
                noindex = :noindex,
                nofollow = :nofollow,
                og_image = :og_image,
                content_html = :content_html,
                blocks_json = :blocks_json,
                status = :status,
                updated_at = datetime('now')
            WHERE id = :id");
        $stmt->execute([
            ':title' => $title,
            ':path' => $path,
            ':meta_title' => $metaTitle ?: null,
            ':meta_description' => $metaDescription ?: null,
            ':canonical_url' => $canonicalUrl ?: null,
            ':noindex' => $noindex,
            ':nofollow' => $nofollow,
            ':og_image' => $ogImage ?: null,
            ':content_html' => $contentHtml,
            ':blocks_json' => $blocksJson,
            ':status' => $status,
            ':id' => $id,
        ]);
        jsonResponse(['success' => true, 'message' => 'Seite aktualisiert.']);
    }

    $stmt = $db->prepare("INSERT INTO site_pages (title, path, meta_title, meta_description, canonical_url, noindex, nofollow, og_image, content_html, blocks_json, status)
        VALUES (:title, :path, :meta_title, :meta_description, :canonical_url, :noindex, :nofollow, :og_image, :content_html, :blocks_json, :status)");
    $stmt->execute([
        ':title' => $title,
        ':path' => $path,
        ':meta_title' => $metaTitle ?: null,
        ':meta_description' => $metaDescription ?: null,
        ':canonical_url' => $canonicalUrl ?: null,
        ':noindex' => $noindex,
        ':nofollow' => $nofollow,
        ':og_image' => $ogImage ?: null,
        ':content_html' => $contentHtml,
        ':blocks_json' => $blocksJson,
        ':status' => $status,
    ]);

    jsonResponse(['success' => true, 'message' => 'Seite erstellt.', 'id' => (int)$db->lastInsertId()], 201);
}

if ($method === 'DELETE') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'ID erforderlich'], 400);
    $db->prepare("DELETE FROM site_pages WHERE id = :id")->execute([':id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Seite gelöscht.']);
}

jsonResponse(['error' => 'Ungültige Anfrage'], 400);
