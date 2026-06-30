<?php

namespace App\Services\Marketplace;

use App\Models\ProductDesignAsset;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Services\Ai\ApiKeyImageGenerator;
use App\Services\Logging\ActivityLogService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use JsonException;
use RuntimeException;
use Throwable;

class MarketplaceListingMetadataService
{
    private const AMAZON_PROMPT_TEMPLATE = <<<'PROMPT'
Ban hay dong vai mot chuyen gia viet content Amazon chuyen nghiep bang tieng Anh, chuyen toi uu Amazon SEO, Title, Bullet Points, Generic Keywords va Product Description. Muc tieu la viet noi dung de doc, tu nhien nhu nguoi ban xu, tang ty le chuyen doi, tranh keyword stuffing, tranh tu bi cam, tranh claim qua da, tranh dung ten thuong hieu doi thu, va tuan thu chinh sach Amazon.

San pham cua toi la: {amazon_product_from_sheet}

Link doi thu de tham khao cau truc, keyword, cach trinh bay va insight khach hang:
[LINK DOI THU : {competitor_link}]

Danh sach keyword uu tien cua toi, hay dung theo thu tu uu tien tu tren xuong duoi. Keyword nao o tren thi uu tien dua vao Title, Bullet Points, Generic Keywords va Product Description truoc. Hay dung toi da nhieu keyword nhat co the nhung phai tu nhien, khong spam, khong lap qua muc:
[KEYWORDS: {keyword_phrase}]


YÃŠU Cáº¦U Äáº¦U RA:

TITLE AMAZON

Viáº¿t 1 Title báº±ng tiáº¿ng Anh, tá»‘i Æ°u keyword, dá»… Ä‘á»c, tá»± nhiÃªn, phÃ¹ há»£p Amazon US.

YÃªu cáº§u báº¯t buá»™c:

Äá»™ dÃ i Title náº±m trong khoáº£ng 180â€“195 kÃ½ tá»± tÃ­nh cáº£ dáº¥u cÃ¡ch.
KhÃ´ng Ä‘Æ°á»£c vÆ°á»£t quÃ¡ 200 kÃ½ tá»± bao gá»“m cáº£ dáº¥u cÃ¡ch.
Æ¯u tiÃªn keyword chÃ­nh á»Ÿ Ä‘áº§u Title.
KhÃ´ng nhá»“i keyword quÃ¡ lá»™.
KhÃ´ng dÃ¹ng ALL CAPS.
KhÃ´ng dÃ¹ng kÃ½ tá»± Ä‘áº·c biá»‡t khÃ´ng cáº§n thiáº¿t.
KhÃ´ng dÃ¹ng claim nhÆ° Best, #1, Guaranteed, Official, Luxury náº¿u khÃ´ng cÃ³ cÄƒn cá»©.
KhÃ´ng dÃ¹ng tÃªn thÆ°Æ¡ng hiá»‡u Ä‘á»‘i thá»§.
Title pháº£i mÃ´ táº£ rÃµ loáº¡i sáº£n pháº©m, Ä‘iá»ƒm cÃ¡ nhÃ¢n hÃ³a, Ä‘á»‘i tÆ°á»£ng táº·ng quÃ  vÃ  dá»‹p sá»­ dá»¥ng.

Sau Title, ghi rÃµ:
TITLE â€” [Sá» KÃ Tá»°] CHARACTERS

BULLET POINTS AMAZON

Viáº¿t 5 Bullet Points báº±ng tiáº¿ng Anh.

YÃªu cáº§u báº¯t buá»™c:

Má»—i bullet point pháº£i cÃ³ Ä‘á»™ dÃ i tá»« 460â€“480 kÃ½ tá»± tÃ­nh cáº£ dáº¥u cÃ¡ch.
KhÃ´ng bullet nÃ o Ä‘Æ°á»£c vÆ°á»£t quÃ¡ 480 kÃ½ tá»±.
Má»—i bullet cÃ³ 1 icon phÃ¹ há»£p á»Ÿ Ä‘áº§u dÃ²ng.
Bullet point Ä‘áº§u tiÃªn pháº£i mÃ´ táº£ trá»±c tiáº¿p sáº£n pháº©m cá»§a tÃ´i: sáº£n pháº©m lÃ  gÃ¬, dÃ¹ng Ä‘á»ƒ lÃ m gÃ¬, Ä‘iá»ƒm cÃ¡ nhÃ¢n hÃ³a chÃ­nh.
CÃ¡c bullet cÃ²n láº¡i pháº£i táº­p trung vÃ o lá»£i Ã­ch, tÃ­nh nÄƒng, quÃ  táº·ng, dá»‹p sá»­ dá»¥ng, cáº£m xÃºc, cÃ¡ch cÃ¡ nhÃ¢n hÃ³a vÃ  giÃ¡ trá»‹ lÆ°u giá»¯ ká»· niá»‡m.
DÃ¹ng keyword theo thá»© tá»± Æ°u tiÃªn tá»« danh sÃ¡ch tÃ´i Ä‘Æ°a.
Keyword pháº£i Ä‘Æ°á»£c Ä‘Æ°a vÃ o tá»± nhiÃªn, khÃ´ng spam.
Ná»™i dung pháº£i phÃ¹ há»£p vá»›i khÃ¡ch hÃ ng Amazon US.
KhÃ´ng dÃ¹ng cÃ¢u cam káº¿t tuyá»‡t Ä‘á»‘i nhÆ° â€œwill last foreverâ€, â€œguaranteed to make them happyâ€, â€œbest qualityâ€.
KhÃ´ng dÃ¹ng tá»« bá»‹ cáº¥m hoáº·c claim y táº¿, tÃ´n giÃ¡o, chÃ­nh trá»‹, phÃ¢n biá»‡t Ä‘á»‘i tÆ°á»£ng náº¿u khÃ´ng liÃªn quan.
KhÃ´ng dÃ¹ng tÃªn brand Ä‘á»‘i thá»§ hoáº·c trademark cá»§a ngÆ°á»i khÃ¡c.

Sau má»—i bullet, ghi rÃµ:
BULLET POINT 1 â€” [Sá» KÃ Tá»°] CHARACTERS
BULLET POINT 2 â€” [Sá» KÃ Tá»°] CHARACTERS
BULLET POINT 3 â€” [Sá» KÃ Tá»°] CHARACTERS
BULLET POINT 4 â€” [Sá» KÃ Tá»°] CHARACTERS
BULLET POINT 5 â€” [Sá» KÃ Tá»°] CHARACTERS

GENERIC KEYWORDS

Viáº¿t Generic Keywords theo Ä‘Ãºng danh sÃ¡ch keyword tÃ´i Ä‘Æ°a, Æ°u tiÃªn tá»« trÃªn xuá»‘ng dÆ°á»›i.

YÃªu cáº§u báº¯t buá»™c:

CÃ¡c tá»« khÃ³a cÃ¡ch nhau báº±ng dáº¥u â€œ;â€
Äá»™ dÃ i Generic Keywords náº±m trong khoáº£ng 230â€“240 kÃ½ tá»± tÃ­nh cáº£ dáº¥u cÃ¡ch thÃ¬ dá»«ng láº¡i.
KhÃ´ng Ä‘Æ°á»£c vÆ°á»£t quÃ¡ 240 kÃ½ tá»± bao gá»“m cáº£ dáº¥u cÃ¡ch.
KhÃ´ng thÃªm keyword náº¿u vÆ°á»£t giá»›i háº¡n.
KhÃ´ng dÃ¹ng tÃªn thÆ°Æ¡ng hiá»‡u Ä‘á»‘i thá»§.
KhÃ´ng dÃ¹ng ASIN.
KhÃ´ng dÃ¹ng tá»« sai chÃ­nh táº£ náº¿u lÃ m listing thiáº¿u chuyÃªn nghiá»‡p.
KhÃ´ng láº·p láº¡i má»™t tá»« khÃ³a quÃ¡ nhiá»u náº¿u khÃ´ng cáº§n thiáº¿t.
Æ¯u tiÃªn keyword cÃ³ volume/search intent cao hÆ¡n.

Sau pháº§n Generic Keywords, ghi rÃµ:
GENERIC KEYWORDS â€” [Sá» KÃ Tá»°] CHARACTERS

PRODUCT DESCRIPTION

Viáº¿t Product Description báº±ng tiáº¿ng Anh.

YÃªu cáº§u báº¯t buá»™c:

Äá»™ dÃ i náº±m trong khoáº£ng 1800â€“1900 kÃ½ tá»± tÃ­nh cáº£ dáº¥u cÃ¡ch.
KhÃ´ng Ä‘Æ°á»£c vÆ°á»£t quÃ¡ 2000 kÃ½ tá»± bao gá»“m cáº£ dáº¥u cÃ¡ch.
Ná»™i dung pháº£i giÃ u cáº£m xÃºc, tá»± nhiÃªn, dá»… Ä‘á»c, tÄƒng chuyá»ƒn Ä‘á»•i.
Giáº£i thÃ­ch rÃµ sáº£n pháº©m lÃ  gÃ¬, dÃ¹ng nhÆ° tháº¿ nÃ o, cÃ¡ nhÃ¢n hÃ³a ra sao, phÃ¹ há»£p táº·ng ai, phÃ¹ há»£p dá»‹p nÃ o.
ÄÆ°a nhiá»u keyword nháº¥t cÃ³ thá»ƒ theo thá»© tá»± Æ°u tiÃªn tá»« danh sÃ¡ch tÃ´i cung cáº¥p, nhÆ°ng pháº£i tá»± nhiÃªn.
KhÃ´ng láº·p keyword quÃ¡ dÃ y.
KhÃ´ng claim quÃ¡ Ä‘Ã .
KhÃ´ng dÃ¹ng tÃªn brand Ä‘á»‘i thá»§.
KhÃ´ng viáº¿t thÃ´ng tin sai vá» cháº¥t liá»‡u, kÃ­ch thÆ°á»›c, quy trÃ¬nh sáº£n xuáº¥t náº¿u tÃ´i chÆ°a cung cáº¥p.
Náº¿u thÃ´ng tin sáº£n pháº©m chÆ°a rÃµ, hÃ£y viáº¿t theo hÆ°á»›ng an toÃ n, khÃ´ng kháº³ng Ä‘á»‹nh quÃ¡ cá»¥ thá»ƒ.
Táº­p trung vÃ o cáº£m xÃºc: family memories, holiday tradition, meaningful keepsake, personalized gift, Christmas tree decor, loved ones, special moments.

Sau pháº§n Product Description, ghi rÃµ:
PRODUCT DESCRIPTION â€” [Sá» KÃ Tá»°] CHARACTERS

KIá»‚M TRA CUá»I CÃ™NG

TrÆ°á»›c khi tráº£ káº¿t quáº£, hÃ£y tá»± kiá»ƒm tra:

Title cÃ³ náº±m trong 180â€“195 kÃ½ tá»± khÃ´ng?
Title cÃ³ vÆ°á»£t 200 kÃ½ tá»± khÃ´ng?
Má»—i bullet cÃ³ náº±m trong 460â€“480 kÃ½ tá»± khÃ´ng?
CÃ³ bullet nÃ o vÆ°á»£t 480 kÃ½ tá»± khÃ´ng?
Generic Keywords cÃ³ náº±m trong 230â€“240 kÃ½ tá»± khÃ´ng?
Generic Keywords cÃ³ vÆ°á»£t 240 kÃ½ tá»± khÃ´ng?
Product Description cÃ³ náº±m trong 1800â€“1900 kÃ½ tá»± khÃ´ng?
Product Description cÃ³ vÆ°á»£t 2000 kÃ½ tá»± khÃ´ng?
CÃ³ dÃ¹ng tÃªn thÆ°Æ¡ng hiá»‡u Ä‘á»‘i thá»§ khÃ´ng?
CÃ³ dÃ¹ng claim quÃ¡ Ä‘Ã  hoáº·c tá»« cÃ³ rá»§i ro chÃ­nh sÃ¡ch khÃ´ng?
Keyword cÃ³ Ä‘Æ°á»£c dÃ¹ng tá»± nhiÃªn khÃ´ng?
Ná»™i dung cÃ³ phÃ¹ há»£p khÃ¡ch hÃ ng Amazon US khÃ´ng?

Äá»ŠNH Dáº NG TRáº¢ Káº¾T QUáº¢:

Tráº£ káº¿t quáº£ theo Ä‘Ãºng format sau, khÃ´ng giáº£i thÃ­ch dÃ i dÃ²ng:

TITLE â€” [Sá» KÃ Tá»°] CHARACTERS

[Title hoÃ n chá»‰nh]

BULLET POINT 1 â€” [Sá» KÃ Tá»°] CHARACTERS

[Bullet 1]

BULLET POINT 2 â€” [Sá» KÃ Tá»°] CHARACTERS

[Bullet 2]

BULLET POINT 3 â€” [Sá» KÃ Tá»°] CHARACTERS

[Bullet 3]

BULLET POINT 4 â€” [Sá» KÃ Tá»°] CHARACTERS

[Bullet 4]

BULLET POINT 5 â€” [Sá» KÃ Tá»°] CHARACTERS

[Bullet 5]

GENERIC KEYWORDS â€” [Sá» KÃ Tá»°] CHARACTERS

[Generic Keywords]

PRODUCT DESCRIPTION â€” [Sá» KÃ Tá»°] CHARACTERS

[Product Description]

SAFETY CHECK

Brand/trademark risk: Passed / Needs Review
Amazon policy risk: Passed / Needs Review
Keyword stuffing risk: Low / Medium / High
Readability: Good / Needs Improvement
PROMPT;

    private const AMAZON_STICKER_PROMPT_TEMPLATE = <<<'PROMPT'
Ban hay dong vai dong vai mot chuyen gia viet content Amazon chuyen nghiep bang tieng anh, chuyen toi uu title, bullet points, description theo dung chuan SEO cua Amazon, tranh tu bi cam, dam bao tang ty le chuyen doi va tuan thu chinh sach.
San pham cua toi la: {amazon_product_from_sheet}
LINK DOI THU : {competitor_link}
KEYWORDS: {keyword_phrase}

Ban hay viet cho toi:
Title toi uu keyword, de doc, tuan thu do dai Amazon o cuoi tieu de co ( 3PCS,3â€) ( co do dai nam trong khoang 180-195 ky tu tinh ca dau cach, khong duoc vuot qua 200 ky tu bao gom ca dau cach, khong duoc lap lai tu stickers qua 2 lan )
Bullet Points (5 dong) ( moi bullet points phai co do dai nam trong khoang 460 den 480 ky tu tinh ca dau cach, khong duoc vuot qua 480 ky tu bao gom ca dau cach - mo ta loi ich va tinh nang san pham
+ Bullet point dau mo ta ve san pham cua toi
+ Co cac icon phu hop o dau cac bullet point
Generic Keyword : Ten sticker toi dua va khoang 5 den 8 tu ben duoi toi dua , theo thu tu uu tien tu tren xuong duoi cac tu cach nhau boi dau ; (Neu Generic Keyword co do dai nam trong khoang 230-240 ky tu tinh ca dau cach thi dung lai khong them cac tu o duoi nua ,Generic Keyword khong duoc vuot qua 240 ky tu bao gom ca dau cach)
Product Description ( co do dai nam trong khoang 1800 den 1900 ky tu tinh ca dau cach, khong duoc vuot qua 2000 ky tu bao gom ca dau cach ) - tang tinh cam xuc & giai thich chi tiet
Chu y so luong ky tu khong duoc vuot qua yeu cau cua toi, va so luong ky tu bao gom ca dau cach

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

Luu y:
- Dung dung san pham la sticker, khong viet theo kieu ornament.
- Khong duoc vuot qua so luong ky tu toi yeu cau, va so luong ky tu tinh ca dau cach.
- Su dung keyword tu nhien, uu tien theo thu tu da cung cap trong KEYWORDS.
- Khong nhac toi doi thu, khong dua ten thuong hieu doi thu vao noi dung tra ve.
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
        private readonly ApiKeyImageGenerator $apiKeyGenerator,
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

        if ($asset->product?->slug === 'ornament-amazon-2') {
            return $this->assets->markListingCompleted($this->generateAmazonMetadata($asset), 'amazon');
        }

        if ($asset->user->can_generate_amazon_listing) {
            return $this->assets->markListingCompleted($this->generateAmazonMetadata($asset), 'amazon');
        }

        if ($asset->user->can_generate_etsy_listing) {
            return $this->assets->markListingCompleted($this->generateEtsyMetadata($asset), 'etsy');
        }

        throw new RuntimeException('User chua bat quyen tao listing metadata Amazon hoac Etsy.');
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
        } catch (Throwable $exception) {
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
            } catch (Throwable $exception) {
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
            $this->generateAmazonListingText($asset),
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


    private function generateAmazonListingText(ProductDesignAsset $asset): string
    {
        $prompt = $this->prompt($this->amazonPromptTemplate($asset), $asset);

        if (($asset->product?->slug ?? null) === 'ornament-amazon-2') {
            if (! $asset->user->canUseAiProvider('v98store')) {
                throw new RuntimeException('User duoc duyet item nay chua bat provider v98Store cho Listing metadata logs.');
            }

            $this->ensureOrnamentAmazonTwoV98StoreBalance($asset->user);

            return $this->apiKeyGenerator->generateText($asset->user, 'v98store', $prompt, 'gpt-5.4');
        }

        return $this->generator->generateText($asset->user, $prompt);
    }

    private function ensureOrnamentAmazonTwoV98StoreBalance(User $user): void
    {
        $balance = $this->v98StoreBalanceForUser($user);

        if (! is_array($balance) || ($balance['ok'] ?? false) !== true) {
            return;
        }

        $remaining = is_numeric($balance['remain_quota'] ?? null) ? (float) $balance['remain_quota'] : 0.0;

        if ($remaining <= 0) {
            $this->notifyV98StoreBalanceExhausted($user, $balance);

            throw new RuntimeException('v98Store da het tien/het quota. Listing metadata logs tam dung, vui long nap them tien roi chay lai.');
        }
    }

    /**
     * @return array{ok: bool, remain_quota?: float|int, used_quota?: float|int, name?: string|null, message?: string, credential_id?: int}|null
     */
    private function v98StoreBalanceForUser(User $user): ?array
    {
        $credential = UserApiCredential::query()
            ->where('provider_key', 'v98store')
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        if (! $credential) {
            return ['ok' => false, 'message' => 'No key'];
        }

        try {
            return Cache::remember(
                "v98store-balance:{$credential->id}",
                now()->addSeconds(15),
                fn (): array => array_merge($this->fetchV98StoreBalance($credential), ['credential_id' => $credential->id]),
            );
        } catch (Throwable) {
            return array_merge($this->fetchV98StoreBalance($credential), ['credential_id' => $credential->id]);
        }
    }

    /**
     * @return array{ok: bool, remain_quota?: float|int, used_quota?: float|int, name?: string|null, message?: string}
     */
    private function fetchV98StoreBalance(UserApiCredential $credential): array
    {
        $endpoint = config('services.api_key_providers.v98store.balance_endpoint', 'https://v98store.com/check-balance');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return ['ok' => false, 'message' => 'No endpoint'];
        }

        try {
            $apiKey = $credential->key_api;
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Key decrypt error'];
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return ['ok' => false, 'message' => 'Empty key'];
        }

        try {
            $response = Http::timeout(10)->get(trim($endpoint), [
                'key_api' => trim($apiKey),
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Request failed'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'message' => 'HTTP '.$response->status()];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Invalid balance'];
        }

        return [
            'ok' => true,
            'remain_quota' => is_numeric($payload['remain_quota'] ?? null) ? $payload['remain_quota'] + 0 : 0,
            'used_quota' => is_numeric($payload['used_quota'] ?? null) ? $payload['used_quota'] + 0 : 0,
            'name' => is_string($payload['name'] ?? null) ? $payload['name'] : null,
            'message' => is_string($payload['message'] ?? null) ? $payload['message'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function notifyV98StoreBalanceExhausted(User $user, array $balance): void
    {
        $credentialId = (string) ($balance['credential_id'] ?? 'unknown');
        $alertKey = "v98store-listing-balance-alert:{$credentialId}";

        if (! Cache::add($alertKey, true, now()->addHours(6))) {
            return;
        }

        $remaining = is_numeric($balance['remain_quota'] ?? null) ? (float) $balance['remain_quota'] : 0.0;
        $used = is_numeric($balance['used_quota'] ?? null) ? (float) $balance['used_quota'] : null;
        $accountName = is_string($balance['name'] ?? null) ? $balance['name'] : 'v98Store';
        $subject = 'v98Store het tien/quota - Listing metadata logs da tam dung';
        $body = implode("\n", array_filter([
            'v98Store het tien/quota nen Listing metadata logs da tam dung.',
            '',
            'User: #'.$user->id.' '.$user->name.' <'.$user->email.'>',
            'Account: '.$accountName,
            'Remain: $'.number_format($remaining, 4, '.', ''),
            $used !== null ? 'Used: '.number_format($used, 4, '.', '') : null,
            'Time: '.now()->format('Y-m-d H:i:s'),
            '',
            'Vui long nap them tien/quota roi bam Duyet lai/Retry de chay Listing metadata logs.',
        ]));

        $recipients = collect([$user->email])
            ->merge(User::query()->where('is_admin', true)->pluck('email'))
            ->filter(fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        foreach ($recipients as $email) {
            try {
                Mail::raw($body, fn ($mail) => $mail->to($email)->subject($subject));
            } catch (Throwable) {
            }
        }
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
        return $asset->product?->slug === 'ornament-amazon-2'
            ? 'amazon'
            : ($asset->user->can_generate_amazon_listing ? 'amazon' : 'etsy');
    }

    private function amazonPromptTemplate(ProductDesignAsset $asset): string
    {
        return $asset->product?->slug === 'sticker'
            ? self::AMAZON_STICKER_PROMPT_TEMPLATE
            : self::AMAZON_PROMPT_TEMPLATE;
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
