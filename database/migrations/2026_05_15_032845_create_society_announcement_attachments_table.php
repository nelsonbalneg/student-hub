<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_announcement_id')->constrained('society_announcements', 'id', 'soc_ann_idx');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_announcement_attachments');
    }
};