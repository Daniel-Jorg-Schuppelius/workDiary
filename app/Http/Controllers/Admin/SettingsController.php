<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Organization, SystemSetting};
use App\Settings\{SettingScope, SettingType, SettingsRegistry};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Generische Einstellungs-Verwaltung aus der Settings-Registry
 * (Feature 067, MVP-174): System- und Organisations-Overrides mit
 * Suche, Gruppierung, Effektivwert + Herkunft und Rollback auf
 * Default. Schreiben ausschließlich über den validierten
 * Registry-Schreibweg; sensible Werte werden maskiert.
 */
class SettingsController extends Controller {
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::PlatformSettingsManage->value);

        $scope = $request->query('scope') === SettingScope::Organization->value
            ? SettingScope::Organization
            : SettingScope::System;
        $organization = $this->currentOrganization();
        $search = trim((string) $request->query('q', ''));

        $rows = [];
        foreach ($this->registry->forScope($scope) as $key => $definition) {
            if ($search !== '' && stripos($key, $search) === false) {
                continue;
            }
            $effective = $this->registry->effective($key, $scope === SettingScope::Organization ? $organization : null);
            $rows[$definition->group()][] = [
                'definition' => $definition,
                'effective' => $effective,
            ];
        }
        ksort($rows);

        return view('admin.settings.index', [
            'groups' => $rows,
            'scope' => $scope,
            'search' => $search,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse {
        Gate::authorize(Permission::PlatformSettingsManage->value);
        $definition = $this->registry->definition($key);
        $scope = $this->requestedScope($request);

        $raw = $request->input('value');
        $value = $this->castValue($definition->type, $raw);

        try {
            $this->registry->set($key, $value, $scope, $this->organizationFor($scope), $request->user()?->id);
        } catch (ValidationException $e) {
            return $this->redirectBack($request)->with('error', implode(' ', $e->validator->errors()->all()));
        } catch (\InvalidArgumentException $e) {
            return $this->redirectBack($request)->with('error', $e->getMessage());
        }

        return $this->redirectBack($request)->with('status', __('settingsregistry.flash.saved', ['key' => $key]));
    }

    public function reset(Request $request, string $key): RedirectResponse {
        Gate::authorize(Permission::PlatformSettingsManage->value);
        $scope = $this->requestedScope($request);

        $this->registry->reset($key, $scope, $this->organizationFor($scope));

        return $this->redirectBack($request)->with('status', __('settingsregistry.flash.reset', ['key' => $key]));
    }

    /** Änderungsverlauf eines System-Keys (Effektivwert-Erklärung, DoD). */
    public function history(string $key): View {
        Gate::authorize(Permission::PlatformSettingsManage->value);
        $definition = $this->registry->definition($key);

        $settingIds = SystemSetting::query()->where('key', $key)->pluck('id');
        $logs = AuditLog::query()
            ->where('auditable_type', SystemSetting::class)
            ->whereIn('auditable_id', $settingIds->isEmpty() ? [0] : $settingIds)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.settings._history_dialog', [
            'definition' => $definition,
            'logs' => $logs,
        ]);
    }

    private function requestedScope(Request $request): SettingScope {
        return $request->input('scope') === SettingScope::Organization->value
            ? SettingScope::Organization
            : SettingScope::System;
    }

    private function organizationFor(SettingScope $scope): ?Organization {
        return $scope === SettingScope::Organization ? $this->currentOrganization() : null;
    }

    private function currentOrganization(): ?Organization {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return $org instanceof Organization ? $org : null;
    }

    private function castValue(SettingType $type, mixed $raw): mixed {
        return match ($type) {
            SettingType::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            SettingType::Integer, SettingType::Duration => $raw === null || $raw === '' ? null : (int) $raw,
            SettingType::Decimal => $raw === null || $raw === '' ? null : (float) $raw,
            SettingType::Json => is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw,
            default => $raw === '' ? null : $raw,
        };
    }

    private function redirectBack(Request $request): RedirectResponse {
        return redirect()->route('admin.settings.index', array_filter([
            'scope' => $request->input('scope'),
            'q' => $request->input('q'),
        ]));
    }
}
