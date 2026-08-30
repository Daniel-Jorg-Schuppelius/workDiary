<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105100_create_learning_access_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugang für Lernende ohne Benutzerkonto (Feature 149, MVP-742).
 *
 * Anwendungsfall: die Sicherheitsunterweisung, die ein Subunternehmer vor
 * dem ersten Baustellentag absolviert. Ein Konto anzulegen wäre für einen
 * einmaligen Vorgang unverhältnismäßig — der Nachweis muss trotzdem
 * derselbe sein.
 *
 * Der Token wird **nur gehasht** gespeichert (Muster der Portal-Einladung):
 * wer die Datenbank liest, kann sich damit keinen Zugang verschaffen. Der
 * Klartext existiert genau einmal, beim Ausstellen.
 *
 * Kein SoftDelete — ein Zugang wird widerrufen, nicht gelöscht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedSmallInteger('use_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('token_hash', 'lrn_access_token_uq');
            $table->index(['organization_id', 'expires_at'], 'lrn_access_org_exp_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_access_tokens');
    }
};
