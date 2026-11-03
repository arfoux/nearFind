<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

final class StatelessUser implements Authenticatable
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public function getAuthIdentifier(): string
    {
        return $this->email;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(mixed $value): void {}

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
