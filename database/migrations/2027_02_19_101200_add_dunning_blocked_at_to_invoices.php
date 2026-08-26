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
 * Mahnlauf (Feature 127, MVP-691 — Vollscan H8): Mahnsperre je Rechnung.
 * Gesetzt = keine Mahnung (weder Einzeldialog noch Sammelmahnung); der
 * Zeitstempel dokumentiert, seit wann — das Warum steht im Audit-Log.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('dunning_blocked_at')->nullable()->after('dunned_at');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('dunning_blocked_at');
        });
    }
};
