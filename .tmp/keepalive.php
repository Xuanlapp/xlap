<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Livewire\Pages\Marketplace\MarketplaceExports;
use App\Livewire\Pages\OrnamentAmazon\AutomationCatalog;
use App\Models\User;

$manager = User::where('username', 'manager')->first();
if (! $manager) { throw new RuntimeException('manager not found'); }

auth()->login($manager);

$exportPage = app(MarketplaceExports::class);
$catalogPage = app(AutomationCatalog::class);

echo 'manager=' . $manager->id . PHP_EOL;
