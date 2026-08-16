<?php
/**
 * Global CMS settings API.
 * GET is public (frontend consumes settings), POST is admin-only.
 */

ob_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB(); // Ensure migrations are applied.

function saveBrandingFile(array $file, string $prefix): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload fehlgeschlagen.');
    }

    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Dateityp nicht erlaubt.');
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Datei ist zu groß.');
    }

    $targetDir = __DIR__ . '/../uploads/branding';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
    }

    $ext = $allowed[$mime];
    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $targetDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    return '/uploads/branding/' . $name;
}

if ($method === 'GET') {
    $allSettings = getAppSettings();
    $authUser = authenticatedUser(false);
    $action = trim((string) ($_GET['action'] ?? ''));

    if (($_GET['scope'] ?? '') === 'admin') {
        if (($authUser['role'] ?? '') !== 'admin') {
            jsonResponse(['error' => 'Nur Administratoren'], 403);
        }
        if ($action === 'mail_status') {
            require_once __DIR__ . '/mail.php';
            $mailStatus = getMailDeliveryStatus();
            if (($_GET['probe'] ?? '') === '1') {
                $mailStatus['probe'] = probeMailTransport();
            }
            jsonResponse(['mail' => $mailStatus]);
        }
        jsonResponse(['settings' => $allSettings]);
    }

    $publicSettings = [];
    foreach (['theme', 'branding', 'typography', 'menu', 'forms', 'homepage', 'seo', 'features'] as $key) {
        if (array_key_exists($key, $allSettings)) {
            $publicSettings[$key] = $allSettings[$key];
        }
    }

    // Only values intentionally consumed by browser-side integrations are public.
    $integrations = is_array($allSettings['integrations'] ?? null) ? $allSettings['integrations'] : [];
    $publicIntegrations = array_intersect_key($integrations, array_flip([
        'cloudflareTurnstileSiteKey',
        'googleAnalyticsId',
        'customHeadCode',
        'customBodyCode',
    ]));
    if ($publicIntegrations) {
        $publicSettings['integrations'] = $publicIntegrations;
    }

    jsonResponse(['settings' => $publicSettings]);
}

if ($method === 'POST') {
    $user = requireAuth();
    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse(['error' => 'Nur Administratoren'], 403);
    }

    $payload = [];
    if (!empty($_POST['settings'])) {
        $payload = json_decode($_POST['settings'], true) ?: [];
    }

    if (!is_array($payload)) {
        jsonResponse(['error' => 'Ungültiges Settings-Format.'], 400);
    }

    $currentBranding = getAppSetting('branding', []);
    if (!is_array($currentBranding)) {
        $currentBranding = [];
    }

    try {
        if (!empty($_FILES['logo_header']) && ($_FILES['logo_header']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $currentBranding['logoHeader'] = saveBrandingFile($_FILES['logo_header'], 'logo-header');
        }
        if (!empty($_FILES['logo_footer']) && ($_FILES['logo_footer']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $currentBranding['logoFooter'] = saveBrandingFile($_FILES['logo_footer'], 'logo-footer');
        }
        if (!empty($_FILES['favicon']) && ($_FILES['favicon']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $currentBranding['favicon'] = saveBrandingFile($_FILES['favicon'], 'favicon');
        }
    } catch (RuntimeException $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }

    if (!empty($currentBranding)) {
        $payload['branding'] = array_merge($currentBranding, $payload['branding'] ?? []);
    }

    foreach ($payload as $key => $value) {
        setAppSetting((string) $key, $value);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Einstellungen gespeichert.',
        'settings' => getAppSettings(),
    ]);
}

jsonResponse(['error' => 'Methode nicht erlaubt'], 405);
