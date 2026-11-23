<?php

namespace App\Enums;

/**
 * Stable identifiers for the events kept in the activity log.
 *
 * The values are stored in the database and exposed to clients. Never rename
 * one without a migration and a contract update.
 */
enum ActivityAction: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserImported = 'user.imported';
    case UserApproved = 'user.approved';
    case UserReactivated = 'user.reactivated';
    case UserDeactivated = 'user.deactivated';
    case UserDeleted = 'user.deleted';
    case UserPasswordReset = 'user.password_reset';

    case SessionCreated = 'session.created';
    case SessionUpdated = 'session.updated';
    case SessionStarted = 'session.started';
    case SessionClosed = 'session.closed';
    case SessionDeleted = 'session.deleted';
}
