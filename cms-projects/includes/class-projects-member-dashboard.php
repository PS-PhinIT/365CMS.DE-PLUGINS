<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Projects_Member_Dashboard
{
    public function __construct(private readonly CMS_Projects_Service $service)
    {
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::addAction('member_dashboard_init', [$this, 'register'], 10);
            \CMS\Hooks::addAction('member_plugin_section_head', [$this, 'enqueueSectionStyles'], 10);
        }
    }

    public function register(\CMS\Member\PluginDashboardRegistry $registry): void
    {
        $registry->register([
            'plugin' => 'cms-projects',
            'slug' => 'projects',
            'label' => 'Projekte',
            'icon' => 'ti-layout-kanban',
            'category' => 'plugins',
            'priority' => 30,
            // Alle angemeldeten Mitglieder (4.0.0: nur 'member', 'admin', Rollen oder echte Capabilities).
            'capability' => 'member',
            'dashboard_widget' => [
                'title' => 'Projekte',
                'description' => 'Projektboards, Widgets und Statusübersichten verwalten.',
                'color' => '#2563eb',
                'stats_callback' => [$this, 'getDashboardStats'],
                'link_label' => 'Zu den Projekten',
                'admin_url' => '/admin/plugins/cms-projects',
                'admin_label' => 'Admin',
            ],
            'render_callback' => [$this, 'renderPage'],
        ]);
    }

    /**
     * Hook member_plugin_section_head liefert ($section, $user, $params);
     * $section ist das Registry-Array des aktiven Member-Bereichs.
     */
    public function enqueueSectionStyles(mixed $section = null): void
    {
        $slug = is_array($section) ? (string) ($section['slug'] ?? '') : (is_string($section) ? $section : '');
        if ($slug !== 'projects') {
            return;
        }

        $cssFile = CMS_PROJECTS_PLUGIN_DIR . 'assets/css/style.css';
        if (!is_file($cssFile)) {
            return;
        }

        echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROJECTS_PLUGIN_URL . 'assets/css/style.css', ENT_QUOTES, 'UTF-8')
            . '?v=' . (int) filemtime($cssFile) . '">' . "\n";
    }

    public function getDashboardStats(object $user): array
    {
        $projects = $this->service->getMemberProjects();
        return [
            'count' => count($projects),
            'label' => 'Projekte',
        ];
    }

    public function renderPage(object $user, array $params = []): void
    {
        $projects = $this->service->getMemberProjects();
        $selectedSlug = trim((string) ($_GET['project'] ?? ''));
        $selectedSlug = preg_replace('/[^a-z0-9\-_]/i', '', $selectedSlug) ?? '';
        $selectedSlug = substr($selectedSlug, 0, 120);
        $currentProject = null;

        if ($selectedSlug !== '') {
            $candidate = $this->service->findProjectBySlug($selectedSlug);
            if ($candidate !== null
                && in_array((string) ($candidate['visibility'] ?? ''), ['member', 'public'], true)
                && in_array((string) ($candidate['status'] ?? ''), ['active', 'paused'], true)) {
                $currentProject = $candidate;
            }
        }

        if ($currentProject === null && $projects !== []) {
            $currentProject = $projects[0];
        }

        $dashboard = $currentProject !== null
            ? $this->service->getProjectDashboardPayload((int) $currentProject['id'], 'member')
            : null;

        include CMS_PROJECTS_PLUGIN_DIR . 'templates/member-projects.php';
    }
}
