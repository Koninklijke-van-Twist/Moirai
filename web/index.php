<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/moirai_data.php';

$isAdmin = moirai_is_admin();
$userEmail = (string) ($_SESSION['user']['email'] ?? '');
$userName = (string) ($_SESSION['user']['name'] ?? $userEmail);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moirai – Apparatenbeheer</title>
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

        .device-list {
            display: grid;
            gap: 10px;
        }

        .device-item {
            width: 100%;
            text-align: left;
            border: 1px solid var(--kvt-line);
            background: #fff;
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
        }

        .device-item:hover {
            border-color: var(--kvt-main-blue);
            background: #f8fbff;
        }

        .device-name {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 4px;
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

        .loader {
            text-align: center;
            color: var(--kvt-muted);
            padding: 24px;
        }
    </style>
</head>
<body>
<div class="page">
    <header class="hero">
        <div class="hero-top">
            <div>
                <h1>Moirai</h1>
                <p class="hero-sub">Apparatenoverzicht voor <?= moirai_h($userName) ?><?= $isAdmin ? ' · admin' : '' ?></p>
            </div>
            <?php if ($isAdmin): ?>
            <div class="hero-actions">
                <a class="btn btn-secondary" href="download_enroll.php">Linux Enroll Script</a>
                <button type="button" class="btn btn-primary" id="add-device-btn">Nieuw apparaat</button>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="panel">
        <div class="tabs" role="tablist" aria-label="Apparaattype">
            <button type="button" class="tab is-active" data-tab="laptop" role="tab" aria-selected="true">Laptops</button>
            <button type="button" class="tab" data-tab="phone" role="tab" aria-selected="false">Telefoons</button>
        </div>

        <div class="attr-filters" id="attr-filters"></div>

        <div class="filters">
            <div class="filters-row">
                <div>
                    <label for="search-input">Zoeken</label>
                    <input type="search" id="search-input" placeholder="Naam, model, serienummer, IMEI, gebruiker…">
                </div>
                <div>
                    <label>Status</label>
                    <div class="status-filters" id="status-filters">
                        <button type="button" class="chip is-active" data-status="all">Alles</button>
                        <button type="button" class="chip" data-status="assigned">Uitgegeven</button>
                        <button type="button" class="chip" data-status="reserve">Reserve</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="message" id="page-message"></div>
        <div class="loader" id="list-loader">Apparaten laden…</div>
        <div class="device-list" id="device-list" hidden></div>
        <div class="empty-state" id="empty-state" hidden>Geen apparaten gevonden.</div>
    </main>
</div>

<div class="modal-backdrop" id="device-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
            <h2 id="modal-title">Apparaat</h2>
            <button type="button" class="close-btn" id="modal-close" aria-label="Sluiten">&times;</button>
        </div>
        <div class="message" id="modal-message"></div>
        <div id="modal-view"></div>
        <div id="modal-form" hidden></div>
        <div class="modal-actions" id="modal-actions"></div>
    </div>
</div>

<script>
(function () {
    var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    var state = {
        tab: 'laptop',
        query: '',
        status: 'all',
        attrFilters: {},
        filterOptions: {},
        devices: [],
        users: [],
        currentDevice: null,
        editing: false
    };

    var filterConfig = {
        laptop: [
            { name: 'os', label: 'OS' },
            { name: 'os_versie', label: 'OS versie' },
            { name: 'model', label: 'Modelnaam' },
            { name: 'ram', label: 'RAM' }
        ],
        phone: [
            { name: 'os', label: 'OS' },
            { name: 'os_versie', label: 'OS versie' },
            { name: 'model', label: 'Modelnaam' },
            { name: 'schermformaat', label: 'Schermformaat' }
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
                    throw new Error(data.error || 'Verzoek mislukt.');
                }
                return data;
            });
        });
    }

    function loadDevices() {
        listLoader.hidden = false;
        deviceList.hidden = true;
        emptyState.hidden = true;
        showMessage(pageMessage, '');

        var params = {
            action: 'list',
            type: state.tab,
            q: state.query,
            status: state.status
        };

        filterConfig[state.tab].forEach(function (filter) {
            if (state.attrFilters[filter.name]) {
                params[filter.name] = state.attrFilters[filter.name];
            }
        });

        fetchJson(apiUrl(params)).then(function (data) {
            state.devices = data.devices || [];
            renderList();
        }).catch(function (error) {
            showMessage(pageMessage, error.message);
            listLoader.hidden = true;
        });
    }

    function renderAttrFilters() {
        var config = filterConfig[state.tab];
        var options = state.filterOptions || {};

        attrFiltersEl.innerHTML = config.map(function (filter) {
            var selected = state.attrFilters[filter.name] || '';
            var html = '<div><label for="filter-' + filter.name + '">' + escapeHtml(filter.label) + '</label>';
            html += '<select id="filter-' + filter.name + '" data-filter="' + filter.name + '">';
            html += '<option value="">— Alles —</option>';
            (options[filter.name] || []).forEach(function (value) {
                var isSelected = selected === value ? ' selected' : '';
                html += '<option value="' + escapeHtml(value) + '"' + isSelected + '>' + escapeHtml(value) + '</option>';
            });
            html += '</select></div>';
            return html;
        }).join('');
    }

    function loadFilterOptions() {
        var params = { action: 'filters', type: state.tab };

        filterConfig[state.tab].forEach(function (filter) {
            if (state.attrFilters[filter.name]) {
                params[filter.name] = state.attrFilters[filter.name];
            }
        });

        return fetchJson(apiUrl(params)).then(function (data) {
            state.filterOptions = data.filters || {};
            filterConfig[state.tab].forEach(function (filter) {
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

    function renderList() {
        listLoader.hidden = true;

        if (!state.devices.length) {
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
                ? 'Uitgegeven aan ' + escapeHtml(device.uitgegeven_aan.naam || device.uitgegeven_aan.email)
                : 'Reserve';
            var key = device[keyField(state.tab)] || device.id;

            return '<button type="button" class="device-item" data-id="' + escapeHtml(key) + '">' +
                '<p class="device-name">' + escapeHtml(device.naam || 'Naamloos') + '</p>' +
                '<p class="device-meta">' + escapeHtml(device.model || '') +
                (key ? ' · ' + escapeHtml(key) : '') + '</p>' +
                '<span class="badge ' + badgeClass + '">' + badgeText + '</span>' +
                '</button>';
        }).join('');
    }

    function fieldDefinitions(type) {
        if (type === 'laptop') {
            return [
                { name: 'naam', label: 'Apparaatnaam', required: true },
                { name: 'model', label: 'Modelnaam' },
                { name: 'serienummer', label: 'Serienummer', required: true, key: true },
                { name: 'ram', label: 'RAM hoeveelheid' },
                { name: 'cpu', label: 'CPU modelnummer' },
                { name: 'aanschafdatum', label: 'Aanschafdatum', type: 'date' },
                {
                    name: 'os',
                    label: 'OS',
                    type: 'select',
                    options: ['Windows', 'OSX', 'Linux']
                },
                { name: 'os_versie', label: 'OS versie' }
            ];
        }

        return [
            { name: 'naam', label: 'Apparaatnaam', required: true },
            { name: 'model', label: 'Modelnaam' },
            { name: 'imei', label: 'IMEI', required: true, key: true },
            { name: 'schermformaat', label: 'Schermformaat' },
            {
                name: 'os',
                label: 'OS',
                type: 'select',
                options: ['Android', 'iPhone']
            },
            { name: 'os_versie', label: 'OS versie' },
            { name: 'aanschafdatum', label: 'Aanschafdatum', type: 'date' }
        ];
    }

    function renderDetails(device) {
        var fields = fieldDefinitions(state.tab);
        var html = '<dl class="detail-grid">';
        fields.forEach(function (field) {
            html += '<div class="detail-row"><dt>' + escapeHtml(field.label) + '</dt><dd>' +
                escapeHtml(device[field.name] || '—') + '</dd></div>';
        });

        html += '<div class="detail-row"><dt>Uitgegeven aan</dt><dd>' +
            (device.uitgegeven_aan
                ? escapeHtml((device.uitgegeven_aan.naam || '') + ' (' + device.uitgegeven_aan.email + ')')
                : 'Reserve') + '</dd></div>';

        html += '<div class="detail-row"><dt>Historie uitgegeven</dt><dd>';
        if (device.historie_uitgegeven && device.historie_uitgegeven.length) {
            html += '<ul class="history-list">';
            device.historie_uitgegeven.slice().reverse().forEach(function (entry) {
                var user = entry.gebruiker || {};
                html += '<li>' + escapeHtml(user.naam || user.email || 'Onbekend') +
                    ' · ' + escapeHtml(entry.van || '?') + ' t/m ' + escapeHtml(entry.tot || '?') + '</li>';
            });
            html += '</ul>';
        } else {
            html += '—';
        }
        html += '</dd></div></dl>';

        modalView.innerHTML = html;
        modalView.hidden = false;
        modalForm.hidden = true;
        modalTitle.textContent = device.naam || 'Apparaat';

        modalActions.innerHTML = '';
        if (isAdmin) {
            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-primary';
            editBtn.textContent = 'Bewerken';
            editBtn.addEventListener('click', function () {
                openEditForm(device);
            });
            modalActions.appendChild(editBtn);
        }
    }

    function userOptions(selectedEmail) {
        var html = '<option value="">— Reserve —</option>';
        state.users.forEach(function (user) {
            var email = String(user.Email || '').toLowerCase();
            var selected = selectedEmail && email === String(selectedEmail).toLowerCase() ? ' selected' : '';
            html += '<option value="' + escapeHtml(email) + '"' + selected + '>' +
                escapeHtml((user.Naam || user.Email || 'Onbekend')) + '</option>';
        });
        return html;
    }

    function openEditForm(device, isNew) {
        state.editing = true;
        var fields = fieldDefinitions(state.tab);
        var html = '<form class="form-grid" id="device-form">';
        html += '<input type="hidden" name="original_key" value="' + escapeHtml(device.id || '') + '">';

        fields.forEach(function (field) {
            var value = device[field.name] || '';
            html += '<div><label for="field-' + field.name + '">' + escapeHtml(field.label) + '</label>';
            if (field.type === 'date') {
                html += '<input type="date" id="field-' + field.name + '" name="' + field.name + '" value="' + escapeHtml(value) + '"' +
                    (field.required ? ' required' : '') + '>';
            } else if (field.type === 'select') {
                html += '<select id="field-' + field.name + '" name="' + field.name + '"' +
                    (field.required ? ' required' : '') + '>';
                html += '<option value="">— Kies —</option>';
                (field.options || []).forEach(function (option) {
                    var selected = value === option ? ' selected' : '';
                    html += '<option value="' + escapeHtml(option) + '"' + selected + '>' +
                        escapeHtml(option) + '</option>';
                });
                html += '</select>';
            } else {
                html += '<input type="text" id="field-' + field.name + '" name="' + field.name + '" value="' + escapeHtml(value) + '"' +
                    (field.required ? ' required' : '') + (field.key && !isNew ? '' : '') + '>';
            }
            html += '</div>';
        });

        html += '<div><label for="field-uitgegeven">Uitgegeven aan</label>' +
            '<select id="field-uitgegeven" name="uitgegeven_email">' +
            userOptions(device.uitgegeven_aan ? device.uitgegeven_aan.email : '') +
            '</select></div></form>';

        modalForm.innerHTML = html;
        modalForm.hidden = false;
        modalView.hidden = true;
        modalTitle.textContent = isNew ? 'Nieuw apparaat' : 'Apparaat bewerken';

        modalActions.innerHTML = '';
        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-primary';
        saveBtn.textContent = 'Opslaan';
        saveBtn.addEventListener('click', saveDevice);

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.textContent = 'Annuleren';
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
            deleteBtn.textContent = 'Verwijderen';
            deleteBtn.addEventListener('click', function () {
                if (!confirm('Weet je zeker dat je dit apparaat wilt verwijderen?')) {
                    return;
                }
                deleteDevice(device);
            });
            modalActions.appendChild(deleteBtn);
        }
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        state.currentDevice = null;
        state.editing = false;
        showMessage(modalMessage, '');
    }

    function openDevice(id) {
        showMessage(modalMessage, '');
        fetchJson(apiUrl({ action: 'get', type: state.tab, id: id }))
            .then(function (data) {
                state.currentDevice = data.device;
                renderDetails(data.device);
                openModal();
            })
            .catch(function (error) {
                showMessage(pageMessage, error.message);
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
        var payload = {
            type: state.tab,
            original_key: formData.get('original_key') || ''
        };

        fieldDefinitions(state.tab).forEach(function (field) {
            payload[field.name] = formData.get(field.name) || '';
        });

        var selectedEmail = formData.get('uitgegeven_email');
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
        } else {
            payload.uitgegeven_aan = null;
        }

        fetchJson('devices_api.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function () {
            closeModal();
            loadFilterOptions().then(loadDevices);
        }).catch(function (error) {
            showMessage(modalMessage, error.message);
        });
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

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (item) {
                item.classList.remove('is-active');
                item.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('is-active');
            tab.setAttribute('aria-selected', 'true');
            state.tab = tab.getAttribute('data-tab');
            resetAttrFilters();
            loadFilterOptions().then(loadDevices);
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

    document.getElementById('modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    if (isAdmin) {
        document.getElementById('add-device-btn').addEventListener('click', function () {
            ensureUsers().then(function () {
                var emptyDevice = { id: '', historie_uitgegeven: [], uitgegeven_aan: null };
                openEditForm(emptyDevice, true);
                openModal();
            }).catch(function (error) {
                showMessage(pageMessage, error.message);
            });
        });
    }

    if (isAdmin) {
        ensureUsers().catch(function (error) {
            showMessage(pageMessage, error.message);
        });
    }

    loadFilterOptions().then(loadDevices);
})();
</script>
</body>
</html>
