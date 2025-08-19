<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

class SplToken
{
    public const TOKEN_PROGRAM_ID = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';

    /** Sum balances from jsonParsed getTokenAccountsByOwner response. */
    public static function sumParsedTokenBalance(array $rpcResponse, ?string $mint = null): string
    {
        $sum = 0;
        $value = $rpcResponse['value'] ?? [];
        foreach ($value as $acc) {
            $info = $acc['account']['data']['parsed']['info'] ?? null;
            if (! $info) {
                continue;
            }
            if ($mint && ($info['mint'] ?? '') !== $mint) {
                continue;
            }
            $amount = (int) (($info['tokenAmount']['amount'] ?? '0'));
            $sum += $amount;
        }

        return (string) $sum;
    }
}
