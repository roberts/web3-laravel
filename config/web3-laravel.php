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
    // Sui-specific settings
    'sui' => [
        // Optional Coin Factory to create fungible assets without publishing a custom package
        // If 'package' is null, Sui token creation falls back to an offline stub (no on-chain submit)
        'coin_factory' => [
            // On mainnet/testnet, set to the published package object ID of your coin factory
            'package' => env('SUI_COIN_FACTORY_PACKAGE', null), // e.g. '0xabc...'
            'module' => env('SUI_COIN_FACTORY_MODULE', 'factory'),
            'function' => env('SUI_COIN_FACTORY_CREATE_FN', 'create'),
            // Whether to attempt an initial mint to recipient after creation (requires TreasuryCap)
            'mint_after_create' => env('SUI_COIN_FACTORY_MINT_AFTER', true),
        ],
        // Default gas multiplier used to estimate gasBudget from referenceGasPrice
        'gas_budget_multiplier' => env('SUI_GAS_BUDGET_MULTIPLIER', 10),
        // Minimum gas budget used when building transactions with helper endpoints
        'min_gas_budget' => env('SUI_MIN_GAS_BUDGET', 1000),
    ],
    // XRPL-specific settings
    'xrpl' => [
        // 'server' to use rippled sign API (trusted environment), 'client' for future client-side signing
        'sign_mode' => env('XRPL_SIGN_MODE', 'server'),
        // Attempt to orchestrate a trustline for recipient (requires managed recipient wallet; not yet automated)
        'auto_trustline' => env('XRPL_AUTO_TRUSTLINE', false),
    ],
    // TON-specific settings
    'ton' => [
        // Toncenter or compatible endpoint
        'rpc_base' => env('TON_RPC_BASE', null), // e.g. 'https://toncenter.com/api/v2'
        'api_key' => env('TON_API_KEY', null),
    ],
    // Hedera-specific settings (optional proxy for on-chain submits)
    'hedera' => [
        // If set, DeployToken will POST to this URL with token parameters and signer info
        'submit_url' => env('HEDERA_SUBMIT_URL', null),
        // Optional bearer/API key header name/value
        'auth_header' => env('HEDERA_AUTH_HEADER', null), // e.g. 'X-API-KEY'
        'auth_token' => env('HEDERA_AUTH_TOKEN', null),
    ],
    // Cardano-specific settings (optional proxy for on-chain submits)
    'cardano' => [
        // If set, DeployToken will POST to this URL with token parameters and signer info
        'submit_url' => env('CARDANO_SUBMIT_URL', null),
        'auth_header' => env('CARDANO_AUTH_HEADER', null),
        'auth_token' => env('CARDANO_AUTH_TOKEN', null),
    ],
];
