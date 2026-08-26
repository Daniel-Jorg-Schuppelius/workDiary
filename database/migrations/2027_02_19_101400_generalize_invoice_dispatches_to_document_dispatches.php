<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101400_generalize_invoice_dispatches_to_document_dispatches.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Generischer Belegversand (Feature 128, MVP-692): Das Zustellnachweis-Log
 * der Rechnung wird zum Log ALLER versendeten Belege (Angebot, AB,
 * Bestellung, Lieferschein). Adressierung über document_kind + document_id
 * (Werte aus RenderDocumentKind); invoice_id bleibt als FK der
 * Rechnungszeilen erhalten (Cascade/bestehende Abfragen), wird für andere
 * Belegarten NULL. Backfill: Bestand = 'invoice', Mahnversände (meta.kind)
 * = 'dunning'.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_dispatches', function (Blueprint $table): void {
            $table->string('document_kind', 40)->nullable()->after('invoice_id');
            $table->unsignedBigInteger('document_id')->nullable()->after('document_kind');
            $table->index(['document_kind', 'document_id', 'created_at'], 'invd_document_idx');
        });

        DB::table('invoice_dispatches')->update([
            'document_kind' => 'invoice',
            'document_id' => DB::raw('invoice_id'),
        ]);
        // Mahnversände tragen ihre Art bislang nur in meta.kind (MVP-691).
        DB::table('invoice_dispatches')
            ->where('meta', 'like', '%"kind":"dunning"%')
            ->update(['document_kind' => 'dunning']);

        Schema::table('invoice_dispatches', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->change();
        });

        Schema::rename('invoice_dispatches', 'document_dispatches');
    }

    public function down(): void {
        Schema::rename('document_dispatches', 'invoice_dispatches');

        DB::table('invoice_dispatches')->whereNull('invoice_id')->delete();

        Schema::table('invoice_dispatches', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->dropIndex('invd_document_idx');
            $table->dropColumn(['document_kind', 'document_id']);
        });
    }
};
