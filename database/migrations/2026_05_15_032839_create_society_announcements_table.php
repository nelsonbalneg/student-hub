<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->string('title');
            $table->longText('body');
            $table->string('category')->nullable();
            $table->string('audience')->default('public');
            $table->timestamp('publish_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_announcements');
    }
};