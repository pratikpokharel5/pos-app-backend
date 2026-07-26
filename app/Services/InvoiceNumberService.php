<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function next(CarbonInterface $date): string
    {
        $sequenceDate = $date->toDateString();

        return DB::transaction(function () use ($date, $sequenceDate): string {
            $sequence = InvoiceSequence::query()
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                InvoiceSequence::query()->create([
                    'sequence_date' => $sequenceDate,
                    'next_number' => 2,
                ]);

                return sprintf('INV-%s-%04d', $date->format('Ymd'), 1);
            }

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf('INV-%s-%04d', $date->format('Ymd'), $number);
        });
    }
}
