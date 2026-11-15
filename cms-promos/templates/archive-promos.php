<?php declare(strict_types=1); if (!defined('ABSPATH')) exit;

$lang = isset($lang) && $lang === 'en' ? 'en' : 'de';
$i18nValue = static function (array $values, string $key, string $fallback = '') use ($lang): string {
    if (function_exists('cms_plugin_public_i18n_value')) {
        return cms_plugin_public_i18n_value($values, $key, $lang, $fallback);
    }
    if ($lang === 'en' && isset($values[$key . '_en']) && (string) $values[$key . '_en'] !== '') {
        return (string) $values[$key . '_en'];
    }
    if (isset($values[$key]) && (string) $values[$key] !== '') {
        return (string) $values[$key];
    }
    return $fallback;
};
$localizedPath = static function (string $path) use ($lang): string {
    if (function_exists('cms_plugin_public_localized_path')) {
        return cms_plugin_public_localized_path($path, $lang);
    }
    $path = trim($path, '/');
    if ($path === '') {
        return $lang === 'en' ? '/en' : '/';
    }
    return $lang === 'en' ? '/en/' . $path : '/' . $path;
};
$t = static function (string $key) use ($lang): string {
    $labels = [
        'kicker' => ['de' => '365CMS Promos', 'en' => '365CMS Promos'],
        'placement_nav_aria' => ['de' => 'Promo-Platzierungen', 'en' => 'Promo placements'],
        'placement_current' => ['de' => 'Aktuelle Platzierung', 'en' => 'Current placement'],
        'empty_eyebrow' => ['de' => 'Noch leer', 'en' => 'Still empty'],
        'empty_title' => ['de' => 'Aktuell keine aktiven Promos', 'en' => 'No active promos currently'],
        'empty_text' => ['de' => 'Sobald Kampagnen aktiviert und einer Platzierung zugeordnet wurden, erscheinen sie hier automatisch.', 'en' => 'As soon as campaigns are activated and assigned to a placement, they will appear here automatically.'],
        'generic_promo' => ['de' => 'Promo', 'en' => 'Promo'],
        'default_button' => ['de' => 'Mehr erfahren', 'en' => 'Learn more'],
    ];

    return $labels[$key][$lang] ?? $labels[$key]['de'] ?? '';
};

$sanitizePromoUrl = static function (string $url): string {
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]]/', $url) === 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
        return '';
    }

    $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
    if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
        return '';
    }

    $ip = filter_var($host, FILTER_VALIDATE_IP);
    if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return '';
    }

    return $url;
};

$sanitizePromoHtml = static function (string $html) use ($sanitizePromoUrl): string {
    $html = trim(strip_tags($html, '<p><a><strong><em><ul><ol><li><br><h2><h3><h4><span>'));
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    return preg_replace_callback('/\s+href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', static function (array $matches) use ($sanitizePromoUrl): string {
        $href = html_entity_decode((string) ($matches[2] ?? $matches[3] ?? $matches[4] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeHref = $sanitizePromoUrl($href);
        return $safeHref !== '' ? ' href="' . htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') . '"' : '';
    }, $html) ?? '';
};
?>

<main class="promos-archive">
    <section class="promos-hero">
        <span class="promos-kicker"><?php echo htmlspecialchars($t('kicker'), ENT_QUOTES, 'UTF-8'); ?></span>
        <h1 class="promos-title"><?php echo htmlspecialchars($i18nValue($settings, 'archive_title', 'Promotions & Highlights'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="promos-lead"><?php echo htmlspecialchars($i18nValue($settings, 'archive_description', 'Zentrale Übersicht aktiver Kampagnen, CTA-Flächen und Teaser-Aktionen.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($currentPlacement !== null): ?>
            <p class="promos-lead"><?php echo htmlspecialchars($t('placement_current'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars($i18nValue($currentPlacement, 'name', ''), ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <?php endif; ?>
    </section>

    <?php if (!empty($placements)): ?>
        <nav class="promos-placement-nav" aria-label="<?php echo htmlspecialchars($t('placement_nav_aria'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($placements as $placement): ?>
                <a class="promos-placement-link" href="<?php echo htmlspecialchars($localizedPath('promos/placement/' . rawurlencode((string) $placement['slug'])), ENT_QUOTES, 'UTF-8'); ?>">
                    <strong><?php echo htmlspecialchars($i18nValue($placement, 'name', ''), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <span><?php echo htmlspecialchars($i18nValue($placement, 'description', ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <section class="promos-grid">
        <?php if (empty($promos)): ?>
            <article class="promos-card" role="status" aria-live="polite">
                <span class="promos-card__eyebrow"><?php echo htmlspecialchars($t('empty_eyebrow'), ENT_QUOTES, 'UTF-8'); ?></span>
                <h2 class="promos-card__title"><?php echo htmlspecialchars($t('empty_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars($t('empty_text'), ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
        <?php else: ?>
            <?php foreach ($promos as $promo): ?>
                <article class="promos-card">
                    <span class="promos-card__eyebrow"><?php echo htmlspecialchars($i18nValue($promo, 'placement_name', $t('generic_promo')), ENT_QUOTES, 'UTF-8'); ?></span>
                    <h2 class="promos-card__title"><?php echo htmlspecialchars($i18nValue($promo, 'title', ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php $promoTeaser = $i18nValue($promo, 'teaser', ''); if ($promoTeaser !== ''): ?><p><?php echo htmlspecialchars($promoTeaser, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php $promoContent = $i18nValue($promo, 'content_html', ''); if ($promoContent !== ''): ?><div class="promos-card__body"><?php echo $sanitizePromoHtml($promoContent); ?></div><?php endif; ?>
                    <?php if (!empty($promo['target_url'])): ?>
                        <p class="promos-card__actions">
                            <?php $buttonLabel = $i18nValue($promo, 'button_label', $i18nValue($settings, 'default_button_label', $t('default_button'))); ?>
                            <a class="promos-card__button" href="<?php echo htmlspecialchars($localizedPath('promo/click/' . rawurlencode((string) $promo['slug'])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
