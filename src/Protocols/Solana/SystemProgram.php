<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

class SystemProgram
{
    public const PROGRAM_ID = '11111111111111111111111111111111';

    /** Build a SystemProgram::Transfer instruction. */
    public static function transfer(string $fromPubkey, string $toPubkey, int $lamports): array
    {
        // Instruction layout: 4 bytes (u32) discriminator for Transfer, then 8 bytes lamports (LE)
        // For simplicity, we leave encoding to a later milestone; return a symbolic structure now.
        return [
            'programId' => self::PROGRAM_ID,
            'keys' => [
                ['pubkey' => $fromPubkey, 'isSigner' => true, 'isWritable' => true],
                ['pubkey' => $toPubkey, 'isSigner' => false, 'isWritable' => true],
            ],
            'data' => ['type' => 'transfer', 'lamports' => $lamports],
        ];
    }
}
