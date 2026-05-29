<?php

return [
    /*
     * The full reporting endpoint URL including your monitor UUID.
     * Find this in the Acumen Logs AI Monitor dashboard.
     *
     * Example: https://app.acumenlogs.com/api/monitor/your-uuid-here/report
     */
    'monitor_url' => env('ACUMEN_LOGS_MONITOR_URL'),

    /*
     * HTTP timeout in seconds for the reporting POST request.
     */
    'timeout' => env('ACUMEN_LOGS_TIMEOUT', 5),
];
