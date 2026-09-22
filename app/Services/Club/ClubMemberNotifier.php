<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubGuardianPermission, ClubNotificationStatus, ClubParticipationStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Club\{ClubEventParticipation, ClubMember, ClubNotification};
use App\Models\{Event, Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Notification\NotificationDispatcher;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Nachrichten an Vereinsmitglieder (Feature 159, MVP-845): Erinnerung,
 * Verschiebung, Absage und Nachrücken. Empfänger sind das verknüpfte
 * Benutzerkonto (über die Benachrichtigungsregeln der Organisation), sonst die
 * Mailadresse des Mitglieds, dazu Vertretungen mit Recht „Nachrichten
 * erhalten“. Jeder Anlass wird je Empfänger genau einmal zugestellt
 * (Zustellprotokoll club_notifications); Fehler bleiben dort sichtbar.
 */
class ClubMemberNotifier {
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** Erinnerung an einen angemeldeten Termin; der Fingerabdruck ist der Beginn — nach Verschiebung wird neu erinnert. */
    public function reminder(Event $event, ClubMember $member): int {
        $key = $this->key(ClubNotification::KIND_REMINDER, $event, $member, (string) $event->started_at->getTimestamp());

        return $this->send($member, $event, ClubNotification::KIND_REMINDER, $key, NotificationEvent::ClubEventReminder, 'club_event_reminder', [
            'name' => $member->fullName(),
            'title' => (string) $event->title,
            'date' => $this->when($event, $event->started_at),
        ], ['date' => $event->started_at->toIso8601String()]);
    }

    /** Verschiebung an alle aktiven Teilnahmen (angemeldet, wartend, eingeladen). */
    public function rescheduled(Event $event, CarbonInterface $previousStart): int {
        $sent = 0;
        foreach ($this->activeMembers($event) as $member) {
            $key = $this->key(ClubNotification::KIND_RESCHEDULED, $event, $member, $previousStart->getTimestamp() . '>' . $event->started_at->getTimestamp());
            $sent += $this->send($member, $event, ClubNotification::KIND_RESCHEDULED, $key, NotificationEvent::ClubEventRescheduled, 'club_event_rescheduled', [
                'name' => $member->fullName(),
                'title' => (string) $event->title,
                'date' => $this->when($event, $event->started_at),
                'previous' => $this->when($event, $previousStart),
            ], ['date' => $event->started_at->toIso8601String(), 'previous' => $previousStart->toIso8601String()]);
        }

        return $sent;
    }

    /** Absage an alle aktiven Teilnahmen. */
    public function cancelled(Event $event): int {
        $sent = 0;
        foreach ($this->activeMembers($event) as $member) {
            $key = $this->key(ClubNotification::KIND_CANCELLED, $event, $member, '');
            $sent += $this->send($member, $event, ClubNotification::KIND_CANCELLED, $key, NotificationEvent::ClubEventCancelled, 'club_event_cancelled', [
                'name' => $member->fullName(),
                'title' => (string) $event->title,
                'date' => $this->when($event, $event->started_at),
            ], ['date' => $event->started_at->toIso8601String()]);
        }

        return $sent;
    }

    /** Nachrücken von der Warteliste. */
    public function promoted(ClubEventParticipation $participation): int {
        $event = $participation->event;
        $member = $participation->member;
        if ($event === null || $member === null) {
            return 0;
        }
        $key = $this->key(ClubNotification::KIND_PROMOTED, $event, $member, (string) ($participation->promoted_at?->getTimestamp() ?? ''));

        return $this->send($member, $event, ClubNotification::KIND_PROMOTED, $key, NotificationEvent::ClubWaitlistPromoted, 'club_waitlist_promoted', [
            'name' => $member->fullName(),
            'title' => (string) $event->title,
            'date' => $this->when($event, $event->started_at),
        ], ['date' => $event->started_at->toIso8601String()]);
    }

    /**
     * Empfänger eines Mitglieds: Konto oder Mailadresse des Mitglieds, dazu
     * aktive Vertretungen mit Recht „Nachrichten erhalten“ — jede Adresse einmal.
     *
     * @return list<array{recipient: string, user: User|null, email: string|null}>
     */
    public function recipientsFor(ClubMember $member): array {
        $recipients = [];
        $add = static function (?User $user, ?string $email) use (&$recipients): void {
            if ($user !== null) {
                $recipients['user:' . $user->id] = ['recipient' => 'user:' . $user->id, 'user' => $user, 'email' => null];
            } elseif ($email !== null && $email !== '') {
                $key = 'mail:' . mb_strtolower($email);
                $recipients[$key] = ['recipient' => $key, 'user' => null, 'email' => $email];
            }
        };

        $add($member->user_id !== null ? $member->user : null, $member->email);

        $today = Carbon::today();
        $guardians = $member->guardians()
            ->whereNull('revoked_at')
            ->where(fn($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<', DateRange::dayAfter($today)))
            ->where(fn($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($today)))
            ->with('user')
            ->get();
        foreach ($guardians as $guardian) {
            if ($guardian->allows(ClubGuardianPermission::ReceiveMessages)) {
                $add($guardian->user_id !== null ? $guardian->user : null, $guardian->email);
            }
        }

        return array_values($recipients);
    }

    /**
     * @param  array<string, string>  $params  gerenderte Parameter (Datum in Org-Zeitzone)
     * @param  array<string, string>  $isoParams  Rohwerte für die Anzeige in Empfänger-Zeitzone
     */
    private function send(ClubMember $member, Event $event, string $kind, string $key, NotificationEvent $type, string $messageKey, array $params, array $isoParams): int {
        $payload = [
            'title' => (string) $event->title,
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $isoParams + $params,
            'url' => $this->safeRoute('club.my.index'),
            'icon' => 'groups',
        ];

        $sent = 0;
        foreach ($this->recipientsFor($member) as $recipient) {
            /** @var ClubNotification|null $existing */
            $existing = ClubNotification::query()->where('dedupe_key', $key)->where('recipient', $recipient['recipient'])->first();
            if ($existing !== null && ! $existing->isFailed()) {
                continue;
            }

            $attributes = [
                'organization_id' => $member->organization_id,
                'club_member_id' => $member->id,
                'event_id' => $event->id,
                'kind' => $kind,
                'dedupe_key' => $key,
                'recipient' => $recipient['recipient'],
                'payload' => ['title' => $payload['title'], 'message' => $payload['message'], 'message_key' => $payload['message_key'], 'message_params' => $payload['message_params']],
            ];
            try {
                if ($recipient['user'] !== null) {
                    $this->dispatcher->notify($type, $event, $recipient['user'], $payload);
                } else {
                    Notification::route('mail', (string) $recipient['email'])->notify(new GenericEventNotification($type, $payload, ['mail']));
                }
                $this->record($existing, $attributes + ['status' => ClubNotificationStatus::Sent->value, 'error' => null, 'sent_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                $this->record($existing, $attributes + ['status' => ClubNotificationStatus::Failed->value, 'error' => mb_substr($e->getMessage(), 0, 500), 'sent_at' => null]);
                if (app()->runningUnitTests()) {
                    throw $e;
                }
            }
        }

        return $sent;
    }

    /** @param array<string, mixed> $attributes */
    private function record(?ClubNotification $existing, array $attributes): void {
        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }
        ClubNotification::query()->create($attributes);
    }

    /** @return \Illuminate\Support\Collection<int, ClubMember> */
    private function activeMembers(Event $event) {
        return ClubMember::query()
            ->where('organization_id', $event->organization_id)
            ->whereHas('eventParticipations', fn($query) => $query
                ->where('event_id', $event->id)
                ->whereIn('status', [ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value, ClubParticipationStatus::Invited->value]))
            ->get();
    }

    private function key(string $kind, Event $event, ClubMember $member, string $fingerprint): string {
        return $kind . ':' . $event->id . ':' . $member->id . ':' . $fingerprint;
    }

    /** Zeitpunkt in der Zeitzone der Organisation — Nachrichten entstehen auch ohne Anmeldung (Scheduler). */
    private function when(Event $event, CarbonInterface $at): string {
        $organization = $event->organization;
        $tz = $organization instanceof Organization ? Tz::ofOrganization($organization) : Tz::current();

        return CarbonImmutable::instance($at)->setTimezone($tz)->format('d.m.Y H:i');
    }

    private function safeRoute(string $name): ?string {
        try {
            return route($name);
        } catch (Throwable) {
            return null;
        }
    }
}
