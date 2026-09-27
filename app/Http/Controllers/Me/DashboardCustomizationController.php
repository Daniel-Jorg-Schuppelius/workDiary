<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardCustomizationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Me;

use App\Enums\Dashboard\WidgetWidth;
use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\Dashboard\{DashboardLayoutService, DashboardPresets};
use App\Services\Navigation\StartPageResolver;
use App\Support\Dashboard\DashboardLayoutItem;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Dashboard anpassen": Reihenfolge, Sichtbarkeit, Breite und Bereich der
 * Kacheln.
 *
 * Gespeichert wird immer die vollständige Liste — auch die ausgeblendeten
 * Kacheln, sonst ginge ihre Position verloren. Wer die Organisation
 * verwalten darf (organization.update), kann dieselbe Liste zusätzlich als
 * Vorgabe für alle hinterlegen; sie greift bei jedem Nutzer, der noch keine
 * eigene Wahl getroffen (oder sie zurückgesetzt) hat.
 */
class DashboardCustomizationController extends Controller {
    public function __construct(
        private readonly DashboardLayoutService $layout,
        private readonly DashboardPresets $presets,
    ) {}

    public function index(): View {
        /** @var User $user */
        $user = Auth::user();

        $items = $this->layout->resolveFor($user)
            ->map(fn (DashboardLayoutItem $item): array => [
                'key' => $item->key(),
                'label' => $item->widget->label(),
                'icon' => $item->widget->icon(),
                'description' => $item->widget->description(),
                'group' => $item->widget->group(),
                'hidden' => $item->hidden,
                'width' => $item->width,
                'tab' => $item->tabKey,
                'source' => $item->source,
            ])
            ->all();

        return view('dashboard.customize', [
            'items' => $items,
            'tabs' => $this->layout->tabsFor($user),
            'presets' => array_map(fn (string $key): array => [
                'key' => $key,
                'label' => $this->presets->label($key),
                'description' => $this->presets->description($key),
            ], $this->presets->keys()),
            'canManageOrgDefault' => Gate::allows(Permission::OrganizationUpdate->value),
            'configurableRoles' => StartPageResolver::CONFIGURABLE_ROLES,
            'rolesWithDefault' => $this->layout->rolesWithDefault(Auth::user()?->organization),
            'hasOrgDefault' => $this->layout->hasOrgDefault($user->organization),
            'hasOwnLayout' => $user->dashboardWidgets()->exists() || $this->layout->hasOwnTabs($user),
        ]);
    }

    public function save(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $payload = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.key' => ['required', 'string', 'max:80'],
            'widgets.*.hidden' => ['nullable', 'boolean'],
            'widgets.*.width' => ['nullable', Rule::enum(WidgetWidth::class)],
            'widgets.*.tab' => ['nullable', 'string', 'max:40'],
            // Bereiche: Schlüssel landen in Alpine-Attributen, deshalb streng
            // auf [a-z0-9-] begrenzt; die Beschriftung ist frei.
            'tabs' => ['nullable', 'array', 'max:' . DashboardLayoutService::MAX_TABS],
            'tabs.*.key' => ['required', 'string', 'regex:/^[a-z0-9-]{1,40}$/'],
            'tabs.*.label' => ['required', 'string', 'max:40'],
            // Symbol ist ein Material-Symbol-Name; eng gefasst, weil er auch
            // in der Org-Vorgabe landet und dort ungeprüft gerendert wird.
            'tabs.*.icon' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,40}$/'],
            // MVP-910: zusätzlich als Vorgabe einer Rolle („role:<rolle>“).
            'scope' => ['nullable', Rule::in(array_merge(['user', 'organization'], array_map(static fn (UserRole $r): string => 'role:' . $r->value, StartPageResolver::CONFIGURABLE_ROLES)))],
        ]);

        /** @var list<array{key:string,hidden?:mixed,width?:?string,tab?:?string}> $rows */
        $rows = $payload['widgets'];
        /** @var list<array{key:string,label:string}> $tabs */
        $tabs = $payload['tabs'] ?? [];

        $scope = (string) ($payload['scope'] ?? 'user');
        $role = str_starts_with($scope, 'role:') ? UserRole::from(substr($scope, 5)) : null;
        $asOrgDefault = $scope === 'organization' || $role !== null;
        if ($asOrgDefault) {
            Gate::authorize(Permission::OrganizationUpdate->value);
            $organization = $user->organization;
            abort_if($organization === null, 404);

            $this->layout->saveOrgDefault($organization, $rows, $tabs, $role);
        }

        $this->layout->saveForUser($user, $rows, $tabs);

        return redirect()->route('dashboard.customize')
            ->with('status', match (true) {
                $role !== null => __('dashboard.role_default.saved', ['role' => $role->label()]),
                $asOrgDefault => __('Dashboard-Konfiguration gespeichert und als Standard der Organisation hinterlegt.'),
                default => __('Dashboard-Konfiguration gespeichert.'),
            });
    }

    /**
     * Übernimmt eine fertige Anordnung. Gespeichert wird über denselben Weg
     * wie eine Handeingabe — inklusive Rechte- und Modul-Filter; danach steht
     * das Ergebnis normal zum Nachbearbeiten in der Liste.
     */
    public function preset(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $key = (string) $request->input('preset', '');
        abort_unless($this->presets->exists($key), 404);

        $this->layout->saveForUser($user, $this->presets->widgets($key), $this->presets->tabs($key));

        return redirect()->route('dashboard.customize')
            ->with('status', __('Anordnung „:preset" übernommen.', ['preset' => $this->presets->label($key)]));
    }

    /**
     * Verwirft die eigene Anordnung; danach gilt wieder die Vorgabe der
     * Organisation bzw. die der Kacheln selbst.
     */
    /** Rollen-Vorgabe entfernen (MVP-910). */
    public function forgetRoleDefault(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationUpdate->value);
        $role = UserRole::from((string) $request->validate(['role' => ['required', Rule::in(array_map(static fn (UserRole $r): string => $r->value, StartPageResolver::CONFIGURABLE_ROLES))]])['role']);
        $organization = Auth::user()?->organization;
        abort_if($organization === null, 404);
        $this->layout->forgetRoleDefault($organization, $role);

        return redirect()->route('dashboard.customize')->with('status', __('dashboard.role_default.forgotten', ['role' => $role->label()]));
    }

    public function reset(): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $this->layout->resetUser($user);

        return redirect()->route('dashboard.customize')
            ->with('status', __('Eigene Anordnung verworfen — es gilt wieder die Vorgabe.'));
    }
}
