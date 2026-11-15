<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mitgliederbereich /member/plugin/projects (PluginDashboardRegistry).
 *
 * Projekte sind redaktionelle Inhalte des Admins (Sichtbarkeit „Mitglieder“ oder „Öffentlich“,
 * Status aktiv/pausiert); der Bereich ist nur lesend und verarbeitet keine POST-Anfragen.
 */
final class CMS_Projects_Member_Dashboard
{
    public const SECTION_SLUG = 'projects';

    public function __construct(private readonly CMS_Projects_Service $service)
    {
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::addAction('member_dashboard_init', [$this, 'register'], 10);
            \CMS\Hooks::addAction('member_plugin_section_head', [$this, 'enqueueSectionStyles'], 10);
        }
    }

    public function register(mixed $registry = null): void
    {
        if (!$registry instanceof \CMS\Member\PluginDashboardRegistry) {
            return;
        }

        $registry->register([
            'plugin' => 'cms-projects',
            'slug' => self::SECTION_SLUG,
            'label' => 'Projekte',
            'icon' => 'PRJ',
            'category' => 'plugins',
            'priority' => 30,
            'capability' => 'member',
            'csrf' => 'core',
            'dashboard_widget' => [
                'title' => 'Projekte',
                'description' => 'Projektboards, Widgets und Statusübersichten.',
                'color' => '#2563eb',
                'stats_callback' => [$this, 'getDashboardStats'],
                'link_label' => 'Zu den Projekten',
                'admin_url' => '/admin/plugins/cms-projects',
                'admin_label' => 'Verwalten',
            ],
            'render_callback' => [$this, 'renderPage'],
        ]);
    }

    /**
     * Hook member_plugin_section_head liefert ($section, $user, $params); $section ist das Registry-Array.
     */
    public function enqueueSectionStyles(mixed $section = null): void
    {
        $slug = is_array($section) ? (string) ($section['slug'] ?? '') : (is_string($section) ? $section : '');
        if ($slug === self::SECTION_SLUG) {
            echo CMS_Projects_View::stylesheetTag();
        }
    }

    public function getDashboardStats(object $user): array
    {
        return ['count' => $this->service->countMemberProjects(), 'label' => 'Projekte'];
    }

    public function renderPage(object $user, array $params = []): void
    {
        $projects = $this->service->getMemberProjects();
        $selectedSlug = substr((string) (preg_replace('/[^a-z0-9\-_]/i', '', (string) ($_GET['project'] ?? '')) ?? ''), 0, 120);
        $currentProject = $selectedSlug !== '' ? $this->service->findAccessibleProjectBySlug($selectedSlug, 'member') : null;

        if ($currentProject === null && $projects !== []) {
            $currentProject = $projects[0];
        }

        $dashboard = $currentProject !== null
            ? $this->service->getProjectDashboardPayload((int) $currentProject['id'], 'member')
            : null;
        $statusLabels = $this->service->getProjectStatuses();

        include CMS_PROJECTS_PLUGIN_DIR . 'templates/member-projects.php';
    }
}
