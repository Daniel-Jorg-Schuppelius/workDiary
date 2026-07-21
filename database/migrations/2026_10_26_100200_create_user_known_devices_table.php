<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100200_create_user_known_devices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bekannte Anmelde-Geräte je Nutzer (Feature 096, MVP-446): Fingerprint aus
 * UA-Familie + grobem Geo — Erstkontakt löst die „Neues Gerät"-Benachrichtigung
 * aus. Kein Tracking-Detail: Label + Land, kurze Lebensdauer inaktiver Einträge.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('user_known_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('label', 120);
            $table->string('country', 80)->nullable();
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'ukd_user_fp_uq');
            $table->index('last_seen_at', 'ukd_seen_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_known_devices');
    }
};
