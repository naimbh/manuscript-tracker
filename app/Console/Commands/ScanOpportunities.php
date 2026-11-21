<?php

namespace App\Console\Commands;

use App\Models\Scan;
use App\Models\User;
use App\Support\OpportunityScanner;
use App\Support\VenueResearcher;
use Illuminate\Console\Command;

class ScanOpportunities extends Command
{
    protected $signature = 'opportunities:scan {--user= : Only this user id} {--scan= : Continue an existing scan record}';

    protected $description = 'Find new calls for papers that fit each user’s interests';

    public function handle(OpportunityScanner $scanner): int
    {
        if (! VenueResearcher::configured()) {
            $this->warn('AI is not set up; skipping.');

            return self::SUCCESS;
        }
        $users = User::query()->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($users as $user) {
            $scan = $this->option('scan') ? Scan::find($this->option('scan')) : null;
            $result = $scanner->scan($user, $scan ? $scan->trigger : 'daily', $scan);
            $this->line("{$user->email}: {$result->status}, {$result->found} new");
        }

        return self::SUCCESS;
    }
}
