<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 100);
            $table->string('action', 50);
            $table->string('subject_type', 50)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index(['created_at', 'id']);
            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $actions = "'user.created', 'user.approved', 'user.reactivated', 'user.deactivated', "
                ."'user.deleted', 'user.password_reset', 'session.created', 'session.updated', "
                ."'session.started', 'session.closed', 'session.deleted'";

            DB::statement("ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_action_check CHECK (action IN ({$actions}))");
            DB::statement("ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_subject_type_check CHECK (subject_type IN ('user', 'session'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
