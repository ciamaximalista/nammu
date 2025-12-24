<?php

$serverTimezone = getenv('TZ');
if (!is_string($serverTimezone) || $serverTimezone === '') {
    $tzFile = @file_get_contents('/etc/timezone');
    if (is_string($tzFile)) {
        $serverTimezone = trim($tzFile);
    }
}
if (!is_string($serverTimezone) || $serverTimezone === '') {
    if (is_link('/etc/localtime')) {
        $tzLink = readlink('/etc/localtime');
        if (is_string($tzLink)) {
            $pos = strpos($tzLink, 'zoneinfo/');
            if ($pos !== false) {
                $serverTimezone = substr($tzLink, $pos + 9);
            }
        }
    }
}
if (is_string($serverTimezone) && $serverTimezone !== '') {
    if (in_array($serverTimezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($serverTimezone);
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'Nammu\\Core\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
