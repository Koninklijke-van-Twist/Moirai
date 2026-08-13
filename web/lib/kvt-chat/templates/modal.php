<?php

/** @var array<string, string> $i18n */
$i18n = KvtChat::config()['i18n'];
$h = static fn(string $key): string => htmlspecialchars((string) ($i18n[$key] ?? $key), ENT_QUOTES, 'UTF-8');
?>
<div class="kvt-chat-overlay" id="kvt-chat-modal" hidden aria-hidden="true" data-role="kvt-chat-modal">
    <div class="kvt-chat-dialog" role="dialog" aria-modal="true" aria-labelledby="kvt-chat-title">
        <div class="kvt-chat-head">
            <h2 id="kvt-chat-title" data-role="kvt-chat-title"><?= $h('title') ?></h2>
            <button type="button" class="kvt-chat-close" data-role="kvt-chat-close"
                aria-label="<?= $h('close') ?>">&times;</button>
        </div>
        <div class="kvt-chat-body ponos-detail-chat">
            <h3 class="ponos-detail-chat-title" data-role="kvt-chat-messages-title"><?= $h('messages') ?></h3>
            <div class="ponos-messages" data-role="kvt-chat-messages" aria-live="polite"></div>
            <p class="kvt-chat-empty" data-role="kvt-chat-empty" hidden><?= $h('empty') ?></p>
            <div class="ponos-message-compose">
                <label class="ponos-compose-label" for="kvt-chat-message-text" data-role="kvt-chat-compose-label"><?= $h('message_label') ?></label>
                <textarea id="kvt-chat-message-text" class="ponos-message-input" data-role="kvt-chat-input"
                    rows="1" maxlength="4000"></textarea>
                <div class="ponos-compose-toolbar" data-role="kvt-chat-compose-toolbar" hidden>
                    <button type="button" class="kvt-chat-btn kvt-chat-btn-secondary" data-role="kvt-chat-cancel-edit"><?= $h('cancel_edit') ?></button>
                </div>
            </div>
        </div>
        <p class="kvt-chat-feedback" data-role="kvt-chat-feedback" hidden></p>
    </div>
</div>

<div class="kvt-chat-confirm-overlay" id="kvt-chat-delete-confirm" hidden aria-hidden="true">
    <div class="kvt-chat-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="kvt-chat-delete-title">
        <h2 id="kvt-chat-delete-title"><?= $h('delete_confirm_title') ?></h2>
        <p data-role="kvt-chat-delete-body"><?= $h('delete_confirm_body') ?></p>
        <div class="kvt-chat-confirm-actions">
            <button type="button" class="kvt-chat-btn kvt-chat-btn-danger" data-role="kvt-chat-delete-yes"><?= $h('delete_confirm_yes') ?></button>
            <button type="button" class="kvt-chat-btn kvt-chat-btn-secondary" data-role="kvt-chat-delete-no"><?= $h('cancel') ?></button>
        </div>
    </div>
</div>
