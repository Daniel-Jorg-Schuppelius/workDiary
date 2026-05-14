<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flex_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->integer('target_minutes')->default(0);
            $table->integer('actual_minutes')->default(0);
            $table->integer('balance_minutes')->default(0);
            $table->integer('carry_over_minutes')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flex_balances');
    }
};
