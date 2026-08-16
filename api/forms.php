<?php
/**
 * WKC – Dynamic Forms API
 * Admin CRUD for form definitions + public form rendering/submission.
 */

ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$db = getDB();

const FORM_FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'signature', 'heading', 'divider'];

function escForm(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireAdminUser(): array {
    $user = requireAuth();
    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse(['error' => 'Nur Administratoren können Formulare verwalten.'], 403);
    }
    return $user;
}

function normalizeFormSlug(string $slug): string {
    $slug = mb_strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9\-_\s]/', '', $slug);
    $slug = preg_replace('/[\s_]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim((string) $slug, '-');
}

function normalizeFormPath(string $path): string {
    $path = trim($path);
    $path = trim($path, '/');
    $path = preg_replace('~[^a-zA-Z0-9\-_/]~', '-', $path);
    $path = preg_replace('~/{2,}~', '/', (string) $path);
    return strtolower((string) $path);
}

function sanitizeFieldName(string $name): string {
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9_]/', '_', $name);
    $name = preg_replace('/_+/', '_', (string) $name);
    $name = trim((string) $name, '_');
    if ($name === '') {
        return '';
    }
    if (!preg_match('/^[a-z]/', $name)) {
        $name = 'field_' . $name;
    }
    return $name;
}

function parseRecipients(string $raw): array {
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $email = trim((string) $part);
        if ($email === '') {
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Ungültige Empfänger-E-Mail-Adresse: ' . $email], 400);
        }
        $emails[] = mb_strtolower($email);
    }
    $emails = array_values(array_unique($emails));
    if (!$emails) {
        jsonResponse(['error' => 'Mindestens eine Empfänger-E-Mail-Adresse ist erforderlich.'], 400);
    }
    return $emails;
}

function decodeFieldOptions(string $type, array $field): array {
    $options = [];
    $layoutWidth = strtolower(trim((string) ($field['layoutWidth'] ?? 'full')));
    $options['layoutWidth'] = in_array($layoutWidth, ['full', 'half'], true) ? $layoutWidth : 'full';

    if ($type === 'select') {
        $rawOptions = $field['selectOptions'] ?? [];
        if (is_string($rawOptions)) {
            $rawOptions = preg_split('/\r\n|\r|\n|,/', $rawOptions) ?: [];
        }
        if (!is_array($rawOptions)) {
            $rawOptions = [];
        }
        $values = [];
        foreach ($rawOptions as $option) {
            $item = trim((string) $option);
            if ($item !== '') {
                $values[] = $item;
            }
        }
        $values = array_values(array_unique($values));
        if (!$values) {
            jsonResponse(['error' => 'Dropdown-Felder benötigen mindestens eine Option.'], 400);
        }
        $options['values'] = $values;
    }
    if ($type === 'checkbox') {
        $checkboxText = trim((string) ($field['checkboxText'] ?? ''));
        $options['checkboxText'] = $checkboxText !== '' ? $checkboxText : 'Ich bestätige diese Angabe.';
    }
    if ($type === 'file') {
        $accept = trim((string) ($field['accept'] ?? ''));
        $maxSizeMb = max(1, min(20, (int) ($field['maxSizeMb'] ?? 10)));
        $options['accept'] = $accept !== '' ? $accept : '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.csv,.zip';
        $options['maxSizeMb'] = $maxSizeMb;
    }
    return $options;
}

function decodeAndValidateFields(string $fieldsJson): array {
    $decoded = json_decode($fieldsJson, true);
    if (!is_array($decoded)) {
        jsonResponse(['error' => 'Felder-Konfiguration ist kein gültiges JSON.'], 400);
    }

    $result = [];
    $usedNames = [];

    foreach ($decoded as $idx => $field) {
        if (!is_array($field)) {
            continue;
        }
        $type = trim((string) ($field['type'] ?? ''));
        if (!in_array($type, FORM_FIELD_TYPES, true)) {
            jsonResponse(['error' => 'Ungültiger Feldtyp bei Eintrag ' . ($idx + 1) . '.'], 400);
        }

        $label = trim((string) ($field['label'] ?? ''));
        $placeholder = trim((string) ($field['placeholder'] ?? ''));
        $helpText = trim((string) ($field['helpText'] ?? ''));
        $required = !empty($field['required']) ? 1 : 0;

        $name = '';
        if (!in_array($type, ['heading', 'divider'], true)) {
            $name = sanitizeFieldName((string) ($field['name'] ?? ''));
            if ($name === '') {
                $fallback = sanitizeFieldName($label);
                $name = $fallback !== '' ? $fallback : ('field_' . ($idx + 1));
            }

            $baseName = $name;
            $suffix = 1;
            while (in_array($name, $usedNames, true)) {
                $suffix++;
                $name = $baseName . '_' . $suffix;
            }
            $usedNames[] = $name;
        } else {
            $required = 0;
        }

        if (in_array($type, ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'signature', 'heading'], true) && $label === '') {
            jsonResponse(['error' => 'Bitte hinterlegen Sie ein Label/Titel für Feld ' . ($idx + 1) . '.'], 400);
        }

        $options = decodeFieldOptions($type, $field);
        if (in_array($type, ['heading', 'divider'], true)) {
            $options['layoutWidth'] = 'full';
        }

        $result[] = [
            'type' => $type,
            'name' => $name,
            'label' => $label,
            'placeholder' => $placeholder,
            'helpText' => $helpText,
            'required' => $required,
            'options' => $options,
            'sortOrder' => $idx,
        ];
    }

    if (!$result) {
        jsonResponse(['error' => 'Bitte mindestens ein Formularfeld anlegen.'], 400);
    }

    $inputCount = 0;
    foreach ($result as $field) {
        if (!in_array($field['type'], ['heading', 'divider'], true)) {
            $inputCount++;
        }
    }
    if ($inputCount === 0) {
        jsonResponse(['error' => 'Das Formular benötigt mindestens ein Eingabefeld.'], 400);
    }

    return $result;
}

function loadFormBySlug(PDO $db, string $slug, bool $onlyActive): ?array {
    $sql = "SELECT * FROM forms WHERE slug = :slug";
    if ($onlyActive) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $form = $stmt->fetch();
    if (!$form) {
        return null;
    }

    $fieldStmt = $db->prepare("SELECT * FROM form_fields WHERE form_id = :form_id ORDER BY sort_order ASC, id ASC");
    $fieldStmt->execute([':form_id' => $form['id']]);
    $fields = $fieldStmt->fetchAll();
    foreach ($fields as &$field) {
        $field['options'] = json_decode((string) ($field['options_json'] ?? '{}'), true);
        if (!is_array($field['options'])) {
            $field['options'] = [];
        }
        $field['required'] = (int) ($field['is_required'] ?? 0) === 1;
    }
    $form['fields'] = $fields;
    return $form;
}

function ensureDirectory(string $path): void {
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Verzeichnis konnte nicht erstellt werden: ' . $path);
    }
}

function allowedFileTypes(): array {
    return [
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function parseAcceptedFileExtensions(string $accept): array {
    $accept = trim($accept);
    if ($accept === '') {
        return [];
    }

    $allowedMimes = allowedFileTypes();
    $parts = preg_split('/\s*,\s*/', $accept) ?: [];
    $extensions = [];

    foreach ($parts as $part) {
        $value = strtolower(trim((string) $part));
        if ($value === '') {
            continue;
        }

        if ($value === 'image/*') {
            $extensions = array_merge($extensions, ['jpg', 'png', 'webp']);
            continue;
        }

        if (str_starts_with($value, '.')) {
            $ext = preg_replace('/[^a-z0-9]/', '', substr($value, 1));
            if ($ext !== '') {
                $extensions[] = $ext;
            }
            continue;
        }

        if (isset($allowedMimes[$value])) {
            $extensions[] = $allowedMimes[$value];
        }
    }

    return array_values(array_unique($extensions));
}

function saveUploadedFormFile(array $file, string $formSlug, string $fieldName, int $maxBytes, array $acceptedExtensions = []): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Datei-Upload fehlgeschlagen.');
    }
    if (($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('Die hochgeladene Datei ist leer.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Datei ist zu groß. Maximal ' . floor($maxBytes / 1024 / 1024) . ' MB erlaubt.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $allowed = allowedFileTypes();
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Dateityp nicht erlaubt: ' . $mime);
    }
    $extension = $allowed[$mime];
    if ($acceptedExtensions && !in_array($extension, $acceptedExtensions, true)) {
        throw new RuntimeException('Dateityp nicht im Feld erlaubt. Erlaubt: ' . implode(', ', $acceptedExtensions));
    }

    $subDir = __DIR__ . '/../uploads/forms/' . $formSlug;
    ensureDirectory($subDir);

    $safeName = sanitizeFieldName($fieldName);
    if ($safeName === '') {
        $safeName = 'file';
    }
    $random = bin2hex(random_bytes(8));
    $fileName = $safeName . '-' . date('YmdHis') . '-' . $random . '.' . $extension;
    $target = $subDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    return [
        'field' => $fieldName,
        'relative_path' => '/uploads/forms/' . $formSlug . '/' . $fileName,
        'full_path' => $target,
        'original_name' => basename((string) ($file['name'] ?? $fileName)),
        'mime' => $mime,
        'size' => (int) $file['size'],
    ];
}

function saveSignatureImage(string $dataUrl, string $formSlug, string $fieldName): array {
    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
        throw new RuntimeException('Ungültiges Signaturformat.');
    }
    $binary = base64_decode($matches[1], true);
    if ($binary === false || strlen($binary) === 0) {
        throw new RuntimeException('Signatur konnte nicht verarbeitet werden.');
    }
    if (strlen($binary) > 4 * 1024 * 1024) {
        throw new RuntimeException('Signatur ist zu groß.');
    }

    $subDir = __DIR__ . '/../uploads/forms/' . $formSlug . '/signatures';
    ensureDirectory($subDir);

    $safeName = sanitizeFieldName($fieldName);
    if ($safeName === '') {
        $safeName = 'signature';
    }
    $fileName = $safeName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.png';
    $target = $subDir . '/' . $fileName;
    if (file_put_contents($target, $binary) === false) {
        throw new RuntimeException('Signaturdatei konnte nicht gespeichert werden.');
    }

    return [
        'field' => $fieldName,
        'relative_path' => '/uploads/forms/' . $formSlug . '/signatures/' . $fileName,
        'full_path' => $target,
        'original_name' => $safeName . '-signature.png',
        'mime' => 'image/png',
        'size' => strlen($binary),
    ];
}

function hasCustomSmtpConfig(array $form): bool {
    foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from'] as $key) {
        if (trim((string) ($form[$key] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function createMailerForForm(array $form): PHPMailer {
    if (!hasCustomSmtpConfig($form)) {
        return createMailer();
    }

    $host = trim((string) ($form['smtp_host'] ?? ''));
    $port = (int) ($form['smtp_port'] ?? 0);
    $user = trim((string) ($form['smtp_user'] ?? ''));
    $pass = trim((string) ($form['smtp_pass'] ?? ''));
    $from = trim((string) ($form['smtp_from'] ?? ''));
    $fromName = trim((string) ($form['smtp_from_name'] ?? ''));
    $secure = strtolower(trim((string) ($form['smtp_secure'] ?? 'tls')));

    if ($host === '' || $port <= 0 || $user === '' || $pass === '' || $from === '') {
        throw new RuntimeException('Formular-Mailkonfiguration unvollständig. Für individuelles SMTP bitte Host, Port, Benutzer, Passwort und Absender setzen.');
    }
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Formular-Mailkonfiguration enthält keine gültige Absenderadresse.');
    }
    if (!in_array($secure, ['tls', 'ssl'], true)) {
        throw new RuntimeException('Formular-Mailkonfiguration: SMTP-Sicherheit muss "tls" oder "ssl" sein.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $port;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);
    $mail->setFrom($from, $fromName !== '' ? $fromName : SITE_NAME);
    $mail->addReplyTo($from, $fromName !== '' ? $fromName : SITE_NAME);

    return $mail;
}

function renderSubmissionEmailBody(array $form, array $submittedFields, array $attachments): string {
    $rows = '';
    foreach ($submittedFields as $field) {
        $label = escForm((string) ($field['label'] ?? $field['name'] ?? 'Feld'));
        $value = $field['value'] ?? '';
        if (is_bool($value)) {
            $valueDisplay = $value ? 'Ja' : 'Nein';
        } elseif (is_array($value)) {
            $valueDisplay = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $valueDisplay = (string) $value;
        }
        $rows .= '<tr>'
            . '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700; background:#f9fafb; width:35%;">' . $label . '</td>'
            . '<td style="padding:10px 12px; border:1px solid #e5e7eb;">' . nl2br(escForm($valueDisplay)) . '</td>'
            . '</tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="2" style="padding:10px 12px; border:1px solid #e5e7eb;">Keine Felder übermittelt.</td></tr>';
    }

    $attachmentList = '';
    if ($attachments) {
        $attachmentList .= '<ul style="margin:10px 0 0; padding-left:18px;">';
        foreach ($attachments as $attachment) {
            $attachmentList .= '<li>' . escForm((string) ($attachment['original_name'] ?? basename((string) ($attachment['relative_path'] ?? '')))) . '</li>';
        }
        $attachmentList .= '</ul>';
    }

    $content = '<p style="margin:0 0 16px;">Es wurde ein neues Formular eingereicht.</p>'
        . '<p style="margin:0 0 16px;"><strong>Formular:</strong> ' . escForm((string) $form['title']) . ' (<code>' . escForm((string) $form['slug']) . '</code>)</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse; margin:8px 0 16px;">'
        . $rows
        . '</table>'
        . ($attachmentList !== '' ? '<p style="margin:0; font-weight:700;">Anhänge:</p>' . $attachmentList : '');

    return emailTemplate('Neue Formular-Einsendung', $content, 'Zum Backend', SITE_URL . '/admin/formulare.php');
}

function sendFormSubmissionMail(array $form, array $submittedFields, array $attachments): bool {
    try {
        $mail = createMailerForForm($form);
        $recipients = parseRecipients((string) ($form['email_recipients'] ?? ''));
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }

        $replyEmail = null;
        $replyName = null;
        foreach ($submittedFields as $field) {
            if (($field['type'] ?? '') === 'email' && !empty($field['value']) && filter_var($field['value'], FILTER_VALIDATE_EMAIL)) {
                $replyEmail = (string) $field['value'];
                $replyName = (string) ($field['label'] ?? '');
                break;
            }
        }
        if ($replyEmail !== null) {
            $mail->clearReplyTos();
            $mail->addReplyTo($replyEmail, $replyName ?: '');
        }

        $templateSubject = trim((string) ($form['email_subject'] ?? ''));
        if ($templateSubject === '') {
            $templateSubject = 'Neue Formular-Einsendung: {form_title}';
        }
        $mail->Subject = strtr($templateSubject, [
            '{form_title}' => (string) ($form['title'] ?? 'Formular'),
            '{form_slug}' => (string) ($form['slug'] ?? ''),
            '{date}' => date('d.m.Y'),
            '{time}' => date('H:i'),
        ]);

        $mail->Body = renderSubmissionEmailBody($form, $submittedFields, $attachments);
        $mail->AltBody = 'Neue Formular-Einsendung: ' . (string) ($form['title'] ?? 'Formular');
        foreach ($submittedFields as $field) {
            $value = $field['value'] ?? '';
            if (is_bool($value)) {
                $value = $value ? 'Ja' : 'Nein';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $mail->AltBody .= "\n- " . (string) ($field['label'] ?? $field['name'] ?? 'Feld') . ': ' . (string) $value;
        }

        foreach ($attachments as $attachment) {
            if (!empty($attachment['full_path']) && is_file($attachment['full_path'])) {
                $mail->addAttachment($attachment['full_path'], (string) ($attachment['original_name'] ?? basename((string) $attachment['full_path'])));
            }
        }

        $mail->send();
        if (function_exists('recordMailDeliveryAttempt')) {
            $context = 'form:' . (string) ($form['slug'] ?? 'unknown');
            recordMailDeliveryAttempt(true, $context);
        }
        return true;
    } catch (Throwable $e) {
        error_log('WKC dynamic form mail error: ' . $e->getMessage());
        $details = trim((string) $e->getMessage());
        if (function_exists('recordMailDeliveryAttempt')) {
            $context = 'form:' . (string) ($form['slug'] ?? 'unknown');
            recordMailDeliveryAttempt(false, $context, $details !== '' ? $details : null);
        }
        return false;
    }
}

function formFieldForPublicApi(array $field): array {
    return [
        'id' => (int) $field['id'],
        'type' => (string) $field['field_type'],
        'name' => (string) ($field['field_name'] ?? ''),
        'label' => (string) ($field['field_label'] ?? ''),
        'placeholder' => (string) ($field['placeholder'] ?? ''),
        'helpText' => (string) ($field['help_text'] ?? ''),
        'required' => (int) ($field['is_required'] ?? 0) === 1,
        'options' => is_array($field['options']) ? $field['options'] : [],
    ];
}

if ($method === 'GET' && $action === 'public_form') {
    $slug = normalizeFormSlug((string) ($_GET['slug'] ?? ''));
    if ($slug === '') {
        jsonResponse(['error' => 'Formular-Slug fehlt.'], 400);
    }

    $form = loadFormBySlug($db, $slug, true);
    if (!$form) {
        jsonResponse(['error' => 'Formular nicht gefunden oder deaktiviert.'], 404);
    }

    $fields = [];
    foreach ($form['fields'] as $field) {
        $fields[] = formFieldForPublicApi($field);
    }

    jsonResponse([
        'form' => [
            'id' => (int) $form['id'],
            'title' => (string) $form['title'],
            'slug' => (string) $form['slug'],
            'description' => (string) ($form['description'] ?? ''),
            'successMessage' => (string) ($form['success_message'] ?? 'Vielen Dank für Ihre Anfrage.'),
            'submitLabel' => (string) ($form['submit_label'] ?? 'Formular absenden'),
        ],
        'fields' => $fields,
    ]);
}

if ($method === 'POST' && $action === 'submit') {
    $slug = normalizeFormSlug((string) ($_GET['slug'] ?? $_POST['slug'] ?? ''));
    if ($slug === '') {
        jsonResponse(['error' => 'Formular-Slug fehlt.'], 400);
    }

    $form = loadFormBySlug($db, $slug, true);
    if (!$form) {
        jsonResponse(['error' => 'Formular nicht gefunden oder deaktiviert.'], 404);
    }

    $honeypot = trim((string) ($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        jsonResponse(['success' => true, 'message' => (string) ($form['success_message'] ?? 'Vielen Dank.')]);
    }

    $submitted = [];
    $attachments = [];
    $maxUploadBytesGlobal = 10 * 1024 * 1024;

    foreach ($form['fields'] as $field) {
        $type = (string) $field['field_type'];
        $name = (string) ($field['field_name'] ?? '');
        $label = (string) ($field['field_label'] ?? $name);
        $required = (int) ($field['is_required'] ?? 0) === 1;
        $options = is_array($field['options']) ? $field['options'] : [];

        if (in_array($type, ['heading', 'divider'], true)) {
            continue;
        }
        if ($name === '') {
            continue;
        }

        if ($type === 'checkbox') {
            $checked = isset($_POST[$name]) && in_array((string) $_POST[$name], ['1', 'true', 'on', 'yes'], true);
            if ($required && !$checked) {
                jsonResponse(['error' => $label . ' muss bestätigt werden.'], 400);
            }
            $submitted[] = ['type' => $type, 'name' => $name, 'label' => $label, 'value' => $checked];
            continue;
        }

        if ($type === 'select') {
            $value = trim((string) ($_POST[$name] ?? ''));
            $allowedValues = array_values(array_filter(array_map(static function ($item) {
                return trim((string) $item);
            }, is_array($options['values'] ?? null) ? $options['values'] : []), static function ($item) {
                return $item !== '';
            }));

            if ($required && $value === '') {
                jsonResponse(['error' => 'Bitte eine Auswahl im Feld "' . $label . '" treffen.'], 400);
            }
            if ($value !== '' && $allowedValues && !in_array($value, $allowedValues, true)) {
                jsonResponse(['error' => 'Ungültige Auswahl im Feld "' . $label . '".'], 400);
            }

            $submitted[] = ['type' => $type, 'name' => $name, 'label' => $label, 'value' => $value];
            continue;
        }

        if ($type === 'file') {
            $file = $_FILES[$name] ?? null;
            $hasFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($required && !$hasFile) {
                jsonResponse(['error' => 'Bitte eine Datei für "' . $label . '" hochladen.'], 400);
            }
            if ($hasFile) {
                $maxSizeMb = max(1, min(20, (int) ($options['maxSizeMb'] ?? 10)));
                $accept = (string) ($options['accept'] ?? '');
                $allowedExtensions = parseAcceptedFileExtensions($accept);
                $upload = saveUploadedFormFile(
                    $file,
                    (string) $form['slug'],
                    $name,
                    min($maxUploadBytesGlobal, $maxSizeMb * 1024 * 1024),
                    $allowedExtensions
                );
                $attachments[] = $upload;
                $submitted[] = [
                    'type' => $type,
                    'name' => $name,
                    'label' => $label,
                    'value' => $upload['original_name'],
                    'file' => $upload['relative_path'],
                ];
            } else {
                $submitted[] = ['type' => $type, 'name' => $name, 'label' => $label, 'value' => 'Keine Datei hochgeladen'];
            }
            continue;
        }

        if ($type === 'signature') {
            $signature = trim((string) ($_POST[$name] ?? ''));
            if ($required && $signature === '') {
                jsonResponse(['error' => 'Bitte unterschreiben Sie das Feld "' . $label . '".'], 400);
            }
            if ($signature !== '') {
                $signatureFile = saveSignatureImage($signature, (string) $form['slug'], $name);
                $attachments[] = $signatureFile;
                $submitted[] = [
                    'type' => $type,
                    'name' => $name,
                    'label' => $label,
                    'value' => 'Digitale Signatur erfasst',
                    'file' => $signatureFile['relative_path'],
                ];
            } else {
                $submitted[] = ['type' => $type, 'name' => $name, 'label' => $label, 'value' => 'Keine Signatur'];
            }
            continue;
        }

        $value = trim((string) ($_POST[$name] ?? ''));
        if ($required && $value === '') {
            jsonResponse(['error' => 'Bitte füllen Sie das Feld "' . $label . '" aus.'], 400);
        }
        if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Bitte eine gültige E-Mail-Adresse im Feld "' . $label . '" eingeben.'], 400);
        }
        if ($type === 'tel' && $value !== '' && mb_strlen($value) < 3) {
            jsonResponse(['error' => 'Die Telefonnummer im Feld "' . $label . '" ist ungültig.'], 400);
        }

        $submitted[] = ['type' => $type, 'name' => $name, 'label' => $label, 'value' => $value];
    }

    $attachmentMeta = array_map(static function (array $item): array {
        return [
            'field' => (string) ($item['field'] ?? ''),
            'path' => (string) ($item['relative_path'] ?? ''),
            'name' => (string) ($item['original_name'] ?? ''),
            'mime' => (string) ($item['mime'] ?? ''),
            'size' => (int) ($item['size'] ?? 0),
        ];
    }, $attachments);

    $insertStmt = $db->prepare("INSERT INTO form_submissions (form_id, payload_json, attachments_json, ip_address, user_agent, created_at)
        VALUES (:form_id, :payload_json, :attachments_json, :ip_address, :user_agent, datetime('now'))");
    $insertStmt->execute([
        ':form_id' => (int) $form['id'],
        ':payload_json' => json_encode($submitted, JSON_UNESCAPED_UNICODE),
        ':attachments_json' => json_encode($attachmentMeta, JSON_UNESCAPED_UNICODE),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $mailSent = sendFormSubmissionMail($form, $submitted, $attachments);
    $response = [
        'success' => true,
        'message' => (string) ($form['success_message'] ?? 'Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt.'),
        'mailDelivered' => $mailSent,
    ];
    if (!$mailSent) {
        $response['warning'] = 'Ihre Eingabe wurde gespeichert, konnte aber nicht per E-Mail weitergeleitet werden. Bitte kontaktieren Sie den Administrator.';
    }

    jsonResponse($response, 201);
}

if ($method === 'GET' && $action === 'list') {
    requireAdminUser();
    $stmt = $db->query("
        SELECT f.id, f.title, f.slug, f.target_path, f.is_active, f.updated_at,
               (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id = f.id) AS submissions_count
        FROM forms f
        ORDER BY f.updated_at DESC, f.id DESC
    ");
    jsonResponse(['forms' => $stmt->fetchAll()]);
}

if ($method === 'GET' && $action === 'detail') {
    requireAdminUser();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Formular-ID fehlt.'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM forms WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $form = $stmt->fetch();
    if (!$form) {
        jsonResponse(['error' => 'Formular nicht gefunden.'], 404);
    }

    $fieldStmt = $db->prepare("SELECT * FROM form_fields WHERE form_id = :form_id ORDER BY sort_order ASC, id ASC");
    $fieldStmt->execute([':form_id' => $id]);
    $fields = $fieldStmt->fetchAll();

    foreach ($fields as &$field) {
        $options = json_decode((string) ($field['options_json'] ?? '{}'), true);
        if (!is_array($options)) {
            $options = [];
        }
        $field = [
            'id' => (int) $field['id'],
            'type' => (string) $field['field_type'],
            'name' => (string) ($field['field_name'] ?? ''),
            'label' => (string) ($field['field_label'] ?? ''),
            'placeholder' => (string) ($field['placeholder'] ?? ''),
            'helpText' => (string) ($field['help_text'] ?? ''),
            'required' => (int) ($field['is_required'] ?? 0) === 1,
            'layoutWidth' => (($options['layoutWidth'] ?? 'full') === 'half') ? 'half' : 'full',
            'selectOptions' => implode("\n", is_array($options['values'] ?? null) ? $options['values'] : []),
            'checkboxText' => (string) ($options['checkboxText'] ?? ''),
            'accept' => (string) ($options['accept'] ?? ''),
            'maxSizeMb' => (int) ($options['maxSizeMb'] ?? 10),
        ];
    }

    jsonResponse([
        'form' => [
            'id' => (int) $form['id'],
            'title' => (string) $form['title'],
            'slug' => (string) $form['slug'],
            'description' => (string) ($form['description'] ?? ''),
            'target_path' => (string) ($form['target_path'] ?? ''),
            'success_message' => (string) ($form['success_message'] ?? ''),
            'submit_label' => (string) ($form['submit_label'] ?? ''),
            'is_active' => (int) ($form['is_active'] ?? 0) === 1,
            'email_recipients' => (string) ($form['email_recipients'] ?? ''),
            'email_subject' => (string) ($form['email_subject'] ?? ''),
            'smtp_host' => (string) ($form['smtp_host'] ?? ''),
            'smtp_port' => (string) ($form['smtp_port'] ?? ''),
            'smtp_secure' => (string) ($form['smtp_secure'] ?? ''),
            'smtp_user' => (string) ($form['smtp_user'] ?? ''),
            'smtp_pass' => (string) ($form['smtp_pass'] ?? ''),
            'smtp_from' => (string) ($form['smtp_from'] ?? ''),
            'smtp_from_name' => (string) ($form['smtp_from_name'] ?? ''),
        ],
        'fields' => $fields,
    ]);
}

if ($method === 'GET' && $action === 'submissions') {
    requireAdminUser();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Formular-ID fehlt.'], 400);
    }
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $stmt = $db->prepare("
        SELECT id, payload_json, attachments_json, ip_address, user_agent, created_at
        FROM form_submissions
        WHERE form_id = :form_id
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':form_id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    foreach ($submissions as &$submission) {
        $submission['payload'] = json_decode((string) ($submission['payload_json'] ?? '[]'), true);
        if (!is_array($submission['payload'])) {
            $submission['payload'] = [];
        }
        $submission['attachments'] = json_decode((string) ($submission['attachments_json'] ?? '[]'), true);
        if (!is_array($submission['attachments'])) {
            $submission['attachments'] = [];
        }
    }
    jsonResponse(['submissions' => $submissions]);
}

if ($method === 'POST' && ($action === 'save' || $action === '')) {
    $user = requireAdminUser();

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = normalizeFormSlug((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $targetPath = normalizeFormPath((string) ($_POST['target_path'] ?? ''));
    $successMessage = trim((string) ($_POST['success_message'] ?? ''));
    $submitLabel = trim((string) ($_POST['submit_label'] ?? ''));
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $emailRecipientsRaw = trim((string) ($_POST['email_recipients'] ?? ''));
    $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
    $fieldsJson = (string) ($_POST['fields_json'] ?? '[]');

    $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
    $smtpPortRaw = trim((string) ($_POST['smtp_port'] ?? ''));
    $smtpSecure = strtolower(trim((string) ($_POST['smtp_secure'] ?? 'tls')));
    $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
    $smtpPass = trim((string) ($_POST['smtp_pass'] ?? ''));
    $smtpFrom = trim((string) ($_POST['smtp_from'] ?? ''));
    $smtpFromName = trim((string) ($_POST['smtp_from_name'] ?? ''));

    if ($title === '') {
        jsonResponse(['error' => 'Formulartitel ist erforderlich.'], 400);
    }
    if ($slug === '') {
        jsonResponse(['error' => 'Slug ist erforderlich und darf nur Buchstaben, Zahlen und Bindestriche enthalten.'], 400);
    }
    if ($emailSubject === '') {
        jsonResponse(['error' => 'E-Mail-Betreff ist erforderlich.'], 400);
    }

    $recipients = parseRecipients($emailRecipientsRaw);
    $fields = decodeAndValidateFields($fieldsJson);

    $smtpPort = $smtpPortRaw === '' ? null : (int) $smtpPortRaw;
    $hasSmtpValues = ($smtpHost !== '' || $smtpPortRaw !== '' || $smtpUser !== '' || $smtpPass !== '' || $smtpFrom !== '' || $smtpFromName !== '');
    if ($hasSmtpValues) {
        if ($smtpHost === '' || !$smtpPort || $smtpUser === '' || $smtpPass === '' || $smtpFrom === '') {
            jsonResponse(['error' => 'Individuelle SMTP-Konfiguration unvollständig. Host, Port, Benutzer, Passwort und Absender sind erforderlich.'], 400);
        }
        if (!in_array($smtpSecure, ['tls', 'ssl'], true)) {
            jsonResponse(['error' => 'SMTP-Sicherheit muss "tls" oder "ssl" sein.'], 400);
        }
        if (!filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'SMTP-Absender ist keine gültige E-Mail-Adresse.'], 400);
        }
    } else {
        $smtpSecure = null;
    }

    $dupStmt = $db->prepare("SELECT id FROM forms WHERE slug = :slug AND (:id = 0 OR id != :id) LIMIT 1");
    $dupStmt->execute([':slug' => $slug, ':id' => $id]);
    if ($dupStmt->fetch()) {
        jsonResponse(['error' => 'Der Slug ist bereits vergeben.'], 409);
    }

    $db->beginTransaction();
    try {
        if ($id > 0) {
            $update = $db->prepare("
                UPDATE forms
                SET title = :title,
                    slug = :slug,
                    description = :description,
                    target_path = :target_path,
                    success_message = :success_message,
                    submit_label = :submit_label,
                    is_active = :is_active,
                    email_recipients = :email_recipients,
                    email_subject = :email_subject,
                    smtp_host = :smtp_host,
                    smtp_port = :smtp_port,
                    smtp_secure = :smtp_secure,
                    smtp_user = :smtp_user,
                    smtp_pass = :smtp_pass,
                    smtp_from = :smtp_from,
                    smtp_from_name = :smtp_from_name,
                    updated_at = datetime('now')
                WHERE id = :id
            ");
            $update->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':description' => $description !== '' ? $description : null,
                ':target_path' => $targetPath !== '' ? $targetPath : null,
                ':success_message' => $successMessage !== '' ? $successMessage : 'Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt.',
                ':submit_label' => $submitLabel !== '' ? $submitLabel : 'Formular absenden',
                ':is_active' => $isActive,
                ':email_recipients' => implode(',', $recipients),
                ':email_subject' => $emailSubject,
                ':smtp_host' => $hasSmtpValues ? $smtpHost : null,
                ':smtp_port' => $hasSmtpValues ? $smtpPort : null,
                ':smtp_secure' => $hasSmtpValues ? $smtpSecure : null,
                ':smtp_user' => $hasSmtpValues ? $smtpUser : null,
                ':smtp_pass' => $hasSmtpValues ? $smtpPass : null,
                ':smtp_from' => $hasSmtpValues ? $smtpFrom : null,
                ':smtp_from_name' => $hasSmtpValues ? ($smtpFromName !== '' ? $smtpFromName : null) : null,
                ':id' => $id,
            ]);
            $formId = $id;
            $db->prepare("DELETE FROM form_fields WHERE form_id = :form_id")->execute([':form_id' => $formId]);
        } else {
            $insert = $db->prepare("
                INSERT INTO forms (
                    title, slug, description, target_path, success_message, submit_label, is_active,
                    email_recipients, email_subject, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, smtp_from, smtp_from_name,
                    created_by, created_at, updated_at
                )
                VALUES (
                    :title, :slug, :description, :target_path, :success_message, :submit_label, :is_active,
                    :email_recipients, :email_subject, :smtp_host, :smtp_port, :smtp_secure, :smtp_user, :smtp_pass, :smtp_from, :smtp_from_name,
                    :created_by, datetime('now'), datetime('now')
                )
            ");
            $insert->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':description' => $description !== '' ? $description : null,
                ':target_path' => $targetPath !== '' ? $targetPath : null,
                ':success_message' => $successMessage !== '' ? $successMessage : 'Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt.',
                ':submit_label' => $submitLabel !== '' ? $submitLabel : 'Formular absenden',
                ':is_active' => $isActive,
                ':email_recipients' => implode(',', $recipients),
                ':email_subject' => $emailSubject,
                ':smtp_host' => $hasSmtpValues ? $smtpHost : null,
                ':smtp_port' => $hasSmtpValues ? $smtpPort : null,
                ':smtp_secure' => $hasSmtpValues ? $smtpSecure : null,
                ':smtp_user' => $hasSmtpValues ? $smtpUser : null,
                ':smtp_pass' => $hasSmtpValues ? $smtpPass : null,
                ':smtp_from' => $hasSmtpValues ? $smtpFrom : null,
                ':smtp_from_name' => $hasSmtpValues ? ($smtpFromName !== '' ? $smtpFromName : null) : null,
                ':created_by' => (int) $user['id'],
            ]);
            $formId = (int) $db->lastInsertId();
        }

        $insertField = $db->prepare("
            INSERT INTO form_fields (
                form_id, field_type, field_name, field_label, placeholder, help_text, options_json, is_required, sort_order, created_at, updated_at
            )
            VALUES (
                :form_id, :field_type, :field_name, :field_label, :placeholder, :help_text, :options_json, :is_required, :sort_order, datetime('now'), datetime('now')
            )
        ");

        foreach ($fields as $field) {
            $insertField->execute([
                ':form_id' => $formId,
                ':field_type' => $field['type'],
                ':field_name' => $field['name'] !== '' ? $field['name'] : null,
                ':field_label' => $field['label'] !== '' ? $field['label'] : null,
                ':placeholder' => $field['placeholder'] !== '' ? $field['placeholder'] : null,
                ':help_text' => $field['helpText'] !== '' ? $field['helpText'] : null,
                ':options_json' => json_encode($field['options'], JSON_UNESCAPED_UNICODE),
                ':is_required' => (int) $field['required'],
                ':sort_order' => (int) $field['sortOrder'],
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('WKC form save error: ' . $e->getMessage());
        jsonResponse(['error' => 'Formular konnte nicht gespeichert werden.'], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Formular gespeichert.',
        'id' => $formId,
        'slug' => $slug,
    ]);
}

if ($method === 'DELETE') {
    requireAdminUser();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Formular-ID fehlt.'], 400);
    }

    $stmt = $db->prepare("DELETE FROM forms WHERE id = :id");
    $stmt->execute([':id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Formular gelöscht.']);
}

jsonResponse(['error' => 'Ungültige Anfrage'], 400);
