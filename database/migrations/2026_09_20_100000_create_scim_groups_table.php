<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_20_100000_create_scim_groups_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCIM-2.0-Gruppen (Feature 057, MVP-121 → Rang 16). Eine Gruppe ist die
 * IdP-seitige Sammlung; die Mitgliedschaft wird — und nur dann — in `team_user`
 * gespiegelt, wenn die Gruppe explizit einem Team zugeordnet ist (`team_id`).
 * SCIM vergibt weiterhin NIE Rollen. `external_id` ist bewusst mutabel (Entra/
 * Okta ändern sie). Die SCIM-`id` ist die WorkDiary-Sqid des Datensatzes.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('scim_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'scimgrp_org_fk')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('external_id')->nullable();
            // IdP-seitige Mitgliederliste (Quelle der SCIM-Sicht, damit GET die
            // Mitglieder auch ohne Team-Mapping zurückgibt → Okta-PUT-Falle).
            // Jedes Element: {value: <User-Sqid>, user_id: <int|null>}.
            $table->json('members')->nullable();
            // Team-Zuordnung = expliziter Admin-Schritt; ohne Team wird die
            // Mitgliedschaft nur vorgehalten, nicht in team_user gespiegelt.
            $table->foreignId('team_id')->nullable()->constrained('teams', indexName: 'scimgrp_team_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'scimgrp_org_idx');
            $table->unique(['organization_id', 'display_name'], 'scimgrp_org_name_unique');
            $table->index(['organization_id', 'external_id'], 'scimgrp_org_ext_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('scim_groups');
    }
};
