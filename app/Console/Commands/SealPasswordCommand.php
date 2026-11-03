<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('nearfind:seal-password {--password= : Kata sandi yang akan di-hash}')]
#[Description('Generate a password hash for the stateless login account')]
class SealPasswordCommand extends Command
{
    public function handle(): int
    {
        $password = $this->option('password');

        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('Kata sandi akun warga');
        }

        if ($password === '') {
            $this->components->error('Kata sandi tidak boleh kosong.');

            return self::FAILURE;
        }

        $this->components->info('Tambahkan nilai berikut ke .env:');
        $this->newLine();
        $this->line("NEARFIND_AUTH_PASSWORD_HASH='".Hash::make($password)."'");

        return self::SUCCESS;
    }
}
