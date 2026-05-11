<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = App\Models\BankTransaction::whereHas('file', function($q){ 
  $q->where('bank_type', 'SBI');
})->latest('id')->first();

if($t){
  echo "Raw: " . var_export($t->getRawOriginal('payload_json'), true) . "\n";
  echo "Processed type: " . gettype($t->payload_json) . "\n";
} else { 
  echo "No SBI transaction found\n"; 
}
