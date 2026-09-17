<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100300_create_user_terminal_pins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminal-PIN (MVP-803, Feature 061): Ersatz für einen vergessenen Ausweis.
 * Gestempelt wird mit Personalnummer + PIN; gespeichert ist nur der Hash.
 * Nach mehreren Fehlversuchen ist die PIN für eine Weile gesperrt — eine
 * vierstellige PIN ist sonst in Minuten durchprobiert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('user_terminal_pins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'utpin_org_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'utpin_user_fk')->cascadeOnDelete();
            $table->string('pin_hash');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users', indexName: 'utpin_set_by_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique('user_id', 'utpin_user_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_terminal_pins');
    }
};
