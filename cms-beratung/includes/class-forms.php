<?php
/**
 * CMS Beratung – Anfrageformular der Landingpages.
 *
 * Schutz: globaler Router-Token `form_guard` (Feld csrf_token) plus seitenbezogener Einmal-Token,
 * Honeypot, optionale Sicherheitsfrage, Ratenbegrenzung (Sitzung + Datenbank), Core-Antispam,
 * Längenbegrenzung aller Felder und Header-sichere Mail-Felder.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Forms
{
    /** Maximal 5 Anfragen je 10 Minuten (Sitzung bzw. IP). */
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 600;
    private const RATE_LIMIT_ACTION = 'beratung_form';

    /** @var array<string,int> Feldlängen */
    private const FIELD_LIMITS = [
        'sender_name' => 160,
        'company' => 200,
        'sender_email' => 254,
        'phone' => 60,
        'topic' => 200,
        'message' => 10000,
    ];

    /** @return array<string,string> */
    public static function desired_services(): array
    {
        return [
            'microsoft-365-beratung' => 'Microsoft 365 Beratung',
            'copilot-readiness-check' => 'Copilot Readiness Check',
            'entra-id-security-review' => 'Entra ID Security Review',
            'sharepoint-governance' => 'SharePoint Governance',
            'microsoft-purview-beratung' => 'Microsoft Purview Beratung',
            'microsoft-defender-review' => 'Microsoft Defender Review',
            'admin-workshop' => 'Admin Workshop',
            'powershell-automatisierung' => 'PowerShell Automatisierung',
            'sonstiges' => 'Sonstiges',
        ];
    }

    /** Sicherheitsfrage für diese Landingpage aktiv? (global oder im Kontaktbereich der Seite) */
    public static function captcha_enabled(array $page): bool
    {
        $contact = is_array($page['contact'] ?? null) ? $page['contact'] : [];
        return (CMS_Beratung_Settings::all()['captcha_prepared'] ?? '0') === '1' || !empty($contact['captcha_enabled']);
    }

    /**
     * Neue Rechenaufgabe erzeugen und in der Sitzung ablegen.
     *
     * @return array{a:int,b:int}
     */
    public static function captcha_challenge(array $page): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cms_beratung_captcha'][(int) ($page['id'] ?? 0)] = $a + $b;
        }
        return ['a' => $a, 'b' => $b];
    }

    /** @param array<string,mixed> $page @return array{success:bool,message:string,errors:array<string,string>,values:array<string,string>} */
    public static function handle_submission(array $page): array
    {
        $settings = CMS_Beratung_Settings::all();
        $empty = ['success' => false, 'message' => '', 'errors' => [], 'values' => []];
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || (string) ($_POST['beratung_form_action'] ?? '') !== 'submit_request') {
            return $empty;
        }

        $values = self::posted_values($_POST);
        $errors = [];
        $pageId = (int) ($page['id'] ?? 0);
        $genericError = trim((string) ($settings['error_message'] ?? '')) ?: 'Bitte prüfen Sie die markierten Felder.';

        $beratungToken = (string) ($_POST['beratung_csrf_token'] ?? '');
        if (!class_exists('CMS\\Security') || !\CMS\Security::instance()->verifyToken($beratungToken, 'beratung_form_' . $pageId)) {
            return ['success' => false, 'message' => 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'errors' => ['csrf' => 'Sicherheitsprüfung fehlgeschlagen.'], 'values' => $values];
        }

        $honeypot = trim((string) ($_POST['website'] ?? ''));
        if (($settings['honeypot_enabled'] ?? '1') === '1' && $honeypot !== '') {
            // Bots erhalten eine scheinbare Erfolgsmeldung; nichts wird gespeichert oder versendet.
            return ['success' => true, 'message' => (string) $settings['success_message'], 'errors' => [], 'values' => []];
        }

        if (!self::rate_limit_ok()) {
            return ['success' => false, 'message' => 'Es wurden zu viele Anfragen in kurzer Zeit gesendet. Bitte versuchen Sie es in einigen Minuten erneut.', 'errors' => ['rate' => 'Ratenbegrenzung.'], 'values' => $values];
        }

        if ($values['sender_name'] === '') {
            $errors['sender_name'] = 'Bitte geben Sie Ihren Namen ein.';
        }
        if ($values['sender_email'] === '' || !filter_var($values['sender_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['sender_email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        }
        if ($values['message'] === '') {
            $errors['message'] = 'Bitte geben Sie eine Nachricht ein.';
        }
        if (empty($_POST['consent'])) {
            $errors['consent'] = 'Bitte bestätigen Sie die Datenschutzhinweise.';
        }
        if ($values['desired_service'] !== '' && !array_key_exists($values['desired_service'], self::desired_services())) {
            $errors['desired_service'] = 'Bitte wählen Sie eine gültige Wunschleistung aus.';
        }
        if (self::captcha_enabled($page) && session_status() === PHP_SESSION_ACTIVE) {
            $expected = $_SESSION['cms_beratung_captcha'][$pageId] ?? null;
            unset($_SESSION['cms_beratung_captcha'][$pageId]);
            $answer = trim((string) ($_POST['beratung_captcha'] ?? ''));
            if (!is_int($expected) || $answer === '' || !ctype_digit($answer) || (int) $answer !== $expected) {
                $errors['captcha'] = 'Die Antwort auf die Sicherheitsfrage ist nicht korrekt.';
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'message' => $genericError, 'errors' => $errors, 'values' => $values];
        }

        $spam = self::antispam_verdict($values);
        if ($spam !== null) {
            return ['success' => false, 'message' => $spam, 'errors' => ['spam' => $spam], 'values' => $values];
        }

        self::record_rate_limit();

        $data = [
            'landingpage_id' => $pageId,
            'sender_name' => $values['sender_name'],
            'sender_email' => $values['sender_email'],
            'phone' => $values['phone'],
            'company' => $values['company'],
            'topic' => $values['topic'],
            'desired_service' => self::desired_services()[$values['desired_service']] ?? '',
            'message' => $values['message'],
            'consent' => true,
            'copy_to_sender' => !empty($_POST['copy_to_sender']),
            'is_spam' => false,
        ];

        if (($settings['form_storage_enabled'] ?? '1') === '1') {
            try {
                CMS_Beratung_Storage::instance()->create_submission($data);
            } catch (\Throwable $e) {
                error_log('CMS Beratung: Anfrage konnte nicht gespeichert werden: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Die Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut.', 'errors' => ['storage' => 'Speichern fehlgeschlagen.'], 'values' => $values];
            }
        }

        if (($settings['notification_enabled'] ?? '1') === '1') {
            self::send_notification($page, $data, $settings);
        }
        if (!empty($data['copy_to_sender']) && ($settings['sender_copy_enabled'] ?? '0') === '1') {
            self::send_sender_copy($page, $data);
        }

        return ['success' => true, 'message' => (string) $settings['success_message'], 'errors' => [], 'values' => []];
    }

    /** @param array<string,mixed> $posted @return array<string,string> */
    private static function posted_values(array $posted): array
    {
        $field = static function (string $key) use ($posted): string {
            $raw = $posted[$key] ?? '';
            $value = is_scalar($raw) ? (string) $raw : '';
            $value = $key === 'sender_email' ? trim(str_replace(["\r", "\n", "\0"], '', $value)) : CMS_Beratung_Settings::text($value);
            if ($key !== 'message') {
                $value = trim(str_replace(["\r", "\n"], ' ', $value));
            }
            return mb_substr($value, 0, self::FIELD_LIMITS[$key] ?? 255);
        };

        return [
            'sender_name' => $field('sender_name'),
            'company' => $field('company'),
            'sender_email' => $field('sender_email'),
            'phone' => $field('phone'),
            'topic' => $field('topic'),
            'desired_service' => CMS_Beratung_Settings::slug(is_scalar($posted['desired_service'] ?? null) ? (string) $posted['desired_service'] : '', ''),
            'message' => $field('message'),
        ];
    }

    private static function client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private static function rate_limit_ok(): bool
    {
        if (!class_exists('CMS\\Security')) {
            return true;
        }
        try {
            if (method_exists(\CMS\Security::class, 'checkRateLimit') && !\CMS\Security::checkRateLimit('cms_beratung_form', self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW)) {
                return false;
            }
            if (method_exists(\CMS\Security::class, 'checkDbRateLimit')) {
                return \CMS\Security::checkDbRateLimit(self::client_ip(), self::RATE_LIMIT_ACTION, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW);
            }
        } catch (\Throwable) {
            // Ratenbegrenzung darf das Formular nicht unbenutzbar machen.
        }
        return true;
    }

    private static function record_rate_limit(): void
    {
        try {
            if (class_exists('CMS\\Security') && method_exists(\CMS\Security::class, 'recordDbRateLimitAttempt')) {
                \CMS\Security::recordDbRateLimitAttempt(self::client_ip(), self::RATE_LIMIT_ACTION);
            }
        } catch (\Throwable) {
            // optional
        }
    }

    /** Core-Antispam (Blacklist, Linkanzahl, leerer User-Agent) – nur wenn im Core aktiviert. */
    private static function antispam_verdict(array $values): ?string
    {
        if (!class_exists('CMS\\Services\\AntispamService')) {
            return null;
        }
        try {
            $result = \CMS\Services\AntispamService::getInstance()->evaluate([
                'honeypot_value' => '',
                'content' => (string) ($values['message'] ?? ''),
                'email' => (string) ($values['sender_email'] ?? ''),
                'author_name' => (string) ($values['sender_name'] ?? ''),
                'ip_address' => self::client_ip(),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'source' => 'cms-beratung',
            ]);
            return !empty($result['rejected']) ? ((string) ($result['message'] ?? '') ?: 'Die Anfrage wurde aus Sicherheitsgründen blockiert.') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $data @param array<string,string> $settings */
    private static function send_notification(array $page, array $data, array $settings): void
    {
        $recipient = trim((string) ($settings['contact_recipient_email'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $subject = self::mail_subject((string) ($settings['contact_subject_prefix'] ?? '[CMS Beratung]'), (string) ($data['topic'] ?? ''));
        $replyTo = (string) ($data['sender_email'] ?? '');
        $headers = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? ['Reply-To' => $replyTo] : [];
        self::send_mail($recipient, $subject, self::mail_body($page, $data), $headers);
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $data */
    private static function send_sender_copy(array $page, array $data): void
    {
        $email = (string) ($data['sender_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        self::send_mail($email, 'Kopie Ihrer Beratungsanfrage', self::mail_body($page, $data));
    }

    /** Betreff ohne Zeilenumbrüche/Steuerzeichen (keine Header-Injection), höchstens 200 Zeichen. */
    private static function mail_subject(string $prefix, string $topic): string
    {
        $clean = static fn(string $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', CMS_Beratung_Settings::text($value)));
        $topic = $clean($topic);
        return mb_substr(trim($clean($prefix) . ' ' . ($topic !== '' ? $topic : 'Neue Beratungsanfrage')), 0, 200);
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $data */
    private static function mail_body(array $page, array $data): string
    {
        return "Neue Beratungsanfrage über: " . (string) ($page['public_title'] ?? 'CMS Beratung') . "\n\n"
            . "Name: " . (string) ($data['sender_name'] ?? '') . "\n"
            . "Firma: " . (string) ($data['company'] ?? '') . "\n"
            . "E-Mail: " . (string) ($data['sender_email'] ?? '') . "\n"
            . "Telefon: " . (string) ($data['phone'] ?? '') . "\n"
            . "Thema: " . (string) ($data['topic'] ?? '') . "\n"
            . "Wunschleistung: " . (string) ($data['desired_service'] ?? '') . "\n\n"
            . (string) ($data['message'] ?? '');
    }

    /** @param array<string,string> $headers */
    private static function send_mail(string $recipient, string $subject, string $body, array $headers = []): void
    {
        $recipient = trim(str_replace(["\r", "\n"], '', $recipient));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        foreach ($headers as $name => $value) {
            $headers[$name] = trim(str_replace(["\r", "\n"], '', (string) $value));
        }
        // Reiner Text: Nutzereingaben werden nie als HTML in die Mail übernommen.
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        try {
            if (function_exists('cms_mail')) {
                cms_mail($recipient, $subject, $body, $headers);
                return;
            }
            if (function_exists('mail')) {
                $headerLines = [];
                foreach ($headers as $name => $value) {
                    $headerLines[] = $name . ': ' . $value;
                }
                @mail($recipient, $subject, $body, implode("\r\n", $headerLines));
            }
        } catch (\Throwable $e) {
            error_log('CMS Beratung: Benachrichtigung konnte nicht gesendet werden: ' . $e->getMessage());
        }
    }
}
