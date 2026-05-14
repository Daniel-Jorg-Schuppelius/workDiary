<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Qualifikationskatalog (org-spezifisch)
        Schema::create('qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('abbreviation', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // Qualifikationen pro Mitarbeiter
        Schema::create('user_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualification_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->comment('null = unbegrenzt gültig');
            $table->timestamps();

            $table->unique(['user_id', 'qualification_id']);
        });

        // Qualifikations-Anforderung an einem Schichttyp (optional)
        Schema::create('shift_type_qualifications', function (Blueprint $table): void {
            $table->foreignId('shift_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualification_id')->constrained()->cascadeOnDelete();
            $table->primary(['shift_type_id', 'qualification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_type_qualifications');
        Schema::dropIfExists('user_qualifications');
        Schema::dropIfExists('qualifications');
    }
};
