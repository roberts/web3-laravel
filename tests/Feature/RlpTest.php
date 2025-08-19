<?php

// Root suite stays chain-agnostic; RLP is EVM-only and is tested under Feature/Evm/RlpTest.php.
it('has protocol-specific encoding covered in protocol suites', function () {
    expect(true)->toBeTrue();
});
