<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmploymentContractController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Applications\JobApplication;
use App\Models\Platform\User;
use App\Services\Contract\EmploymentContractService;
use App\Services\Hr\PersonnelFilePermissions as HR;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/** Arbeitsvertrag zur Unterschrift (MVP-939) für ein Teammitglied oder eine Bewerbung. */
class EmploymentContractController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly EmploymentContractService $contracts) {}

    public function createForMember(User $member): View {
        $this->authorizeHr();
        abort_unless((int) $member->organization_id === $this->currentOrganization()->id, 404);

        return view('hr._employment_contract_dialog', ['name' => $member->name, 'email' => $member->email, 'action' => route('contracts.employment.member.store', $member)]);
    }

    public function storeForMember(Request $request, User $member): RedirectResponse {
        $this->authorizeHr();
        abort_unless((int) $member->organization_id === $this->currentOrganization()->id, 404);

        return $this->store($request, $member);
    }

    public function createForApplication(JobApplication $application): View {
        $this->authorizeHr();

        return view('hr._employment_contract_dialog', ['name' => $application->candidate_name, 'email' => $application->email, 'action' => route('contracts.employment.application.store', $application)]);
    }

    public function storeForApplication(Request $request, JobApplication $application): RedirectResponse {
        $this->authorizeHr();

        return $this->store($request, $application);
    }

    private function store(Request $request, User|JobApplication $person): RedirectResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'declaration_text' => ['required', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);
        try {
            $contract = $this->contracts->create($this->currentOrganization(), $this->authUser(), $person, $data, $request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('contracts.show', $contract)->with('success', __('hr.employment.flash.sent', ['email' => $data['email']]));
    }

    private function authorizeHr(): void {
        abort_unless($this->authUser()->hasEffectivePermission(HR::CREATE), 403);
    }
}
