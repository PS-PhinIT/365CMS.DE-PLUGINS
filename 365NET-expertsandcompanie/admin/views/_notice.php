<?php
/**
 * Teil-View: Meldung oben auf einer Unterseite (nach dem Seitenkopf).
 *
 * @var array{type:string,text:string}|null $notice
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (is_array($notice ?? null)): ?>
    <div class="alert alert-<?= $e($notice['type']) ?>" role="alert"><?= $e($notice['text']) ?></div>
<?php endif;
