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

use App\Models\Organization;
use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-Oberfläche für die White-Label-/Branding-Einstellungen der
 * aktuellen Organisation: Stammdaten, Kontakt, Rechtliches, Farben und
 * pro-PDF-Typ Toggles. Die eigentlichen Logo-Uploads laufen über den
 * AttachmentController (Polymorphic Attachment + meta_type).
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
            'branding.legal.iban' => ['nullable', 'string', 'max:64'],
            'branding.legal.bic' => ['nullable', 'string', 'max:20'],
            'branding.legal.register' => ['nullable', 'string', 'max:200'],
            'branding.legal.footer_text' => ['nullable', 'string', 'max:500'],

            'branding.colors.primary' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'branding.colors.accent' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],

            'branding.pdf' => ['nullable', 'array'],
            'branding.pdf.*.logo' => ['nullable', 'in:light,dark,none'],
            'branding.pdf.*.show_contact' => ['nullable', 'boolean'],
            'branding.pdf.*.show_footer' => ['nullable', 'boolean'],
        ]);

        // Checkbox-Booleans für nicht gesendete Werte vorbelegen, sonst
        // bleibt der bisherige Wert in der DB. Wir wollen aber explizit
        // den UI-Zustand übernehmen.
        $pdfTypes = array_keys((array) config('branding.pdf', []));
        foreach ($pdfTypes as $type) {
            $data['branding']['pdf'][$type]['show_contact'] = (bool) ($request->input("branding.pdf.$type.show_contact"));
            $data['branding']['pdf'][$type]['show_footer'] = (bool) ($request->input("branding.pdf.$type.show_footer"));
            if (! isset($data['branding']['pdf'][$type]['logo'])) {
                $data['branding']['pdf'][$type]['logo'] = 'light';
            }
        }

        $branding = $this->stripEmpty($data['branding']);

        $current = (array) ($organization->settings ?? []);
        $current['branding'] = $branding;
        $organization->update(['settings' => $current]);

        // Cache der BrandingService-Resolution invalidieren wäre nice,
        // ist hier aber unnötig – jede Request resolved frisch und der
        // Service hält nur In-Memory-State pro Request.

        return redirect()
            ->route('admin.branding.edit')
            ->with('success', __('Branding aktualisiert.'));
    }

    /**
     * Ermittelt die Organisation des eingeloggten Admins. Ohne
     * Organisation-Kontext gibt es nichts zu branden → 404.
     */
    private function currentOrganization(): ?Organization {
        // Bevorzugt das vom SetOrganizationContext-Middleware gebundene
        // Modell verwenden – das ist die Single-Source-of-Truth des Requests.
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
     * Entfernt leere Strings/Null-Werte rekursiv, damit unten die
     * config-Defaults greifen, sobald ein Feld geleert wird.
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
