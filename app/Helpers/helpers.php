<?php

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
