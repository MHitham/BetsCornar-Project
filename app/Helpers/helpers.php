<?php

if (! function_exists('displayDateTime')) {
    function displayDateTime(?\DateTimeInterface $date, string $dateFormat = 'Y-m-d'): string
    {
        if ($date === null) {
            return '';
        }

        $local = \Carbon\CarbonImmutable::instance($date)->setTimezone('Africa/Cairo');

        return $local->format($dateFormat.' g:i').' '.($local->hour < 12 ? 'ص' : 'م');
    }
}

if (! function_exists('dashboardKey')) {

    function dashboardKey(string $key): string
    {
        return 'dashboard.'.$key.'_'.now()->startOfDay()->toDateString();
    }
}

if (! function_exists('generateLicenseKey')) {

    function generateLicenseKey(string $machineId): string
    {
        return \App\Services\LicenseService::generateLicenseKey($machineId);
    }
}
