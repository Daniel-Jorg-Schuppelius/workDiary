<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('holidays', function (Blueprint $table): void {
            // date wird für relative Feiertage NULL (NULL != NULL → unique constraint bleibt)
            $table->date('date')->nullable()->change();

            $table->string('recurrence_type', 10)->default('fixed')->after('is_recurring');
            // 0=So, 1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr, 6=Sa (Carbon-Konstanten)
            $table->tinyInteger('recurrence_weekday')->nullable()->after('recurrence_type');
            // 1–4 = Nth, -1 = letzter
            $table->tinyInteger('recurrence_week')->nullable()->after('recurrence_weekday');
            // 1–12 oder NULL = jeden Monat
            $table->tinyInteger('recurrence_month')->nullable()->after('recurrence_week');
        });
    }

    public function down(): void {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropColumn(['recurrence_type', 'recurrence_weekday', 'recurrence_week', 'recurrence_month']);
            $table->date('date')->nullable(false)->change();
        });
    }
};
