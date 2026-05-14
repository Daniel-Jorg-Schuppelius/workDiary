<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duty_plan_id')->nullable()->constrained('duty_plans')->cascadeOnDelete()
                ->comment('NULL = organisationsweite Default-Anforderung');
            $table->foreignId('shift_type_id')->constrained('shift_types')->cascadeOnDelete();

            // Granularität: entweder weekday (0=So..6=Sa) ODER specific_date (überschreibt weekday)
            $table->unsignedTinyInteger('weekday')->nullable()
                ->comment('0=So, 1=Mo, …, 6=Sa. NULL bei specific_date oder Plan-übergreifend');
            $table->date('specific_date')->nullable()
                ->comment('Überschreibt weekday-basierte Anforderung an diesem Tag');

            $table->unsignedSmallInteger('min_staff')->default(1);
            $table->unsignedSmallInteger('max_staff')->nullable();

            $table->json('required_qualification_ids')->nullable()
                ->comment('Liste qualification_id, alle müssen erfüllt sein');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'duty_plan_id']);
            $table->index(['duty_plan_id', 'weekday']);
            $table->index(['duty_plan_id', 'specific_date']);
            $table->index('shift_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_requirements');
    }
};
