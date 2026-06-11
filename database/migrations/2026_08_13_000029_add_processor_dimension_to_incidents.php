<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000029_add_processor_dimension_to_incidents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolle beim Vorfall: eigener Vorfall (Verantwortlicher, Art. 33) vs. AV-Vorfall
 * (Auftragsverarbeiter, Art. 33 Abs. 2 – Verantwortlichen/Kunden informieren).
 * Plus Flag, dass die EIGENE Infrastruktur mitbetroffen ist (eigener Folge-Vorfall).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('privacy_incidents', function (Blueprint $table): void {
            $table->string('controller_role', 20)->default('controller')->after('status');
            $table->string('controller_name')->nullable()->after('controller_role'); // Kunde/Verantwortlicher bei AV-Vorfall
            $table->foreignId('controller_customer_id')->nullable()->after('controller_name')->constrained('customers')->nullOnDelete();
            $table->dateTime('controller_notified_at')->nullable()->after('subjects_notified_at');
            $table->boolean('own_infrastructure_affected')->default(false)->after('controller_notified_at');
        });
    }

    public function down(): void {
        Schema::table('privacy_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('controller_customer_id');
            $table->dropColumn(['controller_role', 'controller_name', 'controller_notified_at', 'own_infrastructure_affected']);
        });
    }
};
