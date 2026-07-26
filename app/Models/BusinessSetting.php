<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'logo',
        'address',
        'phone',
        'email',
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
}
