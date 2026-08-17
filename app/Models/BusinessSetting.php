<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'tax_enabled',
        'default_tax_rate',
        'online_payment_enabled',
    ];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'default_tax_rate' => 'integer',
            'online_payment_enabled' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
