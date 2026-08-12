<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100100_add_validity_and_status_to_terminal_credentials.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-516 (Feature 103): Badge-Gültigkeitszeiträume, opt-in Status-Antwort
 * am Terminal (Standard AUS — Anzeige ist für Umstehende sichtbar) und
 * Pufferstand-Meldung für die Diagnose.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('user_badges', function (Blueprint $table): void {
            $table->date('valid_from')->nullable()->after('badge_hash');
            $table->date('valid_until')->nullable()->after('valid_from');
        });
        Schema::table('attendance_terminals', function (Blueprint $table): void {
            $table->boolean('show_status')->default(false)->after('active');
            $table->unsignedInteger('last_buffer_size')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void {
        Schema::table('user_badges', function (Blueprint $table): void {
            $table->dropColumn(['valid_from', 'valid_until']);
        });
        Schema::table('attendance_terminals', function (Blueprint $table): void {
            $table->dropColumn(['show_status', 'last_buffer_size']);
        });
    }
};
