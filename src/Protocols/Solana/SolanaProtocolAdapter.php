<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Tuupola\Base58;

class SolanaProtocolAdapter implements ProtocolAdapter
{
    public function __construct(private SolanaJsonRpcClient $rpc, private SolanaSigner $signer) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::SOLANA;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana key generation');
        }

        $kp = \sodium_crypto_sign_keypair();
        $secret = \sodium_crypto_sign_secretkey($kp);
        $public = \sodium_crypto_sign_publickey($kp);
        $address = (new Base58(['characters' => Base58::BITCOIN]))->encode($public);
        $encryptedKey = Crypt::encryptString(bin2hex($secret));

        $data = array_merge([
            'address' => $address,
            'key' => $encryptedKey,
            'protocol' => BlockchainProtocol::SOLANA,
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
        return (string) $this->rpc->getBalance($wallet->address);
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana signing');
        }

        // Decrypt secret key (hex encoded) and decode addresses
        $secretHex = Crypt::decryptString($from->key);
        $secretKey = hex2bin($secretHex);
        if ($secretKey === false) {
            throw new \RuntimeException('Invalid Solana secret key encoding');
        }

        $b58 = new Base58(['characters' => Base58::BITCOIN]);
        $fromPub = $b58->decode($from->address);
        $toPub = $b58->decode($toAddress);
        $programPub = $b58->decode(SystemProgram::PROGRAM_ID);

        if (strlen($fromPub) !== 32 || strlen($toPub) !== 32 || strlen($programPub) !== 32) {
            throw new \InvalidArgumentException('Invalid public key(s) length');
        }

        // Fetch recent blockhash
        $latest = $this->rpc->getLatestBlockhash();
        $blockhashB58 = $latest['value']['blockhash'] ?? null;
        if (! is_string($blockhashB58)) {
            throw new \RuntimeException('Failed to fetch latest blockhash');
        }
        $recentBlockhash = $b58->decode($blockhashB58);
        if (strlen($recentBlockhash) !== 32) {
            throw new \RuntimeException('Invalid blockhash length');
        }

        // Build a legacy (v0) transfer message
        $lamportsLe = $this->encodeU64Le($amount);
        $instructionData = \pack('V', 2).$lamportsLe; // SystemInstruction::Transfer (2) + lamports u64 LE

        // Accounts vector: [from (signer,writable), to (writable), program id]
        $accounts = [$fromPub, $toPub, $programPub];
        $programIndex = 2; // index in $accounts

        // Header: numRequiredSignatures, numReadonlySigned, numReadonlyUnsigned
        $header = \chr(1).\chr(0).\chr(1); // 1 signer, 0 ro signed, 1 ro unsigned (program id)

        // Account addresses (shortvec length + concat 32-byte pubkeys)
        $acctSection = $this->shortvec(count($accounts)).implode('', $accounts);

        // Compiled instruction
        $acctIdxs = [0, 1];
        $ci = \chr($programIndex)
            .$this->shortvec(count($acctIdxs))
            .implode('', array_map(fn ($i) => \chr($i), $acctIdxs))
            .$this->shortvec(strlen($instructionData))
            .$instructionData;

        $ixSection = $this->shortvec(1).$ci;

        // Message is header + account keys + recent blockhash + instructions
        $message = $header.$acctSection.$recentBlockhash.$ixSection;

        // Sign the message and build the transaction (signatures vec + message)
        $signature = $this->signer->sign($message, $secretKey);
        if (strlen($signature) !== 64) {
            throw new \RuntimeException('Invalid signature length');
        }

        $tx = $this->shortvec(1).$signature.$message;
        $base64 = base64_encode($tx);

        return $this->rpc->sendTransaction($base64);
    }

    public function normalizeAddress(string $address): string
    {
        // Base58 addresses are used as-is for Solana.
        return $address;
    }

    public function validateAddress(string $address): bool
    {
        if ($address === '') {
            return false;
        }
        try {
            $decoded = (new Base58(['characters' => Base58::BITCOIN]))->decode($address);
        } catch (\Throwable $e) {
            return false;
        }

        return strlen($decoded) === 32;
    }

    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string
    {
        // For Solana, Token model is ERC-20-centric; best effort: sum SPL token accounts by mint if token has contract address stored as mint.
        $mint = $token->contract->address; // Treat contract address as SPL mint when protocol is Solana
        $filter = $mint ? ['mint' => $mint] : ['programId' => SplToken::TOKEN_PROGRAM_ID];
        $resp = $this->rpc->getTokenAccountsByOwner($ownerAddress, $filter, true);

        return SplToken::sumParsedTokenBalance($resp, $mint);
    }

    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string
    {
        $mint = $token->contract->address;
        if (! is_string($mint) || $mint === '') {
            return '0';
        }
        $resp = $this->rpc->getTokenAccountsByOwner($ownerAddress, ['mint' => $mint], true);
        $value = $resp['value'] ?? [];
        foreach ($value as $acc) {
            $info = $acc['account']['data']['parsed']['info'] ?? null;
            if (! $info) {
                continue;
            }
            $delegate = $info['delegate'] ?? null;
            $delegated = $info['delegatedAmount']['amount'] ?? null;
            if (is_string($delegate) && $delegate !== '' && is_string($delegated)) {
                if ($delegate === $spenderAddress) {
                    return $delegated;
                }
            }
        }

        return '0';
    }

    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
    {
        // Implement SPL Token TransferChecked (program v3), assuming associated token accounts exist.
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana signing');
        }

        $mint = $token->contract->address;
        if (! is_string($mint) || $mint === '') {
            throw new \InvalidArgumentException('Token mint (contract address) is required for SPL transfers');
        }

        // Decode keys
        $b58 = new Base58(['characters' => Base58::BITCOIN]);
        $ownerPub = $b58->decode($from->address);
        $toPub = $b58->decode($toAddress);
        $mintPub = $b58->decode($mint);
        $tokenProgramPub = $b58->decode(SplToken::TOKEN_PROGRAM_ID);
        if (strlen($ownerPub) !== 32 || strlen($toPub) !== 32 || strlen($mintPub) !== 32 || strlen($tokenProgramPub) !== 32) {
            throw new \InvalidArgumentException('Invalid public key length for SPL transfer');
        }

        // Resolve Associated Token Accounts via RPC; require they exist (no implicit create)
        $ownerAta = $this->resolveTokenAccount($from->address, $mint);
        $toAta = $this->resolveTokenAccount($toAddress, $mint);
        if ($ownerAta === null) {
            throw new \RuntimeException('Owner associated token account not found');
        }
        if ($toAta === null) {
            throw new \RuntimeException('Recipient associated token account not found; create ATA first');
        }

        // Build instruction for TransferChecked
        // Layout: instruction(12) u8 | amount u64 LE | decimals u8
        $amountLe = $this->encodeU64Le($amount);
        $decimals = (int) ($token->decimals ?? 0);
        $ixData = chr(12).$amountLe.chr($decimals);

        // Accounts (in order): source, mint, destination, owner (signer)
        $accounts = [
            $ownerAta, // source token account (owner ATA)
            $mintPub,  // mint
            $toAta,    // destination token account (recipient ATA)
            $ownerPub, // owner authority (signer)
            $tokenProgramPub, // program id (written separately below, but include as read-only unsigned count)
        ];

        // Message build similar to native, but with token program as program id and account metas.
        $latest = $this->rpc->getLatestBlockhash();
        $blockhashB58 = $latest['value']['blockhash'] ?? null;
        if (! is_string($blockhashB58)) {
            throw new \RuntimeException('Failed to fetch latest blockhash');
        }
        $recentBlockhash = $b58->decode($blockhashB58);
        if (strlen($recentBlockhash) !== 32) {
            throw new \RuntimeException('Invalid blockhash length');
        }

        // Decrypt secret key
        $secretHex = Crypt::decryptString($from->key);
        $secretKey = hex2bin($secretHex);
        if ($secretKey === false) {
            throw new \RuntimeException('Invalid Solana secret key encoding');
        }

        // Build message
        // Header: 1 signer (owner), 0 readonly signed, 2 readonly unsigned (mint, token program)
        $header = chr(1).chr(0).chr(2);

        // Account keys: [owner, owner_ata, mint, dest_ata, token_program]
        // Ensure unique order for program index referencing; we’ll set program index as the last element
        $accountKeys = [$ownerPub, $ownerAta, $mintPub, $toAta, $tokenProgramPub];
        $acctSection = $this->shortvec(count($accountKeys)).implode('', $accountKeys);

        // Program index in account keys
        $programIndex = 4;

        // Accounts for instruction are indices into account keys
        $acctIdxs = [1, 2, 3, 0]; // source, mint, destination, owner
        $ci = chr($programIndex)
            .$this->shortvec(count($acctIdxs))
            .implode('', array_map(fn ($i) => chr($i), $acctIdxs))
            .$this->shortvec(strlen($ixData))
            .$ixData;

        $ixSection = $this->shortvec(1).$ci;

        $message = $header.$acctSection.$recentBlockhash.$ixSection;

        // Sign and submit
        $signature = $this->signer->sign($message, $secretKey);
        if (strlen($signature) !== 64) {
            throw new \RuntimeException('Invalid signature length');
        }
        $tx = $this->shortvec(1).$signature.$message;
        $base64 = base64_encode($tx);

        return $this->rpc->sendTransaction($base64);
    }

    /** Shortvec (compact-u16) length encoding used by Solana. */
    private function shortvec(int $n): string
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

    /** Encode a decimal string as 8-byte little-endian u64 using GMP. */
    private function encodeU64Le(string $dec): string
    {
        $n = gmp_init($dec, 10);
        $be = (string) gmp_export($n, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $be = str_pad($be, 8, "\x00", STR_PAD_LEFT);

        return strrev($be);
    }

    /** Resolve the first SPL token account (ATA) for owner/mint; returns 32-byte pubkey or null if not found. */
    private function resolveTokenAccount(string $ownerAddress, string $mintAddress): ?string
    {
        $resp = $this->rpc->getTokenAccountsByOwner($ownerAddress, ['mint' => $mintAddress], true);
        $value = $resp['value'] ?? [];
        if (! empty($value)) {
            $pubkey = $value[0]['pubkey'] ?? null;
            if (is_string($pubkey) && $pubkey !== '') {
                $b58 = new Base58(['characters' => Base58::BITCOIN]);
                $decoded = $b58->decode($pubkey);
                if (strlen($decoded) === 32) {
                    return $decoded;
                }
            }
        }

        return null;
    }
}
