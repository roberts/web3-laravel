# Deploying Fungible Tokens (Multi‑Chain)

This guide explains how to launch fungible tokens using Web3 Laravel’s chain‑agnostic API and protocol adapters. You enqueue a token creation transaction from a wallet; the adapter handles per‑chain details (prepare → submit → confirm), and the package persists `Contract` and `Token` models for analytics.

## Quick start (enqueue a token creation)

```php
use Roberts\Web3Laravel\Models\Wallet;

$signer = Wallet::find($signerWalletId); // custodial/shared wallet

$tx = $signer->createFungibleToken([
		// Choose one of: blockchain_id OR protocol
		// 'blockchain_id' => 123, // preferred, loads RPC from DB
		'protocol' => 'solana', // or 'sui', 'hedera', 'cardano'

		'name' => 'Example Token',
		'symbol' => 'EXMPL',
		'decimals' => 9,
		'initial_supply' => '1000000000', // raw base units

		// Optional recipient (defaults to signer)
		'recipient_address' => 'recipient-address-here',
		// 'recipient_wallet_id' => 456,

		// Chain‑specific options
		'create_recipient_ata' => true, // Solana only
		'mint_authority_wallet_id' => $signer->id,
		'freeze_authority_wallet_id' => null,

		// Persist any extra metadata on the Token model
		'meta' => ['category' => 'community'],
]);

// A queued job will prepare, sign, and (where implemented) broadcast.
// Track status via $tx->refresh()->status and $tx->tx_hash.
```

What you get:

- A `Transaction` record with `function_params.operation = create_fungible_token` and `meta.standard` set by protocol.
- After submit, a `Contract` (chain‑native token identifier) and `Token` record get created and linked.
- Events fire throughout the pipeline; confirmations are tracked per chain when available.

## Per‑chain details

### Solana (SPL)

- Requirements: PHP `ext-sodium` for ed25519, a Solana RPC endpoint in your `blockchains` table (or `config/web3-laravel.php` fallback).
- Flow:
	1) Prepare collects recent blockhash and rent for the mint account.
	2) Submit creates the mint (CreateAccountWithSeed), initializes it (InitializeMint2), and optionally `MintToChecked` to a recipient.
	3) Persists `Contract.address = <mint pubkey>` and `Token` with your metadata.
- Options:
	- `create_recipient_ata` (bool): When true and supported, will attempt to ensure the recipient Associated Token Account exists before minting (auto‑create can be toggled in config: `solana.auto_create_atas`).
	- `mint_authority_wallet_id`, `freeze_authority_wallet_id`: Choose authorities (default mint authority = signer).
- Returns: a base58 signature in `tx_hash`. Confirmations use Solana’s finalized commitment.

### Sui (Coin Factory)

- Requirements: PHP `ext-sodium`, Sui RPC configured, and a deployed Coin Factory package.
- Configure the Coin Factory in `.env` (or config):

```env
SUI_COIN_FACTORY_PACKAGE=0xYOUR_PACKAGE_ID
SUI_COIN_FACTORY_MODULE=factory
SUI_COIN_FACTORY_CREATE_FN=create
SUI_COIN_FACTORY_MINT_AFTER=true
SUI_GAS_BUDGET_MULTIPLIER=10
SUI_MIN_GAS_BUDGET=1000
```

- Flow:
	1) Prepare caches `referenceGasPrice` and surfaces factory config in `tx.meta.sui.factory`.
	2) Submit performs a `moveCall` to the factory’s `create(name, SYMBOL, decimals)`, signs, and executes.
	3) Parses effects/object changes for `TreasuryCap<...>` to extract the actual `coin_type` and stores it in `tx.meta.sui.coin_type`.
	4) Persists `Contract.address = <coin_type>` and `Token` metadata.
	5) If `SUI_COIN_FACTORY_MINT_AFTER=true` and a `TreasuryCap` is present, performs a `mint<T>(treasury_cap, amount)` then best‑effort transfers a minted coin to the recipient.
- Returns: Sui tx `digest` in `tx_hash`. Confirmations use checkpoints via `sui_getTransactionBlock`.
- Fallback: If no factory package is configured, the system persists models and returns a synthetic digest (no on‑chain submit).

### Hedera (HTS)

- Approach: SDK-first. Bind `HederaSdkInterface` in your app to perform server-side signing and on-chain token creation from the signer wallet.
- Fallbacks: optional HTTP proxy or stub persistence.
- Flow:
	- If `HederaSdkInterface` is bound, calls `createFungibleToken()` with signer + params and persists returned `tokenId`/`txId`.
	- Else if `HEDERA_SUBMIT_URL` is set, POSTs with token params and expects `{ tokenId, txId }`.
	- Else persists a placeholder `tokenId` and synthetic `txId`.
- Configure in `.env` (optional):

```env
HEDERA_SUBMIT_URL=https://your-hedera-proxy.example.com/token/create
HEDERA_AUTH_HEADER=X-API-KEY
HEDERA_AUTH_TOKEN=your_key
```
- Planned: Native integration using Hedera SDK server-side.

### XRPL (IOU issuance)

- Requirements: XRPL RPC with server-side signing enabled (or a trusted signing proxy). The signer wallet must be custodial/shared; store the secret in your app and keep it encrypted at rest.
- Configure in `.env`:

```env
XRPL_SIGN_MODE=server
XRPL_AUTO_TRUSTLINE=false
```

- Flow:
	1) Prepare records `meta.xrpl.Currency` from your symbol, `Sequence`, `Fee`, and `sign_mode`.
	2) Submit persists Contract with canonical `<issuer>:<currency>` and Token metadata.
	3) If `recipient.address` and non-zero `initial_supply`, optionally auto‑creates a trustline (see below) then submits a Payment to the recipient.
	4) Recipient must have a trustline; otherwise the Payment will fail.
- Returns: XRPL transaction hash (or a stub if signing is not configured).
- Notes:
	- 3-char currency codes use the native short-code; longer symbols are hex-encoded up to 20 bytes.
	- You can optionally set issuer AccountSet flags before distribution (future helper).

Auto-trustline (optional):

- Set `XRPL_AUTO_TRUSTLINE=true` to enable a server-side TrustSet when the recipient is a managed wallet in your DB and has a stored `meta.xrpl.secret`.
- The TrustSet is signed in-process via `rippled sign` and submitted before the Payment.

### TON (Jetton)

- Approach: SDK-first. Bind `TonSdkInterface` in your app to compile, sign, and deploy the Jetton master, and optionally mint initial supply.
- Fallbacks: sendBoc with a pre-signed BOC, or stub persistence.
- Flow:
	- If `TonSdkInterface` is bound, calls `deployJetton()` and persists returned `master` and `txHash`.
	- Else if `TON_RPC_BASE` is set and `tx.meta.ton.boc` provided, submits via `sendBoc` and stores returned id/hash. Optionally provide `tx.meta.ton.master` to persist a specific master address.
	- Else persists synthetic master id and stub tx id.
- Configure in `.env` (optional):

```env
TON_RPC_BASE=https://toncenter.com/api/v2
TON_API_KEY=your_api_key
```

- Notes:
	- This package doesn’t build/sign the Jetton deploy BOC yet; you can generate the signed BOC out-of-band and attach it to the transaction meta before submit.

### Cardano (Native Asset)

- Approach: SDK-first. Bind `CardanoSdkInterface` in your app to perform server-side signing and mint the native asset.
- Fallbacks: optional HTTP proxy or stub persistence.
- Flow:
	- If `CardanoSdkInterface` is bound, calls `mintNativeAsset()` and persists returned `assetId`/`txHash`.
	- Else if `CARDANO_SUBMIT_URL` is set, POSTs and expects `{ assetId, txHash }`.
	- Else persists `policyId.assetNameHex` and a synthetic tx hash.
- Configure in `.env` (optional):

```env
CARDANO_SUBMIT_URL=https://your-cardano-proxy.example.com/token/mint
CARDANO_AUTH_HEADER=X-API-KEY
CARDANO_AUTH_TOKEN=your_key
```
- Planned: Native minting using a policy/native script server-side.

## Events & confirmation

- Events: `TransactionPreparing`, `TransactionPrepared`, `TransactionSubmitted`, `TransactionConfirmed`, `TransactionFailed`.
- Confirmations: Solana/Sui implementations poll via their RPCs; Hedera/Cardano are no‑ops until real submit is added.

## Tips

- Use a custodial/shared wallet as the signer; external wallets can’t store private keys in DB.
- Prefer `blockchain_id` over `protocol` to leverage per‑chain RPC endpoints from your DB.
- For large initial supplies, ensure the signer wallet has enough native gas token to cover creation and mint fees.

