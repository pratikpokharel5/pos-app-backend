<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function next(int $businessId, CarbonInterface $date): string
    {
        $sequenceDate = $date->toDateString();

        return DB::transaction(function () use ($businessId, $date, $sequenceDate): string {
            $sequence = InvoiceSequence::query()
                ->where('business_id', $businessId)
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                InvoiceSequence::query()->create([
                    'business_id' => $businessId,
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
