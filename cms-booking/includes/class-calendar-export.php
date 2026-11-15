<?php
/**
 * CMS Booking – iCal / ICS-Export (RFC 5545)
 *
 * - Zeitpunkte werden aus der Zeitzone des Anbieters nach UTC umgerechnet (DTSTART/DTEND mit „Z“).
 * - TEXT-Werte werden nach RFC 5545 §3.3.11 escaped (Backslash, Semikolon, Komma, Zeilenumbruch).
 * - Parameterwerte (CN) werden nach §3.2 in Anführungszeichen gesetzt; DQUOTE und Steuerzeichen entfallen.
 * - Zeilen werden nach §3.1 bei 75 Oktetten UTF-8-sicher gefaltet (CRLF + Leerzeichen).
 * - Der Download ist nur mit gültigem Zugangs-Token möglich (Prüfung im Frontend-Controller).
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
        $uid   = $this->sanitize_ical_token((string) ($booking['ical_uid'] ?? ''));
        $now   = gmdate('Ymd\THis\Z');
        [$start, $end] = $this->utc_range($booking);

        $summary = trim((string) ($booking['service_title'] ?? ''));
        if ($summary === '') {
            $summary = 'Termin';
        }
        $providerName = trim((string) ($booking['provider_name'] ?? ''));
        if ($providerName !== '') {
            $summary .= ' – ' . $providerName;
        }

        $meetingUrl = $this->safe_url((string) ($booking['meeting_url'] ?? ''));
        $descriptionLines = ['Buchung #' . (int) ($booking['id'] ?? 0)];
        if ($providerName !== '') {
            $descriptionLines[] = 'Anbieter: ' . $providerName;
        }
        if ($meetingUrl !== '') {
            $descriptionLines[] = 'Link: ' . $meetingUrl;
        }
        $notes = trim((string) ($booking['notes'] ?? ''));
        if ($notes !== '') {
            $descriptionLines[] = 'Notizen: ' . $notes;
        }

        $locationType = (string) ($booking['location_type'] ?? 'online');
        $location = match ($locationType) {
            'onsite' => 'Vor Ort',
            'hybrid' => $meetingUrl !== '' ? 'Online / Vor Ort: ' . $meetingUrl : 'Online / Vor Ort',
            default  => $meetingUrl !== '' ? $meetingUrl : 'Online',
        };

        $status = match ((string) ($booking['status'] ?? '')) {
            'confirmed', 'completed' => 'CONFIRMED',
            'cancelled', 'no_show'   => 'CANCELLED',
            default                  => 'TENTATIVE',
        };

        $organizer = $this->sanitize_mailto((string) ($booking['provider_email'] ?? ''));
        $attendee  = $this->sanitize_mailto((string) ($booking['customer_email'] ?? ''));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//365CMS//CMS Booking ' . (defined('CMS_BOOKING_VERSION') ? CMS_BOOKING_VERSION : '') . '//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $now,
            'DTSTART:' . $start,
            'DTEND:' . $end,
            'SUMMARY:' . $this->ical_escape($summary),
            'DESCRIPTION:' . $this->ical_escape(implode("\n", $descriptionLines)),
            'LOCATION:' . $this->ical_escape($location),
            'STATUS:' . $status,
            'TRANSP:OPAQUE',
        ];

        if ($meetingUrl !== '') {
            $lines[] = 'URL:' . $meetingUrl;
        }
        if ($organizer !== '') {
            $lines[] = 'ORGANIZER' . $this->cn_param($providerName) . ':mailto:' . $organizer;
        }
        if ($attendee !== '') {
            $lines[] = 'ATTENDEE' . $this->cn_param((string) ($booking['customer_name'] ?? '')) . ';ROLE=REQ-PARTICIPANT:mailto:' . $attendee;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold_line'], $lines)) . "\r\n";
    }

    /**
     * ICS-Datei als HTTP-Download ausliefern.
     */
    public function serve_ics(array $booking): void
    {
        $ics      = $this->generate_ics($booking);
        $filename = 'buchung-' . (int) ($booking['id'] ?? 0) . '.ics';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ics));
        echo $ics;
        exit;
    }

    /**
     * Google Calendar URL generieren (UTC-Zeitpunkte).
     */
    public function google_calendar_url(array $booking): string
    {
        [$start, $end] = $this->utc_range($booking);
        $title   = (string) ($booking['service_title'] ?? 'Termin');
        $details = 'Buchung #' . (int) ($booking['id'] ?? 0)
            . (!empty($booking['provider_name']) ? ' bei ' . (string) $booking['provider_name'] : '');

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $title,
            'dates'    => $start . '/' . $end,
            'details'  => $details,
            'location' => $this->safe_url((string) ($booking['meeting_url'] ?? '')),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /* ================================================================== */
    /*  Helfer                                                             */
    /* ================================================================== */

    /**
     * @return array{0: string, 1: string} UTC-Start und -Ende im Format Ymd\THis\Z
     */
    private function utc_range(array $booking): array
    {
        $timezone = (string) ($booking['provider_timezone'] ?? '');
        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = class_exists('CMS_Booking_Settings') ? CMS_Booking_Settings::timezone() : 'Europe/Berlin';
        }

        $date = (string) ($booking['booking_date'] ?? '');
        $startTime = substr((string) ($booking['start_time'] ?? '00:00'), 0, 5);
        $endTime   = substr((string) ($booking['end_time'] ?? '00:00'), 0, 5);

        try {
            $tz    = new \DateTimeZone($timezone);
            $utc   = new \DateTimeZone('UTC');
            $start = new \DateTimeImmutable($date . ' ' . $startTime, $tz);
            $end   = new \DateTimeImmutable($date . ' ' . $endTime, $tz);
            if ($end <= $start) {
                $end = $start->modify('+' . max(1, (int) ($booking['duration_min'] ?? 60)) . ' minutes');
            }

            return [$start->setTimezone($utc)->format('Ymd\THis\Z'), $end->setTimezone($utc)->format('Ymd\THis\Z')];
        } catch (\Throwable $e) {
            $now = gmdate('Ymd\THis\Z');

            return [$now, $now];
        }
    }

    /**
     * TEXT-Escaping nach RFC 5545 §3.3.11.
     */
    private function ical_escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Steuerzeichen außer Zeilenumbruch entfernen (in TEXT nicht erlaubt).
        $text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    /**
     * CN-Parameter (RFC 5545 §3.2: param-value als quoted-string, DQUOTE nicht erlaubt).
     */
    private function cn_param(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"]/u', '', $name));

        return $name === '' ? '' : ';CN="' . $name . '"';
    }

    /**
     * Zeilenfaltung nach RFC 5545 §3.1: höchstens 75 Oktette je Zeile, ohne UTF-8-Zeichen zu teilen.
     */
    private function fold_line(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $current = '';
        $limit = 75;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $result .= $current . "\r\n ";
                $current = '';
                $limit = 74; // Folgezeilen beginnen mit einem Leerzeichen.
            }
            $current .= $char;
        }

        return $result . $current;
    }

    private function sanitize_ical_token(string $value): string
    {
        $clean = (string) preg_replace('/[^A-Za-z0-9@._\-]/', '', $value);

        return $clean !== '' ? $clean : bin2hex(random_bytes(16)) . '@' . $this->get_host();
    }

    private function sanitize_mailto(string $email): string
    {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        return is_string($email) ? $email : '';
    }

    private function safe_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    private function get_host(): string
    {
        return defined('SITE_URL') ? (parse_url((string) SITE_URL, PHP_URL_HOST) ?: 'localhost') : 'localhost';
    }
}
