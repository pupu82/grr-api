<?php

namespace App\Enums;

/**
 * Lifecycle of a queued report export.
 *
 * The values are stored in the database and exposed in the API. Never rename
 * one without a migration and a contract update.
 */
enum ExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
