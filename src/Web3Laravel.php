<?php

namespace Roberts\Web3Laravel;

use Roberts\Web3Laravel\Models\Blockchain;
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
}
