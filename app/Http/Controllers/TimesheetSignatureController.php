<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetSignatureController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Mail\TimesheetSignatureRequestedMail;
use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Timesheet\PdfRenderer;
use App\Services\Timesheet\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class TimesheetSignatureController extends Controller
{
    public function __construct(protected SignatureService $signatures) {}

    public function store(Request $request, Project $project, Timesheet $timesheet): RedirectResponse
    {
        Gate::authorize('sign', $timesheet);

        $data = $request->validate([
            'signature' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->signatures->sign($timesheet, $data['signature'], $data, $request);

        return back()->with('success', __('Stundenzettel signiert.'));
    }

    public function lock(Request $request, Project $project, Timesheet $timesheet): RedirectResponse
    {
        Gate::authorize('lock', $timesheet);
        $this->signatures->lock($timesheet, $request->user());

        return back()->with('success', __('Gesperrt.'));
    }

    public function unlock(Project $project, Timesheet $timesheet): RedirectResponse
    {
        Gate::authorize('unlock', $timesheet);
        $this->signatures->unlock($timesheet);

        return back()->with('success', __('Entsperrt.'));
    }

    public function pdf(Project $project, Timesheet $timesheet, PdfRenderer $renderer): Response
    {
        Gate::authorize('view', $timesheet);
        $bytes = $renderer->render($timesheet);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="stundenzettel-%d.pdf"', $timesheet->id),
        ]);
    }

    public function magicLink(Project $project, Timesheet $timesheet): RedirectResponse
    {
        Gate::authorize('update', $timesheet);

        $minutes = (int) config('timesheet.signature.magic_minutes', 1440);
        $this->signatures->generateMagicToken($timesheet, $minutes);

        if ($timesheet->customer_email) {
            $url = route('timesheets.public-sign', ['token' => $timesheet->refresh()->magic_token]);
            Mail::to($timesheet->customer_email)->send(new TimesheetSignatureRequestedMail($timesheet, $url));
        }

        return back()->with('success', __('Magic-Link erzeugt.'));
    }
}
