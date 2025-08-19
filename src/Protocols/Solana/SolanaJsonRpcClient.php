<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface;

class SolanaJsonRpcClient
{
    public function __construct(private ClientInterface $rpc) {}

    public function getLatestBlockhash(): array
    {
        return $this->rpc->call('getLatestBlockhash', [
            ['commitment' => 'confirmed'],
        ]);
    }

    public function getBalance(string $address): int
    {
        $res = $this->rpc->call('getBalance', [$address, ['commitment' => 'confirmed']]);

        return (int) ($res['value'] ?? 0);
    }

    public function getSignatureStatuses(array $signatures): array
    {
        return $this->rpc->call('getSignatureStatuses', [$signatures, ['searchTransactionHistory' => true]]);
    }

    public function sendTransaction(string $base64Signed): string
    {
        return (string) $this->rpc->call('sendTransaction', [$base64Signed, ['encoding' => 'base64', 'skipPreflight' => false]]);
    }

    public function getTransaction(string $signature): ?array
    {
        return $this->rpc->call('getTransaction', [$signature, ['maxSupportedTransactionVersion' => 0]]);
    }
    
    public function getTokenAccountsByOwner(string $owner, array $filterOrProgram, bool $parsed = true): array
    {
        $opts = ['commitment' => 'confirmed'];
        if ($parsed) {
            $opts['encoding'] = 'jsonParsed';
        }
        return $this->rpc->call('getTokenAccountsByOwner', [$owner, $filterOrProgram, $opts]);
    }
}
