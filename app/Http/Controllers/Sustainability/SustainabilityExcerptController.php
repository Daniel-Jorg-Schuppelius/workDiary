<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityExcerptController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Sustainability\SustainabilityReportSnapshot;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Sustainability\SustainabilityExcerptService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Freigabe des Nachhaltigkeitsauszugs (MVP-930). Link verwalten wie beim
 * Geräte-Pass (Recht `organization.update`), Inhalt wählt die
 * Nachhaltigkeitsleitung (Snapshot, Ziele).
 */
class SustainabilityExcerptController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly SustainabilityExcerptService $excerpt) {}

    public function edit(Request $request): View {
        Gate::authorize(P::SustainabilityManage->value);
        $organization = $this->currentOrganization();

        return view('sustainability._excerpt_dialog', [
            'status' => $this->excerpt->status($organization),
            'publication' => $this->excerpt->publication($organization),
            'snapshots' => SustainabilityReportSnapshot::query()->orderByDesc('id')->limit(20)->get(),
            'token' => $request->session()->get('sustainability_excerpt_token'),
            'canManageLink' => Gate::allows(P::OrganizationUpdate->value),
        ]);
    }

    public function publish(Request $request): RedirectResponse {
        Gate::authorize(P::SustainabilityManage->value);
        if ($request->filled('snapshot_id')) {
            $request->merge(['snapshot_id' => Sqid::decodeOrNumeric(SustainabilityReportSnapshot::class, $request->string('snapshot_id')->toString())]);
        }
        $data = $request->validate([
            'snapshot_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('sustainability_report_snapshots')],
            'statement' => ['nullable', 'string', 'max:2000'],
        ]);
        $snapshot = isset($data['snapshot_id']) ? SustainabilityReportSnapshot::query()->find((int) $data['snapshot_id']) : null;
        $statement = isset($data['statement']) ? trim($data['statement']) : null;
        $this->excerpt->publish($this->currentOrganization(), $snapshot, $request->boolean('targets'), $statement === '' ? null : $statement);
        // Umweltaussagen prüfen (MVP-961): Hinweis, keine Sperre.
        $findings = $statement !== null ? app(\App\Services\Sustainability\SustainabilityClaimChecker::class)->check($statement) : [];
        $redirect = redirect()->route('sustainability.excerpt.edit')->with('success', __('sustainability.excerpt.flash.published'));

        return $findings === [] ? $redirect : $redirect->with('warning', __('sustainability.claim.warning', ['terms' => implode(', ', array_column($findings, 'term'))]));
    }

    public function rotate(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);
        $token = $this->excerpt->issue($this->currentOrganization());

        return redirect()->route('sustainability.excerpt.edit')->with('sustainability_excerpt_token', $token)->with('success', __('sustainability.excerpt.flash.issued'));
    }

    public function revoke(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);
        $this->excerpt->revoke($this->currentOrganization());

        return redirect()->route('sustainability.excerpt.edit')->with('success', __('sustainability.excerpt.flash.revoked'));
    }

    public function toggle(Request $request): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);
        $this->excerpt->setEnabled($this->currentOrganization(), $request->boolean('enabled'));

        return redirect()->route('sustainability.excerpt.edit')->with('success', __('sustainability.excerpt.flash.saved'));
    }
}
