<?php

namespace Roberts\Web3Laravel\Protocols\Sui;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface;

class SuiJsonRpcClient
{
    public function __construct(private ClientInterface $rpc) {}

    public function getReferenceGasPrice(): int
    {
        $res = $this->rpc->call('suix_getReferenceGasPrice', []);

        return (int) ($res ?? 0);
    }

    /**
     * @return array{data: array<int, array>, nextCursor: mixed, hasNextPage: bool}
     */
    public function getCoins(string $owner, string $coinType = '0x2::sui::SUI', ?string $cursor = null, int $limit = 50): array
    {
        $params = [['owner' => $owner, 'coinType' => $coinType, 'cursor' => $cursor, 'limit' => $limit]];
        $res = $this->rpc->call('suix_getCoins', $params);

        return is_array($res) ? $res : ['data' => [], 'nextCursor' => null, 'hasNextPage' => false];
    }

    public function getLatestCheckpointSequenceNumber(): int
    {
        $res = $this->rpc->call('sui_getLatestCheckpointSequenceNumber', []);

        return (int) ($res ?? 0);
    }

    public function getTransactionBlock(string $digest, array $options = []): ?array
    {
        $defaultOpts = [
            'showInput' => false,
            'showRawInput' => false,
            'showEffects' => true,
            'showEvents' => false,
            'showObjectChanges' => false,
            'showBalanceChanges' => false,
        ];
        $opts = array_merge($defaultOpts, $options);
        $res = $this->rpc->call('sui_getTransactionBlock', [$digest, $opts]);

        return is_array($res) ? $res : null;
    }

    public function executeTransactionBlock(string $txBytesBase64, array $signatures, array $options = []): array
    {
        $defaultOpts = [
            'showInput' => false,
            'showRawInput' => false,
            'showEffects' => true,
            'showEvents' => false,
            'showObjectChanges' => false,
            'showBalanceChanges' => false,
        ];
        $opts = array_merge($defaultOpts, $options);

        return $this->rpc->call('sui_executeTransactionBlock', [
            $txBytesBase64,
            $signatures,
            $opts,
            'WaitForLocalExecution',
        ]);
    }

    /** Build a simple SUI native transfer and return txBytes container. */
    public function transferSui(string $signer, string $suiObjectId, string $recipient, int|string $amount, int $gasBudget): array
    {
        // RPC expects: [signer, suiObjectId, gasBudget, recipient, amount]
        return $this->rpc->call('sui_transferSui', [
            $signer,
            $suiObjectId,
            (int) $gasBudget,
            $recipient,
            (int) $amount,
        ]);
    }

    /** Generic moveCall builder returning txBytes container. */
    public function moveCall(string $signer, string $packageObjectId, string $module, string $function, array $typeArguments, array $arguments, int $gasBudget): array
    {
        return $this->rpc->call('sui_moveCall', [[
            'packageObjectId' => $packageObjectId,
            'module' => $module,
            'function' => $function,
            'typeArguments' => $typeArguments,
            'arguments' => $arguments,
            'gasBudget' => (int) $gasBudget,
            'signer' => $signer,
        ]]);
    }

    /** Transfer a generic object (e.g. TreasuryCap) to a recipient; returns txBytes container. */
    public function transferObject(string $signer, string $objectId, string $recipient, int $gasBudget): array
    {
        return $this->rpc->call('sui_transferObject', [
            $signer,
            $objectId,
            (int) $gasBudget,
            $recipient,
        ]);
    }

    /** Fetch an object by ID with optional options. */
    public function getObject(string $objectId, array $options = []): ?array
    {
        $default = [
            'showType' => true,
            'showOwner' => true,
            'showPreviousTransaction' => false,
            'showContent' => true,
            'showBcs' => false,
            'showStorageRebate' => false,
        ];
        $opts = array_merge($default, $options);
        $res = $this->rpc->call('sui_getObject', [$objectId, $opts]);

        return is_array($res) ? $res : null;
    }
}
