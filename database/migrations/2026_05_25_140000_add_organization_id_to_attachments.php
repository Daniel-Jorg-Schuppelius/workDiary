<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_140000_add_organization_id_to_attachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Trennung für Anhänge: ohne organization_id konnte jeder
 * eingeloggte Benutzer einen Download für ein fremdes Attachment
 * über die signierte URL anfordern, sobald die ID bekannt war.
 *
 * Backfill leitet organization_id aus dem polymorphen Parent ab.
 * Anhänge, für deren Parent keine Organisation ableitbar ist (z. B.
 * Logo der globalen Organisation), bleiben null und werden vom
 * OrganizationScope nicht weiter gefiltert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index('organization_id', 'idx_attachments_org');
        });

        $this->backfillFromParents();
    }

    public function down(): void {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex('idx_attachments_org');
            $table->dropColumn('organization_id');
        });
    }

    /**
     * Ermittelt für jeden Attachable-Typ die zugehörige Tabelle und
     * setzt organization_id aus dem Parent. Tabellen ohne Spalte
     * organization_id (z. B. Logo an Organization selbst) werden
     * übersprungen.
     */
    private function backfillFromParents(): void {
        $types = DB::table('attachments')
            ->select('attachable_type')
            ->whereNotNull('attachable_type')
            ->distinct()
            ->pluck('attachable_type');

        foreach ($types as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $class;
            $parentTable = $model->getTable();

            if ($parentTable === 'organizations') {
                // Logo am Org-Record selbst: organization_id = parent_id
                DB::table('attachments')
                    ->where('attachable_type', $class)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => DB::raw('attachable_id')]);

                continue;
            }

            if (! Schema::hasColumn($parentTable, 'organization_id')) {
                continue;
            }

            // Portabler Backfill (MySQL + SQLite + Postgres): pro Parent
            // mit gesetzter organization_id alle eigenen Attachments
            // patchen. Etwas mehr Statements, dafür kein Dialekt-JOIN.
            DB::table($parentTable)
                ->select(['id', 'organization_id'])
                ->whereNotNull('organization_id')
                ->orderBy('id')
                ->chunk(500, function ($parents) use ($class): void {
                    foreach ($parents as $parent) {
                        DB::table('attachments')
                            ->where('attachable_type', $class)
                            ->where('attachable_id', $parent->id)
                            ->whereNull('organization_id')
                            ->update(['organization_id' => $parent->organization_id]);
                    }
                });
        }
    }
};
