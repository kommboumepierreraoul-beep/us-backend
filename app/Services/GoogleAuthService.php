<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleAuthService
{
    /**
     * @return array{sub: string, email: string, email_verified: bool, name?: string, given_name?: string, picture?: string, aud?: string}
     */
    public function verifyIdToken(string $idToken): array
    {
        $response = Http::get(config('services.google.tokeninfo_url'), [
            'id_token' => $idToken,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'id_token' => 'Token Google invalide.',
            ]);
        }

        $payload = $response->json();
        $allowedClientIds = config('services.google.allowed_client_ids', []);

        if (! in_array($payload['aud'] ?? null, $allowedClientIds, true)) {
            throw ValidationException::withMessages([
                'id_token' => 'Client Google non autorise.',
            ]);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' && ($payload['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'email' => 'Email Google non verifie.',
            ]);
        }

        if (blank($payload['sub'] ?? null) || blank($payload['email'] ?? null)) {
            throw ValidationException::withMessages([
                'id_token' => 'Token Google incomplet.',
            ]);
        }

        return [
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'email_verified' => true,
            'name' => $payload['name'] ?? null,
            'given_name' => $payload['given_name'] ?? null,
            'picture' => $payload['picture'] ?? null,
            'aud' => $payload['aud'] ?? null,
        ];
    }
}
