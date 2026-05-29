<?php

namespace AcumenLogs;

class ResponseNormalizer
{
    public static function normalize(mixed $response): array
    {
        if ($response === null) {
            return ['provider' => null, 'model' => null, 'token_usage' => self::emptyTokens()];
        }

        $provider    = self::detectProvider($response);
        $model       = self::extractModel($response);
        $token_usage = self::extractTokens($response, $provider);

        return compact('provider', 'model', 'token_usage');
    }

    private static function detectProvider(mixed $response): ?string
    {
        // Class-name detection is most reliable — each SDK uses its own namespace.
        if (is_object($response)) {
            $class = get_class($response);
            if (str_contains($class, 'OpenAI\\'))    return 'openai';
            if (str_contains($class, 'Anthropic\\')) return 'anthropic';
            if (str_contains($class, 'Google\\') || str_contains($class, 'Gemini\\')) return 'gemini';
            if (str_contains($class, 'Mistral\\'))   return 'mistral';

            // Duck-type object properties.
            if (isset($response->usage->inputTokens))  return 'anthropic';
            if (isset($response->usageMetadata))       return 'gemini';
            if (isset($response->usage->prompt_tokens) || isset($response->usage->promptTokens)) {
                return self::openaiOrMistralFromModel($response->model ?? '');
            }

            return null;
        }

        if (is_array($response)) {
            $usage = $response['usage'] ?? [];
            if (isset($usage['input_tokens']))         return 'anthropic';
            if (isset($response['usageMetadata']))     return 'gemini';
            if (isset($usage['prompt_tokens']))        return self::openaiOrMistralFromModel($response['model'] ?? '');
        }

        return null;
    }

    private static function openaiOrMistralFromModel(string $model): string
    {
        if (str_starts_with($model, 'mistral-') || str_starts_with($model, 'open-mistral') || str_starts_with($model, 'open-mixtral')) {
            return 'mistral';
        }
        return 'openai';
    }

    private static function extractModel(mixed $response): ?string
    {
        if (is_array($response))  return $response['model'] ?? null;
        if (is_object($response)) return $response->model ?? null;
        return null;
    }

    private static function extractTokens(mixed $response, ?string $provider): array
    {
        if (is_array($response)) {
            return self::extractTokensFromArray($response, $provider);
        }

        if (!is_object($response)) {
            return self::emptyTokens();
        }

        return match ($provider) {
            'anthropic' => [
                'prompt_tokens'     => (int) ($response->usage->inputTokens ?? 0),
                'completion_tokens' => (int) ($response->usage->outputTokens ?? 0),
                'total_tokens'      => (int) (($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0)),
            ],
            'gemini' => [
                'prompt_tokens'     => (int) ($response->usageMetadata->promptTokenCount ?? 0),
                'completion_tokens' => (int) ($response->usageMetadata->candidatesTokenCount ?? 0),
                'total_tokens'      => (int) ($response->usageMetadata->totalTokenCount ?? 0),
            ],
            default => [
                'prompt_tokens'     => (int) ($response->usage->promptTokens ?? $response->usage->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($response->usage->completionTokens ?? $response->usage->completion_tokens ?? 0),
                'total_tokens'      => (int) ($response->usage->totalTokens ?? $response->usage->total_tokens ?? 0),
            ],
        };
    }

    private static function extractTokensFromArray(array $response, ?string $provider): array
    {
        $usage = $response['usage'] ?? [];
        $meta  = $response['usageMetadata'] ?? [];

        return match ($provider) {
            'anthropic' => [
                'prompt_tokens'     => (int) ($usage['input_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens'      => (int) (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0)),
            ],
            'gemini' => [
                'prompt_tokens'     => (int) ($meta['promptTokenCount'] ?? 0),
                'completion_tokens' => (int) ($meta['candidatesTokenCount'] ?? 0),
                'total_tokens'      => (int) ($meta['totalTokenCount'] ?? 0),
            ],
            default => [
                'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
            ],
        };
    }

    private static function emptyTokens(): array
    {
        return ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
    }
}
