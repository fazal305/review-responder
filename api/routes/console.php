<?php

use Illuminate\Support\Facades\Schedule;

// Each run queues only the locations that are in their local 02:00 hour, so hourly covers every timezone.
Schedule::command('reviews:sync')->hourly()->withoutOverlapping();
