<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class NinIntegrationService
{
    /**
     * Anzisha mawasiliano na NIN Management.
     */
    public function start(string $nin): array
    {
        return $this->send('/api/nin/verification/start', [
            'nin' => $nin,
        ]);
    }

    /**
     * Tuma jibu la swali la uthibitishaji.
     */
    public function answer(string $sessionToken, string $answer): array
    {
        return $this->send('/api/nin/verification/answer', [
            'session_token' => $sessionToken,
            'answer' => $answer,
        ]);
    }

    /**
     * Pata taarifa baada ya verification kufanikiwa.
     */
    public function person(string $sessionToken): array
    {
        return $this->send('/api/nin/verification/person', [
            'session_token' => $sessionToken,
        ]);
    }

    /**
     * Tuma POST request kwenda NIN Management.
     */
    private function send(string $endpoint, array $payload): array
    {
        $baseUrl = rtrim(
            (string) config('services.nin.base_url'),
            '/'
        );

        $apiKey = (string) config('services.nin.api_key');

        $timeout = (int) config('services.nin.timeout', 15);

        if ($baseUrl === '' || $apiKey === '') {
            return [
                'ok' => false,
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'NIN service configuration is incomplete.',
                ],
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-NIN-API-KEY' => $apiKey,
                ])
                ->timeout($timeout)
                ->post($baseUrl . $endpoint, $payload);

            $body = $response->json();

            if (! is_array($body)) {
                $body = [
                    'success' => false,
                    'message' => 'NIN service returned an invalid response.',
                ];
            }

            return [
                'ok' => $response->successful()
                    && ($body['success'] ?? false) === true,
                'status' => $response->status(),
                'body' => $body,
            ];

        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'status' => 503,
                'body' => [
                    'success' => false,
                    'message' => 'Unable to connect to NIN Management. Please try again later.',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'An unexpected NIN integration error occurred.',
                ],
            ];
        }
    }
}
