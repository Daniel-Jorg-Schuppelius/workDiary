<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100800_create_support_access_grants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporäre Supportfreigabe (Rang 64, Soll-Konzept §5.2 der
 * Supportzugriff-Grundsätze): Der Kundenadmin gibt den Zugriff für den
 * Plattform-Support zeitlich begrenzt frei; Impersonation ist nur bei
 * aktiver Freigabe zulässig.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'sag_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')
                ->constrained('users', indexName: 'sag_granted_by_fk')
                ->cascadeOnDelete();
            // Optional auf einen konkreten Support-Account eingeschränkt.
            $table->foreignId('granted_to_user_id')->nullable()
                ->constrained('users', indexName: 'sag_granted_to_fk')
                ->nullOnDelete();
            $table->string('scope', 20)->default('read_only'); // read_only|full
            $table->string('purpose', 300);
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoked_reason', 100)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'expires_at'], 'sag_org_expires_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('support_access_grants');
    }
};
