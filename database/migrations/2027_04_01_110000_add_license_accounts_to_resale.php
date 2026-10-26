<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_110000_add_license_accounts_to_resale.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/*
 * Lizenzkonto (Feature 185, MVP-1399/1401): Kundennummer beim Anbieter am Abo
 * und die Aufteilung der Lizenzen eines Kontos auf mehrere Halter je Produkt.
 * Bestand: Konto vorläufig aus dem Firmennamen, der nächste Import setzt die
 * echte Kundennummer.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('resale_subscriptions', 'provider_account')) {
                $table->string('provider_account', 64)->nullable()->after('external_order_id');
                $table->index(['organization_id', 'provider', 'provider_account'], 'resale_subs_org_account_idx');
            }
        });

        foreach (DB::table('resale_subscriptions')->whereNull('provider_account')->whereNotNull('company_name')->get(['id', 'company_name']) as $row) {
            $key = mb_strtolower(trim((string) $row->company_name));
            $key = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $key);
            $key = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/u', ' ', $key)));
            if ($key !== '') {
                DB::table('resale_subscriptions')->where('id', $row->id)->update(['provider_account' => mb_substr($key, 0, 64)]);
            }
        }

        if (! Schema::hasTable('resale_account_allocations')) {
            Schema::create('resale_account_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('provider_account', 64);
                $table->string('product_key', 120);
                $table->string('product_label', 190);
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('foreign_customer_id')->nullable()->constrained('foreign_customers')->nullOnDelete();
                $table->boolean('is_own_holding')->default(false);
                $table->unsignedInteger('quantity');
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->string('note', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['organization_id', 'provider', 'provider_account'], 'resale_alloc_org_account_idx');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('resale_account_allocations');
        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('resale_subs_org_account_idx');
            $table->dropColumn('provider_account');
        });
    }
};
