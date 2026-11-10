<?php

namespace App\Enums;

/**
 * How one inspector finished one session sample.
 *
 * Trials are simulations outside the application and are never stored. The
 * real inspection stores exactly one outcome per inspector per sample: either
 * a verdict (`answered`) or the fact that the response time ran out
 * (`timed_out`, no verdict).
 */
enum AnswerOutcome: string
{
    case Answered = 'answered';
    case TimedOut = 'timed_out';

    public function hasVerdict(): bool
    {
        return $this === self::Answered;
    }
}
