<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_11_100200_create_customer_intake_upload_links_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1078: Upload-Link eines Kundeneingangs bei einem externen Kanal
 * (Nextcloud-Dateiablage). URL und Passwort verschlüsselt; `processed_keys`
 * merkt übernommene und abgelehnte Dateien (Anbieter-ID + ETag).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_intake_upload_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ciul_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_intake_id')->constrained('customer_intakes', indexName: 'ciul_intake_fk')->cascadeOnDelete();
            $table->string('channel', 40);
            $table->string('external_id', 100)->nullable();
            $table->string('folder', 500);
            $table->text('url');
            $table->text('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->json('processed_keys')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ciul_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'revoked_at'], 'ciul_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_intake_upload_links');
    }
};
