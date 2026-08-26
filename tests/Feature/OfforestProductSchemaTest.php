<?php

namespace Tests\Feature;

use App\Livewire\Modals\Admin\AddUser;
use App\Livewire\Modals\Admin\ApiCreditForm;
use App\Livewire\Modals\Admin\EditUser;
use App\Livewire\Modals\Sticker\AddProductDesign;
use App\Livewire\Modals\Sticker\EditProductDetail;
use App\Livewire\Modals\OrnamentAmazon\AddProductDesign as OrnamentAddProductDesign;
use App\Livewire\Modals\OrnamentEtsy\AddProductDesign as OrnamentEtsyAddProductDesign;
use App\Livewire\Modals\Admin\EditProductBackgroundRemoval;
use App\Livewire\Modals\ProductDesign\DeleteIdeaConfirm;
use App\Livewire\Pages\Admin\ListUser;
use App\Mail\Admin\UserLoginCredentialsMail;
use App\Models\ActivityLog;
use App\Models\ApiCreditTracker;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\ProductDriveUpload;
use App\Models\Prompt;
use App\Models\User;
use App\Models\VertexApiCredential;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Services\OrnamentAmazon\OrnamentAmazonService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\Product\ProductBackgroundRemovalService;
use App\Services\Sticker\PsdMockupRenderer;
use App\Services\Sticker\PsdMockupTemplateService;
use App\Services\Sticker\StickerService;
use App\Services\Google\GoogleDriveService;
use App\Services\Product\ApprovedAssetDriveExportService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class OfforestProductSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_product_prompt_credential_and_design_asset_relationships_work(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'mockup')->firstOrFail();

        $user->products()->attach($product);

        $credential = VertexApiCredential::create([
            'user_id' => $user->id,
            'project_id' => 'offorest-project',
            'location' => 'global',
            'client_email' => 'vertex@example.iam.gserviceaccount.com',
            'private_key' => 'secret-key',
            'credentials_json' => ['client_email' => 'vertex@example.iam.gserviceaccount.com'],
        ]);

        $prompt = Prompt::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'prompt_number' => 1,
            'name' => 'Mockup prompt 1',
            'content' => 'Create a clean mockup.',
        ]);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'sunflower',
            'image_link' => 'https://example.com/source.png',
            'redesign' => 'https://example.com/redesign.png',
            'mockup1' => 'https://example.com/mockup1.png',
        ]);

        $this->assertTrue($user->products->contains($product));
        $this->assertTrue($user->vertexApiCredential->is($credential));
        $this->assertTrue($user->prompts->contains($prompt));
        $this->assertTrue($user->productDesignAssets->contains($asset));
        $this->assertTrue($product->prompts->contains($prompt));
        $this->assertTrue($product->designAssets->contains($asset));
    }

    public function test_design_asset_item_number_increments_per_user_and_product(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $sticker = Product::where('slug', 'sticker')->firstOrFail();
        $mockup = Product::where('slug', 'mockup')->firstOrFail();

        $repository = app(ProductDesignAssetRepository::class);

        $firstSticker = $repository->createDraft($firstUser->id, $sticker->id, 'first sticker');
        $secondSticker = $repository->createDraft($firstUser->id, $sticker->id, 'second sticker');
        $firstUserMockup = $repository->createDraft($firstUser->id, $mockup->id, 'first mockup');
        $secondUserSticker = $repository->createDraft($secondUser->id, $sticker->id, 'another user sticker');

        $this->assertSame(1, $firstSticker->item_number);
        $this->assertSame(2, $secondSticker->item_number);
        $this->assertSame(1, $firstUserMockup->item_number);
        $this->assertSame(1, $secondUserSticker->item_number);
    }

    public function test_sticker_card_confirm_delete_removes_the_idea(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        Storage::disk('public')->put('generated/sticker/source/source.png', 'source');
        Storage::disk('public')->put('generated/sticker/redesign/master.png', 'master');
        Storage::disk('public')->put('generated/sticker/redesign/candidate.png', 'candidate');
        Storage::disk('public')->put('generated/sticker/mockups/999/old.png', 'old');

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'ornament sticker',
            'image_link' => '/storage/generated/sticker/source/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'redesign_candidates' => ['/storage/generated/sticker/redesign/candidate.png'],
            'mockup1' => '/storage/generated/sticker/mockups/999/old.png',
        ]);
        Storage::disk('public')->put("generated/sticker/mockups/{$asset->id}/extra.png", 'extra');

        Livewire::actingAs($user)
            ->test(DeleteIdeaConfirm::class)
            ->call('openModal', 'modals.product-design.delete-idea-confirm', [
                'productSlug' => 'sticker',
                'assetId' => $asset->id,
                'keyword' => $asset->keyword,
            ])
            ->assertSet('isOpen', true)
            ->call('deleteAsset');

        $this->assertDatabaseMissing('product_design_assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'sticker.item_deleted']);
        Storage::disk('public')->assertMissing('generated/sticker/source/source.png');
        Storage::disk('public')->assertMissing('generated/sticker/redesign/master.png');
        Storage::disk('public')->assertMissing('generated/sticker/redesign/candidate.png');
        Storage::disk('public')->assertMissing('generated/sticker/mockups/999/old.png');
        Storage::disk('public')->assertMissing("generated/sticker/mockups/{$asset->id}/extra.png");
    }

    public function test_ornament_card_confirm_delete_removes_the_idea(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'ornament amazon',
            'image_link' => 'https://example.com/source.png',
        ]);

        Livewire::actingAs($user)
            ->test(DeleteIdeaConfirm::class)
            ->call('openModal', 'modals.product-design.delete-idea-confirm', [
                'productSlug' => 'ornament',
                'assetId' => $asset->id,
                'keyword' => $asset->keyword,
            ])
            ->assertSet('isOpen', true)
            ->call('deleteAsset');

        $this->assertDatabaseMissing('product_design_assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ornament.item_deleted']);
    }

    public function test_ornament_etsy_card_confirm_delete_removes_the_idea(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-etsy')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'ornament etsy',
            'image_link' => 'https://example.com/source.png',
        ]);

        Livewire::actingAs($user)
            ->test(DeleteIdeaConfirm::class)
            ->call('openModal', 'modals.product-design.delete-idea-confirm', [
                'productSlug' => 'ornament-etsy',
                'assetId' => $asset->id,
                'keyword' => $asset->keyword,
            ])
            ->assertSet('isOpen', true)
            ->call('deleteAsset');

        $this->assertDatabaseMissing('product_design_assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ornament_etsy.item_deleted']);
    }

    public function test_user_can_open_assigned_product_page(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'mockup')->firstOrFail();

        $user->products()->attach($product);

        $this->actingAs($user)
            ->get(route('offorest.products.mockup'))
            ->assertOk()
            ->assertSee('Mockup');
    }

    public function test_user_can_open_assigned_ornament_page(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();

        $user->products()->attach($product);

        $this->actingAs($user)
            ->get(route('offorest.products.ornament'))
            ->assertOk()
            ->assertSee('Ornament Amazon');
    }

    public function test_user_can_open_assigned_ornament_etsy_page(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament-etsy')->firstOrFail();

        $user->products()->attach($product);

        $this->actingAs($user)
            ->get(route('offorest.products.ornament-etsy'))
            ->assertOk()
            ->assertSee('Ornament Etsy');
    }

    public function test_ornament_etsy_service_creates_assets_for_its_own_product(): void
    {
        $user = User::factory()->create();
        $amazonProduct = Product::where('slug', 'ornament')->firstOrFail();
        $etsyProduct = Product::where('slug', 'ornament-etsy')->firstOrFail();
        $user->products()->attach($etsyProduct);

        $asset = app(OrnamentEtsyService::class)->createAsset(
            $user,
            'cute cat ornament',
            'https://example.com/source.png',
        );

        $this->assertSame($etsyProduct->id, $asset->product_id);
        $this->assertNotSame($amazonProduct->id, $asset->product_id);
    }

    public function test_ornament_etsy_add_modal_uses_sticker_style_form_and_requires_ornament_keyword(): void
    {
        $user = User::factory()->create();
        $etsyProduct = Product::where('slug', 'ornament-etsy')->firstOrFail();
        $user->products()->attach($etsyProduct);

        $this->actingAs($user);

        Livewire::test(OrnamentEtsyAddProductDesign::class)
            ->call('openModal', 'modals.ornament-etsy.add-product-design')
            ->assertSet('isOpen', true)
            ->set('keyword', 'cute cat')
            ->set('imageLink', 'https://example.com/source.png')
            ->call('save')
            ->assertHasErrors(['keyword'])
            ->set('keyword', 'cute cat ornament')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false);

        $this->assertDatabaseHas('product_design_assets', [
            'user_id' => $user->id,
            'product_id' => $etsyProduct->id,
            'keyword' => 'cute cat ornament',
            'image_link' => 'https://example.com/source.png',
        ]);
    }

    public function test_user_cannot_open_unassigned_product_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('offorest.products.mockup'))
            ->assertForbidden();
    }

    public function test_non_admin_user_cannot_open_admin_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('offorest.admin.users'))
            ->assertForbidden();
    }

    public function test_admin_user_can_open_admin_page(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('offorest.admin.users'))
            ->assertOk()
            ->assertSee('User access');
    }

    public function test_admin_user_can_open_activity_logs_page(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        ActivityLog::create([
            'user_id' => $user->id,
            'actor_type' => 'admin',
            'event' => 'test.event',
            'description' => 'Test log entry',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('offorest.admin.logs'))
            ->assertOk()
            ->assertSee('Activity logs')
            ->assertSee('test.event');
    }

    public function test_admin_can_create_user_with_new_vertex_credential(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(AddUser::class)
            ->call('openModal', 'modals.admin.add-user')
            ->set('name', 'Sticker Vertex User')
            ->set('username', 'sticker_vertex_user')
            ->set('email', 'vertex-user@example.com')
            ->set('password', 'Password12345')
            ->set('selectedProducts', [$product->id])
            ->set('vertexMode', 'new')
            ->set('vertexLocation', 'us-central1')
            ->set('vertexJson', json_encode([
                'type' => 'service_account',
                'project_id' => 'vertex-project',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n",
                'client_email' => 'vertex@example.iam.gserviceaccount.com',
            ], JSON_THROW_ON_ERROR))
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'vertex-user@example.com')->firstOrFail();
        $credential = $user->vertexApiCredential()->firstOrFail();

        $this->assertSame('vertex-project', $credential->project_id);
        $this->assertSame('us-central1', $credential->location);
        $this->assertSame('vertex@example.iam.gserviceaccount.com', $credential->client_email);
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n", $credential->private_key);
        $this->assertSame('vertex-project', $credential->credentials_json['project_id']);
    }

    public function test_admin_can_grant_product_access_without_user_name_matching_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(AddUser::class)
            ->call('openModal', 'modals.admin.add-user')
            ->set('name', 'Any Operator')
            ->set('username', 'any_operator')
            ->set('email', 'any-operator@example.com')
            ->set('password', 'Password12345')
            ->set('selectedProducts', [$product->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'any-operator@example.com')->firstOrFail();

        $this->assertTrue($user->products()->whereKey($product->id)->exists());
    }

    public function test_admin_add_user_sends_login_credentials_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(AddUser::class)
            ->call('openModal', 'modals.admin.add-user')
            ->set('name', 'Mail Login User')
            ->set('username', 'mail_login_user')
            ->set('email', 'mail-login-user@example.com')
            ->set('password', 'Password12345')
            ->set('selectedProducts', [$product->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'mail-login-user@example.com')->firstOrFail();
        $expectedAppUrl = rtrim((string) config('app.url'), '/');

        if ($expectedAppUrl === '' || str_contains($expectedAppUrl, 'localhost') || str_contains($expectedAppUrl, '127.0.0.1')) {
            $expectedAppUrl = 'https://xlap.tech';
        }

        Mail::assertSent(UserLoginCredentialsMail::class);

        Mail::assertSent(UserLoginCredentialsMail::class, function (UserLoginCredentialsMail $mail) use ($user, $expectedAppUrl): bool {
            return $mail->hasTo($user->email)
                && $mail->user->getKey() === $user->getKey()
                && $mail->user->username === 'mail_login_user'
                && $mail->plainPassword === 'Password12345'
                && $mail->appUrl === $expectedAppUrl
                && $mail->loginUrl === $expectedAppUrl.'/login';
        });
    }

    public function test_admin_can_toggle_product_access_without_user_name_matching_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Any Operator']);
        $product = Product::where('slug', 'ornament')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditUser::class)
            ->call('openModal', 'modals.admin.edit-user', ['userId' => $user->id])
            ->set('selectedProducts', [$product->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($user->products()->whereKey($product->id)->exists());
    }

    public function test_admin_can_edit_product_background_removal_setting_from_modal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $product->update(['auto_remove_background' => true]);

        $this->actingAs($admin);

        Livewire::test(EditProductBackgroundRemoval::class)
            ->call('openModal', 'modals.admin.edit-product-background-removal', ['productId' => $product->id])
            ->assertSet('isOpen', true)
            ->assertSet('autoRemoveBackground', '1')
            ->set('autoRemoveBackground', '0')
            ->set('backgroundRemovalEngine', 'local_rembg')
            ->call('save')
            ->assertDispatched('toast');

        $this->assertFalse($product->refresh()->auto_remove_background);
        $this->assertSame('local_rembg', $product->refresh()->background_removal_engine);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'admin.product_background_removal_updated',
        ]);
    }

    public function test_product_background_removal_requires_global_and_product_setting(): void
    {
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $service = app(ProductBackgroundRemovalService::class);

        config(['services.background_removal.enabled' => true]);
        $product->update(['auto_remove_background' => true]);
        $this->assertTrue($service->enabledFor($product->refresh()));

        $product->update(['auto_remove_background' => false]);
        $this->assertFalse($service->enabledFor($product->refresh()));

        config(['services.background_removal.enabled' => false]);
        $product->update(['auto_remove_background' => true]);
        $this->assertFalse($service->enabledFor($product->refresh()));
    }

    public function test_product_background_removal_uses_the_product_selected_engine(): void
    {
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $product->update(['background_removal_engine' => 'local_rembg']);

        $this->assertSame('local_rembg', app(ProductBackgroundRemovalService::class)->engineFor($product->refresh()));
    }

    public function test_admin_can_create_user_by_copying_vertex_credential_from_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $source->vertexApiCredential()->create([
            'project_id' => 'copy-project',
            'location' => 'global',
            'client_email' => 'copy@example.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\ncopy\n-----END PRIVATE KEY-----\n",
            'credentials_json' => [
                'project_id' => 'copy-project',
                'client_email' => 'copy@example.iam.gserviceaccount.com',
                'private_key' => "-----BEGIN PRIVATE KEY-----\ncopy\n-----END PRIVATE KEY-----\n",
            ],
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(AddUser::class)
            ->call('openModal', 'modals.admin.add-user')
            ->set('name', 'Sticker Copied Vertex User')
            ->set('email', 'copied-vertex@example.com')
            ->set('password', 'Password12345')
            ->set('selectedProducts', [$product->id])
            ->set('vertexMode', 'copy')
            ->set('vertexCopyUserId', $source->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'copied-vertex@example.com')->firstOrFail();
        $credential = $user->vertexApiCredential()->firstOrFail();

        $this->assertSame('copy-project', $credential->project_id);
        $this->assertSame('copy@example.iam.gserviceaccount.com', $credential->client_email);
        $this->assertSame("-----BEGIN PRIVATE KEY-----\ncopy\n-----END PRIVATE KEY-----\n", $credential->private_key);
        $this->assertSame('copy-project', $credential->credentials_json['project_id']);
    }

    public function test_non_admin_user_cannot_open_activity_logs_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('offorest.admin.logs'))
            ->assertForbidden();
    }

    public function test_edit_product_detail_modal_updates_asset_without_page_reload(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'old sticker',
            'image_link' => 'https://example.com/old.png',
        ]);

        $this->actingAs($user);

        Livewire::test(EditProductDetail::class)
            ->call('open', $asset->id)
            ->assertSet('isOpen', true)
            ->assertSet('keyword', 'old sticker')
            ->set('keyword', 'new sticker')
            ->set('imageLink', 'https://example.com/new.jpg')
            ->call('save')
            ->assertSet('isOpen', false);

        $this->assertDatabaseHas('product_design_assets', [
            'id' => $asset->id,
            'keyword' => 'new sticker',
            'image_link' => 'https://example.com/new.jpg',
        ]);
    }

    public function test_sticker_service_creates_asset_with_normalized_source_details(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        $asset = app(StickerService::class)->createAsset(
            $user,
            '  cute cat sticker  ',
            '  https://example.com/source.png  ',
        );

        $this->assertSame(1, $asset->item_number);
        $this->assertSame('cute cat sticker', $asset->keyword);
        $this->assertSame('https://example.com/source.png', $asset->image_link);
    }

    public function test_sticker_keyword_must_contain_product_slug(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Keyword phai chua tu 'sticker'");

        app(StickerService::class)->createAsset(
            $user,
            'cute cat',
            'https://example.com/source.png',
        );
    }

    public function test_sticker_add_modal_shows_keyword_validation_error_without_server_error(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        $this->actingAs($user);

        Livewire::test(AddProductDesign::class)
            ->set('isOpen', true)
            ->set('keyword', 'lap')
            ->set('imageLink', 'https://example.com/source.png')
            ->call('save')
            ->assertHasErrors(['keyword']);
    }

    public function test_ornament_keyword_must_contain_product_slug(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $user->products()->attach($product);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Keyword phai chua tu 'ornament'");

        app(OrnamentAmazonService::class)->createAsset(
            $user,
            'cute cat',
            'https://example.com/source.png',
        );
    }

    public function test_ornament_service_stores_scraped_listing_images_and_data(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $user->products()->attach($product);

        $asset = app(OrnamentAmazonService::class)->createAsset(
            $user,
            'Music Note Stained Glass Ornament',
            'https://example.com/main.jpg',
            [
                'https://example.com/main.jpg',
                'https://example.com/sub-1.jpg',
                'https://example.com/sub-2.jpg',
                'https://example.com/'.str_repeat('a', 1100).'/sub-3.jpg',
            ],
            [
                'platform' => 'amazon',
                'productTitle' => 'Music Note Stained Glass Ornament',
                'link' => 'https://www.amazon.com/dp/example',
                'bulletPoints' => ['First bullet', 'Second bullet'],
                'aplusText' => ['A plus copy'],
                'ignored' => 'not stored',
            ],
        );

        $asset->refresh();

        $this->assertSame('Music Note Stained Glass Ornament', $asset->keyword);
        $this->assertSame('https://example.com/main.jpg', $asset->image_link);
        $this->assertSame([
            'https://example.com/sub-1.jpg',
            'https://example.com/sub-2.jpg',
            'https://example.com/'.str_repeat('a', 1100).'/sub-3.jpg',
        ], $asset->image_sub);
        $this->assertSame('Music Note Stained Glass Ornament', $asset->data_item_add['productTitle']);
        $this->assertSame(['First bullet', 'Second bullet'], $asset->data_item_add['bulletPoints']);
        $this->assertSame(['A plus copy'], $asset->data_item_add['aplusText']);
        $this->assertArrayNotHasKey('ignored', $asset->data_item_add);
    }

    public function test_ornament_add_modal_shows_keyword_validation_error_without_server_error(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $user->products()->attach($product);

        $this->actingAs($user);

        Livewire::test(OrnamentAddProductDesign::class)
            ->set('isOpen', true)
            ->set('keyword', 'lap')
            ->set('imageLink', 'https://example.com/source.png')
            ->call('save')
            ->assertHasErrors(['keyword']);
    }

    public function test_ornament_add_modal_stores_scraped_listing_payload(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'ornament')->firstOrFail();
        $user->products()->attach($product);

        $this->actingAs($user);

        Livewire::test(OrnamentAddProductDesign::class)
            ->set('isOpen', true)
            ->set('keyword', 'Music Note Ornament')
            ->set('imageLink', 'https://example.com/main.jpg')
            ->set('competitorListing', [
                'platform' => 'amazon',
                'productTitle' => 'Music Note Ornament',
                'link' => 'https://www.amazon.com/dp/example',
                'bulletPoints' => ['First bullet', 'Second bullet'],
                'images' => [
                    'https://example.com/main.jpg',
                    'https://example.com/sub-1.jpg',
                    'https://example.com/sub-2.jpg',
                ],
            ])
            ->call('save')
            ->assertSet('isOpen', false);

        $asset = ProductDesignAsset::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('https://example.com/main.jpg', $asset->image_link);
        $this->assertSame([
            'https://example.com/sub-1.jpg',
            'https://example.com/sub-2.jpg',
        ], $asset->image_sub);
        $this->assertSame('Music Note Ornament', $asset->data_item_add['productTitle']);
    }

    public function test_sticker_source_details_cannot_be_edited_after_master_is_created(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'locked sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
        ]);

        $this->actingAs($user);

        Livewire::test(EditProductDetail::class)
            ->call('open', $asset->id)
            ->assertSet('isOpen', false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Item da co Create Master nen khong the edit.');

        app(StickerService::class)->updateProductDetail(
            $user,
            $asset->id,
            'updated sticker',
            'https://example.com/updated.png',
        );
    }

    public function test_sticker_service_generates_redesign_and_final_images(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        foreach ([1, 2, 3, 4] as $promptNumber) {
            Prompt::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'prompt_number' => $promptNumber,
                'content' => "Prompt {$promptNumber}",
            ]);
        }

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'cat sticker',
            'image_link' => 'https://example.com/source.png',
        ]);

        $this->mock(VertexImageGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (User $user, string $imageUri, string $prompt, string $folder): bool => $imageUri === 'https://example.com/source.png'
                    && $prompt === 'Prompt 1'
                    && $folder === 'generated/sticker/redesign')
                ->andReturn('/storage/generated/sticker/redesign/master.png');

            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (User $user, string $imageUri, string $prompt, string $folder): bool => $imageUri === '/storage/generated/sticker/redesign/master.png'
                    && $prompt === 'Prompt 2'
                    && $folder === 'generated/sticker/final')
                ->andReturn('/storage/generated/sticker/final/lifestyle.png');

            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (User $user, string $imageUri, string $prompt, string $folder): bool => $imageUri === '/storage/generated/sticker/redesign/master.png'
                    && $prompt === 'Prompt 3'
                    && $folder === 'generated/sticker/final')
                ->andReturn('/storage/generated/sticker/final/mockup.png');

            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (User $user, string $imageUri, string $prompt, string $folder): bool => $imageUri === '/storage/generated/sticker/redesign/master.png'
                    && $prompt === 'Prompt 4'
                    && $folder === 'generated/sticker/final')
                ->andReturn('/storage/generated/sticker/final/lifestyle3.png');
        });

        $service = app(StickerService::class);

        $service->generateRedesign($user, $asset->id);
        $service->generateFinalImages($user, $asset->id);

        $this->assertDatabaseHas('product_design_assets', [
            'id' => $asset->id,
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'lifestyle1' => '/storage/generated/sticker/final/lifestyle.png',
            'lifestyle2' => '/storage/generated/sticker/final/mockup.png',
            'lifestyle3' => '/storage/generated/sticker/final/lifestyle3.png',
        ]);
    }

    public function test_sticker_redesign_candidates_are_kept_and_can_be_selected_again(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $repository = app(ProductDesignAssetRepository::class);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'lap sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/first.png',
        ]);

        $asset = $repository->updateRedesign($asset, '/storage/generated/sticker/redesign/second.png');

        $this->assertSame('/storage/generated/sticker/redesign/second.png', $asset->redesign);
        $this->assertSame([
            '/storage/generated/sticker/redesign/first.png',
            '/storage/generated/sticker/redesign/second.png',
        ], $asset->redesign_candidates);

        $asset = app(StickerService::class)->selectRedesign($user, $asset->id, '/storage/generated/sticker/redesign/first.png');

        $this->assertSame('/storage/generated/sticker/redesign/first.png', $asset->redesign);
        $this->assertContains('/storage/generated/sticker/redesign/second.png', $asset->redesign_candidates);
    }

    public function test_sticker_master_cannot_be_changed_after_custom_mockup_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'locked master',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'mockup1' => '/storage/generated/sticker/mockups/1/mockup.png',
        ]);

        try {
            app(StickerService::class)->generateRedesign($user, $asset->id);
            $this->fail('Expected Create Master generation to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Item da co Mockup Tu Chon nen khong the tao lai Create Master.', $exception->getMessage());
        }

        try {
            app(StickerService::class)->selectRedesign($user, $asset->id, '/storage/generated/sticker/redesign/other.png');
            $this->fail('Expected selecting another master image to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Item da co Mockup Tu Chon nen khong the tao lai Create Master.', $exception->getMessage());
        }
    }

    public function test_creating_sticker_item_from_redesign_candidate_removes_it_from_source_candidates(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $source = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'lap sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/current.png',
            'redesign_candidates' => [
                '/storage/generated/sticker/redesign/old.png',
                '/storage/generated/sticker/redesign/current.png',
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(AddProductDesign::class)
            ->call('open', 'lap sticker', '/storage/generated/sticker/redesign/old.png', $source->id, '/storage/generated/sticker/redesign/old.png')
            ->call('save');

        $this->assertDatabaseHas('product_design_assets', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 2,
            'keyword' => 'lap sticker',
            'image_link' => '/storage/generated/sticker/redesign/old.png',
        ]);
        $this->assertSame([
            '/storage/generated/sticker/redesign/current.png',
        ], $source->refresh()->redesign_candidates);
    }

    public function test_user_can_store_many_psd_templates_but_only_one_is_active_for_sticker_custom_mockup(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        $service = app(PsdMockupTemplateService::class);

        $first = $service->uploadStickerTemplate(
            $user,
            UploadedFile::fake()->create('first.psd', 10, 'application/octet-stream'),
            'First PSD',
        );

        $second = $service->uploadStickerTemplate(
            $user,
            UploadedFile::fake()->create('second.psd', 10, 'application/octet-stream'),
            'Second PSD',
        );

        $this->assertDatabaseHas('psd_mockup_templates', [
            'id' => $first->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('psd_mockup_templates', [
            'id' => $second->id,
            'is_active' => true,
        ]);

        $this->assertSame(2, $service->stickerTemplatesForUser($user)->count());
        $this->assertTrue($service->activeStickerTemplateForUser($user)->is($second));
    }

    public function test_sticker_service_renders_psd_mockups_from_active_template(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $user->products()->attach($product);

        app(PsdMockupTemplateService::class)->uploadStickerTemplate(
            $user,
            UploadedFile::fake()->create('mockup.psd', 10, 'application/octet-stream'),
            'Sticker PSD',
        );

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'cat sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'mockup4' => '/storage/generated/sticker/mockups/1/old.png',
        ]);

        $this->mock(PsdMockupRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')
                ->once()
                ->withArgs(fn ($template, string $masterImageUri, int $assetId): bool => $template->name === 'Sticker PSD'
                    && $masterImageUri === '/storage/generated/sticker/redesign/master.png'
                    && $assetId > 0)
                ->andReturn([
                    '/storage/generated/sticker/mockups/1/MOCKUP 1.png',
                    '/storage/generated/sticker/mockups/1/MOCKUP 2.png',
                ]);
        });

        app(StickerService::class)->generatePsdMockups($user, $asset->id);

        $this->assertDatabaseHas('product_design_assets', [
            'id' => $asset->id,
            'mockup1' => '/storage/generated/sticker/mockups/1/MOCKUP 1.png',
            'mockup2' => '/storage/generated/sticker/mockups/1/MOCKUP 2.png',
            'mockup3' => null,
            'mockup4' => null,
        ]);
    }

    public function test_sticker_item_can_only_be_approved_after_a_mockup_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'cat sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can co it nhat mot anh mockup hoac lifestyle truoc khi duyet.');

        app(StickerService::class)->toggleApproval($user, $asset->id);
    }

    public function test_sticker_item_approval_toggles_after_a_mockup_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'cat sticker',
            'image_link' => 'https://example.com/source.png',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'mockup1' => '/storage/generated/sticker/mockups/1/MOCKUP 1.png',
        ]);

        $approved = app(StickerService::class)->toggleApproval($user, $asset->id);
        $this->assertTrue($approved->is_approved);
        $this->assertNotNull($approved->approved_at);
        $this->assertDatabaseHas('product_drive_uploads', [
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'waiting',
        ]);

        $unapproved = app(StickerService::class)->toggleApproval($user, $asset->id);
        $this->assertFalse($unapproved->is_approved);
        $this->assertNull($unapproved->approved_at);
        $this->assertDatabaseMissing('product_drive_uploads', [
            'product_design_asset_id' => $asset->id,
            'status' => 'waiting',
        ]);
    }

    public function test_sticker_item_can_be_approved_after_a_lifestyle_image_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'cat sticker',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
            'lifestyle1' => '/storage/generated/sticker/final/lifestyle.png',
        ]);

        $approved = app(StickerService::class)->toggleApproval($user, $asset->id);

        $this->assertTrue($approved->is_approved);
    }

    public function test_approved_sticker_item_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'locked sticker',
            'image_link' => 'https://example.com/source.png',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Item da duyet. Hay bo duyet truoc khi edit.');

        app(StickerService::class)->updateProductDetail(
            $user,
            $asset->id,
            'changed sticker',
            'https://example.com/changed.png',
        );
    }

    public function test_sticker_assets_can_be_filtered_by_workflow_status(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();

        ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'not started',
        ]);

        ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 2,
            'keyword' => 'pending review',
            'redesign' => '/storage/generated/sticker/redesign/master.png',
        ]);

        ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 3,
            'keyword' => 'approved',
            'redesign' => '/storage/generated/sticker/redesign/approved.png',
            'mockup1' => '/storage/generated/sticker/mockups/approved.png',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $service = app(StickerService::class);

        $this->assertSame(3, $service->paginatedAssetsForUser($user, 10, 'all')->total());
        $this->assertSame(2, $service->paginatedAssetsForUser($user, 10, 'unapproved')->total());
        $this->assertSame(1, $service->paginatedAssetsForUser($user, 10, 'approved')->total());
        $this->assertSame([
            'all' => 3,
            'unapproved' => 2,
            'approved' => 1,
        ], $service->statusCountsForUser($user));
    }

    public function test_sticker_assets_can_be_searched_by_keyword_id_and_stt(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'sticker')->firstOrFail();

        $first = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 7,
            'keyword' => 'Halloween ghost',
        ]);

        $second = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 12,
            'keyword' => 'Christmas cat',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $service = app(StickerService::class);

        $this->assertSame([$first->id], $service->paginatedAssetsForUser($user, 10, 'all', 'page', 'ghost')->pluck('id')->all());
        $this->assertSame([$second->id], $service->paginatedAssetsForUser($user, 10, 'all', 'page', (string) $second->id)->pluck('id')->all());
        $this->assertSame([$first->id], $service->paginatedAssetsForUser($user, 10, 'all', 'page', '7')->pluck('id')->all());
        $this->assertSame([
            'all' => 1,
            'unapproved' => 0,
            'approved' => 1,
        ], $service->statusCountsForUser($user, 'cat'));
    }

    public function test_approved_local_images_are_uploaded_to_drive_and_removed_locally(): void
    {
        $user = User::factory()->create(['name' => 'Xuan Lap']);
        $product = Product::where('slug', 'sticker')->firstOrFail();
        $relativePath = 'generated/test-drive/export.png';
        $absolutePath = public_path('storage/'.$relativePath);
        $candidateRelativePath = 'generated/test-drive/candidate.png';
        $candidateAbsolutePath = public_path('storage/'.$candidateRelativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, 'fake image bytes');
        File::put($candidateAbsolutePath, 'fake candidate bytes');

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'drive export',
            'redesign' => '/storage/'.$relativePath,
            'redesign_candidates' => [
                '/storage/'.$candidateRelativePath,
            ],
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock) use ($absolutePath, $asset): void {
            $mock->shouldReceive('findOrCreateFolderPath')
                ->once()
                ->withArgs(fn (array $folders): bool => $folders === ['xuanlap', (string) $asset->id])
                ->andReturn([
                    'id' => 'folder-id',
                    'link' => 'https://drive.google.com/drive/folders/folder-id',
                ]);

            $mock->shouldReceive('uploadLocalFile')
                ->once()
                ->withArgs(fn (string $path, string $filename, ?string $mimeType, ?string $folderId): bool => $path === $absolutePath
                    && $filename === "{$asset->id}_item1_xuanlap.png"
                    && $folderId === 'folder-id')
                ->andReturn('https://drive.google.com/file/d/example/view');
        });

        $result = app(ApprovedAssetDriveExportService::class)->exportApprovedImages();

        $this->assertSame(['assets' => 1, 'images' => 1], $result);
        $this->assertDatabaseHas('product_design_assets', [
            'id' => $asset->id,
            'redesign' => 'https://drive.google.com/file/d/example/view',
        ]);
        $this->assertDatabaseMissing('product_design_assets', [
            'id' => $asset->id,
            'drive_uploaded_at' => null,
        ]);
        $this->assertFileDoesNotExist($absolutePath);
        $this->assertFileDoesNotExist($candidateAbsolutePath);
        $this->assertNull($asset->refresh()->redesign_candidates);
        $this->assertDatabaseHas('product_drive_uploads', [
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'drive_folder_id' => 'folder-id',
        ]);
        $upload = ProductDriveUpload::where('product_design_asset_id', $asset->id)->firstOrFail();
        $this->assertSame("{$asset->id}_item1_xuanlap.png", $upload->drive_files[0]['filename']);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'drive_export.image_uploaded',
            'subject_type' => ProductDesignAsset::class,
            'subject_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'drive_export.redesign_candidate_deleted',
            'subject_type' => ProductDesignAsset::class,
            'subject_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'drive_export.completed',
        ]);
    }

    public function test_drive_preview_falls_back_to_authenticated_download_when_thumbnail_is_not_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Http::fake([
            'drive.google.com/*' => Http::response('<html>not an image</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('downloadImageFile')
                ->once()
                ->with('abc123')
                ->andReturn([
                    'body' => 'image-bytes',
                    'content_type' => 'image/png',
                ]);
        });

        $previewUrl = URL::temporarySignedRoute(
            'image-preview.show',
            now()->addMinutes(5),
            ['url' => 'https://drive.google.com/thumbnail?id=abc123&sz=w800'],
        );

        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('image-bytes');
    }
}
