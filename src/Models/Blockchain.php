<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blockchain extends Model
{
    use HasFactory;

    protected $table = 'blockchains';

    protected $guarded = ['id'];

    protected $casts = [
        'chain_id' => 'integer',
        'evm' => 'boolean',
        'supports_eip1559' => 'boolean',
        'native_decimals' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
}
