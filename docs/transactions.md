# Transactions: Eloquent model and async pipeline (multi‑chain)

Web3 Laravel provides a chain‑agnostic `Transaction` model and an asynchronous prepare → submit → confirm pipeline handled by protocol adapters (EVM, Solana, Sui, XRPL, etc.). You can enqueue a transaction for tracked sending or perform an immediate send from a wallet.

## At a glance

- Create a `Transaction` record; a queued job prepares, signs, and broadcasts it.
- Adapters fill protocol‑specific details and update `tx_hash`, `status`, and `meta`.
- Confirmations are monitored per chain until a configurable threshold.
- Lifecycle events fire at key milestones for observability and integrations.

## Create and track a transaction

```php
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

$wallet = Wallet::first();

$tx = Transaction::create([
	'wallet_id' => $wallet->id,
	'to' => '0x0000000000000000000000000000000000000000',
	'value' => '0x3e8', // 1000 wei (hex or decimal string)
	// 'data' => '0x...', // optional calldata for contract calls
]);

// Later, the async pipeline updates the record:
// $tx->status  => 'submitted' | 'confirmed' | 'failed'
// $tx->tx_hash => '0x...' (EVM) | base58 (Solana) | digest (Sui) | etc.
```

### What’s auto‑filled (EVM)

- gas_limit: estimated via `eth_estimateGas` with a ~12% buffer
- is_1559: defaults to true
- priority_max: from `eth_maxPriorityFeePerGas` (fallback 1 gwei)
- fee_max: suggested from `eth_gasPrice` when needed

Adapters for other chains compute their own equivalents internally, storing useful context in `tx.meta`.

### Immediate send vs tracked send

- Immediate: `$wallet->send([...])` returns a hash/signature and does not create a DB record.
- Tracked: `Transaction::create([...])` persists a record and runs through the async pipeline.

## Status lifecycle

`status` transitions:

- `pending` → on create
- `submitted` → after broadcast
- `confirmed` → after required confirmations observed
- `failed` → on error (with an `error` message)

Other useful fields: `tx_hash`, `nonce`, `chain_id`, `data`, `access_list`, and any protocol‑specific metadata saved under `meta`.

## Lifecycle events

Hook into transaction progress via events:

- `Roberts\\Web3Laravel\\Events\\TransactionPreparing`
- `Roberts\\Web3Laravel\\Events\\TransactionPrepared`
- `Roberts\\Web3Laravel\\Events\\TransactionSubmitted`
- `Roberts\\Web3Laravel\\Events\\TransactionConfirmed`
- `Roberts\\Web3Laravel\\Events\\TransactionFailed` (reason via `$event->reason`)

Example listener registration (EventServiceProvider):

```php
protected $listen = [
	Roberts\\Web3Laravel\\Events\\TransactionSubmitted::class => [
		App\\Listeners\\NotifyOnSubmission::class,
	],
	Roberts\\Web3Laravel\\Events\\TransactionFailed::class => [
		App\\Listeners\\AlertOnFailure::class,
	],
];
```

Example minimal listener:

```php
namespace App\Listeners;

use Roberts\Web3Laravel\Events\TransactionSubmitted;

class NotifyOnSubmission
{
	public function handle(TransactionSubmitted $event): void
	{
		logger()->info('TX submitted', [
			'id' => $event->transaction->id,
			'hash' => $event->transaction->tx_hash,
		]);
	}
}
```

## Confirmations and watcher

Tune thresholds in `config/web3-laravel.php`:

```php
'confirmations_required' => 6,
'confirmations_poll_interval' => 10,
```

Run the watcher to poll confirmations:

```bash
php artisan web3:watch-confirmations --interval=5
```

### Per‑protocol confirmation notes

- EVM: polls receipt and block number; marks `confirmed` once distance ≥ `confirmations_required`.
- Solana: uses finalized commitment via the adapter and returns a base58 signature.
- Sui: checks checkpoints via `sui_getTransactionBlock` and computes confirmations from the latest checkpoint.
- XRPL: prepare/confirm are implemented; submit path uses a server‑side sign helper (client‑side signing WIP).
- Hedera, Cardano, Ton, Bitcoin: placeholders/stubs for submit or confirm in early phases; status remains `submitted` until real integrations are enabled.

## Advanced: estimation and fees (EVM)

```php
$svc = app(Roberts\Web3Laravel\Services\TransactionService::class);
$gasHex = $svc->estimateGas($wallet, ['to' => $to, 'value' => $value, 'data' => $data]);
$fees = $svc->suggestFees($wallet); // ['priority' => '0x..', 'max' => '0x..']
```

## Multi‑chain adapters and metadata

- The protocol router selects the correct adapter from the wallet’s `protocol`.
- Each adapter may add `meta` entries during `prepare` (e.g., Solana recent blockhash, Sui `referenceGasPrice`, input coins, etc.).
- When transactions represent higher‑level operations (e.g., token creation), adapters read `function_params` and `meta` to build chain‑specific payloads and persist resulting models.

