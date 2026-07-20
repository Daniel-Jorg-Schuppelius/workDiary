<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ThemeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\ThemeService;
use App\Support\ThemeDefinition;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Org-Admin-Editor für eigene (Custom-)Themes. Plan-Gating (Pro+) läuft über
 * EnforcePlanModules (config/plans.php → admin.themes.* = module.theming);
 * die Berechtigung wird zusätzlich über die Branding-Policy geprüft (Admin der
 * eigenen Organisation).
 *
 * Custom-Themes liegen in organizations.settings['theme']['custom'][] (Liste),
 * der Org-Default in settings['theme']['default']. Validierung + Sanitisierung
 * laufen ausschließlich über App\Support\ThemeDefinition.
 */
class ThemeController extends Controller {
    use ResolvesCurrentOrganization;

    /** Mindestkontrast neutral ↔ neutral-content (sonst werden .wd-badge-Flächen unlesbar). */
    private const MIN_NEUTRAL_CONTRAST = 3.0;

    public function index(): View {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        return view('admin.themes.index', [
            'organization' => $organization,
            'theme' => app(ThemeService::class),
        ]);
    }

    public function create(): View {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        return view('admin.themes._form_dialog', [
            'mode' => 'create',
            'definition' => null,
        ]);
    }

    public function edit(string $key): View {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        $definition = $this->findDefinition($key);
        abort_if($definition === null, 404);

        return view('admin.themes._form_dialog', [
            'mode' => 'edit',
            'definition' => $definition,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        $themeService = app(ThemeService::class);
        $existing = $themeService->customDefinitions();

        $maxCustom = (int) config('theme.max_custom', 12);
        if (count($existing) >= $maxCustom) {
            return back()->withInput()->with('error', __('Maximale Anzahl eigener Themes erreicht (:max).', ['max' => $maxCustom]));
        }

        $definition = $this->validateDefinition($request);

        // Key-Kollision mit Built-in oder bestehendem Custom-Theme verhindern.
        $usedKeys = array_merge(
            array_keys((array) config('theme.builtin', [])),
            array_map(fn($d) => $d->key, $existing),
        );
        if (in_array($definition->key, $usedKeys, true)) {
            throw ValidationException::withMessages(['key' => __('Dieser Schlüssel ist bereits vergeben.')]);
        }

        $list = array_map(fn($d) => $d->toArray(), $existing);
        $list[] = $definition->toArray();
        $this->persist($organization, $list);

        return redirect()->route('admin.themes.index')->with('success', __('Theme angelegt.'));
    }

    public function update(Request $request, string $key): RedirectResponse {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        $existing = app(ThemeService::class)->customDefinitions();
        abort_if($this->findDefinition($key) === null, 404);

        // Beim Bearbeiten bleibt der Key fix (er ist Teil von data-theme/Pref).
        $request->merge(['key' => $key]);
        $definition = $this->validateDefinition($request);

        $list = [];
        foreach ($existing as $d) {
            $list[] = $d->key === $key ? $definition->toArray() : $d->toArray();
        }
        $this->persist($organization, $list);

        return redirect()->route('admin.themes.index')->with('success', __('Theme aktualisiert.'));
    }

    public function destroy(string $key): RedirectResponse {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        $existing = app(ThemeService::class)->customDefinitions();
        $token = 'org-' . $key;

        $list = [];
        foreach ($existing as $d) {
            if ($d->key !== $key) {
                $list[] = $d->toArray();
            }
        }

        $theme = is_array($organization->settings['theme'] ?? null) ? $organization->settings['theme'] : [];
        // War das gelöschte Theme als Hell-/Dunkel-Standard gesetzt, fällt der
        // jeweilige Standard weg (greift dann auf corporate/dim zurück).
        $meta = [];
        if (($theme['default_light'] ?? null) === $token) {
            $meta['default_light'] = null;
        }
        if (($theme['default_dark'] ?? null) === $token) {
            $meta['default_dark'] = null;
        }

        $this->persist($organization, $list, $meta);

        return redirect()->route('admin.themes.index')->with('success', __('Theme gelöscht.'));
    }

    public function setDefault(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        Gate::authorize('manageBranding', $organization);

        $themeService = app(ThemeService::class);
        $data = $request->validate([
            'default_light' => ['nullable', 'string'],
            'default_dark' => ['nullable', 'string'],
        ]);

        $light = ($data['default_light'] ?? '') !== '' ? $data['default_light'] : null;
        $dark = ($data['default_dark'] ?? '') !== '' ? $data['default_dark'] : null;

        if ($light !== null && $themeService->schemeOf($light) !== 'light') {
            throw ValidationException::withMessages(['default_light' => __('Bitte ein Hell-Theme wählen.')]);
        }
        if ($dark !== null && $themeService->schemeOf($dark) !== 'dark') {
            throw ValidationException::withMessages(['default_dark' => __('Bitte ein Dunkel-Theme wählen.')]);
        }

        $list = array_map(fn($d) => $d->toArray(), $themeService->customDefinitions());
        $this->persist($organization, $list, ['default_light' => $light, 'default_dark' => $dark]);

        return redirect()->route('admin.themes.index')->with('success', __('Standard-Themes gesetzt.'));
    }

    /**
     * Validiert die Form-Eingaben und baut daraus eine ThemeDefinition.
     * Erzwingt zusätzlich den Mindestkontrast neutral ↔ neutral-content.
     */
    private function validateDefinition(Request $request): ThemeDefinition {
        // Gemeinsame Farb-Rule (Vollaudit 2026-07, N49).
        $hex = ['nullable', 'string', new \App\Rules\HexColor()];
        $hexRequired = ['required', 'string', new \App\Rules\HexColor()];
        $unit = ['nullable', 'string', 'regex:/^(0|[0-9]{1,2}(\.[0-9]{1,3})?(rem|px))$/'];

        $rules = [
            'key' => ['required', 'string', 'regex:/^[a-z0-9-]{1,32}$/'],
            'label' => ['required', 'string', 'max:60'],
            'scheme' => ['required', 'in:light,dark'],
        ];
        foreach (ThemeDefinition::REQUIRED_COLORS as $name) {
            $rules["colors.$name"] = $hexRequired;
        }
        foreach (array_keys(ThemeDefinition::STATUS_DEFAULTS) as $name) {
            $rules["colors.$name"] = $hex;
        }
        foreach (['base-content', 'primary-content', 'secondary-content', 'accent-content', 'neutral-content',
            'info-content', 'success-content', 'warning-content', 'error-content'] as $name) {
            $rules["colors.$name"] = $hex;
        }
        foreach (['radius-box', 'radius-field', 'radius-selector', 'border'] as $name) {
            $rules["geometry.$name"] = $unit;
        }

        $data = $request->validate($rules);

        $definition = ThemeDefinition::fromArray($data);
        if ($definition === null) {
            throw ValidationException::withMessages(['key' => __('Theme-Definition ungültig.')]);
        }

        if ($definition->neutralContrast() < self::MIN_NEUTRAL_CONTRAST) {
            throw ValidationException::withMessages([
                'colors.neutral-content' => __('Kontrast zwischen Neutral und Neutral-Text ist zu gering (für Seitenleisten/Panels nötig).'),
            ]);
        }

        return $definition;
    }

    private function findDefinition(string $key): ?ThemeDefinition {
        foreach (app(ThemeService::class)->customDefinitions() as $d) {
            if ($d->key === $key) {
                return $d;
            }
        }

        return null;
    }

    /**
     * Schreibt die Custom-Theme-Liste (und optional Default-Meta) zurück in
     * organizations.settings['theme'] und invalidiert den Request-Cache.
     *
     * @param array<int, array<string, mixed>> $list
     * @param array<string, ?string> $meta Theme-Meta-Keys (default_light/default_dark);
     *                                      Wert null entfernt den Key, sonst wird er gesetzt.
     */
    private function persist(Organization $organization, array $list, array $meta = []): void {
        $settings = (array) ($organization->settings ?? []);
        $theme = is_array($settings['theme'] ?? null) ? $settings['theme'] : [];

        $theme['custom'] = array_values($list);
        foreach ($meta as $key => $value) {
            if ($value === null) {
                unset($theme[$key]);
            } else {
                $theme[$key] = $value;
            }
        }
        // Legacy-Einzel-Default entfernen (ersetzt durch default_light/default_dark).
        unset($theme['default']);

        $settings['theme'] = $theme;
        $organization->update(['settings' => $settings]);
        app(ThemeService::class)->flush();
    }
}
