<?php

// config for Roberts/Web3Laravel
return [
    // Use database 'blockchains' table to resolve networks when true
    'use_database' => true,

    // Default RPC endpoint used if nothing else resolves (Base mainnet)
    'default_rpc' => 'https://mainnet.base.org',

    // Default chain id to use when a chain id isn't provided
    'default_chain_id' => 8453, // Base mainnet by default

    // Request timeout for HTTP providers (seconds)
    'request_timeout' => 10,

    // Native JSON-RPC client settings
    'rpc' => [
        'retries' => 2,
        'backoff_ms' => 200,
        'headers' => [],
        'batch' => [
            'enabled' => false,
            'max' => 20,
        ],
    ],

    // Required confirmations for a transaction to be considered 'confirmed'
    'confirmations_required' => 6,

    // Polling interval in seconds (used by watcher)
    'confirmations_poll_interval' => 10,

    // Optional static chainId=>rpc mapping. This takes priority over DB when set.
    // Example:
    // 1 => 'https://mainnet.infura.io/v3/xxx',
    // 8453 => 'https://mainnet.base.org',
    // 84532 => 'https://sepolia.base.org',
    'networks' => [
        // Prefer Base by default
        8453 => 'https://mainnet.base.org',
    ],

    // Optional webhooks to notify on token balance/allowance updates.
    // If null or empty, no webhook is sent.
    'webhooks' => [
        'balance_updates' => null,
        'allowance_updates' => null,
        // Optional shared secret header to verify origin
        'secret' => null, // set to a string; will be sent as X-Web3Laravel-Secret
    ],
    // Solana defaults (optional): used for Solana wallet creation when a blockchain row isn't provided
    'solana' => [
        'default_rpc' => 'https://api.mainnet-beta.solana.com',
        // When true, automatically create missing Associated Token Accounts (ATAs) during approve/transfer flows
        'auto_create_atas' => false,
    ],
];
