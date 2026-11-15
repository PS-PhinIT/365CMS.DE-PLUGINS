<?php
/**
 * CMS Booking – Frontend Controller
 *
 * Routen:
 *   GET  /booking/{provider-slug}                  → Provider-Übersicht
 *   GET  /booking/{provider-slug}/{service-slug}    → Buchungsformular
 *   POST /booking/{provider-slug}/{service-slug}    → Buchung absenden
 *   GET  /booking/confirm/{booking-id}              → Bestätigungsseite
 *   GET  /booking/ical/{booking-id}                 → ICS-Download
 *   POST /booking/cancel/{booking-id}               → Selbst-Stornierung (Token + CSRF)
 *   GET  /api/booking/slots/{provider-id}/{date}    → Verfügbare Slots (JSON)
 *
 * Öffentliche POSTs: Der Core-Router verlangt für öffentliche Formulare ein Token. Das Plugin meldet
 * über den Filter `cms_public_form_persistent_token` sein eigenes Token (`booking_token`, Aktion
 * `booking_submit` bzw. `booking_cancel`) und prüft es anschließend einmalig selbst; zusätzlich wird
 * `csrf_token` als `form_guard`-Token für Cores ohne diesen Filter mitgesendet.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Frontend
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_routes();
        }
        return self::$instance;
    }

    private function __construct() {}

    /* ================================================================== */
    /*  Routen                                                             */
    /* ================================================================== */

    private function register_routes(): void
    {
        if (!class_exists('CMS\Router')) {
            return;
        }

        $router = \CMS\Router::instance();

        // Slots-API (AJAX)
        $router->addRoute('GET', '/api/booking/slots/:providerId/:date', function (string $providerId, string $date): void {
            $this->api_get_slots((int) $providerId, $date);
        });
        $router->addRoute('GET', '/en/api/booking/slots/:providerId/:date', function (string $providerId, string $date): void {
            $this->api_get_slots((int) $providerId, $date);
        });

        // ICS-Download
        $router->addRoute('GET', '/booking/ical/:bookingId', function (string $bookingId): void {
            $this->serve_ical((int) $bookingId);
        });
        $router->addRoute('GET', '/en/booking/ical/:bookingId', function (string $bookingId): void {
            $this->serve_ical((int) $bookingId);
        });

        // Selbst-Stornierung (vor der generischen POST-Route registrieren)
        $router->addRoute('POST', '/booking/cancel/:bookingId', function (string $bookingId): void {
            $this->process_cancellation((int) $bookingId);
        });
        $router->addRoute('POST', '/en/booking/cancel/:bookingId', function (string $bookingId): void {
            $this->process_cancellation((int) $bookingId);
        });

        // Bestätigungsseite
        $router->addRoute('GET', '/booking/confirm/:bookingId', function (string $bookingId): void {
            $this->render_confirmation((int) $bookingId);
        });
        $router->addRoute('GET', '/en/booking/confirm/:bookingId', function (string $bookingId): void {
            $this->render_confirmation((int) $bookingId);
        });

        // Service-Buchungsformular
        $router->addRoute('GET', '/booking/:providerSlug/:serviceSlug', function (string $providerSlug, string $serviceSlug): void {
            $this->render_service_booking($providerSlug, $serviceSlug);
        });
        $router->addRoute('POST', '/booking/:providerSlug/:serviceSlug', function (string $providerSlug, string $serviceSlug): void {
            $this->process_booking($providerSlug, $serviceSlug);
        });
        $router->addRoute('GET', '/en/booking/:providerSlug/:serviceSlug', function (string $providerSlug, string $serviceSlug): void {
            $this->render_service_booking($providerSlug, $serviceSlug);
        });
        $router->addRoute('POST', '/en/booking/:providerSlug/:serviceSlug', function (string $providerSlug, string $serviceSlug): void {
            $this->process_booking($providerSlug, $serviceSlug);
        });

        // Provider-Übersicht (Services-Liste)
        $router->addRoute('GET', '/booking/:providerSlug', function (string $providerSlug): void {
            $this->render_provider_page($providerSlug);
        });
        $router->addRoute('GET', '/en/booking/:providerSlug', function (string $providerSlug): void {
            $this->render_provider_page($providerSlug);
        });
    }

    /**
     * Filter `cms_public_form_persistent_token`: meldet dem Core-Router das Plugin-Token der
     * öffentlichen Buchungs-/Stornoformulare (nur für die eigenen POST-Pfade).
     */
    public static function public_form_token(mixed $current = null, mixed $routingUri = '', mixed $method = 'GET'): mixed
    {
        if (is_array($current) || strtoupper((string) $method) !== 'POST') {
            return $current;
        }

        $path = '/' . trim((string) $routingUri, '/');
        $path = (string) preg_replace('#^/en(?=/)#', '', $path);
        $token = $_POST['booking_token'] ?? '';
        if (!is_string($token) || $token === '') {
            return $current;
        }

        if (preg_match('#^/booking/cancel/\d+$#', $path) === 1) {
            return ['action' => 'booking_cancel', 'token' => $token];
        }
        if (preg_match('#^/booking/[^/]+/[^/]+$#', $path) === 1) {
            return ['action' => 'booking_submit', 'token' => $token];
        }

        return $current;
    }

    /**
     * @return array{csrf: string, booking: string}
     */
    private function form_tokens(string $action): array
    {
        if (!class_exists('CMS\Security')) {
            return ['csrf' => '', 'booking' => ''];
        }
        $security = \CMS\Security::instance();

        return [
            'csrf'    => $security->generateToken('form_guard'),
            'booking' => $security->generateToken($action),
        ];
    }

    /* ================================================================== */
    /*  Provider-Übersicht                                                 */
    /* ================================================================== */

    private function render_provider_page(string $providerSlug): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();

        $provider = CMS_Booking_Providers::instance()->get_by_slug($providerSlug);
        if (!$provider || $provider['status'] !== 'active') {
            $this->render_404();
            return;
        }

        $services = CMS_Booking_Services::instance()->get_by_provider((int) $provider['id'], 'active');
        $i18n = $this->template_translations($lang);
        $providerTitle = $this->i18n_entity_value($provider, 'display_name', $lang, (string) ($provider['display_name'] ?? ''));
        $providerBio = $this->i18n_entity_value($provider, 'bio', $lang, (string) ($provider['bio'] ?? ''));
        $siteName = defined('SITE_NAME') ? (string) SITE_NAME : '365CMS';

        // SEO
        $this->set_seo(
            $this->t('seo_provider_title', $lang, 'Termin buchen') . ' – ' . $providerTitle,
            $this->t('seo_provider_description', $lang, 'Buchen Sie einen Termin bei') . ' ' . $providerTitle,
            $this->localized_path('booking/' . rawurlencode((string) $provider['slug']), $lang)
        );

        $siteUrl = (string) (defined('SITE_URL') ? SITE_URL : '');
        $bookingBasePath = $this->localized_path('booking', $lang);
        include CMS_BOOKING_PLUGIN_DIR . 'templates/page-provider.php';
        exit;
    }

    /* ================================================================== */
    /*  Service-Buchung                                                    */
    /* ================================================================== */

    private function render_service_booking(string $providerSlug, string $serviceSlug): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();

        $provider = CMS_Booking_Providers::instance()->get_by_slug($providerSlug);
        if (!$provider || $provider['status'] !== 'active') {
            $this->render_404();
            return;
        }

        $service = CMS_Booking_Services::instance()->get_by_slug((int) $provider['id'], $serviceSlug);
        if (!$service || $service['status'] !== 'active') {
            $this->render_404();
            return;
        }

        // Kontakt-Template aus cms-contact laden?
        $useContactForm = $this->should_use_contact_form($service, $provider);

        $tokens       = $this->form_tokens('booking_submit');
        $csrfToken    = $tokens['csrf'];
        $bookingToken = $tokens['booking'];

        // Verfügbare Tage (Vorlauf-Fenster aus den Einstellungen, Tageslimits gebündelt)
        $availDates = $this->available_dates($provider, $service);

        $error   = '';
        $success = '';
        $old     = [];
        $i18n    = $this->template_translations($lang);
        $providerTitle = $this->i18n_entity_value($provider, 'display_name', $lang, (string) ($provider['display_name'] ?? ''));
        $serviceTitle = $this->i18n_entity_value($service, 'title', $lang, (string) ($service['title'] ?? ''));
        $serviceDescription = $this->i18n_entity_value($service, 'description', $lang, (string) ($service['description'] ?? ''));
        $siteName = defined('SITE_NAME') ? (string) SITE_NAME : '365CMS';

        $this->set_seo(
            $serviceTitle . ' – ' . $providerTitle,
            mb_substr(trim(strip_tags($serviceDescription)), 0, 160),
            $this->localized_path('booking/' . rawurlencode((string) $provider['slug']) . '/' . rawurlencode((string) $service['slug']), $lang)
        );

        $siteUrl = (string) (defined('SITE_URL') ? SITE_URL : '');
        $bookingBasePath = $this->localized_path('booking', $lang);
        $slotsApiPath = $this->localized_path('api/booking/slots', $lang);
        include CMS_BOOKING_PLUGIN_DIR . 'templates/page-booking.php';
        exit;
    }

    private function process_booking(string $providerSlug, string $serviceSlug): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();
        $i18n = $this->template_translations($lang);

        $provider = CMS_Booking_Providers::instance()->get_by_slug($providerSlug);
        $service  = $provider ? CMS_Booking_Services::instance()->get_by_slug((int) $provider['id'], $serviceSlug) : null;

        if (!$provider || !$service || $provider['status'] !== 'active' || $service['status'] !== 'active') {
            $this->render_404();
            return;
        }

        $error   = '';
        $success = '';
        $old     = [];
        foreach (['customer_name', 'customer_email', 'customer_phone', 'booking_date', 'start_time', 'notes'] as $field) {
            $old[$field] = $this->post_string($field, $field === 'notes' ? 2000 : 255);
        }

        // CSRF (eigenes Einmal-Token; Fallback: csrf_token mit Aktion booking_submit aus 3.x-Formularen)
        $security = class_exists('CMS\Security') ? \CMS\Security::instance() : null;
        $tokenOk = $security !== null && (
            $security->verifyToken($_POST['booking_token'] ?? '', 'booking_submit')
            || $security->verifyToken($_POST['csrf_token'] ?? '', 'booking_submit')
        );
        if (!$tokenOk) {
            $error = $this->t('error_security_check_failed', $lang, 'Sicherheitscheck fehlgeschlagen. Bitte versuchen Sie es erneut.');
        }

        // Honeypot
        if ($error === '' && $this->post_string('website_url', 255) !== '') {
            $error = $this->t('error_spam_detected', $lang, 'Spam erkannt.');
        }

        // Rate-Limit je IP (Einstellung „Buchungen je Stunde und IP“, 0 = aus)
        if ($error === '' && !$this->rate_limit_allows('booking_submit', CMS_Booking_Settings::int('booking_rate_limit', 10))) {
            $error = $this->t('error_rate_limited', $lang, 'Zu viele Buchungsversuche. Bitte versuchen Sie es später erneut.');
        }

        // Validierung
        if ($error === '') {
            $customerName  = sanitize_text_field($old['customer_name']);
            $customerEmail = filter_var($old['customer_email'], FILTER_VALIDATE_EMAIL);
            $customerPhone = (string) preg_replace('/[^0-9+()\/\-. ]/', '', sanitize_text_field($old['customer_phone']));
            $bookingDate   = $old['booking_date'];
            $startTime     = $old['start_time'];
            $notes         = mb_substr(trim(strip_tags($old['notes'])), 0, 2000);

            if ($customerName === '') {
                $error = $this->t('error_name_required', $lang, 'Bitte geben Sie Ihren Namen an.');
            } elseif (!$customerEmail) {
                $error = $this->t('error_valid_email_required', $lang, 'Bitte geben Sie eine gültige E-Mail-Adresse an.');
            } elseif (!CMS_Booking_Bookings::is_date($bookingDate)) {
                $error = $this->t('error_valid_date_required', $lang, 'Bitte wählen Sie ein gültiges Datum.');
            } elseif ($startTime === '' || !$this->is_valid_time($startTime)) {
                $error = $this->t('error_valid_time_required', $lang, 'Bitte wählen Sie eine Uhrzeit.');
            }
        }

        // Verfügbarkeit prüfen
        if ($error === '') {
            $durationMin = (int) $service['duration_min'];
            $bufferMin   = (int) $service['buffer_min'];

            // Booking-Advance-Window prüfen (Min/Max Vorlaufzeit)
            $advanceMinDays = max(0, CMS_Booking_Settings::int('booking_advance_min', 1));
            $advanceMaxDays = max(1, CMS_Booking_Settings::int('booking_advance_max', 90));
            $bookingTs      = strtotime($bookingDate);
            $todayTs        = strtotime('today');
            $diffDays       = (int) round(($bookingTs - $todayTs) / 86400);
            $noticeHours    = max(0, CMS_Booking_Settings::int('booking_min_notice_hours', 0));

            if ($diffDays < $advanceMinDays) {
                $error = sprintf(
                    $this->t('error_advance_min_days', $lang, 'Buchungen sind frühestens %d Tag(e) im Voraus möglich.'),
                    $advanceMinDays
                );
            } elseif ($diffDays > $advanceMaxDays) {
                $error = sprintf(
                    $this->t('error_advance_max_days', $lang, 'Buchungen sind maximal %d Tage im Voraus möglich.'),
                    $advanceMaxDays
                );
            }

            if ($error === '') {
                $providerTimezone = $this->provider_timezone($provider);
                $tz = new \DateTimeZone($providerTimezone);
                $bookingStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $bookingDate . ' ' . $startTime, $tz);
                $now = new \DateTimeImmutable('now', $tz);
                $minAllowed = $now->modify('+' . $noticeHours . ' hours');
                if (!$bookingStart || $bookingStart <= $now) {
                    $error = $this->t('error_slot_unavailable', $lang, 'Der gewählte Zeitpunkt ist leider nicht mehr verfügbar.');
                } elseif ($noticeHours > 0 && $bookingStart < $minAllowed) {
                    $error = sprintf(
                        $this->t('error_min_notice_hours', $lang, 'Buchungen müssen mindestens %d Stunde(n) im Voraus erfolgen.'),
                        $noticeHours
                    );
                }
            }

            if ($error === '' && $this->is_daily_limit_reached((int) $provider['id'], (int) $service['id'], $bookingDate, $provider, $service)) {
                $error = $this->daily_limit_error($lang, $bookingDate, $provider, $service);
            }

            if ($error === '') {
                $isAvailable = CMS_Booking_Availability::instance()->is_slot_available(
                    (int) $provider['id'],
                    $bookingDate,
                    $startTime,
                    $durationMin,
                    $bufferMin
                );

                if (!$isAvailable) {
                    $error = $this->t('error_slot_unavailable', $lang, 'Der gewählte Zeitpunkt ist leider nicht mehr verfügbar.');
                }
            }
        }

        // Buchung anlegen
        if ($error === '') {
            $endTs  = strtotime("{$bookingDate} {$startTime}") + ($durationMin * 60);
            $endTime = date('H:i', $endTs);

            try {
                // booking_type / auto_confirm auswerten: Sofortbuchung wird direkt bestätigt angelegt
                // (eine Eingangs-Mail mit Bestätigung statt zweier Mails).
                $bookingType = (string) ($service['booking_type'] ?? 'confirmation');
                $autoConfirm = CMS_Booking_Settings::bool('auto_confirm');
                $initialStatus = ($bookingType === 'instant' || $autoConfirm)
                    ? CMS_Booking_Bookings::STATUS_CONFIRMED
                    : CMS_Booking_Bookings::STATUS_PENDING;

                $this->record_rate_limit_attempt('booking_submit');

                $bookingId = CMS_Booking_Bookings::instance()->create([
                    'provider_id'   => (int) $provider['id'],
                    'service_id'    => (int) $service['id'],
                    'user_id'       => $this->get_current_user_id(),
                    'customer_name'  => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'booking_date'   => $bookingDate,
                    'start_time'     => $startTime,
                    'end_time'       => $endTime,
                    'duration_min'   => $durationMin,
                    'location_type'  => $service['location_type'] ?? 'online',
                    'meeting_url'    => $service['meeting_url'] ?? null,
                    'price_cents'    => (int) $service['price_cents'],
                    'currency'       => (string) ($provider['currency'] ?? CMS_Booking_Settings::currency()),
                    'notes'          => $notes,
                    'status'         => $initialStatus,
                ]);

                // Benachrichtigungen versendet CMS_Booking_Notifications über den Hook `booking_created`.
                $booking = CMS_Booking_Bookings::instance()->get($bookingId);
                if (!$booking) {
                    throw new \RuntimeException('Booking record missing after creation.');
                }

                // Zugangs-Token für Bestätigungsseite generieren
                $accessToken = CMS_Booking_Bookings::access_token($bookingId, (string) ($booking['ical_uid'] ?? ''));

                // Weiterleitung zur Bestätigungsseite (lokaler Pfad)
                $confirmUrl = (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . $this->localized_path('booking/confirm/' . $bookingId, $lang)
                    . '?token=' . rawurlencode($accessToken);
                header('Location: ' . $confirmUrl, true, 303);
                exit;
            } catch (\Throwable $e) {
                error_log('[cms-booking] booking not saved: ' . $e->getMessage());
                $error = $this->t('error_save_failed', $lang, 'Beim Speichern der Buchung ist ein Fehler aufgetreten.');
            }
        }

        // Bei Fehler: Formular erneut rendern
        if (!headers_sent()) {
            http_response_code(422);
        }
        $tokens        = $this->form_tokens('booking_submit');
        $csrfToken     = $tokens['csrf'];
        $bookingToken  = $tokens['booking'];
        $availDates    = $this->available_dates($provider, $service);
        $useContactForm = $this->should_use_contact_form($service, $provider);
        $providerTitle = $this->i18n_entity_value($provider, 'display_name', $lang, (string) ($provider['display_name'] ?? ''));
        $serviceTitle = $this->i18n_entity_value($service, 'title', $lang, (string) ($service['title'] ?? ''));
        $serviceDescription = $this->i18n_entity_value($service, 'description', $lang, (string) ($service['description'] ?? ''));
        $siteName = defined('SITE_NAME') ? (string) SITE_NAME : '365CMS';

        $siteUrl = (string) (defined('SITE_URL') ? SITE_URL : '');
        $bookingBasePath = $this->localized_path('booking', $lang);
        $slotsApiPath = $this->localized_path('api/booking/slots', $lang);
        include CMS_BOOKING_PLUGIN_DIR . 'templates/page-booking.php';
        exit;
    }

    /* ================================================================== */
    /*  Bestätigungsseite                                                  */
    /* ================================================================== */

    private function render_confirmation(int $bookingId): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();
        $i18n = $this->template_translations($lang);

        $booking = CMS_Booking_Bookings::instance()->get($bookingId);
        if (!$booking) {
            $this->render_404();
            return;
        }

        // Token-basierter Zugangsschutz (DSGVO)
        $token = $_GET['token'] ?? '';
        if (!CMS_Booking_Bookings::verify_access_token($bookingId, (string) ($booking['ical_uid'] ?? ''), $token)) {
            $this->render_access_denied($lang);
        }

        $accessToken = CMS_Booking_Bookings::access_token($bookingId, (string) ($booking['ical_uid'] ?? ''));
        if (!headers_sent()) {
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow');
        }

        $calExport = CMS_Booking_Calendar_Export::instance();
        $googleUrl = $calExport->google_calendar_url($booking);

        // Selbst-Stornierung
        $canCancel   = $this->can_customer_cancel($booking);
        $cancelTokens = $canCancel ? $this->form_tokens('booking_cancel') : ['csrf' => '', 'booking' => ''];
        $cancelUrl   = $this->localized_path('booking/cancel/' . $bookingId, $lang) . '?token=' . rawurlencode($accessToken);
        $notice      = (string) ($_GET['notice'] ?? '');
        $notice      = in_array($notice, ['cancelled', 'cancel_failed'], true) ? $notice : '';

        $this->set_seo(
            $this->t('seo_confirmation_title', $lang, 'Buchung bestätigt'),
            $this->t('seo_confirmation_description', $lang, 'Ihre Buchung wurde erfolgreich registriert.'),
            '',
            false
        );

        $siteUrl = (string) (defined('SITE_URL') ? SITE_URL : '');
        $bookingBasePath = $this->localized_path('booking', $lang);
        include CMS_BOOKING_PLUGIN_DIR . 'templates/page-confirmation.php';
        exit;
    }

    /* ================================================================== */
    /*  ICS-Download                                                       */
    /* ================================================================== */

    private function serve_ical(int $bookingId): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();

        $booking = CMS_Booking_Bookings::instance()->get($bookingId);
        if (!$booking) {
            http_response_code(404);
            echo htmlspecialchars($this->t('error_booking_not_found', $lang, 'Buchung nicht gefunden.'), ENT_QUOTES, 'UTF-8');
            exit;
        }

        // Token-basierter Zugangsschutz (DSGVO)
        if (!CMS_Booking_Bookings::verify_access_token($bookingId, (string) ($booking['ical_uid'] ?? ''), $_GET['token'] ?? '')) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo $this->t('access_denied', $lang, 'Zugriff verweigert');
            exit;
        }

        CMS_Booking_Calendar_Export::instance()->serve_ics($booking);
    }

    /* ================================================================== */
    /*  Slots-API                                                          */
    /* ================================================================== */

    private function api_get_slots(int $providerId, string $date): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');

        if ($providerId <= 0 || !CMS_Booking_Bookings::is_date($date)) {
            http_response_code(400);
            echo json_encode(['error' => $this->t('api_error_invalid_request', $lang, 'Ungültige Anfrage')]);
            exit;
        }

        $provider = CMS_Booking_Providers::instance()->get($providerId);
        if (!$provider || ($provider['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo json_encode(['error' => $this->t('api_error_provider_not_found', $lang, 'Anbieter nicht gefunden')]);
            exit;
        }

        // Service-ID optional per GET
        $serviceId   = (int) ($_GET['service_id'] ?? 0);
        $durationMin = 60;
        $bufferMin   = 15;

        if ($serviceId > 0) {
            $service = CMS_Booking_Services::instance()->get($serviceId);
            if ($service && (int) ($service['provider_id'] ?? 0) === $providerId && ($service['status'] ?? '') === 'active') {
                $durationMin = (int) $service['duration_min'];
                $bufferMin   = (int) $service['buffer_min'];
            } else {
                http_response_code(404);
                echo json_encode(['error' => $this->t('api_error_service_not_found', $lang, 'Leistung nicht gefunden')]);
                exit;
            }
        }

        if ($serviceId > 0 && isset($service) && is_array($service)) {
            if ($this->is_daily_limit_reached($providerId, $serviceId, $date, $provider, $service)) {
                echo json_encode(['success' => true, 'slots' => []]);
                exit;
            }
        }

        $slots = $this->is_within_advance_window($date)
            ? $this->filter_past_slots(
                CMS_Booking_Availability::instance()->get_available_slots($providerId, $date, $durationMin, $bufferMin),
                $date,
                $provider
            )
            : [];

        echo json_encode(['success' => true, 'slots' => array_values($slots)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    /* ================================================================== */
    /*  Helfer                                                             */
    /* ================================================================== */

    private function should_use_contact_form(array $service, array $provider): bool
    {
        if (!class_exists('CMS\PluginManager')) {
            return false;
        }
        if (!\CMS\PluginManager::instance()->isPluginActive('cms-contact')) {
            return false;
        }
        // Wenn der Service ein contact_template hat → ja
        return !empty($service['contact_template']) || !empty($provider['contact_form_id']);
    }

    private function set_seo(string $title, string $description, string $canonicalPath = '', bool $index = true): void
    {
        try {
            if (!class_exists('CMS\Services\SEOService')) {
                return;
            }
            $seo = method_exists('CMS\Services\SEOService', 'getInstance')
                ? \CMS\Services\SEOService::getInstance()
                : \CMS\Services\SEOService::instance();
            $meta = [
                'title'         => $title,
                'description'   => $description,
                'robots_index'  => $index,
                'robots_follow' => $index,
            ];
            if ($canonicalPath !== '' && defined('SITE_URL')) {
                $meta['canonical_url'] = rtrim((string) SITE_URL, '/') . $canonicalPath;
            }
            if (method_exists($seo, 'setRequestMeta')) {
                $seo->setRequestMeta($meta);
            } else {
                $seo->setTitle($title);
                $seo->setDescription($description);
            }
        } catch (\Throwable $e) {
            // SEO-Metadaten sind optional.
        }
    }

    private function render_404(): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();
        http_response_code(404);
        if (class_exists('CMS\ThemeManager')) {
            \CMS\ThemeManager::instance()->render('404');
        } else {
            echo '<h1>404 – ' . htmlspecialchars($this->t('not_found', $lang, 'Nicht gefunden'), ENT_QUOTES, 'UTF-8') . '</h1>';
        }
        exit;
    }

    private function get_current_user_id(): ?int
    {
        if (!class_exists('CMS\Auth') || !\CMS\Auth::isLoggedIn()) {
            return null;
        }
        $user = \CMS\Auth::getCurrentUser();
        $id = is_object($user) ? (int) ($user->id ?? 0) : 0;

        return $id > 0 ? $id : null;
    }

    /** Skalarer POST-Wert mit Längenbegrenzung (Arrays werden verworfen). */
    private function post_string(string $key, int $maxLength): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
    }

    private function client_ip(): string
    {
        if (class_exists('CMS\Security') && method_exists('CMS\Security', 'getClientIp')) {
            return (string) \CMS\Security::getClientIp();
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function rate_limit_allows(string $action, int $maxPerHour): bool
    {
        if ($maxPerHour <= 0 || !class_exists('CMS\Security')) {
            return true;
        }
        try {
            if (method_exists('CMS\Security', 'checkDbRateLimit')) {
                return \CMS\Security::checkDbRateLimit($this->client_ip(), $action, $maxPerHour, 3600);
            }

            return \CMS\Security::checkRateLimit($action . '_' . $this->client_ip(), $maxPerHour, 3600);
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function record_rate_limit_attempt(string $action): void
    {
        try {
            if (class_exists('CMS\Security') && method_exists('CMS\Security', 'recordDbRateLimitAttempt')) {
                \CMS\Security::recordDbRateLimitAttempt($this->client_ip(), $action);
            }
        } catch (\Throwable $e) {
            // Rate-Limit-Protokoll darf die Buchung nicht verhindern.
        }
    }

    private function render_access_denied(string $lang): never
    {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>'
            . htmlspecialchars($this->t('access_denied', $lang, 'Zugriff verweigert'), ENT_QUOTES, 'UTF-8') . '</title></head><body>';
        echo '<h1>' . htmlspecialchars($this->t('access_denied', $lang, 'Zugriff verweigert'), ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>' . htmlspecialchars($this->t('access_token_invalid', $lang, 'Ungültiger oder fehlender Zugangs-Token.'), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '</body></html>';
        exit;
    }

    /* ================================================================== */
    /*  Selbst-Stornierung                                                 */
    /* ================================================================== */

    /**
     * Darf der Kunde (Token-Inhaber bzw. Mitglied) diese Buchung selbst stornieren?
     * Einstellung „Selbst-Stornierung erlauben“ und Frist „Stornierungsfrist (Std.)“.
     */
    public function can_customer_cancel(array $booking): bool
    {
        if (!CMS_Booking_Settings::bool('allow_customer_cancel')) {
            return false;
        }
        if (!in_array((string) ($booking['status'] ?? ''), CMS_Booking_Bookings::ACTIVE_STATUSES, true)) {
            return false;
        }

        $timezone = (string) ($booking['provider_timezone'] ?? '');
        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = CMS_Booking_Settings::timezone();
        }
        try {
            $start = new \DateTimeImmutable(
                (string) $booking['booking_date'] . ' ' . substr((string) $booking['start_time'], 0, 5),
                new \DateTimeZone($timezone)
            );
        } catch (\Throwable $e) {
            return false;
        }

        $hours = max(0, CMS_Booking_Settings::int('cancellation_hours', 24));

        return $start->getTimestamp() - time() >= $hours * 3600;
    }

    private function process_cancellation(int $bookingId): void
    {
        $this->send_security_headers();
        $lang = $this->detect_lang();

        $booking = $bookingId > 0 ? CMS_Booking_Bookings::instance()->get($bookingId) : null;
        if (!$booking) {
            $this->render_404();
            return;
        }

        $accessToken = $_GET['token'] ?? ($_POST['access_token'] ?? '');
        if (!CMS_Booking_Bookings::verify_access_token($bookingId, (string) ($booking['ical_uid'] ?? ''), $accessToken)) {
            $this->render_access_denied($lang);
        }

        $security = class_exists('CMS\Security') ? \CMS\Security::instance() : null;
        $ok = $security !== null
            && $security->verifyToken($_POST['booking_token'] ?? '', 'booking_cancel')
            && $this->can_customer_cancel($booking);

        if ($ok) {
            try {
                $ok = CMS_Booking_Bookings::instance()->cancel($bookingId);
            } catch (\Throwable $e) {
                $ok = false;
            }
        }

        $target = $this->localized_path('booking/confirm/' . $bookingId, $lang)
            . '?token=' . rawurlencode(CMS_Booking_Bookings::access_token($bookingId, (string) ($booking['ical_uid'] ?? '')))
            . '&notice=' . ($ok ? 'cancelled' : 'cancel_failed');
        header('Location: ' . (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . $target, true, 303);
        exit;
    }

    private function is_valid_time(string $time): bool
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return false;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        return $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59;
    }

    private function send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    private function detect_lang(): string
    {
        if (function_exists('cms_plugin_public_language')) {
            return cms_plugin_public_language();
        }

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (preg_match('#^/en(?:/|$)#', $path) === 1) {
            return 'en';
        }

        return 'de';
    }

    private function localized_path(string $path, string $lang): string
    {
        if (function_exists('cms_plugin_public_localized_path')) {
            return cms_plugin_public_localized_path($path, $lang);
        }

        $normalized = trim($path, '/');
        if ($lang === 'en') {
            return '/en/' . $normalized;
        }

        return '/' . $normalized;
    }

    private function t(string $key, string $lang, string $fallback = ''): string
    {
        $messages = $this->public_messages();
        if (!isset($messages[$key])) {
            return $fallback !== '' ? $fallback : $key;
        }

        if (function_exists('cms_plugin_public_i18n_value')) {
            return cms_plugin_public_i18n_value($messages[$key], 'text', $lang, $fallback !== '' ? $fallback : (string) ($messages[$key]['text'] ?? ''));
        }

        if ($lang === 'en' && !empty($messages[$key]['text_en'])) {
            return (string) $messages[$key]['text_en'];
        }

        return (string) ($messages[$key]['text'] ?? ($fallback !== '' ? $fallback : $key));
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function public_messages(): array
    {
        return [
            'seo_provider_title' => ['text' => 'Termin buchen', 'text_en' => 'Book appointment'],
            'seo_provider_description' => ['text' => 'Buchen Sie einen Termin bei', 'text_en' => 'Book an appointment with'],
            'seo_confirmation_title' => ['text' => 'Buchung bestätigt', 'text_en' => 'Booking confirmed'],
            'seo_confirmation_description' => ['text' => 'Ihre Buchung wurde erfolgreich registriert.', 'text_en' => 'Your booking was successfully registered.'],
            'error_security_check_failed' => ['text' => 'Sicherheitscheck fehlgeschlagen. Bitte versuchen Sie es erneut.', 'text_en' => 'Security check failed. Please try again.'],
            'error_spam_detected' => ['text' => 'Spam erkannt.', 'text_en' => 'Spam detected.'],
            'error_name_required' => ['text' => 'Bitte geben Sie Ihren Namen an.', 'text_en' => 'Please provide your name.'],
            'error_valid_email_required' => ['text' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.', 'text_en' => 'Please provide a valid email address.'],
            'error_valid_date_required' => ['text' => 'Bitte wählen Sie ein gültiges Datum.', 'text_en' => 'Please select a valid date.'],
            'error_valid_time_required' => ['text' => 'Bitte wählen Sie eine Uhrzeit.', 'text_en' => 'Please select a time.'],
            'error_advance_min_days' => ['text' => 'Buchungen sind frühestens %d Tag(e) im Voraus möglich.', 'text_en' => 'Bookings are possible at least %d day(s) in advance.'],
            'error_advance_max_days' => ['text' => 'Buchungen sind maximal %d Tage im Voraus möglich.', 'text_en' => 'Bookings are possible up to %d days in advance.'],
            'error_min_notice_hours' => ['text' => 'Buchungen müssen mindestens %d Stunde(n) im Voraus erfolgen.', 'text_en' => 'Bookings must be made at least %d hour(s) in advance.'],
            'error_slot_unavailable' => ['text' => 'Der gewählte Zeitpunkt ist leider nicht mehr verfügbar.', 'text_en' => 'The selected time is no longer available.'],
            'error_save_failed' => ['text' => 'Beim Speichern der Buchung ist ein Fehler aufgetreten.', 'text_en' => 'An error occurred while saving the booking.'],
            'error_rate_limited' => ['text' => 'Zu viele Buchungsversuche. Bitte versuchen Sie es später erneut.', 'text_en' => 'Too many booking attempts. Please try again later.'],
            'cancel_button' => ['text' => 'Termin stornieren', 'text_en' => 'Cancel appointment'],
            'cancel_confirm' => ['text' => 'Möchten Sie diesen Termin wirklich stornieren?', 'text_en' => 'Do you really want to cancel this appointment?'],
            'cancel_done' => ['text' => 'Ihr Termin wurde storniert.', 'text_en' => 'Your appointment has been cancelled.'],
            'cancel_failed' => ['text' => 'Die Stornierung war nicht möglich (Frist abgelaufen oder Formular ungültig).', 'text_en' => 'Cancellation was not possible (deadline passed or form invalid).'],
            'cancel_hint' => ['text' => 'Eine Stornierung ist bis %d Stunde(n) vor Terminbeginn möglich.', 'text_en' => 'Cancellation is possible up to %d hour(s) before the appointment.'],
            'error_daily_limit_global' => ['text' => 'Für diesen Tag ist das Tageslimit erreicht.', 'text_en' => 'The daily booking limit is reached for this day.'],
            'error_daily_limit_provider' => ['text' => 'Für diesen Anbieter ist das Tageslimit erreicht.', 'text_en' => 'The provider daily limit is reached for this day.'],
            'error_daily_limit_service' => ['text' => 'Für diese Leistung ist das Tageslimit erreicht.', 'text_en' => 'The service daily limit is reached for this day.'],
            'access_denied' => ['text' => 'Zugriff verweigert', 'text_en' => 'Access denied'],
            'access_token_invalid' => ['text' => 'Ungültiger oder fehlender Zugangs-Token.', 'text_en' => 'Invalid or missing access token.'],
            'error_booking_not_found' => ['text' => 'Buchung nicht gefunden.', 'text_en' => 'Booking not found.'],
            'api_error_invalid_request' => ['text' => 'Ungültige Anfrage', 'text_en' => 'Invalid request'],
            'api_error_provider_not_found' => ['text' => 'Anbieter nicht gefunden', 'text_en' => 'Provider not found'],
            'api_error_service_not_found' => ['text' => 'Leistung nicht gefunden', 'text_en' => 'Service not found'],
            'not_found' => ['text' => 'Nicht gefunden', 'text_en' => 'Not found'],
            'location_online' => ['text' => 'Online', 'text_en' => 'Online'],
            'location_onsite' => ['text' => 'Vor Ort', 'text_en' => 'On-site'],
            'location_hybrid' => ['text' => 'Hybrid', 'text_en' => 'Hybrid'],
            'location_online_appointment' => ['text' => 'Online-Termin', 'text_en' => 'Online meeting'],
            'location_online_or_onsite' => ['text' => 'Online oder vor Ort', 'text_en' => 'Online or on-site'],
            'location_online_slash_onsite' => ['text' => 'Online / Vor Ort', 'text_en' => 'Online / On-site'],
            'pricing_free' => ['text' => 'Kostenlos', 'text_en' => 'Free'],
            'time_o_clock' => ['text' => 'Uhr', 'text_en' => 'o\'clock'],
            'duration_minutes' => ['text' => 'Min.', 'text_en' => 'min'],
            'js_loading_slots' => ['text' => 'Zeitfenster werden geladen…', 'text_en' => 'Loading time slots...'],
            'js_no_slots' => ['text' => 'Keine freien Zeiten an diesem Tag.', 'text_en' => 'No free slots on this day.'],
            'js_slot_load_error' => ['text' => 'Fehler beim Laden der Zeitfenster.', 'text_en' => 'Error loading time slots.'],
            'js_choose_date_first' => ['text' => 'Bitte zuerst ein Datum wählen.', 'text_en' => 'Please select a date first.'],
            'js_summary_at' => ['text' => 'um', 'text_en' => 'at'],
            'month_01' => ['text' => 'Januar', 'text_en' => 'January'],
            'month_02' => ['text' => 'Februar', 'text_en' => 'February'],
            'month_03' => ['text' => 'März', 'text_en' => 'March'],
            'month_04' => ['text' => 'April', 'text_en' => 'April'],
            'month_05' => ['text' => 'Mai', 'text_en' => 'May'],
            'month_06' => ['text' => 'Juni', 'text_en' => 'June'],
            'month_07' => ['text' => 'Juli', 'text_en' => 'July'],
            'month_08' => ['text' => 'August', 'text_en' => 'August'],
            'month_09' => ['text' => 'September', 'text_en' => 'September'],
            'month_10' => ['text' => 'Oktober', 'text_en' => 'October'],
            'month_11' => ['text' => 'November', 'text_en' => 'November'],
            'month_12' => ['text' => 'Dezember', 'text_en' => 'December'],
            'weekday_mon_short' => ['text' => 'Mo', 'text_en' => 'Mon'],
            'weekday_tue_short' => ['text' => 'Di', 'text_en' => 'Tue'],
            'weekday_wed_short' => ['text' => 'Mi', 'text_en' => 'Wed'],
            'weekday_thu_short' => ['text' => 'Do', 'text_en' => 'Thu'],
            'weekday_fri_short' => ['text' => 'Fr', 'text_en' => 'Fri'],
            'weekday_sat_short' => ['text' => 'Sa', 'text_en' => 'Sat'],
            'weekday_sun_short' => ['text' => 'So', 'text_en' => 'Sun'],
            'weekday_0' => ['text' => 'Sonntag', 'text_en' => 'Sunday'],
            'weekday_1' => ['text' => 'Montag', 'text_en' => 'Monday'],
            'weekday_2' => ['text' => 'Dienstag', 'text_en' => 'Tuesday'],
            'weekday_3' => ['text' => 'Mittwoch', 'text_en' => 'Wednesday'],
            'weekday_4' => ['text' => 'Donnerstag', 'text_en' => 'Thursday'],
            'weekday_5' => ['text' => 'Freitag', 'text_en' => 'Friday'],
            'weekday_6' => ['text' => 'Samstag', 'text_en' => 'Saturday'],
            'provider_empty_title' => ['text' => 'Aktuell keine Terminart verfügbar', 'text_en' => 'Currently no appointment type available'],
            'provider_empty_text' => ['text' => 'Bitte versuchen Sie es später erneut.', 'text_en' => 'Please try again later.'],
            'provider_services_aria' => ['text' => 'Buchbare Leistungen', 'text_en' => 'Bookable services'],
            'provider_cta_book_now' => ['text' => 'Jetzt buchen →', 'text_en' => 'Book now →'],
            'booking_choose_date' => ['text' => 'Datum wählen', 'text_en' => 'Choose date'],
            'booking_choose_time' => ['text' => 'Uhrzeit wählen', 'text_en' => 'Choose time'],
            'booking_your_details' => ['text' => 'Ihre Daten', 'text_en' => 'Your details'],
            'booking_duration_label' => ['text' => 'Dauer', 'text_en' => 'Duration'],
            'booking_price_label' => ['text' => 'Preis', 'text_en' => 'Price'],
            'booking_phone_label' => ['text' => 'Telefon', 'text_en' => 'Phone'],
            'booking_notes_label' => ['text' => 'Nachricht / Anmerkungen', 'text_en' => 'Message / Notes'],
            'booking_placeholder_name' => ['text' => 'Max Mustermann', 'text_en' => 'John Doe'],
            'booking_placeholder_email' => ['text' => 'max@muster.de', 'text_en' => 'john@example.com'],
            'booking_placeholder_notes' => ['text' => 'Haben Sie besondere Wünsche?', 'text_en' => 'Do you have special requests?'],
            'booking_submit' => ['text' => 'Verbindlich buchen', 'text_en' => 'Confirm booking'],
            'confirmation_page_title' => ['text' => 'Buchung bestätigt', 'text_en' => 'Booking confirmed'],
            'confirmation_thank_you' => ['text' => 'Vielen Dank für Ihre Buchung!', 'text_en' => 'Thank you for your booking!'],
            'confirmation_subtitle_confirmed' => ['text' => 'Ihr Termin wurde bestätigt.', 'text_en' => 'Your appointment has been confirmed.'],
            'confirmation_subtitle_pending' => ['text' => 'Ihre Buchung ist eingegangen und wird in Kürze bestätigt.', 'text_en' => 'Your booking was received and will be confirmed shortly.'],
            'confirmation_booking_no' => ['text' => 'Buchungs-Nr.', 'text_en' => 'Booking no.'],
            'confirmation_service' => ['text' => 'Leistung', 'text_en' => 'Service'],
            'confirmation_provider' => ['text' => 'Anbieter', 'text_en' => 'Provider'],
            'confirmation_date' => ['text' => 'Datum', 'text_en' => 'Date'],
            'confirmation_time' => ['text' => 'Uhrzeit', 'text_en' => 'Time'],
            'confirmation_location' => ['text' => 'Ort', 'text_en' => 'Location'],
            'confirmation_price' => ['text' => 'Preis', 'text_en' => 'Price'],
            'confirmation_add_to_calendar' => ['text' => 'Zum Kalender hinzufügen:', 'text_en' => 'Add to calendar:'],
            'confirmation_google_calendar' => ['text' => 'Google Kalender', 'text_en' => 'Google Calendar'],
            'confirmation_pending_notice' => ['text' => 'Sie erhalten eine E-Mail, sobald Ihre Buchung bestätigt wurde.', 'text_en' => 'You will receive an email once your booking has been confirmed.'],
            'confirmation_sent_prefix' => ['text' => 'Eine Bestätigung wurde an', 'text_en' => 'A confirmation was sent to'],
            'confirmation_sent_suffix' => ['text' => 'gesendet.', 'text_en' => 'sent.'],
            'confirmation_back_home' => ['text' => '← Zur Startseite', 'text_en' => '← Back to homepage'],
            'confirmation_open_meeting_link' => ['text' => 'Meeting-Link öffnen ↗', 'text_en' => 'Open meeting link ↗'],
        ];
    }

    /**
     * @return array<string,string|array<int,string>>
     */
    private function template_translations(string $lang): array
    {
        return [
            'lang' => $lang,
            'bookingBasePath' => $this->localized_path('booking', $lang),
            'slotsApiPath' => $this->localized_path('api/booking/slots', $lang),
            'locationOnline' => $this->t('location_online', $lang, 'Online'),
            'locationOnsite' => $this->t('location_onsite', $lang, 'Vor Ort'),
            'locationHybrid' => $this->t('location_hybrid', $lang, 'Hybrid'),
            'locationOnlineAppointment' => $this->t('location_online_appointment', $lang, 'Online-Termin'),
            'locationOnlineOrOnsite' => $this->t('location_online_or_onsite', $lang, 'Online oder vor Ort'),
            'locationOnlineSlashOnsite' => $this->t('location_online_slash_onsite', $lang, 'Online / Vor Ort'),
            'freeLabel' => $this->t('pricing_free', $lang, 'Kostenlos'),
            'clockLabel' => $this->t('time_o_clock', $lang, 'Uhr'),
            'minutesShort' => $this->t('duration_minutes', $lang, 'Min.'),
            'jsLoadingSlots' => $this->t('js_loading_slots', $lang, 'Zeitfenster werden geladen…'),
            'jsNoSlots' => $this->t('js_no_slots', $lang, 'Keine freien Zeiten an diesem Tag.'),
            'jsSlotLoadError' => $this->t('js_slot_load_error', $lang, 'Fehler beim Laden der Zeitfenster.'),
            'jsChooseDateFirst' => $this->t('js_choose_date_first', $lang, 'Bitte zuerst ein Datum wählen.'),
            'jsSummaryAt' => $this->t('js_summary_at', $lang, 'um'),
            'providerEmptyTitle' => $this->t('provider_empty_title', $lang, 'Aktuell keine Terminart verfügbar'),
            'providerEmptyText' => $this->t('provider_empty_text', $lang, 'Bitte versuchen Sie es später erneut.'),
            'providerServicesAria' => $this->t('provider_services_aria', $lang, 'Buchbare Leistungen'),
            'providerCtaBookNow' => $this->t('provider_cta_book_now', $lang, 'Jetzt buchen →'),
            'bookingChooseDate' => $this->t('booking_choose_date', $lang, 'Datum wählen'),
            'bookingChooseTime' => $this->t('booking_choose_time', $lang, 'Uhrzeit wählen'),
            'bookingYourDetails' => $this->t('booking_your_details', $lang, 'Ihre Daten'),
            'bookingDurationLabel' => $this->t('booking_duration_label', $lang, 'Dauer'),
            'bookingPriceLabel' => $this->t('booking_price_label', $lang, 'Preis'),
            'bookingPhoneLabel' => $this->t('booking_phone_label', $lang, 'Telefon'),
            'bookingNotesLabel' => $this->t('booking_notes_label', $lang, 'Nachricht / Anmerkungen'),
            'bookingPlaceholderName' => $this->t('booking_placeholder_name', $lang, 'Max Mustermann'),
            'bookingPlaceholderEmail' => $this->t('booking_placeholder_email', $lang, 'max@muster.de'),
            'bookingPlaceholderNotes' => $this->t('booking_placeholder_notes', $lang, 'Haben Sie besondere Wünsche?'),
            'bookingSubmit' => $this->t('booking_submit', $lang, 'Verbindlich buchen'),
            'confirmationPageTitle' => $this->t('confirmation_page_title', $lang, 'Buchung bestätigt'),
            'confirmationThankYou' => $this->t('confirmation_thank_you', $lang, 'Vielen Dank für Ihre Buchung!'),
            'confirmationSubtitleConfirmed' => $this->t('confirmation_subtitle_confirmed', $lang, 'Ihr Termin wurde bestätigt.'),
            'confirmationSubtitlePending' => $this->t('confirmation_subtitle_pending', $lang, 'Ihre Buchung ist eingegangen und wird in Kürze bestätigt.'),
            'confirmationBookingNo' => $this->t('confirmation_booking_no', $lang, 'Buchungs-Nr.'),
            'confirmationService' => $this->t('confirmation_service', $lang, 'Leistung'),
            'confirmationProvider' => $this->t('confirmation_provider', $lang, 'Anbieter'),
            'confirmationDate' => $this->t('confirmation_date', $lang, 'Datum'),
            'confirmationTime' => $this->t('confirmation_time', $lang, 'Uhrzeit'),
            'confirmationLocation' => $this->t('confirmation_location', $lang, 'Ort'),
            'confirmationPrice' => $this->t('confirmation_price', $lang, 'Preis'),
            'confirmationAddToCalendar' => $this->t('confirmation_add_to_calendar', $lang, 'Zum Kalender hinzufügen:'),
            'confirmationGoogleCalendar' => $this->t('confirmation_google_calendar', $lang, 'Google Kalender'),
            'confirmationPendingNotice' => $this->t('confirmation_pending_notice', $lang, 'Sie erhalten eine E-Mail, sobald Ihre Buchung bestätigt wurde.'),
            'confirmationSentPrefix' => $this->t('confirmation_sent_prefix', $lang, 'Eine Bestätigung wurde an'),
            'confirmationSentSuffix' => $this->t('confirmation_sent_suffix', $lang, 'gesendet.'),
            'confirmationBackHome' => $this->t('confirmation_back_home', $lang, '← Zur Startseite'),
            'confirmationOpenMeetingLink' => $this->t('confirmation_open_meeting_link', $lang, 'Meeting-Link öffnen ↗'),
            'cancelButton' => $this->t('cancel_button', $lang, 'Termin stornieren'),
            'cancelConfirm' => $this->t('cancel_confirm', $lang, 'Möchten Sie diesen Termin wirklich stornieren?'),
            'cancelDone' => $this->t('cancel_done', $lang, 'Ihr Termin wurde storniert.'),
            'cancelFailed' => $this->t('cancel_failed', $lang, 'Die Stornierung war nicht möglich.'),
            'cancelHint' => sprintf($this->t('cancel_hint', $lang, 'Eine Stornierung ist bis %d Stunde(n) vor Terminbeginn möglich.'), max(0, CMS_Booking_Settings::int('cancellation_hours', 24))),
            'monthNames' => [
                $this->t('month_01', $lang, 'Januar'),
                $this->t('month_02', $lang, 'Februar'),
                $this->t('month_03', $lang, 'März'),
                $this->t('month_04', $lang, 'April'),
                $this->t('month_05', $lang, 'Mai'),
                $this->t('month_06', $lang, 'Juni'),
                $this->t('month_07', $lang, 'Juli'),
                $this->t('month_08', $lang, 'August'),
                $this->t('month_09', $lang, 'September'),
                $this->t('month_10', $lang, 'Oktober'),
                $this->t('month_11', $lang, 'November'),
                $this->t('month_12', $lang, 'Dezember'),
            ],
            'dayLabels' => [
                $this->t('weekday_mon_short', $lang, 'Mo'),
                $this->t('weekday_tue_short', $lang, 'Di'),
                $this->t('weekday_wed_short', $lang, 'Mi'),
                $this->t('weekday_thu_short', $lang, 'Do'),
                $this->t('weekday_fri_short', $lang, 'Fr'),
                $this->t('weekday_sat_short', $lang, 'Sa'),
                $this->t('weekday_sun_short', $lang, 'So'),
            ],
            'weekdayNames' => [
                $this->t('weekday_0', $lang, 'Sonntag'),
                $this->t('weekday_1', $lang, 'Montag'),
                $this->t('weekday_2', $lang, 'Dienstag'),
                $this->t('weekday_3', $lang, 'Mittwoch'),
                $this->t('weekday_4', $lang, 'Donnerstag'),
                $this->t('weekday_5', $lang, 'Freitag'),
                $this->t('weekday_6', $lang, 'Samstag'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private function i18n_entity_value(array $values, string $key, string $lang, string $fallback = ''): string
    {
        if (function_exists('cms_plugin_public_i18n_value')) {
            return cms_plugin_public_i18n_value($values, $key, $lang, $fallback);
        }

        if ($lang === 'en' && isset($values[$key . '_en']) && $values[$key . '_en'] !== '') {
            return (string) $values[$key . '_en'];
        }

        if (isset($values[$key]) && $values[$key] !== '') {
            return (string) $values[$key];
        }

        return $fallback;
    }

    /**
     * @param array<string,mixed> $provider
     */
    private function provider_timezone(array $provider): string
    {
        $timezone = (string) ($provider['timezone'] ?? '');
        if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return CMS_Booking_Settings::timezone();
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $service
     */
    private function is_daily_limit_reached(int $providerId, int $serviceId, string $date, array $provider, array $service): bool
    {
        $globalLimit = max(0, CMS_Booking_Settings::int('booking_daily_limit', 0));
        $providerLimit = $this->json_int_setting($provider['settings_json'] ?? null, 'daily_booking_limit');
        $serviceLimit = $this->json_int_setting($service['settings_json'] ?? null, 'daily_booking_limit');

        if ($globalLimit > 0) {
            $providerCount = CMS_Booking_Bookings::instance()->count_active_for_date($providerId, $date);
            if ($providerCount >= $globalLimit) {
                return true;
            }
        }

        if ($providerLimit > 0) {
            $providerCount = CMS_Booking_Bookings::instance()->count_active_for_date($providerId, $date);
            if ($providerCount >= $providerLimit) {
                return true;
            }
        }

        if ($serviceLimit > 0) {
            $serviceCount = CMS_Booking_Bookings::instance()->count_active_for_date($providerId, $date, $serviceId);
            if ($serviceCount >= $serviceLimit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $service
     */
    private function daily_limit_error(string $lang, string $date, array $provider, array $service): string
    {
        $providerId = (int) ($provider['id'] ?? 0);
        $serviceId = (int) ($service['id'] ?? 0);

        if ($date !== '' && $serviceId > 0) {
            $serviceLimit = $this->json_int_setting($service['settings_json'] ?? null, 'daily_booking_limit');
            if ($serviceLimit > 0) {
                $serviceCount = CMS_Booking_Bookings::instance()->count_active_for_date($providerId, $date, $serviceId);
                if ($serviceCount >= $serviceLimit) {
                    return $this->t('error_daily_limit_service', $lang, 'Für diese Leistung ist das Tageslimit erreicht.');
                }
            }
        }

        if ($date !== '' && $providerId > 0) {
            $providerLimit = $this->json_int_setting($provider['settings_json'] ?? null, 'daily_booking_limit');
            if ($providerLimit > 0) {
                $providerCount = CMS_Booking_Bookings::instance()->count_active_for_date($providerId, $date);
                if ($providerCount >= $providerLimit) {
                    return $this->t('error_daily_limit_provider', $lang, 'Für diesen Anbieter ist das Tageslimit erreicht.');
                }
            }
        }

        return $this->t('error_daily_limit_global', $lang, 'Für diesen Tag ist das Tageslimit erreicht.');
    }

    /**
     * @param array<int,string> $dates
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $service
     * @return array<int,string>
     */
    private function filter_dates_by_daily_limits(array $dates, array $provider, array $service): array
    {
        $providerId = (int) ($provider['id'] ?? 0);
        $serviceId = (int) ($service['id'] ?? 0);
        if ($providerId <= 0 || $serviceId <= 0 || empty($dates)) {
            return $dates;
        }

        $globalLimit   = max(0, CMS_Booking_Settings::int('booking_daily_limit', 0));
        $providerLimit = $this->json_int_setting($provider['settings_json'] ?? null, 'daily_booking_limit');
        $serviceLimit  = $this->json_int_setting($service['settings_json'] ?? null, 'daily_booking_limit');
        if ($globalLimit === 0 && $providerLimit === 0 && $serviceLimit === 0) {
            return array_values(array_map('strval', $dates));
        }

        // Je eine gruppierte Abfrage für den gesamten Zeitraum statt bis zu drei Abfragen je Tag.
        $from = (string) min($dates);
        $to   = (string) max($dates);
        $bookings = CMS_Booking_Bookings::instance();
        $providerCounts = ($globalLimit > 0 || $providerLimit > 0) ? $bookings->count_active_between($providerId, $from, $to) : [];
        $serviceCounts  = $serviceLimit > 0 ? $bookings->count_active_between($providerId, $from, $to, $serviceId) : [];

        $filtered = [];
        foreach ($dates as $date) {
            $date = (string) $date;
            $providerCount = $providerCounts[$date] ?? 0;
            if ($globalLimit > 0 && $providerCount >= $globalLimit) {
                continue;
            }
            if ($providerLimit > 0 && $providerCount >= $providerLimit) {
                continue;
            }
            if ($serviceLimit > 0 && ($serviceCounts[$date] ?? 0) >= $serviceLimit) {
                continue;
            }
            $filtered[] = $date;
        }

        return $filtered;
    }

    /**
     * Buchbare Tage einer Leistung innerhalb des Vorlauf-Fensters (min./max. Tage im Voraus).
     *
     * @return list<string>
     */
    private function available_dates(array $provider, array $service): array
    {
        $minDays = max(0, CMS_Booking_Settings::int('booking_advance_min', 1));
        $maxDays = max(1, min(366, CMS_Booking_Settings::int('booking_advance_max', 90)));
        $dates = CMS_Booking_Availability::instance()->get_available_dates(
            (int) $provider['id'],
            (int) $service['duration_min'],
            $maxDays,
            (int) ($service['buffer_min'] ?? 0),
            $minDays
        );
        $dates = $this->filter_dates_by_daily_limits($dates, $provider, $service);

        // Heute: nur, wenn noch ein künftiger Slot übrig ist.
        $today = date('Y-m-d');
        if (in_array($today, $dates, true)) {
            $slots = $this->filter_past_slots(
                CMS_Booking_Availability::instance()->get_available_slots((int) $provider['id'], $today, (int) $service['duration_min'], (int) ($service['buffer_min'] ?? 0)),
                $today,
                $provider
            );
            if ($slots === []) {
                $dates = array_values(array_filter($dates, static fn (string $d): bool => $d !== $today));
            }
        }

        return $dates;
    }

    private function is_within_advance_window(string $date): bool
    {
        $minDays = max(0, CMS_Booking_Settings::int('booking_advance_min', 1));
        $maxDays = max(1, CMS_Booking_Settings::int('booking_advance_max', 90));
        $diff = (int) round((strtotime($date) - strtotime('today')) / 86400);

        return $diff >= $minDays && $diff <= $maxDays;
    }

    /**
     * Entfernt Slots, die bereits begonnen haben bzw. die Mindest-Vorlaufzeit (Stunden) unterschreiten.
     *
     * @param list<string> $slots
     * @return list<string>
     */
    private function filter_past_slots(array $slots, string $date, array $provider): array
    {
        try {
            $tz  = new \DateTimeZone($this->provider_timezone($provider));
            $min = (new \DateTimeImmutable('now', $tz))->modify('+' . max(0, CMS_Booking_Settings::int('booking_min_notice_hours', 0)) . ' hours');
        } catch (\Throwable $e) {
            return $slots;
        }

        return array_values(array_filter($slots, static function (string $slot) use ($date, $tz, $min): bool {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $slot, $tz);

            return $start !== false && $start > $min;
        }));
    }

    private function json_int_setting(mixed $jsonValue, string $key): int
    {
        if (!is_string($jsonValue) || trim($jsonValue) === '') {
            return 0;
        }

        $decoded = json_decode($jsonValue, true);
        if (!is_array($decoded) || !array_key_exists($key, $decoded)) {
            return 0;
        }

        return max(0, (int) $decoded[$key]);
    }
}
