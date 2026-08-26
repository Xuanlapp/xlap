<?php

namespace App\Services\Glass;

use App\Models\Product;
use App\Models\PsdMockupTemplate;
use App\Models\User;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\PsdMockupTemplateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PsdMockupTemplateService
{
    public const GLASS_CUSTOM_MOCKUP = 'glass_custom_mockup';

    public function __construct(
        private readonly ProductRepository $products,
        private readonly PsdMockupTemplateRepository $templates,
    ) {}

    /**
     * @return Collection<int, PsdMockupTemplate>
     */
    public function glassTemplatesForUser(User $user): Collection
    {
        return $this->templates->forUserProductAndFunction(
            $user->id,
            $this->glassProduct()->id,
            self::GLASS_CUSTOM_MOCKUP,
        );
    }

    public function activeGlassTemplateForUser(User $user): ?PsdMockupTemplate
    {
        return $this->templates->activeForUserProductAndFunction(
            $user->id,
            $this->glassProduct()->id,
            self::GLASS_CUSTOM_MOCKUP,
        );
    }

    public function uploadGlassTemplate(User $user, UploadedFile $file, ?string $name = null): PsdMockupTemplate
    {
        $this->ensurePsdFile($file);

        $product = $this->glassProduct();
        $filename = Str::uuid().'.psd';
        $path = $file->storeAs("psd-mockups/{$user->id}/glass", $filename, 'public');

        return $this->templates->createActive(
            $user->id,
            $product->id,
            self::GLASS_CUSTOM_MOCKUP,
            $this->normalizeName($name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            $file->getClientOriginalName(),
            $path,
        );
    }

    public function activateGlassTemplate(User $user, int $templateId): PsdMockupTemplate
    {
        $template = $this->templates->findForUserProductAndFunction(
            $templateId,
            $user->id,
            $this->glassProduct()->id,
            self::GLASS_CUSTOM_MOCKUP,
        );

        return $this->templates->activate($template);
    }

    private function glassProduct(): Product
    {
        return $this->products->findActiveBySlug('glass');
    }

    private function ensurePsdFile(UploadedFile $file): void
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'psd') {
            throw new InvalidArgumentException('File mockup phai la PSD.');
        }
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Ten PSD khong duoc de trong.');
        }

        return Str::limit($name, 255, '');
    }
}
