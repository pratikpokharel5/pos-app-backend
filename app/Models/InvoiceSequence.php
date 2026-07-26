<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'sequence_date',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'sequence_date' => 'date',
        ];
    }
}
