<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_120000_create_isms_norm_statuses_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konformitätsstatus je Normprofil und Geltungsbereich (Feature 046,
 * Inkrement B): trägt pro Org + Scope + Norm/Ausgabe GENAU EINEN Status
 * der 046-Statuskette (notAssessed … certificateExpired). Die strikte
 * Regel — `certified` NUR mit hinterlegtem, heute gültigem Zertifikat —
 * erzwingt der ConformityService; Zertifikate hängen als
 * isms_certificates an dieser Zeile.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_norm_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->constrained('isms_scopes')->cascadeOnDelete();
            $table->string('norm', 64);
            $table->string('edition', 16);
            $table->string('status', 32)->default('notAssessed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'isms_scope_id', 'norm', 'edition'], 'isms_nstat_org_scope_norm_uq');
            $table->index(['organization_id', 'status'], 'isms_nstat_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_norm_statuses');
    }
};
