<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Makes the "json" log channel emit one JSON object per line instead of
 * Monolog's default human-oriented plain-text line - the schema every real
 * log-aggregation/alerting pipeline (ELK, Datadog, Splunk) expects to
 * ingest, versus a format meant to be read by eye.
 */
class JsonFormatterTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter());
        }
    }
}
