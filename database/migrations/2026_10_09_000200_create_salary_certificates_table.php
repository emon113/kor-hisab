<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The uploaded file, on the private "local" disk.
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            // read: figures found, review pending · unread: reading failed or off · confirmed: reviewed, report ready
            $table->string('status', 20)->default('unread');
            $table->longText('ocr_text')->nullable();
            $table->string('ocr_error')->nullable();
            $table->json('extracted')->nullable();   // what the parser found, kept for reference
            $table->json('data')->nullable();        // the figures the user confirmed
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_certificates');
    }
};
