<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->timestamps(); // creates created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
