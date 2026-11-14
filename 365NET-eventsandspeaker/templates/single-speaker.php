<?php
/** @var object|null $speaker */
/** @var array<int, object> $relatedEvents */
/** @var object|null $linkedCompany */
/** @var object|null $linkedExpert */
/** @var array<string, string> $settings */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

$base = rtrim((string) SITE_URL, '/');

$renderEditor = static function (mixed $json, mixed $fallback): string {
    $json = (string) $json;
    if ($json !== '' && class_exists('CMS\\Services\\EditorJsRenderer')) {
        $rendered = (string) CMS\Services\EditorJsRenderer::getInstance()->render($json);
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8')));
        $hasMediaBlocks = preg_match('/<(img|video|audio|iframe|table|ul|ol|blockquote|pre|h[1-6])\b/i', $rendered) === 1;
        if ($plain !== '' || $hasMediaBlocks) {
            return $rendered;
        }
    }

    $fallback = trim((string) $fallback);
    return $fallback !== ''
        ? '<div class="cms-events-copy">' . nl2br(htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8')) . '</div>'
        : '';
};

$speakerName = (string) ($speaker->display_name ?? '');
$speakerInitial = strtoupper(substr($speakerName !== '' ? $speakerName : 'S', 0, 1));
$profileImageUrl = trim((string) ($speaker->avatar_url ?? ''));
$profileImageAlt = trim((string) (($speaker->avatar_alt ?? '') ?: $speakerName));
$themeImageUrl = trim((string) ($speaker->theme_image_url ?? ''));
$themeImageAlt = trim((string) (($speaker->theme_image_alt ?? '') ?: $speakerName));
$speakerTopic = trim((string) ($speaker->topic ?? ''));

$hasProfileImage = $profileImageUrl !== '';
$hasThemeImage = $themeImageUrl !== '';

$detailRows = [];
$speakerCategories = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($speaker->categories ?? '')) ?: []), static fn(string $value): bool => $value !== ''));
if ($speakerCategories === [] && $speakerTopic !== '') {
    $speakerCategories[] = $speakerTopic;
}
if ($speakerCategories !== []) {
    $detailRows[] = [
        'label' => count($speakerCategories) > 1 ? 'Schwerpunkte' : 'Schwerpunkt',
        'value' => implode(', ', array_slice($speakerCategories, 0, 5)),
    ];
}

$speakerOrganization = '';
if (!empty($linkedCompany) && !empty($linkedCompany->name)) {
    $speakerOrganization = trim((string) $linkedCompany->name);
} elseif (!empty($speaker->company)) {
    $speakerOrganization = trim((string) $speaker->company);
} elseif (!empty($linkedExpert) && !empty($linkedExpert->company)) {
    $speakerOrganization = trim((string) $linkedExpert->company);
}
if ($speakerOrganization !== '') {
    $detailRows[] = ['label' => 'Organisation', 'value' => $speakerOrganization];
}

if ($speakerTopic !== '') {
    $detailRows[] = ['label' => 'Thema', 'value' => $speakerTopic];
}
$speakerAward = trim((string) ($speaker->award ?? ''));
$sidebarMvpBadge = '';
if ($speakerAward !== '') {
    if (preg_match('/\bmvp\b/i', $speakerAward) === 1) {
        $sidebarMvpBadge = 'Microsoft MVP';
    } else {
        $detailRows[] = ['label' => 'Auszeichnung', 'value' => $speakerAward];
    }
}
if (!empty($speaker->speaker_type)) {
    $detailRows[] = ['label' => 'Typ', 'value' => (string) $speaker->speaker_type];
}
if (!empty($speaker->languages)) {
    $detailRows[] = ['label' => 'Sprachen', 'value' => (string) $speaker->languages];
}
if (!empty($speaker->speaking_formats)) {
    $detailRows[] = ['label' => 'Formate', 'value' => (string) $speaker->speaking_formats];
}
if (!empty($speaker->price_class)) {
    $detailRows[] = ['label' => 'Preisklasse', 'value' => (string) $speaker->price_class];
}
if (!empty($speaker->availability)) {
    $detailRows[] = ['label' => 'Verfügbarkeit', 'value' => (string) $speaker->availability];
}
if ($detailRows === []) {
    $detailRows[] = ['label' => 'Hinweis', 'value' => 'Keine Details hinterlegt'];
}

$sidebarPrimaryLink = trim((string) ($speaker->website ?? ''));
$sidebarPrimaryLabel = 'Zur Website';

$sidebarContactLink = '';
$sidebarContactIcon = '✉';
$sidebarContactEmail = trim((string) (($speaker->contact_email ?? '') ?: ($speaker->email ?? '')));
if ($sidebarContactEmail !== '' && filter_var($sidebarContactEmail, FILTER_VALIDATE_EMAIL)) {
    $sidebarContactLink = 'mailto:' . $sidebarContactEmail;
} else {
    $sidebarContactPhoneRaw = trim((string) (($speaker->contact_phone ?? '') ?: ($speaker->phone ?? '')));
    if ($sidebarContactPhoneRaw !== '') {
        $sidebarContactPhone = preg_replace('/[^\d+]/', '', $sidebarContactPhoneRaw);
        if (is_string($sidebarContactPhone) && $sidebarContactPhone !== '') {
            $sidebarContactLink = 'tel:' . $sidebarContactPhone;
            $sidebarContactIcon = '☎';
        }
    }
}

$sidebarSocialLinks = [];
$sidebarSocialMap = [
    ['field' => 'linkedin_url', 'label' => 'LinkedIn', 'icon' => 'in'],
    ['field' => 'x_url', 'label' => 'X', 'icon' => 'X'],
    ['field' => 'youtube_url', 'label' => 'YouTube', 'icon' => '▶'],
    ['field' => 'github_url', 'label' => 'GitHub', 'icon' => '⌘'],
    ['field' => 'facebook_url', 'label' => 'Facebook', 'icon' => 'f'],
    ['field' => 'instagram_url', 'label' => 'Instagram', 'icon' => '◎'],
];

foreach ($sidebarSocialMap as $sidebarSocial) {
    $field = (string) ($sidebarSocial['field'] ?? '');
    if ($field === '' || empty($speaker->{$field})) {
        continue;
    }

    $sidebarSocialLinks[] = [
        'url' => (string) $speaker->{$field},
        'label' => (string) ($sidebarSocial['label'] ?? $field),
        'icon' => (string) ($sidebarSocial['icon'] ?? '↗'),
    ];
}

$hasSidebarPrimaryActions = $sidebarContactLink !== '' || $sidebarPrimaryLink !== '';
$hasSidebarLinks = $hasSidebarPrimaryActions || $sidebarSocialLinks !== [];
?>
<main class="cms-events-public cms-events-detail cms-speaker-detail">
    <div class="cms-events-container">
        <nav class="cms-events-breadcrumb">
            <a href="<?= htmlspecialchars($base . '/speakers', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($settings['speaker_archive_title'] ?? 'Speaker'), ENT_QUOTES, 'UTF-8') ?></a>
            <span>/</span>
            <span><?= htmlspecialchars($speakerName, ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <article class="cms-events-detail-layout">
            <header class="cms-events-detail-header cms-speaker-detail-header cms-events-detail-header--has-image">
                <div class="cms-events-detail-header__content">
                    <div class="cms-events-detail-header__intro">
                        <div class="cms-speaker-detail-badge" aria-label="Speakerprofil">
                            <?php if ($hasProfileImage): ?>
                                <img src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($profileImageAlt, ENT_QUOTES, 'UTF-8') ?>" loading="eager">
                            <?php else: ?>
                                <span class="cms-speaker-detail-badge__initial"><?= htmlspecialchars($speakerInitial, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cms-events-detail-header__text">
                            <h1><?= htmlspecialchars($speakerName, ENT_QUOTES, 'UTF-8') ?></h1>
                        </div>
                    </div>
                </div>
                <figure class="cms-events-detail-header__media cms-speaker-detail-header__media<?= $hasThemeImage ? '' : ' is-placeholder' ?>">
                    <?php if ($hasThemeImage): ?>
                        <img src="<?= htmlspecialchars($themeImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($themeImageAlt, ENT_QUOTES, 'UTF-8') ?>" loading="eager">
                    <?php else: ?>
                        <div class="cms-speaker-detail-topic-placeholder" aria-hidden="true">
                            <span class="cms-speaker-detail-topic-placeholder__icon">🖼️</span>
                            <span class="cms-speaker-detail-topic-placeholder__title">Themenbild</span>
                            <span class="cms-speaker-detail-topic-placeholder__hint">Kein Bild hinterlegt</span>
                        </div>
                    <?php endif; ?>
                </figure>
            </header>

            <div class="cms-events-detail-layout__body">
                <section class="cms-events-detail-main">
                    <?= $renderEditor($speaker->bio_json ?? '', $speaker->bio ?? '') ?>
                </section>

                <aside class="cms-events-sidebar">
                    <div class="cms-events-sidebar-stack">
                        <div class="cms-events-sidecard cms-events-sidecard--details<?= $hasSidebarLinks ? ' cms-events-sidecard--details-has-links' : '' ?>">
                            <dl class="cms-events-sidecard__meta">
                                <?php foreach ($detailRows as $detailRow): ?>
                                    <div class="cms-events-sidecard__meta-row">
                                        <dt><?= htmlspecialchars((string) ($detailRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                                        <dd><?= htmlspecialchars((string) ($detailRow['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                            <?php if ($sidebarMvpBadge !== ''): ?>
                                <div class="cms-events-sidecard__badges cms-events-sidecard__badges--details" aria-label="Auszeichnungen">
                                    <span class="is-mvp"><?= htmlspecialchars($sidebarMvpBadge, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasSidebarLinks): ?>
                            <div class="cms-events-sidecard cms-events-sidecard--links">
                                <?php if ($hasSidebarPrimaryActions): ?>
                                    <div class="cms-events-side-actions cms-events-side-actions--primary-row">
                                        <?php if ($sidebarContactLink !== ''): ?>
                                            <a class="cms-events-side-action cms-events-side-action--primary" href="<?= htmlspecialchars($sidebarContactLink, ENT_QUOTES, 'UTF-8') ?>" aria-label="Kontakt" title="Kontakt">
                                                <span class="cms-events-side-action__icon"><?= htmlspecialchars($sidebarContactIcon, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="cms-events-side-action__label">Kontakt</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($sidebarPrimaryLink !== ''): ?>
                                            <a class="cms-events-side-action cms-events-side-action--primary" href="<?= htmlspecialchars($sidebarPrimaryLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="cms-events-side-action__icon">🌐</span>
                                                <span class="cms-events-side-action__label"><?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($sidebarSocialLinks !== []): ?>
                                    <div class="cms-events-side-actions cms-events-side-actions--social">
                                        <?php foreach ($sidebarSocialLinks as $sidebarSocialLink): ?>
                                            <a class="cms-events-side-action cms-events-side-action--icon-only" href="<?= htmlspecialchars((string) $sidebarSocialLink['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars((string) $sidebarSocialLink['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $sidebarSocialLink['label'], ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="cms-events-side-action__icon"><?= htmlspecialchars((string) $sidebarSocialLink['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($linkedCompany) || !empty($linkedExpert)): ?>
                        <div class="cms-events-sidecard">
                            <h2>365CMS-Verknüpfung</h2>
                            <div class="cms-events-socials">
                                <?php if (!empty($linkedExpert)): ?>
                                    <?php
                                    $linkedExpertName = trim((string) (($linkedExpert->first_name ?? '') . ' ' . ($linkedExpert->last_name ?? '')));
                                    if ($linkedExpertName === '') {
                                        $linkedExpertName = 'Expert';
                                    }
                                    $linkedExpertId = (int) ($linkedExpert->id ?? 0);
                                    ?>
                                    <a class="cms-events-btn" href="<?= htmlspecialchars($linkedExpertId > 0 ? ($base . '/experts/' . $linkedExpertId) : ($base . '/experts'), ENT_QUOTES, 'UTF-8') ?>">👤 <?= htmlspecialchars($linkedExpertName, ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endif; ?>
                                <?php if (!empty($linkedCompany)): ?>
                                    <?php $linkedCompanyName = trim((string) ($linkedCompany->name ?? '')); ?>
                                    <?php $linkedCompanyId = (int) ($linkedCompany->id ?? 0); ?>
                                    <a class="cms-events-btn" href="<?= htmlspecialchars($linkedCompanyId > 0 ? ($base . '/companies/' . $linkedCompanyId) : ($base . '/companies'), ENT_QUOTES, 'UTF-8') ?>">🏢 <?= htmlspecialchars($linkedCompanyName !== '' ? $linkedCompanyName : 'Firma', ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <section class="cms-events-detail-main cms-events-detail-main--fullwidth">
                <section class="cms-events-section cms-events-section--fullwidth">
                    <h2><?= htmlspecialchars((string) ($settings['speaker_events_heading'] ?? 'Events'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($relatedEvents === []): ?>
                        <p><?= htmlspecialchars((string) ($settings['speaker_no_events_text'] ?? 'Noch keine öffentlichen Events verknüpft.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <div class="cms-speaker-list">
                            <?php foreach ($relatedEvents as $event): ?>
                                <a class="cms-speaker-row" href="<?= htmlspecialchars($base . '/events/' . rawurlencode((string) $event->slug), ENT_QUOTES, 'UTF-8') ?>">
                                    <span>📅</span>
                                    <span>
                                        <strong><?= htmlspecialchars((string) $event->title, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small>
                                            <?= htmlspecialchars((string) ($event->date_label ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?= !empty($event->location) ? ' · ' . htmlspecialchars((string) $event->location, ENT_QUOTES, 'UTF-8') : '' ?>
                                        </small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

        </article>
    </div>
</main>
