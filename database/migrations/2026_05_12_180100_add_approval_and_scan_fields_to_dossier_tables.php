<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_dossiers', function (Blueprint $table): void {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_remarks')->nullable()->after('approved_at');
        });

        Schema::table('dossier_documents', function (Blueprint $table): void {
            $table->string('scan_status')->default('pending')->after('checksum');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
            $table->text('scan_message')->nullable()->after('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('dossier_documents', function (Blueprint $table): void {
            $table->dropColumn(['scan_status', 'scanned_at', 'scan_message']);
        });

        Schema::table('student_dossiers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'approval_remarks']);
        });
    }
};
