<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('name', 120);
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'is_recurring']);
            $table->index(['is_recurring', 'date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('holidays');
    }
};
