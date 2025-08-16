<?php

namespace Roberts\Web3Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Roberts\Web3Laravel\Models\Blockchain;

class BlockchainSeeder extends Seeder
{
    public function run(): void
    {
        $chains = [
            // Mainnets
            [
                'name' => 'Ethereum',
                'abbreviation' => 'ETH',
                'chain_id' => 1,
                'rpc' => 'https://rpc.ankr.com/eth',
                'scanner' => 'https://etherscan.io',
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Base',
                'abbreviation' => 'BASE',
                'chain_id' => 8453,
                'rpc' => 'https://mainnet.base.org',
                'scanner' => 'https://basescan.org',
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            [
                'name' => 'Polygon',
                'abbreviation' => 'POL',
                'chain_id' => 137,
                'rpc' => 'https://polygon-rpc.com',
                'scanner' => 'https://polygonscan.com',
                'native_symbol' => 'POL',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            [
                'name' => 'Arbitrum One',
                'abbreviation' => 'ARBI',
                'chain_id' => 42161,
                'rpc' => 'https://arb1.arbitrum.io/rpc',
                'scanner' => 'https://arbiscan.io',
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            [
                'name' => 'Optimism',
                'abbreviation' => 'OP',
                'chain_id' => 10,
                'rpc' => 'https://mainnet.optimism.io',
                'scanner' => 'https://optimistic.etherscan.io',
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            // Others requested
            [
                'name' => 'Abstract',
                'abbreviation' => 'ABS',
                'chain_id' => 2741,
                'rpc' => 'https://api.mainnet.abs.xyz',
                'scanner' => null,
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            [
                'name' => 'ApeChain',
                'abbreviation' => 'APE',
                'chain_id' => 33139,
                'rpc' => 'https://rpc.apechain.com',
                'scanner' => 'https://apescan.io',
                'native_symbol' => 'APE',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
            // Testnets
            [
                'name' => 'Sepolia',
                'abbreviation' => 'SEPOLIA',
                'chain_id' => 11155111,
                'rpc' => 'https://sepolia.infura.io/v3/YOUR_KEY',
                'scanner' => 'https://sepolia.etherscan.io',
                'native_symbol' => 'ETH',
                'native_decimals' => 18,
                'supports_eip1559' => true,
            ],
        ];

        foreach ($chains as $c) {
            Blockchain::updateOrCreate(
                ['chain_id' => $c['chain_id']],
                array_merge([
                    'evm' => true,
                    'is_active' => true,
                ], $c)
            );
        }
    }
}
