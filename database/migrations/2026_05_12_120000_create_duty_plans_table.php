<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('duty_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->date('from_date');
            $table->date('to_date');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedTinyInteger('min_staff')->default(0)->comment('Mindestbesetzung pro Schicht');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'from_date', 'to_date']);
            $table->index(['organization_id', 'status']);
        });

        // duty_plan_id als nullable FK auf scheduled_shifts
        Schema::table('scheduled_shifts', function (Blueprint $table): void {
            $table->foreignId('duty_plan_id')->nullable()->after('organization_id')
                ->constrained('duty_plans')->nullOnDelete();
            $table->index('duty_plan_id');
        });
    }

    public function down(): void {
        Schema::table('scheduled_shifts', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\DutyPlan::class);
            $table->dropColumn('duty_plan_id');
        });
        Schema::dropIfExists('duty_plans');
    }
};
