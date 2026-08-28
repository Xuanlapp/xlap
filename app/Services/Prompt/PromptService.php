<?php

namespace App\Services\Prompt;

use App\Models\Product;
use App\Models\Prompt;
use App\Models\User;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Prompt\PromptRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use RuntimeException;

class PromptService
{
    public const MAX_PROMPTS_PER_PRODUCT = 4;

    public const ORNAMENT_AMAZON_TWO_MAX_PROMPTS = 1;

    /** Sticker and Glass use only the Create Master prompt slot. */
    public const SINGLE_CREATE_MASTER_PROMPT_PRODUCTS = ['sticker', 'glass'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly PromptRepository $prompts,
    ) {}

    public function productBySlug(string $productSlug): Product
    {
        return $this->products->findActiveBySlug($productSlug);
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function promptsForUserProduct(User $user, string $productSlug): Collection
    {
        $product = $this->productBySlug($productSlug);

        $maxPrompts = $this->maxPromptsForProduct($productSlug);

        return $this->prompts->forUserAndProduct($user->id, $product->id)
            ->filter(fn (Prompt $prompt): bool => (int) $prompt->prompt_number <= $maxPrompts)
            ->values();
    }

    public function createNextPrompt(User $user, string $productSlug): Prompt
    {
        $product = $this->productBySlug($productSlug);
        $prompts = $this->promptsForUserProduct($user, $productSlug);
        $maxPrompts = $this->maxPromptsForProduct($productSlug);

        if ($prompts->count() >= $maxPrompts) {
            throw new RuntimeException("Trang nay da du {$maxPrompts} prompt.");
        }

        $usedNumbers = $prompts->pluck('prompt_number')->map(fn ($number): int => (int) $number)->all();
        $promptNumber = $this->nextPromptNumber($usedNumbers, $maxPrompts);

        return $this->prompts->createForSlot(
            $user->id,
            $product->id,
            $promptNumber,
            $this->defaultPromptNameForProduct($productSlug, $promptNumber),
        );
    }

    public function updatePrompt(User $user, string $productSlug, int $promptId, string $name, string $content): Prompt
    {
        $product = $this->productBySlug($productSlug);
        $prompt = $this->prompts->findForUserAndProduct($promptId, $user->id, $product->id);

        $name = trim($name);
        $content = trim($content);

        if ($name === '') {
            throw new InvalidArgumentException('Ten prompt khong duoc de trong.');
        }

        if ($content === '') {
            throw new InvalidArgumentException('Noi dung prompt khong duoc de trong.');
        }

        return $this->prompts->updatePrompt($prompt, $name, $content);
    }

    public function defaultPromptName(int $promptNumber): string
    {
        return match ($promptNumber) {
            1 => 'Design',
            2 => 'Mockup1',
            3 => 'Mockup2',
            4 => 'Mockup3',
            default => 'Prompt '.$promptNumber,
        };
    }

    public function defaultPromptNameForProduct(string $productSlug, int $promptNumber): string
    {
        return in_array(strtolower($productSlug), self::SINGLE_CREATE_MASTER_PROMPT_PRODUCTS, true)
            && $promptNumber === 1
            ? 'Create Master'
            : $this->defaultPromptName($promptNumber);
    }

    public function usesSingleCreateMasterPrompt(string $productSlug): bool
    {
        return in_array(strtolower($productSlug), self::SINGLE_CREATE_MASTER_PROMPT_PRODUCTS, true);
    }

    public function maxPromptsForProduct(string $productSlug): int
    {
        $productSlug = strtolower($productSlug);

        return in_array($productSlug, self::SINGLE_CREATE_MASTER_PROMPT_PRODUCTS, true)
            ? 1
            : ($productSlug === 'ornament-amazon-2'
            ? self::ORNAMENT_AMAZON_TWO_MAX_PROMPTS
            : self::MAX_PROMPTS_PER_PRODUCT);
    }

    /**
     * @param array<int, int> $usedNumbers
     */
    private function nextPromptNumber(array $usedNumbers, int $maxPrompts): int
    {
        for ($number = 1; $number <= $maxPrompts; $number++) {
            if (! in_array($number, $usedNumbers, true)) {
                return $number;
            }
        }

        throw new RuntimeException("Trang nay da du {$maxPrompts} prompt.");
    }
}
