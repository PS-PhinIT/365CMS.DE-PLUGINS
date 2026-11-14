<?php
/**
 * Template Loader für 365NET Experts & Companie.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Experts_And_Companie_Template_Loader
{
    private static ?self $instance = null;
    private string $pluginTemplateDir;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->pluginTemplateDir = CMS_365NET_EXCOMP_PLUGIN_DIR . 'templates/';
    }

    public function render(string $templateName, array $data = []): void
    {
        $template = $this->locateTemplate($templateName);
        if ($template === null) {
            echo '<!-- CMS 365NET Experts & Companie: Template nicht gefunden (' . htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') . ') -->';
            return;
        }

        extract($data, EXTR_SKIP);
        include $template;
    }

    private function locateTemplate(string $templateName): ?string
    {
        $safe = trim(str_replace('.php', '', $templateName));
        if ($safe === '' || preg_match('/[^a-z0-9_-]/i', $safe) === 1) {
            return null;
        }

        $filename = $safe . '.php';

        $themeDir = $this->getThemeTemplateDir();
        if ($themeDir !== '') {
            $themeFile = $themeDir . $filename;
            if ($this->isSafeTemplatePath($themeFile, $themeDir)) {
                return $themeFile;
            }
        }

        $pluginFile = $this->pluginTemplateDir . $filename;
        if ($this->isSafeTemplatePath($pluginFile, $this->pluginTemplateDir)) {
            return $pluginFile;
        }

        return null;
    }

    private function getThemeTemplateDir(): string
    {
        if (!class_exists('CMS\\ThemeManager')) {
            return '';
        }

        return CMS\ThemeManager::instance()->getThemePath() . 'cms-expertsandcompanie/';
    }

    private function isSafeTemplatePath(string $candidate, string $base): bool
    {
        if (!is_file($candidate)) {
            return false;
        }

        $baseReal = realpath($base);
        $candidateReal = realpath($candidate);
        if ($baseReal === false || $candidateReal === false) {
            return false;
        }

        $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($candidateReal, $basePrefix) && pathinfo($candidateReal, PATHINFO_EXTENSION) === 'php';
    }
}
