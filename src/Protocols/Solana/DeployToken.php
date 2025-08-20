<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Tuupola\Base58;

class DeployToken
{
    /** Prepare meta for SPL fungible token creation (mint seed, pubkey, rent, blockhash). */
    public static function prepare(Transaction $tx, Wallet $wallet, SolanaJsonRpcClient $rpc): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['solana'] = $meta['solana'] ?? [];

        try {
            $b58 = new Base58(['characters' => Base58::BITCOIN]);
            $basePub = $b58->decode($wallet->address);
            $ownerProgram = $b58->decode(SplToken::TOKEN_PROGRAM_ID);
            if (strlen($basePub) === 32 && strlen($ownerProgram) === 32) {
                $seed = (string) ($meta['solana']['mint_seed'] ?? ('mint:'.$tx->id));
                $derived = hash('sha256', $basePub.$seed.$ownerProgram, true);
                $mintPubkey = $b58->encode($derived);
                $meta['solana']['mint_seed'] = $seed;
                $meta['solana']['mint_pubkey'] = $mintPubkey;
                $meta['solana']['mint_space'] = 82;
                try {
                    $rent = $rpc->getMinimumBalanceForRentExemption(82);
                    if ($rent > 0) {
                        $meta['solana']['mint_rent_lamports'] = $rent;
                    }
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
        }

        try {
            $latest = $rpc->getLatestBlockhash();
            $value = $latest['value'] ?? [];
            if (isset($value['blockhash'])) {
                $meta['solana']['recentBlockhash'] = (string) $value['blockhash'];
            }
            if (isset($value['lastValidBlockHeight'])) {
                $meta['solana']['lastValidBlockHeight'] = (int) $value['lastValidBlockHeight'];
            }
        } catch (\Throwable) {
        }

        $tx->meta = $meta;
    }

    /** Build, sign, and submit SPL token creation; persist Contract & Token. Returns signature. */
    public static function submit(Transaction $tx, Wallet $wallet, SolanaJsonRpcClient $rpc, SolanaSigner $signer): string
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana signing');
        }

        $meta = (array) ($tx->meta ?? []);
        $tokenMeta = (array) ($meta['token'] ?? []);
        $name = (string) ($tokenMeta['name'] ?? '');
        $symbol = (string) ($tokenMeta['symbol'] ?? '');
        $decimals = (int) ($tokenMeta['decimals'] ?? 0);
        $initial = (string) ($tokenMeta['initial_supply'] ?? '0');
        if ($name === '' || $symbol === '') {
            throw new \InvalidArgumentException('Token name and symbol are required');
        }

        $mintSeed = (string) ($meta['solana']['mint_seed'] ?? ('mint:'.$tx->id));
        $mintPubkeyB58 = (string) ($meta['solana']['mint_pubkey'] ?? '');
        if ($mintPubkeyB58 === '') {
            throw new \RuntimeException('Missing derived mint pubkey in meta');
        }

        $b58 = new Base58(['characters' => Base58::BITCOIN]);
        $signerPub = $b58->decode($wallet->address);
        $mintPub = $b58->decode($mintPubkeyB58);
        $tokenProgramPub = $b58->decode(SplToken::TOKEN_PROGRAM_ID);
        $systemProgramPub = $b58->decode(SystemProgram::PROGRAM_ID);
        if (strlen($signerPub) !== 32 || strlen($mintPub) !== 32 || strlen($tokenProgramPub) !== 32 || strlen($systemProgramPub) !== 32) {
            throw new \InvalidArgumentException('Invalid pubkey length in SPL creation');
        }

        $mintAuthId = (int) ($meta['authorities']['mint_wallet_id'] ?? $wallet->id);
        if ($mintAuthId !== (int) $wallet->id) {
            throw new \RuntimeException('For now, mint authority must equal the signer wallet');
        }
        $freezeAuthId = $meta['authorities']['freeze_wallet_id'] ?? null;
        $freezeAuthPub = null;
        if ($freezeAuthId) {
            $freezeWallet = \Roberts\Web3Laravel\Models\Wallet::find((int) $freezeAuthId);
            if ($freezeWallet) {
                $freezeAuthPub = $b58->decode($freezeWallet->address);
                if (strlen($freezeAuthPub) !== 32) {
                    $freezeAuthPub = null;
                }
            }
        }

        $recipientAddr = (string) ($meta['recipient']['address'] ?? '');
        $createAta = (bool) ($meta['recipient']['create_ata'] ?? false);
        /** @var string|null $destAta Binary 32-byte associated token account address if resolved upstream */
        $destAta = $meta['solana']['dest_ata'] ?? null;
        if ($recipientAddr !== '') {
            // The adapter will resolve and pass this later if needed
        }

        // Blockhash
        $blockhashB58 = (string) ($meta['solana']['recentBlockhash'] ?? '');
        if ($blockhashB58 === '') {
            $latest = $rpc->getLatestBlockhash();
            $blockhashB58 = (string) ($latest['value']['blockhash'] ?? '');
        }
        if ($blockhashB58 === '') {
            throw new \RuntimeException('Failed to fetch latest blockhash');
        }
        $recentBlockhash = $b58->decode($blockhashB58);
        if (strlen($recentBlockhash) !== 32) {
            throw new \RuntimeException('Invalid blockhash length');
        }

        // Account keys: signer, mint, (optional destAta), token program, system program
        $accountKeys = [$signerPub, $mintPub];
        if ($destAta !== null) {
            $accountKeys[] = $destAta;
        }
        $accountKeys[] = $tokenProgramPub;
        $accountKeys[] = $systemProgramPub;

        $readonlyUnsigned = 2; // token + system
        $header = chr(1).chr(0).chr($readonlyUnsigned);
        $acctSection = self::shortvec(count($accountKeys)).implode('', $accountKeys);

        // Ix1: CreateAccountWithSeed
        $programIndexSystem = count($accountKeys) - 1;
        $lamports = (int) ($meta['solana']['mint_rent_lamports'] ?? 0);
        $space = 82;
        $seedBytes = $mintSeed;
        $ix1data = pack('V', 3)
            .$signerPub
            .self::encodeU64Le((string) strlen($seedBytes))
            .$seedBytes
            .self::encodeU64Le((string) $lamports)
            .self::encodeU64Le((string) $space)
            .$tokenProgramPub;
        $ix1accounts = [0, 1];
        $ci1 = chr($programIndexSystem)
            .self::shortvec(count($ix1accounts))
            .implode('', array_map(fn ($i) => chr($i), $ix1accounts))
            .self::shortvec(strlen($ix1data))
            .$ix1data;

        // Ix2: InitializeMint2
        $programIndexToken = count($accountKeys) - 2;
        $freezeFlag = $freezeAuthPub ? chr(1) : chr(0);
        $ix2data = chr(20).chr($decimals).$signerPub.$freezeFlag.($freezeAuthPub ?: '');
        $ix2accounts = [1];
        $ci2 = chr($programIndexToken)
            .self::shortvec(count($ix2accounts))
            .implode('', array_map(fn ($i) => chr($i), $ix2accounts))
            .self::shortvec(strlen($ix2data))
            .$ix2data;

        $compiled = [$ci1, $ci2];

        // Optional Ix3: MintToChecked (only if destAta resolved externally)
        if ($initial !== '0' && $destAta !== null) {
            $amtLe = self::encodeU64Le($initial);
            $ix3data = chr(14).$amtLe.chr($decimals);
            $ix3accounts = [1, 2, 0];
            $ci3 = chr($programIndexToken)
                .self::shortvec(count($ix3accounts))
                .implode('', array_map(fn ($i) => chr($i), $ix3accounts))
                .self::shortvec(strlen($ix3data))
                .$ix3data;
            $compiled[] = $ci3;
        }

        $ixSection = self::shortvec(count($compiled)).implode('', $compiled);
        $message = $header.$acctSection.$recentBlockhash.$ixSection;

        $secretHex = Crypt::decryptString($wallet->key);
        $secretKey = hex2bin($secretHex);
        if ($secretKey === false) {
            throw new \RuntimeException('Invalid Solana secret key encoding');
        }
        $signature = $signer->sign($message, $secretKey);
        if (strlen($signature) !== 64) {
            throw new \RuntimeException('Invalid signature length');
        }
        $txBytes = self::shortvec(1).$signature.$message;
        $base64 = base64_encode($txBytes);

        $sig = $rpc->sendTransaction($base64);

        // Persist Contract and Token
        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $mintPubkeyB58],
                [
                    'blockchain_id' => $tx->blockchain_id,
                    'creator' => $wallet->address,
                    'abi' => null,
                ]
            );
            $supply = ($initial !== '0' && $destAta !== null) ? $initial : '0';
            Web3Token::query()->firstOrCreate(
                ['contract_id' => $contract->id],
                [
                    'symbol' => $symbol,
                    'name' => $name,
                    'decimals' => $decimals,
                    'total_supply' => $supply,
                ]
            );
            if (! $tx->contract_id) {
                $tx->contract_id = $contract->id;
                $tx->save();
            }
        } catch (\Throwable) {
        }

        return $sig;
    }

    // Helpers duplicated from adapter to keep this file self-contained
    private static function shortvec(int $n): string
    {
        $out = '';
        while (true) {
            $byte = $n & 0x7F;
            $n >>= 7;
            if ($n === 0) {
                $out .= chr($byte);
                break;
            } else {
                $out .= chr($byte | 0x80);
            }
        }

        return $out;
    }

    private static function encodeU64Le(string $dec): string
    {
        $n = gmp_init($dec, 10);
        $be = (string) gmp_export($n, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $be = str_pad($be, 8, "\x00", STR_PAD_LEFT);

        return strrev($be);
    }
}
