<?php

namespace App\Services\Notifications;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class FirebaseMessagingService
{
    private bool $credentialsLoaded = false;

    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null && $this->projectId() !== null;
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'Firebase messaging is not configured on this server.',
            ];
        }

        $accessToken = $this->accessToken();

        if (! $accessToken) {
            return [
                'ok' => false,
                'error' => 'Unable to obtain a Firebase access token.',
            ];
        }

        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $dataPayload = $this->stringifyData($data);

        if ($dataPayload !== []) {
            $message['data'] = $dataPayload;
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(15)
            ->post(rtrim((string) config('firebase.messaging_base_url', 'https://fcm.googleapis.com/v1'), '/').'/projects/'.$this->projectId().'/messages:send', [
                'message' => $message,
            ]);

        if ($response->successful()) {
            return [
                'ok' => true,
                'message_id' => data_get($response->json(), 'name'),
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'error' => data_get($response->json(), 'error.message', 'Firebase send failed.'),
            'error_status' => data_get($response->json(), 'error.status'),
        ];
    }

    private function accessToken(): ?string
    {
        $credentials = $this->credentials();

        if (! $credentials) {
            return null;
        }

        $cacheKey = 'firebase_access_token_'.md5(($credentials['client_email'] ?? '').'|'.$this->projectId());

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $tokenUri = $credentials['token_uri'] ?? config('firebase.oauth_token_url', 'https://oauth2.googleapis.com/token');
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'sub' => $credentials['client_email'],
                'aud' => $tokenUri,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful()) {
                return null;
            }

            return data_get($response->json(), 'access_token');
        });
    }

    private function projectId(): ?string
    {
        return config('firebase.project_id') ?: ($this->credentials()['project_id'] ?? null);
    }

    private function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }

        $this->credentialsLoaded = true;
        $path = trim((string) config('firebase.service_account_json', ''));

        if ($path === '') {
            return $this->credentials = null;
        }

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            return $this->credentials = null;
        }

        try {
            $contents = file_get_contents($path);

            if ($contents === false) {
                return $this->credentials = null;
            }

            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (
                ! is_array($decoded)
                || empty($decoded['client_email'])
                || empty($decoded['private_key'])
            ) {
                return $this->credentials = null;
            }

            return $this->credentials = $decoded;
        } catch (Throwable $exception) {
            return $this->credentials = null;
        }
    }

    private function stringifyData(array $data): array
    {
        $payload = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $payload[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $payload;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (bool) preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }
}
