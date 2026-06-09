<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000010_add_hash_chain_to_audit_logs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Macht audit_logs revisionssicher (GoBD): SHA-256-Hash-Kette über die Zeilen.
 * Bestandsdaten werden in id-Reihenfolge rückwirkend verkettet, damit die
 * gesamte vorhandene Historie ab sofort per `audit:verify` prüfbar ist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('prev_hash', 64)->nullable()->after('user_agent');
            $table->string('hash', 64)->nullable()->after('prev_hash');
            $table->index('hash');
        });

        // Bestandsdaten rückwirkend verketten – über denselben Hash-Algorithmus
        // wie der Live-Pfad ({@see AuditLog::chainHash}). DB::table umgeht den
        // append-only Guard des Modells (einmaliger Backfill).
        $prevHash = null;
        DB::table('audit_logs')->orderBy('id')->each(function ($row) use (&$prevHash): void {
            // Über das Modell (Hydration) verketten – gleiche Normalisierung
            // wie Live-/Verify-Pfad (z. B. int-Casts), damit die Kette über
            // alle DB-Treiber konsistent bleibt.
            /** @var AuditLog $model */
            $model = (new AuditLog)->newFromBuilder((array) $row);
            $hash = AuditLog::chainHash($prevHash, $model->hashPayload());
            DB::table('audit_logs')->where('id', $row->id)->update([
                'prev_hash' => $prevHash,
                'hash' => $hash,
            ]);
            $prevHash = $hash;
        });
    }

    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['hash']);
            $table->dropColumn(['prev_hash', 'hash']);
        });
    }
};
