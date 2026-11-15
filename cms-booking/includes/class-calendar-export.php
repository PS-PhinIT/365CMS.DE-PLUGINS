<?php
/**
 * CMS Booking – iCal / ICS-Export
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Calendar_Export
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /**
     * ICS-String für eine einzelne Buchung generieren.
     */
    public function generate_ics(array $booking): string
    {
        $uid   = $this->sanitize_ical_token((string) ($booking['ical_uid'] ?? bin2hex(random_bytes(16)) . '@' . ($this->get_host())));
        $now   = gmdate('Ymd\THis\Z');
        $start = gmdate('Ymd\THis\Z', strtotime("{$booking['booking_date']} {$booking['start_time']}"));
        $end   = gmdate('Ymd\THis\Z', strtotime("{$booking['booking_date']} {$booking['end_time']}"));

        $summary     = $this->ical_escape($booking['service_title'] ?? 'Termin');
        $description = $this->ical_escape(
            "Buchung #{$booking['id']}\n"
            . "Kunde: {$booking['customer_name']}\n"
            . "E-Mail: {$booking['customer_email']}\n"
            . ($booking['meeting_url'] ? "Link: {$booking['meeting_url']}\n" : '')
            . ($booking['notes'] ? "Notizen: {$booking['notes']}\n" : '')
        );
        $location = $this->ical_escape(
            ($booking['location_type'] ?? 'online') === 'online'
                ? ($booking['meeting_url'] ?? 'Online')
                : 'Vor Ort'
        );
        $organizer = $this->sanitize_mailto((string) ($booking['provider_email'] ?? ''));
        $attendee  = $this->sanitize_mailto((string) ($booking['customer_email'] ?? ''));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//365CMS//CMS Booking//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART:{$start}",
            "DTEND:{$end}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            "LOCATION:{$location}",
            "STATUS:CONFIRMED",
        ];

        if ($organizer !== '') {
            $lines[] = "ORGANIZER;CN={$this->ical_escape($booking['provider_name'] ?? '')}:mailto:{$organizer}";
        }
        if ($attendee !== '') {
            $lines[] = "ATTENDEE;CN={$this->ical_escape($booking['customer_name'])}:mailto:{$attendee}";
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * ICS-Datei als HTTP-Download ausliefern.
     */
    public function serve_ics(array $booking): void
    {
        $ics      = $this->generate_ics($booking);
        $filename = 'buchung-' . (int) ($booking['id'] ?? 0) . '.ics';

        header('Content-Type: text/calendar; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ics));
        echo $ics;
        exit;
    }

    /**
     * Google Calendar URL generieren.
     */
    public function google_calendar_url(array $booking): string
    {
        $start   = date('Ymd\THis', strtotime("{$booking['booking_date']} {$booking['start_time']}"));
        $end     = date('Ymd\THis', strtotime("{$booking['booking_date']} {$booking['end_time']}"));
        $title   = $booking['service_title'] ?? 'Termin';
        $details = "Buchung #{$booking['id']} bei {$booking['provider_name']}";

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text='    . urlencode($title)
            . '&dates='   . $start . '/' . $end
            . '&details=' . urlencode($details)
            . '&location=' . urlencode($booking['meeting_url'] ?? '');
    }

    /* ================================================================== */
    /*  Helfer                                                             */
    /* ================================================================== */

    private function ical_escape(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);
        return $text;
    }

    private function sanitize_ical_token(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9@._\-]/', '', $value) ?: bin2hex(random_bytes(16)) . '@' . $this->get_host();
    }

    private function sanitize_mailto(string $email): string
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        return is_string($email) ? $email : '';
    }

    private function get_host(): string
    {
        return defined('SITE_URL') ? (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') : 'localhost';
    }
}
