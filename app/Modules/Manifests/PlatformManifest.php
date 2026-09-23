<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlatformManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Plattform: Organisation, Nutzer, Betrieb“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class PlatformManifest extends Manifest {
    public function code(): string {
        return 'platform';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Plattform: Organisation, Nutzer, Betrieb';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Org',
            'Users',
            'Install',
            'Release',
            'Updates',
            'Diagnostics',
            'Metrics',
            'Navigation',
            'UI',
            'I18n',
            'Onboarding',
            'Support',
            'Stammdaten',
            'Timeline',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'component_updates',
            'geocode_cache',
            'help_topics',
            'help_views',
            'integrity_checks',
            'model_has_permissions',
            'model_has_roles',
            'onboarding_progress',
            'organization_audit_logs',
            'organization_sso_domains',
            'organizations',
            'permissions',
            'plugin_errors',
            'plugin_settings',
            'plugin_states',
            'remote_pending_sessions',
            'role_has_permissions',
            'roles',
            'scheduled_job_overrides',
            'scheduled_job_runs',
            'scheduled_job_states',
            'security_advisories',
            'support_access_grants',
            'system_settings',
            'team_user',
            'teams',
            'user_badges',
            'user_bookmarks',
            'user_dashboard_widgets',
            'user_filter_presets',
            'user_groups',
            'user_known_devices',
            'user_terminal_pins',
            'user_user_group',
            'user_workspaces',
            'users',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Organization,
            PermissionGroup::Members,
            PermissionGroup::Teams,
            PermissionGroup::Platform,
        ];
    }
}
