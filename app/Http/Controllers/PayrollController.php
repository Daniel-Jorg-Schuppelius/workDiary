<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PayrollController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{MinimumWage, MinimumWageReference, Organization, User};
use App\Services\Payroll\{EurostatMinimumWageImporter, MinimumWageService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

/**
 * Zentraler Lohn-/Sozialversicherungsbereich: Org-Stammdaten (Betriebsnummer,
 * Finanzamt), Mindestlohn-Historie und die Übersicht „Mitarbeiter unter
 * Mindestlohn". Zugriff erfordert die Permission `user.payroll.manage`
 * (Personalverwaltung + Geschäftsführung; Admin via syncPermissions).
 */
class PayrollController extends Controller {
    public function index(MinimumWageService $minimumWages): View {
        $organization = $this->authorizePayroll();

        $current = $minimumWages->currentFor(null, $organization->id);

        $belowMinimum = $current === null ? collect() : User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('payroll_hourly_wage')
            ->where('payroll_hourly_wage', '<', $current)
            ->orderBy('name')
            ->get(['id', 'name', 'payroll_hourly_wage', 'employment_type']);

        $country = strtoupper((string) ($organization->payroll('country') ?? 'DE'));

        return view('payroll.index', [
            'organization' => $organization,
            'payroll' => $organization->payroll(),
            'legal' => (array) ($organization->settings['branding']['legal'] ?? []),
            'reference' => MinimumWageReference::latestForCountry($country)->first(),
            'minimumWages' => MinimumWage::query()->orderByDesc('valid_from')->get(),
            'currentMinimum' => $current,
            'minijobLimit' => $minimumWages->minijobMonthlyLimit(null, $organization->id),
            'belowMinimum' => $belowMinimum,
        ]);
    }

    public function storeMinimumWage(Request $request): RedirectResponse {
        $organization = $this->authorizePayroll();

        $data = $request->validate([
            'valid_from' => ['required', 'date'],
            'hourly_amount' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        MinimumWage::updateOrCreate(
            ['organization_id' => $organization->id, 'valid_from' => $data['valid_from']],
            [
                'hourly_amount' => $data['hourly_amount'],
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'created_by' => Auth::id(),
            ],
        );

        return redirect()->route('payroll.index')
            ->with('success', __('Mindestlohn-Satz gespeichert.'));
    }

    /**
     * Lädt die gesetzliche Mindestlohn-Historie für das Land der Organisation
     * (ohne bestehende Sätze zu überschreiben).
     */
    public function seedMinimumWages(): RedirectResponse {
        $organization = $this->authorizePayroll();

        \Database\Seeders\MinimumWageSeeder::seedOrganization($organization);

        return redirect()->route('payroll.index')
            ->with('success', __('Gesetzliche Mindestlohn-Historie wurde geladen.'));
    }

    /** Holt die EU-Mindestlohn-Referenzdaten von Eurostat (synchron). */
    public function importReferences(EurostatMinimumWageImporter $importer): RedirectResponse {
        $this->authorizePayroll();

        try {
            $count = $importer->import();
        } catch (Throwable $e) {
            return back()->withErrors(['eurostat' => __('Eurostat-Import fehlgeschlagen: :error', ['error' => $e->getMessage()])]);
        }

        return redirect()->route('payroll.index')
            ->with('success', __(':count Eurostat-Datenpunkte importiert.', ['count' => $count]));
    }

    public function destroyMinimumWage(MinimumWage $minimumWage): RedirectResponse {
        $organization = $this->authorizePayroll();
        abort_unless((int) $minimumWage->organization_id === (int) $organization->id, 403);

        $minimumWage->delete();

        return redirect()->route('payroll.index')
            ->with('success', __('Mindestlohn-Satz entfernt.'));
    }

    /**
     * Hebt Stundenlöhne unter dem aktuellen Mindestlohn an — entweder für einen
     * einzelnen Mitarbeiter (`user`-Sqid) oder für alle Betroffenen.
     */
    public function raiseToMinimum(Request $request, MinimumWageService $minimumWages): RedirectResponse {
        $organization = $this->authorizePayroll();

        $current = $minimumWages->currentFor(null, $organization->id);
        if ($current === null) {
            return back()->withErrors(['minimum' => __('Es ist kein Mindestlohn hinterlegt.')]);
        }

        $query = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('payroll_hourly_wage')
            ->where('payroll_hourly_wage', '<', $current);

        $single = trim((string) $request->input('user', ''));
        if ($single !== '') {
            $userId = Sqid::decode(User::class, $single) ?? (is_numeric($single) ? (int) $single : null);
            abort_if($userId === null, 422, (string) __('Ungültige Auswahl.'));
            $query->whereKey($userId);
        }

        $count = 0;
        foreach ($query->get() as $member) {
            $member->forceFill(['payroll_hourly_wage' => $current])->save();
            $member->audit('payroll.wage.raised_to_minimum', ['amount' => $current]);
            $count++;
        }

        return redirect()->route('payroll.index')
            ->with('success', __(':count Stundenlohn/-löhne auf den Mindestlohn angehoben.', ['count' => $count]));
    }

    public function updateSettings(Request $request): RedirectResponse {
        $organization = $this->authorizePayroll();

        $data = $request->validate([
            // Payroll-/SV-spezifisch (eigene Gruppe)
            'country' => ['nullable', 'string', 'size:2'],
            'company_number' => ['nullable', 'string', 'max:32'],
            'tax_office' => ['nullable', 'string', 'max:191'],
            // Steuerliche Identifikatoren — geteilte Quelle mit Branding/Rechnungen
            'tax_number' => ['nullable', 'string', 'max:60'],
            'vat_id' => ['nullable', 'string', 'max:60'],
            'register' => ['nullable', 'string', 'max:200'],
        ]);

        $clean = fn(array $keys) => array_filter(
            array_map(fn($k) => trim((string) ($data[$k] ?? '')), array_combine($keys, $keys)),
            fn($v) => $v !== '',
        );

        $settings = (array) ($organization->settings ?? []);

        // Payroll-Gruppe (Land, Betriebsnummer/Knappschaft, Finanzamt).
        $payroll = $clean(['country', 'company_number', 'tax_office']);
        if (isset($payroll['country'])) {
            $payroll['country'] = strtoupper($payroll['country']);
        }
        if ($payroll === []) {
            unset($settings['payroll']);
        } else {
            $settings['payroll'] = $payroll;
        }

        // Steuer-Identifikatoren in branding.legal pflegen (Single Source, auch
        // für Rechnungs-PDFs); nur die hier verwalteten Schlüssel anfassen.
        $legal = (array) ($settings['branding']['legal'] ?? []);
        foreach (['tax_number', 'vat_id', 'register'] as $k) {
            $val = trim((string) ($data[$k] ?? ''));
            if ($val === '') {
                unset($legal[$k]);
            } else {
                $legal[$k] = $val;
            }
        }
        if ($legal === []) {
            unset($settings['branding']['legal']);
            if (($settings['branding'] ?? []) === []) {
                unset($settings['branding']);
            }
        } else {
            $settings['branding']['legal'] = $legal;
        }

        $organization->update(['settings' => $settings]);

        return redirect()->route('payroll.index')
            ->with('success', __('Lohn-Stammdaten wurden gespeichert.'));
    }

    /**
     * Stellt sicher, dass der Benutzer den Lohnbereich verwalten darf, und
     * liefert die zugehörige Organisation.
     */
    protected function authorizePayroll(): Organization {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->organization_id !== null && $user->can(Permission::UserPayrollManage->value), 403);

        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 422, (string) __('Kein Organisationskontext.'));

        return $organization;
    }
}
