<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\GoogleDriveOAuthController;
use App\Http\Controllers\IdeaEtsyExtensionDownloadController;
use App\Http\Controllers\IdeaAmazonExtensionDownloadController;
use App\Http\Controllers\ImagePreviewController;
use App\Http\Controllers\SuncatcherWorkflowImageController;
use App\Http\Controllers\OrnamentAmazonTwoWorkflowImageController;
use App\Http\Controllers\Webhook\TelegramWebhookController;
use App\Livewire\Pages\Admin\ActivityLogs;
use App\Livewire\Pages\Admin\AiModels;
use App\Livewire\Pages\Admin\ApiCredits;
use App\Livewire\Pages\Admin\FinancialManagement;
use App\Livewire\Pages\Admin\ListUser;
use App\Livewire\Pages\Admin\MailTest;
use App\Livewire\Pages\Dashboard\Index as DashboardIndex;
use App\Livewire\Pages\Drive\DriveUploads;
use App\Livewire\Pages\Financial\FinancialManagement as UserFinancialManagement;
use App\Livewire\Pages\Marketplace\MarketplaceExports;
use App\Livewire\Pages\Marketplace\ListingMetadataStatus;
use App\Livewire\Pages\Suncatcher\AutomationCatalog;
use App\Livewire\Pages\Salary\Wali;
use App\Livewire\Modals\Salary\MonthSummary;
use App\Support\ProductRegistry;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['guest'])->group(function () {
    Volt::route('login', 'pages.auth.login')->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');
Route::post('webhook/telegram', [TelegramWebhookController::class, 'handle'])
    ->name('webhook.telegram');

Route::get('image-preview', ImagePreviewController::class)
    ->middleware(['auth', 'signed'])
    ->name('image-preview.show');

Route::middleware(['auth', 'verified'])->prefix('offorest')->group(function (): void {
    foreach (ProductRegistry::all() as $product) {
        Route::get($product['path'], $product['component'])
            ->middleware('product:'.$product['slug'])
            ->name($product['route_name']);
    }
    Route::redirect('ornament-ornament', 'suncatcher');

    Route::get('financial-management', UserFinancialManagement::class)
        ->middleware('financial')
        ->name('offorest.financial-management');
    Route::get('admin/users', ListUser::class)
        ->middleware('admin')
        ->name('offorest.admin.users');

    Route::get('admin/logs', ActivityLogs::class)
        ->middleware('admin')
        ->name('offorest.admin.logs');

    Route::get('admin/api-credits', ApiCredits::class)
        ->middleware('admin')
        ->name('offorest.admin.api-credits');

    Route::get('admin/financial-management', FinancialManagement::class)
        ->middleware('admin')
        ->name('offorest.admin.financial-management');

    Route::get('admin/ai-models', AiModels::class)
        ->middleware('admin')
        ->name('offorest.admin.ai-models');

    Route::get('admin/mail-test', MailTest::class)
        ->middleware('admin')
        ->name('offorest.admin.mail-test');

    Route::get('listing-metadata', ListingMetadataStatus::class)
        ->name('offorest.listing-metadata');


    Route::post('admin/debug/listing-metadata/{asset}/retry', function (App\Models\ProductDesignAsset $asset) {
        try {
            $result = app(App\Services\Marketplace\MarketplaceListingMetadataService::class)
                ->retryApprovedAsset($asset->id);

            return response()->json([
                'ok' => true,
                'asset' => [
                    'id' => $result?->id,
                    'product_slug' => $result?->product?->slug,
                    'title' => $result?->title,
                    'description' => $result?->description,
                    'bullet_point_1' => $result?->bullet_point_1,
                    'bullet_point_2' => $result?->bullet_point_2,
                    'bullet_point_3' => $result?->bullet_point_3,
                    'bullet_point_4' => $result?->bullet_point_4,
                    'bullet_point_5' => $result?->bullet_point_5,
                    'generic_keyword' => $result?->generic_keyword,
                    'tags' => $result?->tags,
                    'marketplace_listing_status' => $result?->marketplace_listing_status,
                    'marketplace_listing_error' => $result?->marketplace_listing_error,
                    'marketplace_listing_attempts' => $result?->marketplace_listing_attempts,
                    'marketplace_listing_started_at' => optional($result?->marketplace_listing_started_at)?->toDateTimeString(),
                    'marketplace_listing_completed_at' => optional($result?->marketplace_listing_completed_at)?->toDateTimeString(),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
            ], 500);
        }
    })->middleware('admin')->name('offorest.admin.debug.listing-metadata.retry');

    Route::get('suncatcher-catalog', AutomationCatalog::class)
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.catalog');

    Route::get('exports', MarketplaceExports::class)
        ->name('offorest.exports');

    Route::get('drive-uploads', DriveUploads::class)
        ->name('offorest.drive-uploads');

    Route::get('salary/wali', Wali::class)
        ->middleware('wali')
        ->name('offorest.salary.wali');

    Route::get('salary/wali/export/{year}/{month}', function (int $year, int $month) {
        abort_unless(auth()->user() && ((bool) auth()->user()->is_admin || (bool) auth()->user()->can_access_wali), 403);

        $month = \Carbon\CarbonImmutable::create($year, max(1, min(12, $month)), 1)->startOfMonth();
        $rows = app(\App\Livewire\Pages\Salary\Wali::class)->exportRowsForMonth($month);
        $filename = 'wali-'.$month->format('m-Y').'.xlsx';
        $tempPath = storage_path('app/tmp/'.$filename);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        app(\App\Support\Salary\WaliSalaryExcelExporter::class)->create($month, $rows, $tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    })->middleware('wali')->name('offorest.salary.wali.export');

    Route::get('idea-etsy/extension/download', IdeaEtsyExtensionDownloadController::class)
        ->middleware('product:idea-etsy')
        ->name('offorest.idea-etsy.extension.download');

    Route::get('idea-amazon/extension/download', IdeaAmazonExtensionDownloadController::class)
        ->middleware('product:idea-amazon')
        ->name('offorest.idea-amazon.extension.download');

    Route::post('suncatcher/workflow/{asset}/listing-images/prepare', [SuncatcherWorkflowImageController::class, 'prepare'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.listing-images.prepare');

    Route::get('suncatcher/workflow/{asset}/listing-images/status', [SuncatcherWorkflowImageController::class, 'status'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.listing-images.status');

    Route::get('suncatcher/workflow/{asset}/mockups/download', [SuncatcherWorkflowImageController::class, 'downloadMockups'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.mockups.download');

    Route::post('suncatcher/workflow/{asset}/redesign', [SuncatcherWorkflowImageController::class, 'redesign'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.redesign');

    Route::post('suncatcher/workflow/{asset}/script', [SuncatcherWorkflowImageController::class, 'script'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.script');

    Route::post('suncatcher/workflow/{asset}/person/{person}', [SuncatcherWorkflowImageController::class, 'person'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.person');

    Route::post('suncatcher/workflow/{asset}/listing-images/{slot}', [SuncatcherWorkflowImageController::class, 'generate'])
        ->middleware('product:suncatcher')
        ->name('offorest.suncatcher.workflow.listing-images.generate');

    Route::post('ornament-amazon-2/workflow/{asset}/listing-images/prepare', [OrnamentAmazonTwoWorkflowImageController::class, 'prepare'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.listing-images.prepare');

    Route::get('ornament-amazon-2/workflow/{asset}/listing-images/status', [OrnamentAmazonTwoWorkflowImageController::class, 'status'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.listing-images.status');

    Route::get('ornament-amazon-2/workflow/{asset}/mockups/download', [OrnamentAmazonTwoWorkflowImageController::class, 'downloadMockups'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.mockups.download');

    Route::post('ornament-amazon-2/workflow/{asset}/redesign', [OrnamentAmazonTwoWorkflowImageController::class, 'redesign'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.redesign');

    Route::post('ornament-amazon-2/workflow/{asset}/script', [OrnamentAmazonTwoWorkflowImageController::class, 'script'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.script');

    Route::post('ornament-amazon-2/workflow/{asset}/person/{person}', [OrnamentAmazonTwoWorkflowImageController::class, 'person'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.person');

    Route::post('ornament-amazon-2/workflow/{asset}/listing-images/{slot}', [OrnamentAmazonTwoWorkflowImageController::class, 'generate'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.listing-images.generate');

    Route::get('admin/google-drive/connect', [GoogleDriveOAuthController::class, 'connect'])
        ->middleware('admin')
        ->name('offorest.admin.google-drive.connect');

    Route::get('admin/google-drive/callback', [GoogleDriveOAuthController::class, 'callback'])
        ->middleware('admin')
        ->name('offorest.admin.google-drive.callback');
});

require __DIR__.'/auth.php';


