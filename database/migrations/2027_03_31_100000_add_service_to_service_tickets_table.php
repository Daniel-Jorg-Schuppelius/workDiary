<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_100000_add_service_to_service_tickets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betroffener Service und Serviceangebot am Ticket (Feature 065, MVP-1257):
 * Grundlage für Filter, Bericht, Routing und Auswirkungsanalyse.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->foreignId('business_service_id')->nullable()->after('sla_contract_id')
                ->constrained('business_services', indexName: 'st_business_service_fk')->nullOnDelete();
            $table->foreignId('service_offering_id')->nullable()->after('business_service_id')
                ->constrained('service_offerings', indexName: 'st_service_offering_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropForeign('st_service_offering_fk');
            $table->dropForeign('st_business_service_fk');
            $table->dropColumn(['service_offering_id', 'business_service_id']);
        });
    }
};
