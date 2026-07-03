<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::query()->orderBy('id')->get(['id','name','username','email','role','is_admin','status']);
foreach ($users as $user) {
    echo json_encode($user->toArray(), JSON_UNESCAPED_UNICODE).PHP_EOL;
}
