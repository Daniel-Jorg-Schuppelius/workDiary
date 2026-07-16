<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageTypeMappingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Surcharge\SurchargeRule;
use App\Models\{TimeExportDeliveryConfig, WageTypeMapping};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin-UI für Lohnarten-Mapping + automatische Export-Lieferung
 * (A21 · MVP-019): interne Lohnarten → externe Lohnartennummern je
 * Export-Profil, optional automatische Lieferung (Mail/SFTP-Ziel).
 * Pflege durch Admin und Buchhaltung/Lohnbüro (wageTypeMapping.manage).
 */
class WageTypeMappingController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', WageTypeMapping::class);

        $mappings = WageTypeMapping::query()
            ->orderBy('profile')
            ->orderBy('wage_type')
            ->get();

        return view('admin.wage-type-mappings.index', [
            'mappings' => $mappings,
            'profiles' => $this->profileLabels(),
            'deliveryConfigs' => TimeExportDeliveryConfig::query()->get()->keyBy('profile'),
            'canManage' => Gate::allows('create', WageTypeMapping::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', WageTypeMapping::class);

        return view('admin.wage-type-mappings._form_dialog', [
            'mapping' => new WageTypeMapping(['profile' => (string) config('exports.default', 'generic')]),
            'profiles' => $this->profileLabels(),
            'wageTypes' => $this->wageTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', WageTypeMapping::class);

        WageTypeMapping::query()->create($this->validated($request));

        return redirect()->route('admin.wage-type-mappings.index')
            ->with('success', __('wage_types.flash.created'));
    }

    public function edit(WageTypeMapping $wageTypeMapping): View {
        Gate::authorize('update', $wageTypeMapping);

        return view('admin.wage-type-mappings._form_dialog', [
            'mapping' => $wageTypeMapping,
            'profiles' => $this->profileLabels(),
            'wageTypes' => $this->wageTypeOptions(),
        ]);
    }

    public function update(Request $request, WageTypeMapping $wageTypeMapping): RedirectResponse {
        Gate::authorize('update', $wageTypeMapping);

        $wageTypeMapping->update($this->validated($request, $wageTypeMapping));

        return redirect()->route('admin.wage-type-mappings.index')
            ->with('success', __('wage_types.flash.updated'));
    }

    public function destroy(WageTypeMapping $wageTypeMapping): RedirectResponse {
        Gate::authorize('delete', $wageTypeMapping);

        $wageTypeMapping->delete();

        return redirect()->route('admin.wage-type-mappings.index')
            ->with('success', __('wage_types.flash.deleted'));
    }

    // ── Automatische Lieferung je Profil ───────────────────────────────

    public function editDelivery(string $profile): View {
        Gate::authorize('create', WageTypeMapping::class);
        abort_unless(array_key_exists($profile, $this->profileLabels()), 404);

        $config = TimeExportDeliveryConfig::query()->firstOrNew(['profile' => $profile]);

        return view('admin.wage-type-mappings._delivery_dialog', [
            'config' => $config,
            'profile' => $profile,
            'profileLabel' => $this->profileLabels()[$profile],
        ]);
    }

    public function updateDelivery(Request $request, string $profile): RedirectResponse {
        Gate::authorize('create', WageTypeMapping::class);
        abort_unless(array_key_exists($profile, $this->profileLabels()), 404);

        // Empfänger-Rohtext splitten, damit die Adress-Validierung je Eintrag greift.
        $rawRecipients = (string) $request->input('mail_recipients_raw', '');
        $recipients = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,;]+/', $rawRecipients) ?: [],
        ), static fn (string $mail): bool => $mail !== ''));
        $request->merge(['mail_recipients' => $recipients]);

        $data = $request->validate([
            'mail_enabled' => ['required', 'boolean'],
            'mail_recipients' => ['array', 'required_if:mail_enabled,1', 'max:20'],
            'mail_recipients.*' => ['email', 'max:190'],
            'sftp_enabled' => ['required', 'boolean'],
            'sftp_host' => ['nullable', 'string', 'max:190', 'required_if:sftp_enabled,1'],
            'sftp_port' => ['nullable', 'integer', 'between:1,65535'],
            'sftp_username' => ['nullable', 'string', 'max:190', 'required_if:sftp_enabled,1'],
            'sftp_password' => ['nullable', 'string', 'max:255'],
            'sftp_root' => ['nullable', 'string', 'max:190'],
        ], [
            'mail_recipients.required_if' => (string) __('wage_types.validation.recipients_required'),
        ]);

        /** @var TimeExportDeliveryConfig $config */
        $config = TimeExportDeliveryConfig::query()->firstOrNew(['profile' => $profile]);

        // Leere Strings ⇒ NULL (encrypted-Casts crashen sonst beim decrypt).
        $attributes = [
            'mail_enabled' => (bool) $data['mail_enabled'],
            'mail_recipients' => $recipients === [] ? null : $recipients,
            'sftp_enabled' => (bool) $data['sftp_enabled'],
            'sftp_host' => trim((string) ($data['sftp_host'] ?? '')) ?: null,
            'sftp_port' => (int) ($data['sftp_port'] ?? 22) ?: 22,
            'sftp_username' => trim((string) ($data['sftp_username'] ?? '')) ?: null,
            'sftp_root' => trim((string) ($data['sftp_root'] ?? '')) ?: null,
        ];

        // Passwort nur bei Eingabe ersetzen; leer lässt den Bestand stehen.
        $password = trim((string) ($data['sftp_password'] ?? ''));
        if ($password !== '') {
            $attributes['sftp_password'] = $password;
        } elseif ($attributes['sftp_enabled'] && ($config->sftp_password ?? '') === '') {
            return back()->withInput()->with('error', __('wage_types.validation.password_required'));
        }

        $config->fill($attributes)->save();

        return redirect()->route('admin.wage-type-mappings.index')
            ->with('success', __('wage_types.flash.delivery_saved'));
    }

    // ── intern ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validated(Request $request, ?WageTypeMapping $mapping = null): array {
        $organizationId = $mapping->organization_id ?? $this->authUser()->organization_id;
        $profileKeys = array_keys($this->profileLabels());
        $profile = (string) $request->input('profile', '');

        return $request->validate([
            'profile' => ['required', 'string', Rule::in($profileKeys)],
            'wage_type' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('wage_type_mappings', 'wage_type')
                    ->where('organization_id', $organizationId)
                    ->where('profile', $profile)
                    ->ignore($mapping?->id),
            ],
            'external_code' => [
                'required',
                'string',
                'max:20',
                'regex:' . $this->externalCodePattern($profile),
            ],
        ], [
            'external_code.regex' => (string) __('wage_types.validation.external_code_format'),
            'wage_type.unique' => (string) __('wage_types.validation.wage_type_unique'),
        ]);
    }

    /** Validierungs-Regex der externen Lohnart für das gewählte Profil. */
    private function externalCodePattern(string $profile): string {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $pattern = $profiles[$profile]['wage_type_code_pattern'] ?? null;

        return is_string($pattern) && $pattern !== '' ? $pattern : '/^[A-Za-z0-9][A-Za-z0-9._-]{0,19}$/';
    }

    /** @return array<string, string> Profil-Schlüssel → Anzeigename. */
    private function profileLabels(): array {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $out = [];
        foreach ($profiles as $key => $cfg) {
            if (! isset($cfg['driver'])) {
                continue;
            }
            $out[(string) $key] = isset($cfg['label']) && is_string($cfg['label']) ? $cfg['label'] : (string) $key;
        }

        return $out;
    }

    /**
     * Interne Lohnarten für das Formular: Standard-Katalog (../WorkDiary-Architecture/zeit-export.md §5.1)
     * plus die Zuschlagsarten der Organisation (surcharge.<code>).
     *
     * @return array<string, array<string, string>> Gruppe → (wage_type → Label)
     */
    private function wageTypeOptions(): array {
        $standard = [];
        foreach (WageTypeMapping::STANDARD_WAGE_TYPES as $type) {
            $standard[$type] = $type;
        }

        $surcharges = [];
        foreach (SurchargeRule::query()->orderBy('code')->get(['code', 'label']) as $rule) {
            $surcharges[$rule->wageType()] = $rule->wageType() . ' — ' . $rule->label;
        }

        return [
            'standard' => $standard,
            'surcharge' => $surcharges,
        ];
    }
}
