<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090000_create_isms_scopes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managementsystem-Geltungsbereiche (Feature 046, gemeinsamer Kern):
 * mehrere Scopes je Organisation; pro Org wird bei Bedarf ein Default-Scope
 * („Gesamtorganisation", is_default = true) automatisch angelegt
 * (ScopeService::ensureDefaultScope).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_default'], 'isms_scopes_org_default_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_scopes');
    }
};
