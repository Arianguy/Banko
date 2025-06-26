<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserDividendRecord;
use Carbon\Carbon;

class UpdateDividendStatuses extends Command
{
    protected $signature = 'dividends:update-statuses';
    protected $description = 'Update dividend statuses for records where dividend date has passed';

    public function handle()
    {
        $this->info('Updating dividend statuses...');

        $records = UserDividendRecord::with('dividendPayment')
            ->where('status', 'qualified')
            ->get();

        $updated = 0;

        foreach ($records as $record) {
            if ($record->dividendPayment && $record->dividendPayment->dividend_date <= Carbon::now()) {
                $record->update(['status' => 'received']);
                $updated++;
            }
        }

        $this->info("Updated {$updated} dividend records to 'received' status.");

        return 0;
    }
}
