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
Ban hay dong vai mot chuyen gia viet content Amazon chuyen nghiep bang tieng Anh, chuyen toi uu Amazon SEO, Title, Bullet Points, Generic Keywords va Product Description. Muc tieu la viet noi dung de doc, tu nhien nhu nguoi ban xu, tang ty le chuyen doi, tranh keyword stuffing, tranh tu bi cam, tranh claim qua da, tranh dung ten thuong hieu doi thu, va tuan thu chinh sach Amazon.

San pham cua toi la: {amazon_product_from_sheet}

Link doi thu de tham khao cau truc, keyword, cach trinh bay va insight khach hang:
[LINK DOI THU : {competitor_link}]

Danh sach keyword uu tien cua toi, hay dung theo thu tu uu tien tu tren xuong duoi. Keyword nao o tren thi uu tien dua vao Title, Bullet Points, Generic Keywords va Product Description truoc. Hay dung toi da nhieu keyword nhat co the nhung phai tu nhien, khong spam, khong lap qua muc:
[KEYWORDS: {keyword_phrase}]

YEU CAU DAU RA:
Return ONLY valid JSON. Do not include markdown, explanation, comments, character-count labels, safety-check text, or extra keys.

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


YÊU CẦU ĐẦU RA:

TITLE AMAZON

Viết 1 Title bằng tiếng Anh, tối ưu keyword, dễ đọc, tự nhiên, phù hợp Amazon US.

Yêu cầu bắt buộc:

Độ dài Title nằm trong khoảng 180–195 ký tự tính cả dấu cách.
Không được vượt quá 200 ký tự bao gồm cả dấu cách.
Ưu tiên keyword chính ở đầu Title.
Không nhồi keyword quá lộ.
Không dùng ALL CAPS.
Không dùng ký tự đặc biệt không cần thiết.
Không dùng claim như Best, #1, Guaranteed, Official, Luxury nếu không có căn cứ.
Không dùng tên thương hiệu đối thủ.
Title phải mô tả rõ loại sản phẩm, điểm cá nhân hóa, đối tượng tặng quà và dịp sử dụng.

Sau Title, ghi rõ:
TITLE — [SỐ KÝ TỰ] CHARACTERS

BULLET POINTS AMAZON

Viết 5 Bullet Points bằng tiếng Anh.

Yêu cầu bắt buộc:

Mỗi bullet point phải có độ dài từ 460–480 ký tự tính cả dấu cách.
Không bullet nào được vượt quá 480 ký tự.
Mỗi bullet có 1 icon phù hợp ở đầu dòng.
Bullet point đầu tiên phải mô tả trực tiếp sản phẩm của tôi: sản phẩm là gì, dùng để làm gì, điểm cá nhân hóa chính.
Các bullet còn lại phải tập trung vào lợi ích, tính năng, quà tặng, dịp sử dụng, cảm xúc, cách cá nhân hóa và giá trị lưu giữ kỷ niệm.
Dùng keyword theo thứ tự ưu tiên từ danh sách tôi đưa.
Keyword phải được đưa vào tự nhiên, không spam.
Nội dung phải phù hợp với khách hàng Amazon US.
Không dùng câu cam kết tuyệt đối như “will last forever”, “guaranteed to make them happy”, “best quality”.
Không dùng từ bị cấm hoặc claim y tế, tôn giáo, chính trị, phân biệt đối tượng nếu không liên quan.
Không dùng tên brand đối thủ hoặc trademark của người khác.

Sau mỗi bullet, ghi rõ:
BULLET POINT 1 — [SỐ KÝ TỰ] CHARACTERS
BULLET POINT 2 — [SỐ KÝ TỰ] CHARACTERS
BULLET POINT 3 — [SỐ KÝ TỰ] CHARACTERS
BULLET POINT 4 — [SỐ KÝ TỰ] CHARACTERS
BULLET POINT 5 — [SỐ KÝ TỰ] CHARACTERS

GENERIC KEYWORDS

Viết Generic Keywords theo đúng danh sách keyword tôi đưa, ưu tiên từ trên xuống dưới.

Yêu cầu bắt buộc:

Các từ khóa cách nhau bằng dấu “;”
Độ dài Generic Keywords nằm trong khoảng 230–240 ký tự tính cả dấu cách thì dừng lại.
Không được vượt quá 240 ký tự bao gồm cả dấu cách.
Không thêm keyword nếu vượt giới hạn.
Không dùng tên thương hiệu đối thủ.
Không dùng ASIN.
Không dùng từ sai chính tả nếu làm listing thiếu chuyên nghiệp.
Không lặp lại một từ khóa quá nhiều nếu không cần thiết.
Ưu tiên keyword có volume/search intent cao hơn.

Sau phần Generic Keywords, ghi rõ:
GENERIC KEYWORDS — [SỐ KÝ TỰ] CHARACTERS

PRODUCT DESCRIPTION

Viết Product Description bằng tiếng Anh.

Yêu cầu bắt buộc:

Độ dài nằm trong khoảng 1800–1900 ký tự tính cả dấu cách.
Không được vượt quá 2000 ký tự bao gồm cả dấu cách.
Nội dung phải giàu cảm xúc, tự nhiên, dễ đọc, tăng chuyển đổi.
Giải thích rõ sản phẩm là gì, dùng như thế nào, cá nhân hóa ra sao, phù hợp tặng ai, phù hợp dịp nào.
Đưa nhiều keyword nhất có thể theo thứ tự ưu tiên từ danh sách tôi cung cấp, nhưng phải tự nhiên.
Không lặp keyword quá dày.
Không claim quá đà.
Không dùng tên brand đối thủ.
Không viết thông tin sai về chất liệu, kích thước, quy trình sản xuất nếu tôi chưa cung cấp.
Nếu thông tin sản phẩm chưa rõ, hãy viết theo hướng an toàn, không khẳng định quá cụ thể.
Tập trung vào cảm xúc: family memories, holiday tradition, meaningful keepsake, personalized gift, Christmas tree decor, loved ones, special moments.

Sau phần Product Description, ghi rõ:
PRODUCT DESCRIPTION — [SỐ KÝ TỰ] CHARACTERS

KIỂM TRA CUỐI CÙNG

Trước khi trả kết quả, hãy tự kiểm tra:

Title có nằm trong 180–195 ký tự không?
Title có vượt 200 ký tự không?
Mỗi bullet có nằm trong 460–480 ký tự không?
Có bullet nào vượt 480 ký tự không?
Generic Keywords có nằm trong 230–240 ký tự không?
Generic Keywords có vượt 240 ký tự không?
Product Description có nằm trong 1800–1900 ký tự không?
Product Description có vượt 2000 ký tự không?
Có dùng tên thương hiệu đối thủ không?
Có dùng claim quá đà hoặc từ có rủi ro chính sách không?
Keyword có được dùng tự nhiên không?
Nội dung có phù hợp khách hàng Amazon US không?

ĐỊNH DẠNG TRẢ KẾT QUẢ:

Trả kết quả theo đúng format sau, không giải thích dài dòng:

TITLE — [SỐ KÝ TỰ] CHARACTERS

[Title hoàn chỉnh]

BULLET POINT 1 — [SỐ KÝ TỰ] CHARACTERS

[Bullet 1]

BULLET POINT 2 — [SỐ KÝ TỰ] CHARACTERS

[Bullet 2]

BULLET POINT 3 — [SỐ KÝ TỰ] CHARACTERS

[Bullet 3]

BULLET POINT 4 — [SỐ KÝ TỰ] CHARACTERS

[Bullet 4]

BULLET POINT 5 — [SỐ KÝ TỰ] CHARACTERS

[Bullet 5]

GENERIC KEYWORDS — [SỐ KÝ TỰ] CHARACTERS

[Generic Keywords]

PRODUCT DESCRIPTION — [SỐ KÝ TỰ] CHARACTERS

[Product Description]

SAFETY CHECK

Brand/trademark risk: Passed / Needs Review
Amazon policy risk: Passed / Needs Review
Keyword stuffing risk: Low / Medium / High
Readability: Good / Needs Improvement
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
            '{amazon_product_from_sheet}' => $this->amazonProductFromSheet($asset),
            '{product}' => $asset->product?->name ?? 'Product',
            '{competitor_link}' => $this->competitorLink($asset),
            '{keyword_phrase}' => $this->keywordPhrase($asset),
        ]);
    }

    private function competitorLink(ProductDesignAsset $asset): string
    {
        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $link = $sourceData['competitor_link'] ?? $sourceData['product_link'] ?? $sourceData['link'] ?? '';

        return is_string($link) && trim($link) !== '' ? trim($link) : 'N/A';
    }

    private function keywordPhrase(ProductDesignAsset $asset): string
    {
        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $keywordPhrase = $sourceData['keyword_phrase'] ?? '';

        return is_string($keywordPhrase) && trim($keywordPhrase) !== '' ? trim($keywordPhrase) : $asset->keyword;
    }

    private function amazonProductFromSheet(ProductDesignAsset $asset): string
    {
        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $product = $sourceData['product'] ?? '';

        return is_string($product) && trim($product) !== '' ? trim($product) : ($asset->product?->name ?? $asset->keyword);
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
