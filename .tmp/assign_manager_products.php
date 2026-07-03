<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$managerId = DB::table('users')->where('username', 'manager')->value('id');
if (! $managerId) {
    throw new RuntimeException('Manager user not found.');
}

DB::table('product_user')->where('user_id', $managerId)->delete();

$products = DB::table('products')
    ->where('is_active', true)
    ->pluck('id');

foreach ($products as $productId) {
    DB::table('product_user')->insert([
        'user_id' => $managerId,
        'product_id' => $productId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "Assigned ".count($products)." products to manager\n";
