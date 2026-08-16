<?php
/**
 * WKC – Articles API
 * CRUD operations for articles/blog posts.
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

    // List published articles (public)
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(50, max(1, intval($_GET['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $where = "WHERE a.status = 'published'";
        $params = [];

        $stmt = $db->prepare("
            SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                   a.tags, a.published_at, a.created_at,
                   u.display_name AS author_name, u.profile_image AS author_image, u.username AS author_slug
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            {$where}
            ORDER BY a.published_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->execute();
        $articles = $stmt->fetchAll();

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) FROM articles a {$where}");
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        jsonResponse([
            'articles' => $articles,
            'total' => (int) $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ]);
    }

    // Single article by slug (public)
    if ($action === 'detail') {
        $slug = $_GET['slug'] ?? '';
        if (empty($slug)) {
            jsonResponse(['error' => 'Slug erforderlich'], 400);
        }

        $stmt = $db->prepare("
            SELECT a.*, u.display_name AS author_name, u.profile_image AS author_image, u.position AS author_position, u.username AS author_slug
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.slug = :slug AND a.status = 'published'
        ");
        $stmt->execute([':slug' => $slug]);
        $article = $stmt->fetch();

        if (!$article) {
            jsonResponse(['error' => 'Artikel nicht gefunden'], 404);
        }

        $article['content'] = sanitizeArticleHtml((string) $article['content']);
        jsonResponse(['article' => $article]);
    }

    // ============================
    // Admin: List ALL articles (auth required)
    // ============================
    if ($action === 'admin_list') {
        $user = requireAuth();
        if (!in_array($user['role'], ['admin', 'editor'], true)) {
            jsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? '';

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($statusFilter) && in_array($statusFilter, ['draft', 'published', 'archived'])) {
            $where .= " AND a.status = :status";
            $params[':status'] = $statusFilter;
        }

        if (!empty($search)) {
            $where .= " AND (a.title LIKE :search OR a.tags LIKE :search2)";
            $params[':search'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
        }

        $stmt = $db->prepare("
            SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                   a.status, a.tags, a.published_at, a.created_at, a.updated_at, a.author_id,
                   u.display_name AS author_name, u.profile_image AS author_image
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            {$where}
            ORDER BY a.updated_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->execute();
        $articles = $stmt->fetchAll();

        // Total counts
        $countStmt = $db->prepare("SELECT COUNT(*) FROM articles a {$where}");
        foreach ($params as $key => $val) {
            if ($key === ':limit' || $key === ':offset') continue;
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $countStmt->bindValue($key, $val, $type);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        // Status counts for filter badges
        $counts = $db->query("
            SELECT status, COUNT(*) as cnt FROM articles GROUP BY status
        ")->fetchAll();
        $statusCounts = ['all' => 0, 'draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($counts as $c) {
            $statusCounts[$c['status']] = (int) $c['cnt'];
            $statusCounts['all'] += (int) $c['cnt'];
        }

        jsonResponse([
            'articles' => $articles,
            'total' => (int) $total,
            'page' => $page,
            'pages' => max(1, ceil($total / $limit)),
            'counts' => $statusCounts,
        ]);
    }

    // ============================
    // Admin: Single article by ID (auth required)
    // ============================
    if ($action === 'admin_detail') {
        $user = requireAuth();
        if (!in_array($user['role'], ['admin', 'editor'], true)) {
            jsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $id = intval($_GET['id'] ?? 0);

        if (!$id) {
            jsonResponse(['error' => 'Artikel-ID erforderlich'], 400);
        }

        $stmt = $db->prepare("
            SELECT a.*, u.display_name AS author_name, u.profile_image AS author_image, u.position AS author_position
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $article = $stmt->fetch();

        if (!$article) {
            jsonResponse(['error' => 'Artikel nicht gefunden'], 404);
        }

        $article['content'] = sanitizeArticleHtml((string) $article['content']);
        jsonResponse(['article' => $article]);
    }
}

// ============================
// Protected Routes (POST, PUT, DELETE)
// ============================
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'editor'], true)) {
        jsonResponse(['error' => 'Keine Berechtigung'], 403);
    }
    $db = getDB();

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'upload_media') {
        if (empty($_FILES['image'])) {
            jsonResponse(['error' => 'Bild erforderlich'], 400);
        }
        $path = handleImageUpload($_FILES['image']);
        jsonResponse(['success' => true, 'path' => $path], 201);
    }

    // CREATE or UPDATE Article (POST)
    if ($method === 'POST') {
        $id = intval($_POST['id'] ?? 0);

        $title = trim($_POST['title'] ?? '');
        $content = sanitizeArticleHtml((string) ($_POST['content'] ?? ''));
        $excerpt = trim($_POST['excerpt'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $tags = trim($_POST['tags'] ?? '');
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');
        $canonicalUrl = trim($_POST['canonical_url'] ?? '');
        $noindex = !empty($_POST['noindex']) ? 1 : 0;
        $nofollow = !empty($_POST['nofollow']) ? 1 : 0;
        $customSlug = trim($_POST['slug'] ?? '');
        $authorId = intval($_POST['author_id'] ?? 0) ?: $user['id'];

        if (empty($title)) {
            jsonResponse(['error' => 'Titel ist erforderlich'], 400);
        }
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            jsonResponse(['error' => 'Ungültiger Artikelstatus'], 400);
        }

        // Handle image upload
        $featuredImage = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $featuredImage = handleImageUpload($_FILES['featured_image']);
        }

        // Custom or auto publish date
        $customDate = trim($_POST['published_at'] ?? '');

        if ($id) {
            // ============================
            // UPDATE existing article
            // ============================
            $fields = [];
            $params = [':id' => $id];

            $allowedFields = ['title', 'excerpt', 'content', 'status', 'tags', 'meta_title', 'meta_description', 'canonical_url', 'noindex', 'nofollow', 'author_id'];
            $postValues = [
                'title' => $title, 'excerpt' => $excerpt, 'content' => $content,
                'status' => $status, 'tags' => $tags,
                'meta_title' => $metaTitle, 'meta_description' => $metaDesc,
                'canonical_url' => $canonicalUrl,
                'noindex' => $noindex,
                'nofollow' => $nofollow,
                'author_id' => $authorId,
            ];
            foreach ($allowedFields as $field) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $postValues[$field];
            }

            // Handle slug update
            if (!empty($customSlug)) {
                $newSlug = generateSlug($customSlug, $id);
                $fields[] = "slug = :slug";
                $params[':slug'] = $newSlug;
            }

            // Handle image: update only when a new file was uploaded
            if ($featuredImage) {
                $fields[] = "featured_image = :image";
                $params[':image'] = $featuredImage;
            }

            // Publish date
            if ($status === 'published') {
                if (!empty($customDate)) {
                    $fields[] = "published_at = :published_at";
                    $params[':published_at'] = $customDate . ' 00:00:00';
                } else {
                    $fields[] = "published_at = COALESCE(published_at, datetime('now'))";
                }
            } elseif (!empty($customDate)) {
                $fields[] = "published_at = :published_at";
                $params[':published_at'] = $customDate . ' 00:00:00';
            }

            $fields[] = "updated_at = datetime('now')";

            $sql = "UPDATE articles SET " . implode(', ', $fields) . " WHERE id = :id";
            $db->prepare($sql)->execute($params);

            jsonResponse(['success' => true, 'message' => 'Artikel aktualisiert.']);
        } else {
            // ============================
            // CREATE new article
            // ============================
            $slug = !empty($customSlug) ? generateSlug($customSlug) : generateSlug($title);

            if (!empty($customDate)) {
                $publishedAt = $customDate . ' 00:00:00';
            } else {
                $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            }

            $stmt = $db->prepare("
                INSERT INTO articles (title, slug, excerpt, content, featured_image, author_id, status, tags, meta_title, meta_description, canonical_url, noindex, nofollow, published_at)
                VALUES (:title, :slug, :excerpt, :content, :image, :author, :status, :tags, :meta_title, :meta_desc, :canonical_url, :noindex, :nofollow, :published)
            ");
            $stmt->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':excerpt' => $excerpt,
                ':content' => $content,
                ':image' => $featuredImage,
                ':author' => $authorId,
                ':status' => $status,
                ':tags' => $tags,
                ':meta_title' => $metaTitle,
                ':meta_desc' => $metaDesc,
                ':canonical_url' => $canonicalUrl ?: null,
                ':noindex' => $noindex,
                ':nofollow' => $nofollow,
                ':published' => $publishedAt,
            ]);

            jsonResponse([
                'success' => true,
                'id' => (int) $db->lastInsertId(),
                'slug' => $slug,
                'message' => 'Artikel erfolgreich erstellt.'
            ], 201);
        }
    }

    // DELETE Article
    if ($method === 'DELETE') {
        parse_str(file_get_contents('php://input'), $input);
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);

        if (!$id) {
            jsonResponse(['error' => 'Artikel-ID erforderlich'], 400);
        }

        $db->prepare("DELETE FROM articles WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Artikel gelöscht.']);
    }
}

// ============================
// Helper Functions
// ============================
function generateSlug(string $title, ?int $excludeId = null): string {
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[äÄ]/', 'ae', $slug);
    $slug = preg_replace('/[öÖ]/', 'oe', $slug);
    $slug = preg_replace('/[üÜ]/', 'ue', $slug);
    $slug = preg_replace('/ß/', 'ss', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    // Check uniqueness
    $db = getDB();
    $original = $slug;
    $counter = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM articles WHERE slug = :slug";
        $params = [':slug' => $slug];
        if ($excludeId) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() == 0) break;
        $slug = $original . '-' . $counter++;
    }

    return $slug;
}

function handleImageUpload(array $file): ?string {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        jsonResponse(['error' => 'Ungültiger Upload'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!isset($allowedTypes[$mime])) {
        jsonResponse(['error' => 'Ungültiges Bildformat (JPG, PNG, WebP, GIF erlaubt)'], 400);
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonResponse(['error' => 'Bild zu groß (max. 5 MB)'], 400);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $path = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        jsonResponse(['error' => 'Upload fehlgeschlagen'], 500);
    }

    return UPLOAD_URL . $filename;
}

function sanitizeArticleHtml(string $html): string {
    if (!class_exists('DOMDocument')) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $allowedTags = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'b' => [],
        'em' => [], 'i' => [], 'u' => [], 'blockquote' => [], 'hr' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
    ];

    $previousErrors = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="article-content">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);

    $container = $doc->getElementById('article-content');
    if (!$container) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            $sanitizeNode($child);
        }

        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if (!isset($allowedTags[$tag])) {
            $parent = $node->parentNode;
            if ($parent) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
            return;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowedTags[$tag], true)) {
                $node->removeAttributeNode($attribute);
                continue;
            }

            if (($name === 'href' || $name === 'src') && sanitizeArticleUrl($attribute->value, $name === 'src') === null) {
                $node->removeAttributeNode($attribute);
                continue;
            }

            if (($name === 'width' || $name === 'height') && !preg_match('/^\d{1,4}$/', $attribute->value)) {
                $node->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'target' && $attribute->value !== '_blank') {
                $node->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img' && !$node->hasAttribute('src')) {
            $node->parentNode?->removeChild($node);
        }
    };

    foreach (iterator_to_array($container->childNodes) as $child) {
        $sanitizeNode($child);
    }

    $result = '';
    foreach ($container->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }
    return $result;
}

function sanitizeArticleUrl(string $url, bool $isImage): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }
    if ($isImage) {
        return preg_match('~^/(?:uploads|src)/~', $url) ? $url : null;
    }
    if (preg_match('~^(?:mailto:|tel:|#)~i', $url) || preg_match('~^/(?!/)~', $url)) {
        return $url;
    }
    return null;
}

