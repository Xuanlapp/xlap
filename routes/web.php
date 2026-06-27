<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\GoogleDriveOAuthController;
use App\Http\Controllers\IdeaEtsyExtensionDownloadController;
use App\Http\Controllers\IdeaAmazonExtensionDownloadController;
use App\Http\Controllers\ImagePreviewController;
use App\Http\Controllers\OrnamentAmazonWorkflowImageController;
use App\Http\Controllers\OrnamentAmazonTwoWorkflowImageController;
use App\Http\Controllers\Webhook\TelegramWebhookController;
use App\Livewire\Pages\Admin\ActivityLogs;
use App\Livewire\Pages\Admin\ApiCredits;
use App\Livewire\Pages\Admin\ListUser;
use App\Livewire\Pages\Admin\MailTest;
use App\Livewire\Pages\Drive\DriveUploads;
use App\Livewire\Pages\Marketplace\MarketplaceExports;
use App\Livewire\Pages\Marketplace\ListingMetadataStatus;
use App\Livewire\Pages\OrnamentAmazon\AutomationCatalog;
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

Route::view('dashboard', 'dashboard')
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

    Route::get('admin/users', ListUser::class)
        ->middleware('admin')
        ->name('offorest.admin.users');

    Route::get('admin/logs', ActivityLogs::class)
        ->middleware('admin')
        ->name('offorest.admin.logs');

    Route::get('admin/api-credits', ApiCredits::class)
        ->middleware('admin')
        ->name('offorest.admin.api-credits');

    Route::get('admin/mail-test', MailTest::class)
        ->middleware('admin')
        ->name('offorest.admin.mail-test');

    Route::get('listing-metadata', ListingMetadataStatus::class)
        ->name('offorest.listing-metadata');

    Route::get('ornament-amazon-catalog', AutomationCatalog::class)
        ->name('offorest.ornament-amazon.catalog');

    Route::get('exports', MarketplaceExports::class)
        ->name('offorest.exports');

    Route::get('drive-uploads', DriveUploads::class)
        ->name('offorest.drive-uploads');

    Route::get('idea-etsy/extension/download', IdeaEtsyExtensionDownloadController::class)
        ->middleware('product:idea-etsy')
        ->name('offorest.idea-etsy.extension.download');

    Route::get('idea-amazon/extension/download', IdeaAmazonExtensionDownloadController::class)
        ->middleware('product:idea-amazon')
        ->name('offorest.idea-amazon.extension.download');

    Route::post('ornament/workflow/{asset}/listing-images/prepare', [OrnamentAmazonWorkflowImageController::class, 'prepare'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.listing-images.prepare');

    Route::get('ornament/workflow/{asset}/listing-images/status', [OrnamentAmazonWorkflowImageController::class, 'status'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.listing-images.status');

    Route::post('ornament/workflow/{asset}/redesign', [OrnamentAmazonWorkflowImageController::class, 'redesign'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.redesign');

    Route::post('ornament/workflow/{asset}/script', [OrnamentAmazonWorkflowImageController::class, 'script'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.script');

    Route::post('ornament/workflow/{asset}/person/{person}', [OrnamentAmazonWorkflowImageController::class, 'person'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.person');

    Route::post('ornament/workflow/{asset}/listing-images/{slot}', [OrnamentAmazonWorkflowImageController::class, 'generate'])
        ->middleware('product:ornament')
        ->name('offorest.ornament.workflow.listing-images.generate');

    Route::post('ornament-amazon-2/workflow/{asset}/listing-images/prepare', [OrnamentAmazonTwoWorkflowImageController::class, 'prepare'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.listing-images.prepare');

    Route::get('ornament-amazon-2/workflow/{asset}/listing-images/status', [OrnamentAmazonTwoWorkflowImageController::class, 'status'])
        ->middleware('product:ornament-amazon-2')
        ->name('offorest.ornament-amazon-2.workflow.listing-images.status');

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

