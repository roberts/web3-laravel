<?php

namespace Roberts\Web3Laravel\Enums;

enum TokenType: string
{
    case ERC20 = 'erc20';
    case ERC721 = 'erc721';
    case ERC1155 = 'erc1155';
}
