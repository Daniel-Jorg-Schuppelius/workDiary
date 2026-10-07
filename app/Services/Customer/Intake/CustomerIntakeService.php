<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Customer\{IntakeKind, IntakeMessageKind, IntakeStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Numbering\NumberScope;
use App\Models\Asset\Asset;
use App\Models\Customer\{CustomerIntake, CustomerIntakeMessage};
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Services\Attachments\FileAttacher;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Fields\FieldDocument;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle des Kundeneingangs (MVP-1074/1075): Einreichung,
 * Dateien, Rückfragen und Statuswechsel. Eine Einreichung wird erst mit dem
 * Commit sichtbar; scheitert sie, werden bereits abgelegte Dateien wieder
 * gelöscht. Derselbe `submission_key` liefert immer denselben Eingang.
 */
class CustomerIntakeService {
    use AssertsValidatedTransition;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly FileAttacher $attacher,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    /**
     * @param  array{kind: IntakeKind, subject: string, description: ?string, desired_date: ?string, submission_key: string}  $data
     * @param  list<UploadedFile>  $files
     * @param  array<string, UploadedFile>  $catalogFiles  Upload-Felder der Katalogvorlage je Feld-Key
     */
    public function submit(User $portalUser, array $data, FieldDocument $form, array $files = [], ?RequestItem $item = null, ?FieldDocument $catalogForm = null, array $catalogFiles = [], ?Asset $asset = null): CustomerIntake {
        $existing = $this->bySubmissionKey($portalUser, $data['submission_key']);
        if ($existing !== null) {
            return $existing;
        }

        $kind = $data['kind'];
        $stored = [];
        try {
            $intake = DB::transaction(function () use ($portalUser, $data, $kind, $form, $files, $item, $catalogForm, $catalogFiles, $asset, &$stored): CustomerIntake {
                $intake = CustomerIntake::query()->create([
                    'organization_id' => (int) $portalUser->organization_id,
                    'customer_id' => (int) $portalUser->customer_id,
                    'submitted_by_user_id' => (int) $portalUser->id,
                    'number' => $this->numbers->next((int) $portalUser->organization_id, NumberScope::CustomerIntake, now()),
                    'kind' => $kind,
                    'status' => IntakeStatus::Submitted,
                    'subject' => trim($data['subject']),
                    'description' => trim((string) $data['description']) ?: null,
                    'desired_date' => $data['desired_date'] ?: null,
                    'form' => $form,
                    'catalog_form' => $catalogForm,
                    'request_item_id' => $item?->id,
                    'asset_id' => $asset?->id,
                    'submission_key' => $data['submission_key'],
                ]);

                $names = $this->storeFiles($intake, $files, $portalUser, true, $stored);
                foreach ($catalogFiles as $key => $file) {
                    $attachment = $this->attacher->store($intake, $file, (int) $portalUser->id, [
                        'organization_id' => $intake->organization_id,
                        'customer_visible' => true,
                        'meta_type' => 'field:' . $key,
                    ], 'customer-intakes', $kind->uploadPurpose());
                    $stored[] = [$attachment->disk, $attachment->path];
                    $names[] = $attachment->original_name;
                }

                $intake->record('submitted', ['kind' => $kind->value, 'files' => $names], $portalUser);

                return $intake;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Parallel doppelt abgeschickt: der andere Request hat den Eingang angelegt.
            $this->deleteStored($stored);
            return $this->bySubmissionKey($portalUser, $data['submission_key']) ?? throw $e;
        } catch (\Throwable $e) {
            $this->deleteStored($stored);
            throw $e;
        }

        $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::SUBMITTED);
        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeSubmitted, 'submitted_message');

        return $intake;
    }

    /**
     * Nachreichung des Kunden an einen dafür freigegebenen Eingang (MVP-1074):
     * neue Anhänge, nie Ersatz eines bestehenden Stands.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addCustomerFiles(CustomerIntake $intake, User $portalUser, array $files): void {
        if (! $intake->acceptsCustomerFiles()) {
            throw ValidationException::withMessages(['uploads' => (string) __('customer_intake.error.uploads_closed')]);
        }
        if ($files === []) {
            throw ValidationException::withMessages(['uploads' => (string) __('customer_intake.error.no_files')]);
        }

        $stored = [];
        try {
            DB::transaction(function () use ($intake, $portalUser, $files, &$stored): void {
                $names = $this->storeFiles($intake, $files, $portalUser, true, $stored);
                $intake->record('files_added', ['files' => $names, 'by_customer' => true], $portalUser);
                $this->resumeAfterCustomer($intake);
            });
        } catch (\Throwable $e) {
            $this->deleteStored($stored);
            throw $e;
        }

        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, 'files_message', ['count' => (string) count($files)]);
    }

    /** @param  list<UploadedFile>  $files */
    public function reply(CustomerIntake $intake, User $portalUser, string $body, array $files = []): CustomerIntakeMessage {
        if (! $intake->status->isOpen()) {
            throw ValidationException::withMessages(['body' => (string) __('customer_intake.error.closed')]);
        }

        $message = $this->writeMessage($intake, $portalUser, IntakeMessageKind::Reply, $body, $files, 'customer_replied');
        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, 'reply_message');

        return $message;
    }

    /**
     * Rückfrage an den Kunden: Eingang wartet auf Rückmeldung, der Kunde
     * erhält eine Mail; Dateien der Rückfrage sind kundensichtbar.
     *
     * @param  list<UploadedFile>  $files
     */
    public function ask(CustomerIntake $intake, User $actor, string $body, array $files = []): CustomerIntakeMessage {
        if ($intake->status !== IntakeStatus::AwaitingCustomer) {
            $this->assertValidatedTransition($intake->status, IntakeStatus::AwaitingCustomer, 'customer_intake.error.invalid_transition');
        }

        $message = $this->writeMessage($intake, $actor, IntakeMessageKind::Question, $body, $files, 'question_asked', IntakeStatus::AwaitingCustomer);
        $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::QUESTION);

        return $message;
    }

    /** @param  list<UploadedFile>  $files */
    public function note(CustomerIntake $intake, User $actor, string $body, array $files = []): CustomerIntakeMessage {
        return $this->writeMessage($intake, $actor, IntakeMessageKind::Note, $body, $files, 'note_added');
    }

    public function assign(CustomerIntake $intake, ?User $assignee, User $actor): CustomerIntake {
        if ($assignee !== null && (int) $assignee->organization_id !== (int) $intake->organization_id) {
            throw ValidationException::withMessages(['assigned_user_id' => (string) __('customer_intake.error.assignee_foreign')]);
        }

        return DB::transaction(function () use ($intake, $assignee, $actor): CustomerIntake {
            $intake->forceFill(['assigned_user_id' => $assignee?->id]);
            if ($intake->status === IntakeStatus::Submitted) {
                $intake->status = IntakeStatus::InProgress;
            }
            $intake->save();
            $intake->record('assigned', ['assigned_user_id' => $assignee?->id, 'name' => $assignee?->name], $actor);

            return $intake;
        });
    }

    /** Ablehnung mit kundenverständlicher Begründung — nicht nach angenommenem Angebot. */
    public function reject(CustomerIntake $intake, string $reason, User $actor): CustomerIntake {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => (string) __('customer_intake.error.reason_required')]);
        }
        if ($intake->quote?->status->isWon() ?? false) {
            throw ValidationException::withMessages(['reason' => (string) __('customer_intake.error.quote_already_accepted')]);
        }
        $this->assertValidatedTransition($intake->status, IntakeStatus::Rejected, 'customer_intake.error.invalid_transition');

        DB::transaction(function () use ($intake, $reason, $actor): void {
            $intake->forceFill([
                'status' => IntakeStatus::Rejected,
                'rejection_reason' => $reason,
                'closed_at' => now(),
            ])->save();
            $intake->record('rejected', ['reason' => $reason], $actor);
        });
        $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::REJECTED);

        return $intake;
    }

    /** Rücknahme durch den Kunden: protokollierte Statusänderung, kein Löschen. */
    public function withdraw(CustomerIntake $intake, User $portalUser): CustomerIntake {
        if (! $intake->canBeWithdrawn()) {
            throw ValidationException::withMessages(['status' => (string) __('customer_intake.error.withdraw_not_possible')]);
        }

        DB::transaction(function () use ($intake, $portalUser): void {
            $intake->forceFill(['status' => IntakeStatus::Withdrawn, 'closed_at' => now()])->save();
            $intake->record('withdrawn', [], $portalUser);
        });
        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, 'withdrawn_message');

        return $intake;
    }

    /**
     * Nachreichkanal nach der Übernahme ausdrücklich öffnen oder schließen
     * (MVP-1074). Offene Eingänge nehmen ohnehin Dateien an.
     */
    public function setUploadChannel(CustomerIntake $intake, bool $open, User $actor): CustomerIntake {
        if ($intake->status !== IntakeStatus::HandedOver) {
            throw ValidationException::withMessages(['status' => (string) __('customer_intake.error.channel_only_after_handover')]);
        }
        if ($intake->is_upload_open === $open) {
            return $intake;
        }

        DB::transaction(function () use ($intake, $open, $actor): void {
            $intake->forceFill(['is_upload_open' => $open])->save();
            $intake->record($open ? 'upload_channel_opened' : 'upload_channel_closed', [], $actor);
        });

        return $intake;
    }

    /** Antwort, Nachreichung oder Angebotsentscheidung des Kunden nimmt die Bearbeitung wieder auf. */
    public function resumeAfterCustomer(CustomerIntake $intake): void {
        if ($intake->status === IntakeStatus::AwaitingCustomer) {
            $intake->forceFill(['status' => IntakeStatus::InProgress])->save();
        }
    }

    private function bySubmissionKey(User $portalUser, string $key): ?CustomerIntake {
        return CustomerIntake::query()->ofPortalUser($portalUser)->where('submission_key', $key)->first();
    }

    /** @param  list<UploadedFile>  $files */
    private function writeMessage(CustomerIntake $intake, User $author, IntakeMessageKind $kind, string $body, array $files, string $event, ?IntakeStatus $status = null): CustomerIntakeMessage {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => (string) __('customer_intake.error.body_required')]);
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($intake, $author, $kind, $body, $files, $event, $status, &$stored): CustomerIntakeMessage {
                /** @var CustomerIntakeMessage $message */
                $message = $intake->messages()->create([
                    'organization_id' => $intake->organization_id,
                    'author_user_id' => $author->id,
                    'kind' => $kind,
                    'body' => $body,
                ]);
                $names = $this->storeFiles($intake, $files, $author, $kind->isCustomerVisible(), $stored);

                if ($status !== null && $intake->status !== $status) {
                    $intake->forceFill(['status' => $status])->save();
                } elseif ($kind === IntakeMessageKind::Reply) {
                    $this->resumeAfterCustomer($intake);
                }
                $intake->record($event, ['message_id' => $message->id, 'files' => $names], $author);

                return $message;
            });
        } catch (\Throwable $e) {
            $this->deleteStored($stored);
            throw $e;
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<array{0: string, 1: string}>  $stored  abgelegte Dateien für die Bereinigung
     * @return list<string> Anzeigenamen
     */
    private function storeFiles(CustomerIntake $intake, array $files, User $uploader, bool $customerVisible, array &$stored): array {
        $names = [];
        foreach ($files as $file) {
            $attachment = $this->attacher->store($intake, $file, (int) $uploader->id, [
                'organization_id' => $intake->organization_id,
                'customer_visible' => $customerVisible,
            ], 'customer-intakes', $intake->kind->uploadPurpose());
            $stored[] = [$attachment->disk, $attachment->path];
            $names[] = $attachment->original_name;
        }

        return $names;
    }

    /** @param  list<array{0: string, 1: string}>  $stored */
    private function deleteStored(array $stored): void {
        foreach ($stored as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
