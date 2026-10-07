<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wealth_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tax_year', 16);
            // Only used when there is no statement for the year before.
            $table->bigInteger('opening_net_wealth')->nullable();
            $table->json('receipts');
            $table->json('expenses');
            $table->json('liabilities');
            $table->json('assets');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_statements');
    }
};
