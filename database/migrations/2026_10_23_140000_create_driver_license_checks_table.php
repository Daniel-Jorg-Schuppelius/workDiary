<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_23_140000_create_driver_license_checks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MVP-417: Führerscheinkontrolle — dokumentierte Sichtprüfung je Fahrer (Halterhaftung).
return new class extends Migration {
    public function up(): void {
        Schema::create('driver_license_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_at');
            // Führerscheinklassen als Anzeige-String (z. B. "B, BE"); bewusst kein Foto (Datensparsamkeit).
            $table->string('license_classes', 60)->nullable();
            $table->date('license_valid_until')->nullable();
            $table->date('next_due_on');
            // Freitext kann PII enthalten → verschlüsselt (nie "" — NULL!).
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'checked_at'], 'dl_checks_org_user_checked_idx');
            $table->index(['organization_id', 'next_due_on'], 'dl_checks_org_due_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('driver_license_checks');
    }
};
