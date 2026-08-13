<?php

/**
 * Backward-compatible wrapper. Prefer lib/kvt-chat directly in new code.
 */

require_once __DIR__ . '/lib/kvt-chat/KvtChat.php';
require_once __DIR__ . '/lib/kvt-chat/avatars.php';

if (!KvtChat::isConfigured()) {
    KvtChat::configure([
        'db_path' => __DIR__ . '/data/moirai.sqlite',
        'avatar_dir' => __DIR__ . '/data/user_avatars',
        'avatar_url' => 'lib/kvt-chat/avatar.php',
        'api_url' => 'lib/kvt-chat/api.php',
    ]);
}

function moirai_user_avatar_dir(): string
{
    return kvt_chat_user_avatar_dir();
}

function moirai_user_avatar_path(string $email, ?string $colorKey = null): string
{
    return kvt_chat_user_avatar_path($email, $colorKey);
}

function moirai_ensure_user_avatar(string $email): bool
{
    return kvt_chat_ensure_user_avatar($email);
}

function moirai_user_avatar_url(string $email): string
{
    return kvt_chat_user_avatar_url($email);
}

function moirai_chat_colors_for_email(string $email): array
{
    return kvt_chat_colors_for_email($email);
}
