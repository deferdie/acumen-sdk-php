<?php

namespace App\Services\AcumenLogs;

trait AcumenTraceHelper
{
    protected $traceId;

    public function setTraceId(string $traceId)
    {
        $this->traceId = $traceId;
    }

    public function getTraceId()
    {
        return $this->traceId;
    }
}
