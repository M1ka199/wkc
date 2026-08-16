<?php
/**
 * WKC â€“ Database & App Configuration
 * 
 * Verwendet SQLite als lokale Datenbank â€“ kein externer DB-Server nÃ¶tig.
 */

// Suppress warnings so they never corrupt JSON responses
error_reporting(E_ERROR | E_PARSE);
ini_set('default_charset', 'UTF-8');

// ============================
// Database Configuration (SQLite)
// ============================
define('DB_PATH', __DIR__ . '/data/wkc.sqlite');
define('LEGACY_DB_PATH', __DIR__ . '/data/' . 'zukunft' . '_wulften.sqlite');

// ============================
// App Settings
// ============================
define('SITE_NAME', 'WKC');
define('SITE_URL', envConfig('SITE_URL', 'https://zukunft-wulften.de'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// ============================
// SMTP Configuration (environment only)
// ============================
define('SMTP_HOST', envConfig('SMTP_HOST'));
define('SMTP_PORT', (int) envConfig('SMTP_PORT', '0'));
define('SMTP_USER', envConfig('SMTP_USER'));
define('SMTP_PASS', envConfig('SMTP_PASS'));
define('SMTP_FROM', envConfig('SMTP_FROM'));
define('SMTP_FROM_NAME', envConfig('SMTP_FROM_NAME', SITE_NAME));
define('SMTP_SECURE', strtolower(envConfig('SMTP_SECURE', 'tls')));
define('CONTACT_RECIPIENT', envConfig('CONTACT_RECIPIENT'));

// ============================
// Session & Security
// ============================
define('SESSION_NAME', 'wkc_session');
define('AUTH_SALT', 'CHANGE_THIS_TO_A_RANDOM_STRING');  // PLATZHALTER
define('AUTH_SESSION_TIMEOUT', 8 * 60 * 60);

function envConfig(string $name, string $default = ''): string {
    $value = getenv($name);
    return is_string($value) && $value !== '' ? trim($value) : $default;
}

$smtpSettings = getAppSetting('smtp', null);
if (!is_array($smtpSettings)) {
    setAppSetting('smtp', [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT > 0 ? SMTP_PORT : 587,
        'user' => SMTP_USER,
        'pass' => SMTP_PASS,
        'from' => SMTP_FROM,
        'from_name' => SMTP_FROM_NAME,
        'contact_recipient' => CONTACT_RECIPIENT,
        'secure' => in_array(SMTP_SECURE, ['tls', 'ssl'], true) ? SMTP_SECURE : 'tls',
    ]);
} elseif (!array_key_exists('contact_recipient', $smtpSettings)) {
    $smtpSettings['contact_recipient'] = CONTACT_RECIPIENT;
    setAppSetting('smtp', $smtpSettings);
}

function resolveDatabasePath(): string {
    // Keep existing deployments on their current database file. Renaming a live
    // SQLite database can lose writes from concurrent requests using the old path.
    if (file_exists(LEGACY_DB_PATH)) {
        return LEGACY_DB_PATH;
    }

    return DB_PATH;
}

function normalizeUtf8Mojibake(string $value): string {
    if ($value === '') {
        return $value;
    }

    $decode = static function (string $escaped): string {
        $decoded = json_decode('"' . $escaped . '"', true);
        return is_string($decoded) ? $decoded : '';
    };

    $map = [
        '\u00c3\u201e' => 'Ã„', '\u00c3\u2013' => 'Ã–', '\u00c3\u0153' => 'Ãœ',
        '\u00c3\u00a4' => 'Ã¤', '\u00c3\u00b6' => 'Ã¶', '\u00c3\u00bc' => 'Ã¼', '\u00c3\u0178' => 'ÃŸ',
        '\u00c3\u00a1' => 'Ã¡', '\u00c3\u0020' => 'Ã ', '\u00c3\u00a2' => 'Ã¢', '\u00c3\u00a3' => 'Ã£', '\u00c3\u00a9' => 'Ã©',
        '\u00c3\u00a8' => 'Ã¨', '\u00c3\u00aa' => 'Ãª', '\u00c3\u00ad' => 'Ã­', '\u00c3\u00ac' => 'Ã¬',
        '\u00c3\u00ae' => 'Ã®', '\u00c3\u00b3' => 'Ã³', '\u00c3\u00b2' => 'Ã²', '\u00c3\u00b4' => 'Ã´',
        '\u00c3\u00ba' => 'Ãº', '\u00c3\u00b9' => 'Ã¹', '\u00c3\u00bb' => 'Ã»', '\u00c3\u00b1' => 'Ã±',
        '\u00c3\u00a7' => 'Ã§',
        '\u00e2\u20ac\u201c' => 'â€“', '\u00e2\u20ac\u201d' => 'â€”', '\u00e2\u20ac\u017e' => 'â€ž',
        '\u00e2\u20ac\u0153' => 'â€œ', '\u00e2\u20ac\u009d' => 'â€', '\u00e2\u20ac\u02dc' => 'â€˜',
        '\u00e2\u20ac\u2122' => 'â€™', '\u00e2\u20ac\u00a6' => 'â€¦', '\u00e2\u20ac\u00a2' => 'â€¢',
        '\u00e2\u201a\u00ac' => 'â‚¬', '\u00e2\u201e\u00a2' => 'â„¢', '\u00c2\u00a9' => 'Â©',
        '\u00c2\u00ae' => 'Â®', '\u00c2\u00b0' => 'Â°',
    ];

    foreach ($map as $escaped => $replacement) {
        $value = str_replace($decode($escaped), $replacement, $value);
    }

    $c2 = $decode('\u00c2');
    if ($c2 !== '') {
        $value = str_replace($c2 . ' ', ' ', $value);
        $value = str_replace($c2, '', $value);
    }

    return $value;
}

function repairUtf8MojibakeTable(PDO $db, string $table, string $pk, array $columns): void {
    if (empty($columns)) {
        return;
    }

    $selectColumns = array_merge([$pk], $columns);
    $query = "SELECT " . implode(', ', $selectColumns) . " FROM {$table}";
    $rows = $db->query($query)->fetchAll();
    if (!$rows) {
        return;
    }

    foreach ($rows as $row) {
        $updates = [];
        $params = [':pk' => $row[$pk]];
        foreach ($columns as $column) {
            $current = $row[$column] ?? null;
            if (!is_string($current) || $current === '') {
                continue;
            }

            $normalized = normalizeUtf8Mojibake($current);
            if ($normalized === $current) {
                continue;
            }

            $updates[] = "{$column} = :{$column}";
            $params[":{$column}"] = $normalized;
        }

        if (!$updates) {
            continue;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $updates) . " WHERE {$pk} = :pk";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
}

function runUtf8DataRepair(PDO $db): void {
    $stateStmt = $db->prepare("SELECT value FROM app_settings WHERE key = 'utf8_repair_v1_done' LIMIT 1");
    $stateStmt->execute();
    $state = $stateStmt->fetchColumn();
    if ($state !== false) {
        return;
    }

    $db->beginTransaction();
    try {
        repairUtf8MojibakeTable($db, 'app_settings', 'key', ['value']);
        repairUtf8MojibakeTable($db, 'site_pages', 'id', ['title', 'path', 'meta_title', 'meta_description', 'canonical_url', 'og_image', 'content_html', 'blocks_json']);
        repairUtf8MojibakeTable($db, 'forms', 'id', ['title', 'slug', 'description', 'target_path', 'success_message', 'submit_label', 'email_recipients', 'email_subject', 'smtp_from_name']);
        repairUtf8MojibakeTable($db, 'form_fields', 'id', ['field_name', 'field_label', 'placeholder', 'help_text', 'options_json']);
        repairUtf8MojibakeTable($db, 'users', 'id', ['username', 'display_name', 'email', 'position', 'bio', 'focus_areas', 'personal_goals', 'quote', 'member_since', 'family_status', 'occupation', 'clubs']);
        repairUtf8MojibakeTable($db, 'articles', 'id', ['title', 'slug', 'excerpt', 'content', 'tags', 'meta_title', 'meta_description', 'canonical_url']);
        repairUtf8MojibakeTable($db, 'events', 'id', ['title', 'description', 'location']);
        repairUtf8MojibakeTable($db, 'documents', 'id', ['title', 'description', 'file_name']);
        repairUtf8MojibakeTable($db, 'galleries', 'id', ['title', 'slug', 'description']);
        repairUtf8MojibakeTable($db, 'gallery_images', 'id', ['caption']);
        repairUtf8MojibakeTable($db, 'goal_topics', 'id', ['name', 'slug', 'icon', 'image', 'description']);
        repairUtf8MojibakeTable($db, 'goal_items', 'id', ['title', 'description', 'icon', 'status']);
        repairUtf8MojibakeTable($db, 'contact_messages', 'id', ['name', 'email', 'subject', 'message', 'type']);

        $markStmt = $db->prepare("
            INSERT INTO app_settings (key, value, updated_at)
            VALUES ('utf8_repair_v1_done', '1', datetime('now'))
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')
        ");
        $markStmt->execute();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// ============================
// Database Connection (PDO / SQLite)
// ============================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dbPath = resolveDatabasePath();
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Enable WAL mode and foreign keys
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            // Auto-setup on first run or apply migrations
            setupDatabase($pdo);
        } catch (Throwable $e) {
            error_log('WKC database connection error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen.']));
        }
    }
    return $pdo;
}

// ============================
// JSON Response Helper
// ============================
function jsonResponse(array $data, int $status = 200): void {
    // Discard any buffered warnings/notices to keep output clean JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// Auth Check Helper
// ============================
function destroyAuthSession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function authenticatedUser(bool $respondOnExpiry = false): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (empty($_SESSION['login_time']) || time() - (int) $_SESSION['login_time'] > AUTH_SESSION_TIMEOUT) {
        destroyAuthSession();
        if ($respondOnExpiry) {
            jsonResponse(['error' => 'Sitzung abgelaufen', 'reason' => 'session_expired'], 401);
        }
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => $_SESSION['display_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'member',
    ];
}

function requireAuth(): array {
    $user = authenticatedUser(true);
    if ($user === null) {
        jsonResponse(['error' => 'Nicht autorisiert'], 401);
    }
    return $user;
}

// ============================
// Database Schema (SQLite)
// ============================
function setupDatabase(?PDO $db = null): void {
    if ($db === null) $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        role TEXT DEFAULT 'member' CHECK(role IN ('admin','editor','member')),
        is_board_member INTEGER DEFAULT 0,
        profile_image TEXT DEFAULT NULL,
        position TEXT DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        focus_areas TEXT DEFAULT NULL,
        personal_goals TEXT DEFAULT NULL,
        quote TEXT DEFAULT NULL,
        member_since TEXT DEFAULT NULL,
        is_active INTEGER DEFAULT 1,
        board_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    // Migration: add missing columns
    $cols = $db->query("PRAGMA table_info(users)")->fetchAll();
    $colNames = array_column($cols, 'name');
    if (!in_array('is_board_member', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN is_board_member INTEGER DEFAULT 0");
    }
    if (!in_array('age', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN age INTEGER DEFAULT NULL");
    }
    if (!in_array('family_status', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN family_status TEXT DEFAULT NULL");
    }
    if (!in_array('children', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN children TEXT DEFAULT NULL");
    }
    if (!in_array('occupation', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN occupation TEXT DEFAULT NULL");
    }
    if (!in_array('clubs', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN clubs TEXT DEFAULT NULL");
    }
    if (!in_array('grandchildren', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN grandchildren INTEGER DEFAULT NULL");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        excerpt TEXT DEFAULT NULL,
        content TEXT NOT NULL DEFAULT '',
        featured_image TEXT DEFAULT NULL,
        author_id INTEGER NOT NULL,
        status TEXT DEFAULT 'draft' CHECK(status IN ('draft','published','archived')),
        is_funding INTEGER DEFAULT 0,
        tags TEXT DEFAULT NULL,
        meta_title TEXT DEFAULT NULL,
        meta_description TEXT DEFAULT NULL,
        canonical_url TEXT DEFAULT NULL,
        noindex INTEGER DEFAULT 0,
        nofollow INTEGER DEFAULT 0,
        published_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $articleCols = $db->query("PRAGMA table_info(articles)")->fetchAll();
    $articleColNames = array_column($articleCols, 'name');
    if (!in_array('canonical_url', $articleColNames, true)) {
        $db->exec("ALTER TABLE articles ADD COLUMN canonical_url TEXT DEFAULT NULL");
    }
    if (!in_array('noindex', $articleColNames, true)) {
        $db->exec("ALTER TABLE articles ADD COLUMN noindex INTEGER DEFAULT 0");
    }
    if (!in_array('nofollow', $articleColNames, true)) {
        $db->exec("ALTER TABLE articles ADD COLUMN nofollow INTEGER DEFAULT 0");
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_articles_published ON articles(published_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_articles_funding ON articles(is_funding)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_articles_slug ON articles(slug)");

    $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT DEFAULT NULL,
        email TEXT DEFAULT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        type TEXT DEFAULT 'contact' CHECK(type IN ('contact','membership','message')),
        is_anonymous INTEGER DEFAULT 0,
        ip_address TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        is_read INTEGER DEFAULT 0
    )");

    // Migration: add type column to contact_messages if missing
    $cmCols = $db->query("PRAGMA table_info(contact_messages)")->fetchAll();
    $cmColNames = array_column($cmCols, 'name');
    if (!in_array('type', $cmColNames)) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN type TEXT DEFAULT 'contact'");
    }

    // ============================
    // Password / Invitation Tokens
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS password_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL CHECK(type IN ('reset','invitation')),
        expires_at TEXT NOT NULL,
        used_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_tokens_token ON password_tokens(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_tokens_user ON password_tokens(user_id)");

    // Migration: add email and invitation_sent to users if missing
    if (!in_array('email', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN email TEXT DEFAULT NULL");
    }
    if (!in_array('invitation_sent', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN invitation_sent INTEGER DEFAULT 0");
    }
    if (!in_array('must_set_password', $colNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN must_set_password INTEGER DEFAULT 0");
    }

    // ============================
    // Events / Termine
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        event_date TEXT NOT NULL,
        event_time TEXT DEFAULT NULL,
        location TEXT DEFAULT NULL,
        visibility TEXT DEFAULT 'public' CHECK(visibility IN ('public','internal')),
        show_on_home INTEGER DEFAULT 1,
        created_by INTEGER NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date)");

    // Migration: add visibility/show_on_home to events
    $eventCols = $db->query("PRAGMA table_info(events)")->fetchAll();
    $eventColNames = array_column($eventCols, 'name');
    if (!in_array('visibility', $eventColNames)) {
        $db->exec("ALTER TABLE events ADD COLUMN visibility TEXT DEFAULT 'public'");
    }
    if (!in_array('show_on_home', $eventColNames)) {
        $db->exec("ALTER TABLE events ADD COLUMN show_on_home INTEGER DEFAULT 1");
    }

    // ============================
    // Documents / Dokumente
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        file_path TEXT NOT NULL,
        file_name TEXT NOT NULL,
        file_size INTEGER DEFAULT 0,
        uploaded_by INTEGER NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // ============================
    // Document Tags
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS document_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT '#6b7280',
        created_at TEXT DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS document_tag_map (
        document_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (document_id, tag_id),
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE
    )");

    // ============================
    // Goals / Ziele â€“ Topics & Sub-goals
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS goal_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT '#7c3aed',
        icon TEXT DEFAULT 'flag',
        image TEXT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS goal_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        topic_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        icon TEXT DEFAULT 'check_circle',
        status TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (topic_id) REFERENCES goal_topics(id) ON DELETE CASCADE
    )");

    // Add status column if missing (migration for existing DBs)
    $cols = $db->query("PRAGMA table_info(goal_items)")->fetchAll();
    $colNames = array_column($cols, 'name');
    if (!in_array('status', $colNames)) {
        $db->exec("ALTER TABLE goal_items ADD COLUMN status TEXT DEFAULT ''");
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_goal_items_topic ON goal_items(topic_id)");

    // ============================
    // App Settings (global CMS configuration)
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    // ============================
    // CMS Pages (hierarchical routing)
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS site_pages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        path TEXT NOT NULL UNIQUE,
        meta_title TEXT DEFAULT NULL,
        meta_description TEXT DEFAULT NULL,
        canonical_url TEXT DEFAULT NULL,
        noindex INTEGER DEFAULT 0,
        nofollow INTEGER DEFAULT 0,
        og_image TEXT DEFAULT NULL,
        content_html TEXT DEFAULT '',
        blocks_json TEXT DEFAULT '{}',
        status TEXT DEFAULT 'published' CHECK(status IN ('draft','published','archived')),
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_site_pages_path ON site_pages(path)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_site_pages_status ON site_pages(status)");

    $sitePageCols = $db->query("PRAGMA table_info(site_pages)")->fetchAll();
    $sitePageColNames = array_column($sitePageCols, 'name');
    if (!in_array('canonical_url', $sitePageColNames, true)) {
        $db->exec("ALTER TABLE site_pages ADD COLUMN canonical_url TEXT DEFAULT NULL");
    }
    if (!in_array('noindex', $sitePageColNames, true)) {
        $db->exec("ALTER TABLE site_pages ADD COLUMN noindex INTEGER DEFAULT 0");
    }
    if (!in_array('nofollow', $sitePageColNames, true)) {
        $db->exec("ALTER TABLE site_pages ADD COLUMN nofollow INTEGER DEFAULT 0");
    }
    if (!in_array('og_image', $sitePageColNames, true)) {
        $db->exec("ALTER TABLE site_pages ADD COLUMN og_image TEXT DEFAULT NULL");
    }

    // ============================
    // Dynamic Forms
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        target_path TEXT DEFAULT NULL,
        success_message TEXT DEFAULT 'Vielen Dank! Ihre Anfrage wurde erfolgreich Ã¼bermittelt.',
        submit_label TEXT DEFAULT 'Formular absenden',
        is_active INTEGER DEFAULT 1,
        email_recipients TEXT NOT NULL,
        email_subject TEXT NOT NULL,
        smtp_host TEXT DEFAULT NULL,
        smtp_port INTEGER DEFAULT NULL,
        smtp_secure TEXT DEFAULT NULL,
        smtp_user TEXT DEFAULT NULL,
        smtp_pass TEXT DEFAULT NULL,
        smtp_from TEXT DEFAULT NULL,
        smtp_from_name TEXT DEFAULT NULL,
        created_by INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS form_fields (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL,
        field_type TEXT NOT NULL CHECK(field_type IN ('text','email','tel','textarea','select','checkbox','file','signature','heading','divider')),
        field_name TEXT DEFAULT NULL,
        field_label TEXT DEFAULT NULL,
        placeholder TEXT DEFAULT NULL,
        help_text TEXT DEFAULT NULL,
        options_json TEXT DEFAULT '{}',
        is_required INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
    )");

    // Migration for older databases where form_fields CHECK constraint has no "select"
    $formFieldsSqlStmt = $db->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'form_fields' LIMIT 1");
    $formFieldsSqlStmt->execute();
    $formFieldsCreateSql = (string) ($formFieldsSqlStmt->fetchColumn() ?: '');
    if ($formFieldsCreateSql !== '' && stripos($formFieldsCreateSql, "'select'") === false) {
        $db->exec("DROP TABLE IF EXISTS form_fields_new");
        $db->exec("CREATE TABLE IF NOT EXISTS form_fields_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            field_type TEXT NOT NULL CHECK(field_type IN ('text','email','tel','textarea','select','checkbox','file','signature','heading','divider')),
            field_name TEXT DEFAULT NULL,
            field_label TEXT DEFAULT NULL,
            placeholder TEXT DEFAULT NULL,
            help_text TEXT DEFAULT NULL,
            options_json TEXT DEFAULT '{}',
            is_required INTEGER DEFAULT 0,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
        )");
        $db->exec("INSERT INTO form_fields_new (id, form_id, field_type, field_name, field_label, placeholder, help_text, options_json, is_required, sort_order, created_at, updated_at)
            SELECT id, form_id, field_type, field_name, field_label, placeholder, help_text, options_json, is_required, sort_order, created_at, updated_at
            FROM form_fields");
        $db->exec("DROP TABLE form_fields");
        $db->exec("ALTER TABLE form_fields_new RENAME TO form_fields");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS form_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL,
        payload_json TEXT NOT NULL,
        attachments_json TEXT DEFAULT '[]',
        ip_address TEXT DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_forms_slug ON forms(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_forms_active ON forms(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_fields_form_sort ON form_fields(form_id, sort_order)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_submissions_form_date ON form_submissions(form_id, created_at DESC)");

    $seedPages = [
        [
            'title' => 'Startseite',
            'path' => '',
            'meta_title' => SITE_NAME,
            'meta_description' => 'Willkommen auf der offiziellen Website von ' . SITE_NAME . '.',
            'content_html' => '<section><h2>Willkommen</h2><p>Diese Startseite wird zentral aus dem CMS verwaltet.</p><p>Bearbeiten Sie Inhalte, SEO und Startseiten-Bausteine in der Seitenverwaltung.</p></section>',
        ],
        [
            'title' => 'Impressum',
            'path' => 'impressum',
            'meta_title' => 'Impressum',
            'meta_description' => 'Impressum des Vereins.',
            'content_html' => '<h2>Angaben gemaess Paragraf 5 TMG</h2><p>Verein: <strong>Bitte im CMS ergaenzen</strong><br>Adresse: <strong>Bitte im CMS ergaenzen</strong></p><h2>Vertretungsberechtigt</h2><p><strong>Bitte im CMS ergaenzen</strong></p><h2>Kontakt</h2><p><strong>Bitte im CMS ergaenzen</strong></p>',
        ],
        [
            'title' => 'Datenschutz',
            'path' => 'datenschutz',
            'meta_title' => 'Datenschutz',
            'meta_description' => 'Datenschutzhinweise des Vereins.',
            'content_html' => '<h2>Datenschutz auf einen Blick</h2><p>Diese Seite wird zentral ueber das CMS verwaltet. Bitte ergaenzen Sie hier Ihre rechtssicheren Datenschutzhinweise.</p><h2>Verantwortliche Stelle</h2><p><strong>Bitte im CMS ergaenzen</strong></p><h2>Kontaktformulare</h2><p>Die Formularinhalte und Pflichtfelder werden ueber die CMS-Einstellungen gesteuert.</p>',
        ],
        [
            'title' => 'Verein',
            'path' => 'verein',
            'meta_title' => 'Unser Verein',
            'meta_description' => 'Informationen zum Verein.',
            'content_html' => '<p>Diese Vereinsseite wird dynamisch aus dem CMS gerendert.</p><p>Sie koennen hier modulare Inhalte, Galerien und individuelle HTML-Bloecke pflegen.</p>',
        ],
        [
            'title' => 'Jahresrueckblick 2026',
            'path' => '2026/karnevals-umzug',
            'meta_title' => 'Karnevals-Umzug 2026',
            'meta_description' => 'Beispiel fuer hierarchische Clean-URL Seite.',
            'content_html' => '<p>Beispielseite fuer die gewuenschte URL-Hierarchie <code>/2026/karnevals-umzug</code>.</p><p>Galerieeinbettung moeglich mit <code>[gallery:slug]</code>.</p>',
        ],
    ];

    $pathExistsStmt = $db->prepare("SELECT id FROM site_pages WHERE path = :path LIMIT 1");
    $insertSeedStmt = $db->prepare("INSERT INTO site_pages (title, path, meta_title, meta_description, content_html, blocks_json, status) VALUES (:title, :path, :meta_title, :meta_description, :content_html, :blocks_json, 'published')");
    foreach ($seedPages as $page) {
        $pathExistsStmt->execute([':path' => $page['path']]);
        $exists = (bool) $pathExistsStmt->fetch();
        if ($exists) {
            continue;
        }

        $insertSeedStmt->execute([
            ':title' => $page['title'],
            ':path' => $page['path'],
            ':meta_title' => $page['meta_title'],
            ':meta_description' => $page['meta_description'],
            ':content_html' => $page['content_html'],
            ':blocks_json' => json_encode([
                'heroEnabled' => false,
                'eventsEnabled' => false,
                'galleryPreviewEnabled' => false,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ============================
    // Galleries
    // ============================
    $db->exec("CREATE TABLE IF NOT EXISTS galleries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        is_published INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS gallery_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gallery_id INTEGER NOT NULL,
        image_path TEXT NOT NULL,
        caption TEXT DEFAULT NULL,
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_gallery_images_gallery ON gallery_images(gallery_id)");

    // ============================
    // Seed default app settings
    // ============================
    $settingsCount = (int) $db->query("SELECT COUNT(*) FROM app_settings")->fetchColumn();
    if ($settingsCount === 0) {
        $defaultSettings = [
            'theme' => [
                'primary' => '#7c3aed',
                'secondary' => '#5b21b6',
                'accent' => '#f59e0b',
                'background' => '#f8faf9',
                'surface' => '#ffffff',
                'text' => '#1f2937',
            ],
            'branding' => [
                'siteName' => SITE_NAME,
                'logoHeader' => '/src/wkc-logo.svg',
                'logoFooter' => '/src/wkc-logo-white.svg',
                'favicon' => '',
            ],
            'typography' => [
                'headingFont' => 'Luckiest Guy',
                'bodyFont' => 'Public Sans',
            ],
            'menu' => [
                'main' => [
                    ['label' => 'Startseite', 'url' => '/'],
                    ['label' => 'Neuigkeiten', 'url' => '/neuigkeiten'],
                        ['label' => 'Galerien', 'url' => '/galerien'],
                    ['label' => 'Themen', 'url' => '/themen'],
                    ['label' => 'Kontakt', 'url' => '/#kontakt'],
                ],
                'footer' => [
                    ['label' => 'Impressum', 'url' => '/impressum'],
                    ['label' => 'Datenschutz', 'url' => '/datenschutz'],
                ],
            ],
            'integrations' => [
                'cloudflareTurnstileSiteKey' => '',
                'cloudflareTurnstileSecret' => '',
                'googleAnalyticsId' => '',
                'customHeadCode' => '',
                'customBodyCode' => '',
            ],
            'smtp' => [
                'host' => SMTP_HOST,
                'port' => SMTP_PORT > 0 ? SMTP_PORT : 587,
                'user' => SMTP_USER,
                'pass' => SMTP_PASS,
                'from' => SMTP_FROM,
                'from_name' => SMTP_FROM_NAME,
                'contact_recipient' => CONTACT_RECIPIENT,
                'secure' => in_array(SMTP_SECURE, ['tls', 'ssl'], true) ? SMTP_SECURE : 'tls',
            ],
            'forms' => [
                'contact' => [
                    'enabled' => true,
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'required' => false],
                        ['name' => 'email', 'label' => 'E-Mail', 'required' => false],
                        ['name' => 'subject', 'label' => 'Betreff', 'required' => true],
                        ['name' => 'message', 'label' => 'Nachricht', 'required' => true],
                    ],
                ],
                'membership' => [
                    'enabled' => true,
                    'fields' => [
                        ['name' => 'vorname', 'label' => 'Vorname', 'required' => true],
                        ['name' => 'nachname', 'label' => 'Nachname', 'required' => true],
                        ['name' => 'email', 'label' => 'E-Mail', 'required' => true],
                        ['name' => 'telefon', 'label' => 'Telefon', 'required' => false],
                        ['name' => 'motivation', 'label' => 'Motivation', 'required' => false],
                    ],
                ],
            ],
            'homepage' => [
                'heroEnabled' => true,
                'newsEnabled' => true,
                'eventsEnabled' => true,
                'galleryPreviewEnabled' => true,
            ],
            'seo' => [
                'defaultMetaTitle' => SITE_NAME,
                'defaultMetaDescription' => 'Aktuelles, Termine und Projekte von ' . SITE_NAME . '.',
                'defaultOgImage' => '',
            ],
            'features' => [
                'politicsEnabled' => false,
            ],
        ];

        $ins = $db->prepare("INSERT INTO app_settings (key, value, updated_at) VALUES (:key, :value, datetime('now'))");
        foreach ($defaultSettings as $key => $value) {
            $ins->execute([
                ':key' => $key,
                ':value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    migrateLegacyBrandingSettings($db);

    // ============================
    // Seed the first administrator only when deployment credentials are supplied.
    // ============================
    $check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($check == 0) {
        $initialUsername = envConfig('ADMIN_INITIAL_USERNAME', 'admin');
        $initialPassword = envConfig('ADMIN_INITIAL_PASSWORD');
        $initialName = envConfig('ADMIN_INITIAL_DISPLAY_NAME', 'Administrator');

        if ($initialPassword !== '' && strlen($initialPassword) >= 8 && preg_match('/^[a-zA-Z0-9_-]+$/', $initialUsername)) {
            $db->prepare("INSERT INTO users (username, password_hash, display_name, role, position, board_order) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$initialUsername, password_hash($initialPassword, PASSWORD_DEFAULT), $initialName, 'admin', 'Administrator', 0]);
        } elseif ($initialPassword !== '') {
            error_log('WKC initial administrator was not created: ADMIN_INITIAL_PASSWORD must be at least 8 characters and ADMIN_INITIAL_USERNAME must be valid.');
        } else {
            error_log('WKC initial administrator was not created: set ADMIN_INITIAL_PASSWORD before first use.');
        }
    }

    // ============================
    // Seed: Default goal topics & items (from existing content)
    // ============================
    $topicCheck = $db->query("SELECT COUNT(*) FROM goal_topics")->fetchColumn();
    if ($topicCheck == 0) {
        $seedTopics = [
            ['Infrastruktur', 'infra', '#2563eb', 'construction', 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=600&h=450&fit=crop', 'Nachhaltiger Ausbau und Sicherung der Infrastruktur â€“ Grundschule, Kinderbetreuung und sinnvolle Nutzung von FlÃ¤chen.', 1],
            ['SouverÃ¤nitÃ¤t', 'politik', '#4f46e5', 'account_balance', 'https://images.unsplash.com/photo-1555848962-6e79363ec58f?w=600&h=450&fit=crop', 'Aufrechterhaltung der politischen und wirtschaftlichen SouverÃ¤nitÃ¤t durch FÃ¶rdermittel, fairen Finanzausgleich und Digitalisierung.', 2],
            ['Natur & Klimaschutz', 'natur', '#16a34a', 'eco', 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=600&h=450&fit=crop', 'Nachhaltige Energiegewinnung, modernisierte Beleuchtung und Schutz natÃ¼rlicher LebensrÃ¤ume.', 3],
            ['Breitband', 'breitband', '#0891b2', 'wifi', 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&h=450&fit=crop', 'Nachhaltiger Ausbau des Breitbandnetzwerkes â€“ Glasfaser und 5G fÃ¼r alle Haushalte.', 4],
            ['Ehrenamt & Vereine', 'ehrenamt', '#ea580c', 'volunteer_activism', 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=600&h=450&fit=crop', 'StÃ¤rkung des Ehrenamts, der Vereine und VerbÃ¤nde â€“ das RÃ¼ckgrat unserer Dorfgemeinschaft.', 5],
            ['Kommunikation', 'kommunikation', '#9333ea', 'forum', 'https://images.unsplash.com/photo-1529070538774-1843cb3265df?w=600&h=450&fit=crop', 'GrÃ¼ndung einer offenen ortsinternen Kommunikationsplattform fÃ¼r BÃ¼rgerbeteiligung und Dialog.', 6],
            ['Gewerbegebiet', 'gewerbe', '#d97706', 'factory', 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&h=450&fit=crop', 'Erweiterung des Gewerbegebiets zur wirtschaftlichen StÃ¤rkung und Schaffung neuer ArbeitsplÃ¤tze.', 7],
        ];

        $topicStmt = $db->prepare("INSERT INTO goal_topics (name, slug, color, icon, image, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedTopics as $t) {
            $topicStmt->execute($t);
        }

        $seedItems = [
            // Infrastruktur (topic 1)
            [1, 'Erhaltung der Grundschule', 'Sicherung des Schulstandorts fÃ¼r die Zukunft unserer Kinder im Ort.', 'school', 1],
            [1, 'Ganztagsbetreuung fÃ¼r Kinder von 1 bis 10 Jahren', 'VerlÃ¤ssliche Betreuungsangebote fÃ¼r Familien schaffen, um Vereinbarkeit von Familie und Beruf zu ermÃ¶glichen.', 'child_care', 2],
            [1, 'Sinnvolle Nutzung/Umwidmung von ungenutzten PlÃ¤tzen und Immobilien', 'Leerstehende FlÃ¤chen revitalisieren und der Gemeinde zugÃ¤nglich machen.', 'real_estate_agent', 3],
            // SouverÃ¤nitÃ¤t (topic 2)
            [2, 'Qualifizierung FÃ¶rdermittelberater', 'Professionelle Beratung zur optimalen Nutzung von FÃ¶rderprogrammen fÃ¼r die Gemeinde.', 'workspace_premium', 1],
            [2, 'Fairer Finanzausgleich der Mitgliedsgemeinden', 'Gerechte Verteilung der finanziellen Lasten innerhalb der Samtgemeinde.', 'balance', 2],
            [2, 'Digitalisierung der Verwaltung', 'Moderne digitale BÃ¼rgerservices einfÃ¼hren und Prozesse vereinfachen.', 'devices', 3],
            [2, 'Interkommunale Zusammenarbeit fÃ¶rdern', 'Gemeinsam mit Nachbargemeinden Synergien schaffen und Kosten senken.', 'handshake', 4],
            // Natur & Klimaschutz (topic 3)
            [3, 'Ausbau Photovoltaik statt Windkraft', 'Nachhaltige Energiegewinnung durch Solaranlagen priorisieren.', 'solar_power', 1],
            [3, 'Umstellung der StraÃŸenlampen auf LED', 'Energieeffiziente Beleuchtung fÃ¼r alle StraÃŸen in Wulften â€“ erfolgreich umgesetzt!', 'lightbulb', 2],
            [3, 'Anlage, Pflege und Ausbau von biologischen Reservaten', 'Schutz und Erweiterung natÃ¼rlicher LebensrÃ¤ume in der Region.', 'forest', 3],
            [3, 'Anlagen und Pflege neuer GrÃ¼nflÃ¤chen innerorts', 'GrÃ¼ne Oasen im Ortskern schaffen und pflegen.', 'park', 4],
            // Breitband (topic 4)
            [4, 'Lokal: Glasfaser', 'FlÃ¤chendeckender Glasfaserausbau fÃ¼r schnelles Internet in ganz Wulften â€“ erfolgreich umgesetzt!', 'cable', 1],
            [4, 'Funk: 5G', 'Verbesserung der Mobilfunkabdeckung im gesamten Gemeindegebiet.', 'cell_tower', 2],
            // Ehrenamt (topic 5)
            [5, 'Vereinsleben stÃ¤rken', 'Aktive UnterstÃ¼tzung fÃ¼r Vereine und VerbÃ¤nde durch FÃ¶rderung, RÃ¤umlichkeiten und Anerkennung des ehrenamtlichen Engagements.', 'groups', 1],
            [5, 'Engagement anerkennen', 'WÃ¼rdigung und Sichtbarkeit fÃ¼r ehrenamtliches Engagement in der Gemeinde.', 'favorite', 2],
            // Kommunikation (topic 6)
            [6, 'BÃ¼rgerbeteiligung & BÃ¼rgerfragestunde', 'â€žEs gibt etwas zu besprechen" â€“ Offene Plattform fÃ¼r Dialog, Fragen und Ideen aller BÃ¼rgerinnen und BÃ¼rger.', 'record_voice_over', 1],
            [6, 'Offene Kommunikationsplattform', 'Digitale und analoge KanÃ¤le zur transparenten Information und Beteiligung der BÃ¼rgerschaft.', 'hub', 2],
            // Gewerbe (topic 7)
            [7, 'Erweiterung und Attraktivierung des Gewerbegebiets', 'Schaffung neuer GewerbeflÃ¤chen und Ansiedlung von Unternehmen zur StÃ¤rkung der lokalen Wirtschaft.', 'domain_add', 1],
            [7, 'ArbeitsplÃ¤tze im Ort schaffen', 'Lokale WirtschaftsfÃ¶rderung und attraktive Bedingungen fÃ¼r Unternehmen und Betriebe.', 'work', 2],
        ];

        $itemStmt = $db->prepare("INSERT INTO goal_items (topic_id, title, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($seedItems as $item) {
            $itemStmt->execute($item);
        }
    }

    runUtf8DataRepair($db);
}

function migrateLegacyBrandingSettings(PDO $db): void {
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = 'branding' LIMIT 1");
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return;
    }

    $branding = json_decode($value, true);
    if (!is_array($branding)) {
        return;
    }

    $changed = false;
    $legacyName = implode(' ', ['WÃ¤hlergruppe', 'Zukunft', 'Wulften']);
    $legacyShortName = implode(' ', ['Zukunft', 'Wulften']);
    if (($branding['siteName'] ?? '') === $legacyName || ($branding['siteName'] ?? '') === $legacyShortName) {
        $branding['siteName'] = SITE_NAME;
        $changed = true;
    }

    foreach (['logoHeader', 'logoFooter'] as $key) {
        $value = $branding[$key] ?? null;
        if (!is_string($value)) {
            continue;
        }
        if (in_array($value, ['/src/wkc-logo.json', '/src/wkc-logo.svg', '/src/wkc-logo-white.svg'], true)) {
            $branding[$key] = $key === 'logoFooter' ? '/src/wkc-logo-white.svg' : '/src/wkc-logo.svg';
            $changed = true;
        }
    }

    if ($changed) {
        $update = $db->prepare("UPDATE app_settings SET value = :value, updated_at = datetime('now') WHERE key = 'branding'");
        $update->execute([':value' => json_encode($branding, JSON_UNESCAPED_UNICODE)]);
    }
}

function getAppSettings(): array {
    $db = getDB();
    $rows = $db->query("SELECT key, value FROM app_settings")->fetchAll();
    $settings = [];

    foreach ($rows as $row) {
        $decoded = json_decode($row['value'], true);
        $settings[$row['key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['value'];
    }

    return $settings;
}

function getAppSetting(string $key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        return $default;
    }

    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $value;
}

function setAppSetting(string $key, $value): void {
    $db = getDB();
    $encoded = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("INSERT INTO app_settings (key, value, updated_at)
        VALUES (:key, :value, datetime('now'))
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')");
    $stmt->execute([
        ':key' => $key,
        ':value' => $encoded,
    ]);
}
