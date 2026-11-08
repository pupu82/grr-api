<?php

namespace App\Enums;

enum Judgement: string
{
    case Acceptable = 'acceptable';
    case Marginal = 'marginal';
    case Unacceptable = 'unacceptable';
}
