<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_150000_create_organization_audit_logs_and_deactivated_at.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Zeitpunkt der Deaktivierung – benötigt für den Cooldown vor dem
        // endgültigen Löschen (Purge) einer Organisation.
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('is_active');
            }
        });

        // Audit-Trail für Lebenszyklus-Ereignisse einer Organisation
        // (Deaktivieren, Reaktivieren, Export, Purge). Bewusst KEIN FK auf
        // organizations: die Zeilen müssen Bestand haben, wenn die
        // Organisation selbst gelöscht wurde.
        Schema::create('organization_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_slug', 255)->nullable();
            $table->string('organization_name', 255)->nullable();
            $table->string('action', 32); // deactivate|reactivate|export|purge
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->json('payload')->nullable();
            $table->string('export_hash', 128)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('organization_audit_logs');

        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'deactivated_at')) {
                $table->dropColumn('deactivated_at');
            }
        });
    }
};
