<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;

/**
 * @property int $id
 * @property string $name
 * @property int $chain_id
 * @property string $rpc_url
 * @property string|null $ws_url
 * @property string $native_symbol
 * @property int $native_decimals
 * @property bool $supports_eip1559
 * @property bool $is_active
 * @property bool $is_default
 * @property \Roberts\Web3Laravel\Enums\BlockchainProtocol $protocol
 */
class Blockchain extends Model
{
    use HasFactory;

    protected $table = 'blockchains';

    protected $guarded = ['id'];

    protected $casts = [
        'chain_id' => 'integer',
        'supports_eip1559' => 'boolean',
        'native_decimals' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    'protocol' => BlockchainProtocol::class,
    ];
}
