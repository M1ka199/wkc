<?php
/**
 * WKC – Mail Helper
 * Versand von E-Mails via PHPMailer mit modernen HTML-Vorlagen.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================
// SVG Logo (inline, simplified for emails)
// ============================
function getEmailLogoSvg(): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 746.81 204.3" width="200" height="55">
  <path fill="#303031" d="M273.67,43.02h33.65l23.65,77.4,23.97-77.4h26.83l24.44,77.4,23.17-77.4h33.33l-38.89,124.42h-32.22l-23.18-75.28-22.7,75.28h-32.54l-39.52-124.42Z"/>
  <path fill="#303031" d="M506.63,71.44h-41.86l8.88-28.42,79.17.15-47.46,96h47.14v28.27h-93.18l47.3-95.99Z"/>
  <path fill="#7c3aed" d="M557.76,43.02h33.65l23.65,77.4,23.97-77.4h26.83l24.44,77.4,23.17-77.4h33.33l-38.89,124.42h-32.22l-23.17-75.28-22.7,75.28h-32.54l-39.53-124.42Z"/>
  <path fill="#7c3aed" d="M144.06,152.17c68.59,3.23,95.75,43.22,103.39,38.44,6.76-4.23-.15-23.99-29.36-41.86-17.93-10.96-41.46-16.34-69.73-16.34-7.04,0-14.45.35-22.03,1.04l-5.38.49,51.22-97.19c-18.63-12.24-39.16-18.7-59.52-18.7-23.51,0-44.8,8.95-59.96,25.21-16.31,17.5-25.25,42.89-25.97,73.62l82.44-26.41-23.95,73.61c19.02-8,40.54-11.92,58.86-11.92Z"/>
  <path fill="#7c3aed" d="M66.22,134.97L.24,153.87l-.12-3.84C-1.37,102.73,11.47,63.68,37.24,37.09,60.42,13.17,92.93,0,128.78,0c30.45,0,59.45,10.15,77.58,27.14l1.65,1.54-42.6,79.01c33.38,2.37,56.03,14.65,69.63,25.32,40.91,32.13,32.64,70.86,10.04,71.27-8.44.15-19.34-5.11-30.2-11.14-16.66-9.25-33.01-15.86-63.61-16.39-48.81-.85-102.07,25.44-102.07,25.44l17.03-67.23Z"/>
</svg>';
}

// ============================
// Create PHPMailer instance
// ============================
function mailConfigurationError(): ?string {
    $smtp = getAppSetting('smtp', []);
    $missing = [];
    foreach (['host', 'port', 'user', 'pass', 'from'] as $key) {
        if (empty($smtp[$key])) {
            $missing[] = $key;
        }
    }
    if ($missing) {
        return 'E-Mail-Versand ist nicht konfiguriert. Fehlende SMTP-Einstellungen: ' . implode(', ', $missing) . '.';
    }
    $secure = strtolower($smtp['secure'] ?? 'tls');
    if (!in_array($secure, ['tls', 'ssl'], true)) {
        return 'E-Mail-Versand ist nicht konfiguriert. Secure muss "tls" oder "ssl" sein.';
    }
    return null;
}

function createMailer(): PHPMailer {
    $configurationError = mailConfigurationError();
    if ($configurationError !== null) {
        throw new RuntimeException($configurationError);
    }

    $smtp = getAppSetting('smtp', []);
    $host = trim((string) ($smtp['host'] ?? ''));
    $port = (int) ($smtp['port'] ?? 0);
    $user = trim((string) ($smtp['user'] ?? ''));
    $pass = trim((string) ($smtp['pass'] ?? ''));
    $from = trim((string) ($smtp['from'] ?? ''));
    $fromName = trim((string) ($smtp['from_name'] ?? ''));
    $secure = strtolower(trim((string) ($smtp['secure'] ?? 'tls')));

    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    // From
    $mail->setFrom($from, $fromName !== '' ? $fromName : SITE_NAME);
    $mail->addReplyTo($from, $fromName !== '' ? $fromName : SITE_NAME);
    $mail->isHTML(true);

    return $mail;
}

function recordMailDeliveryAttempt(bool $success, string $context, ?string $errorMessage = null): void {
    $status = getAppSetting('mail_delivery_status', []);
    if (!is_array($status)) {
        $status = [];
    }

    $now = date('c');
    $status['last_attempt_at'] = $now;
    $status['last_context'] = $context;
    $status['last_success'] = $success;
    if ($success) {
        $status['last_success_at'] = $now;
        $status['last_error'] = null;
    } else {
        $status['last_error'] = [
            'message' => $errorMessage ?? 'Unbekannter Mailfehler',
            'at' => $now,
            'context' => $context,
        ];
    }

    setAppSetting('mail_delivery_status', $status);
}

function getMailDeliveryStatus(): array {
    $smtp = getAppSetting('smtp', []);
    if (!is_array($smtp)) {
        $smtp = [];
    }
    $status = getAppSetting('mail_delivery_status', []);
    if (!is_array($status)) {
        $status = [];
    }

    return [
        'config_error' => mailConfigurationError(),
        'smtp' => [
            'host' => trim((string) ($smtp['host'] ?? '')),
            'port' => (int) ($smtp['port'] ?? 0),
            'secure' => strtolower(trim((string) ($smtp['secure'] ?? 'tls'))),
            'from' => trim((string) ($smtp['from'] ?? '')),
            'user' => trim((string) ($smtp['user'] ?? '')),
            'contact_recipient' => trim((string) ($smtp['contact_recipient'] ?? '')),
        ],
        'delivery' => [
            'last_attempt_at' => (string) ($status['last_attempt_at'] ?? ''),
            'last_success' => (bool) ($status['last_success'] ?? false),
            'last_success_at' => (string) ($status['last_success_at'] ?? ''),
            'last_context' => (string) ($status['last_context'] ?? ''),
            'last_error' => is_array($status['last_error'] ?? null) ? $status['last_error'] : null,
        ],
    ];
}

function probeMailTransport(): array {
    try {
        $mail = createMailer();
        $mail->Timeout = 10;
        $connected = $mail->smtpConnect();
        if (!$connected) {
            $details = trim((string) $mail->ErrorInfo);
            throw new RuntimeException($details !== '' ? $details : 'SMTP-Verbindung konnte nicht aufgebaut werden.');
        }
        $mail->smtpClose();
        return [
            'ok' => true,
            'message' => 'SMTP-Verbindung erfolgreich aufgebaut.',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

// ============================
// Send Email (wrapper)
// ============================
function sendMail(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $replyToName = null): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($to);
        if ($replyTo) {
            $mail->clearReplyTos();
            $mail->addReplyTo($replyTo, $replyToName ?? '');
        }
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'], "\n", $htmlBody));
        $mail->send();
        recordMailDeliveryAttempt(true, 'global_mail');
        return true;
    } catch (Throwable $e) {
        error_log("WKC Mail Error: " . $e->getMessage());
        $details = trim((string) $e->getMessage());
        if (isset($mail)) {
            error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
            $errorInfo = trim((string) $mail->ErrorInfo);
            if ($errorInfo !== '') {
                $details = $details !== '' ? ($details . ' | ' . $errorInfo) : $errorInfo;
            }
        }
        recordMailDeliveryAttempt(false, 'global_mail', $details !== '' ? $details : null);
        return false;
    }
}

// ============================
// Base Email Template
// ============================
function emailTemplate(string $title, string $content, ?string $buttonText = null, ?string $buttonUrl = null, ?string $footer = null): string {
    $logoSvg = getEmailLogoSvg();
    $siteUrl = SITE_URL;
    $year = date('Y');

    $buttonHtml = '';
    if ($buttonText && $buttonUrl) {
        $buttonHtml = '
        <tr>
            <td align="center" style="padding: 30px 0 10px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td style="border-radius: 12px; background-color: #7c3aed;">
                            <a href="' . htmlspecialchars($buttonUrl) . '" target="_blank" style="display: inline-block; padding: 16px 36px; font-family: \'Public Sans\', Arial, sans-serif; font-size: 15px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px; letter-spacing: 0.3px;">
                                ' . htmlspecialchars($buttonText) . '
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
    }

    $footerHtml = $footer ? '<tr><td style="padding: 20px 0 0; font-size: 13px; color: #9ca3af; line-height: 1.6;">' . $footer . '</td></tr>' : '';

    return '<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap");
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f8f7; font-family: \'Public Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <!-- Preheader (hidden text for inbox preview) -->
    <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: #f5f8f7;">' . htmlspecialchars($title) . '</div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f8f7;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; width: 100%;">

                    <!-- Logo Header -->
                    <tr>
                        <td align="center" style="padding: 0 0 32px;">
                            <a href="' . htmlspecialchars($siteUrl) . '" target="_blank" style="text-decoration: none;">
                                ' . $logoSvg . '
                            </a>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 16px; border: 1px solid rgba(0, 140, 90, 0.08); box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);">
                            <!-- Green top accent -->
                            <div style="height: 4px; background: linear-gradient(90deg, rgba(0, 140, 90, 0.2), #7c3aed, rgba(0, 140, 90, 0.2)); border-radius: 16px 16px 0 0;"></div>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 40px 40px 36px;">
                                        <!-- Title -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px; padding-bottom: 8px; line-height: 1.3;">
                                                    ' . htmlspecialchars($title) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 24px;">
                                                    <div style="width: 48px; height: 3px; background-color: #7c3aed; border-radius: 2px;"></div>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Content -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="font-size: 15px; line-height: 1.7; color: #4b5563;">
                                                    ' . $content . '
                                                </td>
                                            </tr>
                                            ' . $buttonHtml . '
                                            ' . $footerHtml . '
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 32px 16px; text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #9ca3af; font-weight: 600;">
                                WKC e.V.
                            </p>
                            <p style="margin: 0 0 12px; font-size: 12px; color: #d1d5db;">
                                37199 Wulften am Harz &bull; info@zukunft-wulften.de
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #d1d5db;">
                                &copy; 2021–' . $year . ' WKC. Alle Rechte vorbehalten.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// ============================
// Specific Email Builders
// ============================

/**
 * Kontaktformular-Benachrichtigung â†’ an info@zukunft-wulften.de
 */
function emailContactNotification(string $name, string $email, string $subject, string $message, bool $isAnonymous): string {
    $senderInfo = $isAnonymous
        ? '<span style="color: #6b7280; font-style: italic;">Anonym gesendet</span>'
        : '<strong>' . htmlspecialchars($name) . '</strong>' . ($email ? ' (<a href="mailto:' . htmlspecialchars($email) . '" style="color: #7c3aed;">' . htmlspecialchars($email) . '</a>)' : '');

    $content = '
        <p style="margin: 0 0 20px;">Eine neue Nachricht wurde über das Kontaktformular auf der Website eingereicht:</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
            <tr>
                <td style="padding: 20px 24px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Absender</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 16px; font-size: 15px; color: #111827;">' . $senderInfo . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Betreff</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 16px; font-size: 15px; color: #111827; font-weight: 600;">' . htmlspecialchars($subject) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Nachricht</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-size: 15px; color: #374151; line-height: 1.7; white-space: pre-wrap;">' . nl2br(htmlspecialchars($message)) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

    return emailTemplate('Neue Kontaktanfrage', $content, 'Im Backend anzeigen', SITE_URL . '/admin/nachrichten.php');
}

/**
 * Kontaktformular-Bestätigung â†’ an Sender
 */
function emailContactConfirmation(string $name, string $subject): string {
    $greeting = !empty($name) && $name !== 'Anonym' ? 'Hallo ' . htmlspecialchars($name) . ',' : 'Hallo,';
    $content = '
        <p style="margin: 0 0 16px;">' . $greeting . '</p>
        <p style="margin: 0 0 16px;">vielen Dank für Ihre Nachricht zum Thema <strong>„' . htmlspecialchars($subject) . '"</strong>. Wir haben Ihre Anfrage erhalten und werden uns so schnell wie möglich bei Ihnen melden.</p>
        <p style="margin: 0 0 8px;">Ihre WKC</p>';

    return emailTemplate('Ihre Nachricht wurde empfangen', $content, 'Unsere Website besuchen', SITE_URL);
}

/**
 * Beitrittsanfrage-Benachrichtigung â†’ an info@zukunft-wulften.de
 */
function emailMembershipNotification(string $name, string $email, string $phone, string $message): string {
    $content = '
        <p style="margin: 0 0 20px;">Eine neue Beitrittsanfrage wurde über die Website eingereicht:</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ecfdf5; border-radius: 12px; border: 1px solid #a7f3d0;">
            <tr>
                <td style="padding: 20px 24px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px;">Name</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 16px; font-size: 15px; color: #111827; font-weight: 600;">' . htmlspecialchars($name) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px;">E-Mail</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 16px; font-size: 15px; color: #111827;"><a href="mailto:' . htmlspecialchars($email) . '" style="color: #7c3aed;">' . htmlspecialchars($email) . '</a></td>
                        </tr>
                        ' . ($phone !== '' ? '
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px;">Telefon</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 16px; font-size: 15px; color: #111827;">' . htmlspecialchars($phone) . '</td>
                        </tr>' : '') . '
                        <tr>
                            <td style="padding: 8px 0; font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px;">Nachricht</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-size: 15px; color: #374151; line-height: 1.7;">' . nl2br(htmlspecialchars($message ?: 'Keine Nachricht hinterlassen.')) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

    return emailTemplate('Neue Beitrittsanfrage', $content, 'Im Backend anzeigen', SITE_URL . '/admin/nachrichten.php');
}

/**
 * Beitrittsanfrage-Bestätigung â†’ an Bewerber
 */
function emailMembershipConfirmation(string $name): string {
    $content = '
        <p style="margin: 0 0 16px;">Hallo ' . htmlspecialchars($name) . ',</p>
        <p style="margin: 0 0 16px;">vielen Dank für Ihr Interesse an der WKC! Wir haben Ihre Beitrittsanfrage erhalten und freuen uns sehr über Ihr Engagement.</p>
        <p style="margin: 0 0 16px;">Ein Mitglied unseres Vorstands wird sich in Kürze bei Ihnen melden, um die nächsten Schritte zu besprechen.</p>
        <div style="background: #f0fdf4; padding: 16px 20px; border-radius: 12px; border-left: 4px solid #7c3aed; margin: 20px 0;">
            <p style="margin: 0; font-size: 14px; color: #374151; font-style: italic;">
                „Karneval lebt vom Mitmachen. Wir freuen uns, Sie bald in unserer Gemeinschaft begrüßen zu dürfen."
            </p>
        </div>
        <p style="margin: 0 0 8px;">Herzliche Grüße,<br>Ihre WKC</p>';

    return emailTemplate('Willkommen – Ihre Beitrittsanfrage', $content, 'Mehr über uns erfahren', SITE_URL);
}

/**
 * Mitglieder-Einladung â†’ an neues Mitglied
 */
function emailMemberInvitation(string $name, string $username, string $inviteUrl): string {
    $content = '
        <p style="margin: 0 0 16px;">Hallo ' . htmlspecialchars($name) . ',</p>
        <p style="margin: 0 0 16px;">Sie wurden als Mitglied der <strong>WKC</strong> eingeladen! Ihr Benutzerkonto wurde bereits angelegt.</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; margin: 20px 0;">
            <tr>
                <td style="padding: 20px 24px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="font-size: 12px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; padding-bottom: 6px;">Ihr Benutzername</td>
                        </tr>
                        <tr>
                            <td style="font-size: 18px; color: #7c3aed; font-weight: 800; font-family: monospace, \'Public Sans\', sans-serif;">' . htmlspecialchars($username) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <p style="margin: 0 0 16px;">Klicken Sie auf den Button, um Ihr persönliches Passwort festzulegen und sich erstmalig anzumelden:</p>';

    $footer = '<p style="margin: 0; font-size: 12px; color: #9ca3af;">Dieser Link ist <strong>48 Stunden</strong> gültig. Falls der Button nicht funktioniert, kopieren Sie diesen Link: <a href="' . htmlspecialchars($inviteUrl) . '" style="color: #7c3aed; word-break: break-all;">' . htmlspecialchars($inviteUrl) . '</a></p>';

    return emailTemplate('Einladung – WKC', $content, 'Passwort festlegen', $inviteUrl, $footer);
}

/**
 * Passwort zurücksetzen â†’ an Benutzer
 */
function emailPasswordReset(string $name, string $resetUrl): string {
    $content = '
        <p style="margin: 0 0 16px;">Hallo ' . htmlspecialchars($name) . ',</p>
        <p style="margin: 0 0 16px;">Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts für das Backend der WKC gestellt.</p>
        <p style="margin: 0 0 16px;">Klicken Sie auf den folgenden Button, um ein neues Passwort festzulegen:</p>';

    $footer = '
        <p style="margin: 0 0 6px; font-size: 12px; color: #9ca3af;">Dieser Link ist <strong>1 Stunde</strong> gültig.</p>
        <p style="margin: 0 0 6px; font-size: 12px; color: #9ca3af;">Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren. Ihr Passwort bleibt unverändert.</p>
        <p style="margin: 0; font-size: 12px; color: #9ca3af;">Link: <a href="' . htmlspecialchars($resetUrl) . '" style="color: #7c3aed; word-break: break-all;">' . htmlspecialchars($resetUrl) . '</a></p>';

    return emailTemplate('Passwort zurücksetzen', $content, 'Neues Passwort festlegen', $resetUrl, $footer);
}

/**
 * Passwort-Bestätigung nach erfolgreichem Setzen
 */
function emailPasswordChanged(string $name): string {
    $content = '
        <p style="margin: 0 0 16px;">Hallo ' . htmlspecialchars($name) . ',</p>
        <p style="margin: 0 0 16px;">Ihr Passwort für das Backend der WKC wurde erfolgreich geändert.</p>
        <div style="background: #fefce8; padding: 16px 20px; border-radius: 12px; border-left: 4px solid #eab308; margin: 20px 0;">
            <p style="margin: 0; font-size: 14px; color: #854d0e;">
                Falls Sie diese Änderung nicht veranlasst haben, kontaktieren Sie bitte umgehend den Administrator unter <a href="mailto:info@zukunft-wulften.de" style="color: #7c3aed;">info@zukunft-wulften.de</a>.
            </p>
        </div>
        <p style="margin: 0 0 8px;">Ihre WKC</p>';

    return emailTemplate('Passwort geändert', $content, 'Zum Login', SITE_URL . '/admin/');
}
