<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Wishes;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Wishes\{AppWish, AppWishVote};
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Notification\NotificationDispatcher;
use App\Support\Tz;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Route};
use Illuminate\Validation\ValidationException;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659): Anlegen mit Seite und
 * Tagesgrenze je Person, Stimmen (eine je Person), Status und Antwort der
 * Organisation, Weitergabe an den Plattformbetrieb ohne Personenbezug und
 * Status, Antwort und Bündel des Betriebs. Den Herstellerkanal (MVP-1677)
 * führt {@see VendorChannel}.
 *
 * Datenschutz der Weitergabe: der Betrieb sieht nur den bereinigten Text
 * (`forwarded_body`), Art, Seite, Stimmenzahl und die Organisation — nie den
 * Ursprungstext, die verfassende Person oder Abstimmende.
 */
final class AppWishService {
    use AssertsValidatedTransition;

    /** Höchstens so viele neue Wünsche je Person und Kalendertag (Ortszeit). */
    public const int DAILY_LIMIT = 30;

    public const int MAX_BODY = 5000;

    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /**
     * Neuer Wunsch der Person in ihrer Organisation. Die Seite ist ein
     * Routenname der App; ein unbekannter Name wird verworfen.
     *
     * @throws ValidationException bei überschrittener Tagesgrenze
     */
    public function create(User $author, Organization $organization, AppWishKind $kind, string $body, ?string $routeName): AppWish {
        $wish = DB::transaction(function () use ($author, $organization, $kind, $body, $routeName): AppWish {
            // Je Person serialisieren, damit parallele Anfragen die Tagesgrenze nicht gemeinsam überschreiten.
            User::query()->whereKey($author->id)->lockForUpdate()->first();
            if ($this->createdToday($author) >= self::DAILY_LIMIT) {
                throw ValidationException::withMessages(['body' => __('wishes.validation.daily_limit', ['limit' => self::DAILY_LIMIT])]);
            }

            return AppWish::query()->create([
                'organization_id' => (int) $organization->id,
                'kind' => $kind,
                'body' => $body,
                'route_name' => $this->routeName($routeName),
                'status' => AppWishStatus::New,
                'app_version' => StringHelper::trimToNull((string) config('app.version')),
                'created_by' => (int) $author->id,
            ]);
        });

        // Hinweis an die Bearbeitenden (Rollen der Regel), ohne Namen der verfassenden Person.
        $this->notifications->notify(NotificationEvent::WishSubmitted, $wish, null, [
            'title' => (string) __('wishes.notification.submitted_title', ['kind' => $kind->label()]),
            'title_key' => 'wishes.notification.submitted_title',
            'title_params' => ['kind' => $kind->label()],
            'message' => StringHelper::truncate($body, 140),
            'url' => route('wishes.index', ['wish' => $wish->sqid]),
        ]);

        return $wish;
    }

    /** Wünsche der Person seit Beginn des heutigen Tages (Ortszeit). */
    public function createdToday(User $author): int {
        return AppWish::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('created_by', $author->id)
            ->where('created_at', '>=', Tz::startOfDay()->setTimezone((string) config('app.timezone')))
            ->count();
    }

    /** Text und Art ändern — nur die verfassende Person, solange der Wunsch neu ist (Policy). */
    public function update(AppWish $wish, AppWishKind $kind, string $body): AppWish {
        return DB::transaction(function () use ($wish, $kind, $body): AppWish {
            $locked = $this->lock($wish);
            if (! $locked->isEditableByAuthor()) {
                throw ValidationException::withMessages(['body' => __('wishes.validation.not_editable')]);
            }
            $locked->update(['kind' => $kind, 'body' => $body]);

            return $locked;
        });
    }

    /** Stimme abgeben; eine zweite Stimme derselben Person zählt nicht. */
    public function vote(AppWish $wish, User $voter): bool {
        $vote = AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            ['app_wish_id' => $wish->id, 'user_id' => $voter->id],
            ['organization_id' => (int) $wish->organization_id],
        );

        return $vote->wasRecentlyCreated;
    }

    public function unvote(AppWish $wish, User $voter): void {
        AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('app_wish_id', $wish->id)
            ->where('user_id', $voter->id)
            ->delete();
    }

    /**
     * Status und Antwort der Organisation. Eine neue oder geänderte Antwort
     * geht als Hinweis an Verfassende und Abstimmende.
     */
    public function answer(AppWish $wish, User $actor, AppWishStatus $status, ?string $answer): AppWish {
        $answer = StringHelper::trimToNull($answer);
        [$locked, $answerChanged] = DB::transaction(function () use ($wish, $actor, $status, $answer): array {
            $locked = AppWish::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $status) {
                $this->assertValidatedTransition($locked->status, $status, 'wishes.validation.invalid_transition');
            }
            $answerChanged = $answer !== null && $answer !== $locked->answer;
            $locked->update([
                'status' => $status,
                'answer' => $answer,
                'answered_at' => $answerChanged ? now() : ($answer === null ? null : $locked->answered_at),
                'answered_by' => $answerChanged ? (int) $actor->id : ($answer === null ? null : $locked->answered_by),
            ]);

            return [$locked, $answerChanged];
        });

        if ($answerChanged) {
            $this->notifyAnswered($locked, 'organization');
        }

        return $locked;
    }

    /**
     * Weitergabe an den Plattformbetrieb (MVP-1659): einmal je Wunsch, mit dem
     * vor dem Senden bereinigten Text. Name und Ursprungstext bleiben in der
     * Organisation.
     */
    public function forward(AppWish $wish, User $actor, string $cleanedBody): AppWish {
        return DB::transaction(function () use ($wish, $actor, $cleanedBody): AppWish {
            $locked = $this->lock($wish);
            if ($locked->isForwarded()) {
                throw ValidationException::withMessages(['forwarded_body' => __('wishes.validation.already_forwarded')]);
            }
            $locked->update([
                'forwarded_at' => now(),
                'forwarded_by' => (int) $actor->id,
                'forwarded_body' => $cleanedBody,
            ]);
            $locked->audit('app_wish.forwarded');

            return $locked;
        });
    }

    // ── Plattformsicht (MVP-1659) ─────────────────────────────────────────────

    /**
     * Weitergegebene Wünsche aller Organisationen — ohne Organisations-Scope,
     * nur für Plattform-Betreiber (Controller).
     *
     * @return Builder<AppWish>
     */
    public function forwardedQuery(): Builder {
        return AppWish::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('forwarded_at');
    }

    public function findForwarded(string $sqid): AppWish {
        $wish = $this->forwardedQuery()->whereKey(\App\Support\Sqid::decode(AppWish::class, $sqid))->first();
        abort_if(! $wish instanceof AppWish, 404);

        return $wish;
    }

    /** Stimmen eines Bündels: führender Wunsch und alle gebündelten (organisationsübergreifend). */
    public function bundleVotes(AppWish $lead): int {
        $ids = [$lead->id, ...$this->forwardedQuery()->where('platform_bundle_id', $lead->id)->pluck('id')->map(static fn ($id): int => (int) $id)->all()];

        return AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)->whereIn('app_wish_id', $ids)->count();
    }

    /**
     * Gleichartige Wünsche bündeln: `$wish` folgt `$lead` (null löst das
     * Bündel). Der gebündelte Wunsch übernimmt Status und Antwort des Betriebs
     * vom führenden; ein führender Wunsch mit eigenem Bündel lässt sich nicht
     * unterordnen.
     */
    public function bundle(AppWish $wish, ?AppWish $lead): AppWish {
        return DB::transaction(function () use ($wish, $lead): AppWish {
            $locked = $this->lockForwarded($wish);
            if ($lead === null) {
                $locked->update(['platform_bundle_id' => null]);

                return $locked;
            }
            $lead = $this->lockForwarded($lead);
            if ((int) $lead->id === (int) $locked->id || $lead->platform_bundle_id !== null
                || $this->forwardedQuery()->where('platform_bundle_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['lead' => __('wishes.validation.bundle_invalid')]);
            }
            $answerChanged = $lead->platform_answer !== null && $lead->platform_answer !== $locked->platform_answer;
            $locked->update([
                'platform_bundle_id' => $lead->id,
                'platform_status' => $lead->platform_status,
                'platform_answer' => $lead->platform_answer,
                'platform_answered_at' => $lead->platform_answered_at,
            ]);
            if ($answerChanged) {
                $this->notifyAnswered($locked, 'platform');
            }

            return $locked;
        });
    }

    /**
     * Status und Antwort des Betriebs — gelten für das ganze Bündel und
     * erscheinen im Wunsch der jeweiligen Organisation.
     */
    public function platformAnswer(AppWish $wish, ?AppWishStatus $status, ?string $answer): AppWish {
        $answer = StringHelper::trimToNull($answer);
        [$lead, $notify] = DB::transaction(function () use ($wish, $status, $answer): array {
            $lead = $this->lockForwarded($wish);
            if ($lead->platform_bundle_id !== null) {
                // Ein gebündelter Wunsch folgt dem führenden; beantwortet wird dort.
                throw ValidationException::withMessages(['platform_status' => __('wishes.validation.answer_at_lead')]);
            }
            $current = $lead->platform_status ?? AppWishStatus::New;
            if ($status !== null && $status !== $current) {
                $this->assertValidatedTransition($current, $status, 'wishes.validation.invalid_transition');
            }
            $answerChanged = $answer !== null && $answer !== $lead->platform_answer;
            $values = [
                'platform_status' => $status,
                'platform_answer' => $answer,
                'platform_answered_at' => $answerChanged ? now() : ($answer === null ? null : $lead->platform_answered_at),
            ];
            $notify = [];
            foreach ([$lead, ...$this->forwardedQuery()->where('platform_bundle_id', $lead->id)->lockForUpdate()->get()->all()] as $member) {
                $member->update($values);
                if ($answerChanged) {
                    $notify[] = $member;
                }
            }

            return [$lead, $notify];
        });
        foreach ($notify as $member) {
            $this->notifyAnswered($member, 'platform');
        }

        return $lead;
    }

    /**
     * Hinweis „Antwort auf Ihren Wunsch“ an Verfassende und Abstimmende der
     * Organisation des Wunsches. `$from`: organization, platform oder vendor.
     */
    public function notifyAnswered(AppWish $wish, string $from): void {
        $recipientIds = array_values(array_unique(array_filter([
            (int) $wish->created_by,
            ...AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)->where('app_wish_id', $wish->id)->pluck('user_id')->map(static fn ($id): int => (int) $id)->all(),
        ])));
        if ($recipientIds === []) {
            return;
        }
        $recipients = User::query()->where('organization_id', $wish->organization_id)->whereIn('id', $recipientIds)->get();
        $titleKey = 'wishes.notification.answered_title_' . $from;
        foreach ($recipients as $recipient) {
            $this->notifications->notify(NotificationEvent::WishAnswered, $wish, $recipient, [
                'title' => (string) __($titleKey),
                'title_key' => $titleKey,
                'message' => StringHelper::truncate($wish->body, 140),
                'url' => route('wishes.index', ['wish' => $wish->sqid]),
            ]);
        }
    }

    /** Nur ein Routenname der App, sonst nichts (keine freien Pfade oder Parameter). */
    private function routeName(?string $routeName): ?string {
        $routeName = StringHelper::trimToNull($routeName);
        if ($routeName === null || mb_strlen($routeName) > 150 || ! Route::has($routeName)) {
            return null;
        }

        return $routeName;
    }

    private function lock(AppWish $wish): AppWish {
        $locked = AppWish::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($wish->id)->lockForUpdate()->first();
        abort_if(! $locked instanceof AppWish, 404);

        return $locked;
    }

    private function lockForwarded(AppWish $wish): AppWish {
        $locked = $this->forwardedQuery()->whereKey($wish->id)->lockForUpdate()->first();
        abort_if(! $locked instanceof AppWish, 404);

        return $locked;
    }
}
