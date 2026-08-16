<?php
/**
 * WKC – Goals / Ziele API
 * CRUD operations for goal topics and goal items.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

// ============================
// Public Routes (GET)
// ============================
if ($method === 'GET') {
    $db = getDB();
    $action = $_GET['action'] ?? 'list';

    // List all topics with their items (public)
    if ($action === 'list') {
        $topics = $db->query("SELECT * FROM goal_topics ORDER BY sort_order ASC")->fetchAll();

        foreach ($topics as &$topic) {
            $stmt = $db->prepare("SELECT * FROM goal_items WHERE topic_id = :tid ORDER BY sort_order ASC");
            $stmt->execute([':tid' => $topic['id']]);
            $topic['items'] = $stmt->fetchAll();
        }
        unset($topic);

        jsonResponse(['topics' => $topics]);
    }

    // Single topic detail (public)
    if ($action === 'detail') {
        $id = intval($_GET['id'] ?? 0);
        $slug = $_GET['slug'] ?? '';

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM goal_topics WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } elseif (!empty($slug)) {
            $stmt = $db->prepare("SELECT * FROM goal_topics WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
        } else {
            jsonResponse(['error' => 'ID oder Slug erforderlich'], 400);
        }

        $topic = $stmt->fetch();
        if (!$topic) {
            jsonResponse(['error' => 'Thema nicht gefunden'], 404);
        }

        $itemStmt = $db->prepare("SELECT * FROM goal_items WHERE topic_id = :tid ORDER BY sort_order ASC");
        $itemStmt->execute([':tid' => $topic['id']]);
        $topic['items'] = $itemStmt->fetchAll();

        jsonResponse(['topic' => $topic]);
    }

    // Admin list (requires auth)
    if ($action === 'admin_list') {
        $user = requireAuth();
        if (!in_array($user['role'], ['admin', 'editor'])) {
            jsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $search = trim($_GET['search'] ?? '');

        $query = "SELECT * FROM goal_topics";
        $params = [];

        if (!empty($search)) {
            $query .= " WHERE name LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        $query .= " ORDER BY sort_order ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $topics = $stmt->fetchAll();

        foreach ($topics as &$topic) {
            $itemQuery = "SELECT * FROM goal_items WHERE topic_id = :tid";
            $itemParams = [':tid' => $topic['id']];

            if (!empty($search)) {
                $itemQuery .= " AND (title LIKE :search OR description LIKE :search2)";
                $itemParams[':search'] = '%' . $search . '%';
                $itemParams[':search2'] = '%' . $search . '%';
            }
            $itemQuery .= " ORDER BY sort_order ASC";

            $itemStmt = $db->prepare($itemQuery);
            $itemStmt->execute($itemParams);
            $topic['items'] = $itemStmt->fetchAll();
        }
        unset($topic);

        $totalTopics = $db->query("SELECT COUNT(*) FROM goal_topics")->fetchColumn();
        $totalItems = $db->query("SELECT COUNT(*) FROM goal_items")->fetchColumn();

        jsonResponse([
            'topics' => $topics,
            'counts' => [
                'topics' => (int) $totalTopics,
                'items' => (int) $totalItems,
            ],
        ]);
    }

    jsonResponse(['error' => 'Unbekannte Aktion'], 400);
}

// ============================
// Protected Routes (POST/DELETE) – require auth
// ============================
if ($method === 'POST') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'])) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    $db = getDB();
    $action = $_GET['action'] ?? '';

    // ============================
    // Create / Update Topic
    // ============================
    if ($action === 'save_topic') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#7c3aed');
        $icon = trim($_POST['icon'] ?? 'flag');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if (empty($name)) {
            jsonResponse(['error' => 'Name ist erforderlich'], 400);
        }

        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)), '-'));
        if (empty($slug)) $slug = 'thema-' . time();

        // Handle file upload for image
        if (!empty($_FILES['image_file']['tmp_name'])) {
            $uploadDir = UPLOAD_DIR . 'goals/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed)) {
                jsonResponse(['error' => 'Ungültiges Bildformat'], 400);
            }

            $fileName = $slug . '-' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                $image = 'uploads/goals/' . $fileName;
            }
        }

        if ($id > 0) {
            // Update
            $stmt = $db->prepare("UPDATE goal_topics SET name = :name, slug = :slug, color = :color, icon = :icon, image = :image, description = :description, updated_at = datetime('now') WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':color' => $color,
                ':icon' => $icon,
                ':image' => $image,
                ':description' => $description,
                ':id' => $id,
            ]);
            jsonResponse(['success' => true, 'id' => $id, 'message' => 'Thema aktualisiert']);
        } else {
            // Get next sort order
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM goal_topics")->fetchColumn();

            $stmt = $db->prepare("INSERT INTO goal_topics (name, slug, color, icon, image, description, sort_order) VALUES (:name, :slug, :color, :icon, :image, :description, :sort)");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':color' => $color,
                ':icon' => $icon,
                ':image' => $image,
                ':description' => $description,
                ':sort' => $maxOrder + 1,
            ]);
            jsonResponse(['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Thema erstellt']);
        }
    }

    // ============================
    // Create / Update Item
    // ============================
    if ($action === 'save_item') {
        $id = intval($_POST['id'] ?? 0);
        $topicId = intval($_POST['topic_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'check_circle');
        $status = trim($_POST['status'] ?? '');

        // Validate status value
        $allowedStatuses = ['', 'erreicht', 'teils_erreicht'];
        if (!in_array($status, $allowedStatuses)) {
            $status = '';
        }

        if (empty($title)) {
            jsonResponse(['error' => 'Titel ist erforderlich'], 400);
        }
        if ($topicId <= 0) {
            jsonResponse(['error' => 'Thema ist erforderlich'], 400);
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE goal_items SET topic_id = :tid, title = :title, description = :desc, icon = :icon, status = :status, updated_at = datetime('now') WHERE id = :id");
            $stmt->execute([
                ':tid' => $topicId,
                ':title' => $title,
                ':desc' => $description,
                ':icon' => $icon,
                ':status' => $status,
                ':id' => $id,
            ]);
            jsonResponse(['success' => true, 'id' => $id, 'message' => 'Ziel aktualisiert']);
        } else {
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM goal_items WHERE topic_id = $topicId")->fetchColumn();

            $stmt = $db->prepare("INSERT INTO goal_items (topic_id, title, description, icon, status, sort_order) VALUES (:tid, :title, :desc, :icon, :status, :sort)");
            $stmt->execute([
                ':tid' => $topicId,
                ':title' => $title,
                ':desc' => $description,
                ':icon' => $icon,
                ':status' => $status,
                ':sort' => $maxOrder + 1,
            ]);
            jsonResponse(['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Ziel erstellt']);
        }
    }

    // ============================
    // Reorder Topics
    // ============================
    if ($action === 'reorder_topics') {
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];

        if (empty($ids)) {
            jsonResponse(['error' => 'IDs erforderlich'], 400);
        }

        $stmt = $db->prepare("UPDATE goal_topics SET sort_order = :order WHERE id = :id");
        foreach ($ids as $index => $id) {
            $stmt->execute([':order' => $index + 1, ':id' => intval($id)]);
        }
        jsonResponse(['success' => true]);
    }

    // ============================
    // Reorder Items within a Topic
    // ============================
    if ($action === 'reorder_items') {
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];

        if (empty($ids)) {
            jsonResponse(['error' => 'IDs erforderlich'], 400);
        }

        $stmt = $db->prepare("UPDATE goal_items SET sort_order = :order WHERE id = :id");
        foreach ($ids as $index => $id) {
            $stmt->execute([':order' => $index + 1, ':id' => intval($id)]);
        }
        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unbekannte Aktion'], 400);
}

// ============================
// DELETE
// ============================
if ($method === 'DELETE') {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'])) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }

    $db = getDB();
    $type = $_GET['type'] ?? '';
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['error' => 'ID erforderlich'], 400);
    }

    if ($type === 'topic') {
        // Delete topic and all its items (cascade)
        $db->prepare("DELETE FROM goal_items WHERE topic_id = :id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM goal_topics WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Thema gelöscht']);
    }

    if ($type === 'item') {
        $db->prepare("DELETE FROM goal_items WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Ziel gelöscht']);
    }

    jsonResponse(['error' => 'Typ (topic/item) erforderlich'], 400);
}

jsonResponse(['error' => 'Methode nicht erlaubt'], 405);

