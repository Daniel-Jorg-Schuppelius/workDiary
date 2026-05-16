<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSignatureController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\PublicSignatureRequest;
use App\Models\Timesheet;
use App\Services\Timesheet\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicSignatureController extends Controller
{
    public function __construct(protected SignatureService $signatures) {}

    public function show(string $token): View|Response
    {
        $timesheet = $this->resolve($token);

        return view('public.timesheet-sign', ['timesheet' => $timesheet, 'token' => $token]);
    }

    public function store(string $token, PublicSignatureRequest $request): RedirectResponse
    {
        $timesheet = $this->resolve($token);
        $this->signatures->sign($timesheet, $request->string('signature')->toString(), $request->validated(), $request);

        return redirect()->route('timesheets.public-thanks');
    }

    public function thanks(): View
    {
        return view('public.timesheet-thanks');
    }

    protected function resolve(string $token): Timesheet
    {
        $timesheet = Timesheet::query()->withoutGlobalScopes()->where('magic_token', $token)->first();
        abort_if(! $timesheet, 404);
        abort_if($timesheet->magic_expires_at && $timesheet->magic_expires_at->isPast(), 410);

        return $timesheet;
    }
}
