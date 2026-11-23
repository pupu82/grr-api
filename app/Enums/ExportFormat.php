<?php

namespace App\Enums;

/**
 * File formats accepted by the session evaluation export endpoint.
 *
 * The values are stored in the database and exposed in the API. Never rename
 * one without a migration and a contract update.
 */
enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
}
