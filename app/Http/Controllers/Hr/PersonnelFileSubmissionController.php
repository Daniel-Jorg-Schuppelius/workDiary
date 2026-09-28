<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSubmissionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Hr\{HrDocumentCategory, PersonnelFileSubmissionStatus};
use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Hr\PersonnelFileSubmission;
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Hr\{PersonnelFilePermissions, PersonnelFileService};
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Einreichungen zur Personalakte (MVP-987): Die betroffene Person reicht eine
 * Unterlage ein, der hrFile-Kreis übernimmt sie in die Akte oder lehnt mit
 * Grund ab — nie für die eigene Akte.
 */
class PersonnelFileSubmissionController extends Controller {
    public function __construct(private readonly PersonnelFileService $service) {}

    /** Offene Einreichungen aller Mitglieder für den Akten-Kreis. */
    public function index(): View {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->hasEffectivePermission(PersonnelFilePermissions::VIEW_ANY), 403);

        return view('hr.personnel-file.submissions', [
            'submissions' => PersonnelFileSubmission::query()->with('user:id,name')
                ->where('status', PersonnelFileSubmissionStatus::Submitted->value)
                ->where('user_id', '!=', $user->id)
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function form(): View {
        return view('hr.personnel-file._submit_dialog');
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'hr_category' => ['required', Rule::enum(HrDocumentCategory::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:' . FileAttacher::maxKb()],
        ]);
        /** @var User $user */
        $user = Auth::user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->service->submit($user, ['title' => (string) $data['title'], 'hr_category' => (string) $data['hr_category'], 'note' => $data['note'] ?? null], $file);

        return redirect()->route('account.personnel-file')->with('success', __('hr.personnel_file.flash.submitted'));
    }

    public function download(PersonnelFileSubmission $submission): StreamedResponse {
        $this->authorizeDecision($submission);
        abort_if($submission->path === null || ! Storage::disk($submission->disk)->exists($submission->path), 404);

        return Storage::disk($submission->disk)->download($submission->path, $submission->original_name);
    }

    public function acceptForm(PersonnelFileSubmission $submission): View {
        $this->authorizeDecision($submission);

        return view('hr.personnel-file._accept_dialog', ['submission' => $submission]);
    }

    public function accept(Request $request, PersonnelFileSubmission $submission): RedirectResponse {
        $member = $this->authorizeDecision($submission);
        $data = $request->validate(PersonnelFileService::rules(includeFile: false));
        /** @var User $actor */
        $actor = Auth::user();
        $this->service->accept($submission, $actor, $data);

        return redirect()->route('org.members.personnel-file.index', $member)->with('success', __('hr.personnel_file.flash.accepted'));
    }

    public function rejectForm(PersonnelFileSubmission $submission): View {
        $this->authorizeDecision($submission);

        return view('hr.personnel-file._reject_dialog', ['submission' => $submission]);
    }

    public function reject(Request $request, PersonnelFileSubmission $submission): RedirectResponse {
        $member = $this->authorizeDecision($submission);
        $data = $request->validate(['review_note' => ['required', 'string', 'min:3', 'max:500']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->service->reject($submission, $actor, (string) $data['review_note']);

        return redirect()->route('org.members.personnel-file.index', $member)->with('success', __('hr.personnel_file.flash.rejected'));
    }

    /** Entscheiden darf der Akten-Kreis, nie über die eigene Einreichung. */
    private function authorizeDecision(PersonnelFileSubmission $submission): User {
        $member = $submission->user()->firstOrFail();
        Gate::authorize('createPersonnelFile', [Document::class, $member]);
        abort_if((int) $member->id === (int) Auth::id(), 403);

        return $member;
    }
}
