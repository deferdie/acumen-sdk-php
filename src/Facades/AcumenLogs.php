<?php

namespace AcumenLogs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null beginTrace(string $featureContext, array $extra = [])
 * @method static void logSpan(string $traceId, string $spanName, mixed $response, \Throwable|null $error = null, array $extra = [])
 * @method static void recordFeedback(string $traceId, string $signal, array $extra = [])
 * @method static void logAiMonitor(string $featureContext, mixed $response, \Throwable|null $error = null, array $extra = [])
 *
 * @see \AcumenLogs\AcumenLogs
 */
class AcumenLogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AcumenLogs\AcumenLogs::class;
    }
}
