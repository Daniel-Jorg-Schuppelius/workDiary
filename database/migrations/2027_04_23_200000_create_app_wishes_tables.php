<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_23_200000_create_app_wishes_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659/1677).
 *
 * `app_wishes`: Wunsch, Fehler oder Frage einer Organisation mit der Seite
 * (Routenname), Status und Antwort der Organisation (`status`, `answer`).
 * Weitergabe an den Plattformbetrieb (MVP-1659) ohne Personenbezug: der
 * bereinigte Text steht in `forwarded_body`; Status, Antwort und Bündel des
 * Betriebs in `platform_*`. Herstellerkanal (MVP-1677): was an den Hersteller
 * ging (`vendor_body`, `vendor_sent_at`) und seine Antwort (`vendor_answer`).
 *
 * `app_wish_votes`: eine Stimme je Person und Wunsch; gezählt wird je Wunsch,
 * wer wie oft abstimmt, wird nicht ausgewertet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('app_wishes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->text('body');
            $table->string('route_name', 150)->nullable();
            $table->string('status', 16)->default('new');
            $table->text('answer')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('app_version', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Weitergabe an den Plattformbetrieb (MVP-1659).
            $table->dateTime('forwarded_at')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('forwarded_body')->nullable();
            $table->string('platform_status', 16)->nullable();
            $table->text('platform_answer')->nullable();
            $table->dateTime('platform_answered_at')->nullable();
            $table->foreignId('platform_bundle_id')->nullable()->constrained('app_wishes')->nullOnDelete();
            // Herstellerkanal (MVP-1677).
            $table->text('vendor_body')->nullable();
            $table->dateTime('vendor_sent_at')->nullable();
            $table->foreignId('vendor_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('vendor_answer')->nullable();
            $table->dateTime('vendor_answered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at'], 'app_wishes_org_status_idx');
            $table->index(['created_by', 'created_at'], 'app_wishes_author_idx');
            $table->index(['forwarded_at'], 'app_wishes_forwarded_idx');
        });

        Schema::create('app_wish_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('app_wish_id')->constrained('app_wishes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['app_wish_id', 'user_id'], 'app_wish_votes_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('app_wish_votes');
        Schema::dropIfExists('app_wishes');
    }
};
