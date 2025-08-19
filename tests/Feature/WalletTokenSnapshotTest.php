<?php

use Roberts\Web3Laravel\Services\WalletTokenService;

// Protocol-agnostic: service is bound; protocol specifics live in sub-suites
it('resolves wallet token service from container', function () {
    expect(app(WalletTokenService::class))->toBeInstanceOf(WalletTokenService::class);
});
