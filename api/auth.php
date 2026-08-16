<?php
/**
 * WKC – Auth API
 * Login / Logout / Session check for backend users.
 */

ob_start();
require_once __DIR__ . '/config.php';

// Start session with correct name for all actions
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================
// LOGIN
// ============================
if ($method === 'POST' && $action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Benutzername und Passwort erforderlich'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, username, display_name, password_hash, position, profile_image, role
        FROM users
        WHERE username = :username AND is_active = 1
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Rate-limit hint: sleep briefly to slow brute-force
        usleep(500000); // 500ms
        jsonResponse(['error' => 'Ungültige Anmeldedaten'], 401);
    }

    // Regenerate session to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['position'] = $user['position'];
    $_SESSION['profile_image'] = $user['profile_image'];
    $_SESSION['user_role'] = $user['role'] ?? 'member';
    $_SESSION['login_time'] = time();

    jsonResponse([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'position' => $user['position'],
            'profile_image' => $user['profile_image'],
            'role' => $user['role'] ?? 'member',
        ],
        'message' => 'Erfolgreich angemeldet.'
    ]);
}

// ============================
// LOGOUT
// ============================
if ($action === 'logout') {
    destroyAuthSession();

    jsonResponse(['success' => true, 'message' => 'Abgemeldet.']);
}

// ============================
// SESSION CHECK
// ============================
if ($method === 'GET' && $action === 'check') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['authenticated' => false], 401);
    }

    $user = authenticatedUser();
    if ($user === null) {
        jsonResponse(['authenticated' => false, 'reason' => 'session_expired'], 401);
    }

    jsonResponse([
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $_SESSION['username'],
            'display_name' => $_SESSION['display_name'],
            'position' => $_SESSION['position'],
            'profile_image' => $_SESSION['profile_image'],
            'role' => $_SESSION['user_role'] ?? 'member',
        ]
    ]);
}

// ============================
// CHANGE PASSWORD
// ============================
if ($method === 'POST' && $action === 'password') {
    $user = requireAuth();
    $db = getDB();

    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (empty($currentPw) || empty($newPw)) {
        jsonResponse(['error' => 'Alle Felder sind erforderlich'], 400);
    }
    if ($newPw !== $confirmPw) {
        jsonResponse(['error' => 'Passwörter stimmen nicht überein'], 400);
    }
    if (strlen($newPw) < 8) {
        jsonResponse(['error' => 'Neues Passwort muss mindestens 8 Zeichen lang sein'], 400);
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($currentPw, $hash)) {
        jsonResponse(['error' => 'Aktuelles Passwort ist falsch'], 403);
    }

    $db->prepare("UPDATE users SET password_hash = :pw WHERE id = :id")->execute([
        ':pw' => password_hash($newPw, PASSWORD_DEFAULT),
        ':id' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'Passwort geändert.']);
}

// ============================
// REQUEST PASSWORD RESET
// ============================
if ($method === 'POST' && $action === 'request_reset') {
    $db = getDB();
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        jsonResponse(['error' => 'Benutzername oder E-Mail erforderlich'], 400);
    }

    // Look up user by username or email
    $stmt = $db->prepare("SELECT id, display_name, email, username FROM users WHERE (username = :id1 OR email = :id2) AND is_active = 1");
    $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
    $resetUser = $stmt->fetch();

    // Always return success (don't reveal if user exists)
    if (!$resetUser || empty($resetUser['email'])) {
        // Still sleep to prevent timing attacks
        usleep(500000);
        jsonResponse(['success' => true, 'message' => 'Falls ein Konto mit diesen Daten existiert, wurde eine E-Mail zum Zurücksetzen gesendet.']);
    }

    // Invalidate old tokens
    $db->prepare("DELETE FROM password_tokens WHERE user_id = :uid AND type = 'reset'")->execute([':uid' => $resetUser['id']]);

    // Create token (valid for 1 hour)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $db->prepare("INSERT INTO password_tokens (user_id, token, type, expires_at) VALUES (:uid, :token, 'reset', :expires)")
        ->execute([':uid' => $resetUser['id'], ':token' => $token, ':expires' => $expires]);

    // Send reset email
    require_once __DIR__ . '/mail.php';
    $resetUrl = SITE_URL . '/admin/passwort-setzen.php?token=' . $token;
    $html = emailPasswordReset($resetUser['display_name'], $resetUrl);
    sendMail($resetUser['email'], 'Passwort zurücksetzen – WKC', $html);

    jsonResponse(['success' => true, 'message' => 'Falls ein Konto mit diesen Daten existiert, wurde eine E-Mail zum Zurücksetzen gesendet.']);
}

// ============================
// VERIFY TOKEN (for reset & invitation)
// ============================
if ($method === 'GET' && $action === 'verify_token') {
    $db = getDB();
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        jsonResponse(['error' => 'Token erforderlich'], 400);
    }

    $stmt = $db->prepare("
        SELECT pt.*, u.display_name, u.username
        FROM password_tokens pt
        JOIN users u ON u.id = pt.user_id
        WHERE pt.token = :token AND pt.used_at IS NULL AND pt.expires_at > datetime('now')
    ");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        jsonResponse(['error' => 'Token ungültig oder abgelaufen'], 400);
    }

    jsonResponse([
        'valid' => true,
        'type' => $tokenData['type'],
        'display_name' => $tokenData['display_name'],
        'username' => $tokenData['username'],
    ]);
}

// ============================
// SET PASSWORD (via token – reset or invitation)
// ============================
if ($method === 'POST' && $action === 'set_password') {
    $db = getDB();
    $token = $_POST['token'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        jsonResponse(['error' => 'Token erforderlich'], 400);
    }
    if (empty($newPw)) {
        jsonResponse(['error' => 'Passwort erforderlich'], 400);
    }
    if ($newPw !== $confirmPw) {
        jsonResponse(['error' => 'Passwörter stimmen nicht überein'], 400);
    }
    if (strlen($newPw) < 8) {
        jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen lang sein'], 400);
    }

    // Verify token
    $stmt = $db->prepare("
        SELECT pt.*, u.display_name, u.email
        FROM password_tokens pt
        JOIN users u ON u.id = pt.user_id
        WHERE pt.token = :token AND pt.used_at IS NULL AND pt.expires_at > datetime('now')
    ");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        jsonResponse(['error' => 'Token ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.'], 400);
    }

    // Set password
    $db->prepare("UPDATE users SET password_hash = :pw, must_set_password = 0 WHERE id = :id")->execute([
        ':pw' => password_hash($newPw, PASSWORD_DEFAULT),
        ':id' => $tokenData['user_id'],
    ]);

    // Mark token as used
    $db->prepare("UPDATE password_tokens SET used_at = datetime('now') WHERE id = :id")->execute([':id' => $tokenData['id']]);

    // Send confirmation email
    if (!empty($tokenData['email'])) {
        require_once __DIR__ . '/mail.php';
        $html = emailPasswordChanged($tokenData['display_name']);
        sendMail($tokenData['email'], 'Passwort geändert – WKC', $html);
    }

    $typeMsg = $tokenData['type'] === 'invitation'
        ? 'Ihr Passwort wurde festgelegt. Sie können sich jetzt anmelden.'
        : 'Ihr Passwort wurde zurückgesetzt. Sie können sich jetzt anmelden.';

    jsonResponse(['success' => true, 'message' => $typeMsg]);
}

// ============================
// INITIAL SETUP – deployment-only
// ============================
if ($method === 'POST' && $action === 'setup') {
    jsonResponse([
        'error' => 'Die Erstinstallation erfolgt ausschließlich über ADMIN_INITIAL_USERNAME und ADMIN_INITIAL_PASSWORD.'
    ], 403);
}

// Fallback
jsonResponse(['error' => 'Ungültige Anfrage'], 400);
