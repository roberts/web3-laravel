<?php

namespace Roberts\Web3Laravel\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Events\WalletTokenAllowanceUpdated;
use Roberts\Web3Laravel\Events\WalletTokenBalanceUpdated;

class SendBalanceUpdateWebhook
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(WalletTokenBalanceUpdated::class, [$this, 'onBalanceUpdated']);
        $events->listen(WalletTokenAllowanceUpdated::class, [$this, 'onAllowanceUpdated']);
    }

    public function onBalanceUpdated(WalletTokenBalanceUpdated $event): void
    {
        $url = config('web3-laravel.webhooks.balance_updates');
        if (! $url) {
            return;
        }

        $payload = [
            'wallet' => $event->wallet->address,
            'protocol' => $event->wallet->protocol->value,
            'token_id' => $event->token->id,
            'contract' => optional($event->token->contract)->address,
            'old' => $event->oldBalance,
            'new' => $event->newBalance,
            'formatted_new' => $event->record?->formattedBalance(),
            'updated_at' => now()->toIso8601String(),
        ];

        $headers = [];
        if ($secret = config('web3-laravel.webhooks.secret')) {
            $headers['X-Web3Laravel-Secret'] = $secret;
        }

        try {
            Http::withHeaders($headers)->post($url, $payload);
        } catch (\Throwable $e) {
            // Swallow errors to not disrupt app flow; consider logging
        }
    }

    public function onAllowanceUpdated(WalletTokenAllowanceUpdated $event): void
    {
        $url = config('web3-laravel.webhooks.allowance_updates');
        if (! $url) {
            return;
        }

        /** @var \Roberts\Web3Laravel\Protocols\ProtocolRouter $router */
        $router = app(\Roberts\Web3Laravel\Protocols\ProtocolRouter::class);
        $adapter = $router->for($event->owner->protocol);
        $normalizedSpender = $adapter->normalizeAddress($event->spender);

        $payload = [
            'owner' => $event->owner->address,
            'spender' => $normalizedSpender,
            'protocol' => $event->owner->protocol->value,
            'token_id' => $event->token->id,
            'contract' => optional($event->token->contract)->address,
            'old' => $event->oldAllowance,
            'new' => $event->newAllowance,
            'updated_at' => now()->toIso8601String(),
        ];

        $headers = [];
        if ($secret = config('web3-laravel.webhooks.secret')) {
            $headers['X-Web3Laravel-Secret'] = $secret;
        }

        try {
            Http::withHeaders($headers)->post($url, $payload);
        } catch (\Throwable $e) {
            // Swallow errors
        }
    }
}
