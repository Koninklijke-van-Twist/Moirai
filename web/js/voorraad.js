/* Voorraad-tabblad: categorieën met een aantal op voorraad. Server: voorraad_api.php. */
(function () {
    'use strict';
    var panel = document.getElementById('voorraad-panel');
    if (!panel) {
        return;
    }
    var isAdmin = panel.getAttribute('data-admin') === '1';
    var i18n = JSON.parse(panel.getAttribute('data-i18n') || '{}');
    var listEl = document.getElementById('voorraad-list');
    var loader = document.getElementById('voorraad-loader');
    var emptyEl = document.getElementById('voorraad-empty');
    var pageMsg = document.getElementById('voorraad-message');
    var modalBody = document.getElementById('voorraad-modal-body');
    var modalActions = document.getElementById('voorraad-modal-actions');
    var modalMsg = document.getElementById('voorraad-modal-message');
    var modalTitle = document.getElementById('voorraad-modal-title');
    var state = { q: '', items: [], current: null, req: 0, active: false };
    var searchTimer = null;

    function t(key) {
        var args = Array.prototype.slice.call(arguments, 1);
        var str = i18n[key] || key;
        args.forEach(function (arg) { str = str.replace('%s', arg); });
        return str;
    }
    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function msg(el, text, ok) {
        el.textContent = text || '';
        el.classList.toggle('is-visible', !!text);
        el.classList.toggle('voorraad-ok', !!ok);
    }
    function api(action, params, body) {
        var url = 'voorraad_api.php?' + new URLSearchParams(Object.assign({ action: action }, params || {})).toString();
        var opts = body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {};
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok || !data.ok) { throw new Error(data.error || t('moirai.error.request_failed')); }
                return data;
            });
        });
    }
    function props(item) {
        return String(item.eigenschappen || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    }
    function openModal() { var el = document.getElementById('voorraad-modal'); el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false'); }
    function closeModal() { var el = document.getElementById('voorraad-modal'); el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); msg(modalMsg, ''); state.current = null; }

    function load() {
        var req = ++state.req;
        loader.hidden = false;
        return api('list', { q: state.q }).then(function (data) {
            if (req !== state.req) { return; }
            state.items = data.items || [];
            render();
        }).catch(function (e) {
            msg(pageMsg, e.message);
        }).then(function () { if (req === state.req) { loader.hidden = true; } });
    }

    function render() {
        msg(pageMsg, '');
        emptyEl.hidden = state.items.length > 0;
        listEl.hidden = state.items.length === 0;
        listEl.innerHTML = state.items.map(function (item) {
            var p = props(item);
            var badge = item.aantal > 0
                ? '<span class="badge badge-reserve">' + esc(t('voorraad.badge.count', item.aantal)) + '</span>'
                : '<span class="badge badge-unavailable">' + esc(t('voorraad.badge.empty')) + '</span>';
            var note = '<div class="device-item-note" data-voorraad-note="' + esc(item.id) + '"></div>';
            return '<button type="button" class="device-item" data-voorraad-id="' + esc(item.id) + '">' +
                '<div class="device-item-main">' +
                '<p class="device-name"><span>' + esc(item.naam) + '</span></p>' +
                (p.length ? '<ul class="voorraad-props">' + p.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '') +
                '<div class="device-badges">' + badge + '</div>' +
                '</div>' + note + '</button>';
        }).join('');
        state.items.forEach(function (item) {
            if (!item.last_note || typeof KvtChat === 'undefined' || !KvtChat.renderMessage) { return; }
            var slot = listEl.querySelector('[data-voorraad-note="' + String(item.id).replace(/"/g, '') + '"]');
            if (slot) { KvtChat.renderMessage(slot, item.last_note, { actions: false }); }
        });
    }

    function btn(label, cls, fn) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn ' + cls;
        b.textContent = label;
        b.addEventListener('click', fn);
        modalActions.appendChild(b);
        return b;
    }

    function updateItem(item) {
        state.current = item;
        state.items = state.items.map(function (x) { return x.id === item.id ? Object.assign({}, x, item) : x; });
        render();
    }

    function showDetails(item) {
        state.current = item;
        modalTitle.textContent = item.naam;
        var p = props(item);
        var html = '<dl class="detail-grid">' +
            '<div class="detail-row"><dt>' + esc(t('voorraad.field.aantal')) + '</dt><dd><span class="voorraad-count' + (item.aantal > 0 ? '' : ' is-empty') + '" id="voorraad-count">' + esc(item.aantal) + '</span></dd></div>' +
            '<div class="detail-row"><dt>' + esc(t('voorraad.field.eigenschappen')) + '</dt><dd>' +
            (p.length ? '<ul class="voorraad-props">' + p.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '—') +
            '</dd></div></dl>';
        if (isAdmin) {
            html += '<div class="voorraad-adjust"><div><label for="voorraad-adjust-amount">' + esc(t('voorraad.adjust.label')) + '</label>' +
                '<input type="number" id="voorraad-adjust-amount" min="0" step="1" inputmode="numeric" value="1"></div>' +
                '<button type="button" class="btn btn-secondary" data-adjust="add">' + esc(t('voorraad.btn.add')) + '</button>' +
                '<button type="button" class="btn btn-secondary" data-adjust="set">' + esc(t('voorraad.btn.set')) + '</button></div>';
        }
        modalBody.innerHTML = html;
        modalActions.innerHTML = '';
        if (isAdmin) {
            var issueBtn = btn(t('voorraad.btn.issue'), 'btn-primary', function () {
                api('issue', null, { id: item.id }).then(function (data) {
                    updateItem(data.item);
                    showDetails(data.item);
                    msg(modalMsg, t('voorraad.issued', data.item.aantal), true);
                }).catch(function (e) { msg(modalMsg, e.message); });
            });
            issueBtn.disabled = item.aantal <= 0;
            modalBody.querySelectorAll('[data-adjust]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var amount = document.getElementById('voorraad-adjust-amount').value;
                    api('adjust', null, { id: item.id, mode: b.getAttribute('data-adjust'), amount: amount }).then(function (data) {
                        updateItem(data.item);
                        showDetails(data.item);
                        msg(modalMsg, '');
                    }).catch(function (e) { msg(modalMsg, e.message); });
                });
            });
        }
        btn(t('moirai.btn.notes'), 'btn-secondary', function () {
            if (typeof KvtChat === 'undefined' || !KvtChat.open) { return; }
            KvtChat.open({ threadKey: 'device:voorraad:' + item.id, title: t('moirai.notes.title') });
        });
        btn(t('moirai.btn.print_label'), 'btn-secondary', function () {
            fetch('print_label.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'voorraad', id: item.id })
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok || !data.url) { throw new Error(data.error || t('moirai.error.print_failed')); }
                window.location.href = data.url;
            }).catch(function (e) { msg(modalMsg, e.message || t('moirai.error.print_failed')); });
        });
        if (isAdmin) {
            btn(t('moirai.btn.edit'), 'btn-secondary', function () { showForm(item); });
        }
    }

    function showForm(item) {
        var isNew = !item;
        item = item || { id: '', naam: '', eigenschappen: '', aantal: 0 };
        modalTitle.textContent = t(isNew ? 'voorraad.modal.new' : 'voorraad.modal.edit');
        modalBody.innerHTML = '<form class="form-grid" id="voorraad-form">' +
            '<div><label for="voorraad-naam">' + esc(t('voorraad.field.naam')) + '</label>' +
            '<input type="text" id="voorraad-naam" name="naam" maxlength="120" required autocomplete="off" value="' + esc(item.naam) + '"></div>' +
            '<div><label for="voorraad-eig">' + esc(t('voorraad.field.eigenschappen')) + '</label>' +
            '<textarea id="voorraad-eig" name="eigenschappen" rows="3">' + esc(item.eigenschappen) + '</textarea>' +
            '<p class="voorraad-hint">' + esc(t('voorraad.field.eigenschappen_hint')) + '</p></div>' +
            '<div><label for="voorraad-aantal">' + esc(t('voorraad.field.aantal')) + '</label>' +
            '<input type="number" id="voorraad-aantal" name="aantal" min="0" step="1" inputmode="numeric" required value="' + esc(item.aantal) + '"></div>' +
            '</form>';
        modalActions.innerHTML = '';
        msg(modalMsg, '');
        btn(t('moirai.btn.save'), 'btn-primary', function () {
            var form = document.getElementById('voorraad-form');
            api('save', null, {
                id: item.id,
                naam: form.naam.value,
                eigenschappen: form.eigenschappen.value,
                aantal: form.aantal.value
            }).then(function (data) {
                load();
                showDetails(data.item);
                msg(modalMsg, '');
            }).catch(function (e) { msg(modalMsg, e.message); });
        });
        btn(t('moirai.btn.cancel'), 'btn-secondary', function () {
            if (isNew) { closeModal(); } else { showDetails(item); }
        });
        if (!isNew) {
            btn(t('moirai.btn.delete'), 'btn-danger', function () {
                if (!window.confirm(t('voorraad.delete.confirm', item.naam))) { return; }
                api('delete', null, { id: item.id }).then(function () {
                    closeModal();
                    load();
                }).catch(function (e) { msg(modalMsg, e.message); });
            });
        }
        openModal();
        document.getElementById('voorraad-naam').focus();
    }

    function openCategory(id) {
        return api('get', { id: id }).then(function (data) {
            msg(modalMsg, '');
            showDetails(data.item);
            openModal();
            return data.item;
        }).catch(function (e) { msg(pageMsg, e.message); });
    }

    listEl.addEventListener('click', function (event) {
        var row = event.target.closest('[data-voorraad-id]');
        if (row) { openCategory(row.getAttribute('data-voorraad-id')); }
    });
    document.getElementById('voorraad-search').addEventListener('input', function (event) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.q = event.target.value.trim(); load(); }, 200);
    });
    var addBtn = document.getElementById('voorraad-add-btn');
    if (addBtn) { addBtn.addEventListener('click', function () { showForm(null); }); }
    document.querySelectorAll('[data-voorraad-close]').forEach(function (b) { b.addEventListener('click', closeModal); });
    document.getElementById('voorraad-modal').addEventListener('click', function (event) {
        if (event.target === event.currentTarget) { closeModal(); }
    });

    window.MoiraiVoorraad = {
        activate: function (show) {
            state.active = !!show;
            panel.hidden = !show;
            document.body.classList.toggle('voorraad-active', !!show);
            if (show) { return load(); }
            return Promise.resolve();
        },
        openCategory: openCategory
    };
})();
