<?php

use Roberts\Web3Laravel\Support\Rlp;

it('encodes small integers and strings correctly', function () {
    expect(bin2hex(Rlp::encodeInt(0)))->toBe('80');
    expect(bin2hex(Rlp::encodeInt(15)))->toBe('0f'); // single byte < 0x80
    expect(bin2hex(Rlp::encodeString("\x7f")))->toBe('7f');
    expect(bin2hex(Rlp::encodeString("\x80")))->toBe('8180');
    expect(bin2hex(Rlp::encodeString('dog')))->toBe('83646f67');
});

it('encodes lists correctly', function () {
    $cat = Rlp::encodeString('cat');
    $dog = Rlp::encodeString('dog');
    $list = Rlp::encodeList([$cat, $dog]);
    expect(bin2hex($list))->toBe('c88363617483646f67');
});
