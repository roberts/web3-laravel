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
            [
                'name' => 'Abstract',
                'abbreviation' => 'ABS',
                'chain_id' => 2741,
                'rpc' => 'https://api.mainnet.abs.xyz',
                'scanner' => 'https://abscan.org',
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
            // New non-EVM protocols
            [
                'name' => 'Bitcoin',
                'abbreviation' => 'BTC',
                'chain_id' => 100000, // synthetic unique id for non-EVM
                'rpc' => 'https://blockstream.info/api', // placeholder REST endpoint
                'scanner' => 'https://blockstream.info',
                'native_symbol' => 'BTC',
                'native_decimals' => 8,
                'supports_eip1559' => false,
                'protocol' => 'bitcoin',
                'is_default' => false,
            ],
            [
                'name' => 'Sui',
                'abbreviation' => 'SUI',
                'chain_id' => 784, // SLIP-44 coin type used as synthetic id
                'rpc' => 'https://fullnode.mainnet.sui.io:443',
                'scanner' => 'https://suiexplorer.com',
                'native_symbol' => 'SUI',
                'native_decimals' => 9,
                'supports_eip1559' => false,
                'protocol' => 'sui',
                'is_default' => false,
            ],
            [
                'name' => 'XRP Ledger',
                'abbreviation' => 'XRP',
                'chain_id' => 144, // SLIP-44 coin type as synthetic id
                'rpc' => 'https://xrplcluster.com',
                'scanner' => 'https://livenet.xrpl.org',
                'native_symbol' => 'XRP',
                'native_decimals' => 6,
                'supports_eip1559' => false,
                'protocol' => 'xrpl',
                'is_default' => false,
            ],
            [
                'name' => 'Cardano',
                'abbreviation' => 'ADA',
                'chain_id' => 1815, // SLIP-44 coin type as synthetic id
                'rpc' => 'https://cardano-mainnet.blockfrost.io/api/v0', // requires key; placeholder
                'scanner' => 'https://cardanoscan.io',
                'native_symbol' => 'ADA',
                'native_decimals' => 6,
                'supports_eip1559' => false,
                'protocol' => 'cardano',
                'is_default' => false,
            ],
            [
                'name' => 'Hedera',
                'abbreviation' => 'HBAR',
                'chain_id' => 3030, // SLIP-44 coin type as synthetic id
                'rpc' => 'https://mainnet-public.mirrornode.hedera.com/api/v1', // REST
                'scanner' => 'https://hashscan.io/mainnet',
                'native_symbol' => 'HBAR',
                'native_decimals' => 8,
                'supports_eip1559' => false,
                'protocol' => 'hedera',
                'is_default' => false,
            ],
            [
                'name' => 'TON',
                'abbreviation' => 'TON',
                'chain_id' => 607, // SLIP-44 coin type as synthetic id
                'rpc' => 'https://toncenter.com/api/v2/jsonRPC',
                'scanner' => 'https://tonviewer.com',
                'native_symbol' => 'TON',
                'native_decimals' => 9,
                'supports_eip1559' => false,
                'protocol' => 'ton',
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
