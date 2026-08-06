<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_02_100000_add_msgraph_webhooks_and_todo_delta.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 102, Folgeausbau (Webhooks + To-Do-Delta): Graph-Change-Notification-
 * Subscriptions für Zwei-Wege-Kalender (msgraph_connections), To-Do-Listen
 * (msgraph_task_list_links) und Graph-Postfächer (email_connections,
 * transport=msgraph) — je Träger `subscription_id`/`subscription_expires_at`/
 * `webhook_secret` (clientState, encrypted-Cast) nach dem Muster des
 * Dokumenteingangs (cloud_document_connections, MVP-354). Zusätzlich der
 * To-Do-Delta-Checkpoint `delta_link` (absolute Graph-URL) je Listen-Link —
 * Folgeläufe holen nur noch Änderungen statt der vollen Sicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('msgraph_task_list_links', function (Blueprint $table): void {
            $table->text('delta_link')->nullable()->after('status'); // absolute Graph-URL
            $table->string('subscription_id', 190)->nullable()->after('delta_link')->index('msgtl_sub_idx');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_id');
            $table->text('webhook_secret')->nullable()->after('subscription_expires_at'); // encrypted-Cast
        });

        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->string('subscription_id', 190)->nullable()->after('last_imported_at')->index('msgc_sub_idx');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_id');
            $table->text('webhook_secret')->nullable()->after('subscription_expires_at'); // encrypted-Cast
        });

        Schema::table('email_connections', function (Blueprint $table): void {
            $table->string('subscription_id', 190)->nullable()->index('emailc_sub_idx');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->text('webhook_secret')->nullable(); // encrypted-Cast
        });
    }

    public function down(): void {
        Schema::table('msgraph_task_list_links', function (Blueprint $table): void {
            $table->dropIndex('msgtl_sub_idx');
            $table->dropColumn(['delta_link', 'subscription_id', 'subscription_expires_at', 'webhook_secret']);
        });

        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->dropIndex('msgc_sub_idx');
            $table->dropColumn(['subscription_id', 'subscription_expires_at', 'webhook_secret']);
        });

        Schema::table('email_connections', function (Blueprint $table): void {
            $table->dropIndex('emailc_sub_idx');
            $table->dropColumn(['subscription_id', 'subscription_expires_at', 'webhook_secret']);
        });
    }
};
