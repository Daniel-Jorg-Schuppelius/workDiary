<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Customer\CustomerQueryStatus;
use App\Http\Controllers\{AttachmentController, Controller};
use App\Models\{Attachment, CustomerQuery, User};
use App\Services\Attachments\FileAttacher;
use App\Services\Customer\CustomerQueryService;
use App\Services\CustomerPortal\PortalQuerySubjects;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rückfragen/Kommentare im Kundenportal (MVP-512): read-only bleibt das
 * Portal — hier entsteht ausschließlich ein nachvollziehbarer
 * Frage-Antwort-Vorgang je ausdrücklich sichtbarem Subject
 * ({@see PortalQuerySubjects}). Nach dem Absenden sind Text und Anhänge
 * (MVP-712, Upload-Policy wie {@see TicketController}) aus Nachweisgründen
 * nicht editierbar; eine Rücknahme ist eine Statusänderung.
 */
class QueryController extends Controller {
    /** Obergrenze je Rückfrage — identisch zum Portal-Ticket. */
    public const MAX_FILES = 5;

    public function __construct(
        private readonly PortalQuerySubjects $subjects,
        private readonly CustomerQueryService $service,
        private readonly FileAttacher $attacher,
    ) {}

    /** Eigene Rückfragen des Kunden mit Antwort, Status und Subject-Kontext. */
    public function index(): View {
        $user = $this->portalUser();

        $queries = CustomerQuery::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('customer_id', $user->customer_id)
            ->with(['subject', 'answeredBy:id,name', 'attachments'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('customer.queries.index', [
            'queries' => $queries,
            'subjects' => $this->subjects,
        ]);
    }

    /** Formular (Subject über Query-Parameter vorbelegt). */
    public function create(Request $request): View {
        $user = $this->portalUser();

        $subject = $this->subjects->resolve(
            $user,
            (string) $request->query('subject_type', ''),
            (string) $request->query('subject', ''),
        );
        abort_if($subject === null, 404);

        return view('customer.queries.create', [
            'subject' => $subject,
            'subjectLabel' => $this->subjects->label($subject),
            'subjectType' => (string) $request->query('subject_type'),
            'subjectSqid' => (string) $request->query('subject'),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $user = $this->portalUser();

        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:32'],
            'subject' => ['required', 'string', 'max:64'],
            // Kein HTML: Ausgabe wird escaped, Eingabe hart begrenzt.
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $files = $this->validatedUploads($request);

        $subject = $this->subjects->resolve($user, (string) $data['subject_type'], (string) $data['subject']);
        abort_if($subject === null, 404);

        if (trim((string) $data['question']) === '') {
            return back()->withErrors(['question' => (string) __('Die Rückfrage darf nicht leer sein.')]);
        }

        $query = $this->service->raise($subject, [
            'organization_id' => (int) $user->organization_id,
            'customer_id' => (int) $user->customer_id,
            'asker_name' => $user->name,
            'asker_email' => $user->email,
            'question' => (string) $data['question'],
        ]);

        // Kunden-Uploads hängen kundensichtbar an der Rückfrage (MVP-712) —
        // Ablage über den kanonischen FileAttacher inkl. Quota-Guard.
        if ($files !== []) {
            $names = [];
            foreach ($files as $file) {
                $attachment = $this->attacher->store($query, $file, (int) $user->id, [
                    'organization_id' => (int) $user->organization_id,
                    'customer_visible' => true,
                ]);
                $names[] = $attachment->original_name;
            }
            $query->audit('portal.query.attachments_added', ['count' => count($names), 'files' => $names, 'by_portal_user_id' => (int) $user->id]);
        }

        return redirect()->route('customer.queries.index')
            ->with('status', __('Ihre Rückfrage wurde übermittelt. Sie werden benachrichtigt, sobald eine Antwort vorliegt.'));
    }

    /**
     * Sicherer Portal-Download eines Rückfrage-Anhangs (MVP-712): gleiche
     * Scope-Grenze wie die Liste (nur eigener Kunde), Anhang muss zur
     * Rückfrage gehören und kundensichtbar sein — sonst 404. Pfade stammen
     * ausschließlich aus der DB.
     */
    public function downloadAttachment(CustomerQuery $query, Attachment $attachment): BinaryFileResponse {
        $user = $this->portalUser();

        abort_unless(
            (int) $query->organization_id === (int) $user->organization_id
            && (int) $query->customer_id === (int) $user->customer_id,
            404,
        );
        abort_unless(
            $attachment->customer_visible
            && $attachment->attachable_type === $query->getMorphClass()
            && (int) $attachment->attachable_id === (int) $query->getKey(),
            404,
        );

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    /**
     * Datei-Uploads nach der zentralen Policy des {@see AttachmentController}
     * prüfen (Extension-Whitelist + Server-MIME via Fileinfo + Größenlimit) —
     * exakt das Muster des Portal-Tickets.
     *
     * @return list<UploadedFile>
     */
    private function validatedUploads(Request $request): array {
        $request->validate([
            'files' => ['nullable', 'array', 'max:' . self::MAX_FILES],
            'files.*' => ['file', 'max:' . FileAttacher::maxKb()],
        ]);

        $files = array_values(array_filter((array) $request->file('files', []), fn ($f) => $f instanceof UploadedFile));
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
            $serverMime = $file->getMimeType() ?? '';
            if (! in_array($ext, AttachmentController::ALLOWED_EXTENSIONS, true)
                || ! in_array($serverMime, AttachmentController::ALLOWED_MIMES, true)) {
                throw ValidationException::withMessages(['files' => (string) __('Dateityp nicht erlaubt.')]);
            }
        }

        return $files;
    }

    /** Rücknahme = protokollierte Statusänderung, kein spurloses Löschen. */
    public function withdraw(CustomerQuery $query): RedirectResponse {
        $user = $this->portalUser();

        abort_unless(
            (int) $query->organization_id === (int) $user->organization_id
            && (int) $query->customer_id === (int) $user->customer_id,
            404,
        );

        if ($query->status === CustomerQueryStatus::Open) {
            $this->service->close($query);
            $query->audit('portal.query.withdrawn', ['by_portal_user_id' => (int) $user->id]);
        }

        return redirect()->route('customer.queries.index')
            ->with('status', __('Die Rückfrage wurde zurückgezogen.'));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
