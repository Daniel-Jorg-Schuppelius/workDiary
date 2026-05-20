<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BelongsToOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Automatically scopes a model to the current organization and populates
 * organization_id on creation when an organization context is active.
 *
 * @property int $organization_id
 *
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope<\Illuminate\Database\Eloquent\Model>|\Closure $scope)
 * @method static void creating(\Closure $callback)
 */
trait BelongsToOrganization {
    public static function bootBelongsToOrganization(): void {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (self $model): void {
            if (empty($model->organization_id) && app()->bound('currentOrganization')) {
                /** @var Organization|null $org */
                $org = app('currentOrganization');
                if ($org instanceof Organization) {
                    $model->organization_id = $org->id;
                }
            }

            // Fallback: organization_id vom zugehörigen Benutzer ableiten.
            // Hilfreich in Konsolen-/Queue-/Test-Kontexten, in denen kein
            // HTTP-Request die currentOrganization-Bindung gesetzt hat.
            if (
                empty($model->organization_id)
                && array_key_exists('user_id', $model->getAttributes())
                && ! empty($model->getAttribute('user_id'))
            ) {
                $owner = \App\Models\User::query()
                    ->whereKey($model->getAttribute('user_id'))
                    ->first();
                if ($owner !== null && ! empty($owner->organization_id)) {
                    $model->organization_id = $owner->organization_id;
                }
            }

            // Verhindert „Waisen"-Records: wenn ein eingeloggter Benutzer
            // ohne Organisations-Zuordnung versucht, einen tenant-scoped
            // Datensatz anzulegen, brechen wir mit klarer Fehlermeldung ab.
            // Konsolen-/Queue-/Seeder-Kontexte ohne Auth bleiben unberührt;
            // sie müssen organization_id ohnehin explizit setzen oder per
            // Model::withoutEvents() bewusst globale Vorlagen erzeugen.
            if (
                empty($model->organization_id)
                && \Illuminate\Support\Facades\Auth::check()
            ) {
                /** @var \App\Models\User|null $authUser */
                $authUser = \Illuminate\Support\Facades\Auth::user();
                if (
                    $authUser instanceof \App\Models\User
                    && empty($authUser->organization_id)
                ) {
                    throw new \App\Exceptions\MissingOrganizationException(static::class);
                }
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
