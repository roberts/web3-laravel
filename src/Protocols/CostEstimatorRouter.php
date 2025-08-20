<?php

namespace Roberts\Web3Laravel\Protocols;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Contracts\TransactionCostEstimator as EstimatorContract;

class CostEstimatorRouter
{
    /** @var array<string, class-string<EstimatorContract>> */
    private array $map = [
        'evm' => \Roberts\Web3Laravel\Protocols\Evm\TransactionCostEstimator::class,
        'solana' => \Roberts\Web3Laravel\Protocols\Solana\TransactionCostEstimator::class,
        'xrpl' => \Roberts\Web3Laravel\Protocols\Xrpl\TransactionCostEstimator::class,
        'sui' => \Roberts\Web3Laravel\Protocols\Sui\TransactionCostEstimator::class,
        'bitcoin' => \Roberts\Web3Laravel\Protocols\Bitcoin\TransactionCostEstimator::class,
        'cardano' => \Roberts\Web3Laravel\Protocols\Cardano\TransactionCostEstimator::class,
        'hedera' => \Roberts\Web3Laravel\Protocols\Hedera\TransactionCostEstimator::class,
        'ton' => \Roberts\Web3Laravel\Protocols\Ton\TransactionCostEstimator::class,
    ];

    public function for(BlockchainProtocol $protocol): EstimatorContract
    {
        $key = $protocol->value;
        if (! isset($this->map[$key])) {
            throw new \InvalidArgumentException("No TransactionCostEstimator registered for protocol {$key}");
        }

        /** @var EstimatorContract $estimator */
        $estimator = app($this->map[$key]);

        return $estimator;
    }
}
