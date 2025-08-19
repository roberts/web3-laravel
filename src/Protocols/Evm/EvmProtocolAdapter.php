<?php

namespace Roberts\Web3Laravel\Protocols\Evm;

use Elliptic\EC;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter;
use Roberts\Web3Laravel\Support\Address;
use Roberts\Web3Laravel\Support\Hex;
use Roberts\Web3Laravel\Support\Keccak;

class EvmProtocolAdapter implements ProtocolAdapter, ProtocolTransactionAdapter
{
    public function __construct(private EvmClientInterface $evm) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::EVM;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        $privHex = '0x'.str_pad($keyPair->getPrivate('hex'), 64, '0', STR_PAD_LEFT);

        $pub = $keyPair->getPublic(false, 'hex'); // 04 + x + y
        $pubNoPrefix = substr($pub, 2);
        $hash = Keccak::hash($pubNoPrefix, true);
        $address = '0x'.substr(Hex::stripZero($hash), -40);
        $address = strtolower($address);

        $data = array_merge([
            'address' => $address,
            'key' => $privHex, // Wallet mutator will encrypt
            'protocol' => BlockchainProtocol::EVM,
            'is_active' => true,
        ], $attributes);

        if ($owner instanceof Model) {
            $data['owner_id'] = $owner->getKey();
        }
        if ($blockchain) {
            $data['blockchain_id'] = $blockchain->getKey();
        }

        return Wallet::create($data);
    }

    public function getNativeBalance(Wallet $wallet): string
    {
        return (string) $this->evm->getBalance($wallet->address, 'latest');
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        // Leave EVM transfer to TransactionService in current codebase; not implemented here yet.
        throw new \RuntimeException('transferNative not implemented via adapter for EVM');
    }

    public function normalizeAddress(string $address): string
    {
        return Address::normalize($address);
    }

    public function validateAddress(string $address): bool
    {
        return Address::isValidEvm($address);
    }

    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string
    {
        // Reuse existing ERC-20 path for EVM
        $svc = app(\Roberts\Web3Laravel\Services\TokenService::class);

        return $svc->balanceOf($token, $ownerAddress);
    }

    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string
    {
        $svc = app(\Roberts\Web3Laravel\Services\TokenService::class);

        return $svc->allowance($token, $ownerAddress, $spenderAddress);
    }

    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
    {
        // For EVM, token transfers are handled via TransactionService/TokenService to build ABI calls.
        throw new \RuntimeException('transferToken not implemented via adapter for EVM');
    }

    public function approveToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress, string $amount): string
    {
        // For EVM, approvals are handled via TransactionService/TokenService to build ABI calls.
        throw new \RuntimeException('approveToken not implemented via adapter for EVM');
    }

    public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress): string
    {
        // For EVM, approvals are handled via TransactionService/TokenService to build ABI calls (approve 0).
        throw new \RuntimeException('revokeToken not implemented via adapter for EVM');
    }

    // -----------------------------
    // ProtocolTransactionAdapter (EVM)
    // -----------------------------
    public function prepareTransaction(\Roberts\Web3Laravel\Models\Transaction $tx, Wallet $wallet): void
    {
        // Mirror current PrepareTransaction logic but keep here for EVM specifics
        // We keep it minimal; the job continues to do generic parts.
        if (empty($tx->chain_id)) {
            $tx->chain_id = (int) (config('web3-laravel.default_chain_id'));
        }
        // Fill nonce if missing
        if ($tx->nonce === null) {
            try {
                $tx->nonce = (int) $this->evm->getTransactionCount($wallet->address, 'pending');
            } catch (\Throwable) {
                // leave null; node will handle or submission will fail
            }
        }
    }

    public function submitTransaction(\Roberts\Web3Laravel\Models\Transaction $tx, Wallet $wallet): string
    {
        $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);
        $payload = [
            'to' => $tx->to,
            'value' => $tx->value,
            'data' => $tx->data,
            'gas' => $tx->gas_limit,
            'nonce' => $tx->nonce,
            'chainId' => $tx->chain_id,
        ];
        if ($tx->is_1559) {
            $payload['maxFeePerGas'] = $tx->fee_max;
            $payload['maxPriorityFeePerGas'] = $tx->priority_max;
            if (! empty($tx->access_list)) {
                $payload['accessList'] = json_decode($tx->access_list, true) ?: [];
            }
        } else {
            $payload['gasPrice'] = $tx->gwei;
        }

        return $svc->sendRaw($wallet, $payload);
    }

    public function checkConfirmations(\Roberts\Web3Laravel\Models\Transaction $tx, Wallet $wallet): array
    {
        try {
            $receipt = $this->evm->getTransactionReceipt((string) $tx->tx_hash);
            if (! $receipt) {
                return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
            }
            $currentBlock = $this->evm->blockNumber();
            $receiptBlock = $receipt['blockNumber'] ?? null;
            if (! $receiptBlock || ! $currentBlock) {
                return ['confirmed' => false, 'confirmations' => 0, 'receipt' => $receipt, 'blockNumber' => null];
            }
            $blockNum = $this->parseHexOrInt($receiptBlock);
            $head = $this->parseHexOrInt($currentBlock);
            $confirmations = max(0, $head - $blockNum + 1);
            $required = (int) config('web3-laravel.confirmations_required', 6);

            return [
                'confirmed' => $confirmations >= $required,
                'confirmations' => $confirmations,
                'receipt' => $receipt,
                'blockNumber' => $blockNum,
            ];
        } catch (\Throwable) {
            return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
        }
    }

    private function parseHexOrInt(string|int|null $v): int
    {
        if ($v === null) {
            return 0;
        }
        if (is_string($v) && str_starts_with($v, '0x')) {
            return (int) hexdec(substr($v, 2));
        }

        return (int) $v;
    }
}
