<?php

namespace App\Livewire\Modals\Prompt;

use App\Models\Prompt;
use App\Services\Prompt\PromptService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class DetailPrompt extends Component
{
    public bool $isOpen = false;

    public string $productSlug = '';

    public string $productName = '';

    public ?int $selectedPromptId = null;

    public string $name = '';

    public string $content = '';

    /**
     * Open this modal through the shared modal event used by product pages.
     *
     * Expected arguments:
     * - productSlug: products.slug for the current page.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.prompt.detail-prompt') {
            return;
        }

        $productSlug = (string) ($arguments['productSlug'] ?? '');

        if ($productSlug === '') {
            return;
        }

        $this->open($productSlug);
    }

    public function open(string $productSlug): void
    {
        $this->resetValidation();

        $service = app(PromptService::class);
        $product = $service->productBySlug($productSlug);

        $this->productSlug = $product->slug;
        $this->productName = $product->name;
        $this->isOpen = true;

        $this->selectFirstPrompt();
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset([
            'isOpen',
            'productSlug',
            'productName',
            'selectedPromptId',
            'name',
            'content',
        ]);
    }

    public function addPrompt(): void
    {
        try {
            $prompt = app(PromptService::class)->createNextPrompt(auth()->user(), $this->productSlug);

            $this->selectPrompt($prompt->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them prompt moi.');
        } catch (Throwable $exception) {
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function selectPrompt(int $promptId): void
    {
        $prompt = $this->prompts()->firstWhere('id', $promptId);

        if (! $prompt) {
            return;
        }

        $this->resetValidation();
        $this->selectedPromptId = $prompt->id;
        $this->name = (string) ($prompt->name ?: app(PromptService::class)->defaultPromptName((int) $prompt->prompt_number));
        $this->content = (string) $prompt->content;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        if (! $this->selectedPromptId) {
            return;
        }

        try {
            $prompt = app(PromptService::class)->updatePrompt(
                auth()->user(),
                $this->productSlug,
                $this->selectedPromptId,
                $validated['name'],
                $validated['content'],
            );

            $this->selectPrompt($prompt->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu prompt.');
        } catch (Throwable $exception) {
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        $prompts = $this->isOpen ? $this->prompts() : new Collection();
        $maxPrompts = $this->isOpen
            ? app(PromptService::class)->maxPromptsForProduct($this->productSlug)
            : PromptService::MAX_PROMPTS_PER_PRODUCT;

        return view('livewire.modals.prompt.detail-prompt', [
            'prompts' => $prompts,
            'promptLabels' => $this->promptLabels($prompts),
            'canAddPrompt' => $this->isOpen && $prompts->count() < $maxPrompts,
            'maxPrompts' => $maxPrompts,
        ]);
    }

    /**
     * @return Collection<int, Prompt>
     */
    private function prompts(): Collection
    {
        if ($this->productSlug === '') {
            return new Collection();
        }

        return app(PromptService::class)->promptsForUserProduct(auth()->user(), $this->productSlug);
    }

    private function selectFirstPrompt(): void
    {
        $prompt = $this->prompts()->first();

        if ($prompt) {
            $this->selectPrompt($prompt->id);

            return;
        }

        $this->selectedPromptId = null;
        $this->name = '';
        $this->content = '';
    }

    /**
     * @param Collection<int, Prompt> $prompts
     * @return array<int, string>
     */
    private function promptLabels(Collection $prompts): array
    {
        $service = app(PromptService::class);

        return $prompts
            ->mapWithKeys(fn (Prompt $prompt): array => [
                $prompt->id => $prompt->name ?: $service->defaultPromptName((int) $prompt->prompt_number),
            ])
            ->all();
    }
}
