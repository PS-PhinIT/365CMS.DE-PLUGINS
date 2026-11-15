<?php
/**
 * CMS Booking – Admin Buchungen (Liste, Filter, Sammelaktionen, Detail, CSV-Export)
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait CMS_Booking_Page_Bookings_Trait
{
    /** @var array<string, string> Aktion => Status */
    private static array $bookingStatusActions = [
        'confirm'  => CMS_Booking_Bookings::STATUS_CONFIRMED,
        'cancel'   => CMS_Booking_Bookings::STATUS_CANCELLED,
        'complete' => CMS_Booking_Bookings::STATUS_COMPLETED,
        'no_show'  => CMS_Booking_Bookings::STATUS_NO_SHOW,
        'pending'  => CMS_Booking_Bookings::STATUS_PENDING,
    ];

    /**
     * Filter aus GET (Liste) bzw. aus versteckten Feldern (Export/Sammelaktion).
     *
     * @return array{status: string, search: string, provider_id: int, date_from: string, date_to: string}
     */
    private static function booking_filters(array $source): array
    {
        $status = is_scalar($source['status'] ?? null) ? (string) $source['status'] : '';
        $dateFrom = is_scalar($source['date_from'] ?? null) ? (string) $source['date_from'] : '';
        $dateTo = is_scalar($source['date_to'] ?? null) ? (string) $source['date_to'] : '';

        return [
            'status'      => array_key_exists($status, CMS_Booking_Bookings::status_labels()) ? $status : '',
            'search'      => is_scalar($source['q'] ?? null) ? mb_substr(trim(strip_tags((string) $source['q'])), 0, 100) : '',
            'provider_id' => max(0, (int) ($source['provider_id'] ?? 0)),
            'date_from'   => CMS_Booking_Bookings::is_date($dateFrom) ? $dateFrom : '',
            'date_to'     => CMS_Booking_Bookings::is_date($dateTo) ? $dateTo : '',
        ];
    }

    /** @return array<string, string|int> */
    private static function booking_filter_query(array $filters): array
    {
        return [
            'status'      => $filters['status'],
            'q'           => $filters['search'],
            'provider_id' => $filters['provider_id'],
            'date_from'   => $filters['date_from'],
            'date_to'     => $filters['date_to'],
        ];
    }

    public function handle_bookings_post(): string
    {
        $bookings = CMS_Booking_Bookings::instance();
        $filters  = self::booking_filters($_POST);
        $query    = self::booking_filter_query($filters);

        // CSV-Export (Persistent-Token: mehrfacher Download ohne Neuladen)
        if (isset($_POST['export_csv'])) {
            $error = self::guard_post('booking_export', true);
            if ($error !== '') {
                return $error;
            }
            $this->export_bookings_csv($filters);
        }

        $error = self::guard_post('booking_admin');
        if ($error !== '') {
            return $error;
        }

        // Interne Notizen (Detailansicht)
        if (isset($_POST['save_notes'])) {
            $id = (int) ($_POST['booking_id'] ?? 0);
            if ($id <= 0 || !$bookings->get($id)) {
                return 'Buchung nicht gefunden.';
            }
            $notes = mb_substr(trim(strip_tags((string) (is_scalar($_POST['internal_notes'] ?? null) ? $_POST['internal_notes'] : ''))), 0, 5000);
            $bookings->update_internal_notes($id, $notes);
            self::redirect_with('bookings', ['view' => 'detail', 'id' => $id], 'success', 'Interne Notiz gespeichert.');
        }

        // Einzelaktion aus Zeile/Detail: row_action = "<aktion>:<id>"
        $rowAction = is_scalar($_POST['row_action'] ?? null) ? (string) $_POST['row_action'] : '';
        if ($rowAction !== '') {
            [$action, $id] = array_pad(explode(':', $rowAction, 2), 2, '0');
            $id = (int) $id;
            if ($id <= 0 || ($action !== 'delete' && !isset(self::$bookingStatusActions[$action]))) {
                return 'Ungültige Aktion.';
            }
            $back = isset($_POST['return_detail']) && $action !== 'delete' ? ['view' => 'detail', 'id' => $id] : $query;
            if ($action === 'delete') {
                $bookings->delete($id);
                self::redirect_with('bookings', $query, 'success', 'Buchung #' . $id . ' wurde gelöscht.');
            }
            $changed = $bookings->set_status($id, self::$bookingStatusActions[$action]);
            $label = CMS_Booking_Bookings::status_labels()[self::$bookingStatusActions[$action]] ?? $action;
            self::redirect_with('bookings', $back, 'success', $changed
                ? 'Buchung #' . $id . ': Status „' . $label . '“ gesetzt.'
                : 'Buchung #' . $id . ' hatte bereits den Status „' . $label . '“.');
        }

        // Sammelaktion
        $bulk = is_scalar($_POST['bulk_action'] ?? null) ? (string) $_POST['bulk_action'] : '';
        $ids  = self::posted_ids();
        if ($bulk === '' || $ids === []) {
            return 'Bitte eine Aktion und mindestens eine Buchung auswählen.';
        }
        if ($bulk === 'delete') {
            $count = $bookings->delete_many($ids);
            self::redirect_with('bookings', $query, 'success', $count . ' Buchung(en) gelöscht.');
        }
        if (!isset(self::$bookingStatusActions[$bulk])) {
            return 'Ungültige Sammelaktion.';
        }
        $count = 0;
        foreach ($ids as $id) {
            if ($bookings->set_status($id, self::$bookingStatusActions[$bulk])) {
                $count++;
            }
        }
        $label = CMS_Booking_Bookings::status_labels()[self::$bookingStatusActions[$bulk]] ?? $bulk;
        self::redirect_with('bookings', $query, 'success', $count . ' Buchung(en) auf „' . $label . '“ gesetzt.');
    }

    public function render_bookings_page(): void
    {
        $bookings = CMS_Booking_Bookings::instance();
        $error    = $this->postError;
        $statusLabels = CMS_Booking_Bookings::status_labels();
        $csrfToken    = self::generate_nonce('booking_admin');

        if (self::get_text('view') === 'detail') {
            $booking = $bookings->get((int) ($_GET['id'] ?? 0));
            if ($booking) {
                $meta = $bookings->get_all_meta((int) $booking['id']);
                include CMS_BOOKING_PLUGIN_DIR . 'admin/views/booking-detail.php';
                return;
            }
            $error = 'Buchung nicht gefunden.';
        }

        $filters = self::booking_filters($_GET);
        $query   = self::booking_filter_query($filters);
        $page    = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage = 25;
        $total   = $bookings->count_filtered($filters);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min($page, $pages);
        $items   = $bookings->query_list($filters, ($page - 1) * $perPage, $perPage);

        $providers   = CMS_Booking_Providers::instance()->get_options();
        $exportToken = self::generate_nonce('booking_export');

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/bookings.php';
    }

    /**
     * CSV (UTF-8 mit BOM, Semikolon) der gefilterten Buchungen; Formel-Injection-Schutz.
     */
    private function export_bookings_csv(array $filters): never
    {
        $rows = CMS_Booking_Bookings::instance()->query_list($filters, 0, 5000);
        $labels = CMS_Booking_Bookings::status_labels();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = 'buchungen-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        $columns = ['ID', 'Datum', 'Beginn', 'Ende', 'Status', 'Anbieter', 'Leistung', 'Kunde', 'E-Mail', 'Telefon',
            'Ort', 'Preis', 'Währung', 'Nachricht', 'Interne Notiz', 'Angelegt'];
        fputcsv($out, $columns, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                (int) $row['id'],
                (string) $row['booking_date'],
                substr((string) $row['start_time'], 0, 5),
                substr((string) $row['end_time'], 0, 5),
                $labels[(string) $row['status']] ?? (string) $row['status'],
                (string) ($row['provider_name'] ?? ''),
                (string) ($row['service_title'] ?? ''),
                (string) $row['customer_name'],
                (string) $row['customer_email'],
                (string) ($row['customer_phone'] ?? ''),
                (string) ($row['location_type'] ?? ''),
                number_format(((int) $row['price_cents']) / 100, 2, ',', ''),
                (string) ($row['currency'] ?? ''),
                (string) ($row['notes'] ?? ''),
                (string) ($row['internal_notes'] ?? ''),
                (string) ($row['created_at'] ?? ''),
            ]), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Zellen mit = + - @ Tab CR am Anfang werden mit ' präfixiert (CSV-/Formel-Injection). */
    public static function csv_cell(mixed $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
