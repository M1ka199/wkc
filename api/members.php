<?php
/**
 * WKC – Members API
 * CRUD operations for board members / users.
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

    // List active members (public, excludes admin role)
    if ($action === 'list') {
        $stmt = $db->query("
            SELECT id, username, display_name, position, profile_image, bio, focus_areas, member_since, board_order, is_board_member
            FROM users
            WHERE is_active = 1 AND role != 'admin'
            ORDER BY board_order ASC, display_name ASC
        ");
        $members = $stmt->fetchAll();

        // Parse focus_areas from JSON
        foreach ($members as &$m) {
            $m['focus_areas'] = json_decode($m['focus_areas'] ?? '[]', true) ?: [];
        }

        jsonResponse(['members' => $members]);
    }

    // List board members only (public, for homepage)
    if ($action === 'board_list') {
        $stmt = $db->query("
            SELECT id, username, display_name, position, profile_image, bio, focus_areas, member_since, board_order
            FROM users
            WHERE is_active = 1 AND is_board_member = 1
            ORDER BY board_order ASC, display_name ASC
        ");
        $members = $stmt->fetchAll();

        foreach ($members as &$m) {
            $m['focus_areas'] = json_decode($m['focus_areas'] ?? '[]', true) ?: [];
        }

        jsonResponse(['members' => $members]);
    }

    // Management list: administrators receive private account fields; editors receive author-safe fields.
    if ($action === 'admin_list') {
        $user = requireAuth();
        if (!in_array($user['role'] ?? '', ['admin', 'editor'], true)) {
            jsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $fields = ($user['role'] ?? '') === 'admin'
            ? 'id, username, display_name, email, position, profile_image, bio, focus_areas, member_since, board_order, role, is_board_member, invitation_sent, must_set_password'
            : 'id, username, display_name, position, profile_image, bio, focus_areas, member_since, board_order, is_board_member';
        $stmt = $db->query("SELECT {$fields} FROM users WHERE is_active = 1 ORDER BY board_order ASC, display_name ASC");
        $members = $stmt->fetchAll();

        foreach ($members as &$m) {
            $m['focus_areas'] = json_decode($m['focus_areas'] ?? '[]', true) ?: [];
        }

        jsonResponse(['members' => $members]);
    }

    // Single member profile. Public callers receive only explicitly public fields.
    if ($action === 'profile') {
        $id = intval($_GET['id'] ?? 0);
        $slug = $_GET['slug'] ?? '';

        if (!$id && empty($slug)) {
            jsonResponse(['error' => 'Mitglied-ID oder Slug erforderlich'], 400);
        }

        if ($id) {
            $targetStmt = $db->prepare("SELECT id, role FROM users WHERE id = :id AND is_active = 1");
            $targetStmt->execute([':id' => $id]);
        } else {
            $targetStmt = $db->prepare("SELECT id, role FROM users WHERE username = :slug AND is_active = 1");
            $targetStmt->execute([':slug' => $slug]);
        }

        $target = $targetStmt->fetch();
        if (!$target) {
            jsonResponse(['error' => 'Mitglied nicht gefunden'], 404);
        }

        $viewer = authenticatedUser();
        $publicFields = 'id, username, display_name, position, profile_image, bio, focus_areas, personal_goals, quote, member_since, board_order, is_board_member';
        $privateProfileFields = 'age, family_status, children, grandchildren, occupation, clubs';
        if (($viewer['role'] ?? '') === 'admin') {
            $fields = $publicFields . ', ' . $privateProfileFields . ', email, role, invitation_sent, must_set_password';
        } elseif (($viewer['id'] ?? 0) === (int) $target['id']) {
            $fields = $publicFields . ', ' . $privateProfileFields . ', email';
        } elseif (($target['role'] ?? '') === 'admin') {
            jsonResponse(['error' => 'Mitglied nicht gefunden'], 404);
        } else {
            $fields = $publicFields;
        }

        $stmt = $db->prepare("SELECT {$fields} FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $target['id']]);
        $member = $stmt->fetch();
        $member['focus_areas'] = json_decode($member['focus_areas'] ?? '[]', true) ?: [];
        $member['personal_goals'] = json_decode($member['personal_goals'] ?? '[]', true) ?: [];
        $member['clubs'] = json_decode($member['clubs'] ?? '[]', true) ?: [];

        jsonResponse(['member' => $member]);
    }
}

// ============================
// Protected Routes (POST, PUT, DELETE)
// ============================
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $user = requireAuth();
    $db = getDB();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Members may update their own profile; all user-management changes require an administrator.
    if (!($method === 'POST' && $action === 'self_update') && ($user['role'] ?? '') !== 'admin') {
        jsonResponse(['error' => 'Nur Administratoren können Mitglieder verwalten'], 403);
    }

    // CREATE Member
    if ($method === 'POST') {
        // SELF-UPDATE endpoint (for Einstellungen tab)
        if ($action === 'self_update') {
            $id = $user['id'];
            $fields = [];
            $params = [':id' => $id];

            $selfAllowed = ['display_name', 'position', 'bio', 'focus_areas', 'personal_goals', 'quote', 'member_since', 'age', 'family_status', 'children', 'grandchildren', 'occupation', 'clubs'];
            foreach ($selfAllowed as $field) {
                if (isset($_POST[$field])) {
                    $fields[] = "{$field} = :{$field}";
                    $params[":{$field}"] = $_POST[$field];
                }
            }

            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $profileImage = handleMemberImageUpload($_FILES['profile_image']);
                $fields[] = "profile_image = :profile_image";
                $params[':profile_image'] = $profileImage;
            }

            if (empty($fields)) {
                jsonResponse(['error' => 'Keine Änderungen übermittelt'], 400);
            }

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
            $db->prepare($sql)->execute($params);

            // Update session display_name if changed
            if (isset($_POST['display_name'])) {
                $_SESSION['display_name'] = $_POST['display_name'];
            }
            if (isset($_POST['position'])) {
                $_SESSION['position'] = $_POST['position'];
            }
            if (isset($profileImage)) {
                $_SESSION['profile_image'] = $profileImage;
            }

            jsonResponse(['success' => true, 'message' => 'Profil aktualisiert.']);
        }

        // SEND INVITATION endpoint
        if ($action === 'send_invitation') {
            if ($user['role'] !== 'admin') {
                jsonResponse(['error' => 'Nur Administratoren können Einladungen versenden'], 403);
            }

            $memberId = intval($_POST['member_id'] ?? 0);
            if (!$memberId) jsonResponse(['error' => 'Mitglied-ID erforderlich'], 400);

            // Get member data
            $memberStmt = $db->prepare("SELECT id, username, display_name, email FROM users WHERE id = :id AND is_active = 1");
            $memberStmt->execute([':id' => $memberId]);
            $member = $memberStmt->fetch();

            if (!$member) jsonResponse(['error' => 'Mitglied nicht gefunden'], 404);
            if (empty($member['email'])) jsonResponse(['error' => 'Keine E-Mail-Adresse hinterlegt. Bitte zuerst eine E-Mail beim Mitglied eintragen.'], 400);

            // Invalidate old invitation tokens
            $db->prepare("DELETE FROM password_tokens WHERE user_id = :uid AND type = 'invitation'")->execute([':uid' => $memberId]);

            // Create invitation token (valid for 48 hours)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 48 * 3600);

            $db->prepare("INSERT INTO password_tokens (user_id, token, type, expires_at) VALUES (:uid, :token, 'invitation', :expires)")
                ->execute([':uid' => $memberId, ':token' => $token, ':expires' => $expires]);

            // Mark invitation as sent + set must_set_password
            $db->prepare("UPDATE users SET invitation_sent = 1, must_set_password = 1 WHERE id = :id")->execute([':id' => $memberId]);

            // Send invitation email
            require_once __DIR__ . '/mail.php';
            $inviteUrl = SITE_URL . '/admin/passwort-setzen.php?token=' . $token;
            $html = emailMemberInvitation($member['display_name'], $member['username'], $inviteUrl);
            $sent = sendMail($member['email'], 'Einladung – WKC', $html);

            if ($sent) {
                jsonResponse(['success' => true, 'message' => 'Einladung wurde an ' . $member['email'] . ' gesendet.']);
            } else {
                jsonResponse(['error' => 'E-Mail konnte nicht gesendet werden. Bitte SMTP-Konfiguration prüfen.'], 500);
            }
        }

        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $position = trim($_POST['position'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $focusAreas = $_POST['focus_areas'] ?? '[]';
        $personalGoals = $_POST['personal_goals'] ?? '[]';
        $quote = trim($_POST['quote'] ?? '');
        $memberSince = trim($_POST['member_since'] ?? '');
        $boardOrder = intval($_POST['board_order'] ?? 99);
        $role = $_POST['role'] ?? 'member';
        if (!in_array($role, ['admin', 'editor', 'member'])) $role = 'member';
        $isBoardMember = isset($_POST['is_board_member']) && $_POST['is_board_member'] ? 1 : 0;
        $age = isset($_POST['age']) && $_POST['age'] !== '' ? intval($_POST['age']) : null;
        $familyStatus = trim($_POST['family_status'] ?? '');
        $children = trim($_POST['children'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $clubs = $_POST['clubs'] ?? '[]';
        $grandchildren = isset($_POST['grandchildren']) && $_POST['grandchildren'] !== '' ? intval($_POST['grandchildren']) : null;

        $email = trim($_POST['email'] ?? '');
        $sendInvitation = isset($_POST['send_invitation']) && $_POST['send_invitation'] === '1';

        // Validate – password only required if not sending invitation
        if (empty($username) || empty($displayName)) {
            jsonResponse(['error' => 'Benutzername und Anzeigename erforderlich'], 400);
        }
        if (!$sendInvitation && (empty($password) || strlen($password) < 8)) {
            jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }
        if ($sendInvitation && empty($email)) {
            jsonResponse(['error' => 'E-Mail erforderlich für den Einladungsversand'], 400);
        }

        // Check duplicate username
        $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
        $check->execute([':u' => $username]);
        if ($check->fetchColumn() > 0) {
            jsonResponse(['error' => 'Benutzername bereits vergeben'], 409);
        }

        // Handle profile image
        $profileImage = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $profileImage = handleMemberImageUpload($_FILES['profile_image']);
        }

        // If sending invitation, set a random password (user will set their own)
        $passwordHash = $sendInvitation
            ? password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)
            : password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, display_name, email, position, profile_image, bio, focus_areas, personal_goals, quote, member_since, board_order, role, is_board_member, age, family_status, children, grandchildren, occupation, clubs, must_set_password)
            VALUES (:username, :pw, :name, :email, :position, :image, :bio, :focus, :goals, :quote, :since, :board_order, :role, :is_board, :age, :family_status, :children, :grandchildren, :occupation, :clubs, :must_set_pw)
        ");
        $stmt->execute([
            ':username' => $username,
            ':pw' => $passwordHash,
            ':email' => $email ?: null,
            ':name' => $displayName,
            ':position' => $position,
            ':image' => $profileImage,
            ':bio' => $bio,
            ':focus' => $focusAreas,
            ':goals' => $personalGoals,
            ':quote' => $quote,
            ':since' => $memberSince ?: null,
            ':board_order' => $boardOrder,
            ':role' => $role,
            ':is_board' => $isBoardMember,
            ':age' => $age,
            ':family_status' => $familyStatus ?: null,
            ':children' => $children ?: null,
            ':occupation' => $occupation ?: null,
            ':grandchildren' => $grandchildren,
            ':clubs' => $clubs,
            ':must_set_pw' => $sendInvitation ? 1 : 0,
        ]);

        $newMemberId = (int) $db->lastInsertId();
        $invitationSent = null;

        // If invitation requested, send it now
        if ($sendInvitation) {
            // Create invitation token (valid for 48 hours)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
            $db->prepare("INSERT INTO password_tokens (user_id, token, type, expires_at) VALUES (:uid, :token, 'invitation', :expires)")
                ->execute([':uid' => $newMemberId, ':token' => $token, ':expires' => $expires]);
            $db->prepare("UPDATE users SET invitation_sent = 1 WHERE id = :id")->execute([':id' => $newMemberId]);

            require_once __DIR__ . '/mail.php';
            $inviteUrl = SITE_URL . '/admin/passwort-setzen.php?token=' . $token;
            $html = emailMemberInvitation($displayName, $username, $inviteUrl);
            $invitationSent = sendMail($email, 'Einladung – WKC', $html);
        }

        jsonResponse([
            'success' => true,
            'id' => $newMemberId,
            'message' => $sendInvitation
                ? ($invitationSent ? 'Mitglied angelegt und Einladung versendet.' : 'Mitglied angelegt, aber die Einladung konnte nicht gesendet werden.')
                : 'Mitglied angelegt.',
            'email_error' => $sendInvitation && !$invitationSent,
        ], 201);
    }

    // UPDATE Member
    if ($method === 'PUT') {
        parse_str(file_get_contents('php://input'), $input);
        $id = intval($input['id'] ?? 0);

        if (!$id) {
            jsonResponse(['error' => 'Mitglied-ID erforderlich'], 400);
        }

        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['display_name', 'email', 'position', 'bio', 'focus_areas', 'personal_goals', 'quote', 'member_since', 'board_order', 'is_active', 'role', 'is_board_member', 'age', 'family_status', 'children', 'grandchildren', 'occupation', 'clubs'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        // Password update (optional)
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 8) {
                jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen lang sein'], 400);
            }
            $fields[] = "password_hash = :pw";
            $params[':pw'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'Keine Änderungen übermittelt'], 400);
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $db->prepare($sql)->execute($params);

        jsonResponse(['success' => true, 'message' => 'Mitglied aktualisiert.']);
    }

    // DELETE Member (soft-delete: set is_active = 0)
    if ($method === 'DELETE') {
        parse_str(file_get_contents('php://input'), $input);
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);

        if (!$id) {
            jsonResponse(['error' => 'Mitglied-ID erforderlich'], 400);
        }

        // Prevent self-deletion
        if ($id == $user['id']) {
            jsonResponse(['error' => 'Du kannst dich nicht selbst deaktivieren'], 403);
        }

        $db->prepare("UPDATE users SET is_active = 0 WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Mitglied deaktiviert.']);
    }
}

// ============================
// Helper
// ============================
function handleMemberImageUpload(array $file): ?string {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        jsonResponse(['error' => 'Ungültiger Upload'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!isset($allowedTypes[$mime])) {
        jsonResponse(['error' => 'Ungültiges Bildformat (JPG, PNG, WebP erlaubt)'], 400);
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonResponse(['error' => 'Bild zu groß (max. 5 MB)'], 400);
    }

    $dir = __DIR__ . '/../src/vorstandsmitglieder/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'member_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $path = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        jsonResponse(['error' => 'Upload fehlgeschlagen'], 500);
    }

    return 'src/vorstandsmitglieder/' . $filename;
}
