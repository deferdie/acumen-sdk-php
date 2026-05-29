<?php

namespace AcumenLogs;

use Illuminate\Support\Facades\Http;

class AcumenLogs
{
    public function __construct(private readonly array $config) {}

    /**
     * Start a new workflow trace. Returns trace UUID or null on failure.
     */
    public function beginTrace(string $featureContext, array $extra = []): ?string
    {
        try {
            $url = $this->tracesUrl();
            if (empty($url)) {
                return null;
            }

            $payload = [
                'trace' => array_filter([
                    'feature_context' => $featureContext,
                    'session_id'      => $extra['session_id'] ?? null,
                    'user_id'         => $extra['user_id'] ?? null,
                    'metadata'        => $extra['metadata'] ?? null,
                ], fn ($v) => $v !== null),
            ];

            $timeout = (int) ($this->config['timeout'] ?? 5);
            $response = Http::timeout($timeout)->post($url, $payload);

            if (!$response->successful()) {
                return null;
            }

            return $response->json('trace_id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Log a span within an existing trace (fire-and-forget).
     */
    public function logSpan(
        string $traceId,
        string $spanName,
        mixed $response,
        \Throwable|null $error = null,
        array $extra = [],
    ): void {
        $extra['trace_id'] = $traceId;
        $extra['span'] = array_merge([
            'name' => $spanName,
            'kind' => $extra['kind'] ?? 'llm',
        ], $extra['span'] ?? []);

        $this->logAiMonitor($extra['feature_context'] ?? $spanName, $response, $error, $extra);
    }

    /**
     * Record user feedback for a trace (fire-and-forget).
     */
    public function recordFeedback(string $traceId, string $signal, array $extra = []): void
    {
        try {
            $url = $this->feedbackUrl($traceId);
            if (empty($url)) {
                return;
            }

            $payload = array_filter([
                'signal'      => $signal,
                'source'      => $extra['source'] ?? 'explicit',
                'reason_code' => $extra['reason_code'] ?? null,
                'comment'     => $extra['comment'] ?? null,
                'metadata'    => $extra['metadata'] ?? null,
            ], fn ($v) => $v !== null);

            $timeout = (int) ($this->config['timeout'] ?? 5);
            $this->dispatch($url, $payload, $timeout);
        } catch (\Throwable) {
            // Never let reporting affect the caller.
        }
    }

    /**
     * Log an AI monitor call fire-and-forget.
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
                'trace_id'        => $extra['trace_id'] ?? null,
                'trace'           => $extra['trace'] ?? null,
                'span'            => $extra['span'] ?? null,
                'provider'        => $normalized['provider'],
                'model'           => $normalized['model'],
                'token_usage'     => $normalized['token_usage'],
                'feature_context' => $featureContext,
                'session_id'      => $extra['session_id'] ?? null,
                'user_id'         => $extra['user_id'] ?? null,
                'outcome'         => $extra['outcome'] ?? null,
                'latency_ms'      => $extra['latency_ms'] ?? null,
                'error'           => $error ? ['message' => $error->getMessage()] : null,
            ], fn ($v) => $v !== null);

            $timeout = (int) ($this->config['timeout'] ?? 5);

            $this->dispatch($url, $payload, $timeout);
        } catch (\Throwable) {
            // Never let reporting affect the caller.
        }
    }

    private function tracesUrl(): ?string
    {
        $reportUrl = $this->config['monitor_url'] ?? null;
        if (!$reportUrl) {
            return null;
        }

        return preg_replace('#/report$#', '/traces', $reportUrl) ?: null;
    }

    private function feedbackUrl(string $traceId): ?string
    {
        $reportUrl = $this->config['monitor_url'] ?? null;
        if (!$reportUrl) {
            return null;
        }

        $base = preg_replace('#/report$#', '', $reportUrl);

        return $base ? "{$base}/traces/{$traceId}/feedback" : null;
    }

    private function dispatch(string $url, array $payload, int $timeout): void
    {
        if (function_exists('dispatch')) {
            dispatch(static function () use ($url, $payload, $timeout): void {
                try {
                    Http::timeout($timeout)->post($url, $payload);
                } catch (\Throwable) {
                    // Swallow silently.
                }
            })->catch(static fn () => null);

            return;
        }

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
