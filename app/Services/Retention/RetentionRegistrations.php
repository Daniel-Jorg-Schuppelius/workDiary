<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionRegistrations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Retention;

use App\Modules\ModuleRegistry;
use App\Services\Retention\Contracts\RetentionPolicyProvider;

/**
 * Sammelt die Löschbereiche aller Module (MVP-863): jedes Modul meldet seine
 * {@see RetentionPolicy}s über `Manifest::extensions()[RetentionPolicyProvider::class]`.
 */
class RetentionRegistrations {
    public static function register(RetentionRegistry $registry): void {
        foreach (app(ModuleRegistry::class)->extensions(RetentionPolicyProvider::class) as $class) {
            /** @var RetentionPolicyProvider $provider */
            $provider = app($class);
            foreach ($provider->policies() as $policy) {
                $registry->register($policy);
            }
        }
    }
}
