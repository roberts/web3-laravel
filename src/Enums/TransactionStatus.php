<?php

namespace Roberts\Web3Laravel\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Prepared = 'prepared';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}
