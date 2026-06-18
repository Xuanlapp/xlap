<?php

namespace App\Services\Marketplace;

use App\Models\ProductDesignAsset;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Services\Logging\ActivityLogService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class MarketplaceListingMetadataService
{
    private const AMAZON_PROMPT_TEMPLATE = <<<'PROMPT'
Ban hay dong vai mot chuyen gia viet content Amazon chuyen nghiep bang tieng Anh, chuyen toi uu title, bullet points, description theo dung chuan SEO cua Amazon, tranh tu bi cam, dam bao tang ty le chuyen doi va tuan thu chinh sach.

San pham cua toi la sticker.
Ten sticker / product keyword cua toi la: "{keyword}"
Product page: "{product}"

Hay viet cho toi noi dung Amazon listing bang tieng Anh theo cac yeu cau duoi day.
Return ONLY valid JSON. Do not include markdown, explanation, comments, or extra keys.

Required JSON schema, with exact keys:
{
  "title": "string",
  "description": "string",
  "bullet_point_1": "string",
  "bullet_point_2": "string",
  "bullet_point_3": "string",
  "bullet_point_4": "string",
  "bullet_point_5": "string",
  "generic_keyword": "string"
}

Yeu cau bat buoc:
- Title toi uu keyword, de doc, co do dai tu 180 den 195 ky tu tinh ca dau cach. Khong duoc vuot qua 200 ky tu tinh ca dau cach. Khong lap lai tu "stickers" qua 2 lan.
- Bullet Points gom 5 dong. Moi bullet point co do dai tu 460 den 480 ky tu tinh ca dau cach. Khong duoc vuot qua 480 ky tu tinh ca dau cach.
- Bullet point dau tien phai mo ta ve san pham cua toi.
- Moi bullet point phai bat dau bang mot icon phu hop.
- Generic Keyword phai gom ten sticker toi dua "{keyword}" va them khoang 5 den 8 keyword uu tien ben duoi, theo thu tu uu tien tu tren xuong duoi. Cac tu cach nhau bang dau cham phay ";". Generic Keyword khong duoc vuot qua 200 ky tu tinh ca dau cach. Neu gan dat 200 ky tu thi dung lai, khong them tu nua.
- Product Description co do dai tu 1800 den 1900 ky tu tinh ca dau cach. Khong duoc vuot qua 2000 ky tu tinh ca dau cach. Noi dung can tang tinh cam xuc va giai thich chi tiet.
- Dua theo keyword list ben duoi de toi uu SEO cho Bullet Points va Product Description. Chua toi da nhieu keyword co the, theo thu tu uu tien tu tren xuong duoi.
- Use natural US English.
- Keep claims safe, non-medical, non-offensive, and compliant with Amazon policy.
- Do not mention Amazon, Etsy, Midjourney, AI, prompts, competitor, or policy rules in the output.
- Do not include trademarked brands unless they are present in the product keyword.
- Khong duoc vuot qua so luong ky tu toi yeu cau. So luong ky tu tinh ca dau cach.

Priority SEO keyword list:
stickers for adults
water bottle stickers
stickers for water bottles
vinyl stickers
laptop stickers
waterproof stickers
waterproof stickers for water bottle
fun stickers
water bottle stickers for adults
vinyl stickers for water bottles
stickers waterproof
phone stickers
laptop stickers for women
computer stickers
stanley cup stickers
stickers for laptop
waterbottle stickers
water proof stickers for water bottles
phone case stickers
stickers for phone case
adult stickers uncensored
karol g stickers
luggage stickers for suitcases
fun stickers for adults
water bottle stickers waterproof
tumbler stickers
ipad stickers
assorted stickers
cup stickers for tumblers waterproof
tumbler stickers decals waterproof
water proof stickers
water bottle stickers for teens
decal stickers
owala stickers
stickers for water bottles adult
stickers for cups
computer stickers for laptop
stickers for ipad case
cup stickers
waterbottle stickers for adults
water bottle sticker
teen stickers
waterproof stickers for water bottles
laptop decals
stanley stickers waterproof
suitcase stickers
sticker for water bottle
pack of stickers
sticker bomb pack
sticker set
stickers adult
waterproof vinyl stickers
bottle stickers
dishwasher safe stickers
vinyl stickers for adults
macbook stickers for laptop
waterproof sticker
vinyl stickers waterproof
waterproof stickers for kids
PROMPT;

    private const ETSY_PROMPT_TEMPLATE = <<<'PROMPT'
You are an expert Etsy SEO listing copywriter.

Create marketplace metadata for the product keyword below.
Return ONLY valid JSON. Do not include markdown.

Keyword: "{keyword}"
Product page: "{product}"

Required JSON schema:
{
  "title": "Etsy SEO title, max 140 characters",
  "description": "Friendly Etsy product description, 1-2 paragraphs",
  "tags": "13 Etsy tags, comma-separated, each tag 20 characters or less"
}

Rules:
- Use natural US English.
- Focus on buyer intent, giftability, style, and product use.
- Do not mention Amazon, Midjourney, AI, or prompts.
- Do not include trademarked brands unless they are present in the keyword.
PROMPT;

    public function __construct(
        private readonly VertexImageGenerator $generator,
        private readonly ProductDesignAssetRepository $assets,
    ) {}

    /**
     * Generate and persist listing metadata for the approved asset based on the owner's marketplace access.
     */
    public function generateForApprovedAsset(int $assetId): ?ProductDesignAsset
    {
        $asset = ProductDesignAsset::query()
            ->with(['user', 'product'])
            ->findOrFail($assetId);

        if (! $asset->is_approved) {
            return null;
        }

        if ($asset->user->can_generate_amazon_listing) {
            return $this->assets->markListingCompleted($this->generateAmazonMetadata($asset), 'amazon');
        }

        if ($asset->user->can_generate_etsy_listing) {
            return $this->assets->markListingCompleted($this->generateEtsyMetadata($asset), 'etsy');
        }

        return null;
    }

    public function retryApprovedAsset(int $assetId): ?ProductDesignAsset
    {
        $asset = ProductDesignAsset::query()
            ->with(['user', 'product'])
            ->findOrFail($assetId);

        if (! $asset->is_approved) {
            throw new RuntimeException('Item nay chua duyet nen khong the tao listing metadata.');
        }

        if ($asset->title) {
            return $asset;
        }

        $processing = $this->assets->markListingProcessing($asset, $this->marketplaceForAsset($asset));

        try {
            return $this->generateForApprovedAsset($processing->id);
        } catch (RuntimeException $exception) {
            $this->assets->markListingFailed($processing, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * Generate listing metadata for approved assets that do not have a title yet.
     */
    public function generatePendingApprovedAssets(int $limit = 0, int $delaySeconds = 0): int
    {
        $processed = 0;
        $claimed = 0;

        while ($limit <= 0 || $claimed < $limit) {
            $asset = $this->claimNextPendingApprovedAsset();

            if (! $asset) {
                break;
            }

            $claimed++;

            try {
                if ($this->generateForApprovedAsset($asset->id)) {
                    $processed++;
                }
            } catch (RuntimeException $exception) {
                $this->assets->markListingFailed($asset, $exception->getMessage());
                Log::warning('Marketplace listing metadata generation failed.', [
                    'asset_id' => $asset->id,
                    'user_id' => $asset->user_id,
                    'message' => $exception->getMessage(),
                ]);
            }

            if ($delaySeconds > 0 && ($limit <= 0 || $claimed < $limit)) {
                sleep($delaySeconds);
            }
        }

        return $processed;
    }

    private function claimNextPendingApprovedAsset(): ?ProductDesignAsset
    {
        return DB::transaction(function (): ?ProductDesignAsset {
            $asset = ProductDesignAsset::query()
                ->with(['user', 'product'])
                ->where('is_approved', true)
                ->whereNull('title')
                ->where(function ($query): void {
                    $query
                        ->whereNull('marketplace_listing_status')
                        ->orWhere('marketplace_listing_status', 'waiting')
                        ->orWhere('marketplace_listing_status', 'failed')
                        ->orWhere(function ($query): void {
                            $query
                                ->where('marketplace_listing_status', 'processing')
                                ->where(function ($query): void {
                                    $query
                                        ->whereNull('marketplace_listing_started_at')
                                        ->orWhere('marketplace_listing_started_at', '<=', $this->staleProcessingCutoff());
                                });
                        });
                })
                ->whereHas('user', function ($query): void {
                    $query
                        ->where('can_generate_amazon_listing', true)
                        ->orWhere('can_generate_etsy_listing', true);
                })
                ->orderBy('approved_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $asset) {
                return null;
            }

            return $this->assets->markListingProcessing($asset, $this->marketplaceForAsset($asset));
        });
    }

    private function staleProcessingCutoff(): \DateTimeInterface
    {
        $minutes = max(1, (int) config('services.marketplace_listing.stale_processing_minutes', 10));

        return now()->subMinutes($minutes);
    }

    private function generateAmazonMetadata(ProductDesignAsset $asset): ProductDesignAsset
    {
        $payload = $this->jsonPayload(
            $this->generator->generateText($asset->user, $this->prompt(self::AMAZON_PROMPT_TEMPLATE, $asset)),
        );

        $updatedAsset = $this->assets->updateListingMetadata($asset, [
            'title' => $this->stringValue($payload, 'title', 255),
            'description' => $this->stringValue($payload, 'description'),
            'bullet_point_1' => $this->stringValue($payload, 'bullet_point_1'),
            'bullet_point_2' => $this->stringValue($payload, 'bullet_point_2'),
            'bullet_point_3' => $this->stringValue($payload, 'bullet_point_3'),
            'bullet_point_4' => $this->stringValue($payload, 'bullet_point_4'),
            'bullet_point_5' => $this->stringValue($payload, 'bullet_point_5'),
            'generic_keyword' => $this->stringValue($payload, 'generic_keyword', 255),
            'tags' => null,
        ]);

        $this->logGenerated($updatedAsset, 'amazon');

        return $updatedAsset;
    }

    private function generateEtsyMetadata(ProductDesignAsset $asset): ProductDesignAsset
    {
        $payload = $this->jsonPayload(
            $this->generator->generateText($asset->user, $this->prompt(self::ETSY_PROMPT_TEMPLATE, $asset)),
        );

        $updatedAsset = $this->assets->updateListingMetadata($asset, [
            'title' => $this->stringValue($payload, 'title', 255),
            'description' => $this->stringValue($payload, 'description'),
            'bullet_point_1' => null,
            'bullet_point_2' => null,
            'bullet_point_3' => null,
            'bullet_point_4' => null,
            'bullet_point_5' => null,
            'generic_keyword' => null,
            'tags' => $this->stringValue($payload, 'tags'),
        ]);

        $this->logGenerated($updatedAsset, 'etsy');

        return $updatedAsset;
    }

    private function marketplaceForAsset(ProductDesignAsset $asset): string
    {
        return $asset->user->can_generate_amazon_listing ? 'amazon' : 'etsy';
    }

    private function prompt(string $template, ProductDesignAsset $asset): string
    {
        return strtr($template, [
            '{keyword}' => $asset->keyword,
            '{product}' => $asset->product?->name ?? 'Product',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        try {
            $payload = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Vertex khong tra ve JSON listing hop le.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Vertex khong tra ve JSON listing hop le.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, ?int $maxLength = null): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }

    private function logGenerated(ProductDesignAsset $asset, string $marketplace): void
    {
        app(ActivityLogService::class)->record(
            event: "marketplace_listing.{$marketplace}_generated",
            description: "Generated {$marketplace} listing metadata for approved asset.",
            subject: $asset,
            properties: [
                'item_number' => $asset->item_number,
                'keyword' => $asset->keyword,
            ],
        );
    }
}
