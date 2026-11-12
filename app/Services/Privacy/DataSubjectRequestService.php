<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Numbering\NumberScope;
use App\Enums\Privacy\{DataSubjectRequestStatus, DataSubjectRequestType};
use App\Mail\Privacy\DsarDeadlineExtensionMail;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Numbering\NumberSequenceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log, Mail, Storage};
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Workflow der Betroffenenanfragen: Anlegen (per-Fall-Krypto), Identitaetspruefung,
 * Zuweisung, Entscheidung und Crypto-Shredding nach Aufbewahrung. Jeder Schritt
 * schreibt ein Ereignis in die Hash-Kette ({@see PrivacyEventService}).
 */
class DataSubjectRequestService {
    use AssertsValidatedTransition;

    public function __construct(
        private readonly PrivacyEventService $events,
        private readonly NumberSequenceService $numbers,
    ) {}

    /**
     * Neue Anfrage anlegen: verschluesselt Identitaet/Anliegen, setzt die Frist (Art. 12).
     *
     * `$contactEmail` (Selbstmeldeportal, G11) wird mit demselben Fall-DEK
     * abgelegt und nimmt damit am Crypto-Shredding teil.
     */
    public function open(
        Organization $organization,
        DataSubjectRequestType $type,
        string $subject,
        string $content,
        ?string $channel = null,
        ?User $actor = null,
        ?string $contactEmail = null,
    ): DataSubjectRequest {
        return DB::transaction(function () use ($organization, $type, $subject, $content, $channel, $actor, $contactEmail): DataSubjectRequest {
            $now = Carbon::now();
            $days = (int) config('dataprotection.dsr_deadline_days', 30);

            $dsr = new DataSubjectRequest;
            $dsr->organization_id = $organization->id;
            $dsr->request_number = $this->nextNumber($organization, $now);
            $dsr->type = $type;
            $dsr->status = DataSubjectRequestStatus::Intake;
            $dsr->channel = $channel;
            $dsr->received_at = $now;
            $dsr->deadline_at = $now->copy()->addDays($days);
            $dsr->setAttribute('created_by', $actor?->id);
            $dsr->initializeDek();
            $dsr->subject_ciphertext = $subject;
            $dsr->content_ciphertext = $content;
            if ($contactEmail !== null && $contactEmail !== '') {
                $dsr->contact_email_ciphertext = $contactEmail;
            }
            $dsr->save();

            $this->events->record($dsr, 'opened', $actor, ['type' => $type->value, 'channel' => $channel]);

            return $dsr;
        });
    }

    public function verifyIdentity(DataSubjectRequest $request, ?User $actor = null): DataSubjectRequest {
        $request->forceFill([
            'identity_verified_at' => Carbon::now(),
            'status' => DataSubjectRequestStatus::InProgress,
        ])->save();
        $this->events->record($request, 'identity_verified', $actor);

        return $request;
    }

    /**
     * Bearbeitungsstand (MVP-1620): Identitätsnachweis angefordert, Rückfrage
     * offen, Antwort erhalten oder von der Person zurückgezogen. Die Frist
     * läuft weiter; eine Rücknahme schließt den Fall.
     */
    public function transition(DataSubjectRequest $request, DataSubjectRequestStatus $target, ?User $actor = null): DataSubjectRequest {
        return DB::transaction(function () use ($request, $target, $actor): DataSubjectRequest {
            /** @var DataSubjectRequest $locked */
            $locked = DataSubjectRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertValidatedTransition($locked->status, $target, 'dsar.internal.status_invalid');
            $from = $locked->status;
            $locked->forceFill([
                'status' => $target,
                'closed_at' => $target === DataSubjectRequestStatus::Withdrawn ? Carbon::now() : $locked->closed_at,
            ])->save();
            $this->events->record($locked, match ($target) {
                DataSubjectRequestStatus::IdentityCheck => 'identity_check_started',
                DataSubjectRequestStatus::AwaitingInfo => 'info_requested',
                DataSubjectRequestStatus::Withdrawn => 'withdrawn',
                default => 'resumed',
            }, $actor, ['from' => $from->value]);

            return $locked;
        });
    }

    public function assign(DataSubjectRequest $request, User $assignee, ?User $actor = null): DataSubjectRequest {
        $request->forceFill(['assigned_user_id' => $assignee->id])->save();
        $this->events->record($request, 'assigned', $actor, ['assignee_id' => $assignee->id]);

        return $request;
    }

    /** Entscheidung dokumentieren und Fall abschliessen. */
    public function decide(
        DataSubjectRequest $request,
        string $decision,
        string $note,
        ?User $actor = null,
    ): DataSubjectRequest {
        $status = $decision === 'rejected'
            ? DataSubjectRequestStatus::Rejected
            : DataSubjectRequestStatus::Completed;

        $now = Carbon::now();
        $request->decision = $decision;
        $request->decision_note_ciphertext = $note; // verschluesselt via Cast
        $request->forceFill([
            'status' => $status,
            'decided_at' => $now,
            'closed_at' => $now,
        ]);
        $request->save();
        $this->events->record($request, 'decided', $actor, ['decision' => $decision]);

        return $request;
    }

    /**
     * Fristverlängerung nach Art. 12 Abs. 3 DSGVO: um höchstens zwei weitere
     * Monate, mit Grund, und nur solange die erste Frist läuft – innerhalb
     * dieser Frist muss die Person davon erfahren. Hat die Anfrage eine
     * Kontaktadresse, geht die Mitteilung von hier aus.
     */
    public function extendDeadline(DataSubjectRequest $request, CarbonInterface $deadline, string $reason, ?User $actor = null): DataSubjectRequest {
        $current = $request->deadline_at;
        $first = $request->original_deadline_at ?? $current;
        $deadline = Carbon::instance($deadline)->endOfDay();

        if (! $request->status->isOpen() || $current === null || $first === null) {
            throw ValidationException::withMessages(['deadline_at' => __('privacy.dsr_extension.closed')]);
        }
        if ($first->isPast()) {
            throw ValidationException::withMessages(['deadline_at' => __('privacy.dsr_extension.first_deadline_passed')]);
        }
        if ($deadline->lessThanOrEqualTo($current->copy()->endOfDay())) {
            throw ValidationException::withMessages(['deadline_at' => __('privacy.dsr_extension.not_later')]);
        }
        $latest = $request->latestPossibleDeadline()?->endOfDay();
        if ($latest !== null && $deadline->greaterThan($latest)) {
            throw ValidationException::withMessages(['deadline_at' => __('privacy.dsr_extension.too_late', ['date' => $latest->format('d.m.Y')])]);
        }

        DB::transaction(function () use ($request, $deadline, $reason, $actor, $first): void {
            $request->deadline_extension_reason_ciphertext = $reason;
            $request->forceFill([
                'original_deadline_at' => $first,
                'deadline_at' => $deadline,
                'deadline_extended_at' => Carbon::now(),
            ])->save();
            // Der Grund ist Inhalt und steht verschlüsselt am Fall, nie im Ereignis.
            $this->events->record($request, 'deadline_extended', $actor, [
                'original_deadline_at' => $first->toIso8601String(),
                'deadline_at' => $deadline->toIso8601String(),
            ]);
        });

        $this->notifyExtension($request, $reason);

        return $request;
    }

    /** Mitteilung an die Person; ein Mailfehler nimmt die dokumentierte Verlängerung nicht zurück. */
    private function notifyExtension(DataSubjectRequest $request, string $reason): void {
        $email = trim((string) $request->contact_email_ciphertext);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new DsarDeadlineExtensionMail(
                requestNumber: (string) $request->request_number,
                organizationName: (string) $request->organization?->name,
                deadlineDate: $request->deadline_at?->format('d.m.Y') ?? '',
                reason: $reason,
            ));
            $this->events->record($request, 'deadline_extension_notified');
        } catch (Throwable $e) {
            Log::warning('DSAR-Fristverlängerung konnte nicht mitgeteilt werden.', [
                'request_id' => $request->id,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
            $this->events->record($request, 'deadline_extension_notice_failed');
        }
    }

    /**
     * Crypto-Shredding nach Ablauf der Aufbewahrung – Inhalte unwiederbringlich.
     * Unverschlüsselt abgelegte Nachweise verschwinden mit; die mit dem
     * Fall-Schlüssel verschlüsselten Auskunftspakete bleiben als unlesbarer Rest.
     */
    public function shred(DataSubjectRequest $request, ?User $actor = null): DataSubjectRequest {
        foreach ($request->attachments()->get() as $attachment) {
            if (str_starts_with((string) $attachment->path, SubjectDataExporter::STORAGE_PREFIX)) {
                continue;
            }
            Storage::disk('local')->delete((string) $attachment->path);
            $attachment->delete();
        }

        $request->shredDek();
        $this->events->record($request, 'shredded', $actor);

        return $request;
    }

    private function nextNumber(Organization $organization, Carbon $now): string {
        // W1.1: zentraler Nummernkreis (lockForUpdate, reset_per_year) statt
        // race-anfälligem count()+1 — Format bleibt DSR-<Jahr>-0001.
        return $this->numbers->next($organization, NumberScope::DataSubjectRequest, $now);
    }
}
