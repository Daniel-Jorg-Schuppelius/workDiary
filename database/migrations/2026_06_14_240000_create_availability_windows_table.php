<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_240000_create_availability_windows_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verfügbarkeiten/Wunschdienste (Feature 007): wiederkehrend (weekday) ODER
 * datumsbezogen (specific_date). `kind` unterscheidet verfügbar / nicht
 * verfügbar / bevorzugt. Optionaler Gültigkeitszeitraum (valid_from/until)
 * für wiederkehrende Fenster.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('availability_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable()
                ->comment('0=So, 1=Mo, …, 6=Sa. NULL bei specific_date');
            $table->date('specific_date')->nullable()
                ->comment('Datumsbezogenes Fenster; überschreibt weekday');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('kind', 16)->default('available')
                ->comment('available | unavailable | preferred');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id'], 'avail_win_org_user_idx');
            $table->index(['user_id', 'weekday'], 'avail_win_user_weekday_idx');
            $table->index(['user_id', 'specific_date'], 'avail_win_user_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('availability_windows');
    }
};
