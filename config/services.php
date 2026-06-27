<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram_bot' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'timeout' => env('TELEGRAM_BOT_TIMEOUT', 10),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'ai_enabled' => env('TELEGRAM_BOT_AI_ENABLED', true),
        'vertex_user_id' => env('TELEGRAM_BOT_VERTEX_USER_ID'),
    ],

    'telegram_error_log' => [
        'enabled' => env('TELEGRAM_ERROR_LOG_ENABLED', false),
        'bot_token' => env('TELEGRAM_ERROR_LOG_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_ERROR_LOG_CHAT_ID'),
        'timeout' => env('TELEGRAM_ERROR_LOG_TIMEOUT', 5),
    ],

    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'vertex' => [
        'model' => env('VERTEX_MODEL', 'gemini-2.5-flash-image'),
        'text_model' => env('VERTEX_TEXT_MODEL', 'gemini-2.5-flash'),
        'lock_seconds' => env('VERTEX_LOCK_SECONDS', 600),
        'lock_wait_seconds' => env('VERTEX_LOCK_WAIT_SECONDS', 600),
        'priority_lock_wait_seconds' => env('VERTEX_PRIORITY_LOCK_WAIT_SECONDS', 600),
        'cooldown_seconds' => env('VERTEX_COOLDOWN_SECONDS', 90),
        'http_proxy' => env('VERTEX_HTTP_PROXY'),
        'max_input_dimension' => env('VERTEX_MAX_INPUT_DIMENSION', 1400),
        'max_inline_image_bytes' => env('VERTEX_MAX_INLINE_IMAGE_BYTES', 4_194_304),
        'google_drive_thumbnail_size' => env('VERTEX_GOOGLE_DRIVE_THUMBNAIL_SIZE', 1200),
        'debug_payload' => env('VERTEX_DEBUG_PAYLOAD', false),
    ],

    'background_removal' => [
        'enabled' => env('OFFOREST_REMOVE_VERTEX_BACKGROUND', false),
        'engine' => env('OFFOREST_BACKGROUND_REMOVAL_ENGINE', 'magic_eraser'),
        'model' => env('OFFOREST_BACKGROUND_REMOVAL_MODEL', 'briaai/RMBG-1.4'),
        'image_driver' => env('OFFOREST_BACKGROUND_REMOVAL_IMAGE_DRIVER', 'GD'),
        'clean_alpha' => env('OFFOREST_BACKGROUND_REMOVAL_CLEAN_ALPHA', true),
        'alpha_min_opacity' => env('OFFOREST_BACKGROUND_REMOVAL_ALPHA_MIN_OPACITY', 45),
        'min_component_area' => env('OFFOREST_BACKGROUND_REMOVAL_MIN_COMPONENT_AREA', 180),
        'edge_margin_ratio' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_MARGIN_RATIO', 0.015),
        'foreground_gap_ratio' => env('OFFOREST_BACKGROUND_REMOVAL_FOREGROUND_GAP_RATIO', 0.08),
        'edge_flood_clean' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_FLOOD_CLEAN', true),
        'edge_color_tolerance' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_COLOR_TOLERANCE', 58),
        'edge_flood_min_opacity' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_FLOOD_MIN_OPACITY', 12),
        'edge_color_samples' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_COLOR_SAMPLES', 3),
        'edge_color_bucket_size' => env('OFFOREST_BACKGROUND_REMOVAL_EDGE_COLOR_BUCKET_SIZE', 24),
    ],

];
