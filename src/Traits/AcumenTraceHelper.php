<?php

namespace AcumenLogs\Traits;

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
