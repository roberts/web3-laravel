<?php

namespace Roberts\Web3Laravel\Protocols\Xrpl;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface;

class XrplJsonRpcClient
{
    public function __construct(private ClientInterface $rpc) {}

    /** Get latest ledger index. */
    public function ledgerCurrent(): int
    {
        $res = $this->rpc->call('ledger_current', []);
        return (int) ($res['ledger_current_index'] ?? 0);
    }

    /** Fetch a transaction by hash. */
    public function tx(string $hash): ?array
    {
    $res = $this->rpc->call('tx', [['transaction' => $hash, 'binary' => false]]);
        if (!is_array($res)) { return null; }
        return $res;
    }

    /** Submit a signed transaction blob (hex). */
    public function submit(string $txBlobHex): array
    {
    return $this->rpc->call('submit', [['tx_blob' => $txBlobHex]]);
    }

    /** Server-side signing (use only in trusted environments). */
    public function sign(array $txJson, array $opts): array
    {
    $params = array_merge(['tx_json' => $txJson], $opts);
    return $this->rpc->call('sign', [$params]);
    }

    /** Get fee info. */
    public function fee(): array
    {
        return $this->rpc->call('fee', []);
    }

    /** Get account info (sequence, balance). */
    public function accountInfo(string $account): array
    {
    return $this->rpc->call('account_info', [['account' => $account, 'ledger_index' => 'current', 'strict' => true]]);
    }
}
