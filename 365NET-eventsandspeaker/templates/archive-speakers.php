<?php
/** @var array<int, object> $speakers */
/** @var array<string, string> $filters */
/** @var array<string, string> $settings */
/** @var array{current:int,total:int,per_page:int,total_pages:int} $pagination */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
$base = rtrim((string) SITE_URL, '/');
$q = htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8');
$pagination = is_array($pagination ?? null) ? $pagination : ['current' => 1, 'total' => count($speakers), 'per_page' => 15, 'total_pages' => 1];
$currentPage = max(1, (int) ($pagination['current'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$paginationUrl = static function (int $page) use ($base, $filters): string {
    $query = [];
    if ((string) ($filters['q'] ?? '') !== '') {
        $query['q'] = (string) $filters['q'];
    }
    if ($page > 1) {
        $query['page'] = (string) $page;
    }

    return $base . '/speakers' . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
};

$speakersHeaderTextEnabled = (string) ($settings['speakers_header_text_enabled'] ?? '1') !== '0';
$speakersHeaderKickerEnabled = (string) ($settings['speakers_header_kicker_enabled'] ?? '1') !== '0';
$speakersHeaderTitleEnabled = (string) ($settings['speakers_header_title_enabled'] ?? '1') !== '0';
$speakersHeaderDescriptionEnabled = (string) ($settings['speakers_header_description_enabled'] ?? '1') !== '0';
$speakersSearchPlaceholderEnabled = (string) ($settings['speakers_search_placeholder_enabled'] ?? '1') !== '0';
$speakersHeaderHasTextContent = $speakersHeaderTextEnabled && ($speakersHeaderKickerEnabled || $speakersHeaderTitleEnabled || $speakersHeaderDescriptionEnabled);
$normalizeHeaderCta = static function (string $text, string $url): ?array {
    $label = trim($text);
    $href = trim($url);
    if ($label === '' || $href === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $href) !== 1 && !str_starts_with($href, '/')) {
        return null;
    }

    return ['text' => $label, 'url' => $href];
};

$speakersHeaderButtons = [];
for ($buttonIndex = 1; $buttonIndex <= 3; $buttonIndex++) {
    $button = $normalizeHeaderCta(
        (string) ($settings['speakers_header_btn_' . $buttonIndex . '_text'] ?? ''),
        (string) ($settings['speakers_header_btn_' . $buttonIndex . '_url'] ?? '')
    );

    if ($button !== null) {
        $speakersHeaderButtons[] = $button;
    }
}

$speakerCardExcerpt = static function (object $speaker): string {
    $raw = trim((string) ($speaker->bio ?? ''));
    if ($raw === '') {
        $raw = trim((string) ($speaker->description ?? ''));
    }
    if ($raw === '') {
        return '';
    }

    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }

    return function_exists('mb_substr')
        ? (string) mb_substr($text, 0, 180, 'UTF-8')
        : substr($text, 0, 180);
};
?>
<main class="cms-events-public cms-speakers-archive">
    <div class="cms-events-container">
        <section class="cms-events-hero cms-events-hero--compact<?= $speakersHeaderButtons !== [] ? ' cms-events-hero--has-actions' : '' ?><?= !$speakersHeaderHasTextContent ? ' cms-events-hero--no-text' : '' ?>">
            <?php if ($speakersHeaderTextEnabled): ?>
                <?php if ($speakersHeaderKickerEnabled): ?><span class="cms-events-kicker"><?= htmlspecialchars((string) ($settings['speaker_archive_kicker'] ?? '365NET Speaker Directory'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                <?php if ($speakersHeaderTitleEnabled): ?><h1><?= htmlspecialchars((string) ($settings['speaker_archive_title'] ?? 'Event-Speaker'), ENT_QUOTES, 'UTF-8') ?></h1><?php endif; ?>
                <?php if ($speakersHeaderDescriptionEnabled): ?><p class="cms-events-hero__description"><?= htmlspecialchars((string) ($settings['speaker_archive_description'] ?? 'Echte Personen mit Bühne, Erfahrung und starken Themen aus dem Event-Datensatz.'), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($speakersHeaderButtons !== []): ?>
                <div class="cms-events-hero__actions" aria-label="Header-Aktionen">
                    <?php foreach ($speakersHeaderButtons as $button): ?>
                        <a class="cms-events-hero__action" href="<?= htmlspecialchars((string) $button['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $button['text'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <form method="GET" class="cms-events-search cms-events-search--header-card" role="search">
            <div class="cms-events-search__field">
                <input type="search" name="q" value="<?= $q ?>" placeholder="<?= $speakersSearchPlaceholderEnabled ? htmlspecialchars((string) ($settings['speaker_search_placeholder'] ?? 'Speaker, Thema oder Tag suchen …'), ENT_QUOTES, 'UTF-8') : '' ?>">
            </div>
            <div class="cms-events-search__actions">
                <button type="submit"><?= htmlspecialchars((string) ($settings['archive_search_button'] ?? 'Suchen'), ENT_QUOTES, 'UTF-8') ?></button>
                <?php if ($q !== ''): ?><a href="<?= htmlspecialchars($base . '/speakers', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($settings['archive_reset_label'] ?? 'Zurücksetzen'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
            </div>
        </form>
        <div class="cms-events-grid cms-speakers-grid">
            <?php foreach ($speakers as $speaker): ?>
                <?php
                $speakerUrl = $base . '/speakers/' . rawurlencode((string) $speaker->slug);
                $speakerExcerpt = $speakerCardExcerpt($speaker);
                $speakerEvents = (int) ($speaker->event_count ?? 0);
                $speakerMetaItems = array_values(array_filter([
                    trim((string) ($speaker->location ?? '')),
                    trim((string) ($speaker->speaking_formats ?? '')),
                ], static fn(string $value): bool => $value !== ''));
                $speakerMetaLine = implode(' · ', array_slice($speakerMetaItems, 0, 3));
                ?>
                <article class="cms-events-card cms-speaker-card">
                    <div class="cms-speaker-card__head">
                        <div class="cms-speaker-card__media">
                            <?php if (!empty($speaker->avatar_url)): ?>
                                <img class="cms-speaker-photo cms-speaker-photo--card" src="<?= htmlspecialchars((string) $speaker->avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($speaker->avatar_alt ?? $speaker->display_name ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            <?php else: ?>
                                <span class="cms-speaker-avatar cms-speaker-avatar--card"><?= htmlspecialchars(strtoupper(substr((string) ($speaker->display_name ?? 'S'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cms-speaker-card__titleblock">
                            <h2 class="cms-speaker-card__name"><a href="<?= htmlspecialchars($speakerUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $speaker->display_name, ENT_QUOTES, 'UTF-8') ?></a></h2>
                            <?php if ($speakerMetaLine !== ''): ?>
                                <p class="cms-speaker-card__meta"><?= htmlspecialchars($speakerMetaLine, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($speakerExcerpt !== ''): ?>
                        <p class="cms-speaker-card__excerpt"><?= htmlspecialchars($speakerExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <footer>
                        <span class="cms-speaker-card__events"><?= $speakerEvents ?> Events</span>
                        <a class="cms-events-card__more" href="<?= htmlspecialchars($speakerUrl, ENT_QUOTES, 'UTF-8') ?>">Mehr Infos …</a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="cms-events-pagination" aria-label="Speaker-Seiten">
                <a class="cms-events-pagination__link<?= $currentPage <= 1 ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($paginationUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>" aria-disabled="<?= $currentPage <= 1 ? 'true' : 'false' ?>">Zurück</a>
                <div class="cms-events-pagination__pages">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 2): ?>
                            <a class="cms-events-pagination__page<?= $i === $currentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($paginationUrl($i), ENT_QUOTES, 'UTF-8') ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
                        <?php elseif ($i === $currentPage - 3 || $i === $currentPage + 3): ?>
                            <span class="cms-events-pagination__ellipsis" aria-hidden="true">…</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <a class="cms-events-pagination__link<?= $currentPage >= $totalPages ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($paginationUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>" aria-disabled="<?= $currentPage >= $totalPages ? 'true' : 'false' ?>">Weiter</a>
            </nav>
        <?php endif; ?>
    </div>
</main>
