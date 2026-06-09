<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000012_add_hash_chain_to_org_audit_and_chain_heads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\OrganizationAuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Erweitert die Hash-Kette auf organization_audit_logs und führt die
 * Kettenkopf-Tabelle audit_chain_heads ein. Der Kopf (head_hash/height) wird
 * beim Insert per lockForUpdate gesperrt → serialisiert nebenläufige Inserts,
 * sodass keine zwei Zeilen denselben prev_hash erhalten ({@see App\Models\Concerns\HashChained}).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('organization_audit_logs', function (Blueprint $table): void {
            $table->string('prev_hash', 64)->nullable()->after('export_hash');
            $table->string('hash', 64)->nullable()->after('prev_hash');
            $table->index('hash');
        });

        // Org-Audit-Bestand rückwirkend verketten – über das Modell (gleiche
        // Normalisierung wie der Live-Pfad), per DB::table unter Umgehung des
        // append-only Guards (einmaliger Backfill).
        $prevHash = null;
        DB::table('organization_audit_logs')->orderBy('id')->each(function ($row) use (&$prevHash): void {
            /** @var OrganizationAuditLog $model */
            $model = (new OrganizationAuditLog)->newFromBuilder((array) $row);
            $hash = OrganizationAuditLog::chainHash($prevHash, $model->hashPayload());
            DB::table('organization_audit_logs')->where('id', $row->id)->update([
                'prev_hash' => $prevHash,
                'hash' => $hash,
            ]);
            $prevHash = $hash;
        });

        // Kettenkopf je Kette: head_hash + height (Zeilenzahl).
        Schema::create('audit_chain_heads', function (Blueprint $table): void {
            $table->string('chain', 64)->primary();
            $table->string('head_hash', 64)->nullable();
            $table->unsignedBigInteger('height')->default(0);
        });

        foreach (['audit_logs', 'organization_audit_logs'] as $chain) {
            $tail = DB::table($chain)->orderByDesc('id')->first();
            DB::table('audit_chain_heads')->insert([
                'chain' => $chain,
                'head_hash' => $tail->hash ?? null,
                'height' => DB::table($chain)->count(),
            ]);
        }
    }

    public function down(): void {
        Schema::dropIfExists('audit_chain_heads');

        Schema::table('organization_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['hash']);
            $table->dropColumn(['prev_hash', 'hash']);
        });
    }
};
