<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ReportExport;
use App\Models\User;

class ReportExportPolicy
{
    /**
     * A queued evaluation file is a report: admins and managers only. Inspectors
     * keep access to their own stored results, never the comparative export.
     */
    public function view(User $actor, ReportExport $export): bool
    {
        return in_array($actor->role, [Role::Admin, Role::Manager], strict: true);
    }
}
