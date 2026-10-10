<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_110300_create_incoming_einvoice_transfers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Übergabejournal des Rechnungseingangs (Feature 163, MVP-1111): eine Zeile je
 * Eingang und Buchhaltungsziel. Die bisherigen Eingangs-Übertragungen von DATEV
 * Online ziehen hierher um; deren Tabelle behält nur noch Ausgangsbelege und
 * Buchungsstapel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('incoming_einvoice_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_einvoice_id')->constrained('incoming_einvoices')->cascadeOnDelete();
            $table->string('target', 40);
            $table->string('status', 16)->default('pending');
            $table->string('external_id', 100)->nullable();
            $table->string('external_number', 100)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->unique(['incoming_einvoice_id', 'target'], 'incoming_einv_transfer_target_unique');
            $table->index(['organization_id', 'status'], 'incoming_einv_transfer_org_status_idx');
        });

        if (! Schema::hasTable('datev_online_transfers')) {
            return;
        }
        $alias = 'incoming_einvoices';
        DB::table('datev_online_transfers')->where('kind', 'incoming_document')->where('source_type', $alias)->orderBy('id')
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    if (! DB::table('incoming_einvoices')->where('id', $row->source_id)->exists()) {
                        continue;
                    }
                    DB::table('incoming_einvoice_transfers')->insertOrIgnore([
                        'organization_id' => $row->organization_id,
                        'incoming_einvoice_id' => $row->source_id,
                        'target' => 'datev-online',
                        'status' => in_array($row->status, ['transferred', 'succeeded'], true) ? 'transferred' : ($row->status === 'failed' ? 'failed' : 'pending'),
                        'external_id' => $row->datev_reference,
                        'attempts' => $row->attempts,
                        'error' => $row->error,
                        'transferred_at' => $row->transferred_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
        DB::table('datev_online_transfers')->where('kind', 'incoming_document')->delete();
    }

    public function down(): void {
        Schema::dropIfExists('incoming_einvoice_transfers');
    }
};
