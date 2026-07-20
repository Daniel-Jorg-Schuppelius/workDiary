<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BrandingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Organization, User};
use App\Services\BrandingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Admin-Oberfläche für die White-Label-/Branding-Einstellungen der aktuellen
 * Organisation (Stammdaten, Kontakt, Rechtliches, Farben, pro-PDF-Typ Toggles).
 * Logo-Uploads laufen über den {@see AttachmentController}.
 */
class BrandingController extends Controller {
    public function edit(): View|RedirectResponse {
        $organization = $this->currentOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->route('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }
        Gate::authorize('manageBranding', $organization);

        return view('admin.branding.edit', [
            'organization' => $organization,
            'branding' => app(BrandingService::class),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->route('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }
        Gate::authorize('manageBranding', $organization);

        $data = $request->validate([
            'branding.app_name' => ['nullable', 'string', 'max:120'],
            'branding.slogan' => ['nullable', 'string', 'max:200'],

            'branding.contact.street' => ['nullable', 'string', 'max:200'],
            'branding.contact.postal_code' => ['nullable', 'string', 'max:20'],
            'branding.contact.city' => ['nullable', 'string', 'max:120'],
            'branding.contact.country' => ['nullable', 'string', 'max:120'],
            'branding.contact.phone' => ['nullable', 'string', 'max:60'],
            'branding.contact.email' => ['nullable', 'email', 'max:200'],
            'branding.contact.web' => ['nullable', 'url', 'max:200'],

            'branding.legal.vat_id' => ['nullable', 'string', 'max:60'],
            'branding.legal.tax_number' => ['nullable', 'string', 'max:60'],
            // Gemeinsame Format-Rules (Vollaudit 2026-07, M39).
            'branding.legal.iban' => ['nullable', 'string', 'max:64', new \App\Rules\Iban()],
            'branding.legal.bic' => ['nullable', 'string', 'max:20', new \App\Rules\Bic()],
            'branding.legal.bank_name' => ['nullable', 'string', 'max:200'],
            'branding.legal.account_holder' => ['nullable', 'string', 'max:200'],
            'branding.legal.register' => ['nullable', 'string', 'max:200'],
            'branding.legal.footer_text' => ['nullable', 'string', 'max:500'],

            // Gemeinsame Farb-Rule (Vollaudit 2026-07, N49).
            'branding.colors.primary' => ['nullable', 'string', new \App\Rules\HexColor()],
            'branding.colors.accent' => ['nullable', 'string', new \App\Rules\HexColor()],

            'branding.pdf' => ['nullable', 'array'],
            'branding.pdf.*.logo' => ['nullable', 'in:light,dark,none'],
            'branding.pdf.*.show_contact' => ['nullable', 'boolean'],
            'branding.pdf.*.show_footer' => ['nullable', 'boolean'],
        ]);

        // Nicht gesendete Checkbox-Booleans vorbelegen, sonst bleibt der alte DB-Wert stehen statt den UI-Zustand zu übernehmen.
        $pdfTypes = array_keys((array) config('branding.pdf', []));
        foreach ($pdfTypes as $type) {
            $data['branding']['pdf'][$type]['show_contact'] = (bool) ($request->input("branding.pdf.$type.show_contact"));
            $data['branding']['pdf'][$type]['show_footer'] = (bool) ($request->input("branding.pdf.$type.show_footer"));
            if (! isset($data['branding']['pdf'][$type]['logo'])) {
                $data['branding']['pdf'][$type]['logo'] = 'light';
            }
        }

        // IBAN über den Toolkit-Normalisierer (Vollaudit 2026-07, M39/N40);
        // BIC bleibt manuell (kein Toolkit-Pendant).
        $data['branding']['legal']['iban'] = \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) ($data['branding']['legal']['iban'] ?? ''));
        $bic = (string) preg_replace('/\s+/', '', (string) ($data['branding']['legal']['bic'] ?? ''));
        $data['branding']['legal']['bic'] = $bic !== '' ? strtoupper($bic) : null;

        $branding = $this->stripEmpty($data['branding']);

        $current = (array) ($organization->settings ?? []);
        $current['branding'] = $branding;
        $organization->update(['settings' => $current]);

        // Keine Cache-Invalidierung nötig: BrandingService hält nur In-Memory-State pro Request.

        return redirect()
            ->route('admin.branding.edit')
            ->with('success', __('Branding aktualisiert.'));
    }

    /**
     * Ermittelt die Organisation des eingeloggten Admins.
     */
    private function currentOrganization(): ?Organization {
        // Bevorzugt das vom SetOrganizationContext-Middleware gebundene Modell (Single-Source-of-Truth des Requests).
        if (app()->bound('currentOrganization')) {
            $bound = app('currentOrganization');
            if ($bound instanceof Organization) {
                return $bound;
            }
        }

        /** @var User|null $user */
        $user = Auth::user();

        return $user?->organization;
    }

    /**
     * Entfernt leere Strings/Null-Werte rekursiv, damit die config-Defaults greifen, sobald ein Feld geleert wird.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stripEmpty(array $values): array {
        $out = [];
        foreach ($values as $k => $v) {
            if (is_array($v)) {
                $nested = $this->stripEmpty($v);
                if ($nested !== []) {
                    $out[$k] = $nested;
                }

                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
