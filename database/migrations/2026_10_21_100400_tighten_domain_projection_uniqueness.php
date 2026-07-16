<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_21_100400_tighten_domain_projection_uniqueness.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verschärft die Domain-Eindeutigkeit (Feature 083): statt „eindeutig je
 * Verbindung" gilt jetzt „eindeutig je Organisation je Domainname". Damit
 * gehört eine Domain innerhalb einer Organisation genau EINER Projektionszeile
 * — und über die einzelne `customer_id`-Spalte genau einem Kunden — auch über
 * mehrere Providerverbindungen hinweg. Verhindert Doppelzuordnung, wenn dieselbe
 * Domain (z. B. nach einem Push) in zwei Konten derselben Organisation auftaucht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->dropUnique('dp_org_conn_domainhash_uq');
            $table->unique(['organization_id', 'domain_hash'], 'dp_org_domainhash_uq');
        });
    }

    public function down(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->dropUnique('dp_org_domainhash_uq');
            $table->unique(['organization_id', 'connection_id', 'domain_hash'], 'dp_org_conn_domainhash_uq');
        });
    }
};
