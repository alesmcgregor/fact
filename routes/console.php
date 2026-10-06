<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('fact:prune-analyses')->daily()->withoutOverlapping();
