<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110200_add_subtitle_review_to_media_renditions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maschinelle Untertitel (Feature 150).
 *
 * Whisper kann eine Untertitelspur erzeugen. **Als Barrierefreiheits-
 * nachweis zählt sie damit noch nicht** (WCAG 1.2.2): eine Maschine
 * verhört sich bei Fachbegriffen, Namen und Zahlen — und genau die tragen
 * in einer Unterweisung die Bedeutung. Deshalb trägt jede Spur, woher sie
 * kommt, und wer sie durchgesehen hat.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('media_renditions', function (Blueprint $table): void {
            // manual|machine
            $table->string('source', 8)->default('manual')->after('locale');
            $table->timestamp('reviewed_at')->nullable()->after('source');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users', indexName: 'media_rend_reviewer_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('media_renditions', function (Blueprint $table): void {
            $table->dropForeign('media_rend_reviewer_fk');
            $table->dropColumn(['source', 'reviewed_at', 'reviewed_by']);
        });
    }
};
