<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_130000_create_ebics_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-124: EBICS-Bankzugang je Bankkonto. Schlüsselbund und Passphrase liegen
 * verschlüsselt (APP_KEY) — der Bund ist zusätzlich mit der Passphrase
 * geschützt. Das Journal hält jeden Schritt und jeden Auftrag fest.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ebics_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ebc_org_fk')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->unique('ebc_account_unique')->constrained('bank_accounts', indexName: 'ebc_account_fk')->cascadeOnDelete();
            $table->string('host_url', 255);
            $table->string('ebics_host', 35);
            $table->string('ebics_partner', 35);
            $table->string('ebics_user', 35);
            $table->string('status', 20)->default('draft');
            $table->longText('keyring')->nullable();
            $table->text('keyring_secret')->nullable();
            $table->timestamp('keys_created_at')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->date('statements_until')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ebc_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ebics_connection_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ebics_connection_id')->constrained('ebics_connections', indexName: 'ebce_conn_fk')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'ebce_actor_fk')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['ebics_connection_id', 'occurred_at'], 'ebce_chrono_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ebics_connection_events');
        Schema::dropIfExists('ebics_connections');
    }
};
