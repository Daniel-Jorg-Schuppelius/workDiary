<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_entry_id')->constrained('diary_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['diary_entry_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('comments');
    }
};
