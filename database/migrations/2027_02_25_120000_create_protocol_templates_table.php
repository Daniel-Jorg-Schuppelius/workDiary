<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_120000_create_protocol_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-901: Protokollvorlagen mit Zuordnung und Version; das Protokoll merkt sich seine Vorlage. */
return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('kind', 32);
            $table->text('description')->nullable();
            $table->json('items');
            $table->foreignId('entry_type_id')->nullable()->constrained('entry_types')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'protocol_templates_org_active_idx');
        });

        Schema::table('protocols', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('type')->constrained('protocol_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable()->after('template_id');
        });
    }

    public function down(): void {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('template_version');
        });
        Schema::dropIfExists('protocol_templates');
    }
};
