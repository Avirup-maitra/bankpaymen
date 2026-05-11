<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transactions = App\Models\BankTransaction::all();
$fixed = 0;
foreach($transactions as $t) {
    // getRawOriginal gives the actual string in DB
    $raw = $t->getRawOriginal('payload_json');
    if ($raw && strpos($raw, '"{') === 0) {
        // It's double encoded, e.g. "{\"something\":\"value\"}"
        $decodedOnce = json_decode($raw, true);
        if (is_string($decodedOnce)) {
            $t->payload_json = json_decode($decodedOnce, true);
            $t->save();
            $fixed++;
        }
    }
}
echo "Fixed $fixed transactions.\n";
