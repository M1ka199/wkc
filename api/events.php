<?php
/**
 * WKC – Events API
 * CRUD operations for member events / Termine.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================
// LIST EVENTS (for members)
// ============================
if ($method === 'GET' && $action === 'list') {
    $user = requireAuth();
    $db = getDB();

    $showPast = ($_GET['past'] ?? '0') === '1';

    if ($showPast) {
        $stmt = $db->query("SELECT e.*, u.display_name AS creator_name FROM events e LEFT JOIN users u ON e.created_by = u.id ORDER BY e.event_date DESC, e.event_time DESC");
    } else {
        $visibilityFilter = ($user['role'] === 'admin') ? '' : " AND e.visibility IN ('public','internal')";
        $stmt = $db->prepare("SELECT e.*, u.display_name AS creator_name FROM events e LEFT JOIN users u ON e.created_by = u.id WHERE e.event_date >= :today{$visibilityFilter} ORDER BY e.event_date ASC, e.event_time ASC");
        $stmt->execute([':today' => date('Y-m-d')]);
    }

    $events = $stmt->fetchAll();
    jsonResponse(['events' => $events]);
}

// ============================
// PUBLIC EVENTS LIST (no auth)
// ============================
if ($method === 'GET' && $action === 'public_list') {
    $db = getDB();
    $limit = min(30, max(1, intval($_GET['limit'] ?? 10)));
    $stmt = $db->prepare("SELECT id, title, description, event_date, event_time, location, visibility, show_on_home
        FROM events
        WHERE visibility = 'public' AND event_date >= :today
        ORDER BY event_date ASC, event_time ASC
        LIMIT :limit");
    $stmt->bindValue(':today', date('Y-m-d'));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    jsonResponse(['events' => $stmt->fetchAll()]);
}

// ============================
// HOMEPAGE EVENT TEASER LIST (no auth)
// ============================
if ($method === 'GET' && $action === 'home_list') {
    $db = getDB();
    $limit = min(12, max(1, intval($_GET['limit'] ?? 3)));
    $stmt = $db->prepare("SELECT id, title, description, event_date, event_time, location
        FROM events
        WHERE visibility = 'public' AND show_on_home = 1 AND event_date >= :today
        ORDER BY event_date ASC, event_time ASC
        LIMIT :limit");
    $stmt->bindValue(':today', date('Y-m-d'));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    jsonResponse(['events' => $stmt->fetchAll()]);
}

// ============================
// GET SINGLE EVENT
// ============================
if ($method === 'GET' && $action === 'detail') {
    $user = requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT e.*, u.display_name AS creator_name FROM events e LEFT JOIN users u ON e.created_by = u.id WHERE e.id = :id");
    $stmt->execute([':id' => $id]);
    $event = $stmt->fetch();

    if (!$event) jsonResponse(['error' => 'Termin nicht gefunden'], 404);
    jsonResponse(['event' => $event]);
}

// ============================
// CREATE EVENT (admin only)
// ============================
if ($method === 'POST' && $action === 'create') {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $visibility = trim($_POST['visibility'] ?? 'public');
    $showOnHome = (int)($_POST['show_on_home'] ?? 1) ? 1 : 0;

    if (!in_array($visibility, ['public', 'internal'], true)) {
        $visibility = 'public';
    }

    if (empty($title) || empty($eventDate)) {
        jsonResponse(['error' => 'Titel und Datum erforderlich'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO events (title, description, event_date, event_time, location, visibility, show_on_home, created_by) VALUES (:t, :d, :date, :time, :loc, :vis, :home, :uid)");
    $stmt->execute([
        ':t' => $title,
        ':d' => $description ?: null,
        ':date' => $eventDate,
        ':time' => $eventTime ?: null,
        ':loc' => $location ?: null,
        ':vis' => $visibility,
        ':home' => $showOnHome,
        ':uid' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'Termin erstellt.', 'id' => $db->lastInsertId()], 201);
}

// ============================
// UPDATE EVENT (admin only)
// ============================
if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    if ($method === 'PUT') {
        parse_str(file_get_contents('php://input'), $data);
    } else {
        $data = $_POST;
    }

    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $eventDate = trim($data['event_date'] ?? '');
    $eventTime = trim($data['event_time'] ?? '');
    $location = trim($data['location'] ?? '');
    $visibility = trim($data['visibility'] ?? 'public');
    $showOnHome = (int)($data['show_on_home'] ?? 1) ? 1 : 0;

    if (!in_array($visibility, ['public', 'internal'], true)) {
        $visibility = 'public';
    }

    if (empty($title) || empty($eventDate)) {
        jsonResponse(['error' => 'Titel und Datum erforderlich'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE events SET title = :t, description = :d, event_date = :date, event_time = :time, location = :loc, visibility = :vis, show_on_home = :home, updated_at = datetime('now') WHERE id = :id");
    $stmt->execute([
        ':t' => $title,
        ':d' => $description ?: null,
        ':date' => $eventDate,
        ':time' => $eventTime ?: null,
        ':loc' => $location ?: null,
        ':vis' => $visibility,
        ':home' => $showOnHome,
        ':id' => $id,
    ]);

    jsonResponse(['success' => true, 'message' => 'Termin aktualisiert.']);
}

// ============================
// DELETE EVENT (admin only)
// ============================
if ($method === 'DELETE') {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

    $db = getDB();
    $db->prepare("DELETE FROM events WHERE id = :id")->execute([':id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Termin gelöscht.']);
}

jsonResponse(['error' => 'Ungültige Anfrage'], 400);
