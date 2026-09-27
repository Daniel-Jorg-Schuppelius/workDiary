<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterviewOfferController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Applications\{JobApplication, JobApplicationInterview};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Applications\InterviewOfferService;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Terminangebot an den Bewerber anlegen und versenden (MVP-925). */
class InterviewOfferController extends Controller {
    public function store(Request $request, JobApplication $application, InterviewOfferService $offers): RedirectResponse {
        Gate::authorize('update', $application);
        abort_if($application->anonymized_at !== null, 404);
        if ($request->filled('interviewer_user_id')) {
            $request->merge(['interviewer_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('interviewer_user_id')->toString())]);
        }
        $request->merge(['slots' => array_values(array_filter((array) $request->input('slots', []), static fn (mixed $s): bool => is_string($s) && $s !== ''))]);
        $data = $request->validate([
            'slots' => ['required', 'array', 'min:1', 'max:5'],
            'slots.*' => ['required', 'date'],
            'mode' => ['required', Rule::in(JobApplicationInterview::MODES)],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'interviewer_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'valid_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        // datetime-local ist Ortszeit der Organisation; nur künftige Termine.
        $slots = array_values(array_filter(
            array_map(static fn (string $s): CarbonImmutable => Tz::parse($s)->utc(), $data['slots']),
            static fn (CarbonImmutable $s): bool => $s->isFuture(),
        ));

        try {
            $offers->offer($application, $slots, (string) $data['mode'], (int) $data['duration_minutes'], isset($data['interviewer_user_id']) ? (int) $data['interviewer_user_id'] : null, CarbonImmutable::now()->addDays((int) $data['valid_days']), $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('recruiting.offer.flash.sent', ['count' => count($slots)]));
    }
}
