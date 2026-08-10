<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQueryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Customer;

use App\Enums\Customer\CustomerQueryStatus;
use App\Enums\Notification\NotificationEvent;
use App\Mail\CustomerQueryAnsweredMail;
use App\Models\{CustomerQuery, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Mail};
use InvalidArgumentException;

/**
 * Domain-Service für Kunden-Rückfragen (Feature 012).
 *
 * Erfasst Rückfragen, die ein Kunde über das Portal bzw. den Signaturlink zu
 * einem vorgelegten Vorgang stellt, benachrichtigt die zuständige Rolle und
 * verwaltet die interne Beantwortung. Schreibende Operationen laufen
 * ausschließlich hierüber, damit Status, Audit-Trail und Benachrichtigung
 * konsistent bleiben.
 */
class CustomerQueryService {
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /**
     * Erfasst eine neue Rückfrage des Kunden und benachrichtigt die Org.
     *
     * @param  array{question?: string|null, organization_id: int, customer_id?: int|null, signature_token_id?: int|null, asker_name?: string|null, asker_email?: string|null}  $data
     */
    public function raise(Model $subject, array $data): CustomerQuery {
        $question = trim((string) ($data['question'] ?? ''));
        if ($question === '') {
            throw new InvalidArgumentException('Die Rückfrage darf nicht leer sein.');
        }

        $query = CustomerQuery::query()->create([
            'organization_id' => $data['organization_id'],
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'customer_id' => $data['customer_id'] ?? null,
            'signature_token_id' => $data['signature_token_id'] ?? null,
            'asker_name' => $data['asker_name'] ?? null,
            'asker_email' => $data['asker_email'] ?? null,
            'question' => $question,
            'status' => CustomerQueryStatus::Open->value,
        ]);

        $this->notifyRaised($query, $subject);

        return $query;
    }

    /**
     * Beantwortet eine Rückfrage intern. Die Antwort wird dem Kunden über
     * denselben Kanal (Portal/Link) sichtbar.
     */
    public function answer(CustomerQuery $query, User $actor, string $answer): CustomerQuery {
        $answer = trim($answer);
        if ($answer === '') {
            throw new InvalidArgumentException('Die Antwort darf nicht leer sein.');
        }

        $query->update([
            'answer' => $answer,
            'status' => CustomerQueryStatus::Answered->value,
            'answered_at' => Carbon::now(),
            'answered_by_user_id' => $actor->id,
        ]);

        // Antwort-Benachrichtigung an den Fragesteller (MVP-512) — nur wenn
        // eine Adresse vorliegt (Portal-Konto bzw. Signaturlink mit E-Mail).
        $email = trim((string) $query->asker_email);
        if ($email !== '') {
            DB::afterCommit(function () use ($query, $email): void {
                Mail::to($email)->send(new CustomerQueryAnsweredMail($query));
            });
        }

        return $query->refresh();
    }

    public function close(CustomerQuery $query): CustomerQuery {
        $query->update(['status' => CustomerQueryStatus::Closed->value]);

        return $query->refresh();
    }

    private function notifyRaised(CustomerQuery $query, Model $subject): void {
        // Benachrichtigung erst nach Commit – darf die Erfassung nie blocken.
        DB::afterCommit(function () use ($query, $subject): void {
            $this->notifications->notify(
                NotificationEvent::CustomerQueryRaised,
                $query,
                null,
                [
                    'title' => (string) __('notification.message.customer_query_raised'),
                    'message' => \Illuminate\Support\Str::limit($query->question, 160),
                    'url' => \App\Support\NotificationLinks::subjectUrl($subject),
                ],
            );
        });
    }
}
