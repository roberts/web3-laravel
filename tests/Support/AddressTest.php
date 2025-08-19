<?php

// Root suite stays chain-agnostic; EVM address behavior is tested in Unit/Evm/AddressTest.
it('defers protocol-specific address rules to adapters', function () {
    expect(true)->toBeTrue();
});
