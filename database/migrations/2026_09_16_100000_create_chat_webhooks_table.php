<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_16_100000_create_chat_webhooks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgehender Team-Messenger-Kanal je Organisation (Feature 056, MVP-119):
 * Microsoft Teams bzw. Mattermost/Rocket.Chat Incoming Webhook. Die Kanal-URL
 * enthält das Geheimnis und ist daher komplett verschlüsselt at-rest
 * (`encrypted`-Cast, APP_KEY!). Auto-Deaktivierung nach wiederholten Fehlern
 * (analog `webhook_endpoints`).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('chat_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'chatwh_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 16); // teams|mattermost
            $table->text('webhook_url'); // encrypted at-rest (enthält das Geheimnis)
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'chatwh_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'chatwh_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_webhooks');
    }
};
