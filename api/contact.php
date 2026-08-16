<?php
/**
 * WKC – Contact Form API
 * Empfängt Kontaktformular-Daten und versendet E-Mails via PHPMailer.
 */

ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

// CORS & Method
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================
// LIST MESSAGES (admin only)
// ============================
if ($method === 'GET' && $action === 'list') {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Nur Administratoren'], 403);

    $db = getDB();
    $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 20");
    $messages = $stmt->fetchAll();
    jsonResponse(['messages' => $messages]);
}

if ($method !== 'POST') {
    jsonResponse(['error' => 'Nur POST-Anfragen erlaubt'], 405);
}

$settings = getAppSettings();
$formsCfg = $settings['forms'] ?? [];
$integrationsCfg = $settings['integrations'] ?? [];
$smtpCfg = $settings['smtp'] ?? [];
$contactRecipient = trim((string) ($smtpCfg['contact_recipient'] ?? ''));
if ($contactRecipient === '') {
    $contactRecipient = trim((string) CONTACT_RECIPIENT);
}

function verifyTurnstileToken(string $secret, string $token): bool {
    if ($secret === '' || $token === '') return false;

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);

    $result = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($result === false) return false;

    $data = json_decode($result, true);
    return !empty($data['success']);
}

// ============================
// Input Validation
// ============================
$type = trim($_POST['type'] ?? 'contact'); // 'contact' or 'membership'
if (!in_array($type, ['contact', 'membership'])) $type = 'contact';

$contactEnabled = (bool)($formsCfg['contact']['enabled'] ?? true);
$membershipEnabled = (bool)($formsCfg['membership']['enabled'] ?? true);
if (($type === 'contact' && !$contactEnabled) || ($type === 'membership' && !$membershipEnabled)) {
    jsonResponse(['error' => 'Dieses Formular ist derzeit deaktiviert.'], 403);
}

// Honeypot: silently accept bot submissions to reduce retries.
$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
    jsonResponse(['success' => true, 'message' => 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.']);
}

$turnstileSiteKey = trim((string)($integrationsCfg['cloudflareTurnstileSiteKey'] ?? ''));
$turnstileSecret = trim((string)($integrationsCfg['cloudflareTurnstileSecret'] ?? ''));
if ($turnstileSiteKey !== '' && $turnstileSecret !== '') {
    $turnstileToken = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if (!verifyTurnstileToken($turnstileSecret, $turnstileToken)) {
        jsonResponse(['error' => 'Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.'], 400);
    }
}

$isAnonymous = isset($_POST['anonymous']) && $_POST['anonymous'] === 'on';
$name = $isAnonymous ? 'Anonym' : trim($_POST['name'] ?? '');
$email = $isAnonymous ? '' : trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$privacy = isset($_POST['privacy']);

// Membership-specific fields
$phone = trim($_POST['phone'] ?? '');
$membershipMotivation = trim($_POST['motivation'] ?? '');

// Auto-generate subject for membership requests
if ($type === 'membership' && empty($subject)) {
    $subject = 'Beitrittsanfrage von ' . $name;
}

// Validation
if (empty($message) && $type === 'contact') {
    jsonResponse(['error' => 'Betreff und Nachricht sind Pflichtfelder.'], 400);
}

if ($type === 'membership' && empty($name)) {
    jsonResponse(['error' => 'Name ist ein Pflichtfeld.'], 400);
}

if (!$privacy) {
    jsonResponse(['error' => 'Bitte stimmen Sie der Datenschutzerklärung zu.'], 400);
}

if (!$isAnonymous && !empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 400);
}

// Build full message for membership
if ($type === 'membership') {
    $fullMessage = $message;
    if ($phone) $fullMessage = "Telefon: $phone\n" . ($membershipMotivation ? "Motivation: $membershipMotivation\n\n" : "\n") . $fullMessage;
    if ($membershipMotivation && !$phone) $fullMessage = "Motivation: $membershipMotivation\n\n" . $fullMessage;
    $message = $fullMessage ?: 'Beitrittsanfrage ohne weitere Angaben.';
}

// ============================
// Save to Database
// ============================
$savedToDatabase = false;
try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message, is_anonymous, ip_address, type) 
                          VALUES (:name, :email, :subject, :message, :is_anonymous, :ip, :type)");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':subject' => $subject,
        ':message' => $message,
        ':is_anonymous' => $isAnonymous ? 1 : 0,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':type' => $type,
    ]);
    $savedToDatabase = true;
} catch (Throwable $e) {
    error_log('WKC contact message persistence error: ' . $e->getMessage());
}

// ============================
// Send Email via PHPMailer
// ============================
$notificationSent = false;
$confirmationSent = false;
$mailError = mailConfigurationError();
try {
    if ($mailError === null && $contactRecipient !== '') {
        if ($type === 'membership') {
            $html = emailMembershipNotification($name, $email, $phone, $membershipMotivation ?: $message);
        } else {
            $html = emailContactNotification($name, $email, $subject, $message, $isAnonymous);
        }
        $notificationSent = sendMail(
            $contactRecipient,
            ($type === 'membership' ? '[Beitritt] ' : '[Kontakt] ') . $subject,
            $html,
            (!$isAnonymous && $email) ? $email : null,
            $name
        );
    } elseif ($mailError === null) {
        $mailError = 'E-Mail-Versand ist nicht konfiguriert. Es fehlt ein Kontakt-Empfänger in den SMTP-Einstellungen.';
    }

    // A confirmation is useful only after the notification reached the organization.
    if ($notificationSent && !$isAnonymous && !empty($email)) {
        if ($type === 'membership') {
            $confirmHtml = emailMembershipConfirmation($name);
        } else {
            $confirmHtml = emailContactConfirmation($name, $subject);
        }
        $confirmationSent = sendMail($email, 'Ihre Anfrage bei WKC', $confirmHtml);
    }
} catch (Throwable $e) {
    $mailError = 'E-Mail-Versand fehlgeschlagen.';
    error_log('WKC contact mail error: ' . $e->getMessage());
}

if (!$savedToDatabase && !$notificationSent) {
    jsonResponse([
        'error' => 'Ihre Nachricht konnte nicht gespeichert oder versendet werden. Bitte versuchen Sie es später erneut.',
    ], 503);
}

if (!$notificationSent) {
    jsonResponse([
        'success' => true,
        'message' => 'Ihre Nachricht wurde gespeichert. ' . ($mailError ?: 'Die E-Mail-Benachrichtigung konnte nicht gesendet werden.'),
        'email_error' => true,
    ]);
}

jsonResponse([
    'success' => true,
    'message' => $type === 'membership'
        ? 'Vielen Dank für Ihre Beitrittsanfrage! Wir melden uns bei Ihnen.'
        : 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.',
    'confirmation_sent' => !$isAnonymous && !empty($email) ? $confirmationSent : null,
]);
