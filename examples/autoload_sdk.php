<?php

declare(strict_types=1);

namespace OpenTelemetry\Example;

putenv('OTEL_PHP_AUTOLOAD_ENABLED=true');
putenv('OTEL_TRACES_EXPORTER=otlp');
putenv('OTEL_METRICS_EXPORTER=otlp');
putenv('OTEL_LOGS_EXPORTER=otlp');
putenv('OTEL_EXPORTER_OTLP_PROTOCOL=grpc');
putenv('OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317');
putenv('OTEL_PROPAGATORS=b3,baggage,tracecontext');

echo 'autoloading SDK example starting...' . PHP_EOL;

// Composer autoloader will execute SDK/_autoload.php which will register global instrumentation from environment configuration
require dirname(__DIR__) . '/vendor/autoload.php';

$instrumentation = new \OpenTelemetry\API\Instrumentation\CachedInstrumentation('demo');

$instrumentation->tracer()->spanBuilder('root')->startSpan()->end();
$instrumentation->meter()->createCounter('cnt')->add(1);
$instrumentation->eventLogger()->emit('foo', 'hello, otel');

echo 'Finished!' . PHP_EOL;
