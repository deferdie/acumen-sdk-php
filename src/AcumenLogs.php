<?php

namespace AcumenLogs;

use Illuminate\Support\Facades\Http;

class AcumenLogs
{
    public function __construct(private readonly array $config) {}

    /**
     * Log an AI monitor call fire-and-forget.
     *
     * @param  string          $featureContext  Label for the feature/flow that made the call.
     * @param  mixed           $response        LLM response object (OpenAI, Anthropic, Gemini, Mistral).
     * @param  \Throwable|null $error           Exception thrown by the LLM call, if any.
     * @param  array           $extra           Optional context: session_id, user_id, outcome.
     */
    public function logAiMonitor(
        string $featureContext,
        mixed $response,
        \Throwable|null $error = null,
        array $extra = [],
    ): void {
        try {
            $url = $this->config['monitor_url'] ?? null;

            if (empty($url)) {
                return;
            }

            $normalized = ResponseNormalizer::normalize($response);

            $payload = array_filter([
                'provider'        => $normalized['provider'],
                'model'           => $normalized['model'],
                'token_usage'     => $normalized['token_usage'],
                'feature_context' => $featureContext,
                'session_id'      => $extra['session_id'] ?? null,
                'user_id'         => $extra['user_id'] ?? null,
                'outcome'         => $extra['outcome'] ?? null,
                'error'           => $error ? ['message' => $error->getMessage()] : null,
            ], fn($v) => $v !== null);

            $timeout = (int) ($this->config['timeout'] ?? 5);

            $this->dispatch($url, $payload, $timeout);
        } catch (\Throwable) {
            // Never let reporting affect the caller.
        }
    }

    private function dispatch(string $url, array $payload, int $timeout): void
    {
        try {
            Http::timeout($timeout)->post($url, $payload);
        } catch (\Throwable) {
                    // Swallow silently.
        }
        // Use Laravel's queue dispatcher when available — truly non-blocking.
        if (function_exists('dispatch')) {
            dispatch(static function () use ($url, $payload, $timeout): void {
                try {
                    Http::timeout($timeout)->post($url, $payload);
                } catch (\Throwable) {
                    // Swallow silently.
                }
            })->catch(static fn() => null);

            return;
        }

        // Fallback: daemon thread via cURL for non-Laravel contexts.
        $this->curlAsync($url, $payload, $timeout);
    }

    private function curlAsync(string $url, array $payload, int $timeout): void
    {
        $body = json_encode($payload);
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
