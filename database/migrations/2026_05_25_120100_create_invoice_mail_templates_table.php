<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_120100_create_invoice_mail_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vom Admin editierbare Mail-Templates für den Rechnungsversand.
 *
 * - organization_id nullable = globales Default-Template.
 * - Pro Organisation kann genau ein Template als is_default markiert sein
 *   (DB-seitig via Composite-Unique optional; hier per Application-Logik).
 * - body_html + body_text: einfacher {{var}}-Placeholder-Renderer
 *   (kein Blade in DB! XSS-sicher per htmlspecialchars beim Render).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->text('body_text');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_mail_templates');
    }
};
