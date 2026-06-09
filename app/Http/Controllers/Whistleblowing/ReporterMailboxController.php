<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReporterMailboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Http\Controllers\Controller;
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\{
    WhistleblowingAttachmentService,
    WhistleblowingMailboxService,
    WhistleblowingMessageService,
};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

/**
 * Anonymes Postfach (Abschnitt 7.2 / 25). Login nur per Geheimnis, kurzlebige
 * Cookie-Sitzung. Der Reporter sieht nur freigegebene Nachrichten und den
 * groben Status – keine internen Notizen, keine Bearbeiterdaten.
 */
class ReporterMailboxController extends Controller {
    public function login(Request $request): View|RedirectResponse {
        if ($request->session()->has('wb_mailbox_case_id')) {
            return redirect()->route('whistleblowing.mailbox.show');
        }

        return view('whistleblowing.public.mailbox_login');
    }

    public function authenticate(Request $request, WhistleblowingMailboxService $mailbox): RedirectResponse {
        $request->validate(['secret' => ['required', 'string', 'max:200']]);

        $case = $mailbox->authenticate((string) $request->input('secret'));

        if ($case === null) {
            // Konstante Fehlermeldung – keine Information ueber Existenz.
            return back()->withErrors(['secret' => __('Zugang nicht moeglich. Bitte pruefen Sie Ihr Geheimnis.')]);
        }

        $request->session()->regenerate(); // Fixation verhindern
        $request->session()->put('wb_mailbox_case_id', $case->getKey());
        $request->session()->put('wb_mailbox_expires_at', Carbon::now()
            ->addMinutes((int) config('whistleblowing.mailbox_session_minutes', 30))->toIso8601String());

        return redirect()->route('whistleblowing.mailbox.show');
    }

    public function show(Request $request, WhistleblowingMailboxService $mailbox): View {
        $case = $this->case($request);
        $mailbox->markHandlerMessagesRead($case);

        $messages = $case->messages()
            ->where('visibility', 'reporter')
            ->orderBy('sent_at')
            ->get();

        $attachments = $case->attachments()
            ->where('uploaded_by_type', 'reporter')
            ->get();

        return view('whistleblowing.public.mailbox', [
            'case' => $case,
            'messages' => $messages,
            'attachments' => $attachments,
            'reporterStatus' => $case->status->reporterStatus(),
        ]);
    }

    public function message(Request $request, WhistleblowingMessageService $messages): RedirectResponse {
        $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $messages->receiveFromReporter($this->case($request), (string) $request->input('body'));

        return redirect()->route('whistleblowing.mailbox.show')->with('success', __('Ihre Nachricht wurde uebermittelt.'));
    }

    public function attachment(Request $request, WhistleblowingAttachmentService $attachments): RedirectResponse {
        $allowed = (array) config('whistleblowing.uploads.allowed_mimes', []);
        $maxKb = (int) ceil(((int) config('whistleblowing.uploads.max_bytes', 26214400)) / 1024);
        $request->validate(['file' => ['required', File::types($allowed)->max($maxKb)]]);

        $attachments->storeReporterUpload($this->case($request), $request->file('file'));

        return redirect()->route('whistleblowing.mailbox.show')->with('success', __('Datei hochgeladen.'));
    }

    public function logout(Request $request): RedirectResponse {
        $request->session()->forget(['wb_mailbox_case_id', 'wb_mailbox_expires_at']);
        $request->session()->regenerate();

        return redirect()->route('whistleblowing.mailbox.login');
    }

    private function case(Request $request): WhistleblowingCase {
        /** @var WhistleblowingCase $case */
        $case = $request->attributes->get('wb_mailbox_case');

        return $case;
    }
}
