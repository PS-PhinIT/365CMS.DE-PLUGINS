<?php
/**
 * CMS Booking – Admin Leistungen (Liste, Filter, Sammelaktionen, Anlegen/Bearbeiten)
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait CMS_Booking_Page_Services_Trait
{
    public function handle_services_post(): string
    {
        $error = self::guard_post('booking_services');
        if ($error !== '') {
            return $error;
        }

        $servicesSvc = CMS_Booking_Services::instance();
        $listQuery = ['q' => self::post_text('q_filter', 100), 'provider_id' => max(0, (int) ($_POST['provider_filter'] ?? 0))];

        if (isset($_POST['save_service'])) {
            return $this->save_service_from_post();
        }

        $rowAction = self::post_text('row_action', 40);
        if ($rowAction !== '') {
            [$action, $id] = array_pad(explode(':', $rowAction, 2), 2, '0');
            $id = (int) $id;
            $service = $id > 0 ? $servicesSvc->get($id) : null;
            if ($service === null) {
                return 'Leistung nicht gefunden.';
            }
            if ($action === 'delete') {
                $servicesSvc->delete($id);
                self::redirect_with('services', $listQuery, 'success', 'Leistung „' . $service['title'] . '“ gelöscht.');
            }
            if ($action === 'activate' || $action === 'deactivate') {
                $servicesSvc->set_status_many([$id], $action === 'activate' ? 'active' : 'inactive');
                self::redirect_with('services', $listQuery, 'success', 'Leistung „' . $service['title'] . '“ ' . ($action === 'activate' ? 'aktiviert.' : 'deaktiviert.'));
            }

            return 'Ungültige Aktion.';
        }

        $bulk = self::post_text('bulk_action', 20);
        $ids  = self::posted_ids();
        if ($bulk === '' || $ids === []) {
            return 'Bitte eine Aktion und mindestens eine Leistung auswählen.';
        }
        $count = match ($bulk) {
            'activate'   => $servicesSvc->set_status_many($ids, 'active'),
            'deactivate' => $servicesSvc->set_status_many($ids, 'inactive'),
            'delete'     => $servicesSvc->delete_many($ids),
            default      => -1,
        };
        if ($count < 0) {
            return 'Ungültige Sammelaktion.';
        }
        $verb = ['activate' => 'aktiviert', 'deactivate' => 'deaktiviert', 'delete' => 'gelöscht'][$bulk];
        self::redirect_with('services', $listQuery, 'success', $count . ' Leistung(en) ' . $verb . '.');
    }

    private function save_service_from_post(): string
    {
        $servicesSvc = CMS_Booking_Services::instance();
        $id = (int) ($_POST['service_id'] ?? 0);
        $existing = $id > 0 ? $servicesSvc->get($id) : null;
        if ($id > 0 && $existing === null) {
            return 'Leistung nicht gefunden.';
        }

        $title = self::post_text('title', 255);
        if ($title === '') {
            return 'Bitte einen Titel angeben.';
        }

        $locationType = self::post_text('location_type', 20);
        if (!in_array($locationType, ['online', 'onsite', 'hybrid'], true)) {
            $locationType = 'online';
        }
        $bookingType = self::post_text('booking_type', 20);
        if (!in_array($bookingType, ['confirmation', 'instant', 'request'], true)) {
            $bookingType = 'confirmation';
        }
        $status = self::post_text('status', 20);
        if (!in_array($status, CMS_Booking_Services::STATUSES, true)) {
            $status = 'active';
        }

        $meetingRaw = trim((string) (is_scalar($_POST['meeting_url'] ?? null) ? $_POST['meeting_url'] : ''));
        $meetingUrl = '';
        if ($meetingRaw !== '') {
            $valid = filter_var($meetingRaw, FILTER_VALIDATE_URL);
            if ($valid === false || !in_array(strtolower((string) parse_url($meetingRaw, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                return 'Die Meeting-URL muss mit http:// oder https:// beginnen.';
            }
            $meetingUrl = mb_substr($meetingRaw, 0, 500);
        }

        $descriptionRaw = $_POST['description'] ?? '';
        $description = is_scalar($descriptionRaw)
            ? mb_substr(trim(strip_tags((string) $descriptionRaw)), 0, 5000)
            : '';

        $settings = [];
        if ($existing !== null && is_string($existing['settings_json'] ?? null)) {
            $decoded = json_decode((string) $existing['settings_json'], true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        $settings['daily_booking_limit'] = max(0, min(1000, (int) ($_POST['daily_booking_limit'] ?? 0)));

        $priceRaw = str_replace(',', '.', (string) (is_scalar($_POST['price'] ?? null) ? $_POST['price'] : '0'));
        $data = [
            'title'            => $title,
            'description'      => $description,
            'duration_min'     => max(5, min(1440, (int) ($_POST['duration_min'] ?? 60))),
            'buffer_min'       => max(0, min(480, (int) ($_POST['buffer_min'] ?? 15))),
            'price_cents'      => max(0, (int) round(((float) $priceRaw) * 100)),
            'location_type'    => $locationType,
            'meeting_url'      => $meetingUrl,
            'booking_type'     => $bookingType,
            'contact_template' => self::post_text('contact_template', 50),
            'status'           => $status,
            'sort_order'       => max(0, min(9999, (int) ($_POST['sort_order'] ?? 0))),
            'settings_json'    => (string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($existing !== null) {
            $servicesSvc->update($id, $data);
            self::redirect_with('services', ['view' => 'edit', 'id' => $id], 'success', 'Leistung gespeichert.');
        }

        $providerId = (int) ($_POST['provider_id'] ?? 0);
        if ($providerId <= 0 || CMS_Booking_Providers::instance()->get($providerId) === null) {
            return 'Bitte einen gültigen Anbieter wählen.';
        }
        $newId = $servicesSvc->create($data + ['provider_id' => $providerId]);
        self::redirect_with('services', ['view' => 'edit', 'id' => $newId], 'success', 'Leistung angelegt.');
    }

    public function render_services_page(): void
    {
        $servicesSvc = CMS_Booking_Services::instance();
        $error     = $this->postError;
        $csrfToken = self::generate_nonce('booking_services');
        $providers = CMS_Booking_Providers::instance()->get_options();

        if (self::get_text('view', 20) === 'edit') {
            $id = (int) ($_GET['id'] ?? 0);
            $service = $id > 0 ? $servicesSvc->get($id) : null;
            if ($id > 0 && $service === null) {
                $error = 'Leistung nicht gefunden.';
            } else {
                $serviceSettings = [];
                if ($service !== null && is_string($service['settings_json'] ?? null)) {
                    $decoded = json_decode((string) $service['settings_json'], true);
                    $serviceSettings = is_array($decoded) ? $decoded : [];
                }
                $presetProvider = (int) ($_GET['provider_id'] ?? 0);
                include CMS_BOOKING_PLUGIN_DIR . 'admin/views/service-edit.php';
                return;
            }
        }

        $search     = self::get_text('q', 100);
        $providerId = max(0, (int) ($_GET['provider_id'] ?? 0));
        $status     = self::get_text('status', 20);
        $status     = in_array($status, CMS_Booking_Services::STATUSES, true) ? $status : '';
        $query      = ['q' => $search, 'provider_id' => $providerId, 'status' => $status];
        $perPage    = 25;
        $total      = $servicesSvc->count($search, $providerId, $status);
        $pages      = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, (int) ($_GET['paged'] ?? 1)), $pages);
        $items      = $servicesSvc->get_all(($page - 1) * $perPage, $perPage, $search, $providerId, $status);

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/services.php';
    }
}
