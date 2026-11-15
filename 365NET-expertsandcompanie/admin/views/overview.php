<?php
/**
 * Übersicht (Dashboard): Schnellaktionen, Status-/System-Check, letzte Aktivitäten, Seed-Daten.
 * Die KPI-Karten stehen in der Kopfzeile und werden hier nicht wiederholt.
 *
 * @var array{type:string,text:string}|null $notice
 * @var string $dashboard
 * @var string $csrf
 * @var string $seedUrl
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

include __DIR__ . '/_notice.php';

echo $dashboard;
?>
<section class="admin-card cms-excomp-card">
    <div class="cms-excomp-card__head">
        <h3><i class="ti ti-database-import" aria-hidden="true"></i> Seed-Daten</h3>
    </div>
    <p class="cms-excomp-muted">Die Start-Datensätze liegen im Plugin (<code>defaults/experts.csv</code>, <code>defaults/companies.csv</code>). „Seed neu einspielen“ legt fehlende Seed-Einträge wieder an; geänderte oder eigene Einträge bleiben unverändert.</p>
    <form method="post" action="<?= $e($seedUrl) ?>" data-cms-confirm="Fehlende Seed-Einträge jetzt neu anlegen?">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <input type="hidden" name="excomp_action" value="seed_refresh">
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-refresh" aria-hidden="true"></i> Seed neu einspielen</button>
    </form>
</section>
