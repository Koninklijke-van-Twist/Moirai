(function (global) {
    'use strict';

    var boot = global.KvtChatBoot || {};
    var state = {
        threadKey: '',
        editingId: null,
        pendingDeleteId: null,
        messagesById: {},
        i18n: Object.assign({}, boot.i18n || {}),
        dialog: Object.assign({}, boot.dialog || {}),
        pollMs: Number(boot.pollMs || 12000),
        maxLength: Number(boot.maxLength || 4000),
        apiUrl: String(boot.apiUrl || 'lib/kvt-chat/api.php'),
        avatarUrl: String(boot.avatarUrl || 'lib/kvt-chat/avatar.php')
    };

    var modal = null;
    var confirmEl = null;
    var messagesEl = null;
    var emptyEl = null;
    var inputEl = null;
    var feedbackEl = null;
    var composeLabel = null;
    var composeToolbar = null;
    var titleEl = null;
    var messagesTitleEl = null;
    var pollTimer = null;
    var sendInFlight = false;
    var loadInFlight = false;
    var ready = false;

    function t(key) {
        return state.i18n[key] || key;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#039;');
    }

    function applyDialog(dialog) {
        if (!modal || !dialog) {
            return;
        }
        if (dialog.width) {
            modal.style.setProperty('--kvt-chat-width', String(dialog.width));
        }
        if (dialog.maxHeight) {
            modal.style.setProperty('--kvt-chat-max-height', String(dialog.maxHeight));
        }
        if (dialog.minHeight) {
            modal.style.setProperty('--kvt-chat-min-height', String(dialog.minHeight));
        }
        if (dialog.zIndex != null) {
            modal.style.setProperty('--kvt-chat-z', String(dialog.zIndex));
        }
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
        return {
            border: 'hsl(' + hue + ', ' + saturation + '%, ' + Math.max(lightness - 6, 48) + '%)',
            chipBackground: 'hsl(' + hue + ', ' + saturation + '%, ' + lightness + '%)',
            cardBackground: 'hsl(' + hue + ', ' + Math.min(saturation, 48) + '%, 96%)',
            chipTextColor: lightness >= 58 ? '#1e293b' : '#ffffff'
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
        var base = state.avatarUrl;
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + 'email=' + encodeURIComponent(normalized);
    }

    function renderUserAvatarHtml(email, colors) {
        var url = userAvatarUrl(email);
        if (!url) {
            return '';
        }
        return '<img class="ponos-user-avatar" src="' + escapeHtml(url) + '" width="30" height="30" alt="" style="border-color:'
            + escapeHtml(colors.border) + '">';
    }

    function renderMessageHtml(message, options) {
        options = options || {};
        var showActions = options.actions !== false;
        var email = String(message.user_email || '').toLowerCase().trim();
        var authorName = String(message.user_label || '').trim() || email || t('unknown_user');
        var colors = (message.colors && typeof message.colors === 'object')
            ? message.colors
            : colorFromText(email);
        var timeLabel = String(message.created_at_label || '');
        if (message.updated_at) {
            timeLabel += t('edited_suffix');
        }
        var html = '';
        html += '<div class="ponos-message-row" data-message-id="' + escapeAttr(String(message.id || '')) + '">';
        html += '<div class="ponos-message-avatar-wrap">' + renderUserAvatarHtml(email, colors) + '</div>';
        html += '<article class="ponos-message" style="border-color:' + escapeHtml(colors.border)
            + ';background:' + escapeHtml(colors.cardBackground) + '">';
        html += '<div class="ponos-message-meta"><span class="ponos-message-email" style="background:'
            + escapeHtml(colors.chipBackground) + ';color:' + escapeHtml(colors.chipTextColor) + '">'
            + escapeHtml(authorName) + '</span><span>' + escapeHtml(timeLabel) + '</span>';
        if (showActions && (message.can_edit || message.can_delete)) {
            html += '<span class="ponos-message-actions">';
            if (message.can_edit) {
                html += '<button type="button" class="ponos-message-action" data-kvt-chat-edit="'
                    + escapeAttr(String(message.id || '')) + '">' + escapeHtml(t('edit')) + '</button>';
            }
            if (message.can_delete) {
                html += '<button type="button" class="ponos-message-action is-danger" data-kvt-chat-delete="'
                    + escapeAttr(String(message.id || '')) + '">' + escapeHtml(t('delete')) + '</button>';
            }
            html += '</span>';
        }
        html += '</div><div class="ponos-message-body">' + formatDescriptionHtml(message.message_text || '') + '</div></article></div>';
        return html;
    }

    /**
     * Render one chat message into an arbitrary DOM node.
     * @param {Element|string} target
     * @param {object|null} message
     * @param {{actions?: boolean, replace?: boolean, className?: string}} [options]
     */
    function renderMessage(target, message, options) {
        options = options || {};
        var el = typeof target === 'string' ? document.querySelector(target) : target;
        if (!el) {
            return null;
        }
        if (!message) {
            if (options.replace !== false) {
                el.innerHTML = '';
            }
            return el;
        }
        var className = options.className || 'kvt-chat-embed';
        if (className && !el.classList.contains(className)) {
            el.classList.add(className);
        }
        var html = renderMessageHtml(message, { actions: options.actions === true });
        if (options.replace === false) {
            el.insertAdjacentHTML('beforeend', html);
        } else {
            el.innerHTML = html;
        }
        return el;
    }

    function apiUrl(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        var base = state.apiUrl;
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + query.toString();
    }

    function fetchJson(url, options) {
        return fetch(url, options || {}).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data || !data.ok) {
                    var err = new Error((data && data.error) || t('load_failed'));
                    throw err;
                }
                return data;
            });
        });
    }

    function scrollMessagesToEnd() {
        if (!messagesEl) {
            return;
        }
        global.requestAnimationFrame(function () {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    }

    function resizeInput() {
        if (!inputEl) {
            return;
        }
        var maxHeight = Math.min(global.innerHeight * 0.32, 280);
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
        state.editingId = editingId || null;
        if (composeLabel) {
            composeLabel.textContent = state.editingId ? t('edit_label') : t('message_label');
        }
        if (composeToolbar) {
            composeToolbar.hidden = !state.editingId;
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
        state.messagesById = {};
        messagesEl.innerHTML = '';
        list.forEach(function (message) {
            var id = String(message.id || '');
            if (!id) {
                return;
            }
            state.messagesById[id] = message;
            messagesEl.insertAdjacentHTML('beforeend', renderMessageHtml(message));
        });
        if (emptyEl) {
            emptyEl.hidden = Object.keys(state.messagesById).length > 0;
        }
        scrollMessagesToEnd();
    }

    function loadMessages() {
        if (!state.threadKey || loadInFlight) {
            return Promise.resolve();
        }
        loadInFlight = true;
        return fetchJson(apiUrl('list', { thread_key: state.threadKey })).then(function (data) {
            loadInFlight = false;
            showFeedback('');
            setMessages(data.messages || []);
        }).catch(function () {
            loadInFlight = false;
            showFeedback(t('load_failed'), true);
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
            if (modal && !modal.hidden) {
                loadMessages();
            }
        }, state.pollMs);
    }

    function closeConfirm() {
        state.pendingDeleteId = null;
        if (!confirmEl) {
            return;
        }
        confirmEl.hidden = true;
        confirmEl.classList.remove('is-open');
        confirmEl.setAttribute('aria-hidden', 'true');
    }

    function openConfirm(id) {
        state.pendingDeleteId = String(id);
        if (!confirmEl) {
            return;
        }
        confirmEl.hidden = false;
        confirmEl.classList.add('is-open');
        confirmEl.setAttribute('aria-hidden', 'false');
    }

    function close() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        stopPolling();
        cancelEdit();
        closeConfirm();
        state.threadKey = '';
    }

    function open(options) {
        ensureReady();
        options = options || {};
        var threadKey = String(options.threadKey || options.thread_key || '').trim();
        if (!threadKey) {
            throw new Error('KvtChat.open requires threadKey');
        }
        if (options.i18n && typeof options.i18n === 'object') {
            state.i18n = Object.assign({}, state.i18n, options.i18n);
        }
        if (options.dialog && typeof options.dialog === 'object') {
            state.dialog = Object.assign({}, state.dialog, options.dialog);
            applyDialog(state.dialog);
        }
        if (options.title && titleEl) {
            titleEl.textContent = String(options.title);
        } else if (titleEl) {
            titleEl.textContent = t('title');
        }
        if (messagesTitleEl) {
            messagesTitleEl.textContent = t('messages');
        }
        if (emptyEl) {
            emptyEl.textContent = t('empty');
        }
        if (inputEl && state.maxLength) {
            inputEl.setAttribute('maxlength', String(state.maxLength));
        }

        state.threadKey = threadKey;
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        showFeedback('');
        cancelEdit();
        loadMessages().then(function () {
            resizeInput();
            if (inputEl) {
                inputEl.focus();
            }
        });
        startPolling();
    }

    function beginEdit(id) {
        var message = state.messagesById[String(id)];
        if (!message || !inputEl) {
            return;
        }
        setComposeMode(String(id));
        inputEl.value = String(message.message_text || '');
        resizeInput();
        inputEl.focus();
    }

    function confirmDelete() {
        var noteId = state.pendingDeleteId;
        if (!noteId) {
            closeConfirm();
            return;
        }
        fetchJson(apiUrl('delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: Number(noteId) })
        }).then(function () {
            closeConfirm();
            if (state.editingId && String(state.editingId) === String(noteId)) {
                cancelEdit();
            }
            return loadMessages();
        }).catch(function () {
            showFeedback(t('delete_failed'), true);
            closeConfirm();
        });
    }

    function sendOrSave() {
        if (!inputEl || sendInFlight || !state.threadKey) {
            return;
        }
        var text = String(inputEl.value || '').trim();
        if (!text) {
            return;
        }
        sendInFlight = true;
        showFeedback('');
        var editingId = state.editingId;
        var request;
        if (editingId) {
            request = fetchJson(apiUrl('edit'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: Number(editingId), message_text: text })
            });
        } else {
            request = fetchJson(apiUrl('add'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ thread_key: state.threadKey, message_text: text })
            });
        }
        request.then(function () {
            sendInFlight = false;
            cancelEdit();
            return loadMessages();
        }).catch(function () {
            sendInFlight = false;
            showFeedback(editingId ? t('save_failed') : t('send_failed'), true);
        });
    }

    function ensureReady() {
        if (ready) {
            return;
        }
        modal = document.getElementById('kvt-chat-modal');
        confirmEl = document.getElementById('kvt-chat-delete-confirm');
        if (!modal) {
            throw new Error('KvtChat markup missing. Call KvtChat::render() on the page.');
        }
        messagesEl = modal.querySelector('[data-role="kvt-chat-messages"]');
        emptyEl = modal.querySelector('[data-role="kvt-chat-empty"]');
        inputEl = modal.querySelector('[data-role="kvt-chat-input"]');
        feedbackEl = modal.querySelector('[data-role="kvt-chat-feedback"]');
        composeLabel = modal.querySelector('[data-role="kvt-chat-compose-label"]');
        composeToolbar = modal.querySelector('[data-role="kvt-chat-compose-toolbar"]');
        titleEl = modal.querySelector('[data-role="kvt-chat-title"]');
        messagesTitleEl = modal.querySelector('[data-role="kvt-chat-messages-title"]');
        applyDialog(state.dialog);

        var closeBtn = modal.querySelector('[data-role="kvt-chat-close"]');
        var cancelEditBtn = modal.querySelector('[data-role="kvt-chat-cancel-edit"]');
        var deleteYes = confirmEl ? confirmEl.querySelector('[data-role="kvt-chat-delete-yes"]') : null;
        var deleteNo = confirmEl ? confirmEl.querySelector('[data-role="kvt-chat-delete-no"]') : null;

        if (closeBtn) {
            closeBtn.addEventListener('click', close);
        }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                close();
            }
        });
        if (confirmEl) {
            confirmEl.addEventListener('click', function (event) {
                if (event.target === confirmEl) {
                    closeConfirm();
                }
            });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !modal || modal.hidden) {
                return;
            }
            if (confirmEl && !confirmEl.hidden) {
                closeConfirm();
                return;
            }
            close();
        });
        if (messagesEl) {
            messagesEl.addEventListener('click', function (event) {
                var editBtn = event.target.closest('[data-kvt-chat-edit]');
                if (editBtn) {
                    beginEdit(editBtn.getAttribute('data-kvt-chat-edit'));
                    return;
                }
                var deleteBtn = event.target.closest('[data-kvt-chat-delete]');
                if (deleteBtn) {
                    openConfirm(deleteBtn.getAttribute('data-kvt-chat-delete'));
                }
            });
        }
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', cancelEdit);
        }
        if (deleteYes) {
            deleteYes.addEventListener('click', confirmDelete);
        }
        if (deleteNo) {
            deleteNo.addEventListener('click', closeConfirm);
        }
        if (inputEl) {
            inputEl.addEventListener('input', resizeInput);
            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendOrSave();
                }
            });
        }
        ready = true;
    }

    global.KvtChat = {
        open: open,
        close: close,
        renderMessage: renderMessage,
        messageHtml: function (message, options) {
            return renderMessageHtml(message, options || { actions: false });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            try { ensureReady(); } catch (e) { /* markup may be absent on some pages */ }
        });
    } else {
        try { ensureReady(); } catch (e) { /* markup may be absent on some pages */ }
    }
})(window);
