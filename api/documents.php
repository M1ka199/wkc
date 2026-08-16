<?php
/**
 * WKC – Documents API
 * CRUD operations for member documents / Dokumente.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Sub-directory for documents inside UPLOAD_DIR
define('DOCS_DIR', UPLOAD_DIR . 'documents/');

// Allowed document MIME types
$ALLOWED_DOC_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/png',
    'image/webp',
    'text/plain',
    'text/csv',
    'application/zip',
    'application/x-zip-compressed',
];

// ============================
// LIST DOCUMENTS
// ============================
if ($method === 'GET' && $action === 'list') {
    $user = requireAuth();
    $db = getDB();

    $stmt = $db->query("SELECT d.*, u.display_name AS uploader_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id ORDER BY d.created_at DESC");
    $documents = $stmt->fetchAll();

    // Load tags for each document
    foreach ($documents as &$doc) {
        $tagStmt = $db->prepare("SELECT t.id, t.name, t.color FROM document_tags t INNER JOIN document_tag_map m ON t.id = m.tag_id WHERE m.document_id = :did ORDER BY t.name");
        $tagStmt->execute([':did' => $doc['id']]);
        $doc['tags'] = $tagStmt->fetchAll();
    }

    jsonResponse(['documents' => $documents]);
}

// ============================
// LIST TAGS
// ============================
if ($method === 'GET' && $action === 'tags') {
    $user = requireAuth();
    $db = getDB();
    $stmt = $db->query("SELECT * FROM document_tags ORDER BY name ASC");
    jsonResponse(['tags' => $stmt->fetchAll()]);
}

// ============================
// CREATE / UPDATE TAG
// ============================
if ($method === 'POST' && $action === 'save_tag') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'])) jsonResponse(['error' => 'Keine Berechtigung'], 403);

    $db = getDB();
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6b7280');

    if (empty($name)) jsonResponse(['error' => 'Tag-Name erforderlich'], 400);

    if ($id) {
        $db->prepare("UPDATE document_tags SET name = :name, color = :color WHERE id = :id")->execute([':name' => $name, ':color' => $color, ':id' => $id]);
    } else {
        $db->prepare("INSERT INTO document_tags (name, color) VALUES (:name, :color)")->execute([':name' => $name, ':color' => $color]);
        $id = $db->lastInsertId();
    }
    jsonResponse(['success' => true, 'id' => $id, 'message' => 'Tag gespeichert.']);
}

// ============================
// DELETE TAG
// ============================
if ($method === 'POST' && $action === 'delete_tag') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'])) jsonResponse(['error' => 'Keine Berechtigung'], 403);

    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Tag-ID erforderlich'], 400);

    $db = getDB();
    $db->prepare("DELETE FROM document_tag_map WHERE tag_id = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM document_tags WHERE id = :id")->execute([':id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Tag gelöscht.']);
}

// ============================
// UPDATE DOCUMENT TAGS
// ============================
if ($method === 'POST' && $action === 'set_tags') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'])) jsonResponse(['error' => 'Keine Berechtigung'], 403);

    $docId = intval($_POST['document_id'] ?? 0);
    $tagIds = json_decode($_POST['tag_ids'] ?? '[]', true);

    if (!$docId) jsonResponse(['error' => 'Dokument-ID erforderlich'], 400);

    $db = getDB();
    $db->prepare("DELETE FROM document_tag_map WHERE document_id = :did")->execute([':did' => $docId]);

    if (is_array($tagIds)) {
        $insert = $db->prepare("INSERT OR IGNORE INTO document_tag_map (document_id, tag_id) VALUES (:did, :tid)");
        foreach ($tagIds as $tid) {
            $insert->execute([':did' => $docId, ':tid' => intval($tid)]);
        }
    }
    jsonResponse(['success' => true, 'message' => 'Tags aktualisiert.']);
}

// ============================
// DOWNLOAD / SERVE DOCUMENT
// ============================
if ($method === 'GET' && $action === 'download') {
    $user = requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch();

    if (!$doc) jsonResponse(['error' => 'Dokument nicht gefunden'], 404);

    $filePath = __DIR__ . '/../' . ltrim($doc['file_path'], '/');
    if (!file_exists($filePath)) jsonResponse(['error' => 'Datei nicht gefunden'], 404);

    // Clean output buffer and serve file
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// ============================
// UPLOAD DOCUMENT (admin only)
// ============================
if ($method === 'POST' && $action === 'upload') {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        jsonResponse(['error' => 'Titel erforderlich'], 400);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'Datei zu groß (Server-Limit)',
            UPLOAD_ERR_FORM_SIZE  => 'Datei zu groß (Formular-Limit)',
            UPLOAD_ERR_PARTIAL    => 'Datei nur teilweise hochgeladen',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewählt',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
            UPLOAD_ERR_CANT_WRITE => 'Schreibfehler auf Festplatte',
        ];
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        jsonResponse(['error' => $errMap[$code] ?? 'Upload-Fehler'], 400);
    }

    $file = $_FILES['file'];

    // Validate size (10 MB for documents)
    $maxDocSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxDocSize) {
        jsonResponse(['error' => 'Datei zu groß (max. 10 MB)'], 400);
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $ALLOWED_DOC_TYPES)) {
        jsonResponse(['error' => 'Dateityp nicht erlaubt: ' . $mimeType], 400);
    }

    // Create documents directory if it doesn't exist
    if (!is_dir(DOCS_DIR)) {
        mkdir(DOCS_DIR, 0755, true);
    }

    // Generate safe filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = uniqid('doc_') . ($ext ? '.' . strtolower($ext) : '');
    $destPath = DOCS_DIR . $safeFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        jsonResponse(['error' => 'Datei konnte nicht gespeichert werden'], 500);
    }

    // Store in DB
    $webPath = '/uploads/documents/' . $safeFilename;
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO documents (title, description, file_path, file_name, file_size, uploaded_by) VALUES (:t, :d, :fp, :fn, :fs, :uid)");
    $stmt->execute([
        ':t' => $title,
        ':d' => $description ?: null,
        ':fp' => $webPath,
        ':fn' => $file['name'],
        ':fs' => $file['size'],
        ':uid' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'Dokument hochgeladen.', 'id' => $db->lastInsertId()], 201);
}

// ============================
// DELETE DOCUMENT (admin only)
// ============================
if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
    } else {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    }
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch();

    if (!$doc) jsonResponse(['error' => 'Dokument nicht gefunden'], 404);

    // Delete file from disk
    $filePath = __DIR__ . '/../' . ltrim($doc['file_path'], '/');
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete DB record
    $db->prepare("DELETE FROM documents WHERE id = :id")->execute([':id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Dokument gelöscht.']);
}

jsonResponse(['error' => 'Ungültige Anfrage'], 400);
