<?php
/**
 * CMS Booking – E-Mail-Benachrichtigungen
 *
 * - Versand über den Core-Mailer (`cms_mail()` → MailService/MailQueue, SMTP-Konfiguration, Mail-Log),
 *   Rückfall auf `mail()` nur ohne Core-Helfer.
 * - Header-Injection: Betreff, Absendername und Adressen werden von CR/LF und Steuerzeichen befreit,
 *   Adressen per FILTER_VALIDATE_EMAIL geprüft, der Absendername als quoted-string gesetzt.
 * - Alle Platzhalter werden HTML-escaped; URLs nur mit http/https.
 * - Erinnerungen über `cms_cron_hourly` (Einstellungen „Erinnerungen versenden“ / „Stunden vorher“).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Notifications
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        if (class_exists('CMS\Hooks')) {
            \CMS\Hooks::addAction('booking_created',          [$this, 'on_booking_created'],    10);
            \CMS\Hooks::addAction('booking_status_confirmed', [$this, 'on_booking_confirmed'],  10);
            \CMS\Hooks::addAction('booking_status_cancelled', [$this, 'on_booking_cancelled'],  10);
            \CMS\Hooks::addAction('cms_cron_hourly',          [$this, 'send_due_reminders'],    40);
        }
    }

    /* ================================================================== */
    /*  Hook-Callbacks                                                     */
    /* ================================================================== */

    public function on_booking_created(mixed $bookingId = 0): void
    {
        $booking = $this->load((int) $bookingId);
        if ($booking === null) {
            return;
        }

        $this->send_customer_confirmation($booking);
        $this->send_provider_notification($booking);
        $this->send_admin_notification($booking);
    }

    public function on_booking_confirmed(mixed $bookingId = 0): void
    {
        $booking = $this->load((int) $bookingId);
        if ($booking === null) {
            return;
        }
        $this->send_customer_status_update($booking, 'bestätigt');
    }

    public function on_booking_cancelled(mixed $bookingId = 0): void
    {
        $booking = $this->load((int) $bookingId);
        if ($booking === null) {
            return;
        }
        $this->send_customer_status_update($booking, 'storniert');
        $this->send_provider_cancellation($booking);
    }

    /**
     * Kompatibilität 3.x: direkter Versand der Eingangsmails für einen geladenen Datensatz.
     */
    public function send_booking_created(array $booking): void
    {
        $this->on_booking_created((int) ($booking['id'] ?? 0));
    }

    /**
     * Cron (stündlich): Erinnerungen für bestätigte Termine innerhalb der eingestellten Vorlaufzeit.
     */
    public function send_due_reminders(): int
    {
        try {
            if (!CMS_Booking_Settings::bool('send_reminders')) {
                return 0;
            }
            $hours = max(1, CMS_Booking_Settings::int('reminder_hours', 24));
            $sent  = 0;
            $now   = time();

            foreach (CMS_Booking_Bookings::instance()->get_reminder_candidates($hours) as $booking) {
                $startTs = $this->start_timestamp($booking);
                if ($startTs === null || $startTs <= $now || $startTs - $now > $hours * 3600) {
                    continue;
                }
                $this->send_reminder($booking);
                CMS_Booking_Bookings::instance()->mark_reminder_sent((int) $booking['id']);
                $sent++;
            }

            return $sent;
        } catch (\Throwable $e) {
            $this->log_notice('reminder run failed: ' . $e->getMessage());

            return 0;
        }
    }

    /* ================================================================== */
    /*  Kunden-Mails                                                       */
    /* ================================================================== */

    private function send_customer_confirmation(array $booking): void
    {
        $confirmed = ($booking['status'] ?? '') === CMS_Booking_Bookings::STATUS_CONFIRMED;
        $subject = ($confirmed ? 'Terminbestätigung – ' : 'Buchungseingang – ') . ($booking['service_title'] ?? 'Termin');
        $body    = $this->render_mail(
            $confirmed ? 'Ihr Termin ist bestätigt' : 'Ihre Buchung ist eingegangen',
            [
                'Hallo ' . (string) ($booking['customer_name'] ?? '') . ',',
                $confirmed
                    ? 'Ihr Termin wurde verbindlich gebucht.'
                    : 'Ihre Buchung wurde registriert. Sie erhalten eine weitere E-Mail, sobald sie bestätigt wurde.',
            ],
            $this->booking_rows($booking, ['booking_id', 'service', 'provider', 'date', 'time', 'location', 'price']),
            $booking,
            true
        );
        $this->send((string) ($booking['customer_email'] ?? ''), $subject, $body);
    }

    private function send_customer_status_update(array $booking, string $statusText): void
    {
        $subject = "Buchung {$statusText} – " . ($booking['service_title'] ?? 'Termin');
        $body    = $this->render_mail(
            'Buchung ' . $statusText,
            [
                'Hallo ' . (string) ($booking['customer_name'] ?? '') . ',',
                'der Status Ihrer Buchung #' . (int) ($booking['id'] ?? 0) . ' wurde auf „' . $statusText . '“ geändert.',
            ],
            $this->booking_rows($booking, ['service', 'provider', 'date', 'time', 'location']),
            $booking,
            $statusText !== 'storniert'
        );
        $this->send((string) ($booking['customer_email'] ?? ''), $subject, $body);
    }

    private function send_reminder(array $booking): void
    {
        $subject = 'Erinnerung: ' . ($booking['service_title'] ?? 'Termin') . ' am ' . $this->format_date((string) ($booking['booking_date'] ?? ''));
        $body    = $this->render_mail(
            'Terminerinnerung',
            [
                'Hallo ' . (string) ($booking['customer_name'] ?? '') . ',',
                'wir erinnern Sie an Ihren bevorstehenden Termin.',
            ],
            $this->booking_rows($booking, ['service', 'provider', 'date', 'time', 'location']),
            $booking,
            true
        );
        $this->send((string) ($booking['customer_email'] ?? ''), $subject, $body);
    }

    /* ================================================================== */
    /*  Provider-Mails                                                     */
    /* ================================================================== */

    private function send_provider_notification(array $booking): void
    {
        $email = (string) ($booking['provider_email'] ?? '');
        if ($email === '') {
            return;
        }

        $subject = 'Neue Buchung von ' . (string) ($booking['customer_name'] ?? '');
        $body    = $this->render_mail(
            'Neue Buchungsanfrage',
            [
                'Hallo ' . (string) ($booking['provider_name'] ?? '') . ',',
                'Sie haben eine neue Buchung erhalten.',
            ],
            $this->booking_rows($booking, ['booking_id', 'customer', 'customer_email', 'customer_phone', 'service', 'date', 'time', 'notes']),
            $booking,
            false
        );
        $this->send($email, $subject, $body);
    }

    private function send_provider_cancellation(array $booking): void
    {
        $email = (string) ($booking['provider_email'] ?? '');
        if ($email === '') {
            return;
        }

        $subject = 'Stornierung – ' . (string) ($booking['customer_name'] ?? '');
        $body    = $this->render_mail(
            'Stornierung',
            [
                'Hallo ' . (string) ($booking['provider_name'] ?? '') . ',',
                'die folgende Buchung wurde storniert.',
            ],
            $this->booking_rows($booking, ['booking_id', 'customer', 'service', 'date', 'time']),
            $booking,
            false
        );
        $this->send($email, $subject, $body);
    }

    /* ================================================================== */
    /*  Admin-Mail                                                         */
    /* ================================================================== */

    private function send_admin_notification(array $booking): void
    {
        $adminEmail = CMS_Booking_Settings::get('admin_email');
        if ($adminEmail === '') {
            return;
        }

        $subject = '[CMS Booking] Neue Buchung #' . (int) ($booking['id'] ?? 0);
        $rows = $this->booking_rows($booking, ['booking_id', 'customer', 'customer_email', 'provider', 'service', 'date', 'time', 'price', 'status']);
        $adminUrl = (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '')
            . '/admin/plugins/booking/bookings?view=detail&id=' . (int) ($booking['id'] ?? 0);
        $body = $this->render_mail('Neue Buchung', ['Im Buchungssystem ist eine neue Buchung eingegangen.'], $rows, $booking, false, $adminUrl, 'Im Admin öffnen');
        $this->send($adminEmail, $subject, $body);
    }

    /* ================================================================== */
    /*  Mail-Aufbau                                                        */
    /* ================================================================== */

    /**
     * @param list<string> $keys
     * @return list<array{0: string, 1: string}>
     */
    private function booking_rows(array $booking, array $keys): array
    {
        $labels = CMS_Booking_Bookings::status_labels();
        $locationLabels = ['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Online / Vor Ort'];
        $rows = [];
        foreach ($keys as $key) {
            $row = match ($key) {
                'booking_id'     => ['Buchung', '#' . (int) ($booking['id'] ?? 0)],
                'customer'       => ['Kunde', (string) ($booking['customer_name'] ?? '')],
                'customer_email' => ['E-Mail', (string) ($booking['customer_email'] ?? '')],
                'customer_phone' => ['Telefon', (string) ($booking['customer_phone'] ?? '')],
                'provider'       => ['Anbieter', (string) ($booking['provider_name'] ?? '')],
                'service'        => ['Leistung', (string) ($booking['service_title'] ?? 'Termin')],
                'date'           => ['Datum', $this->format_date((string) ($booking['booking_date'] ?? ''))],
                'time'           => ['Uhrzeit', substr((string) ($booking['start_time'] ?? ''), 0, 5) . ' – ' . substr((string) ($booking['end_time'] ?? ''), 0, 5) . ' Uhr'],
                'location'       => ['Ort', $locationLabels[(string) ($booking['location_type'] ?? '')] ?? (string) ($booking['location_type'] ?? '')],
                'price'          => ['Preis', CMS_Booking_Services::format_price((int) ($booking['price_cents'] ?? 0), (string) ($booking['currency'] ?? ''))],
                'status'         => ['Status', $labels[(string) ($booking['status'] ?? '')] ?? (string) ($booking['status'] ?? '')],
                'notes'          => ['Nachricht', (string) ($booking['notes'] ?? '')],
                default          => null,
            };
            if ($row !== null && trim($row[1]) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Einheitliches, schlichtes HTML-Layout (Inline-Styles sind in E-Mails erforderlich; keine Emojis).
     *
     * @param list<string>                     $paragraphs Klartext (wird escaped)
     * @param list<array{0: string, 1: string}> $rows       Label/Wert (wird escaped)
     */
    private function render_mail(
        string $heading,
        array $paragraphs,
        array $rows,
        array $booking,
        bool $withCustomerLink,
        string $buttonUrl = '',
        string $buttonLabel = ''
    ): string {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $siteName = defined('SITE_NAME') ? (string) SITE_NAME : '365CMS';

        if ($withCustomerLink && $buttonUrl === '') {
            $buttonUrl = $this->customer_link($booking);
            $buttonLabel = 'Buchung ansehen';
        }
        $meetingUrl = $this->safe_url((string) ($booking['meeting_url'] ?? ''));

        $html = '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">'
            . '<div style="background:#1e293b;color:#ffffff;padding:20px 24px;border-radius:2px 2px 0 0;">'
            . '<h2 style="margin:0;font-size:20px;">' . $e($heading) . '</h2></div>'
            . '<div style="padding:24px;background:#ffffff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 2px 2px;">';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p style="margin:0 0 12px;">' . $e($paragraph) . '</p>';
        }
        if ($rows !== []) {
            $html .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
            foreach ($rows as [$label, $value]) {
                $html .= '<tr><td style="padding:6px 8px;color:#64748b;vertical-align:top;width:35%;">' . $e($label) . ':</td>'
                    . '<td style="padding:6px 8px;">' . nl2br($e($value)) . '</td></tr>';
            }
            $html .= '</table>';
        }
        if ($withCustomerLink && $meetingUrl !== '') {
            $html .= '<p style="margin:0 0 12px;">Meeting-Link: <a href="' . $e($meetingUrl) . '">' . $e($meetingUrl) . '</a></p>';
        }
        if ($buttonUrl !== '' && $this->safe_url($buttonUrl) !== '') {
            $html .= '<p style="margin:20px 0;"><a href="' . $e($buttonUrl) . '" style="display:inline-block;padding:10px 18px;background:#1e293b;color:#ffffff;text-decoration:none;border-radius:2px;">'
                . $e($buttonLabel !== '' ? $buttonLabel : 'Öffnen') . '</a></p>';
        }
        $html .= '<p style="margin-top:24px;color:#94a3b8;font-size:12px;">– ' . $e($siteName) . '</p></div></div>';

        return $html;
    }

    private function customer_link(array $booking): string
    {
        $id  = (int) ($booking['id'] ?? 0);
        $uid = (string) ($booking['ical_uid'] ?? '');
        if ($id <= 0 || $uid === '' || !defined('SITE_URL')) {
            return '';
        }

        return rtrim((string) SITE_URL, '/') . '/booking/confirm/' . $id . '?token=' . rawurlencode(CMS_Booking_Bookings::access_token($id, $uid));
    }

    private function format_date(string $date): string
    {
        $ts = $date !== '' ? strtotime($date) : false;

        return $ts !== false ? date('d.m.Y', $ts) : $date;
    }

    private function start_timestamp(array $booking): ?int
    {
        $timezone = (string) ($booking['provider_timezone'] ?? '');
        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = CMS_Booking_Settings::timezone();
        }
        try {
            $start = new \DateTimeImmutable(
                (string) ($booking['booking_date'] ?? '') . ' ' . substr((string) ($booking['start_time'] ?? ''), 0, 5),
                new \DateTimeZone($timezone)
            );

            return $start->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ================================================================== */
    /*  Mail-Versand                                                       */
    /* ================================================================== */

    private function send(string $to, string $subject, string $htmlBody): bool
    {
        $to = $this->sanitize_email($to);
        if ($to === '') {
            return false;
        }

        $subject   = $this->sanitize_header_value($subject);
        $fromEmail = CMS_Booking_Settings::sender_email();
        $fromName  = $this->sanitize_display_name(
            CMS_Booking_Settings::get('from_name') ?: (defined('SITE_NAME') ? (string) SITE_NAME : '365CMS')
        );

        $headers = [];
        if ($fromEmail !== '') {
            $headers['From'] = '"' . $fromName . '" <' . $fromEmail . '>';
        }

        try {
            if (function_exists('cms_mail')) {
                $sent = cms_mail($to, $subject, $htmlBody, $headers);
            } else {
                $raw = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8'];
                $raw[] = 'From: "' . $fromName . '" <' . ($fromEmail !== '' ? $fromEmail : 'noreply@' . $this->host()) . '>';
                $sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $raw));
            }
        } catch (\Throwable $e) {
            $sent = false;
        }

        if (!$sent) {
            $this->log_notice('booking notification mail not sent');
        }

        return $sent;
    }

    private function load(int $bookingId): ?array
    {
        if ($bookingId <= 0) {
            return null;
        }
        try {
            return CMS_Booking_Bookings::instance()->get($bookingId);
        } catch (\Throwable $e) {
            $this->log_notice('booking not loadable for notification');

            return null;
        }
    }

    private function sanitize_email(string $email): string
    {
        $email = trim(str_replace(["\r", "\n", "\0"], '', $email));
        $clean = filter_var($email, FILTER_VALIDATE_EMAIL);
        return is_string($clean) ? $clean : '';
    }

    private function sanitize_header_value(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
        $value = mb_substr($value, 0, 200);
        return $value === '' ? '365CMS' : $value;
    }

    private function sanitize_display_name(string $value): string
    {
        $value = str_replace(['"', '\\', '<', '>'], '', $this->sanitize_header_value($value));

        return $value === '' ? '365CMS' : mb_substr($value, 0, 100);
    }

    private function safe_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    }

    private function host(): string
    {
        return defined('SITE_URL') ? (string) (parse_url((string) SITE_URL, PHP_URL_HOST) ?: 'localhost') : 'localhost';
    }

    private function log_notice(string $message): void
    {
        error_log('[cms-booking] ' . $message);
    }
}
