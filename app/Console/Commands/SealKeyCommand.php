<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nearfind:seal-key')]
#[Description('Generate a base64url encoded 32-byte stateless-seal key')]
class SealKeyCommand extends Command
{
    public function handle(): int
    {
        $key = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->components->info('Tambahkan nilai berikut ke .env:');
        $this->newLine();
        $this->line('SEAL_KEY_ID='.date('Y-m'));
        $this->line('SEAL_KEY='.$key);

        return self::SUCCESS;
    }
}
