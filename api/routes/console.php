<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('lottery:sync')->everyTenMinutes()->withoutOverlapping();
Schedule::command('lottery:release-pending')->hourly()->withoutOverlapping();
Schedule::command('marketing:send --window=24h --template=draw-reminder')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('marketing:send --window=2h --template=jackpot-alert')->everyTenMinutes()->withoutOverlapping();
Schedule::command('marketing:send --window=24h --template=pool-highlight')->dailyAt('14:00')->withoutOverlapping();
