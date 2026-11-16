<?php
/**
 * Medien-Verwendungsnachweis (365CMS ab 4.1.0): meldet die Bildverweise des Plugins an den
 * Core-`MediaUsageIndex`, damit die Mediathek „verwendet in …“ zeigt und vor dem Löschen warnt.
 *
 * Auf Cores ohne `CMS\Services\Media\MediaReferenceExtractor` (4.0.x) bleibt die Klasse wirkungslos.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CMS_365NET_ExCo_Media_Usage')) {
    final class CMS_365NET_ExCo_Media_Usage
    {
        /**
         * Inhaltsarten: Tabelle (ohne Präfix), Titelspalten, Spalte => Kontext, Editor-Link.
         *
         * @var array<string, array{label:string, table:string, title:list<string>, columns:array<string,string>, edit_url:string}>
         */
        public const TYPES = [
            'net365_expert' => ['label' => 'Experte', 'table' => '365net_excomp_experts', 'title' => ['first_name', 'last_name'], 'columns' => ['photo_url' => 'featured', 'biography_json' => 'content', 'biography' => 'content'], 'edit_url' => '/admin/plugins/experts-companie/experts-companie?view=experts-companie-experts&action=edit&id={id}'],
            'net365_company' => ['label' => 'Firma', 'table' => '365net_excomp_companies', 'title' => ['name'], 'columns' => ['logo_url' => 'featured', 'description_json' => 'content', 'description' => 'content'], 'edit_url' => '/admin/plugins/experts-companie/experts-companie?view=experts-companie-companies&action=edit&id={id}'],
        ];

        private static bool $registered = false;

        public static function init(): void
        {
            if (self::$registered || !class_exists('\CMS\Hooks')) {
                return;
            }
            self::$registered = true;
            \CMS\Hooks::addAction('cms_register_media_reference_extractors', [self::class, 'register_extractors']);
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
                    $index->register(new CMS_365NET_ExCo_Media_Usage_Extractor($type, $config));
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

CMS_365NET_ExCo_Media_Usage::init();
