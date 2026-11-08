<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
 
    public function acceptsAnswers(): bool
    {
        return $this === self::InProgress;
    }
}