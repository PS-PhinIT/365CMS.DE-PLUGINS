<?php
/**
 * CMS Booking – Admin Anbieter (Liste, Sammelaktionen, Anlegen/Bearbeiten, Verfügbarkeit)
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait CMS_Booking_Page_Providers_Trait
{
    public function handle_providers_post(): string
    {
        $error = self::guard_post('booking_providers');
        if ($error !== '') {
            return $error;
        }

        $providersSvc = CMS_Booking_Providers::instance();
        $listQuery = [
            'status' => in_array(self::post_text('status_filter', 20), CMS_Booking_Providers::STATUSES, true) ? self::post_text('status_filter', 20) : '',
            'q'      => self::post_text('q_filter', 100),
        ];

        // Anlegen / Bearbeiten
        if (isset($_POST['save_provider'])) {
            return $this->save_provider_from_post();
        }

        // Verfügbarkeit
        if (isset($_POST['save_weekly']) || isset($_POST['add_override']) || isset($_POST['delete_override'])) {
            return $this->save_availability_from_post();
        }

        // Einzelaktion: row_action = "<aktion>:<id>"
        $rowAction = self::post_text('row_action', 40);
        if ($rowAction !== '') {
            [$action, $id] = array_pad(explode(':', $rowAction, 2), 2, '0');
            $id = (int) $id;
            $provider = $id > 0 ? $providersSvc->get($id) : null;
            if ($provider === null) {
                return 'Anbieter nicht gefunden.';
            }
            switch ($action) {
                case 'activate':
                    $providersSvc->set_status($id, 'active');
                    self::redirect_with('providers', $listQuery, 'success', 'Anbieter „' . $provider['display_name'] . '“ aktiviert.');
                case 'deactivate':
                    $providersSvc->set_status($id, 'inactive');
                    self::redirect_with('providers', $listQuery, 'success', 'Anbieter „' . $provider['display_name'] . '“ deaktiviert.');
                case 'delete':
                    $providersSvc->delete($id);
                    self::redirect_with('providers', $listQuery, 'success', 'Anbieter „' . $provider['display_name'] . '“ samt Leistungen, Verfügbarkeiten und Buchungen gelöscht.');
                case 'sync':
                    if (!CMS_Booking_Integration::is_registered((string) $provider['source_plugin'])) {
                        return 'Für diesen Anbieter ist keine Integration aktiv – Synchronisation nicht möglich.';
                    }
                    try {
                        CMS_Booking_Integration::sync_provider((string) $provider['source_plugin'], (int) $provider['source_id']);
                    } catch (\Throwable $e) {
                        return 'Synchronisation fehlgeschlagen.';
                    }
                    self::redirect_with('providers', $listQuery, 'success', 'Anbieter synchronisiert.');
                default:
                    return 'Ungültige Aktion.';
            }
        }

        // Sammelaktion
        $bulk = self::post_text('bulk_action', 20);
        $ids  = self::posted_ids();
        if ($bulk === '' || $ids === []) {
            return 'Bitte eine Aktion und mindestens einen Anbieter auswählen.';
        }
        $count = match ($bulk) {
            'activate'   => $providersSvc->set_status_many($ids, 'active'),
            'deactivate' => $providersSvc->set_status_many($ids, 'inactive'),
            'delete'     => $providersSvc->delete_many($ids),
            default      => -1,
        };
        if ($count < 0) {
            return 'Ungültige Sammelaktion.';
        }
        $verb = ['activate' => 'aktiviert', 'deactivate' => 'deaktiviert', 'delete' => 'gelöscht'][$bulk];
        self::redirect_with('providers', $listQuery, 'success', $count . ' Anbieter ' . $verb . '.');
    }

    private function save_provider_from_post(): string
    {
        $providersSvc = CMS_Booking_Providers::instance();
        $id = (int) ($_POST['provider_id'] ?? 0);

        $name = self::post_text('display_name', 255);
        if ($name === '') {
            return 'Bitte einen Namen angeben.';
        }
        $emailRaw = self::post_text('email', 255);
        $email = $emailRaw === '' ? '' : (string) (filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ?: '');
        if ($emailRaw !== '' && $email === '') {
            return 'Die E-Mail-Adresse ist ungültig.';
        }
        $timezone = self::post_text('timezone', 80);
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = CMS_Booking_Settings::timezone();
        }
        $currency = self::post_text('currency', 3);
        if (!in_array($currency, ['EUR', 'CHF', 'USD', 'GBP'], true)) {
            $currency = CMS_Booking_Settings::currency();
        }
        $status = self::post_text('status', 20);
        if (!in_array($status, CMS_Booking_Providers::STATUSES, true)) {
            $status = 'active';
        }
        $bioRaw = $_POST['bio'] ?? '';
        $bio = is_scalar($bioRaw) ? mb_substr(trim(strip_tags((string) $bioRaw)), 0, 5000) : '';

        $existing = $id > 0 ? $providersSvc->get($id) : null;
        if ($id > 0 && $existing === null) {
            return 'Anbieter nicht gefunden.';
        }
        $settings = [];
        if ($existing !== null && is_string($existing['settings_json'] ?? null)) {
            $decoded = json_decode((string) $existing['settings_json'], true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        $settings['daily_booking_limit'] = max(0, min(1000, (int) ($_POST['daily_booking_limit'] ?? 0)));

        $data = [
            'display_name'  => $name,
            'email'         => $email,
            'phone'         => (string) preg_replace('/[^0-9+()\/\-. ]/', '', self::post_text('phone', 50)),
            'bio'           => $bio,
            'timezone'      => $timezone,
            'currency'      => $currency,
            'status'        => $status,
            'settings_json' => (string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($existing !== null) {
            $providersSvc->update($id, $data);
            self::redirect_with('providers', ['view' => 'edit', 'id' => $id], 'success', 'Anbieter gespeichert.');
        }

        $newId = $providersSvc->create_manual($data);
        self::redirect_with('providers', ['view' => 'availability', 'id' => $newId], 'success', 'Anbieter angelegt. Legen Sie jetzt den Wochenplan fest.');
    }

    private function save_availability_from_post(): string
    {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        if ($providerId <= 0 || CMS_Booking_Providers::instance()->get($providerId) === null) {
            return 'Anbieter nicht gefunden.';
        }
        $availability = CMS_Booking_Availability::instance();
        $back = ['view' => 'availability', 'id' => $providerId];
        $time = static function (mixed $value): string {
            $value = is_scalar($value) ? substr(trim((string) $value), 0, 5) : '';

            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : '';
        };

        if (isset($_POST['delete_override'])) {
            $overrideId = (int) $_POST['delete_override'];
            if (!$availability->delete($overrideId, $providerId)) {
                return 'Eintrag nicht gefunden.';
            }
            self::redirect_with('providers', $back, 'success', 'Ausnahme gelöscht.');
        }

        if (isset($_POST['add_override'])) {
            $date  = self::post_text('override_date', 10);
            $start = $time($_POST['override_start'] ?? '');
            $end   = $time($_POST['override_end'] ?? '');
            $type  = self::post_text('override_type', 10) === 'extra' ? 'extra' : 'blocked';
            if (!CMS_Booking_Bookings::is_date($date)) {
                return 'Bitte ein gültiges Datum angeben.';
            }
            if ($start === '' && $end === '' && $type === 'blocked') {
                $start = '00:00';
                $end = '23:59';
            }
            if ($start === '' || $end === '' || $end <= $start) {
                return 'Bitte eine gültige Zeitspanne angeben (Ende nach Beginn).';
            }
            $availability->create([
                'provider_id'   => $providerId,
                'specific_date' => $date,
                'start_time'    => $start,
                'end_time'      => $end,
                'is_blocked'    => $type === 'blocked' ? 1 : 0,
            ]);
            self::redirect_with('providers', $back, 'success', $type === 'blocked' ? 'Abwesenheit eingetragen.' : 'Zusätzliche Verfügbarkeit eingetragen.');
        }

        // Wochenplan: je Tag bis zu zwei Zeitfenster
        $weekly = [];
        $days = $_POST['weekly'] ?? [];
        if (!is_array($days)) {
            $days = [];
        }
        foreach (array_keys(CMS_Booking_Availability::day_labels()) as $dow) {
            $day = is_array($days[$dow] ?? null) ? $days[$dow] : [];
            if (empty($day['enabled'])) {
                continue;
            }
            foreach ([1, 2] as $slotNo) {
                $start = $time($day['start' . $slotNo] ?? '');
                $end   = $time($day['end' . $slotNo] ?? '');
                if ($start === '' && $end === '') {
                    continue;
                }
                if ($start === '' || $end === '' || $end <= $start) {
                    return 'Ungültiges Zeitfenster am ' . CMS_Booking_Availability::day_label($dow) . ' (Ende muss nach Beginn liegen).';
                }
                $weekly[] = ['day_of_week' => $dow, 'start_time' => $start, 'end_time' => $end];
            }
        }
        $availability->replace_weekly($providerId, $weekly);
        self::redirect_with('providers', $back, 'success', 'Wochenplan gespeichert (' . count($weekly) . ' Zeitfenster).');
    }

    public function render_providers_page(): void
    {
        $providersSvc = CMS_Booking_Providers::instance();
        $error     = $this->postError;
        $csrfToken = self::generate_nonce('booking_providers');
        $types     = CMS_Booking_Integration::get_provider_types();
        $view      = self::get_text('view', 20);

        if ($view === 'edit' || $view === 'availability') {
            $id = (int) ($_GET['id'] ?? 0);
            $provider = $id > 0 ? $providersSvc->get($id) : null;
            if ($view === 'availability' && $provider === null) {
                $error = 'Anbieter nicht gefunden.';
            } elseif ($view === 'edit' && $id > 0 && $provider === null) {
                $error = 'Anbieter nicht gefunden.';
            } elseif ($view === 'edit') {
                $settings = [];
                if ($provider !== null && is_string($provider['settings_json'] ?? null)) {
                    $decoded = json_decode((string) $provider['settings_json'], true);
                    $settings = is_array($decoded) ? $decoded : [];
                }
                include CMS_BOOKING_PLUGIN_DIR . 'admin/views/provider-edit.php';
                return;
            } else {
                $availability = CMS_Booking_Availability::instance();
                $weeklyRows = $availability->get_weekly($id);
                $weekly = [];
                foreach ($weeklyRows as $row) {
                    $weekly[(int) $row['day_of_week']][] = [substr((string) $row['start_time'], 0, 5), substr((string) $row['end_time'], 0, 5)];
                }
                $overrides = $availability->get_upcoming_overrides($id);
                include CMS_BOOKING_PLUGIN_DIR . 'admin/views/provider-availability.php';
                return;
            }
        }

        $status  = self::get_text('status', 20);
        $status  = in_array($status, CMS_Booking_Providers::STATUSES, true) ? $status : '';
        $search  = self::get_text('q', 100);
        $query   = ['status' => $status, 'q' => $search];
        $perPage = 25;
        $total   = $providersSvc->count($status, $search);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['paged'] ?? 1)), $pages);
        $items   = $providersSvc->get_all(($page - 1) * $perPage, $perPage, $status, $search);

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/providers.php';
    }
}
