<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';

$isAdmin = moirai_is_admin();
$userEmail = (string) ($_SESSION['user']['email'] ?? '');
$userName = (string) ($_SESSION['user']['name'] ?? $userEmail);
$todayIso = date('Y-m-d');

$moiraiJsKeys = [
    'moirai.badge.assigned', 'moirai.badge.reserve', 'moirai.unnamed', 'moirai.filter.all',
    'moirai.filter.os', 'moirai.filter.os_version', 'moirai.filter.model', 'moirai.filter.ram', 'moirai.filter.storage', 'moirai.filter.screen', 'moirai.filter.keyboard',
    'moirai.modal.device', 'moirai.modal.edit', 'moirai.modal.new', 'moirai.modal.assign', 'moirai.modal.history',
    'moirai.btn.edit', 'moirai.btn.assign', 'moirai.btn.history', 'moirai.btn.notes', 'moirai.btn.print_label', 'moirai.btn.save', 'moirai.btn.cancel',
    'moirai.btn.delete', 'moirai.btn.refresh_users', 'moirai.field.model', 'moirai.field.serial', 'moirai.field.imei',
    'moirai.field.ram', 'moirai.field.storage', 'moirai.field.cpu', 'moirai.field.purchase_date', 'moirai.field.os', 'moirai.field.os_version', 'moirai.field.keyboard',
    'moirai.field.screen', 'moirai.field.assigned_to', 'moirai.select.choose', 'moirai.select.reserve',
    'moirai.history.empty', 'moirai.history.current', 'moirai.history.entry', 'moirai.history.since',
    'moirai.confirm.delete', 'moirai.delete.confirm.title', 'moirai.delete.confirm.body',
    'moirai.btn.delete_confirm', 'moirai.unknown_user', 'moirai.error.request_failed', 'moirai.missing.fields',
    'moirai.error.print_failed',
    'moirai.notes.title', 'moirai.notes.messages', 'moirai.notes.empty', 'moirai.notes.message_label', 'moirai.notes.edit_label',
    'moirai.notes.load_failed', 'moirai.notes.send_failed', 'moirai.notes.save_failed', 'moirai.notes.delete_failed',
    'moirai.notes.cancel_edit', 'moirai.notes.edited_suffix', 'moirai.notes.delete.confirm.title', 'moirai.notes.delete.confirm.body',
    'moirai.notes.btn.delete_confirm',
];

?>
<!DOCTYPE html>
<html lang="<?= moirai_h(getHtmlLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= moirai_h(LOC('moirai.title')) ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="stylesheet" href="brand.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 16px 16px 48px;
        }

        .hero {
            background: var(--kvt-panel-bg);
            border: 1px solid var(--kvt-line);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .hero-top {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .hero h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--kvt-perkins-blue);
        }

        .hero-sub {
            margin: 8px 0 0;
            color: var(--kvt-muted);
            font-size: 0.95rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .panel {
            background: var(--kvt-panel-bg);
            border: 1px solid var(--kvt-line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .tab {
            flex: 1;
            border: 1px solid var(--kvt-line);
            background: #f8fbff;
            color: var(--kvt-text);
            border-radius: 10px;
            padding: 12px 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .tab.is-active {
            background: var(--kvt-main-blue);
            border-color: var(--kvt-main-blue);
            color: #fff;
        }

        .filters {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .filters-row {
            display: grid;
            gap: 10px;
        }

        @media (min-width: 640px) {
            .filters-row {
                grid-template-columns: 1fr auto;
                align-items: end;
            }
        }

        .attr-filters {
            display: grid;
            gap: 10px;
            grid-template-columns: 1fr;
            margin-bottom: 16px;
        }

        @media (min-width: 640px) {
            .attr-filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 900px) {
            .attr-filters {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        label {
            display: block;
            font-size: 0.85rem;
            color: var(--kvt-muted);
            margin-bottom: 4px;
        }

        input[type="search"],
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--kvt-line);
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
            background: #fff;
        }

        .status-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border: 1px solid var(--kvt-line);
            background: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .chip.is-active {
            background: #e6f4ff;
            border-color: var(--kvt-main-blue);
            color: var(--kvt-perkins-blue);
        }

        .device-list:not([hidden]) {
            display: grid;
            gap: 10px;
        }

        #list-loader[hidden],
        #device-list[hidden],
        #empty-state[hidden] {
            display: none !important;
        }

        .device-item {
            width: 100%;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            text-align: left;
            border: 1px solid var(--kvt-line);
            background: #fff;
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
        }

        .device-item-main {
            flex: 1;
            min-width: 0;
        }

        .device-missing {
            flex: 0 1 auto;
            max-width: 42%;
            text-align: right;
            font-size: 0.72rem;
            line-height: 1.4;
            color: #92400e;
        }

        .device-missing-label {
            display: block;
            font-weight: 700;
            color: var(--kvt-danger);
            margin-bottom: 3px;
        }

        @media (max-width: 540px) {
            .device-item {
                flex-direction: column;
            }

            .device-missing {
                max-width: none;
                width: 100%;
                text-align: left;
                padding-top: 8px;
                border-top: 1px dashed var(--kvt-line);
            }
        }

        .device-item:hover {
            border-color: var(--kvt-main-blue);
            background: #f8fbff;
        }

        .device-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .device-qr-icon {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            color: var(--kvt-main-blue);
        }

        .device-qr-icon img,
        .device-qr-icon svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .device-meta {
            margin: 0;
            color: var(--kvt-muted);
            font-size: 0.9rem;
        }

        .badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-assigned {
            background: var(--kvt-row-ok);
            color: #166534;
        }

        .badge-reserve {
            background: #eef2ff;
            color: #3730a3;
        }

        .empty-state {
            text-align: center;
            color: var(--kvt-muted);
            padding: 28px 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--kvt-main-blue);
            color: #fff;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: var(--kvt-text);
        }

        .btn-danger {
            background: #fee2e2;
            color: var(--kvt-danger);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
            z-index: 1000;
        }

        .modal-backdrop.is-open {
            display: flex;
        }

        @media (min-width: 640px) {
            .modal-backdrop {
                align-items: center;
                padding: 20px;
            }
        }

        .modal {
            width: 100%;
            max-width: 640px;
            max-height: 92vh;
            overflow: auto;
            background: #fff;
            border-radius: 16px 16px 0 0;
            padding: 20px;
        }

        @media (min-width: 640px) {
            .modal {
                border-radius: 16px;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .close-btn {
            border: none;
            background: transparent;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: var(--kvt-muted);
        }

        .detail-grid {
            display: grid;
            gap: 12px;
        }

        .detail-row dt {
            font-size: 0.8rem;
            color: var(--kvt-muted);
            margin-bottom: 2px;
        }

        .detail-row dd {
            margin: 0;
            font-weight: 600;
        }

        .history-list {
            margin: 0;
            padding-left: 18px;
        }

        .history-list li {
            margin-bottom: 8px;
        }

        .form-grid {
            display: grid;
            gap: 12px;
        }

        .assign-user-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }

        .assign-user-row select {
            flex: 1;
            min-width: 0;
        }

        .btn-icon {
            flex: 0 0 auto;
            width: 42px;
            min-width: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .btn-icon svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .btn-icon.is-busy {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn-icon.is-busy svg {
            animation: moirai-spin 0.8s linear infinite;
        }

        @keyframes moirai-spin {
            to { transform: rotate(360deg); }
        }

        .modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .message {
            display: none;
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fdebec;
            color: var(--kvt-danger);
            font-weight: 600;
        }

        .message.is-visible {
            display: block;
        }

        .history-block {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--kvt-line);
        }

        .history-block h3 {
            margin: 0 0 8px;
            font-size: 0.95rem;
            color: var(--kvt-perkins-blue);
        }

        .loader {
            text-align: center;
            color: var(--kvt-muted);
            padding: 24px;
        }

        .modal-backdrop.confirm-layer {
            z-index: 1200;
        }

        .confirm-delete-modal {
            padding: 0;
            overflow: hidden;
            border: 2px solid var(--kvt-danger);
            animation: confirm-delete-pulse 1.15s ease-in-out infinite;
        }

        @keyframes confirm-delete-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(180, 35, 24, 0.55);
            }
            50% {
                box-shadow: 0 0 0 14px rgba(180, 35, 24, 0);
            }
        }

        .hazard-tape {
            overflow: hidden;
            height: 34px;
            border-bottom: 2px solid #111827;
            background: repeating-linear-gradient(
                -45deg,
                #facc15 0 14px,
                #111827 14px 28px
            );
            background-size: 40px 40px;
            animation: hazard-tape-scroll 1.4s linear infinite;
        }

        @keyframes hazard-tape-scroll {
            from { background-position: 0 0; }
            to { background-position: -40px 0; }
        }

        .confirm-delete-body {
            padding: 20px;
        }

        .confirm-delete-body h2 {
            margin: 0 0 10px;
            font-size: 1.15rem;
            color: var(--kvt-danger);
        }

        .confirm-delete-body p {
            margin: 0 0 8px;
            color: var(--kvt-text);
            line-height: 1.5;
        }

        .confirm-delete-device {
            margin: 0 0 16px;
            font-weight: 700;
            color: var(--kvt-perkins-blue);
        }

        .notes-overlay {
            position: fixed;
            inset: 0;
            z-index: 1100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(15, 35, 63, 0.45);
        }

        .notes-overlay.is-open {
            display: flex;
        }

        .notes-dialog {
            width: min(720px, 96vw);
            max-height: min(88vh, 820px);
            min-height: min(70vh, 560px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(15, 35, 63, 0.22);
            overflow: hidden;
        }

        .notes-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--kvt-line);
            flex: 0 0 auto;
        }

        .notes-head h2 {
            margin: 0;
            font-size: 1.15rem;
            color: var(--kvt-perkins-blue);
        }

        .notes-close {
            border: 0;
            background: transparent;
            font-size: 1.6rem;
            line-height: 1;
            cursor: pointer;
            color: var(--kvt-muted);
            padding: 2px 8px;
            border-radius: 8px;
        }

        .notes-close:hover,
        .notes-close:focus-visible {
            background: #f1f5f9;
            color: var(--kvt-text);
            outline: none;
        }

        .notes-chat.ponos-detail-chat {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
            overflow: hidden;
            padding: 18px 20px 20px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }

        .notes-dialog .ponos-detail-chat-title {
            margin: 0 0 12px;
            flex: 0 0 auto;
            font-size: 1rem;
            color: var(--kvt-perkins-blue);
        }

        .notes-dialog .ponos-messages {
            display: grid;
            gap: 10px;
            flex: 1 1 0;
            min-height: 0;
            overflow-y: auto;
            overflow-x: visible;
            padding: 0 4px 0 12px;
            align-content: start;
        }

        .notes-dialog .ponos-message-row {
            display: flex;
            align-items: flex-start;
            gap: 0;
            overflow: visible;
        }

        .notes-dialog .ponos-message-avatar-wrap {
            flex: 0 0 30px;
            margin-right: -9px;
            margin-top: 6px;
            position: relative;
            z-index: 2;
        }

        .notes-dialog .ponos-message-row .ponos-message {
            flex: 1 1 auto;
            min-width: 0;
        }

        .notes-dialog .ponos-message {
            border: 1px solid var(--kvt-line);
            border-radius: 12px;
            padding: 10px 12px;
        }

        .notes-dialog .ponos-message-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.82rem;
        }

        .notes-dialog .ponos-message-email {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
        }

        .notes-dialog .ponos-message-actions {
            display: inline-flex;
            gap: 4px;
            margin-left: auto;
        }

        .notes-dialog .ponos-message-action {
            border: 1px solid var(--kvt-line);
            background: #fff;
            color: var(--kvt-muted);
            border-radius: 8px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            line-height: 1.4;
        }

        .notes-dialog .ponos-message-action:hover,
        .notes-dialog .ponos-message-action:focus-visible {
            color: var(--kvt-text);
            border-color: var(--kvt-main-blue);
            outline: none;
        }

        .notes-dialog .ponos-message-action.is-danger:hover,
        .notes-dialog .ponos-message-action.is-danger:focus-visible {
            color: var(--kvt-danger);
            border-color: var(--kvt-danger);
        }

        .notes-dialog .ponos-user-avatar {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: block;
            border: 1px solid;
            border-radius: 4px;
            background: #fff;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
            object-fit: none;
        }

        .notes-dialog .ponos-message-compose {
            display: grid;
            gap: 8px;
            flex: 0 0 auto;
            flex-shrink: 0;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--kvt-line);
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }

        .notes-dialog .ponos-compose-label {
            display: block;
            font-weight: 700;
            color: var(--kvt-perkins-blue);
            font-size: 0.9rem;
        }

        .notes-dialog .ponos-compose-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .notes-dialog .ponos-compose-toolbar[hidden] {
            display: none !important;
        }

        .notes-dialog .ponos-message-input {
            width: 100%;
            min-height: 2.75rem;
            max-height: min(32vh, 280px);
            resize: none;
            overflow-y: hidden;
            font: inherit;
            border-radius: 10px;
            border: 1px solid var(--kvt-line);
            padding: 10px 12px;
            box-sizing: border-box;
            line-height: 1.45;
        }

        .notes-dialog .ponos-text-link {
            color: var(--kvt-main-blue);
        }

        .notes-empty {
            margin: 0 0 8px;
            flex: 0 0 auto;
            color: var(--kvt-muted);
        }

        .notes-feedback {
            margin: 0;
            padding: 8px 18px 14px;
            flex: 0 0 auto;
            color: var(--kvt-muted);
        }

        .notes-feedback.is-error {
            color: var(--kvt-danger);
        }

        .modal-backdrop.confirm-layer.notes-confirm-layer {
            z-index: 1300;
        }

        @media (max-width: 540px) {
            .notes-dialog {
                width: 100%;
                max-height: 94vh;
                min-height: 70vh;
                border-radius: 12px;
            }

            .notes-chat.ponos-detail-chat {
                padding: 14px 14px 16px;
            }
        }
    </style>
    <?php renderMoiraiLanguageRailStyles(); ?>
</head>
<body>
<?php renderMoiraiLanguageRail(); ?>
<div class="page">
    <header class="hero">
        <div class="hero-top">
            <div>
                <h1><?= moirai_h(LOC('moirai.title')) ?></h1>
                <p class="hero-sub">
                    <?= moirai_h(LOC('moirai.hero.subtitle', $userName)) ?><?= $isAdmin ? ' · ' . moirai_h(LOC('moirai.admin.badge')) : '' ?>
                </p>
            </div>
            <?php if ($isAdmin): ?>
            <div class="hero-actions">
                <a class="btn btn-secondary" href="download_enroll.php"><?= moirai_h(LOC('moirai.btn.enroll')) ?></a>
                <button type="button" class="btn btn-primary" id="add-device-btn"><?= moirai_h(LOC('moirai.btn.add_device')) ?></button>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="panel">
        <div class="tabs" role="tablist" aria-label="<?= moirai_h(LOC('moirai.title')) ?>">
            <button type="button" class="tab is-active" data-tab="laptop" role="tab" aria-selected="true"><?= moirai_h(LOC('moirai.tab.laptops')) ?></button>
            <button type="button" class="tab" data-tab="phone" role="tab" aria-selected="false"><?= moirai_h(LOC('moirai.tab.phones')) ?></button>
        </div>

        <div class="attr-filters" id="attr-filters"></div>

        <div class="filters">
            <div class="filters-row">
                <div>
                    <label for="search-input"><?= moirai_h(LOC('moirai.label.search')) ?></label>
                    <input type="search" id="search-input" placeholder="<?= moirai_h(LOC('moirai.placeholder.search')) ?>">
                </div>
                <div>
                    <label><?= moirai_h(LOC('moirai.label.status')) ?></label>
                    <div class="status-filters" id="status-filters">
                        <button type="button" class="chip is-active" data-status="all"><?= moirai_h(LOC('moirai.status.all')) ?></button>
                        <button type="button" class="chip" data-status="assigned"><?= moirai_h(LOC('moirai.status.assigned')) ?></button>
                        <button type="button" class="chip" data-status="reserve"><?= moirai_h(LOC('moirai.status.reserve')) ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="message" id="page-message"></div>
        <div class="loader" id="list-loader"><?= moirai_h(LOC('moirai.loader.devices')) ?></div>
        <div class="device-list" id="device-list" hidden></div>
        <div class="empty-state" id="empty-state" hidden><?= moirai_h(LOC('moirai.empty.devices')) ?></div>
    </main>
</div>

<div class="modal-backdrop" id="device-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
            <h2 id="modal-title"><?= moirai_h(LOC('moirai.modal.device')) ?></h2>
            <button type="button" class="close-btn" data-close-modal="device-modal" aria-label="<?= moirai_h(LOC('moirai.btn.close')) ?>">&times;</button>
        </div>
        <div class="message" id="modal-message"></div>
        <div id="modal-view"></div>
        <div id="modal-form" hidden></div>
        <div class="modal-actions" id="modal-actions"></div>
    </div>
</div>

<div class="modal-backdrop" id="assign-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="assign-modal-title">
        <div class="modal-header">
            <h2 id="assign-modal-title"><?= moirai_h(LOC('moirai.modal.assign')) ?></h2>
            <button type="button" class="close-btn" data-close-modal="assign-modal" aria-label="<?= moirai_h(LOC('moirai.btn.close')) ?>">&times;</button>
        </div>
        <div class="message" id="assign-modal-message"></div>
        <div id="assign-history"></div>
        <div id="assign-form-wrap"></div>
        <div class="modal-actions" id="assign-modal-actions"></div>
    </div>
</div>

<div class="modal-backdrop" id="history-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="history-modal-title">
        <div class="modal-header">
            <h2 id="history-modal-title"><?= moirai_h(LOC('moirai.modal.history')) ?></h2>
            <button type="button" class="close-btn" data-close-modal="history-modal" aria-label="<?= moirai_h(LOC('moirai.btn.close')) ?>">&times;</button>
        </div>
        <div id="history-modal-body"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-close-modal="history-modal"><?= moirai_h(LOC('moirai.btn.close')) ?></button>
        </div>
    </div>
</div>

<div class="modal-backdrop confirm-layer" id="delete-confirm-modal" aria-hidden="true">
    <div class="modal confirm-delete-modal" role="alertdialog" aria-modal="true" aria-labelledby="delete-confirm-title">
        <div class="hazard-tape" aria-hidden="true"></div>
        <div class="confirm-delete-body">
            <h2 id="delete-confirm-title"><?= moirai_h(LOC('moirai.delete.confirm.title')) ?></h2>
            <p><?= moirai_h(LOC('moirai.delete.confirm.body')) ?></p>
            <p class="confirm-delete-device" id="delete-confirm-device"></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" id="delete-confirm-yes"><?= moirai_h(LOC('moirai.btn.delete_confirm')) ?></button>
                <button type="button" class="btn btn-secondary" id="delete-confirm-no"><?= moirai_h(LOC('moirai.btn.cancel')) ?></button>
            </div>
        </div>
    </div>
</div>

<div class="notes-overlay" id="notes-modal" hidden aria-hidden="true" data-role="notes-modal">
    <div class="notes-dialog" role="dialog" aria-modal="true" aria-labelledby="notes-title">
        <div class="notes-head">
            <h2 id="notes-title"><?= moirai_h(LOC('moirai.notes.title')) ?></h2>
            <button type="button" class="notes-close" data-role="notes-close"
                aria-label="<?= moirai_h(LOC('moirai.notes.close')) ?>">&times;</button>
        </div>
        <div class="notes-chat ponos-detail-chat">
            <h3 class="ponos-detail-chat-title"><?= moirai_h(LOC('moirai.notes.messages')) ?></h3>
            <div class="ponos-messages" data-role="notes-messages" aria-live="polite"></div>
            <p class="notes-empty" data-role="notes-empty" hidden><?= moirai_h(LOC('moirai.notes.empty')) ?></p>
            <div class="ponos-message-compose">
                <label class="ponos-compose-label" for="notes-message-text" data-role="notes-compose-label"><?= moirai_h(LOC('moirai.notes.message_label')) ?></label>
                <textarea id="notes-message-text" class="ponos-message-input" data-role="notes-input"
                    rows="1" maxlength="4000"></textarea>
                <div class="ponos-compose-toolbar" data-role="notes-compose-toolbar" hidden>
                    <button type="button" class="btn btn-secondary" data-role="notes-cancel-edit"><?= moirai_h(LOC('moirai.notes.cancel_edit')) ?></button>
                </div>
            </div>
        </div>
        <p class="notes-feedback" data-role="notes-feedback" hidden></p>
    </div>
</div>

<div class="modal-backdrop confirm-layer notes-confirm-layer" id="notes-delete-confirm-modal" aria-hidden="true">
    <div class="modal confirm-delete-modal" role="alertdialog" aria-modal="true" aria-labelledby="notes-delete-confirm-title">
        <div class="hazard-tape" aria-hidden="true"></div>
        <div class="confirm-delete-body">
            <h2 id="notes-delete-confirm-title"><?= moirai_h(LOC('moirai.notes.delete.confirm.title')) ?></h2>
            <p><?= moirai_h(LOC('moirai.notes.delete.confirm.body')) ?></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" id="notes-delete-confirm-yes"><?= moirai_h(LOC('moirai.notes.btn.delete_confirm')) ?></button>
                <button type="button" class="btn btn-secondary" id="notes-delete-confirm-no"><?= moirai_h(LOC('moirai.btn.cancel')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    var todayIso = <?= json_encode($todayIso) ?>;
    var laptopKeyboardOptions = <?= json_encode(MOIRAI_LAPTOP_KEYBOARD_OPTIONS, JSON_UNESCAPED_UNICODE) ?>;
    var i18n = <?= localizationJsTranslations($moiraiJsKeys) ?>;
    var state = {
        tab: 'laptop',
        query: '',
        status: 'all',
        attrFilters: {},
        filterOptions: {},
        devices: [],
        users: [],
        currentDevice: null,
        editing: false,
        pendingDeleteDevice: null,
        notesDevice: null,
        notesEditingId: null,
        pendingDeleteNoteId: null
    };
    var listRequestId = 0;

    var filterConfig = {
        laptop: [
            { name: 'os', labelKey: 'moirai.filter.os' },
            { name: 'os_versie', labelKey: 'moirai.filter.os_version' },
            { name: 'model', labelKey: 'moirai.filter.model' },
            { name: 'ram', labelKey: 'moirai.filter.ram' },
            { name: 'opslag', labelKey: 'moirai.filter.storage' },
            { name: 'toetsenbord', labelKey: 'moirai.filter.keyboard' }
        ],
        phone: [
            { name: 'os', labelKey: 'moirai.filter.os' },
            { name: 'os_versie', labelKey: 'moirai.filter.os_version' },
            { name: 'model', labelKey: 'moirai.filter.model' },
            { name: 'schermformaat', labelKey: 'moirai.filter.screen' },
            { name: 'opslag', labelKey: 'moirai.filter.storage' }
        ]
    };

    var tabs = document.querySelectorAll('.tab');
    var searchInput = document.getElementById('search-input');
    var statusFilters = document.getElementById('status-filters');
    var attrFiltersEl = document.getElementById('attr-filters');
    var deviceList = document.getElementById('device-list');
    var listLoader = document.getElementById('list-loader');
    var emptyState = document.getElementById('empty-state');
    var pageMessage = document.getElementById('page-message');
    var modal = document.getElementById('device-modal');
    var modalView = document.getElementById('modal-view');
    var modalForm = document.getElementById('modal-form');
    var modalActions = document.getElementById('modal-actions');
    var modalMessage = document.getElementById('modal-message');
    var modalTitle = document.getElementById('modal-title');
    var assignModalMessage = document.getElementById('assign-modal-message');

    function t(key) {
        var args = Array.prototype.slice.call(arguments, 1);
        var str = i18n[key] || key;
        args.forEach(function (arg) {
            str = str.replace('%s', arg);
        });
        return str;
    }

    function openBackdrop(id) {
        var el = document.getElementById(id);
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function closeBackdrop(id) {
        var el = document.getElementById(id);
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
    }

    function keyField(type) {
        return type === 'laptop' ? 'serienummer' : 'imei';
    }

    function keyLabel(type) {
        return type === 'laptop' ? 'Serienummer' : 'IMEI';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showMessage(el, text) {
        if (!text) {
            el.textContent = '';
            el.classList.remove('is-visible');
            return;
        }
        el.textContent = text;
        el.classList.add('is-visible');
    }

    function apiUrl(params) {
        var query = new URLSearchParams(params);
        return 'devices_api.php?' + query.toString();
    }

    function fetchJson(url, options) {
        return fetch(url, options || {}).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || t('moirai.error.request_failed'));
                }
                return data;
            });
        });
    }

    function showListLoading() {
        listLoader.hidden = false;
        deviceList.innerHTML = '';
        deviceList.hidden = true;
        emptyState.hidden = true;
    }

    function loadDevices() {
        var req = ++listRequestId;
        var tab = state.tab;
        showListLoading();
        showMessage(pageMessage, '');

        var params = {
            action: 'list',
            type: tab,
            q: state.query,
            status: state.status
        };

        filterConfig[tab].forEach(function (filter) {
            if (state.attrFilters[filter.name]) {
                params[filter.name] = state.attrFilters[filter.name];
            }
        });

        return fetchJson(apiUrl(params)).then(function (data) {
            if (req !== listRequestId || tab !== state.tab) {
                return;
            }
            state.devices = data.devices || [];
            renderList();
        }).catch(function (error) {
            if (req !== listRequestId || tab !== state.tab) {
                return;
            }
            showMessage(pageMessage, error.message);
            listLoader.hidden = true;
        });
    }

    function renderAttrFilters() {
        var config = filterConfig[state.tab];
        var options = state.filterOptions || {};

        attrFiltersEl.innerHTML = config.map(function (filter) {
            var selected = state.attrFilters[filter.name] || '';
            var html = '<div><label for="filter-' + filter.name + '">' + escapeHtml(t(filter.labelKey)) + '</label>';
            html += '<select id="filter-' + filter.name + '" data-filter="' + filter.name + '">';
            html += '<option value="">' + escapeHtml(t('moirai.filter.all')) + '</option>';
            (options[filter.name] || []).forEach(function (value) {
                var isSelected = selected === value ? ' selected' : '';
                html += '<option value="' + escapeHtml(value) + '"' + isSelected + '>' + escapeHtml(value) + '</option>';
            });
            html += '</select></div>';
            return html;
        }).join('');
    }

    function loadFilterOptions() {
        var tab = state.tab;
        var params = { action: 'filters', type: tab };

        filterConfig[tab].forEach(function (filter) {
            if (state.attrFilters[filter.name]) {
                params[filter.name] = state.attrFilters[filter.name];
            }
        });

        return fetchJson(apiUrl(params)).then(function (data) {
            if (tab !== state.tab) {
                return;
            }
            state.filterOptions = data.filters || {};
            filterConfig[tab].forEach(function (filter) {
                var selected = state.attrFilters[filter.name];
                if (!selected) {
                    return;
                }
                var options = state.filterOptions[filter.name] || [];
                var stillValid = options.some(function (value) {
                    return String(value) === String(selected);
                });
                if (!stillValid) {
                    delete state.attrFilters[filter.name];
                }
            });
            renderAttrFilters();
        });
    }

    function resetAttrFilters() {
        state.attrFilters = {};
    }

    function deviceTitle(device) {
        return device.model || device.naam || t('moirai.unnamed');
    }

    function deviceSubtitle(device, type) {
        var os = String(device.os || '').trim();
        var osVersion = String(device.os_versie || '').trim();
        var key = String(device[keyField(type)] || device.id || '').trim();
        var osPart = [os, osVersion].filter(Boolean).join(' ');

        if (osPart && key) {
            return osPart + ' - ' + key;
        }

        return osPart || key || '—';
    }

    function missingDeviceFields(device, type) {
        return fieldDefinitions(type).filter(function (field) {
            return String(device[field.name] ?? '').trim() === '';
        });
    }

    function renderMissingFieldsBlock(device, type) {
        if (!isAdmin) {
            return '';
        }

        var missing = missingDeviceFields(device, type);
        if (!missing.length) {
            return '';
        }

        var labels = missing.map(function (field) {
            return escapeHtml(t(field.labelKey));
        }).join(', ');

        return '<div class="device-missing">' +
            '<span class="device-missing-label">' + escapeHtml(t('moirai.missing.fields')) + '</span>' +
            '<span>' + labels + '</span></div>';
    }

    function renderList() {
        listLoader.hidden = true;

        if (!state.devices.length) {
            deviceList.innerHTML = '';
            deviceList.hidden = true;
            emptyState.hidden = false;
            return;
        }

        emptyState.hidden = true;
        deviceList.hidden = false;
        deviceList.innerHTML = state.devices.map(function (device) {
            var assigned = device.uitgegeven_aan && device.uitgegeven_aan.email;
            var badgeClass = assigned ? 'badge-assigned' : 'badge-reserve';
            var badgeText = assigned
                ? t('moirai.badge.assigned', device.uitgegeven_aan.naam || device.uitgegeven_aan.email)
                : t('moirai.badge.reserve');
            var key = device[keyField(state.tab)] || device.id;
            var qrIcon = device.qr_geldig
                ? '<span class="device-qr-icon" aria-hidden="true"><img src="icons/qr-verified.svg" alt=""></span>'
                : '';

            return '<button type="button" class="device-item" data-id="' + escapeHtml(key) + '">' +
                '<div class="device-item-main">' +
                '<p class="device-name">' + qrIcon + '<span>' + escapeHtml(deviceTitle(device)) + '</span></p>' +
                '<p class="device-meta">' + escapeHtml(deviceSubtitle(device, state.tab)) + '</p>' +
                '<span class="badge ' + badgeClass + '">' + badgeText + '</span>' +
                '</div>' +
                renderMissingFieldsBlock(device, state.tab) +
                '</button>';
        }).join('');
    }

    function fieldDefinitions(type) {
        if (type === 'laptop') {
            return [
                { name: 'model', labelKey: 'moirai.field.model', required: true },
                { name: 'serienummer', labelKey: 'moirai.field.serial', required: true, key: true },
                { name: 'ram', labelKey: 'moirai.field.ram' },
                { name: 'opslag', labelKey: 'moirai.field.storage' },
                { name: 'cpu', labelKey: 'moirai.field.cpu' },
                { name: 'aanschafdatum', labelKey: 'moirai.field.purchase_date', type: 'date' },
                { name: 'os', labelKey: 'moirai.field.os', type: 'select', options: ['Windows', 'OSX', 'Linux'] },
                { name: 'os_versie', labelKey: 'moirai.field.os_version' },
                { name: 'toetsenbord', labelKey: 'moirai.field.keyboard', type: 'select', options: laptopKeyboardOptions, defaultNew: 'QWERTY (US)' }
            ];
        }

        return [
            { name: 'model', labelKey: 'moirai.field.model', required: true },
            { name: 'imei', labelKey: 'moirai.field.imei', required: true, key: true },
            { name: 'schermformaat', labelKey: 'moirai.field.screen' },
            { name: 'opslag', labelKey: 'moirai.field.storage' },
            { name: 'os', labelKey: 'moirai.field.os', type: 'select', options: ['Android', 'iOS'] },
            { name: 'os_versie', labelKey: 'moirai.field.os_version' },
            { name: 'aanschafdatum', labelKey: 'moirai.field.purchase_date', type: 'date' }
        ];
    }

    function deviceDeepLink(device, type) {
        var deviceId = String(device[keyField(type)] || device.id || '').trim();
        var url = new URL(window.location.origin + window.location.pathname);
        url.searchParams.set('t', type === 'laptop' ? 'l' : 'p');
        url.searchParams.set('d', deviceId);
        return url.toString();
    }

    function migrateHashDeepLinkToQuery() {
        var hash = window.location.hash.replace(/^#/, '');
        if (!hash) {
            return;
        }

        var hashMatch = hash.match(/^(l|p)\/(.+)$/);
        if (!hashMatch) {
            return;
        }

        var params = new URLSearchParams(window.location.search);
        if (params.get('t') && params.get('d')) {
            return;
        }

        params.set('t', hashMatch[1]);
        params.set('d', decodeURIComponent(hashMatch[2]));
        var query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
    }

    function parseDeepLinkFromUrl() {
        var hash = window.location.hash.replace(/^#/, '');
        if (hash) {
            var hashMatch = hash.match(/^(l|p)\/(.+)$/);
            if (hashMatch) {
                return {
                    type: hashMatch[1] === 'l' ? 'laptop' : 'phone',
                    deviceId: decodeURIComponent(hashMatch[2])
                };
            }
        }

        var params = new URLSearchParams(window.location.search);
        var shortType = params.get('t');
        var shortId = params.get('d');
        if (shortType && shortId && (shortType === 'l' || shortType === 'p')) {
            return {
                type: shortType === 'l' ? 'laptop' : 'phone',
                deviceId: shortId
            };
        }

        var type = params.get('type');
        var deviceId = params.get('device');
        if (type && deviceId && (type === 'laptop' || type === 'phone')) {
            return { type: type, deviceId: deviceId };
        }

        return null;
    }

    function clearDeepLinkFromUrl() {
        var params = new URLSearchParams(window.location.search);
        params.delete('type');
        params.delete('device');
        params.delete('t');
        params.delete('d');
        var query = params.toString();
        var nextUrl = window.location.pathname + (query ? '?' + query : '');
        window.history.replaceState({}, '', nextUrl);
    }

    function printDeviceLabel(device, type) {
        var id = String(device.id || device[keyField(type)] || '').trim();
        if (!id) {
            showMessage(modalMessage, t('moirai.error.print_failed'));
            return;
        }

        showMessage(modalMessage, '');
        fetchJson('print_label.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: id })
        }).then(function (data) {
            if (!data.url) {
                throw new Error(t('moirai.error.print_failed'));
            }
            window.location.href = data.url;
        }).catch(function (error) {
            showMessage(modalMessage, error.message || t('moirai.error.print_failed'));
        });
    }

    function renderHistoryBlock(device) {
        var html = '';
        if (device.uitgegeven_aan && device.uitgegeven_aan.email) {
            html += '<div class="history-block"><h3>' + escapeHtml(t('moirai.history.current')) + '</h3><p>' +
                escapeHtml((device.uitgegeven_aan.naam || device.uitgegeven_aan.email) + ' · ' +
                t('moirai.history.since', device.uitgegeven_sinds || '—')) + '</p></div>';
        }
        html += '<div class="history-block"><h3>' + escapeHtml(t('moirai.modal.history')) + '</h3>';
        if (device.historie_uitgegeven && device.historie_uitgegeven.length) {
            html += '<ul class="history-list">';
            device.historie_uitgegeven.slice().reverse().forEach(function (entry) {
                var user = entry.gebruiker || {};
                html += '<li>' + escapeHtml(t('moirai.history.entry',
                    user.naam || user.email || t('moirai.unknown_user'),
                    entry.van || '?',
                    entry.tot || '?')) + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p>' + escapeHtml(t('moirai.history.empty')) + '</p>';
        }
        html += '</div>';
        return html;
    }

    function renderDetails(device) {
        var fields = fieldDefinitions(state.tab);
        var html = '<dl class="detail-grid">';
        fields.forEach(function (field) {
            html += '<div class="detail-row"><dt>' + escapeHtml(t(field.labelKey)) + '</dt><dd>' +
                escapeHtml(device[field.name] || '—') + '</dd></div>';
        });
        html += '<div class="detail-row"><dt>' + escapeHtml(t('moirai.field.assigned_to')) + '</dt><dd>' +
            (device.uitgegeven_aan
                ? escapeHtml((device.uitgegeven_aan.naam || '') + ' (' + device.uitgegeven_aan.email + ')')
                : escapeHtml(t('moirai.badge.reserve'))) + '</dd></div></dl>';

        modalView.innerHTML = html;
        modalView.hidden = false;
        modalForm.hidden = true;
        modalTitle.textContent = deviceTitle(device);

        modalActions.innerHTML = '';
        var historyBtn = document.createElement('button');
        historyBtn.type = 'button';
        historyBtn.className = 'btn btn-secondary';
        historyBtn.textContent = t('moirai.btn.history');
        historyBtn.addEventListener('click', function () { openHistoryModal(device); });
        modalActions.appendChild(historyBtn);

        var notesBtn = document.createElement('button');
        notesBtn.type = 'button';
        notesBtn.className = 'btn btn-secondary';
        notesBtn.textContent = t('moirai.btn.notes');
        notesBtn.addEventListener('click', function () { openNotesModal(device); });
        modalActions.appendChild(notesBtn);

        var printBtn = document.createElement('button');
        printBtn.type = 'button';
        printBtn.className = 'btn btn-secondary';
        printBtn.textContent = t('moirai.btn.print_label');
        printBtn.addEventListener('click', function () { printDeviceLabel(device, state.tab); });
        modalActions.appendChild(printBtn);

        if (isAdmin) {
            var assignBtn = document.createElement('button');
            assignBtn.type = 'button';
            assignBtn.className = 'btn btn-primary';
            assignBtn.textContent = t('moirai.btn.assign');
            assignBtn.addEventListener('click', function () { openAssignModal(device); });
            modalActions.appendChild(assignBtn);

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-secondary';
            editBtn.textContent = t('moirai.btn.edit');
            editBtn.addEventListener('click', function () { openEditForm(device); });
            modalActions.appendChild(editBtn);
        }
    }

    function userOptions(selectedEmail) {
        var html = '<option value="">' + escapeHtml(t('moirai.select.reserve')) + '</option>';
        state.users.forEach(function (user) {
            var email = String(user.Email || '').toLowerCase();
            var selected = selectedEmail && email === String(selectedEmail).toLowerCase() ? ' selected' : '';
            html += '<option value="' + escapeHtml(email) + '"' + selected + '>' +
                escapeHtml((user.Naam || user.Email || t('moirai.unknown_user'))) + '</option>';
        });
        return html;
    }

    function refreshIconSvg() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
            '<path fill="currentColor" d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>' +
            '</svg>';
    }

    function renderAssignForm(device) {
        var selectedEmail = device.uitgegeven_aan ? device.uitgegeven_aan.email : '';
        document.getElementById('assign-history').innerHTML = renderHistoryBlock(device);
        document.getElementById('assign-form-wrap').innerHTML =
            '<form class="form-grid" id="assign-form"><div>' +
            '<label for="assign-user">' + escapeHtml(t('moirai.field.assigned_to')) + '</label>' +
            '<div class="assign-user-row">' +
            '<select id="assign-user" name="uitgegeven_email">' + userOptions(selectedEmail) + '</select>' +
            '<button type="button" class="btn btn-secondary btn-icon" id="refresh-users-btn" title="' +
            escapeHtml(t('moirai.btn.refresh_users')) + '" aria-label="' +
            escapeHtml(t('moirai.btn.refresh_users')) + '">' + refreshIconSvg() + '</button>' +
            '</div></div></form>';

        var refreshBtn = document.getElementById('refresh-users-btn');
        refreshBtn.addEventListener('click', function () {
            refreshUsersList(device, refreshBtn);
        });
    }

    function openHistoryModal(device) {
        document.getElementById('history-modal-body').innerHTML = renderHistoryBlock(device);
        document.getElementById('history-modal-title').textContent = t('moirai.modal.history') + ': ' + deviceTitle(device);
        openBackdrop('history-modal');
    }

    function openAssignModal(device) {
        showMessage(assignModalMessage, '');
        ensureUsers().then(function () {
            renderAssignForm(device);
            document.getElementById('assign-modal-title').textContent = t('moirai.modal.assign') + ': ' + deviceTitle(device);
            var actions = document.getElementById('assign-modal-actions');
            actions.innerHTML = '';
            var saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'btn btn-primary';
            saveBtn.textContent = t('moirai.btn.save');
            saveBtn.addEventListener('click', function () { assignDevice(device); });
            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary';
            cancelBtn.textContent = t('moirai.btn.cancel');
            cancelBtn.addEventListener('click', function () { closeBackdrop('assign-modal'); });
            actions.appendChild(saveBtn);
            actions.appendChild(cancelBtn);
            openBackdrop('assign-modal');
        }).catch(function (error) {
            showMessage(assignModalMessage, error.message);
            openBackdrop('assign-modal');
        });
    }

    function refreshUsersList(device, refreshBtn) {
        if (!isAdmin) {
            return;
        }

        showMessage(assignModalMessage, '');
        refreshBtn.classList.add('is-busy');
        refreshBtn.disabled = true;

        fetchJson(apiUrl({ action: 'users', refresh: '1' })).then(function (data) {
            state.users = data.users || [];
            var selected = '';
            var select = document.getElementById('assign-user');
            if (select) {
                selected = select.value;
            } else if (device.uitgegeven_aan && device.uitgegeven_aan.email) {
                selected = device.uitgegeven_aan.email;
            }
            renderAssignForm(device);
            var nextSelect = document.getElementById('assign-user');
            if (nextSelect && selected) {
                nextSelect.value = selected;
            }
        }).catch(function (error) {
            showMessage(assignModalMessage, error.message);
        }).then(function () {
            var btn = document.getElementById('refresh-users-btn');
            if (btn) {
                btn.classList.remove('is-busy');
                btn.disabled = false;
            }
        });
    }

    function openEditForm(device, isNew) {
        state.editing = true;
        var fields = fieldDefinitions(state.tab);
        var html = '<form class="form-grid" id="device-form">';
        html += '<input type="hidden" name="original_key" value="' + escapeHtml(device.id || '') + '">';

        fields.forEach(function (field) {
            var value = device[field.name] || '';
            if (isNew && field.type === 'date' && !value) {
                value = todayIso;
            }
            if (isNew && field.defaultNew && !value) {
                value = field.defaultNew;
            }
            html += '<div><label for="field-' + field.name + '">' + escapeHtml(t(field.labelKey)) + '</label>';
            if (field.type === 'date') {
                html += '<input type="date" id="field-' + field.name + '" name="' + field.name + '" value="' + escapeHtml(value) + '" max="' + escapeHtml(todayIso) + '"' +
                    (field.required ? ' required' : '') + '>';
            } else if (field.type === 'select') {
                html += '<select id="field-' + field.name + '" name="' + field.name + '"' +
                    (field.required ? ' required' : '') + '>';
                html += '<option value="">' + escapeHtml(t('moirai.select.choose')) + '</option>';
                (field.options || []).forEach(function (option) {
                    var selected = value === option ? ' selected' : '';
                    html += '<option value="' + escapeHtml(option) + '"' + selected + '>' +
                        escapeHtml(option) + '</option>';
                });
                html += '</select>';
            } else {
                html += '<input type="text" id="field-' + field.name + '" name="' + field.name + '" value="' + escapeHtml(value) + '"' +
                    (field.required ? ' required' : '') + '>';
            }
            html += '</div>';
        });
        html += '</form>';

        modalForm.innerHTML = html;
        modalForm.hidden = false;
        modalView.hidden = true;
        modalTitle.textContent = isNew ? t('moirai.modal.new') : t('moirai.modal.edit');

        modalActions.innerHTML = '';
        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-primary';
        saveBtn.textContent = t('moirai.btn.save');
        saveBtn.addEventListener('click', saveDevice);

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.textContent = t('moirai.btn.cancel');
        cancelBtn.addEventListener('click', function () {
            if (isNew) {
                closeModal();
            } else {
                renderDetails(device);
            }
        });

        modalActions.appendChild(saveBtn);
        modalActions.appendChild(cancelBtn);

        if (!isNew) {
            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger';
            deleteBtn.textContent = t('moirai.btn.delete');
            deleteBtn.addEventListener('click', function () {
                openDeleteConfirm(device);
            });
            modalActions.appendChild(deleteBtn);
        }
    }

    function openModal() {
        openBackdrop('device-modal');
    }

    function closeModal() {
        closeBackdrop('device-modal');
        state.currentDevice = null;
        state.editing = false;
        showMessage(modalMessage, '');
    }

    function openDevice(id, type) {
        var deviceType = type || state.tab;
        showMessage(modalMessage, '');
        return fetchJson(apiUrl({ action: 'get', type: deviceType, id: id }))
            .then(function (data) {
                state.currentDevice = data.device;
                renderDetails(data.device);
                openModal();
                return data.device;
            })
            .catch(function (error) {
                showMessage(modalMessage, error.message);
                openModal();
                throw error;
            });
    }

    function markQrVerifiedSilent(type, id, device) {
        if (!device || device.qr_geldig) {
            return Promise.resolve();
        }

        return fetchJson('devices_api.php?action=verify_qr', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: id })
        }).then(function (data) {
            var updated = data.device;
            state.devices.forEach(function (item, index) {
                var itemKey = item[keyField(type)] || item.id;
                if (String(itemKey) === String(id)) {
                    state.devices[index] = updated;
                }
            });
            if (state.tab === type) {
                renderList();
            }
        }).catch(function () {
            // Stil bijwerken; geen melding tonen.
        });
    }

    function ensureUsers() {
        if (!isAdmin || state.users.length) {
            return Promise.resolve();
        }
        return fetchJson(apiUrl({ action: 'users' })).then(function (data) {
            state.users = data.users || [];
        });
    }

    function saveDevice() {
        var form = document.getElementById('device-form');
        if (!form) {
            return;
        }

        var formData = new FormData(form);
        var wasNew = !String(formData.get('original_key') || '').trim();
        var payload = {
            type: state.tab,
            original_key: formData.get('original_key') || ''
        };

        fieldDefinitions(state.tab).forEach(function (field) {
            payload[field.name] = formData.get(field.name) || '';
        });

        fetchJson('devices_api.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (data) {
            return loadFilterOptions().then(loadDevices).then(function () {
                if (wasNew && data.device) {
                    state.editing = false;
                    state.currentDevice = data.device;
                    renderDetails(data.device);
                    openModal();
                    return;
                }

                closeModal();
            });
        }).catch(function (error) {
            showMessage(modalMessage, error.message);
        });
    }

    function assignDevice(device) {
        var form = document.getElementById('assign-form');
        if (!form) {
            return;
        }
        var selectedEmail = new FormData(form).get('uitgegeven_email');
        var payload = {
            type: state.tab,
            id: device.id,
            uitgegeven_aan: null
        };
        if (selectedEmail) {
            var user = state.users.find(function (item) {
                return String(item.Email || '').toLowerCase() === String(selectedEmail).toLowerCase();
            });
            if (user) {
                payload.uitgegeven_aan = {
                    id: user.Id,
                    naam: user.Naam,
                    email: user.Email
                };
            }
        }

        fetchJson('devices_api.php?action=assign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function () {
            closeBackdrop('assign-modal');
            closeModal();
            loadFilterOptions().then(loadDevices);
        }).catch(function (error) {
            showMessage(assignModalMessage, error.message);
        });
    }

    function openDeleteConfirm(device) {
        state.pendingDeleteDevice = device;
        var nameEl = document.getElementById('delete-confirm-device');
        if (nameEl) {
            nameEl.textContent = deviceTitle(device);
        }
        openBackdrop('delete-confirm-modal');
    }

    function closeDeleteConfirm() {
        state.pendingDeleteDevice = null;
        closeBackdrop('delete-confirm-modal');
    }

    function confirmDeleteDevice() {
        if (!state.pendingDeleteDevice) {
            return;
        }
        var device = state.pendingDeleteDevice;
        closeDeleteConfirm();
        deleteDevice(device);
    }

    function deleteDevice(device) {
        fetchJson('devices_api.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: state.tab,
                id: device.id
            })
        }).then(function () {
            closeModal();
            loadFilterOptions().then(loadDevices);
        }).catch(function (error) {
            showMessage(modalMessage, error.message);
        });
    }

    function activateTab(type) {
        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-tab') === type;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (state.tab === type) {
            return Promise.resolve();
        }

        listRequestId++;
        state.devices = [];
        deviceList.innerHTML = '';
        showListLoading();
        state.tab = type;
        resetAttrFilters();
        var tabLoadId = listRequestId;
        var tabName = type;
        return loadFilterOptions().then(function () {
            if (tabLoadId !== listRequestId || tabName !== state.tab) {
                return;
            }
            return loadDevices();
        });
    }

    function handleDeepLinkFromUrl() {
        migrateHashDeepLinkToQuery();
        var link = parseDeepLinkFromUrl();
        if (!link) {
            return Promise.resolve();
        }

        return activateTab(link.type).then(function () {
            return openDevice(link.deviceId, link.type);
        }).then(function (device) {
            markQrVerifiedSilent(link.type, link.deviceId, device);
            clearDeepLinkFromUrl();
        }).catch(function () {
            clearDeepLinkFromUrl();
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-tab'));
        });
    });

    attrFiltersEl.addEventListener('change', function (event) {
        var select = event.target.closest('select[data-filter]');
        if (!select) {
            return;
        }
        var name = select.getAttribute('data-filter');
        state.attrFilters[name] = select.value;
        loadFilterOptions().then(loadDevices);
    });

    searchInput.addEventListener('input', function () {
        state.query = searchInput.value.trim();
        loadDevices();
    });

    statusFilters.addEventListener('click', function (event) {
        var chip = event.target.closest('.chip');
        if (!chip) {
            return;
        }
        statusFilters.querySelectorAll('.chip').forEach(function (item) {
            item.classList.remove('is-active');
        });
        chip.classList.add('is-active');
        state.status = chip.getAttribute('data-status');
        loadDevices();
    });

    deviceList.addEventListener('click', function (event) {
        var item = event.target.closest('.device-item');
        if (!item) {
            return;
        }
        openDevice(item.getAttribute('data-id'));
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modalId = btn.getAttribute('data-close-modal');
            if (modalId === 'device-modal') {
                closeModal();
                return;
            }
            closeBackdrop(modalId);
        });
    });

    ['assign-modal', 'history-modal', 'delete-confirm-modal', 'notes-delete-confirm-modal'].forEach(function (id) {
        var backdrop = document.getElementById(id);
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) {
                if (id === 'delete-confirm-modal') {
                    closeDeleteConfirm();
                    return;
                }
                if (id === 'notes-delete-confirm-modal') {
                    closeNotesDeleteConfirm();
                    return;
                }
                closeBackdrop(id);
            }
        });
    });

    document.getElementById('delete-confirm-yes').addEventListener('click', confirmDeleteDevice);
    document.getElementById('delete-confirm-no').addEventListener('click', closeDeleteConfirm);

    var closeNotesDeleteConfirm = function () {
        state.pendingDeleteNoteId = null;
        closeBackdrop('notes-delete-confirm-modal');
    };
    var confirmDeleteNote = function () {};
    var openNotesModal = function () {};

    (function initNotesModal() {
        var modal = document.getElementById('notes-modal');
        if (!modal) {
            return;
        }
        var closeButton = modal.querySelector('[data-role="notes-close"]');
        var messagesEl = modal.querySelector('[data-role="notes-messages"]');
        var emptyEl = modal.querySelector('[data-role="notes-empty"]');
        var inputEl = modal.querySelector('[data-role="notes-input"]');
        var feedbackEl = modal.querySelector('[data-role="notes-feedback"]');
        var composeLabel = modal.querySelector('[data-role="notes-compose-label"]');
        var composeToolbar = modal.querySelector('[data-role="notes-compose-toolbar"]');
        var cancelEditBtn = modal.querySelector('[data-role="notes-cancel-edit"]');
        var deleteYesBtn = document.getElementById('notes-delete-confirm-yes');
        var deleteNoBtn = document.getElementById('notes-delete-confirm-no');
        var pollTimer = null;
        var sendInFlight = false;
        var loadInFlight = false;
        var messagesById = {};

        function deviceKey(device) {
            if (!device) {
                return '';
            }
            return String(device[keyField(state.tab)] || device.id || '').trim();
        }

        function escapeAttr(value) {
            return escapeHtml(value).replace(/'/g, '&#039;');
        }

        function hashTextForColor(value) {
            var hash = 0;
            var text = String(value || '');
            for (var i = 0; i < text.length; i += 1) {
                hash = text.charCodeAt(i) + ((hash << 5) - hash);
                hash = hash & 0x7fffffff;
            }
            return hash;
        }

        function colorFromText(text) {
            var normalized = String(text || '').toLowerCase().trim();
            if (normalized === '') {
                return {
                    border: '#cbd5e1',
                    chipBackground: '#e2e8f0',
                    cardBackground: '#ffffff',
                    chipTextColor: '#334155'
                };
            }
            var hash = hashTextForColor(normalized);
            var hue = Math.abs(hash) % 360;
            var saturation = 72 + (Math.abs(hash >> 8) % 14);
            var lightness = 56 + (Math.abs(hash >> 16) % 10);
            var chipTextColor = lightness >= 58 ? '#1e293b' : '#ffffff';
            return {
                border: 'hsl(' + hue + ', ' + saturation + '%, ' + Math.max(lightness - 6, 48) + '%)',
                chipBackground: 'hsl(' + hue + ', ' + saturation + '%, ' + lightness + '%)',
                cardBackground: 'hsl(' + hue + ', ' + Math.min(saturation, 48) + '%, 96%)',
                chipTextColor: chipTextColor
            };
        }

        function trimLinkTrailingPunctuation(value) {
            return String(value || '').replace(/[.,;:!?)\]]+$/, '');
        }

        function linkifyEscapedHtml(escaped) {
            var pattern = /\b((?:https?:\/\/|www\.)[^\s<]+|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/gi;
            return escaped.replace(pattern, function (match) {
                if (match.indexOf('@') >= 0) {
                    return '<a class="ponos-text-link" href="mailto:' + escapeAttr(match) + '">' + match + '</a>';
                }
                var trimmed = trimLinkTrailingPunctuation(match);
                var suffix = match.slice(trimmed.length);
                var href = trimmed;
                if (/^www\./i.test(href)) {
                    href = 'https://' + href;
                }
                if (!/^https?:\/\//i.test(href)) {
                    return match;
                }
                return '<a class="ponos-text-link" href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer">'
                    + trimmed + '</a>' + suffix;
            });
        }

        function formatDescriptionHtml(value) {
            return linkifyEscapedHtml(escapeHtml(value).replace(/\r\n|\r|\n/g, '<br/>'));
        }

        function userAvatarUrl(email) {
            var normalized = String(email || '').toLowerCase().trim();
            if (normalized === '') {
                return '';
            }
            return 'user_avatar.php?email=' + encodeURIComponent(normalized);
        }

        function renderUserAvatarHtml(email, colors) {
            var normalized = String(email || '').toLowerCase().trim();
            if (normalized === '') {
                return '';
            }
            var url = userAvatarUrl(normalized);
            if (url === '') {
                return '';
            }
            return '<img class="ponos-user-avatar" src="' + escapeHtml(url) + '" width="30" height="30" alt="" style="border-color:'
                + escapeHtml(colors.border) + '">';
        }

        function renderMessageHtml(message) {
            var email = String(message.user_email || '').toLowerCase().trim();
            var authorName = String(message.user_label || '').trim() || email || t('moirai.unknown_user');
            var colors = (message.colors && typeof message.colors === 'object')
                ? message.colors
                : colorFromText(email);
            var timeLabel = String(message.created_at_label || '');
            if (message.updated_at) {
                timeLabel += t('moirai.notes.edited_suffix');
            }
            var html = '';
            html += '<div class="ponos-message-row" data-message-id="' + escapeAttr(String(message.id || '')) + '">';
            html += '<div class="ponos-message-avatar-wrap">' + renderUserAvatarHtml(email, colors) + '</div>';
            html += '<article class="ponos-message" style="border-color:' + escapeHtml(colors.border)
                + ';background:' + escapeHtml(colors.cardBackground) + '">';
            html += '<div class="ponos-message-meta"><span class="ponos-message-email" style="background:'
                + escapeHtml(colors.chipBackground) + ';color:' + escapeHtml(colors.chipTextColor) + '">'
                + escapeHtml(authorName) + '</span><span>' + escapeHtml(timeLabel) + '</span>';
            if (message.can_edit || message.can_delete) {
                html += '<span class="ponos-message-actions">';
                if (message.can_edit) {
                    html += '<button type="button" class="ponos-message-action" data-note-edit="'
                        + escapeAttr(String(message.id || '')) + '">' + escapeHtml(t('moirai.btn.edit')) + '</button>';
                }
                if (message.can_delete) {
                    html += '<button type="button" class="ponos-message-action is-danger" data-note-delete="'
                        + escapeAttr(String(message.id || '')) + '">' + escapeHtml(t('moirai.btn.delete')) + '</button>';
                }
                html += '</span>';
            }
            html += '</div>';
            html += '<div>' + formatDescriptionHtml(message.message_text || '') + '</div>';
            html += '</article></div>';
            return html;
        }

        function scrollMessagesToEnd() {
            if (!messagesEl) {
                return;
            }
            window.requestAnimationFrame(function () {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            });
        }

        function resizeInput() {
            if (!inputEl) {
                return;
            }
            var maxHeight = Math.min(window.innerHeight * 0.32, 280);
            inputEl.style.height = 'auto';
            var nextHeight = Math.min(inputEl.scrollHeight, maxHeight);
            inputEl.style.height = nextHeight + 'px';
            inputEl.style.overflowY = inputEl.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }

        function showFeedback(message, isError) {
            if (!feedbackEl) {
                return;
            }
            feedbackEl.textContent = message || '';
            feedbackEl.hidden = !message;
            feedbackEl.classList.toggle('is-error', !!isError);
        }

        function setComposeMode(editingId) {
            state.notesEditingId = editingId || null;
            if (composeLabel) {
                composeLabel.textContent = state.notesEditingId
                    ? t('moirai.notes.edit_label')
                    : t('moirai.notes.message_label');
            }
            if (composeToolbar) {
                composeToolbar.hidden = !state.notesEditingId;
            }
        }

        function cancelEdit() {
            setComposeMode(null);
            if (inputEl) {
                inputEl.value = '';
                resizeInput();
            }
            showFeedback('');
        }

        function setMessages(messages) {
            if (!messagesEl) {
                return;
            }
            var list = Array.isArray(messages) ? messages : [];
            messagesById = {};
            messagesEl.innerHTML = '';
            list.forEach(function (message) {
                var id = String(message.id || '');
                if (id === '') {
                    return;
                }
                messagesById[id] = message;
                messagesEl.insertAdjacentHTML('beforeend', renderMessageHtml(message));
            });
            if (emptyEl) {
                emptyEl.hidden = Object.keys(messagesById).length > 0;
            }
            scrollMessagesToEnd();
        }

        function loadNotes() {
            var device = state.notesDevice;
            var id = deviceKey(device);
            if (!device || id === '' || loadInFlight) {
                return Promise.resolve();
            }
            loadInFlight = true;
            return fetchJson(apiUrl({ action: 'notes_list', type: state.tab, id: id })).then(function (data) {
                loadInFlight = false;
                showFeedback('');
                setMessages(data.messages || []);
            }).catch(function () {
                loadInFlight = false;
                showFeedback(t('moirai.notes.load_failed'), true);
            });
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function startPolling() {
            stopPolling();
            pollTimer = setInterval(function () {
                if (!modal.hidden) {
                    loadNotes();
                }
            }, 12000);
        }

        function closeNotesModal() {
            modal.hidden = true;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            stopPolling();
            cancelEdit();
            state.notesDevice = null;
            closeNotesDeleteConfirm();
        }

        openNotesModal = function (device) {
            state.notesDevice = device;
            modal.hidden = false;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            showFeedback('');
            cancelEdit();
            loadNotes().then(function () {
                resizeInput();
                if (inputEl) {
                    inputEl.focus();
                }
            });
            startPolling();
        };

        confirmDeleteNote = function () {
            var noteId = state.pendingDeleteNoteId;
            if (!noteId) {
                closeNotesDeleteConfirm();
                return;
            }
            fetchJson(apiUrl({ action: 'notes_delete' }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: Number(noteId) })
            }).then(function () {
                closeNotesDeleteConfirm();
                if (state.notesEditingId && String(state.notesEditingId) === String(noteId)) {
                    cancelEdit();
                }
                return loadNotes();
            }).catch(function () {
                showFeedback(t('moirai.notes.delete_failed'), true);
                closeNotesDeleteConfirm();
            });
        };

        function beginEdit(noteId) {
            var message = messagesById[String(noteId)];
            if (!message || !inputEl) {
                return;
            }
            setComposeMode(String(noteId));
            inputEl.value = String(message.message_text || '');
            resizeInput();
            inputEl.focus();
        }

        function askDelete(noteId) {
            state.pendingDeleteNoteId = String(noteId);
            openBackdrop('notes-delete-confirm-modal');
        }

        function sendOrSave() {
            if (!inputEl || sendInFlight || !state.notesDevice) {
                return;
            }
            var text = String(inputEl.value || '').trim();
            if (text === '') {
                return;
            }
            sendInFlight = true;
            showFeedback('');
            var editingId = state.notesEditingId;
            var request;
            if (editingId) {
                request = fetchJson(apiUrl({ action: 'notes_edit' }), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        note_id: Number(editingId),
                        message_text: text
                    })
                });
            } else {
                request = fetchJson(apiUrl({ action: 'notes_add' }), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: state.tab,
                        id: deviceKey(state.notesDevice),
                        message_text: text
                    })
                });
            }
            request.then(function () {
                sendInFlight = false;
                cancelEdit();
                return loadNotes();
            }).catch(function () {
                sendInFlight = false;
                showFeedback(editingId ? t('moirai.notes.save_failed') : t('moirai.notes.send_failed'), true);
            });
        }

        if (closeButton) {
            closeButton.addEventListener('click', closeNotesModal);
        }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeNotesModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                if (document.getElementById('notes-delete-confirm-modal').classList.contains('is-open')) {
                    closeNotesDeleteConfirm();
                    return;
                }
                closeNotesModal();
            }
        });
        if (messagesEl) {
            messagesEl.addEventListener('click', function (event) {
                var editBtn = event.target.closest('[data-note-edit]');
                if (editBtn) {
                    beginEdit(editBtn.getAttribute('data-note-edit'));
                    return;
                }
                var deleteBtn = event.target.closest('[data-note-delete]');
                if (deleteBtn) {
                    askDelete(deleteBtn.getAttribute('data-note-delete'));
                }
            });
        }
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', cancelEdit);
        }
        if (deleteYesBtn) {
            deleteYesBtn.addEventListener('click', function () { confirmDeleteNote(); });
        }
        if (deleteNoBtn) {
            deleteNoBtn.addEventListener('click', closeNotesDeleteConfirm);
        }
        if (inputEl) {
            inputEl.addEventListener('input', resizeInput);
            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendOrSave();
                }
            });
            resizeInput();
        }
    })();

    if (isAdmin) {
        document.getElementById('add-device-btn').addEventListener('click', function () {
            var emptyDevice = { id: '', historie_uitgegeven: [], uitgegeven_aan: null, aanschafdatum: todayIso };
            openEditForm(emptyDevice, true);
            openModal();
        });
    }

    window.addEventListener('hashchange', handleDeepLinkFromUrl);

    loadFilterOptions().then(loadDevices).then(handleDeepLinkFromUrl);
})();
</script>
<?php renderMoiraiLanguageRailScript(); ?>
</body>
</html>
