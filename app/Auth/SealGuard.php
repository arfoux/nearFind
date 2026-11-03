<?php

namespace App\Auth;

use App\Models\Profile;
use App\Support\Seal\Sealer;
use App\Support\Seal\SealException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

final class SealGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private Request $request,
        private readonly Sealer $sealer,
        private readonly string $cookieName,
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->cookie($this->cookieName);

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = $this->sealer->unseal(Sealer::PURPOSE_SESSION, $token);
        } catch (SealException) {
            return null;
        }

        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($name) || $name === '') {
            return null;
        }

        return $this->user = new StatelessUser($email, Profile::displayNameForEmail($email) ?? $name);
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;
        $this->user = null;

        return $this;
    }
}
