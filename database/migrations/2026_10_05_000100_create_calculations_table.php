<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('notes')->nullable();
            $table->string('tax_year', 16);
            $table->string('category', 32);
            $table->unsignedBigInteger('gross_income');
            $table->unsignedBigInteger('liability');
            $table->bigInteger('payable');
            $table->decimal('effective_rate', 8, 6);
            $table->json('inputs');
            $table->json('summary');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
