<?php

namespace Roberts\Web3Laravel\Enums;

enum BlockchainProtocol: string
{
    case EVM = 'evm';
    case SOLANA = 'solana';
    case BITCOIN = 'bitcoin';
    case SUI = 'sui';
    case XRPL = 'xrpl';
    case CARDANO = 'cardano';
    case HEDERA = 'hedera';
    case TON = 'ton';

    public function isEvm(): bool
    {
        return $this === self::EVM;
    }

    public function isSolana(): bool
    {
        return $this === self::SOLANA;
    }

    public function isBitcoin(): bool
    {
        return $this === self::BITCOIN;
    }

    public function isSui(): bool
    {
        return $this === self::SUI;
    }

    public function isXrpl(): bool
    {
        return $this === self::XRPL;
    }

    public function isCardano(): bool
    {
        return $this === self::CARDANO;
    }

    public function isHedera(): bool
    {
        return $this === self::HEDERA;
    }

    public function isTon(): bool
    {
        return $this === self::TON;
    }
}
