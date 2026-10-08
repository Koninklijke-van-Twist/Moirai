<?php
/**
 * Markup voor het tabblad Telefoonbudget (alleen ICT-admins). Wordt ge-include vanuit index.php.
 * Logica: js/telefoonbudget.js, server: budget_api.php / moirai_budget.php.
 */
if (!moirai_is_admin()) {
    return;
}
$budgetKeys = array_values(array_filter(array_map('trim', array_keys(TRANSLATIONS['nl'])), static fn(string $k): bool => str_starts_with($k, 'budget.')));
$budgetKeys = array_merge($budgetKeys, ['moirai.btn.save', 'moirai.btn.cancel', 'moirai.btn.close', 'moirai.error.request_failed', 'moirai.loader.devices']);
$budgetModal = static function (string $id, string $titleKey, string $bodyHtml, string $extraClass = ''): void {
    echo '<div class="modal-backdrop budget-layer ' . moirai_h($extraClass) . '" id="' . moirai_h($id) . '" aria-hidden="true">'
        . '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="' . moirai_h($id) . '-title">'
        . '<div class="modal-header"><h2 id="' . moirai_h($id) . '-title">' . moirai_h(LOC($titleKey)) . '</h2>'
        . '<button type="button" class="close-btn" data-budget-close="' . moirai_h($id) . '" aria-label="' . moirai_h(LOC('moirai.btn.close')) . '">&times;</button></div>'
        . '<div class="message" data-budget-message></div>'
        . $bodyHtml
        . '</div></div>';
};
?>
<div id="budget-root">
<style>
    .panel.is-budget > :not(.tabs):not(#budget-root) { display: none !important; }
    body.budget-active #add-device-btn { display: none; }
    #budget-panel[hidden] { display: none; }
    .budget-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 12px; }
    .budget-toolbar .budget-search { flex: 1 1 240px; }
    .budget-toolbar input[type=search] { width: 100%; }
    .budget-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
    .budget-table th, .budget-table td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--kvt-line); vertical-align: top; }
    .budget-table td.num, .budget-table th.num { text-align: right; white-space: nowrap; }
    .budget-table tbody tr.budget-person-row { cursor: pointer; }
    .budget-table tbody tr.budget-person-row:hover { background: #f3f8ff; }
    .budget-muted { color: var(--kvt-muted); font-size: 0.85rem; }
    .budget-pager { display: flex; gap: 8px; align-items: center; justify-content: flex-end; margin-top: 10px; }
    .budget-top { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 12px; border: 1px solid var(--kvt-line); border-radius: 12px; background: #f8fbff; margin-bottom: 12px; }
    .budget-amount { font-size: 1.8rem; font-weight: 800; color: var(--kvt-perkins-blue); }
    .budget-plus { width: 44px; height: 44px; border-radius: 50%; font-size: 1.6rem; line-height: 1; padding: 0; }
    .budget-value { font-size: 0.9rem; }
    .budget-status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; }
    .budget-status-onbevestigd { background: #fff4d6; color: #8a5a00; border: 1px dashed #d79a00; }
    .budget-status-bevestigd { background: #e3f6e8; color: #1d6b34; }
    tr.budget-unconfirmed td { background: #fffbef; }
    .budget-row-actions { display: flex; flex-wrap: wrap; gap: 4px; }
    .budget-row-actions .btn { padding: 4px 8px; font-size: 0.8rem; }
    .budget-note { white-space: pre-wrap; color: var(--kvt-muted); font-size: 0.82rem; }
    .budget-preview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
    .budget-preview div { border: 1px solid var(--kvt-line); border-radius: 10px; padding: 8px; }
    .budget-preview strong { display: block; font-size: 1.1rem; }
    .budget-preview .is-own strong { color: var(--kvt-danger); }
    .modal-backdrop.budget-layer { z-index: 1100; }
    .modal-backdrop.budget-layer.budget-layer-2 { z-index: 1150; }
    .modal-backdrop.budget-layer.budget-layer-3 { z-index: 1250; }
    #budget-person-modal .modal, #budget-import-modal .modal { max-width: 980px; }
    #budget-match-suggestions { display: flex; flex-wrap: wrap; gap: 6px; }
    #budget-match-suggestions .chip.is-active { outline: 2px solid currentColor; }
    .budget-start-form { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; margin-bottom: 12px; }
    .budget-link-block { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--kvt-line); }
    .budget-link-block select { width: 100%; }
    .budget-table-wrap { overflow-x: auto; }
</style>

<section id="budget-panel" hidden data-csrf="<?= moirai_h((string) ($budgetCsrf ?? moirai_budget_csrf_token())) ?>" data-i18n="<?= moirai_h(localizationJsTranslations($budgetKeys)) ?>" data-today="<?= moirai_h(moirai_today()->format('Y-m-d')) ?>">
    <div class="budget-toolbar">
        <div class="budget-search">
            <label for="budget-search"><?= moirai_h(LOC('moirai.label.search')) ?></label>
            <input type="search" id="budget-search" placeholder="<?= moirai_h(LOC('budget.search.placeholder')) ?>">
        </div>
        <div class="status-filters" id="budget-filters">
            <button type="button" class="chip is-active" data-filter="all"><?= moirai_h(LOC('budget.filter.all')) ?></button>
            <button type="button" class="chip" data-filter="with"><?= moirai_h(LOC('budget.filter.with')) ?></button>
            <button type="button" class="chip" data-filter="without"><?= moirai_h(LOC('budget.filter.without')) ?></button>
        </div>
        <button type="button" class="btn btn-primary" id="budget-add-person-btn"><?= moirai_h(LOC('budget.add_person')) ?></button>
        <button type="button" class="btn btn-secondary" id="budget-settings-btn"><?= moirai_h(LOC('budget.settings')) ?></button>
        <button type="button" class="btn btn-secondary" id="budget-import-btn"><?= moirai_h(LOC('budget.import')) ?></button>
    </div>
    <div class="message" id="budget-page-message"></div>
    <div class="budget-table-wrap">
        <table class="budget-table">
            <thead><tr>
                <th><?= moirai_h(LOC('budget.col.person')) ?></th>
                <th><?= moirai_h(LOC('budget.col.start')) ?></th>
                <th class="num"><?= moirai_h(LOC('budget.col.budget')) ?></th>
                <th><?= moirai_h(LOC('budget.col.last')) ?></th>
            </tr></thead>
            <tbody id="budget-people"></tbody>
        </table>
    </div>
    <div class="budget-pager">
        <span class="budget-muted" id="budget-page-info"></span>
        <button type="button" class="btn btn-secondary" id="budget-prev"><?= moirai_h(LOC('budget.prev')) ?></button>
        <button type="button" class="btn btn-secondary" id="budget-next"><?= moirai_h(LOC('budget.next')) ?></button>
    </div>
</section>
<?php
$budgetModal('budget-person-modal', 'budget.modal.person', '<div id="budget-person-body"></div>');
$budgetModal('budget-purchase-modal', 'budget.modal.purchase_new', '
    <form class="form-grid" id="budget-purchase-form" autocomplete="off">
        <label>' . moirai_h(LOC('budget.field.price')) . '<input type="text" inputmode="decimal" name="prijs" required></label>
        <label>' . moirai_h(LOC('budget.field.date')) . '<input type="date" name="datum" required></label>
        <label>' . moirai_h(LOC('budget.field.phone')) . '<input type="text" name="telefoon" maxlength="200"></label>
        <label>' . moirai_h(LOC('budget.field.note')) . '<textarea name="notitie" rows="3" maxlength="4000"></textarea></label>
        <div class="budget-preview" id="budget-purchase-preview" aria-live="polite"></div>
        <p class="budget-muted">' . moirai_h(LOC('budget.unconfirmed_hint')) . '</p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">' . moirai_h(LOC('moirai.btn.save')) . '</button>
            <button type="button" class="btn btn-secondary" data-budget-close="budget-purchase-modal">' . moirai_h(LOC('moirai.btn.cancel')) . '</button>
        </div>
    </form>', 'budget-layer-2');
$budgetModal('budget-confirm-modal', 'budget.confirm.title', '
    <p id="budget-confirm-text"></p>
    <div class="modal-actions">
        <button type="button" class="btn btn-primary" id="budget-confirm-yes">' . moirai_h(LOC('budget.btn.yes')) . '</button>
        <button type="button" class="btn btn-secondary" data-budget-close="budget-confirm-modal">' . moirai_h(LOC('moirai.btn.cancel')) . '</button>
    </div>', 'budget-layer-3');
$budgetModal('budget-settings-modal', 'budget.settings', '
    <form class="form-grid" id="budget-settings-form">
        <label>' . moirai_h(LOC('budget.settings.start')) . '<input type="text" inputmode="decimal" name="start_cents" required></label>
        <label>' . moirai_h(LOC('budget.settings.monthly')) . '<input type="text" inputmode="decimal" name="monthly_cents" required></label>
        <label>' . moirai_h(LOC('budget.settings.max')) . '<input type="text" inputmode="decimal" name="max_cents" required></label>
        <label>' . moirai_h(LOC('budget.settings.depreciation')) . '<input type="text" inputmode="decimal" name="depreciation_cents" required></label>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">' . moirai_h(LOC('moirai.btn.save')) . '</button>
            <button type="button" class="btn btn-secondary" data-budget-close="budget-settings-modal">' . moirai_h(LOC('moirai.btn.cancel')) . '</button>
        </div>
    </form>');
$budgetModal('budget-import-modal', 'budget.import', '
    <form class="form-grid" id="budget-import-form">
        <label>' . moirai_h(LOC('budget.import.file')) . '<input type="file" name="file" accept=".xls,.xlsx" required></label>
        <div class="modal-actions"><button type="submit" class="btn btn-secondary">' . moirai_h(LOC('budget.import.preview')) . '</button></div>
    </form>
    <div id="budget-import-result"></div>');
$budgetModal('budget-start-modal', 'budget.import.ask_start', '
    <form class="form-grid" id="budget-start-form">
        <p id="budget-start-text"></p>
        <label>' . moirai_h(LOC('budget.col.start')) . '<input type="date" name="indiensttreding" required></label>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">' . moirai_h(LOC('budget.import.next')) . '</button>
            <button type="button" class="btn btn-secondary" data-budget-close="budget-start-modal">' . moirai_h(LOC('budget.import.cancel')) . '</button>
        </div>
    </form>', 'budget-layer-2');
$budgetModal('budget-add-person-modal', 'budget.add_person', '
    <form class="form-grid" id="budget-add-person-form" autocomplete="off">
        <p class="budget-muted">' . moirai_h(LOC('budget.add_person.hint')) . '</p>
        <label>' . moirai_h(LOC('budget.field.email')) . '<input type="email" name="email" required maxlength="200"></label>
        <label>' . moirai_h(LOC('budget.field.name')) . '<input type="text" name="naam" maxlength="200"></label>
        <label>' . moirai_h(LOC('budget.col.start')) . '<input type="date" name="indiensttreding" required></label>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">' . moirai_h(LOC('moirai.btn.save')) . '</button>
            <button type="button" class="btn btn-secondary" data-budget-close="budget-add-person-modal">' . moirai_h(LOC('moirai.btn.cancel')) . '</button>
        </div>
    </form>');
$budgetModal('budget-match-modal', 'budget.import.match_title', '
    <form class="form-grid" id="budget-match-form" autocomplete="off">
        <p id="budget-match-text"></p>
        <div id="budget-match-suggestions"></div>
        <label>' . moirai_h(LOC('budget.field.email')) . '<input type="email" name="email" list="budget-match-people" maxlength="200"></label>
        <datalist id="budget-match-people"></datalist>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">' . moirai_h(LOC('budget.import.link')) . '</button>
            <button type="button" class="btn btn-secondary" id="budget-match-skip">' . moirai_h(LOC('budget.import.skip_person')) . '</button>
            <button type="button" class="btn btn-secondary" data-budget-close="budget-match-modal">' . moirai_h(LOC('budget.import.cancel')) . '</button>
        </div>
    </form>', 'budget-layer-2');
?>
</div>
<script src="js/telefoonbudget.js?v=<?= (int) @filemtime(__DIR__ . '/js/telefoonbudget.js') ?>"></script>
