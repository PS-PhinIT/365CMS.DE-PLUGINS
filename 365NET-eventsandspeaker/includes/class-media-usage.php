<?php
/**
 * Medien-Verwendungsnachweis (365CMS ab 4.1.0): meldet die Bildverweise des Plugins an den
 * Core-`MediaUsageIndex`, damit die Mediathek „verwendet in …“ zeigt und vor dem Löschen warnt.
 *
 * Auf Cores ohne `CMS\Services\Media\MediaReferenceExtractor` (4.0.x) bleibt die Klasse wirkungslos.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CMS_365NETEvents_Media_Usage')) {
    final class CMS_365NETEvents_Media_Usage
    {
        /**
         * Inhaltsarten: Tabelle (ohne Präfix), Titelspalten, Spalte => Kontext, Editor-Link.
         *
         * @var array<string, array{label:string, table:string, title:list<string>, columns:array<string,string>, edit_url:string}>
         */
        public const TYPES = [
            'net365_event' => ['label' => 'Event', 'table' => '365net_events', 'title' => ['title', 'slug'], 'columns' => ['image_url' => 'featured', 'og_image_url' => 'featured', 'gallery_json' => 'gallery', 'description_json' => 'content', 'description' => 'content'], 'edit_url' => '/admin/plugins/365netevents/365netevents?view=365netevents-events&action=edit&id={id}'],
            'net365_speaker' => ['label' => 'Speaker', 'table' => '365net_event_speakers', 'title' => ['display_name', 'first_name', 'last_name'], 'columns' => ['avatar_url' => 'featured', 'theme_image_url' => 'featured', 'og_image_url' => 'featured', 'bio_json' => 'content', 'bio' => 'content'], 'edit_url' => '/admin/plugins/365netevents/365netevents?view=365netevents-speakers&action=edit&id={id}'],
        ];

        private static bool $registered = false;

        public static function init(): void
        {
            if (self::$registered || !class_exists('\CMS\Hooks')) {
                return;
            }
            self::$registered = true;
            \CMS\Hooks::addAction('cms_register_media_reference_extractors', [self::class, 'register_extractors']);
            // Speichern/Löschen meldet die Datenbankklasse über eigene Hooks.
            foreach (['event' => 'net365_event', 'speaker' => 'net365_speaker'] as $hook => $type) {
                \CMS\Hooks::addAction('cms_365net_' . $hook . '_created', static fn (mixed $id = 0): mixed => self::touch($type, (int) $id));
                \CMS\Hooks::addAction('cms_365net_' . $hook . '_updated', static fn (mixed $id = 0): mixed => self::touch($type, (int) $id));
                \CMS\Hooks::addAction('cms_365net_' . $hook . '_deleted', static fn (mixed $id = 0): mixed => self::forget($type, (int) $id));
            }
        }

        public static function available(): bool
        {
            return interface_exists('\CMS\Services\Media\MediaReferenceExtractor')
                && class_exists('\CMS\Services\Media\MediaUsageIndex');
        }

        public static function register_extractors(mixed $index): void
        {
            if (!self::available() || !$index instanceof \CMS\Services\Media\MediaUsageIndex) {
                return;
            }
            require_once __DIR__ . '/class-media-usage-extractor.php';
            foreach (self::TYPES as $type => $config) {
                try {
                    $index->register(new CMS_365NETEvents_Media_Usage_Extractor($type, $config));
                } catch (\Throwable) {
                    // Ungültige Inhaltsart: Index bleibt ohne diesen Typ.
                }
            }
        }

        /** Nach dem Speichern: Verweise eines Datensatzes neu schreiben. */
        public static function touch(string $type, int $id): void
        {
            self::apply($type, $id, false);
        }

        /** Nach dem Löschen: Verweise eines Datensatzes entfernen. */
        public static function forget(string $type, int $id): void
        {
            self::apply($type, $id, true);
        }

        private static function apply(string $type, int $id, bool $delete): void
        {
            if ($id <= 0 || !isset(self::TYPES[$type]) || !self::available()) {
                return;
            }
            try {
                $index = \CMS\Services\Media\MediaUsageIndex::getInstance();
                $delete ? $index->removeContent($type, $id) : $index->reindexContent($type, $id);
            } catch (\Throwable $e) {
                // Der Verwendungsnachweis darf das Speichern nie verhindern.
                if (class_exists('\CMS\Logger')) {
                    \CMS\Logger::instance()->withChannel('plugins')->warning('Media usage index update failed.', [
                        'type' => $type,
                        'id' => $id,
                        'exception' => $e,
                    ]);
                }
            }
        }
    }
}

CMS_365NETEvents_Media_Usage::init();
