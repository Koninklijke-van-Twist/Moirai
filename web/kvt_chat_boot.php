<?php

/**
 * Host boot for kvt-chat endpoints (api.php / avatar.php) and pages that render the modal.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';
require_once __DIR__ . '/lib/kvt-chat/KvtChat.php';

if (!KvtChat::isConfigured()) {
    KvtChat::configure([
        'pdo' => moirai_db(),
        'avatar_dir' => __DIR__ . '/data/user_avatars',
        'avatar_url' => 'lib/kvt-chat/avatar.php',
        'api_url' => 'lib/kvt-chat/api.php',
        'viewer' => static fn(): array => [
            'email' => moirai_current_user_email(),
            'name' => moirai_current_user_name(),
        ],
        'is_admin' => static fn(): bool => moirai_is_admin(),
        'can_edit' => 'own',
        'can_delete' => 'own',
        'admin_bypass' => true,
        'migrate_device_notes' => true,
        'date_locale' => function_exists('getDateLocale') ? getDateLocale() : 'nl-NL',
        'i18n' => [
            'title' => LOC('moirai.notes.title'),
            'messages' => LOC('moirai.notes.messages'),
            'empty' => LOC('moirai.notes.empty'),
            'message_label' => LOC('moirai.notes.message_label'),
            'edit_label' => LOC('moirai.notes.edit_label'),
            'close' => LOC('moirai.btn.close'),
            'edit' => LOC('moirai.btn.edit'),
            'delete' => LOC('moirai.btn.delete'),
            'cancel' => LOC('moirai.btn.cancel'),
            'cancel_edit' => LOC('moirai.notes.cancel_edit'),
            'edited_suffix' => LOC('moirai.notes.edited_suffix'),
            'delete_confirm_title' => LOC('moirai.notes.delete.confirm.title'),
            'delete_confirm_body' => LOC('moirai.notes.delete.confirm.body'),
            'delete_confirm_yes' => LOC('moirai.notes.btn.delete_confirm'),
            'load_failed' => LOC('moirai.notes.load_failed'),
            'send_failed' => LOC('moirai.notes.send_failed'),
            'save_failed' => LOC('moirai.notes.save_failed'),
            'delete_failed' => LOC('moirai.notes.delete_failed'),
            'unknown_user' => LOC('moirai.unknown_user'),
            'error.thread_required' => LOC('moirai.error.invalid_input'),
            'error.forbidden' => LOC('moirai.error.forbidden'),
            'error.empty' => LOC('moirai.error.note_empty'),
            'error.too_long' => LOC('moirai.error.note_too_long'),
            'error.not_found' => LOC('moirai.error.note_not_found'),
        ],
    ]);
}

// Ensure schema exists and legacy device_notes are imported once.
KvtChat::store();
