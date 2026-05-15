<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 120);
            $table->text('current_url')->nullable();
            $table->string('current_route')->nullable();
            $table->string('current_module')->nullable();
            $table->string('page_title')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('device_type', 40)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->string('status', 20)->default('online');
            $table->timestamps();

            $table->unique(['user_id', 'session_id']);
            $table->index(['last_activity_at', 'status']);
            $table->index('current_module');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('module')->nullable();
            $table->string('action', 80);
            $table->text('description')->nullable();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('route_name')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 20)->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index('ip_address');
        });

        Schema::create('carbon_footprint_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 120)->nullable();
            $table->string('module')->nullable();
            $table->string('route_name')->nullable();
            $table->text('url')->nullable();
            $table->string('page_title')->nullable();
            $table->decimal('estimated_data_kb', 12, 2)->default(0);
            $table->decimal('estimated_energy_kwh', 14, 8)->default(0);
            $table->decimal('estimated_co2e_grams', 14, 6)->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('device_type', 40)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_footprint_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_activity_sessions');
    }
};
