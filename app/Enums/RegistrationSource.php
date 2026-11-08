<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case SelfRegistered = 'self';
    case Admin = 'admin';
}
