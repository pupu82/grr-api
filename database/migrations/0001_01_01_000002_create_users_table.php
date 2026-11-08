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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('employee_code', 30);
            $table->string('name', 100);
            $table->string('password');
            $table->string('role', 20);
            $table->string('status', 20)->default('pending');
            $table->string('registered_via', 10);
            $table->boolean('must_change_password')->default(false);
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // $table->string('active_employee_code', 30)
            //     ->nullable()
            //     ->storedAs('CASE WHEN deleted_at IS NULL THEN employee_code END')
            //     ->unique('users_active_employee_code_unique');

            $table->index('team_id');
            $table->index(['role', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX users_employee_code_unique ON users (employee_code) WHERE deleted_at IS NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
