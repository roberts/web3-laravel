<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

class SolanaSigner
{
    /**
     * Sign a message with an ed25519 secret key (binary).
     * Returns the detached signature (binary).
     */
    public function sign(string $message, string $secretKey): string
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana signing');
        }
        return sodium_crypto_sign_detached($message, $secretKey);
    }
}
