<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalContactController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Enums\User\Permission;
use App\Models\ExternalContact;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Verwaltung wiederverwendbarer externer Kontakt-/Rollenprofile (Feature 033,
 * Rang 30). Org-gescopt (BelongsToOrganization), Berechtigung wie die übrige
 * Externen-Verwaltung ({@see Permission::ExternalParticipantManage}). Anlegen/
 * Bearbeiten laufen als Modal-Dialog.
 */
class ExternalContactController extends Controller {
    public function index(): View {
        $this->authorizeManage();

        return view('external-contacts.index', [
            'contacts' => ExternalContact::query()->orderBy('name')->paginate(30),
            'parties' => ExternalParty::selectable(),
        ]);
    }

    public function create(): View {
        $this->authorizeManage();

        return view('external-contacts._form_dialog', [
            'contact' => null,
            'parties' => ExternalParty::selectable(),
        ]);
    }

    public function edit(ExternalContact $externalContact): View {
        $this->authorizeManage();

        return view('external-contacts._form_dialog', [
            'contact' => $externalContact,
            'parties' => ExternalParty::selectable(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeManage();
        $data = $this->validated($request);

        ExternalContact::query()->create($data);

        return redirect()->route('external-contacts.index')->with('success', __('external.contact.flash.created'));
    }

    public function update(Request $request, ExternalContact $externalContact): RedirectResponse {
        $this->authorizeManage();
        $externalContact->update($this->validated($request));

        return redirect()->route('external-contacts.index')->with('success', __('external.contact.flash.updated'));
    }

    public function destroy(ExternalContact $externalContact): RedirectResponse {
        $this->authorizeManage();
        $externalContact->delete();

        return redirect()->route('external-contacts.index')->with('success', __('external.contact.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['nullable', 'string', 'max:120'],
            'party' => ['required', 'string', 'in:' . implode(',', array_map(fn (ExternalParty $p): string => $p->value, ExternalParty::cases()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function authorizeManage(): void {
        abort_unless(Auth::user()?->can(Permission::ExternalParticipantManage->value) ?? false, 403);
    }
}
