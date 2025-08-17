<?php

namespace Roberts\Web3Laravel;

use Elliptic\EC;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Web3\Utils as Web3Utils;
use Web3\Providers\HttpProvider;
use Web3\Web3;

/**
 * Web3 manager: resolves an RPC URL (from config or DB) and returns a Web3 client.
 */
class Web3Laravel
{
    /** @var array<string,mixed> */
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a Web3 client for the given chain id or RPC URL.
     *
     * Priority: explicit $rpc > chain id lookup > default chain id > default rpc.
     */
    public function web3(?int $chainId = null, ?string $rpc = null): Web3
    {
        $rpcUrl = $rpc ?: $this->resolveRpcUrl($chainId);
        $timeout = (int) ($this->config['request_timeout'] ?? 10);

        // web3p/web3.php v1 accepts either a URL string or a Provider instance.
        // HttpProvider(host, timeout) is supported, so pass it directly.
        return new Web3(new HttpProvider($rpcUrl, $timeout));
    }

    /**
     * Get a Web3 client using a WebSocket provider when available.
     */
    public function web3Ws(?int $chainId = null, ?string $ws = null): Web3
    {
        $wsUrl = $ws ?: $this->resolveWsUrl($chainId);
        $timeout = (int) ($this->config['request_timeout'] ?? 10);
        if (! $wsUrl) {
            // Fallback to HTTP if WS not configured
            return $this->web3($chainId, null);
        }
        // Create WS provider if available, else fallback to HTTP
        if (class_exists('Web3\\Providers\\WebSocketProvider')) {
            $providerClass = 'Web3\\Providers\\WebSocketProvider';

            return new Web3(new $providerClass($wsUrl, $timeout));
        }

        return $this->web3($chainId, null);
    }

    /**
     * Resolve an RPC URL for a chain id or the default chain.
     */
    public function resolveRpcUrl(?int $chainId = null): string
    {
        // If networks mapping exists in config, prefer it
        $networks = (array) ($this->config['networks'] ?? []);
        $defaultRpc = (string) ($this->config['default_rpc'] ?? 'http://localhost:8545');
        $useDb = (bool) ($this->config['use_database'] ?? false);

        if ($chainId !== null) {
            if (isset($networks[$chainId]) && is_string($networks[$chainId])) {
                return $networks[$chainId];
            }

            if ($useDb) {
                $rpc = Blockchain::query()->where('chain_id', $chainId)->value('rpc');
                if (is_string($rpc) && $rpc !== '') {
                    return $rpc;
                }
            }
        }

        // Fallback to default chain id
        $defaultChainId = $this->config['default_chain_id'] ?? null;
        if (is_int($defaultChainId)) {
            if (isset($networks[$defaultChainId]) && is_string($networks[$defaultChainId])) {
                return $networks[$defaultChainId];
            }
            if ($useDb) {
                $rpc = Blockchain::query()->where('chain_id', $defaultChainId)->value('rpc');
                if (is_string($rpc) && $rpc !== '') {
                    return $rpc;
                }
            }
        }

        // As a final fallback, use the first DB default or the configured default rpc
        if ($useDb) {
            $rpc = Blockchain::query()->where('is_default', true)->value('rpc');
            if (is_string($rpc) && $rpc !== '') {
                return $rpc;
            }
        }

        return $defaultRpc;
    }

    /**
     * Resolve a WS URL via config mapping or naive conversion from HTTP.
     */
    public function resolveWsUrl(?int $chainId = null): ?string
    {
        $wsNetworks = (array) ($this->config['ws_networks'] ?? []);
        $defaultWs = $this->config['default_ws'] ?? null;
        $useDb = (bool) ($this->config['use_database'] ?? false);

        if ($chainId !== null) {
            if (isset($wsNetworks[$chainId]) && is_string($wsNetworks[$chainId])) {
                return $wsNetworks[$chainId];
            }
        }

        if (is_string($defaultWs) && $defaultWs !== '') {
            return $defaultWs;
        }

        // Attempt to convert HTTP RPC to WS
        $http = $this->resolveRpcUrl($chainId);
        if (str_starts_with($http, 'http://')) {
            return 'ws://'.substr($http, 7);
        }
        if (str_starts_with($http, 'https://')) {
            return 'wss://'.substr($http, 8);
        }

        return null;
    }

    /**
     * Create and persist a new wallet:
     * - Generates a new secp256k1 key pair (private key via web3.php utils/fallback; public via elliptic)
     * - Derives the Ethereum address from the public key using keccak256 (web3.php Utils)
     * - Encrypts the private key with Laravel's Crypt facade before storing
     *
     * @param  int|null  $chainId  Optional chain id to associate with the wallet (looked up in DB when enabled)
     * @param  array  $attributes  Additional attributes to merge when creating the Wallet
     */
    public function createWallet(?int $chainId = null, array $attributes = []): Wallet
    {
    // Generate a random 32-byte private key hex (0x-prefixed)
    $privHex = '0x'.strtolower(bin2hex(random_bytes(32)));

        // Derive public key and address
        $ec = new EC('secp256k1');
        $kp = $ec->keyFromPrivate(Web3Utils::stripZero($privHex), 'hex');
        $pub = $kp->getPublic(false, 'hex'); // 04 + x(64) + y(64)
        $pubNoPrefix = substr($pub, 2);
        $hash = Web3Utils::sha3('0x'.$pubNoPrefix);
        $address = '0x'.substr(Web3Utils::stripZero($hash), -40);
        $address = strtolower($address);

        // Encrypt the private key before persisting
        $encryptedKey = Crypt::encryptString($privHex);

        // Associate blockchain when using DB
        $useDb = (bool) ($this->config['use_database'] ?? false);
        $blockchainId = null;
        if ($useDb) {
            $lookupId = $chainId ?? ($this->config['default_chain_id'] ?? null);
            if ($lookupId !== null) {
                $blockchainId = Blockchain::query()->where('chain_id', (int) $lookupId)->value('id');
            }
        }

        $data = array_merge([
            'address' => $address,
            'key' => $encryptedKey, // Wallet mutator will keep as-is since already encrypted
            'blockchain_id' => $blockchainId,
            'is_active' => true,
        ], $attributes);

        return Wallet::create($data);
    }
}
