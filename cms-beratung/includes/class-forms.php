<?php
/**
 * CMS Beratung – frontend consultation form handling.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Forms
{
    /** Höchstens 5 Anfragen je 10 Minuten (Sitzung bzw. IP). */
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 600;
    private const RATE_LIMIT_ACTION = 'beratung_form';

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

        $beratungToken = (string) ($_POST['beratung_csrf_token'] ?? $_POST['csrf_token'] ?? '');
        if (!class_exists('CMS\\Security') || !\CMS\Security::instance()->verifyToken($beratungToken, 'beratung_form_' . (int) ($page['id'] ?? 0))) {
            return ['success' => false, 'message' => 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'errors' => ['csrf' => 'Sicherheitsprüfung fehlgeschlagen.'], 'values' => $values];
        }

        if (($settings['honeypot_enabled'] ?? '1') === '1' && trim((string) ($_POST['website'] ?? '')) !== '') {
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

        if ($errors !== []) {
            return ['success' => false, 'message' => 'Bitte prüfen Sie die markierten Felder.', 'errors' => $errors, 'values' => $values];
        }

        $spam = self::antispam_verdict($values);
        if ($spam !== null) {
            return ['success' => false, 'message' => $spam, 'errors' => ['spam' => $spam], 'values' => $values];
        }

        self::record_rate_limit();

        $data = [
            'landingpage_id' => (int) ($page['id'] ?? 0),
            'sender_name' => $values['sender_name'],
            'sender_email' => $values['sender_email'],
            'phone' => $values['phone'],
            'company' => $values['company'],
            'topic' => $values['topic'],
            'desired_service' => self::desired_services()[$values['desired_service']] ?? $values['desired_service'],
            'message' => $values['message'],
            'consent' => true,
            'copy_to_sender' => !empty($_POST['copy_to_sender']),
            'is_spam' => false,
        ];

        if (($settings['form_storage_enabled'] ?? '1') === '1') {
            CMS_Beratung_Storage::instance()->create_submission($data);
        }

        if (($settings['notification_enabled'] ?? '1') === '1') {
            self::send_notification($page, $data, $settings);
        }
        if (!empty($data['copy_to_sender']) && ($settings['sender_copy_enabled'] ?? '0') === '1') {
            self::send_sender_copy($page, $data, $settings);
        }

        return ['success' => true, 'message' => (string) $settings['success_message'], 'errors' => [], 'values' => []];
    }

    /** @param array<string,mixed> $posted @return array<string,string> */
    /** Client-IP über den Core (berücksichtigt vertrauenswürdige Proxys), sonst REMOTE_ADDR. */
    public static function client_ip(): string
    {
        if (class_exists('CMS\\Security') && method_exists(\CMS\Security::class, 'getClientIp')) {
            try {
                return (string) \CMS\Security::getClientIp();
            } catch (\Throwable) {
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /** Höchstens RATE_LIMIT_MAX Anfragen je RATE_LIMIT_WINDOW Sekunden – je Sitzung und je IP (Core-Ratenbegrenzung). */
    private static function rate_limit_ok(): bool
    {
        if (!class_exists('CMS\\Security')) {
            return true;
        }
        try {
            if (!\CMS\Security::checkRateLimit(self::RATE_LIMIT_ACTION, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW)) {
                return false;
            }
            $ip = self::client_ip();
            if (filter_var($ip, FILTER_VALIDATE_IP) && method_exists(\CMS\Security::class, 'checkDbRateLimit')) {
                return \CMS\Security::checkDbRateLimit($ip, self::RATE_LIMIT_ACTION, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW);
            }
        } catch (\Throwable) {
            // Die Ratenbegrenzung darf das Formular nicht unbenutzbar machen.
        }

        return true;
    }

    private static function record_rate_limit(): void
    {
        try {
            $ip = self::client_ip();
            if (filter_var($ip, FILTER_VALIDATE_IP) && class_exists('CMS\\Security') && method_exists(\CMS\Security::class, 'recordDbRateLimitAttempt')) {
                \CMS\Security::recordDbRateLimitAttempt($ip, self::RATE_LIMIT_ACTION);
            }
        } catch (\Throwable) {
        }
    }

    /** Core-Antispam (Blacklist, Linkanzahl, Mindestzeit …) – greift nur, wenn er im Core aktiviert ist. */
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

    private static function posted_values(array $posted): array
    {
        return [
            'sender_name' => CMS_Beratung_Settings::text((string) ($posted['sender_name'] ?? '')),
            'company' => CMS_Beratung_Settings::text((string) ($posted['company'] ?? '')),
            'sender_email' => trim((string) ($posted['sender_email'] ?? '')),
            'phone' => CMS_Beratung_Settings::text((string) ($posted['phone'] ?? '')),
            'topic' => CMS_Beratung_Settings::text((string) ($posted['topic'] ?? '')),
            'desired_service' => CMS_Beratung_Settings::slug((string) ($posted['desired_service'] ?? ''), ''),
            'message' => CMS_Beratung_Settings::text((string) ($posted['message'] ?? '')),
        ];
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $data @param array<string,string> $settings */
    private static function send_notification(array $page, array $data, array $settings): void
    {
        $recipient = (string) ($settings['contact_recipient_email'] ?? '');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $subject = self::mail_subject((string) ($settings['contact_subject_prefix'] ?? '[CMS Beratung]'), (string) ($data['topic'] ?? 'Neue Beratungsanfrage'));
        $body = self::mail_body($page, $data);
        self::send_mail($recipient, $subject, $body);
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $data @param array<string,string> $settings */
    private static function send_sender_copy(array $page, array $data, array $settings): void
    {
        unset($settings);
        $email = (string) ($data['sender_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        self::send_mail($email, 'Kopie Ihrer Beratungsanfrage', self::mail_body($page, $data));
    }

    private static function mail_subject(string $prefix, string $topic): string
    {
        $prefix = str_replace(["\r", "\n"], '', CMS_Beratung_Settings::text($prefix));
        $topic = str_replace(["\r", "\n"], '', CMS_Beratung_Settings::text($topic));
        return trim($prefix . ' ' . ($topic !== '' ? $topic : 'Neue Beratungsanfrage'));
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

    private static function send_mail(string $recipient, string $subject, string $body): void
    {
        // Core-Versand (MailService: SMTP/Graph, Mail-Log); seit 365CMS 4.0.0 immer vorhanden.
        if (!function_exists('cms_mail') || !cms_mail($recipient, $subject, $body)) {
            error_log('[cms-beratung] Benachrichtigung konnte nicht versendet werden.');
        }
    }
}
