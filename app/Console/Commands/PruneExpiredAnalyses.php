<?php

namespace App\Console\Commands;

use App\Models\Analysis;
use Illuminate\Console\Command;

class PruneExpiredAnalyses extends Command
{
    protected $signature = 'fact:prune-analyses';

    protected $description = 'Delete expired public reports and their evidence snapshots.';

    public function handle(): int
    {
        $deleted = Analysis::query()->where('expires_at', '<', now())->delete();

        $this->components->info("Deleted {$deleted} expired analyses.");

        return self::SUCCESS;
    }
}
