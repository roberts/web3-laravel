# SDK integrations: Hedera, Cardano, and TON

This package supports an SDK‑first, fully server‑side signing approach for Hedera, Cardano, and TON. Your Laravel app binds concrete SDK implementations that use the signer wallet stored in your database to create/mint tokens automatically. No client‑side signing is required.

See also: `docs/deploytokens.md` for the end‑to‑end token creation pipeline.

## How it works

- You enqueue a token creation with `Wallet::createFungibleToken([...])`.
- During submit, the per‑chain DeployToken helper:
  - Tries your SDK binding first.
  - Falls back to optional HTTP proxy (Hedera/Cardano) if configured.
  - Otherwise persists a stub record (keeps tests and flows green without network access).

## Interfaces to implement in your app

Your app should provide concrete implementations and bind them in a service provider. These interfaces live in the package and are used at runtime.

### Hedera

```php
namespace Roberts\\Web3Laravel\\Protocols\\Hedera;

use Roberts\\Web3Laravel\\Models\\Wallet;

interface HederaSdkInterface
{
    /**
     * Create a fungible token via a Hedera SDK using the signer wallet.
     * Return array with keys: tokenId (string), txId (string).
     */
    public function createFungibleToken(Wallet $signer, array $params): array;
}
```

Expected params:
- name: string
- symbol: string
- decimals: int
- initial_supply: string (base units)
- recipient: string|null (address)

Expected return:
- tokenId: string (e.g., "0.0.123456")
- txId: string

### Cardano

```php
namespace Roberts\\Web3Laravel\\Protocols\\Cardano;

use Roberts\\Web3Laravel\\Models\\Wallet;

interface CardanoSdkInterface
{
    /**
     * Mint a native asset using a Cardano SDK.
     * Return array with keys: assetId (string), txHash (string).
     */
    public function mintNativeAsset(Wallet $signer, array $params): array;
}
```

Expected params:
- name: string
- symbol: string
- decimals: int (for UI/formatting)
- initial_supply: string (base units)
- recipient: string|null (address)

Expected return:
- assetId: string ("<policyId>.<assetNameHex>")
- txHash: string

### TON (Jetton)

```php
namespace Roberts\\Web3Laravel\\Protocols\\Ton;

use Roberts\\Web3Laravel\\Models\\Wallet;

interface TonSdkInterface
{
    /**
     * Deploy a Jetton master and optionally mint initial supply.
     * Return array with keys: master (string), txHash (string).
     */
    public function deployJetton(Wallet $signer, array $params): array;
}
```

Expected params:
- name: string
- symbol: string
- decimals: int (default 9 if not relevant to your SDK)
- initial_supply: string (base units)
- recipient: string|null (address)

Expected return:
- master: string (Jetton master address)
- txHash: string

## Binding your SDKs in Laravel

Bind your implementations in a service provider (e.g., `AppServiceProvider`). The package registers default throw‑by‑default bindings; your app should override them.

```php
use Illuminate\\Support\\ServiceProvider;
use Roberts\\Web3Laravel\\Protocols\\Hedera\\HederaSdkInterface;
use Roberts\\Web3Laravel\\Protocols\\Cardano\\CardanoSdkInterface;
use Roberts\\Web3Laravel\\Protocols\\Ton\\TonSdkInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HederaSdkInterface::class, function () {
            return new class implements HederaSdkInterface {
                public function createFungibleToken($signer, array $p): array
                {
                    // 1) Decrypt key for signer (server‑side)
                    $secretHex = $signer->decryptKey();
                    // 2) Use your Hedera SDK to build+sign TokenCreate, optionally initial mint/transfer
                    // 3) Return tokenId and txId
                    return ['tokenId' => '0.0.123456', 'txId' => '0.0.100@1690000000.000000000'];
                }
            };
        });

        $this->app->bind(CardanoSdkInterface::class, function () {
            return new class implements CardanoSdkInterface {
                public function mintNativeAsset($signer, array $p): array
                {
                    $secretHex = $signer->decryptKey();
                    // Use your Cardano SDK (policy/native script) to mint and send to recipient
                    return ['assetId' => 'abcd...ef01.544f4b454e', 'txHash' => '0x...'];
                }
            };
        });

        $this->app->bind(TonSdkInterface::class, function () {
            return new class implements TonSdkInterface {
                public function deployJetton($signer, array $p): array
                {
                    $secretHex = $signer->decryptKey();
                    // Compile+deploy Jetton master and optionally mint to recipient
                    return ['master' => 'EQD...', 'txHash' => 'boc:...'];
                }
            };
        });
    }
}
```

Notes:
- `Wallet::decryptKey()` uses your app’s APP_KEY to decrypt the stored private key. Keep it secure.
- Implement robust error handling and bubble up errors as exceptions; the transaction pipeline will mark failures accordingly.

## Fallbacks and configuration

- Hedera/Cardano:
  - Optional HTTP proxy endpoints can be set in `config/web3-laravel.php` (`hedera.submit_url`, `cardano.submit_url`).
  - If neither SDK nor proxy is available, the helpers persist stubs so flows and tests don’t break.
- TON:
  - If no SDK is bound, you can still submit a pre‑signed BOC by placing it in `tx.meta.ton.boc` and setting TON RPC in `.env`.
  - Otherwise, a stub is persisted.

## Security considerations

- Do all signing server‑side; do not expose raw keys to clients.
- Rate‑limit and protect any SDK/proxy endpoints you operate.
- Monitor gas/fees for large initial supplies.

## Troubleshooting

- If you see "SDK not bound" exceptions, ensure you’ve registered your bindings and that your provider is loaded.
- Verify the signer wallet is custodial/shared and has a stored private key.
- Inspect `Transaction.meta` for the parameters your SDK receives.
