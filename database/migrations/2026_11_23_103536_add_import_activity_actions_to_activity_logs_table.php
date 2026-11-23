<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE activity_logs DROP CONSTRAINT activity_logs_action_check');

        $actions = "'user.created', 'user.updated', 'user.imported', 'user.approved', "
            ."'user.reactivated', 'user.deactivated', 'user.deleted', 'user.password_reset', "
            ."'session.created', 'session.updated', 'session.started', 'session.closed', "
            ."'session.deleted'";

        DB::statement("ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_action_check CHECK (action IN ({$actions}))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE activity_logs DROP CONSTRAINT activity_logs_action_check');

        $actions = "'user.created', 'user.approved', 'user.reactivated', 'user.deactivated', "
            ."'user.deleted', 'user.password_reset', 'session.created', 'session.updated', "
            ."'session.started', 'session.closed', 'session.deleted'";

        DB::statement("ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_action_check CHECK (action IN ({$actions}))");
    }
};
