<?php

$keys = [];
$configuredKeys = env('SEAL_KEYS');

if (is_string($configuredKeys) && $configuredKeys !== '') {
    $decodedKeys = json_decode($configuredKeys, true);

    if (is_array($decodedKeys)) {
        $keys = array_filter($decodedKeys, static fn (mixed $key): bool => is_string($key) && $key !== '');
    }
}

$currentKeyId = (string) env('SEAL_KEY_ID', 'local');
$currentKey = env('SEAL_KEY');

if (is_string($currentKey) && $currentKey !== '') {
    $keys[$currentKeyId] = $currentKey;
}

return [
    'issuer' => env('SEAL_ISSUER', env('APP_NAME', 'nearFind')),
    'audience' => env('SEAL_AUDIENCE', 'web'),
    'current_key_id' => $currentKeyId,
    'keys' => $keys,
    'max_token_size' => (int) env('SEAL_MAX_TOKEN_SIZE', 16384),
    'clock_tolerance' => (int) env('SEAL_CLOCK_TOLERANCE', 60),
    'session' => [
        'cookie' => 'nearfind_seal',
        'ttl' => env('SEAL_SESSION_TTL', '12h'),
        'remember_ttl' => env('SEAL_REMEMBER_TTL', '30d'),
    ],
    'auth' => [
        'email' => env('NEARFIND_AUTH_EMAIL'),
        'name' => env('NEARFIND_AUTH_NAME', 'Warga nearFind'),
        'password_hash' => env('NEARFIND_AUTH_PASSWORD_HASH'),
    ],
];
