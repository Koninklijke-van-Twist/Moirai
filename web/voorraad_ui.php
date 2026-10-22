<?php
require_once __DIR__ . '/kvt_protect_dirs.php';
/**
 * Markup voor het tabblad Voorraad. Wordt ge-include vanuit index.php.
 * Logica: js/voorraad.js, server: voorraad_api.php / moirai_voorraad.php.
 * Iedereen die is ingelogd mag kijken; aanmaken/bewerken/uitgeven alleen ICT-admins.
 */
$voorraadKeys = array_values(array_filter(array_keys(TRANSLATIONS['nl']), static fn(string $k): bool => str_starts_with($k, 'voorraad.')));
$voorraadKeys = array_merge($voorraadKeys, [
    'moirai.btn.save', 'moirai.btn.cancel', 'moirai.btn.close', 'moirai.btn.edit', 'moirai.btn.delete', 'moirai.btn.notes',
    'moirai.btn.print_label', 'moirai.error.request_failed', 'moirai.error.print_failed', 'moirai.notes.title',
]);
?>
<div id="voorraad-root">
<style>
    .panel.is-voorraad > :not(.tabs):not(#voorraad-root) { display: none !important; }
    body.voorraad-active #add-device-btn { display: none; }
    #voorraad-panel[hidden] { display: none; }
    .voorraad-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 12px; }
    .voorraad-toolbar .voorraad-search { flex: 1 1 240px; }
    .voorraad-toolbar input[type=search] { width: 100%; }
    .voorraad-props { margin: 4px 0 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 4px; }
    .voorraad-props li { font-size: 0.78rem; border: 1px solid var(--kvt-line); border-radius: 999px; padding: 1px 8px; color: var(--kvt-muted); }
    .voorraad-count { font-size: 1.6rem; font-weight: 800; color: var(--kvt-main-blue); }
    .voorraad-count.is-empty { color: var(--kvt-danger, #b91c1c); }
    .voorraad-adjust { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--kvt-line); }
    .voorraad-adjust input { width: 110px; }
    .message.voorraad-ok { background: #e7f6ec; color: #166534; }
    .voorraad-hint { font-size: 0.8rem; color: var(--kvt-muted); margin: 4px 0 0; }
</style>

<section id="voorraad-panel" hidden
    data-admin="<?= moirai_is_admin() ? '1' : '0' ?>"
    data-i18n="<?= moirai_h(localizationJsTranslations($voorraadKeys)) ?>">
    <div class="voorraad-toolbar">
        <div class="voorraad-search">
            <label for="voorraad-search"><?= moirai_h(LOC('moirai.label.search')) ?></label>
            <input type="search" id="voorraad-search" placeholder="<?= moirai_h(LOC('voorraad.search.placeholder')) ?>">
        </div>
        <?php if (moirai_is_admin()): ?>
        <button type="button" class="btn btn-primary" id="voorraad-add-btn"><?= moirai_h(LOC('voorraad.add')) ?></button>
        <?php endif; ?>
    </div>
    <div class="message" id="voorraad-message"></div>
    <div class="loader" id="voorraad-loader"><?= moirai_h(LOC('voorraad.loading')) ?></div>
    <div class="device-list" id="voorraad-list" hidden></div>
    <div class="empty-state" id="voorraad-empty" hidden><?= moirai_h(LOC('voorraad.empty')) ?></div>
</section>

<div class="modal-backdrop" id="voorraad-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="voorraad-modal-title">
        <div class="modal-header">
            <h2 id="voorraad-modal-title"><?= moirai_h(LOC('voorraad.tab')) ?></h2>
            <button type="button" class="close-btn" data-voorraad-close aria-label="<?= moirai_h(LOC('moirai.btn.close')) ?>">&times;</button>
        </div>
        <div class="message" id="voorraad-modal-message"></div>
        <div id="voorraad-modal-body"></div>
        <div class="modal-actions" id="voorraad-modal-actions"></div>
    </div>
</div>
<script src="js/voorraad.js?v=<?= (int) @filemtime(__DIR__ . '/js/voorraad.js') ?>"></script>
</div>
