<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reportes:recuperar-cargas')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('reportes.cargas_asincronas'));
