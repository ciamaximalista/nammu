<?php
/**
 * Nammu — panel de administración.
 * Utilidades de depuración y trazas de tiempo para la ejecución por CLI (cron).
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_cli_debug_enabled(): bool
{
    static $enabled = null;
    if (is_bool($enabled)) {
        return $enabled;
    }
    $env = getenv('NAMMU_DEBUG');
    if ($env !== false) {
        $value = strtolower(trim((string) $env));
        if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
            $enabled = true;
            return true;
        }
        if (in_array($value, ['0', 'false', 'off', 'no'], true)) {
            $enabled = false;
            return false;
        }
    }
    global $__nammuCliDebugEnabled;
    $enabled = (bool) ($__nammuCliDebugEnabled ?? false);
    return $enabled;
}

function admin_cli_timing_log(string $scope, string $step, float $startedAt, array $extra = []): void
{
    if (PHP_SAPI !== 'cli' || !admin_cli_debug_enabled()) {
        return;
    }
    $payload = array_merge([
        'scope' => $scope,
        'step' => $step,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ], $extra);
    fwrite(STDERR, '[nammu-timing] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
}
