<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

DB::transaction(function () {
    $source = DB::table('users')->where('id', 1)->first();
    if (! $source) {
        throw new RuntimeException('Source user #1 not found.');
    }

    DB::table('users')->where('id', 1)->update([
        'email' => 'duansticker8386@gmail.com',
        'updated_at' => now(),
    ]);

    $adminId = DB::table('users')->where('username', 'adminxlap')->value('id');
    $adminPayload = [
        'name' => 'Admin XLAP',
        'username' => 'adminxlap',
        'email' => 'xuanlap250203@gmail.com',
        'password' => Hash::make('Nhxlap@2502!'),
        'status' => 'active',
        'role' => 'admin',
        'is_admin' => true,
        'can_generate_amazon_listing' => (bool) ($source->can_generate_amazon_listing ?? false),
        'can_generate_etsy_listing' => (bool) ($source->can_generate_etsy_listing ?? false),
        'can_access_wali' => (bool) ($source->can_access_wali ?? false),
        'updated_at' => now(),
    ];

    if ($adminId) {
        DB::table('users')->where('id', $adminId)->update($adminPayload);
    } else {
        $adminPayload['created_at'] = now();
        $adminId = DB::table('users')->insertGetId($adminPayload);
    }

    DB::table('product_user')->where('user_id', $adminId)->delete();
    $products = DB::table('product_user')->where('user_id', 1)->get();
    foreach ($products as $product) {
        DB::table('product_user')->insert([
            'user_id' => $adminId,
            'product_id' => $product->product_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('user_ai_providers')->where('user_id', $adminId)->delete();
    $providers = DB::table('user_ai_providers')->where('user_id', 1)->get();
    foreach ($providers as $provider) {
        DB::table('user_ai_providers')->insert([
            'user_id' => $adminId,
            'provider_key' => $provider->provider_key,
            'is_enabled' => $provider->is_enabled,
            'is_default' => $provider->is_default,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('vertex_api_credentials')->where('user_id', $adminId)->delete();
    $vertex = DB::table('vertex_api_credentials')->where('user_id', 1)->first();
    if ($vertex) {
        DB::table('vertex_api_credentials')->insert([
            'user_id' => $adminId,
            'function_key' => $vertex->function_key,
            'project_id' => $vertex->project_id,
            'location' => $vertex->location,
            'client_email' => $vertex->client_email,
            'private_key' => $vertex->private_key,
            'credentials_json' => $vertex->credentials_json,
            'is_active' => $vertex->is_active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('user_api_credentials')->where('user_id', $adminId)->delete();
    $apiCredentials = DB::table('user_api_credentials')->where('user_id', 1)->get();
    foreach ($apiCredentials as $credential) {
        DB::table('user_api_credentials')->insert([
            'user_id' => $adminId,
            'provider_key' => $credential->provider_key,
            'name' => $credential->name,
            'key_api' => $credential->key_api,
            'is_active' => $credential->is_active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $managerId = DB::table('users')->where('username', 'manager')->value('id');
    $managerPayload = [
        'name' => 'Manager Test',
        'username' => 'manager',
        'email' => 'manager@gmail.com',
        'password' => Hash::make('12345678'),
        'status' => 'active',
        'role' => 'manager',
        'is_admin' => false,
        'can_generate_amazon_listing' => false,
        'can_generate_etsy_listing' => false,
        'can_access_wali' => false,
        'updated_at' => now(),
    ];

    if ($managerId) {
        DB::table('users')->where('id', $managerId)->update($managerPayload);
    } else {
        $managerPayload['created_at'] = now();
        DB::table('users')->insert($managerPayload);
    }
});

echo "Done\n";
