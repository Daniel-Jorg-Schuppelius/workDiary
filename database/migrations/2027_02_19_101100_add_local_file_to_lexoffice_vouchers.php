<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buchhaltungswechsel-Folgeschnitte (Feature 110/126ff, MVP-690 — Vollscan
 * G3): Lexoffice-Belegbilder werden lokal materialisiert, damit sie nach
 * Vertragsende verfügbar bleiben (GoBD). `file_materialized_at` gesetzt +
 * `file_path` NULL bedeutet „geprüft, Beleg hat kein Belegbild".
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->string('file_path', 255)->nullable()->after('payload');
            $table->timestamp('file_materialized_at')->nullable()->after('file_path');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->dropColumn(['file_path', 'file_materialized_at']);
        });
    }
};
