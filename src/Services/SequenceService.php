<?php

namespace Roberts\Web3Laravel\Services;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\HasSequence;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Protocols\Xrpl\XrplJsonRpcClient;

class SequenceService
{
    /**
     * Return the next sequence/nonce-like value for the wallet's account, when applicable.
     * - EVM: pending transaction count (nonce) as 0x hex string.
     * - XRPL: account Sequence as integer.
     * - Others: null.
     */
    public function current(Wallet $wallet): int|string|null
    {
        // Prefer adapter capability when available
        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        $adapter = $router->for($wallet->protocol);
        if ($adapter instanceof HasSequence) {
            return $adapter->sequence($wallet);
        }

        $protocol = $wallet->protocol;
        if ($protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->getTransactionCount($wallet->address, 'pending');
        }

        if ($protocol === BlockchainProtocol::XRPL) {
            try {
                /** @var XrplJsonRpcClient $rpc */
                $rpc = app(XrplJsonRpcClient::class);
                $info = $rpc->accountInfo($wallet->address);

                return (int) (data_get($info, 'account_data.Sequence') ?? 0);
            } catch (\Throwable) {
                return null;
            }
        }

        // Solana, Sui, Bitcoin, Cardano, Hedera, Ton: no account nonce in the same sense
        return null;
    }
}
