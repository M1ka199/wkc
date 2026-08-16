<?php
/**
 * WKC – Messages API
 * Verwaltet alle eingehenden Nachrichten (Kontakt, Beitritt, allgemein).
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// All routes require authentication
$user = requireAuth();
if (!in_array($user['role'], ['admin', 'editor'], true)) {
    jsonResponse(['error' => 'Keine Berechtigung'], 403);
}

$db = getDB();

// ============================
// LIST MESSAGES (with type filter)
// ============================
if ($method === 'GET' && $action === 'list') {
    $type = $_GET['type'] ?? '';
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $where = '1=1';
    $params = [];

    if ($type && in_array($type, ['contact', 'membership', 'message'])) {
        $where .= ' AND type = :type';
        $params[':type'] = $type;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages WHERE $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();

    // Get unread counts by type
    $countsStmt = $db->query("SELECT type, COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM contact_messages GROUP BY type");
    $counts = [];
    while ($row = $countsStmt->fetch()) {
        $counts[$row['type'] ?? 'contact'] = [
            'total' => (int) $row['total'],
            'unread' => (int) $row['unread'],
        ];
    }

    jsonResponse([
        'messages' => $messages,
        'total' => $total,
        'counts' => $counts,
    ]);
}

// ============================
// GET SINGLE MESSAGE
// ============================
if ($method === 'GET' && $action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $message = $stmt->fetch();

    if (!$message) jsonResponse(['error' => 'Nachricht nicht gefunden'], 404);

    // Mark as read
    $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute([':id' => $id]);

    jsonResponse(['message' => $message]);
}

// ============================
// MARK AS READ / UNREAD
// ============================
if ($method === 'POST' && $action === 'toggle_read') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $stmt = $db->prepare("SELECT is_read FROM contact_messages WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetchColumn();

    if ($current === false) jsonResponse(['error' => 'Nachricht nicht gefunden'], 404);

    $newState = $current ? 0 : 1;
    $db->prepare("UPDATE contact_messages SET is_read = :read WHERE id = :id")->execute([
        ':read' => $newState,
        ':id' => $id,
    ]);

    jsonResponse(['success' => true, 'is_read' => $newState]);
}

// ============================
// MARK ALL AS READ (by type)
// ============================
if ($method === 'POST' && $action === 'mark_all_read') {
    $type = $_POST['type'] ?? '';

    if ($type && in_array($type, ['contact', 'membership', 'message'])) {
        $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE type = :type")->execute([':type' => $type]);
    } else {
        $db->exec("UPDATE contact_messages SET is_read = 1");
    }

    jsonResponse(['success' => true, 'message' => 'Alle als gelesen markiert.']);
}

// ============================
// DELETE MESSAGE
// ============================
if ($method === 'POST' && $action === 'delete') {
    if (!in_array($user['role'], ['admin', 'editor'])) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $db->prepare("DELETE FROM contact_messages WHERE id = :id")->execute([':id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Nachricht gelöscht.']);
}

// ============================
// GET UNREAD COUNT (for sidebar badge)
// ============================
if ($method === 'GET' && $action === 'unread_count') {
    $count = (int) $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    jsonResponse(['unread' => $count]);
}

// Fallback
jsonResponse(['error' => 'Ungültige Anfrage'], 400);
