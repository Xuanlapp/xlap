<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
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

    'psd_mockup_renderer' => [
        'command' => env('PSD_MOCKUP_RENDERER_COMMAND', 'node scripts/psd-renderer/render.js'),
        'lock_seconds' => env('PSD_MOCKUP_RENDERER_LOCK_SECONDS', 900),
        'wait_seconds' => env('PSD_MOCKUP_RENDERER_WAIT_SECONDS', 1800),
    ],

    'google_drive' => [
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'make_public' => env('GOOGLE_DRIVE_MAKE_PUBLIC', true),
        'supports_all_drives' => env('GOOGLE_DRIVE_SUPPORTS_ALL_DRIVES', true),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'scopes' => env('GOOGLE_DRIVE_SCOPES', 'https://www.googleapis.com/auth/drive.file'),
    ],

    'marketplace_listing' => [
        'batch_size' => env('MARKETPLACE_LISTING_BATCH_SIZE', 0),
        'delay_seconds' => env('MARKETPLACE_LISTING_DELAY_SECONDS', 30),
        'lock_seconds' => env('MARKETPLACE_LISTING_LOCK_SECONDS', 21600),
    ],

    'api_key_providers' => [
        'defaults' => [
            'image_min_interval_ms' => (int) env('API_IMAGE_MIN_INTERVAL_MS', 2500),
            'text_min_interval_ms' => (int) env('API_TEXT_MIN_INTERVAL_MS', 700),
        ],
        'v98store' => [
            'image_generation_endpoint' => env('V98STORE_IMAGE_GENERATION_ENDPOINT', 'https://v98store.com/v1/images/generations'),
            'image_edit_endpoint' => env('V98STORE_IMAGE_EDIT_ENDPOINT', env('V98STORE_IMAGE_ENDPOINT', 'https://v98store.com/v1/images/edits')),
            'text_endpoint' => env('V98STORE_TEXT_ENDPOINT', 'https://v98store.com/v1/chat/completions'),
            'balance_endpoint' => env('V98STORE_BALANCE_ENDPOINT', 'https://v98store.com/check-balance'),
            'model' => env('V98STORE_IMAGE_MODEL', 'gpt-image-2'),
            'text_model' => env('V98STORE_TEXT_MODEL', 'gpt-5.4'),
            'image_min_interval_ms' => (int) env('V98STORE_IMAGE_MIN_INTERVAL_MS', 0),
            'text_min_interval_ms' => (int) env('V98STORE_TEXT_MIN_INTERVAL_MS', 0),
        ],
        'chatgpt' => [
            'image_generation_endpoint' => env('OPENAI_IMAGE_GENERATION_ENDPOINT', 'https://api.openai.com/v1/images/generations'),
            'image_edit_endpoint' => env('OPENAI_IMAGE_EDIT_ENDPOINT', env('OPENAI_IMAGE_ENDPOINT', 'https://api.openai.com/v1/images/edits')),
            'text_endpoint' => env('OPENAI_TEXT_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
            'text_model' => env('OPENAI_TEXT_MODEL', 'gpt-4.1-mini'),
            'image_min_interval_ms' => (int) env('OPENAI_IMAGE_MIN_INTERVAL_MS', env('API_IMAGE_MIN_INTERVAL_MS', 2500)),
            'text_min_interval_ms' => (int) env('OPENAI_TEXT_MIN_INTERVAL_MS', env('API_TEXT_MIN_INTERVAL_MS', 700)),
        ],
    ],

];
