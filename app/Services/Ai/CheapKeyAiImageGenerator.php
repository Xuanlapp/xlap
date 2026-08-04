<?php

namespace App\Services\Ai;

use App\Models\User;

class CheapKeyAiImageGenerator
{
    public function __construct(
        private readonly ApiKeyImageGenerator $generator,
    ) {}

    public function generate(
        User $user,
        string $imageUri,
        string $prompt,
        string $folder,
        bool $removeBackground = false,
        ?string $model = null,
        ?string $functionKey = null,
    ): string {
        return $this->generator->generate(
            user: $user,
            providerKey: 'cheapkeyai',
            imageUri: $imageUri,
            prompt: $prompt,
            folder: $folder,
            removeBackground: $removeBackground,
            model: $model,
            functionKey: $functionKey,
        );
    }
}
