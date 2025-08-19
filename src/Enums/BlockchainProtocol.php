<?php

namespace Roberts\Web3Laravel\Enums;

enum BlockchainProtocol: string
{
    case EVM = 'evm';
    case SOLANA = 'solana';

    public function isEvm(): bool
    {
        return $this === self::EVM;
    }

    public function isSolana(): bool
    {
        return $this === self::SOLANA;
    }
}
