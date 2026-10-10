<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_110000_add_mailbox_intake_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Rechnungspostfach (Feature 163, MVP-1107): Richtung und Belegart, Käufer,
 * Erkennungsart (strukturiert, erkannt, Klärfall) und Mail-Herkunft am Eingang.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('direction', 16)->default('incoming')->after('status');
            $table->string('kind', 32)->default('invoice')->after('direction');
            $table->string('recognition', 16)->default('structured')->after('kind');
            $table->string('buyer_name', 191)->nullable()->after('seller_vat_id');
            $table->string('buyer_vat_id', 32)->nullable()->after('buyer_name');
            $table->string('sender_email', 191)->nullable()->after('source');
            $table->string('source_reference', 191)->nullable()->after('sender_email');

            $table->index(['organization_id', 'direction', 'status'], 'incoming_einv_org_dir_status_idx');
            $table->index(['organization_id', 'source_reference'], 'incoming_einv_org_source_ref_idx');
        });

        DB::table('incoming_einvoices')->select(['id', 'summary'])->orderBy('id')->chunkById(500, static function ($rows): void {
            foreach ($rows as $row) {
                $summary = json_decode((string) $row->summary, true);
                if (is_array($summary) && ($summary['unstructured'] ?? false)) {
                    DB::table('incoming_einvoices')->where('id', $row->id)->update(['recognition' => 'extracted']);
                }
            }
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropIndex('incoming_einv_org_dir_status_idx');
            $table->dropIndex('incoming_einv_org_source_ref_idx');
            $table->dropColumn(['direction', 'kind', 'recognition', 'buyer_name', 'buyer_vat_id', 'sender_email', 'source_reference']);
        });
    }
};
