<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Inspector = 'inspector';
 
    public function canManageData(): bool
    {
        return $this === self::Admin;
    }
 
    public function canViewAllResults(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }
}
