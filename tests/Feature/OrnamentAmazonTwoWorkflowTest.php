<?php

namespace Tests\Feature;

use App\Livewire\Pages\OrnamentAmazonTwo\ProductDesignCard;
use App\Livewire\Pages\OrnamentAmazonTwo\WorkflowActionButton;
use App\Livewire\Modals\Image\ReviewImage;
use App\Models\OrnamentAmazonTwoWorkflow;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserAiProvider;
use App\Services\Ai\ApiKeyImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class OrnamentAmazonTwoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ornament_amazon_two_card_renders_workflow_panel(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'personalized dog ornament',
            'image_link' => 'https://example.com/source.png',
            'data_item_add' => [
                'productTitle' => 'Personalized Dog Ornament',
                'bulletPoints' => ['Custom keepsake', 'Gift ready'],
                'images' => ['https://example.com/source.png'],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->assertSee('3. Script')
            ->assertSee('6. Mockup');
    }

    public function test_workflow_data_and_image_generation_are_saved_to_item_json(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'personalized cat ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'data_item_add' => [
                'productTitle' => 'Personalized Cat Ornament',
                'bulletPoints' => ['Custom cat artwork', 'Holiday gift'],
                'images' => ['https://example.com/source.png'],
            ],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateText')
                ->once()
                ->andReturn(json_encode([
                    'analysis' => [
                        'audience' => 'Pet owners buying holiday keepsakes.',
                        'positioning' => 'Personalized emotional gift.',
                        'product_facts' => ['round ornament', 'custom cat print'],
                        'style' => 'warm Christmas premium listing',
                        'safe_claims' => ['custom keepsake'],
                    ],
                    'prompts' => [
                        'usp' => 'USP hero with custom cat ornament.',
                        'before_after' => 'Before after gift transformation.',
                        'comparison' => 'Generic ornament versus personalized ornament.',
                        'features' => 'Feature callouts for print and ribbon.',
                        'details' => 'Close-up details of print and hanger.',
                        'custom_guide' => 'Three step custom guide.',
                    ],
                ], JSON_THROW_ON_ERROR));

            $mock->shouldReceive('generateWithReferences')
                ->once()
                ->andReturn('/storage/generated/ornament-amazon-2/workflow/usp.png');
        });

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->call('generateWorkflowData')
            ->call('generateWorkflowImage', 'usp')
            ->assertSee('6. Mockup');

        $freshAsset = ProductDesignAsset::findOrFail($asset->id);
        $workflowRecord = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail();
        $workflow = $workflowRecord->workflow_data;

        $this->assertSame('USP hero with custom cat ornament.', $workflow['prompts']['usp']);
        $this->assertSame('Three step custom guide.', $workflow['prompts']['custom_guide']);
        $this->assertArrayNotHasKey('main', $workflow['prompts']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/usp.png', $workflow['images']['usp']['url']);
        $this->assertSame('USP hero with custom cat ornament.', $workflowRecord->workflow_data['prompts']['usp']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/usp.png', $freshAsset->mockup1);
        $this->assertArrayNotHasKey('ornament_amazon_two_workflow', $freshAsset->data_item_add ?? []);
    }

    public function test_create_master_uses_selected_image_model(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 3,
            'keyword' => 'personalized dog ornament',
            'image_link' => 'https://example.com/source.png',
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        Prompt::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'prompt_number' => 1,
            'name' => 'Create Master',
            'content' => 'Create clean master ornament.',
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (
                    User $user,
                    string $providerKey,
                    string $imageUri,
                    string $prompt,
                    string $folder,
                    bool $removeBackground,
                    ?string $model,
                ): bool {
                    return $providerKey === 'chatgpt'
                        && $imageUri === 'https://example.com/source.png'
                        && $prompt === 'Create clean master ornament.'
                        && $folder === 'generated/ornament-amazon-2/redesign'
                        && $model === 'gpt-image-2';
                })
                ->andReturn('/storage/generated/ornament-amazon-2/redesign/master.png');
        });

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-2',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->call('generateRedesign')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(
            '/storage/generated/ornament-amazon-2/redesign/master.png',
            ProductDesignAsset::findOrFail($asset->id)->redesign,
        );
    }

    public function test_workflow_action_button_dispatches_asset_scoped_refresh_event(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 4,
            'keyword' => 'personalized dog ornament',
            'image_link' => 'https://example.com/source.png',
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        Prompt::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'prompt_number' => 1,
            'name' => 'Create Master',
            'content' => 'Create clean master ornament.',
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn('/storage/generated/ornament-amazon-2/redesign/master.png');
        });

        Livewire::actingAs($user)
            ->test(WorkflowActionButton::class, [
                'assetId' => $asset->id,
                'action' => 'main',
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-2',
            ])
            ->call('run')
            ->assertDispatched("ornament-amazon-two-product-design-updated.{$asset->id}")
            ->assertDispatched('ornament-amazon-two-product-design-updated', assetId: $asset->id);
    }

    public function test_full_b1_to_b5_workflow_saves_refs_prompts_listing_and_aplus_outputs(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 7,
            'keyword' => 'personalized family ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'data_item_add' => [
                'productTitle' => 'Personalized Family Ornament',
                'bulletPoints' => ['Custom family keepsake', 'Gift ready'],
                'images' => ['https://example.com/source.png'],
            ],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateText')
                ->times(3)
                ->andReturn(
                    implode("\n\n", [
                        '===SECTION:AUDIENCE==='."\n".'Gift givers buy this for emotional holiday keepsakes.',
                        '===SECTION:STYLE==='."\n".'Premium warm Christmas. HEADLINE_HEX_GRADIENT: #111111 -> #555555 -> #999999',
                        '===SECTION:MAIN==='."\n".'Main product only on white background.',
                        '===SECTION:USP==='."\n".'Person A holds the custom ornament with 3 callouts.',
                        '===SECTION:BEFORE_AFTER==='."\n".'Before smaller sad panel, after larger happy gift panel.',
                        '===SECTION:COMPARISON==='."\n".'Generic ornament versus personalized ornament.',
                        '===SECTION:FEATURES==='."\n".'Person A with feature callouts.',
                        '===SECTION:DETAILS==='."\n".'Macro details with 3 zoom circles.',
                        '===SECTION:CUSTOM_GUIDE==='."\n".'Three panel upload, design, receive guide.',
                    ]),
                    implode("\n\n", [
                        '===PERSON_A==='."\n".'A 38-year-old white American mother with shoulder-length brown hair, warm brown eyes, soft sweater, gentle smile, holding a keepsake ornament in a cozy holiday living room.',
                        '===PERSON_B==='."\n".'A 29-year-old white American adult daughter with blonde hair, blue eyes, casual cardigan, bright gifting smile, presenting the ornament beside the Christmas tree.',
                    ]),
                    json_encode([
                    'prompts' => [
                        'usp' => 'USP hero with custom ornament.',
                        'before_after' => 'Before after generic gift transformation.',
                        'comparison' => 'Generic versus personalized comparison.',
                        'features' => 'Feature callouts for ribbon and print.',
                        'details' => 'Close-up detail prompt.',
                        'custom_guide' => 'Three step upload design receive prompt.',
                    ],
                    'aplus_prompts' => [
                        'pain' => ['desktop' => 'Pain desktop banner.', 'mobile' => 'Pain mobile banner.'],
                        'solution' => ['desktop' => 'Solution desktop banner.', 'mobile' => 'Solution mobile banner.'],
                        'paradise' => ['desktop' => 'Paradise desktop banner.', 'mobile' => 'Paradise mobile banner.'],
                        'closeup' => ['desktop' => 'Closeup desktop banner.', 'mobile' => 'Closeup mobile banner.'],
                        'guide' => ['desktop' => 'Guide desktop banner.', 'mobile' => 'Guide mobile banner.'],
                        'care' => ['desktop' => 'Care desktop banner.', 'mobile' => 'Care mobile banner.'],
                    ],
                    ], JSON_THROW_ON_ERROR),
                );

            $mock->shouldReceive('generateFromPrompt')
                ->times(2)
                ->andReturn(
                    '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                );

            $mock->shouldReceive('generateWithReferences')
                ->once()
                ->andReturn('/storage/generated/ornament-amazon-2/workflow/aplus/pain-desktop.png');
        });

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->set('supplierNotes', 'Acrylic ornament, gift box included')
            ->set('reviewsRaw', "Loved the personalization\nWish text was more readable")
            ->call('generateWorkflowScript')
            ->call('generateWorkflowPerson', 'a')
            ->call('generateWorkflowPerson', 'b')
            ->call('generateWorkflowPrompts')
            ->call('generateWorkflowAplusImage', 'pain', 'desktop')
            ->assertSee('6. Mockup');

        $freshAsset = ProductDesignAsset::findOrFail($asset->id);
        $workflowRecord = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail();
        $workflow = $workflowRecord->workflow_data;

        $this->assertSame(2, $workflow['version']);
        $this->assertSame('Gift givers buy this for emotional holiday keepsakes.', $workflow['script']['audience']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/refs/person-a.png', $workflow['b2']['person_a_ref']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/refs/person-b.png', $workflow['b2']['person_b_ref']);
        $this->assertNotEmpty($workflow['b2']['person_a_prompt']);
        $this->assertNotEmpty($workflow['b2']['person_b_prompt']);
        $this->assertSame('USP hero with custom ornament.', $workflow['prompts']['usp']);
        $this->assertSame('Three step upload design receive prompt.', $workflow['prompts']['custom_guide']);
        $this->assertArrayNotHasKey('main', $workflow['prompts']);
        $this->assertStringContainsString('AMAZON A+ CONTENT IMAGE - A+ Pain', $workflow['aplus_prompts']['pain']['desktop']);
        $this->assertStringContainsString('B4 BEFORE-AFTER SOURCE:', $workflow['aplus_prompts']['pain']['desktop']);
        $this->assertStringContainsString('Before after generic gift transformation.', $workflow['aplus_prompts']['pain']['desktop']);
        $this->assertStringContainsString('1464 x 600', $workflow['aplus_prompts']['pain']['desktop']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/aplus/pain-desktop.png', $workflow['aplus_images']['pain']['desktop']['url']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/aplus/pain-desktop.png', $workflowRecord->workflow_data['aplus_images']['pain']['desktop']['url']);
        $this->assertArrayNotHasKey('ornament_amazon_two_workflow', $freshAsset->data_item_add ?? []);
    }

    public function test_prompt_create_unlocks_after_same_card_refresh_without_page_reload(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 71,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'script' => [
                    'audience' => 'Gift givers.',
                    'style' => 'Warm premium Christmas.',
                    'usp' => 'USP script.',
                ],
                'b2' => [
                    'person_a_prompt' => 'Person A prompt.',
                    'person_b_prompt' => 'Person B prompt.',
                ],
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->assertSee('Can tao du 4. Person A/B truoc.');

        OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->update([
            'workflow_data' => [
                'version' => 2,
                'script' => [
                    'audience' => 'Gift givers.',
                    'style' => 'Warm premium Christmas.',
                    'usp' => 'USP script.',
                ],
                'b2' => [
                    'person_a_prompt' => 'Person A prompt.',
                    'person_b_prompt' => 'Person B prompt.',
                    'person_a_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    'person_b_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                ],
            ],
        ]);

        $component
            ->call('refreshWhenUpdated')
            ->assertDontSee('Can tao du 4. Person A/B truoc.')
            ->assertSee('$wire.generateWorkflowPrompts()', false)
            ->assertSee('Writing prompt...');
    }

    public function test_script_generation_fails_when_provider_returns_empty_script(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 11,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'data_item_add' => [
                'productTitle' => 'Personalized Ornament',
                'bulletPoints' => ['Custom keepsake'],
                'images' => ['https://example.com/source.png'],
            ],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateText')
                ->once()
                ->andReturn(json_encode([
                    'analysis' => [],
                    'script' => [],
                ], JSON_THROW_ON_ERROR));
        });

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->call('generateWorkflowScript')
            ->assertDispatched('toast', type: 'error');

        $workflowRecord = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail();
        $workflow = $workflowRecord->workflow_data ?? [];

        $this->assertArrayNotHasKey('script', $workflow);
        $this->assertArrayNotHasKey('script', $workflowRecord->workflow_data);
    }

    public function test_b1_script_generation_clears_stale_b4_prompts_and_b5_outputs(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 12,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png',
            'mockup6' => '/storage/generated/ornament-amazon-2/workflow/old-guide.png',
            'data_item_add' => [
                'productTitle' => 'Personalized Ornament',
                'bulletPoints' => ['Custom keepsake'],
                'images' => ['https://example.com/source.png'],
            ],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'prompts_generated_at' => now()->subHour()->toIso8601String(),
                'prompts' => [
                    'usp' => 'Old USP prompt.',
                    'before_after' => 'Old before after prompt.',
                    'comparison' => 'Old comparison prompt.',
                    'features' => 'Old features prompt.',
                    'details' => 'Old details prompt.',
                    'custom_guide' => 'Old custom guide prompt.',
                ],
                'aplus_prompts' => [
                    'pain' => ['desktop' => 'Old pain desktop.', 'mobile' => 'Old pain mobile.'],
                ],
                'images' => [
                    'usp' => ['url' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png'],
                    'custom_guide' => ['url' => '/storage/generated/ornament-amazon-2/workflow/old-guide.png'],
                ],
                'aplus_images' => [
                    'pain' => ['desktop' => ['url' => '/storage/generated/ornament-amazon-2/workflow/aplus/old-pain.png']],
                ],
                'flow_payload' => ['prompts' => ['usp' => 'Old USP prompt.']],
                'flow_sent_at' => now()->subHour()->toIso8601String(),
            ],
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateText')
                ->times(2)
                ->andReturn(
                    implode("\n\n", [
                        '===SECTION:AUDIENCE==='."\n".'Gift buyers want a sentimental custom keepsake.',
                        '===SECTION:STYLE==='."\n".'Bright realistic Christmas listing style.',
                        '===SECTION:MAIN==='."\n".'Main product on clean white.',
                        '===SECTION:USP==='."\n".'New USP script.',
                        '===SECTION:BEFORE_AFTER==='."\n".'New before after script.',
                        '===SECTION:COMPARISON==='."\n".'New comparison script.',
                        '===SECTION:FEATURES==='."\n".'New features script.',
                        '===SECTION:DETAILS==='."\n".'New details script.',
                        '===SECTION:CUSTOM_GUIDE==='."\n".'New custom guide script.',
                    ]),
                    implode("\n\n", [
                        '===PERSON_A==='."\n".'A warm adult gift receiver in a cozy holiday room.',
                        '===PERSON_B==='."\n".'A cheerful adult gift giver near a Christmas tree.',
                    ]),
                );
        });

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->call('generateWorkflowScript')
            ->assertSee('3. Script')
            ->assertSee('5. Prompt create')
            ->assertDontSee('B4 Prompt Output');

        $freshAsset = ProductDesignAsset::findOrFail($asset->id);
        $workflow = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail()->workflow_data;

        $this->assertSame('New USP script.', $workflow['script']['usp']);
        $this->assertArrayNotHasKey('prompts', $workflow);
        $this->assertArrayNotHasKey('aplus_prompts', $workflow);
        $this->assertArrayNotHasKey('prompts_generated_at', $workflow);
        $this->assertArrayNotHasKey('images', $workflow);
        $this->assertArrayNotHasKey('aplus_images', $workflow);
        $this->assertArrayNotHasKey('flow_payload', $workflow);
        $this->assertNull($freshAsset->mockup1);
        $this->assertNull($freshAsset->mockup6);
    }

    public function test_b5_listing_image_uses_project_ads_reference_lock_and_ref_order(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 21,
            'keyword' => 'personalized family ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'data_item_add' => ['productTitle' => 'Personalized Family Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'script' => [
                    'style' => 'Premium warm Christmas. HEADLINE_HEX_GRADIENT: #111111 -> #555555 -> #999999',
                ],
                'b2' => [
                    'person_a_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    'person_b_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                ],
                'prompts' => [
                    'custom_guide' => 'Three panel upload, design, receive prompt.',
                ],
            ],
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateWithReferences')
                ->once()
                ->withArgs(function (
                    User $user,
                    string $providerKey,
                    array $imageUris,
                    string $prompt,
                    string $folder,
                    bool $removeBackground,
                    ?string $model,
                ): bool {
                    return $providerKey === 'chatgpt'
                        && $imageUris === [
                            '/storage/generated/ornament-amazon-2/redesign/master.png',
                            '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                            '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                        ]
                        && str_contains($prompt, 'PRIORITY NOTICE - REFERENCE IMAGE IS GROUND TRUTH')
                        && str_contains($prompt, 'FACE REFERENCE LOCK')
                        && str_contains($prompt, 'FOR THIS IMAGE (Custom Guide)')
                        && str_contains($prompt, 'Steps 1-2: Person B uploads photo / designs customization.')
                        && str_contains($prompt, 'STYLE LOCK')
                        && $folder === 'generated/ornament-amazon-2/workflow'
                        && $removeBackground === false
                        && $model === 'gpt-image-1';
                })
                ->andReturn('/storage/generated/ornament-amazon-2/workflow/custom-guide.png');
        });

        app(\App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService::class)
            ->generateWorkflowImage($user, $asset->id, 'custom_guide', 'chatgpt', 'gpt-image-1');

        $workflow = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail()->workflow_data;
        $freshAsset = ProductDesignAsset::findOrFail($asset->id);

        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/custom-guide.png', $workflow['images']['custom_guide']['url']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/custom-guide.png', $freshAsset->mockup6);
    }

    public function test_b5_listing_image_requires_create_master_product_lock(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 22,
            'keyword' => 'personalized family ornament',
            'image_link' => 'https://example.com/source.png',
            'data_item_add' => ['productTitle' => 'Personalized Family Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'prompts' => [
                    'usp' => 'USP prompt',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Can tao anh 2. Create Master truoc de lam anh khoa san pham cho B5.');

        app(\App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService::class)
            ->generateWorkflowImage($user, $asset->id, 'usp', 'chatgpt', 'gpt-image-1');
    }

    public function test_b5_custom_guide_requires_b4_prompt_even_when_b1_script_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 23,
            'keyword' => 'personalized family ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'data_item_add' => ['productTitle' => 'Personalized Family Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'script' => [
                    'custom_guide' => 'Three panel guide: upload photo, approve design, receive finished ornament.',
                ],
                'b2' => [
                    'person_a_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    'person_b_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                ],
                'prompts' => [
                    'usp' => 'USP prompt',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Chua co prompt cho slot Custom Guide. Hay bam Generate B4 Listing + A+ Prompts truoc.');

        app(\App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService::class)
            ->generateWorkflowImage($user, $asset->id, 'custom_guide', 'chatgpt', 'gpt-image-1');
    }

    public function test_b5_listing_images_render_from_product_design_asset_mockups_not_workflow_backup(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 41,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/mockup-source-usp.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'prompts' => [
                    'usp' => 'USP prompt',
                ],
                'images' => [
                    'usp' => [
                        'url' => '/storage/generated/ornament-amazon-2/workflow/workflow-backup-usp.png',
                    ],
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(ProductDesignCard::class, [
                'assetId' => $asset->id,
                'providerKey' => 'chatgpt',
                'imageModel' => 'gpt-image-1',
                'textModel' => 'gpt-4.1-mini',
            ])
            ->assertSee('/storage/generated/ornament-amazon-2/workflow/mockup-source-usp.png', false)
            ->assertSee('src="/storage/generated/ornament-amazon-2/workflow/mockup-source-usp.png', false)
            ->assertSee('/storage/generated/ornament-amazon-2/workflow/mockup-source-usp.png', false)
            ->assertSee('border-dashed border-slate-200 bg-slate-50', false)
            ->assertSee('x-show="isGenerating', false)
            ->assertDontSee('/storage/generated/ornament-amazon-2/workflow/workflow-backup-usp.png', false);
    }

    public function test_generate_all_listing_images_retries_missing_slots_until_all_six_are_saved(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 31,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'b2' => [
                    'person_a_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    'person_b_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                ],
                'prompts' => [
                    'usp' => 'USP prompt',
                    'before_after' => 'Before after prompt',
                    'comparison' => 'Comparison prompt',
                    'features' => 'Features prompt',
                    'details' => 'Details prompt',
                    'custom_guide' => 'Guide prompt',
                ],
                'images' => [
                    'usp' => [
                        'url' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png',
                    ],
                ],
            ],
        ]);

        $attempts = [];

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock) use (&$attempts): void {
            $mock->shouldReceive('generateWithReferences')
                ->andReturnUsing(function (
                    User $user,
                    string $providerKey,
                    array $imageUris,
                    string $prompt,
                    string $folder,
                    bool $removeBackground,
                    ?string $model,
                ) use (&$attempts): string {
                    preg_match('/Now generate this image type: ([^\n]+)/', $prompt, $match);
                    $label = $match[1] ?? 'Unknown';
                    $attempts[$label] = ($attempts[$label] ?? 0) + 1;

                    if ($label === 'Comparison' && $attempts[$label] === 1) {
                        throw new RuntimeException('HTTP 429: rate limited');
                    }

                    return '/storage/generated/ornament-amazon-2/workflow/'.Str::slug($label).'.png';
                });
        });

        app(\App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService::class)
            ->generateAllWorkflowImages($user, $asset->id, 'chatgpt', 'gpt-image-1');

        $workflow = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail()->workflow_data;
        $freshAsset = ProductDesignAsset::findOrFail($asset->id);

        $this->assertCount(6, $workflow['images']);
        $this->assertArrayNotHasKey('images_errors', $workflow);
        $this->assertSame(1, $attempts['USP']);
        $this->assertSame(2, $attempts['Comparison']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/usp.png', $freshAsset->mockup1);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/comparison.png', $freshAsset->mockup3);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/custom-guide.png', $freshAsset->mockup6);
    }

    public function test_parallel_b5_endpoints_prepare_and_merge_slot_outputs(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 51,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png',
            'mockup2' => '/storage/generated/ornament-amazon-2/workflow/old-before-after.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'b2' => [
                    'person_a_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-a.png',
                    'person_b_ref' => '/storage/generated/ornament-amazon-2/workflow/refs/person-b.png',
                ],
                'prompts' => [
                    'usp' => 'USP prompt',
                    'before_after' => 'Before after prompt',
                    'comparison' => 'Comparison prompt',
                    'features' => 'Features prompt',
                    'details' => 'Details prompt',
                    'custom_guide' => 'Guide prompt',
                ],
                'images' => [
                    'usp' => ['url' => '/storage/generated/ornament-amazon-2/workflow/old-usp.png'],
                    'before_after' => ['url' => '/storage/generated/ornament-amazon-2/workflow/old-before-after.png'],
                ],
            ],
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateWithReferences')
                ->twice()
                ->andReturn(
                    '/storage/generated/ornament-amazon-2/workflow/new-usp.png',
                    '/storage/generated/ornament-amazon-2/workflow/new-before-after.png',
                );
        });

        $this->actingAs($user)
            ->postJson(route('offorest.ornament-amazon-2.workflow.listing-images.prepare', ['asset' => $asset->id]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull(ProductDesignAsset::findOrFail($asset->id)->mockup1);
        $this->assertNull(ProductDesignAsset::findOrFail($asset->id)->mockup2);

        $this->actingAs($user)
            ->postJson(route('offorest.ornament-amazon-2.workflow.listing-images.generate', ['asset' => $asset->id, 'slot' => 'usp']), [
                'provider_key' => 'chatgpt',
                'image_model' => 'gpt-image-1',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'slot' => 'usp',
                'url' => '/storage/generated/ornament-amazon-2/workflow/new-usp.png',
            ]);

        $this->actingAs($user)
            ->postJson(route('offorest.ornament-amazon-2.workflow.listing-images.generate', ['asset' => $asset->id, 'slot' => 'before_after']), [
                'provider_key' => 'chatgpt',
                'image_model' => 'gpt-image-1',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'slot' => 'before_after',
                'url' => '/storage/generated/ornament-amazon-2/workflow/new-before-after.png',
            ]);

        $workflow = OrnamentAmazonTwoWorkflow::where('product_design_asset_id', $asset->id)->firstOrFail()->workflow_data;
        $freshAsset = ProductDesignAsset::findOrFail($asset->id);

        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/new-usp.png', $workflow['images']['usp']['url']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/new-before-after.png', $workflow['images']['before_after']['url']);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/new-usp.png', $freshAsset->mockup1);
        $this->assertSame('/storage/generated/ornament-amazon-2/workflow/new-before-after.png', $freshAsset->mockup2);
    }

    public function test_mockup_preview_custom_prompt_edits_current_mockup_image(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 61,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (
                    User $user,
                    string $providerKey,
                    string $imageUri,
                    string $prompt,
                    string $folder,
                    bool $removeBackground,
                    ?string $model,
                ): bool {
                    return $providerKey === 'chatgpt'
                        && $imageUri === '/storage/generated/ornament-amazon-2/workflow/mockup-1.png'
                        && str_contains($prompt, 'Use the attached image as the exact visual base.')
                        && str_contains($prompt, 'make the text brighter')
                        && $folder === 'generated/ornament-amazon-2/custom-edits'
                        && $removeBackground === false
                        && $model === 'gpt-image-1';
                })
                ->andReturn('/storage/generated/ornament-amazon-2/custom-edits/mockup-1-edited.png');
        });

        Livewire::actingAs($user)
            ->test(ReviewImage::class)
            ->call('open',
                '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
                '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
                'MOCKUP 1 USP',
                [[
                    'src' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
                    'original' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
                    'title' => 'MOCKUP 1 USP',
                    'editTarget' => 'mockup1',
                    'prompt' => 'USP prompt for preview.',
                ]],
                0,
                'ornament-amazon-two-custom-image',
                'ornament-amazon-2',
                $asset->id,
                'personalized ornament',
                'mockup1',
                'chatgpt',
                'gpt-image-1',
            )
            ->assertSee('Prompt Create Image')
            ->assertSee('USP prompt for preview.')
            ->set('customPrompt', 'make the text brighter')
            ->call('customizeOrnamentAmazonTwoImage')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(
            '/storage/generated/ornament-amazon-2/custom-edits/mockup-1-edited.png',
            ProductDesignAsset::findOrFail($asset->id)->mockup1,
        );
    }

    public function test_empty_mockup_preview_can_generate_current_slot_from_b4_prompt(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-amazon-2')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 62,
            'keyword' => 'personalized ornament',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/ornament-amazon-2/redesign/master.png',
            'mockup1' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png',
            'data_item_add' => ['productTitle' => 'Personalized Ornament'],
        ]);

        UserAiProvider::create([
            'user_id' => $user->id,
            'provider_key' => 'chatgpt',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        OrnamentAmazonTwoWorkflow::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'workflow_data' => [
                'version' => 2,
                'prompts' => [
                    'features' => 'Feature callout prompt.',
                ],
            ],
        ]);

        $this->mock(ApiKeyImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateWithReferences')
                ->once()
                ->withArgs(function (
                    User $user,
                    string $providerKey,
                    array $imageUris,
                    string $prompt,
                    string $folder,
                    bool $removeBackground,
                    ?string $model,
                ): bool {
                    return $providerKey === 'chatgpt'
                        && $imageUris === ['/storage/generated/ornament-amazon-2/redesign/master.png']
                        && str_contains($prompt, 'Now generate this image type: Features')
                        && str_contains($prompt, 'Feature callout prompt.')
                        && $folder === 'generated/ornament-amazon-2/workflow'
                        && $removeBackground === false
                        && $model === 'gpt-image-1';
                })
                ->andReturn('/storage/generated/ornament-amazon-2/workflow/features.png');
        });

        Livewire::actingAs($user)
            ->test(ReviewImage::class)
            ->call('open',
                '',
                '',
                'MOCKUP 4 Features',
                [
                    ['src' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png', 'original' => '/storage/generated/ornament-amazon-2/workflow/mockup-1.png', 'title' => 'MOCKUP 1', 'editTarget' => 'mockup1', 'prompt' => 'USP prompt.'],
                    ['src' => '', 'original' => '', 'title' => 'MOCKUP 2', 'editTarget' => 'mockup2'],
                    ['src' => '', 'original' => '', 'title' => 'MOCKUP 3', 'editTarget' => 'mockup3'],
                    ['src' => '', 'original' => '', 'title' => 'MOCKUP 4', 'editTarget' => 'mockup4', 'prompt' => 'Feature callout prompt.'],
                    ['src' => '', 'original' => '', 'title' => 'MOCKUP 5', 'editTarget' => 'mockup5'],
                    ['src' => '', 'original' => '', 'title' => 'MOCKUP 6', 'editTarget' => 'mockup6'],
                ],
                3,
                'ornament-amazon-two-custom-image',
                'ornament-amazon-2',
                $asset->id,
                'personalized ornament',
                'mockup4',
                'chatgpt',
                'gpt-image-1',
            )
            ->assertSee('Generate This Image')
            ->assertSee('No image')
            ->assertSee('Prompt Create Image')
            ->assertSee('Feature callout prompt.')
            ->call('generateOrnamentAmazonTwoMockupImage')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(
            '/storage/generated/ornament-amazon-2/workflow/features.png',
            ProductDesignAsset::findOrFail($asset->id)->mockup4,
        );
    }
}
