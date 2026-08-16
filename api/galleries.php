<?php
/**
 * Gallery API: album CRUD + image management.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = getDB();

function normalizeSlug(string $text): string {
    $text = trim(strtolower($text));
    $text = preg_replace('~[^a-z0-9\-\s]~', '', $text);
    $text = preg_replace('~\s+~', '-', $text);
    $text = preg_replace('~-{2,}~', '-', $text);
    return trim((string)$text, '-');
}

function uploadGalleryImage(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Bild-Upload fehlgeschlagen.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Nur JPG, PNG und WebP sind erlaubt.');
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE * 2) {
        throw new RuntimeException('Bild ist zu groß.');
    }

    $dir = __DIR__ . '/../uploads/gallery';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
    }

    $ext = $allowed[$mime];
    $name = 'gallery-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Bild konnte nicht gespeichert werden.');
    }

    return '/uploads/gallery/' . $name;
}

function extractUploadFiles(array $filesInput): array {
    if (isset($filesInput['tmp_name']) && is_array($filesInput['tmp_name'])) {
        $out = [];
        $count = count($filesInput['tmp_name']);
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name' => $filesInput['name'][$i] ?? '',
                'type' => $filesInput['type'][$i] ?? '',
                'tmp_name' => $filesInput['tmp_name'][$i] ?? '',
                'error' => $filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesInput['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
    return [$filesInput];
}

if ($method === 'GET') {
    if ($action === 'public_list') {
        $stmt = $db->query("SELECT id, title, slug, description, created_at FROM galleries WHERE is_published = 1 ORDER BY created_at DESC");
        jsonResponse(['galleries' => $stmt->fetchAll()]);
    }

    if ($action === 'public_detail') {
        $slug = trim($_GET['slug'] ?? '');
        if ($slug === '') jsonResponse(['error' => 'Slug erforderlich'], 400);

        $stmt = $db->prepare("SELECT * FROM galleries WHERE slug = :slug AND is_published = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $gallery = $stmt->fetch();
        if (!$gallery) jsonResponse(['error' => 'Galerie nicht gefunden'], 404);

        $imgStmt = $db->prepare("SELECT * FROM gallery_images WHERE gallery_id = :id ORDER BY sort_order ASC, id ASC");
        $imgStmt->execute([':id' => $gallery['id']]);
        $gallery['images'] = $imgStmt->fetchAll();

        jsonResponse(['gallery' => $gallery]);
    }

    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    if ($action === 'detail') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) jsonResponse(['error' => 'ID erforderlich'], 400);

        $stmt = $db->prepare("SELECT * FROM galleries WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $gallery = $stmt->fetch();
        if (!$gallery) jsonResponse(['error' => 'Galerie nicht gefunden'], 404);

        $imgStmt = $db->prepare("SELECT * FROM gallery_images WHERE gallery_id = :id ORDER BY sort_order ASC, id ASC");
        $imgStmt->execute([':id' => $id]);
        $gallery['images'] = $imgStmt->fetchAll();

        jsonResponse(['gallery' => $gallery]);
    }

    $stmt = $db->query("SELECT id, title, slug, description, is_published, created_at, updated_at FROM galleries ORDER BY updated_at DESC");
    jsonResponse(['galleries' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        $slug = normalizeSlug($slugInput !== '' ? $slugInput : $title);
        $description = trim($_POST['description'] ?? '');
        $isPublished = (int)($_POST['is_published'] ?? 1) ? 1 : 0;

        if ($title === '' || $slug === '') {
            jsonResponse(['error' => 'Titel und Slug sind Pflichtfelder.'], 400);
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE galleries SET title = :title, slug = :slug, description = :description, is_published = :pub, updated_at = datetime('now') WHERE id = :id");
            $stmt->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':description' => $description ?: null,
                ':pub' => $isPublished,
                ':id' => $id,
            ]);
            jsonResponse(['success' => true, 'message' => 'Galerie aktualisiert.']);
        }

        $stmt = $db->prepare("INSERT INTO galleries (title, slug, description, is_published) VALUES (:title, :slug, :description, :pub)");
        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':description' => $description ?: null,
            ':pub' => $isPublished,
        ]);
        jsonResponse(['success' => true, 'message' => 'Galerie erstellt.', 'id' => (int)$db->lastInsertId()], 201);
    }

    if ($action === 'upload_image') {
        $galleryId = intval($_POST['gallery_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        if ($galleryId <= 0) jsonResponse(['error' => 'gallery_id erforderlich'], 400);
        $uploadBucket = $_FILES['images'] ?? $_FILES['image'] ?? null;
        if (!$uploadBucket) jsonResponse(['error' => 'Bild erforderlich'], 400);

        $files = extractUploadFiles($uploadBucket);
        if (!$files) jsonResponse(['error' => 'Bild erforderlich'], 400);

        $uploaded = [];
        $sortOrder = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images WHERE gallery_id = " . (int)$galleryId)->fetchColumn();
        $stmt = $db->prepare("INSERT INTO gallery_images (gallery_id, image_path, caption, sort_order) VALUES (:gid, :path, :caption, :sort)");

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $path = uploadGalleryImage($file);
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }

            $stmt->execute([
                ':gid' => $galleryId,
                ':path' => $path,
                ':caption' => $caption ?: null,
                ':sort' => $sortOrder,
            ]);

            $uploaded[] = [
                'id' => (int)$db->lastInsertId(),
                'image_path' => $path,
                'sort_order' => $sortOrder,
            ];
            $sortOrder++;
        }

        if (!$uploaded) {
            jsonResponse(['error' => 'Keine gueltigen Bilddateien uebergeben.'], 400);
        }

        jsonResponse([
            'success' => true,
            'message' => count($uploaded) > 1 ? 'Bilder hochgeladen.' : 'Bild hochgeladen.',
            'images' => $uploaded,
            'id' => $uploaded[0]['id'],
            'image_path' => $uploaded[0]['image_path'],
        ], 201);
    }

    if ($action === 'update_image') {
        $id = intval($_POST['id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        $sort = intval($_POST['sort_order'] ?? 0);
        if ($id <= 0) jsonResponse(['error' => 'Bild-ID erforderlich'], 400);

        $stmt = $db->prepare("UPDATE gallery_images SET caption = :caption, sort_order = :sort WHERE id = :id");
        $stmt->execute([
            ':caption' => $caption ?: null,
            ':sort' => $sort,
            ':id' => $id,
        ]);

        jsonResponse(['success' => true, 'message' => 'Bild aktualisiert.']);
    }

    jsonResponse(['error' => 'Ungültige Aktion'], 400);
}

if ($method === 'DELETE') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    if ($action === 'image') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) jsonResponse(['error' => 'Bild-ID erforderlich'], 400);

        $stmt = $db->prepare("SELECT image_path FROM gallery_images WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $path = $stmt->fetchColumn();

        if ($path) {
            $full = __DIR__ . '/../' . ltrim((string)$path, '/');
            if (is_file($full)) {
                @unlink($full);
            }
        }

        $db->prepare("DELETE FROM gallery_images WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Bild gelöscht.']);
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'ID erforderlich'], 400);
    $db->prepare("DELETE FROM galleries WHERE id = :id")->execute([':id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Galerie gelöscht.']);
}

jsonResponse(['error' => 'Ungültige Anfrage'], 400);
