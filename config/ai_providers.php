<?php

return [
    'default' => 'vertex',

    'providers' => [
        'vertex' => [
            'label' => 'Vertex',
            'description' => 'Google Vertex AI image generation.',
            'model' => env('VERTEX_MODEL', 'gemini-2.5-flash-image'),
        ],
        'chatgpt' => [
            'label' => 'ChatGPT',
            'description' => 'OpenAI image/text generation.',
            'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
            'image_models' => [
                'gpt-image-2' => 'GPT Image 2',
            ],
            'text_models' => [
                'gpt-5.1' => 'GPT 5.1',
                'gpt-4.1' => 'GPT 4.1',
                'gpt-4.1-mini' => 'GPT 4.1 Mini',
            ],
        ],
        'v98store' => [
            'label' => 'v98Store',
            'description' => 'v98Store image/text generation API.',
            'model' => env('V98STORE_IMAGE_MODEL', 'gpt-image-2'),
            'image_models' => [
                'gpt-image-2' => 'GPT Image 2',
            ],
            'text_models' => [
                'gpt-5.4' => 'GPT-5.4 (best)',
                'gpt-5.4-mini' => 'GPT-5.4 Mini',
                'gpt-5.4-nano' => 'GPT-5.4 Nano',
                'gpt-5.2' => 'GPT-5.2',
                'gpt-5.1' => 'GPT-5.1',
                'gpt-5' => 'GPT-5',
                'gpt-5-mini' => 'GPT-5 Mini',
            ],
        ],
        'cheapkeyai' => [
            'label' => 'CheapKeyAI',
            'description' => 'CheapKeyAI image/text generation API.',
            'model' => env('CHEAPKEYAI_IMAGE_MODEL', 'gpt-image-2'),
            'image_models' => [
                'gpt-image-2' => 'GPT Image 2',
            ],
            'text_models' => [
                'gpt-5.4' => 'GPT-5.4 (best)',
                'gpt-5.4-mini' => 'GPT-5.4 Mini',
                'gpt-5.4-nano' => 'GPT-5.4 Nano',
                'gpt-5.2' => 'GPT-5.2',
                'gpt-5.1' => 'GPT-5.1',
                'gpt-5' => 'GPT-5',
                'gpt-5-mini' => 'GPT-5 Mini',
            ],
        ],
    ],
];
