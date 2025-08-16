<?php

// config for Roberts/Web3Laravel
return [
	// Use database 'blockchains' table to resolve networks when true
	'use_database' => true,

	// Default RPC endpoint used if nothing else resolves
	'default_rpc' => 'http://localhost:8545',

	// Default chain id to use when a chain id isn't provided
	'default_chain_id' => 8453, // Base mainnet by default

	// Request timeout for HTTP/WebSocket providers (seconds)
	'request_timeout' => 10,

	// Optional static chainId=>rpc mapping. This takes priority over DB when set.
	// Example:
	// 1 => 'https://mainnet.infura.io/v3/xxx',
	// 8453 => 'https://mainnet.base.org',
	// 84532 => 'https://sepolia.base.org',
	'networks' => [
		// leave empty by default
	],
];
