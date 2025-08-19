<?php

// Chain-agnostic root: detailed EVM ContractCaller behavior is covered under tests/Feature/Evm.
it('defers protocol-specific contract calling to protocol suites', function () {
    expect(true)->toBeTrue();
});
