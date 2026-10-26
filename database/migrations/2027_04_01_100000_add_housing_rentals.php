<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_100000_add_housing_rentals.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Branchenprofil Vermieter: Wohnung = Mietprofil mit Vermietungsart, Mietverhältnisse der Dauervermietung, Gäste der Ferienanfrage.
return new class extends Migration {
    public function up(): void {
        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->string('rental_mode', 20)->nullable()->index();
            $table->text('public_description')->nullable();
            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->unsignedSmallInteger('minimum_nights')->default(1);
            $table->string('check_in_time', 5)->default('15:00');
            $table->string('check_out_time', 5)->default('10:00');
            $table->decimal('rent_amount', 12, 2)->nullable();
            $table->decimal('ancillary_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
        });
        Schema::create('rental_tenancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('requested');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('rent_amount', 12, 2)->nullable();
            $table->decimal('ancillary_amount', 12, 2)->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'asset_id', 'status']);
        });
        Schema::table('rental_requests', fn (Blueprint $table) => $table->unsignedSmallInteger('guests')->nullable());
    }

    public function down(): void {
        Schema::dropIfExists('rental_tenancies');
        Schema::table('rental_requests', fn (Blueprint $table) => $table->dropColumn('guests'));
        Schema::table('rental_profiles', fn (Blueprint $table) => $table->dropIndex(['rental_mode']));
        Schema::table('rental_profiles', fn (Blueprint $table) => $table->dropColumn([
            'rental_mode', 'public_description', 'max_guests', 'minimum_nights',
            'check_in_time', 'check_out_time', 'rent_amount', 'ancillary_amount', 'currency',
        ]));
    }
};
