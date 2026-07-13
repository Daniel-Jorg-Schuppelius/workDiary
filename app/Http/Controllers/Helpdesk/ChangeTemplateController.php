<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Change, ChangeTemplate, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Standard-Change-Vorlagen (Feature 065, MVP-157): Modal-CRUD +
 * „freigeben"-Aktion. Versionierung nach Bestandsmuster (RequestItem/
 * MVP-154): jede inhaltliche Änderung erhöht die Version UND zieht die
 * Freigabe zurück (erneutes Freigeben nötig — sonst wäre das
 * approved-Flag wertlos); bestehende Changes bleiben über ihren
 * template_snapshot unberührt.
 */
class ChangeTemplateController extends Controller {
    public function index(): View {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);

        return view('helpdesk.change-templates.index', [
            'templates' => ChangeTemplate::query()
                ->withCount('changes')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);

        return view('helpdesk.change-templates._form_dialog', [
            'template' => new ChangeTemplate,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);

        $template = ChangeTemplate::query()->create([
            ...$this->validatedTemplate($request),
            'organization_id' => (int) $this->actor()->organization_id,
        ]);
        $template->audit('change_template.created', ['actor' => $this->actor()->id]);

        return redirect()->route('servicedesk.change-templates.index')
            ->with('success', __('Vorlage angelegt.'));
    }

    public function edit(ChangeTemplate $template): View {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);
        $this->assertSameOrg($template);

        return view('helpdesk.change-templates._form_dialog', [
            'template' => $template,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, ChangeTemplate $template): RedirectResponse {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);
        $this->assertSameOrg($template);

        // Version-Bump + Freigabe-Rückzug (Vier-Augen-Sinn der Freigabe);
        // laufende Changes bleiben über ihre Snapshots unberührt.
        $template->update([
            ...$this->validatedTemplate($request),
            'version' => (int) $template->version + 1,
            'approved' => false,
        ]);
        $template->audit('change_template.updated', ['version' => $template->version, 'actor' => $this->actor()->id]);

        return redirect()->route('servicedesk.change-templates.index')
            ->with('success', __('Vorlage gespeichert — bitte erneut freigeben.'));
    }

    /** Freigabe: erst damit taugt die Vorlage für Standard-Changes. */
    public function approve(ChangeTemplate $template): RedirectResponse {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);
        $this->assertSameOrg($template);

        if (! $template->approved) {
            $template->update(['approved' => true]);
            $template->audit('change_template.approved', ['version' => $template->version, 'actor' => $this->actor()->id]);
        }

        return redirect()->route('servicedesk.change-templates.index')
            ->with('success', __('Vorlage freigegeben.'));
    }

    public function destroy(ChangeTemplate $template): RedirectResponse {
        Gate::authorize(Permission::ServiceDeskChangeManage->value);
        $this->assertSameOrg($template);

        if (Change::query()->where('change_template_id', $template->id)->exists()) {
            return back()->with('error', __('Zur Vorlage existieren Changes — bitte stattdessen nicht freigeben.'));
        }

        $template->delete();

        return redirect()->route('servicedesk.change-templates.index')
            ->with('success', __('Vorlage gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'implementation_plan' => ['nullable', 'string', 'max:20000'],
            'test_plan' => ['nullable', 'string', 'max:20000'],
            'rollback_plan' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    private function assertSameOrg(ChangeTemplate $template): void {
        abort_unless((int) $template->organization_id === (int) $this->actor()->organization_id, 404);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
