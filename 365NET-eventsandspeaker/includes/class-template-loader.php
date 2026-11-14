<?php
/**
 * Template Loader für 365NET Events & Speaker.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Template_Loader
{
    private static ?self $instance = null;
    private string $templateDir;
    /** @var array<int, string> */
    private array $themeTemplateDirs = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->templateDir = CMS_365NET_EVENTS_PLUGIN_DIR . 'templates/';
        if (class_exists('CMS\\ThemeManager')) {
            $themePath = CMS\ThemeManager::instance()->getThemePath();
            $this->themeTemplateDirs = [
                $themePath . 'cms-365neteventsandspeaker/',
                $themePath . 'cms-365netevents/',
            ];
        }
    }

    /**
     * Rendert ein Template mit einer kleinen, expliziten Context-Whitelist.
     */
    public function render(string $templateName, array $context = []): void
    {
        $file = $this->locate($templateName);
        if ($file === null) {
            echo '<!-- 365NET Events template not found: ' . htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') . ' -->';
            return;
        }

        $events = $context['events'] ?? [];
        $event = $context['event'] ?? null;
        $speakers = $context['speakers'] ?? [];
        $speaker = $context['speaker'] ?? null;
        $settings = $context['settings'] ?? [];
        $filters = $context['filters'] ?? [];
        $pagination = $context['pagination'] ?? [];
        $relatedEvents = $context['relatedEvents'] ?? [];
        $linkedCompany = $context['linkedCompany'] ?? null;
        $linkedExpert = $context['linkedExpert'] ?? null;

        include $file;
    }

    public function get(string $templateName, array $context = []): string
    {
        ob_start();
        $this->render($templateName, $context);
        return (string) ob_get_clean();
    }

    private function locate(string $templateName): ?string
    {
        $templateName = preg_replace('/[^a-z0-9_-]+/i', '', str_replace('.php', '', $templateName));
        if (!is_string($templateName) || $templateName === '') {
            return null;
        }

        $filename = $templateName . '.php';
        foreach ([...$this->themeTemplateDirs, $this->templateDir] as $baseDir) {
            if ($baseDir === '') {
                continue;
            }
            $resolved = $this->resolve($baseDir, $filename);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolve(string $baseDir, string $filename): ?string
    {
        $baseReal = realpath($baseDir);
        if ($baseReal === false || !is_dir($baseReal)) {
            return null;
        }

        $candidate = $baseReal . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($candidate)) {
            return null;
        }

        $real = realpath($candidate);
        $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($real === false || !str_starts_with($real, $prefix) || pathinfo($real, PATHINFO_EXTENSION) !== 'php') {
            return null;
        }

        return $real;
    }
}
