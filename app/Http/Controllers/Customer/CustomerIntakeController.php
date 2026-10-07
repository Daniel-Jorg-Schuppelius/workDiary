<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Enums\Sales\QuoteStatus;
use App\Http\Controllers\Concerns\{ResolvesGlobalDateRange, ValidatesUploadedFiles};
use App\Http\Controllers\Controller;
use App\Models\Attachments\Attachment;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\User;
use App\Models\Sales\Quote;
use App\Services\Customer\Intake\{CustomerIntakeHandoverService, CustomerIntakeQuoteService, CustomerIntakeService, CustomerIntakeStages, CustomerIntakeUploadChannels};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Interne Bearbeitung der Kundeneingänge (Feature 162, MVP-1074/1075):
 * gemeinsame Liste (offene immer, abgeschlossene im globalen Zeitraum),
 * Akte mit Dateien, Rückfragen, Angebot und Übernahme. Formulare laufen als
 * Dialoge; geschrieben wird nur über die Eingangsdienste.
 */
class CustomerIntakeController extends Controller {
    use ResolvesGlobalDateRange;
    use ValidatesUploadedFiles;

    public function __construct(
        private readonly CustomerIntakeService $intakes,
        private readonly CustomerIntakeQuoteService $quotes,
        private readonly CustomerIntakeHandoverService $handover,
        private readonly CustomerIntakeStages $stages,
        private readonly CustomerIntakeUploadChannels $channels,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', CustomerIntake::class);
        [$from, $to] = $this->globalDateRangeBounds();
        $open = array_map(static fn (IntakeStatus $status): string => $status->value, IntakeStatus::open());
        $status = IntakeStatus::tryFrom($request->string('status')->toString());
        $kind = IntakeKind::tryFrom($request->string('kind')->toString());

        $intakes = CustomerIntake::query()
            ->with(['customer:id,name', 'assignee:id,name', 'quote:id,status,number,version,valid_until'])
            ->where(fn ($query) => $query->whereIn('status', $open)->orWhereBetween('closed_at', [$from, $to]))
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind?->value))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_user_id', (int) $request->user()?->id))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('customer-intakes.index', [
            'intakes' => $intakes,
            'stages' => $this->stages,
            'openCount' => CustomerIntake::query()->whereIn('status', $open)->count(),
            'awaitingCount' => CustomerIntake::query()->where('status', IntakeStatus::AwaitingCustomer->value)->count(),
        ]);
    }

    public function show(Request $request, CustomerIntake $intake): View {
        Gate::authorize('view', $intake);
        $intake->load(['customer', 'submitter', 'assignee', 'handoverUser', 'quote.items', 'messages.author', 'asset', 'requestItem', 'journal.actor']);
        $viewer = $this->actor($request);

        $adapter = $this->handover->targetFor($intake->kind);
        $target = $intake->target_id !== null ? $intake->target()->withoutGlobalScopes()->first() : null;
        $quote = $intake->quote;

        return view('customer-intakes.show', [
            'intake' => $intake,
            'stage' => $this->stages->for($intake),
            'files' => $intake->attachments()->with('uploader:id,name,customer_id')->orderBy('created_at')->get(),
            'target' => $target,
            'targetLabel' => $target !== null ? $adapter?->targetLabel($target) : null,
            'targetUrl' => $target !== null ? $adapter?->internalUrl($target, $viewer) : null,
            'targetPanel' => $target !== null ? $adapter?->internalPanelView() : null,
            'blockers' => $intake->status->isOpen() ? $this->handover->blockers($intake, $viewer) : [],
            'quotesAvailable' => $this->quotes->available(),
            'quoteSuperseded' => $quote !== null && $this->quotes->isSuperseded($quote),
            'quoteDecidable' => $quote !== null && $this->quotes->decidable($quote),
            'uploadLink' => $this->channels->latestLink($intake),
        ]);
    }

    /** Upload-Link sofort abholen (MVP-1078). */
    public function syncUploadLink(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $link = $this->channels->latestLink($intake);
        abort_if($link === null || $link->revoked_at !== null, 404);
        $result = $this->channels->sync($link, $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with(
            $result['failed'] ? 'error' : 'status',
            $result['failed'] ? __('customer_intake.cloud.flash.failed') : __('customer_intake.cloud.flash.synced', ['imported' => $result['imported'], 'rejected' => $result['rejected']]),
        );
    }

    public function revokeUploadLink(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $link = $this->channels->latestLink($intake);
        abort_if($link === null || $link->revoked_at !== null, 404);
        $this->channels->revoke($link, $this->actor($request), 'manual');

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.cloud.flash.revoked'));
    }

    /** Interner Download — auch interne Notizdateien; Anhang muss zum Eingang gehören. */
    public function download(CustomerIntake $intake, Attachment $attachment): BinaryFileResponse {
        Gate::authorize('view', $intake);
        abort_unless(
            $attachment->attachable_type === $intake->getMorphClass() && (int) $attachment->attachable_id === (int) $intake->getKey(),
            404,
        );
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public function assignForm(CustomerIntake $intake): View {
        Gate::authorize('update', $intake);

        return view('customer-intakes._assign_dialog', [
            'intake' => $intake,
            'users' => User::query()->where('organization_id', $intake->organization_id)->whereNull('customer_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function assign(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $request->merge(['assigned_user_id' => Sqid::decode(User::class, (string) $request->input('assigned_user_id', ''))]);
        $data = $request->validate(['assigned_user_id' => ['nullable', 'integer']]);
        $assignee = isset($data['assigned_user_id'])
            ? User::query()->where('organization_id', $intake->organization_id)->whereNull('customer_id')->findOrFail((int) $data['assigned_user_id'])
            : null;

        $this->intakes->assign($intake, $assignee, $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.flash.assigned'));
    }

    public function messageForm(Request $request, CustomerIntake $intake): View {
        Gate::authorize('update', $intake);

        return view('customer-intakes._message_dialog', [
            'intake' => $intake,
            'kind' => $request->query('kind') === 'note' ? 'note' : 'question',
        ]);
    }

    /** Rückfrage (an den Kunden, Dateien kundensichtbar) oder interne Notiz (Dateien intern). */
    public function message(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $data = $request->validate([
            'kind' => ['required', 'in:question,note'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $purpose = $intake->kind->uploadPurpose();
        $files = $this->validatedUploads($request, $purpose->maxFiles(), $purpose, 'uploads');
        $actor = $this->actor($request);

        if ($data['kind'] === 'question') {
            $this->intakes->ask($intake, $actor, (string) $data['body'], $files);
            $flash = __('customer_intake.flash.question_sent');
        } else {
            $this->intakes->note($intake, $actor, (string) $data['body'], $files);
            $flash = __('customer_intake.flash.note_added');
        }

        return redirect()->route('customer-intakes.show', $intake)->with('status', $flash);
    }

    public function rejectForm(CustomerIntake $intake): View {
        Gate::authorize('update', $intake);

        return view('customer-intakes._reject_dialog', ['intake' => $intake]);
    }

    public function reject(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->intakes->reject($intake, (string) $data['reason'], $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.flash.rejected'));
    }

    public function quoteForm(CustomerIntake $intake): View {
        Gate::authorize('update', $intake);
        abort_unless($this->quotes->available(), 404);

        $taken = CustomerIntake::query()->whereNotNull('quote_id')->whereKeyNot($intake->id)->pluck('quote_id');

        return view('customer-intakes._quote_dialog', [
            'intake' => $intake,
            'quotes' => Quote::query()
                ->where('customer_id', $intake->customer_id)
                ->whereNotIn('status', [QuoteStatus::Accepted->value, QuoteStatus::PartiallyAccepted->value])
                ->whereNotIn('id', $taken)
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'number', 'version', 'status', 'total', 'valid_until']),
        ]);
    }

    public function linkQuote(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $request->merge(['quote_id' => Sqid::decode(Quote::class, (string) $request->input('quote_id', ''))]);
        $data = $request->validate(['quote_id' => ['required', 'integer']]);
        $quote = Quote::query()->findOrFail((int) $data['quote_id']);

        $this->quotes->link($intake, $quote, $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.flash.quote_linked'));
    }

    /** Neues Angebot für den Kunden anlegen und verknüpfen — Positionen folgen am Angebot. */
    public function createQuote(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        Gate::authorize('create', Quote::class);
        $quote = $this->quotes->create($intake, $this->actor($request));

        return redirect()->route('quotes.show', $quote)->with('status', __('customer_intake.flash.quote_created', ['number' => $intake->number]));
    }

    public function announceQuote(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $this->quotes->announce($intake, $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.flash.quote_announced'));
    }

    public function unlinkQuote(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $this->quotes->unlink($intake, $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)->with('status', __('customer_intake.flash.quote_unlinked'));
    }

    public function handoverForm(Request $request, CustomerIntake $intake): View {
        Gate::authorize('handover', $intake);
        $adapter = $this->handover->targetFor($intake->kind);

        return view('customer-intakes._handover_dialog', [
            'intake' => $intake->loadMissing('quote'),
            'blockers' => $this->handover->blockers($intake, $this->actor($request)),
            'needsScopeNote' => $this->handover->needsScopeNote($intake),
            'formView' => $adapter?->formView(),
            'formData' => $adapter?->formData($intake) ?? [],
        ]);
    }

    public function handover(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('handover', $intake);
        $adapter = $this->handover->targetFor($intake->kind)
            ?? throw ValidationException::withMessages(['handover' => (string) __('customer_intake.handover.blocked.target_unavailable')]);
        $input = $request->validate([
            ...$adapter->rules(),
            'scope_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $scopeNote = $input['scope_note'] ?? null;
        unset($input['scope_note']);

        $target = $this->handover->handOver($intake, $this->actor($request), $input, $scopeNote);

        return redirect()->route('customer-intakes.show', $intake)
            ->with('status', __('customer_intake.flash.handed_over', ['target' => $adapter->targetLabel($target)]));
    }

    public function uploadChannel(Request $request, CustomerIntake $intake): RedirectResponse {
        Gate::authorize('update', $intake);
        $data = $request->validate(['open' => ['required', 'boolean']]);
        $this->intakes->setUploadChannel($intake, (bool) $data['open'], $this->actor($request));

        return redirect()->route('customer-intakes.show', $intake)
            ->with('status', __($data['open'] ? 'customer_intake.flash.channel_opened' : 'customer_intake.flash.channel_closed'));
    }

    private function actor(Request $request): User {
        return $request->user() ?? abort(401);
    }
}
