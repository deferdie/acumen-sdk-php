<?php

namespace AcumenLogs\Facades;

use Illuminate\Support\Facades\Facade;

/**
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
