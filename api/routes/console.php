<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('lottery:sync')->everyTenMinutes()->withoutOverlapping();
Schedule::command('lottery:release-pending')->hourly()->withoutOverlapping();

