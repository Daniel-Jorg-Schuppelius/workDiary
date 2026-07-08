<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100300_create_attachment_confirmations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto-Bestätigung durch den Kunden im Portal (Feature 012, Rang 55):
 * einmalige Bestätigung je Anhang und Portal-Benutzer; Beanstandungen laufen
 * über den bestehenden CustomerQuery-Flow.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('attachment_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['attachment_id', 'user_id'], 'attconf_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('attachment_confirmations');
    }
};
