/* Telefoonbudget-tabblad (alleen ICT-admins). Server: budget_api.php. Bedragen in centen. */
(function () {
    'use strict';
    var panel = document.getElementById('budget-panel');
    if (!panel) {
        return;
    }
    var csrf = panel.getAttribute('data-csrf') || '';
    var i18n = JSON.parse(panel.getAttribute('data-i18n') || '{}');
    var todayIso = panel.getAttribute('data-today') || new Date().toISOString().slice(0, 10);
    var state = { q: '', filter: 'all', page: 1, pages: 1, person: null, purchase: null, confirmAction: null, importData: null, importQueue: [], importStarts: {}, previewTimer: null, loaded: false };

    function t(key) {
        var args = Array.prototype.slice.call(arguments, 1);
        var str = i18n[key] || key;
        args.forEach(function (arg) { str = str.replace('%s', arg); });
        return str;
    }
    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function money(cents) {
        if (cents === null || cents === undefined) { return '—'; }
        var neg = cents < 0; var abs = Math.abs(cents);
        var euros = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (neg ? '-' : '') + '€ ' + euros + ',' + String(abs % 100).padStart(2, '0');
    }
    function moneyInput(cents) {
        return String(Math.floor(cents / 100)) + ',' + String(cents % 100).padStart(2, '0');
    }
    function date(iso) {
        if (!iso) { return '—'; }
        var p = String(iso).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : iso;
    }
    function open(id) { var el = document.getElementById(id); el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false'); }
    function close(id) { var el = document.getElementById(id); el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); msg(id, ''); }
    function msg(id, text) {
        var root = document.getElementById(id);
        var el = root && (root.querySelector('[data-budget-message]') || root);
        if (!el || !el.classList.contains('message')) { return; }
        el.textContent = text || '';
        el.classList.toggle('is-visible', !!text);
    }
    function api(action, params) {
        var q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch('budget_api.php?' + q.toString(), { credentials: 'same-origin' }).then(handle);
    }
    function post(action, body) {
        return fetch('budget_api.php?action=' + encodeURIComponent(action), {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body || {})
        }).then(handle);
    }
    function handle(response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
            if (!response.ok || !data.ok) { throw new Error(data.error || t('moirai.error.request_failed')); }
            return data;
        });
    }

    /* ------------------------------------------------------------ lijst -- */
    function loadPeople() {
        msg('budget-page-message', '');
        return api('people', { q: state.q, page: state.page, filter: state.filter }).then(function (data) {
            state.page = data.page; state.pages = data.pages;
            var body = document.getElementById('budget-people');
            body.innerHTML = data.items.map(function (p) {
                return '<tr class="budget-person-row" data-email="' + esc(p.email) + '" data-naam="' + esc(p.naam) + '">' +
                    '<td><strong>' + esc(p.naam || p.email) + '</strong><br><span class="budget-muted">' + esc(p.email) +
                    (p.in_directory ? '' : ' · ' + esc(t('budget.not_in_directory'))) + '</span></td>' +
                    '<td>' + (p.indiensttreding ? esc(date(p.indiensttreding)) : '<span class="budget-muted">' + esc(t('budget.no_start')) + '</span>') + '</td>' +
                    '<td class="num">' + esc(money(p.budget_cents)) + (p.onbevestigd ? ' <span class="budget-status budget-status-onbevestigd">' + p.onbevestigd + '× ' + esc(t('budget.status.onbevestigd')) + '</span>' : '') + '</td>' +
                    '<td>' + esc(date(p.laatste_aankoop)) + '</td></tr>';
            }).join('');
            document.getElementById('budget-page-info').textContent = t('budget.page', data.page, data.pages, data.total);
            document.getElementById('budget-prev').disabled = data.page <= 1;
            document.getElementById('budget-next').disabled = data.page >= data.pages;
        }).catch(function (e) { msg('budget-page-message', e.message); });
    }

    /* ---------------------------------------------------- persoonsmodal -- */
    function openPerson(email, naam) {
        return api('person', { email: email, naam: naam || '' }).then(function (data) {
            renderPerson(data.person);
            open('budget-person-modal');
        }).catch(function (e) { msg('budget-page-message', e.message); });
    }
    function renderPerson(p) {
        state.person = p;
        document.getElementById('budget-person-modal-title').textContent = (p.naam || p.email) + ' — ' + t('budget.modal.person');
        var html = '';
        if (p.indiensttreding) {
            html += '<div class="budget-top"><div><div class="budget-muted">' + esc(t('budget.current')) + '</div>' +
                '<div class="budget-amount">' + esc(money(p.budget_cents)) + '</div></div>';
            if (p.telefoon_waarde) {
                html += '<div class="budget-value"><div class="budget-muted">' + esc(t('budget.phone_value')) + '</div><strong>' +
                    esc(money(p.telefoon_waarde.value_cents)) + '</strong><div class="budget-muted">' +
                    esc(t('budget.phone_value_hint', money(p.settings.depreciation_cents), p.telefoon_waarde.months)) + '</div></div>';
            }
            html += '<button type="button" class="btn btn-primary budget-plus" id="budget-add" title="' + esc(t('budget.add')) + '" aria-label="' + esc(t('budget.add')) + '">+</button></div>';
        } else {
            html += '<p class="budget-muted">' + esc(t('budget.start_required')) + '</p>';
        }
        html += '<form class="budget-start-form" id="budget-person-start"><label>' + esc(t('budget.col.start')) +
            '<br><input type="date" name="indiensttreding" required value="' + esc(p.indiensttreding || '') + '"></label>' +
            '<button type="submit" class="btn btn-secondary">' + esc(t('budget.save_start')) + '</button>' +
            '<span class="budget-muted">' + esc(p.email) + '</span></form>';
        if (!p.purchases.length) {
            html += '<p class="budget-muted">' + esc(t('budget.no_purchases')) + '</p>';
        } else {
            html += '<div class="budget-table-wrap"><table class="budget-table"><thead><tr><th>' + esc(t('budget.col.id')) + '</th><th>' + esc(t('budget.col.date')) +
                '</th><th>' + esc(t('budget.field.phone')) + '</th><th class="num">' + esc(t('budget.col.price')) + '</th><th class="num">' + esc(t('budget.col.own')) +
                '</th><th>' + esc(t('budget.col.status')) + '</th><th>' + esc(t('budget.col.linked_phone')) + '</th><th></th></tr></thead><tbody>';
            p.purchases.slice().sort(function (a, b) { return a.datum < b.datum ? 1 : a.datum > b.datum ? -1 : b.id - a.id; }).forEach(function (row) {
                var unconfirmed = row.status === 'onbevestigd';
                html += '<tr class="' + (unconfirmed ? 'budget-unconfirmed' : '') + '"><td>#' + row.id + (row.geimporteerd ? '<br><span class="budget-muted">' + esc(t('budget.imported')) + '</span>' : '') + '</td>' +
                    '<td>' + esc(date(row.datum)) + '</td>' +
                    '<td>' + esc(row.telefoon || '—') + (row.notitie ? '<div class="budget-note">' + esc(row.notitie) + '</div>' : '') + '</td>' +
                    '<td class="num">' + esc(money(row.prijs_cents)) + '</td>' +
                    '<td class="num">' + esc(money(row.eigen_bijdrage_cents)) + '</td>' +
                    '<td><span class="budget-status budget-status-' + esc(row.status) + '">' + esc(t('budget.status.' + row.status)) + '</span></td>' +
                    '<td>' + esc(row.phone_label || '—') + '</td>' +
                    '<td><div class="budget-row-actions">' +
                    (unconfirmed ? '<button type="button" class="btn btn-primary" data-act="confirm" data-id="' + row.id + '">' + esc(t('budget.btn.confirm')) + '</button>'
                        : '<button type="button" class="btn btn-secondary" data-act="unconfirm" data-id="' + row.id + '">' + esc(t('budget.btn.unconfirm')) + '</button>') +
                    '<button type="button" class="btn btn-secondary" data-act="edit" data-id="' + row.id + '">' + esc(t('budget.btn.edit')) + '</button>' +
                    '<button type="button" class="btn btn-danger" data-act="delete" data-id="' + row.id + '">' + esc(t('budget.btn.delete')) + '</button>' +
                    '</div></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        document.getElementById('budget-person-body').innerHTML = html;
    }
    function refreshAfter(data) {
        if (data && data.person) { renderPerson(data.person); }
        loadPeople();
    }

    document.getElementById('budget-person-body').addEventListener('submit', function (event) {
        if (event.target.id !== 'budget-person-start') { return; }
        event.preventDefault();
        var value = event.target.elements.indiensttreding.value;
        post('set_start', { email: state.person.email, naam: state.person.naam, indiensttreding: value })
            .then(refreshAfter).catch(function (e) { msg('budget-person-modal', e.message); });
    });
    document.getElementById('budget-person-body').addEventListener('click', function (event) {
        if (event.target.id === 'budget-add') { openPurchase(null); return; }
        var btn = event.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var act = btn.getAttribute('data-act');
        var row = state.person.purchases.filter(function (p) { return p.id === id; })[0];
        if (act === 'edit') { openPurchase(row); return; }
        var textKey = act === 'confirm' ? 'budget.confirm.confirm' : act === 'unconfirm' ? 'budget.confirm.unconfirm' : 'budget.confirm.delete';
        askConfirm(t(textKey, id), function () {
            return post(act === 'delete' ? 'delete_purchase' : act, { id: id }).then(refreshAfter);
        }, act === 'delete');
    });

    function askConfirm(text, action, danger) {
        state.confirmAction = action;
        document.getElementById('budget-confirm-text').textContent = text;
        var yes = document.getElementById('budget-confirm-yes');
        yes.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
        open('budget-confirm-modal');
    }
    document.getElementById('budget-confirm-yes').addEventListener('click', function () {
        var action = state.confirmAction;
        if (!action) { return; }
        action().then(function () { state.confirmAction = null; close('budget-confirm-modal'); })
            .catch(function (e) { msg('budget-confirm-modal', e.message); });
    });

    /* ----------------------------------------------------- aankoopmodal -- */
    var purchaseForm = document.getElementById('budget-purchase-form');
    function openPurchase(row) {
        state.purchase = row;
        document.getElementById('budget-purchase-modal-title').textContent = t(row ? 'budget.modal.purchase_edit' : 'budget.modal.purchase_new') + (row ? ' #' + row.id : '');
        purchaseForm.elements.prijs.value = row ? moneyInput(row.prijs_cents) : '';
        purchaseForm.elements.datum.value = row ? row.datum : todayIso;
        purchaseForm.elements.telefoon.value = row ? row.telefoon : '';
        purchaseForm.elements.notitie.value = row ? row.notitie : '';
        document.getElementById('budget-purchase-preview').innerHTML = '';
        open('budget-purchase-modal');
        updatePreview();
        purchaseForm.elements.prijs.focus();
    }
    function updatePreview() {
        clearTimeout(state.previewTimer);
        state.previewTimer = setTimeout(function () {
            var prijs = purchaseForm.elements.prijs.value.trim();
            var target = document.getElementById('budget-purchase-preview');
            if (!prijs) { target.innerHTML = ''; msg('budget-purchase-modal', ''); return; }
            api('preview', { email: state.person.email, prijs: prijs, datum: purchaseForm.elements.datum.value, id: state.purchase ? state.purchase.id : 0 })
                .then(function (data) {
                    var pv = data.preview;
                    msg('budget-purchase-modal', '');
                    target.innerHTML = '<div><span class="budget-muted">' + esc(t('budget.preview.before')) + '</span><strong>' + esc(money(pv.budget_voor_cents)) + '</strong></div>' +
                        '<div><span class="budget-muted">' + esc(t('budget.preview.after')) + '</span><strong>' + esc(money(pv.budget_na_cents)) + '</strong></div>' +
                        '<div class="' + (pv.eigen_bijdrage_cents > 0 ? 'is-own' : '') + '"><span class="budget-muted">' + esc(t('budget.preview.own')) + '</span><strong>' + esc(money(pv.eigen_bijdrage_cents)) + '</strong></div>';
                }).catch(function (e) { target.innerHTML = ''; msg('budget-purchase-modal', e.message); });
        }, 250);
    }
    purchaseForm.elements.prijs.addEventListener('input', updatePreview);
    purchaseForm.elements.datum.addEventListener('change', updatePreview);
    purchaseForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = {
            prijs: purchaseForm.elements.prijs.value, datum: purchaseForm.elements.datum.value,
            telefoon: purchaseForm.elements.telefoon.value, notitie: purchaseForm.elements.notitie.value
        };
        var request = state.purchase
            ? post('update_purchase', Object.assign({ id: state.purchase.id }, body))
            : post('add_purchase', Object.assign({ email: state.person.email }, body));
        request.then(function (data) { close('budget-purchase-modal'); refreshAfter(data); })
            .catch(function (e) { msg('budget-purchase-modal', e.message); });
    });

    /* ------------------------------------------------------- instellingen -- */
    var settingsForm = document.getElementById('budget-settings-form');
    document.getElementById('budget-settings-btn').addEventListener('click', function () {
        api('settings').then(function (data) {
            Object.keys(data.settings).forEach(function (k) { if (settingsForm.elements[k]) { settingsForm.elements[k].value = moneyInput(data.settings[k]); } });
            open('budget-settings-modal');
        }).catch(function (e) { msg('budget-page-message', e.message); });
    });
    settingsForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = {};
        ['start_cents', 'monthly_cents', 'max_cents', 'depreciation_cents'].forEach(function (k) { body[k] = settingsForm.elements[k].value; });
        post('save_settings', body).then(function () {
            close('budget-settings-modal'); msg('budget-page-message', t('budget.settings.saved')); loadPeople();
        }).catch(function (e) { msg('budget-settings-modal', e.message); });
    });

    /* -------------------------------------------------------------- import -- */
    var importForm = document.getElementById('budget-import-form');
    document.getElementById('budget-import-btn').addEventListener('click', function () {
        importForm.reset();
        document.getElementById('budget-import-result').innerHTML = '';
        state.importData = null;
        open('budget-import-modal');
    });
    importForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var fd = new FormData(importForm);
        msg('budget-import-modal', '');
        fetch('budget_api.php?action=import_preview', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(handle).then(function (data) { state.importData = data; renderImport(); })
            .catch(function (e) { msg('budget-import-modal', e.message); });
    });
    function personOptions(p, mensen) {
        var html = '<option value="">' + esc(t('budget.import.skip')) + '</option>';
        var seen = {};
        p.kandidaten.forEach(function (c) {
            seen[c.email] = true;
            html += '<option value="' + esc(c.email) + '"' + (p.email === c.email ? ' selected' : '') + '>' + esc((c.naam || c.email) + ' <' + c.email + '>') + '</option>';
        });
        html += '<option disabled>──────────</option>';
        mensen.forEach(function (m) {
            if (!seen[m.email]) { html += '<option value="' + esc(m.email) + '">' + esc((m.naam || m.email) + ' <' + m.email + '>') + '</option>'; }
        });
        return html;
    }
    function renderImport() {
        var d = state.importData;
        var mensen = d.mensen.slice().sort(function (a, b) { return (a.naam || a.email).localeCompare(b.naam || b.email); });
        var c = d.tellingen;
        var html = '<p><strong>' + esc(t('budget.import.summary', c.totaal, c.nieuw, c.al_geimporteerd, c.fout)) + '</strong></p>';
        html += '<h3>' + esc(t('budget.import.persons')) + '</h3><div class="budget-table-wrap"><table class="budget-table"><thead><tr><th>' + esc(t('budget.import.source')) +
            '</th><th>' + esc(t('budget.import.match')) + '</th><th class="num">' + esc(t('budget.import.rows')) + '</th><th>' + esc(t('budget.col.start')) + '</th></tr></thead><tbody>';
        d.personen.slice().sort(function (a, b) { return (a.zeker === b.zeker) ? a.bron.localeCompare(b.bron) : (a.zeker ? 1 : -1); }).forEach(function (p) {
            var badge = p.zeker ? 'bevestigd' : 'onbevestigd';
            var label = p.zeker ? t('budget.import.sure') : (p.kandidaten.length ? t('budget.import.unsure') : t('budget.import.unknown'));
            html += '<tr><td>' + esc(p.bron) + ' <span class="budget-status budget-status-' + badge + '">' + esc(label) + '</span></td>' +
                '<td><select data-import-key="' + esc(p.key) + '" data-sure="' + (p.zeker ? '1' : '0') + '">' + (p.zeker ? '' : '<option value="__choose__" selected>…</option>') + personOptions(p, mensen) + '</select></td>' +
                '<td class="num">' + p.nieuw + ' / ' + p.aantal + '</td><td>' + esc(date(p.indiensttreding)) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        var errors = d.rows.filter(function (r) { return r.error; });
        if (errors.length) {
            html += '<h3>' + esc(t('budget.import.errors')) + '</h3><ul>' + errors.map(function (r) { return '<li>#' + r.row + ': ' + esc(r.persoon || '?') + ' (' + esc(r.error) + ')</li>'; }).join('') + '</ul>';
        }
        html += '<div class="modal-actions"><button type="button" class="btn btn-primary" id="budget-import-run">' + esc(t('budget.import.run')) + '</button></div>';
        document.getElementById('budget-import-result').innerHTML = html;
    }
    function collectMapping() {
        var mapping = {};
        var missingChoice = false;
        document.querySelectorAll('#budget-import-result select[data-import-key]').forEach(function (sel) {
            if (sel.value === '__choose__') { missingChoice = true; return; }
            mapping[sel.getAttribute('data-import-key')] = sel.value;
        });
        return missingChoice ? null : mapping;
    }
    document.getElementById('budget-import-result').addEventListener('click', function (event) {
        if (event.target.id !== 'budget-import-run') { return; }
        var mapping = collectMapping();
        if (!mapping) { msg('budget-import-modal', t('budget.import.need_choice')); return; }
        msg('budget-import-modal', '');
        var d = state.importData;
        var known = {};
        d.mensen.forEach(function (m) { known[m.email] = m; });
        var byEmail = {};
        d.personen.forEach(function (p) {
            var email = mapping[p.key];
            if (!email || p.nieuw === 0) { return; }
            var m = known[email] || { email: email, naam: email, indiensttreding: null };
            if (m.indiensttreding) { return; }
            if (!byEmail[email]) { byEmail[email] = { email: email, naam: m.naam || email, bron: p.bron, aantal: 0, eerste: p.eerste_datum }; }
            byEmail[email].aantal += p.nieuw;
            if (p.eerste_datum < byEmail[email].eerste) { byEmail[email].eerste = p.eerste_datum; }
        });
        state.importMapping = mapping;
        state.importStarts = {};
        state.importQueue = Object.keys(byEmail).map(function (k) { return byEmail[k]; });
        nextStartQuestion();
    });
    var startForm = document.getElementById('budget-start-form');
    function nextStartQuestion() {
        var next = state.importQueue[0];
        if (!next) { close('budget-start-modal'); commitImport(); return; }
        document.getElementById('budget-start-text').textContent = t('budget.import.ask_start_body', next.naam, next.email, next.bron, next.aantal, date(next.eerste));
        startForm.elements.indiensttreding.value = '';
        startForm.elements.indiensttreding.max = next.eerste;
        open('budget-start-modal');
        startForm.elements.indiensttreding.focus();
    }
    startForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var current = state.importQueue.shift();
        state.importStarts[current.email] = startForm.elements.indiensttreding.value;
        nextStartQuestion();
    });
    function commitImport() {
        post('import_commit', { token: state.importData.token, mapping: state.importMapping, starts: state.importStarts })
            .then(function (data) {
                var r = data.result;
                document.getElementById('budget-import-result').innerHTML = '<p><strong>' + esc(t('budget.import.done', r.toegevoegd, r.overgeslagen, r.personen)) + '</strong></p>';
                state.importData = null;
                loadPeople();
            }).catch(function (e) { msg('budget-import-modal', e.message); });
    }

    /* ----------------------------------------------------- telefoon-koppeling -- */
    function renderPhoneLink(container, imei) {
        if (!container || !imei) { return; }
        container.className = 'budget-link-block';
        container.innerHTML = '<span class="budget-muted">…</span>';
        api('phone_options', { imei: imei }).then(function (data) {
            var html = '<h3>' + esc(t('budget.link.title')) + '</h3>';
            if (!data.email) {
                html += '<p class="budget-muted">' + esc(t('budget.link.no_user')) + '</p>';
                if (data.linked) { html += '<p>#' + data.linked.id + ' · ' + esc(date(data.linked.datum)) + ' · ' + esc(money(data.linked.prijs_cents)) + '</p>'; }
            }
            if (data.email || data.linked) {
                html += '<select data-budget-link="' + esc(imei) + '"><option value="0">' + esc(t('budget.link.none')) + '</option>';
                var options = data.options.slice();
                if (data.linked && !options.some(function (o) { return o.id === data.linked.id; })) { options.unshift(Object.assign({ beschikbaar: true }, data.linked)); }
                options.forEach(function (o) {
                    var selected = data.linked && data.linked.id === o.id;
                    html += '<option value="' + o.id + '"' + (selected ? ' selected' : '') + (o.beschikbaar ? '' : ' disabled') + '>#' + o.id + ' · ' + esc(date(o.datum)) + ' · ' + esc(money(o.prijs_cents)) +
                        (o.telefoon ? ' · ' + esc(o.telefoon) : '') + (o.beschikbaar ? '' : ' (' + esc(t('budget.link.taken')) + ')') + '</option>';
                });
                html += '</select><div class="message" data-budget-link-msg></div>';
            }
            container.innerHTML = html;
            var sel = container.querySelector('select');
            if (sel) {
                sel.addEventListener('change', function () {
                    var box = container.querySelector('[data-budget-link-msg]');
                    post('link_phone', { imei: imei, purchase_id: parseInt(sel.value, 10) || 0 }).then(function () {
                        box.textContent = t('budget.link.saved'); box.classList.add('is-visible');
                    }).catch(function (e) { box.textContent = e.message; box.classList.add('is-visible'); renderPhoneLink(container, imei); });
                });
            }
        }).catch(function (e) { container.innerHTML = '<p class="budget-muted">' + esc(e.message) + '</p>'; });
    }

    /* -------------------------------------------------------------- events -- */
    var searchTimer = null;
    document.getElementById('budget-search').addEventListener('input', function (event) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.q = event.target.value.trim(); state.page = 1; loadPeople(); }, 200);
    });
    document.getElementById('budget-filters').addEventListener('click', function (event) {
        var chip = event.target.closest('.chip');
        if (!chip) { return; }
        document.querySelectorAll('#budget-filters .chip').forEach(function (c) { c.classList.toggle('is-active', c === chip); });
        state.filter = chip.getAttribute('data-filter'); state.page = 1; loadPeople();
    });
    document.getElementById('budget-prev').addEventListener('click', function () { if (state.page > 1) { state.page--; loadPeople(); } });
    document.getElementById('budget-next').addEventListener('click', function () { if (state.page < state.pages) { state.page++; loadPeople(); } });
    document.getElementById('budget-people').addEventListener('click', function (event) {
        var row = event.target.closest('tr[data-email]');
        if (row) { openPerson(row.getAttribute('data-email'), row.getAttribute('data-naam')); }
    });
    document.querySelectorAll('[data-budget-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-budget-close');
            if (id === 'budget-start-modal') { state.importQueue = []; }
            close(id);
        });
    });

    window.MoiraiBudget = {
        activate: function (show) {
            panel.hidden = !show;
            document.body.classList.toggle('budget-active', !!show);
            if (show) { loadPeople(); }
        },
        renderPhoneLink: renderPhoneLink
    };
})();
