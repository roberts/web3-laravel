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
                'protocol' => 'evm',
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
                'protocol' => 'evm',
                'is_default' => true,
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
                'protocol' => 'evm',
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
                'protocol' => 'evm',
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
                'protocol' => 'evm',
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
                'protocol' => 'evm',
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
                'protocol' => 'evm',
            ],
            // Non-EVM
            [
                'name' => 'Solana',
                'abbreviation' => 'SOL',
                'chain_id' => 0, // placeholder; Solana does not use EVM chain IDs
                'rpc' => 'https://api.mainnet-beta.solana.com',
                'scanner' => 'https://explorer.solana.com',
                'native_symbol' => 'SOL',
                'native_decimals' => 9,
                'supports_eip1559' => false,
                'protocol' => 'solana',
                'is_default' => false,
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
                'protocol' => 'evm',
            ],
        ];

        foreach ($chains as $c) {
            Blockchain::updateOrCreate(
                ['chain_id' => $c['chain_id']],
                array_merge([
                    'protocol' => $c['protocol'],
                    'is_active' => true,
                ], $c)
            );
        }
    }
}
