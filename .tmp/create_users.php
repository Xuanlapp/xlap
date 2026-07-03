<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\UserAiProvider;
use App\Models\VertexApiCredential;
use App\Models\UserApiCredential;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $source = User::query()->findOrFail(1);

    // Update current xlap admin email.
    $source->update([
        'email' => 'duansticker8386@gmail.com',
    ]);

    // Create or update the new admin user.
    $admin = User::query()->updateOrCreate(
        ['username' => 'adminxlap'],
        [
            'name' => 'Admin XLAP',
            'email' => 'xuanlap250203@gmail.com',
            'password' => Hash::make('Nhxlap@2502!'),
            'status' => 'active',
            'role' => 'admin',
            'is_admin' => true,
        ]
    );

    // Copy product access.
    $admin->products()->sync($source->products()->pluck('products.id')->all());

    // Copy AI provider access.
    UserAiProvider::query()->where('user_id', $admin->id)->delete();
    foreach ($source->aiProviders()->get() as $provider) {
        $admin->aiProviders()->create([
            'provider_key' => $provider->provider_key,
            'is_enabled' => $provider->is_enabled,
            'is_default' => $provider->is_default,
        ]);
    }

    // Copy Vertex credential.
    $sourceVertex = $source->vertexApiCredential;
    if ($sourceVertex) {
        VertexApiCredential::query()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'project_id' => $sourceVertex->project_id,
                'location' => $sourceVertex->location,
                'client_email' => $sourceVertex->client_email,
                'private_key' => $sourceVertex->private_key,
                'credentials_json' => $sourceVertex->credentials_json,
                'is_active' => $sourceVertex->is_active,
            ]
        );
    }

    // Copy v98Store / other API credentials if present.
    foreach (UserApiCredential::query()->where('user_id', $source->id)->get() as $credential) {
        UserApiCredential::query()->updateOrCreate(
            [
                'user_id' => $admin->id,
                'provider_key' => $credential->provider_key,
            ],
            [
                'api_key' => $credential->api_key,
                'api_secret' => $credential->api_secret,
                'access_token' => $credential->access_token,
                'refresh_token' => $credential->refresh_token,
                'expires_at' => $credential->expires_at,
                'is_active' => $credential->is_active,
            ]
        );
    }

    // Create manager test user.
    User::query()->updateOrCreate(
        ['username' => 'manager'],
        [
            'name' => 'Manager Test',
            'email' => 'manager@gmail.com',
            'password' => Hash::make('12345678'),
            'status' => 'active',
            'role' => 'manager',
            'is_admin' => false,
        ]
    );
});

echo "Done\n";
