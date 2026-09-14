<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiToolService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Enums\Learning\LearningCourseStatus;
use App\Models\ExternalParticipant;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningLtiPlatform, LearningLtiSubject};
use App\Services\ExternalParticipant\ExternalParticipantService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, WebLinkHelper};
use ELearningToolkit\Lti\{ContentItem, DeepLinkingResponse, DeepLinkingSettings, LaunchMessage, LaunchValidator, LoginInitiation, LtiException, MessageType, Registration, Roles};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Cache, DB, Validator};
use Jose\Component\Core\JWKSet;

/**
 * WorkDiary als LTI-1.3-Tool (Feature 149): Login-Anstoß fremder Plattformen, Start
 * freigegebener Kurse und Deep Linking.
 *
 * Es entsteht nie ein Nutzerkonto. Die Person ist ein externer Teilnehmer, erkannt am
 * Abdruck ihres `sub` bei genau dieser Plattform.
 */
final class LearningLtiToolService {
    /** Parameter in `target_link_uri` oder `custom`, der den Kurs nennt. */
    public const COURSE_PARAMETER = 'kurs';

    private const STATE_TTL_SECONDS = 600;

    /** Die Auswahl darf dauern — eine Kursgestalterin blättert im Katalog. */
    private const DEEP_LINKING_TTL_SECONDS = 1800;

    private const STATE_CACHE_PREFIX = 'learning.lti.tool.state.';

    public function __construct(
        private readonly LearningLtiRemoteKeys $remoteKeys,
        private readonly LearningLtiNonceStore $nonces,
        private readonly LearningLtiKeyService $keys,
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /**
     * Die Plattform, wie dieses Tool sie registriert hat.
     *
     * @throws LtiException
     */
    public function registration(LearningLtiPlatform $platform, bool $withKeys = false, bool $freshKeys = false): Registration {
        return new Registration(
            $platform->issuer,
            $platform->client_id,
            $withKeys ? $this->remoteKeys->keySet($platform->jwks_url, $freshKeys) : new JWKSet([]),
            $platform->deployment_ids,
            $platform->authorization_endpoint,
        );
    }

    /**
     * Login-Anstoß der Plattform (Security Framework 5.1.1.1). `state` und Nonce liegen
     * serverseitig — ein Cookie ginge im fremden iframe ohnehin verloren.
     *
     * @param  array<mixed>  $parameters
     *
     * @throws LtiException
     */
    public function login(array $parameters): string {
        $login = LoginInitiation::fromParameters($parameters);
        $platform = $this->platformFor($login->issuer, $login->clientId);

        if ($login->deploymentId !== null && ! in_array($login->deploymentId, $platform->deployment_ids, true)) {
            throw new LtiException(LtiException::UNKNOWN_DEPLOYMENT, $login->deploymentId);
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        Cache::put(self::STATE_CACHE_PREFIX . CryptoHelper::hash($state), ['p' => $platform->id, 'n' => $nonce, 't' => $login->targetLinkUri], self::STATE_TTL_SECONDS);

        return $login->authenticationRequestUrl($this->registration($platform), route('learning.lti.tool.launch'), $state, $nonce);
    }

    /**
     * Start durch die Plattform: `state` einlösen, ID-Token prüfen, dann einschreiben oder
     * die Auswahl für Deep Linking vorbereiten.
     *
     * @return array{kind: 'launch', enrollment: LearningEnrollment}|array{kind: 'deep_linking', context: array<string, mixed>}
     *
     * @throws LtiException
     */
    public function launch(string $idToken, string $state, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $stored = $state !== '' ? Cache::pull(self::STATE_CACHE_PREFIX . CryptoHelper::hash($state)) : null;

        if (! is_array($stored) || ! is_int($stored['p'] ?? null) || ! is_string($stored['n'] ?? null)) {
            throw new LtiException(LtiException::STATE_MISMATCH);
        }

        // TENANT-BYPASS: sitzungsloser Start, gebunden über den serverseitigen Zustand.
        $platform = LearningLtiPlatform::query()->withoutGlobalScopes()->where('is_active', true)->find($stored['p']);

        if ($platform === null) {
            throw new LtiException(LtiException::STATE_MISMATCH);
        }

        $nonce = $stored['n'];
        $target = is_string($stored['t'] ?? null) ? $stored['t'] : null;
        $validate = fn (bool $fresh): LaunchMessage => (new LaunchValidator($this->nonces))
            ->validate($idToken, $this->registration($platform, true, $fresh), $now, $nonce, $target);

        try {
            $message = $validate(false);
        } catch (LtiException $e) {
            // Unbekannter kid: Die Plattform hat womöglich getauscht — einmal frisch laden.
            if ($e->reason !== LtiException::UNKNOWN_KEY) {
                throw $e;
            }

            $message = $validate(true);
        }

        return match ($message->type) {
            MessageType::ResourceLinkRequest => ['kind' => 'launch', 'enrollment' => $this->enroll($platform, $message)],
            MessageType::DeepLinkingRequest => ['kind' => 'deep_linking', 'context' => $this->deepLinkingContext($platform, $message, $now)],
            default => throw new LtiException(LtiException::UNSUPPORTED_MESSAGE_TYPE),
        };
    }

    /**
     * @param  array<mixed>  $context
     * @return array{platform: LearningLtiPlatform, courses: Collection<int, LearningCourse>}
     *
     * @throws LtiException
     */
    public function deepLinkingChoices(array $context, ?CarbonImmutable $now = null): array {
        $platform = $this->platformForContext($context, $now ?? CarbonImmutable::now());

        return ['platform' => $platform, 'courses' => $this->availableCourses($platform)];
    }

    /**
     * Deep-Linking-Antwort an die Plattform; ohne Kurs eine leere Auswahl.
     *
     * @param  array<mixed>  $context
     * @return array{jwt: string, returnUrl: string}
     *
     * @throws LtiException
     */
    public function deepLinkingResponse(array $context, ?string $courseSqid, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $platform = $this->platformForContext($context, $now);
        $items = [];

        if ($courseSqid !== null && $courseSqid !== '') {
            $course = $this->availableCourse($platform, $courseSqid) ?? throw new LtiException(LtiException::INVALID_CLAIM, 'course');
            $items[] = ContentItem::ltiResourceLink($this->courseTarget($course), $course->title);
        }

        return DeepLinkingResponse::build(
            $this->registration($platform),
            (string) $context['d'],
            DeepLinkingSettings::fromClaim($context['s']),
            $items,
            $this->keys->active()->privateKey(),
            Str::random(32),
            $now,
        );
    }

    /** Ziel eines Kurses für die Plattform — der Kurs steht als Parameter darin. */
    public function courseTarget(LearningCourse $course): string {
        return route('learning.lti.tool.launch', [self::COURSE_PARAMETER => $course->sqid]);
    }

    private function enroll(LearningLtiPlatform $platform, LaunchMessage $message): LearningEnrollment {
        // Ein anonymer Start lässt sich keiner Person zuordnen — und ohne Person kein Nachweis.
        $subject = $message->subject ?? throw new LtiException(LtiException::MISSING_CLAIM, 'sub');

        $sqid = $message->custom()[self::COURSE_PARAMETER] ?? null;

        if (! is_string($sqid) && $message->targetLinkUri !== null
            && WebLinkHelper::origin($message->targetLinkUri) === WebLinkHelper::origin(route('learning.lti.tool.launch'))) {
            parse_str((string) parse_url($message->targetLinkUri, PHP_URL_QUERY), $query);
            $sqid = $query[self::COURSE_PARAMETER] ?? null;
        }

        $course = (is_string($sqid) ? $this->availableCourse($platform, $sqid) : null)
            ?? throw new LtiException(LtiException::INVALID_CLAIM, 'target_link_uri');

        return $this->enrollments->enroll($course, $this->participant($platform, $course, $message, $subject));
    }

    private function participant(LearningLtiPlatform $platform, LearningCourse $course, LaunchMessage $message, string $subject): ExternalParticipant {
        $subjectHash = CryptoHelper::hash($platform->issuer . "\n" . $subject);
        $claimedName = $message->claims['name'] ?? null;
        $claimedEmail = $message->claims['email'] ?? null;
        $name = is_string($claimedName) && trim($claimedName) !== ''
            ? mb_substr(trim($claimedName), 0, 150)
            : (string) __('learning.lti_tool.participant_name', ['platform' => $platform->name]);
        $email = is_string($claimedEmail) && Validator::make(['email' => $claimedEmail], ['email' => ['email', 'max:255']])->passes()
            ? $claimedEmail
            : null;

        return DB::transaction(function () use ($platform, $course, $subjectHash, $name, $email): ExternalParticipant {
            $record = LearningLtiSubject::query()->withoutGlobalScopes()->firstOrNew(
                ['learning_lti_platform_id' => $platform->id, 'subject_hash' => $subjectHash],
                ['organization_id' => $platform->organization_id],
            );
            $participant = $record->external_participant_id !== null
                ? ExternalParticipant::query()->withoutGlobalScopes()->find($record->external_participant_id)
                : null;
            $expires = Carbon::now()->addDays(ExternalParticipantService::MAX_TTL_DAYS);

            if ($participant !== null) {
                $participant->fill(['name' => $name, 'email' => $email ?? $participant->email, 'expires_at' => $expires, 'last_access_at' => Carbon::now()])->save();

                return $participant;
            }

            $participant = ExternalParticipant::query()->create([
                'organization_id' => $platform->organization_id,
                'subject_type' => $course->getMorphClass(),
                'subject_id' => $course->id,
                'name' => $name,
                'email' => $email,
                'role' => 'LTI',
                'party' => ExternalParty::Other->value,
                // Kein Einstiegslink: Zugang gibt allein der Start über die Plattform.
                'token_hash' => CryptoHelper::hash(Str::random(64)),
                'abilities' => [],
                'expires_at' => $expires,
                'created_at' => Carbon::now(),
            ]);

            $record->external_participant_id = $participant->id;
            $record->save();

            return $participant;
        });
    }

    /** @return array<string, mixed> */
    private function deepLinkingContext(LearningLtiPlatform $platform, LaunchMessage $message, CarbonImmutable $now): array {
        $settings = $message->deepLinkingSettings ?? throw new LtiException(LtiException::MISSING_CLAIM, 'deep_linking_settings');

        // Auswählen darf, wer im LMS Kurse gestaltet — Lernende nicht.
        if (! Roles::includesAny($message->roles, Roles::INSTRUCTOR, Roles::CONTENT_DEVELOPER, Roles::ADMINISTRATOR)) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'roles');
        }

        return ['p' => $platform->id, 'd' => $message->deploymentId, 's' => $settings->toClaim(), 'x' => $now->getTimestamp() + self::DEEP_LINKING_TTL_SECONDS];
    }

    /**
     * @param  array<mixed>  $context
     *
     * @throws LtiException
     */
    private function platformForContext(array $context, CarbonImmutable $now): LearningLtiPlatform {
        if (! is_int($context['p'] ?? null) || ! is_int($context['x'] ?? null) || ! is_string($context['d'] ?? null)
            || ! is_array($context['s'] ?? null) || $context['x'] < $now->getTimestamp()) {
            throw new LtiException(LtiException::STATE_MISMATCH);
        }

        // TENANT-BYPASS: Gast-Sitzung ohne Organisation; die Plattform bestimmt sie.
        return LearningLtiPlatform::query()->withoutGlobalScopes()->where('is_active', true)->find($context['p'])
            ?? throw new LtiException(LtiException::STATE_MISMATCH);
    }

    /** @throws LtiException */
    private function platformFor(string $issuer, ?string $clientId): LearningLtiPlatform {
        // TENANT-BYPASS: sitzungsloser Login-Anstoß; Aussteller und Client-ID bestimmen die Organisation.
        $query = LearningLtiPlatform::query()->withoutGlobalScopes()->where('is_active', true);
        $candidates = $clientId !== null
            ? $query->where('lookup_hash', LearningLtiPlatform::lookupHash($issuer, $clientId))->get()
            : $query->where('issuer', $issuer)->get();

        // Ohne Client-ID nur, wenn der Aussteller eindeutig ist.
        if ($candidates->count() !== 1) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'iss');
        }

        return $candidates->first() ?? throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'iss');
    }

    /** @return Collection<int, LearningCourse> */
    private function availableCourses(LearningLtiPlatform $platform): Collection {
        return $this->courseQuery($platform)->orderBy('title')->get();
    }

    private function availableCourse(LearningLtiPlatform $platform, string $sqid): ?LearningCourse {
        $id = Sqid::decode(LearningCourse::class, $sqid);

        return $id === null ? null : $this->courseQuery($platform)->whereKey($id)->first();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<LearningCourse> */
    private function courseQuery(LearningLtiPlatform $platform): \Illuminate\Database\Eloquent\Builder {
        // TENANT-BYPASS: Kurse der Organisation, der die Plattform gehört — nur freigegebene.
        return LearningCourse::query()->withoutGlobalScopes()
            ->where('organization_id', $platform->organization_id)
            ->where('lti_available', true)
            ->where('status', LearningCourseStatus::Released->value);
    }
}
