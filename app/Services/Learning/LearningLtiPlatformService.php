<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiPlatformService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningLtiLink, LearningLtiTool, LearningUnit};
use App\Models\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\WebLinkHelper;
use ELearningToolkit\Lti\{AuthenticationRequest, Claims, DeepLinkingResponse, DeepLinkingSettings, IdTokenBuilder, Keys, LoginInitiation, LtiException, Registration, Roles};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Jose\Component\Core\{JWK, JWKSet};

/**
 * WorkDiary als LTI-1.3-Plattform (Feature 149): Login-Anstoß beim Tool und die
 * Antwort auf dessen Authentifizierungsanfrage.
 *
 * Das Tool bekommt nie mehr als nötig: die Sqid als Subjekt, Name und Mail nur mit
 * ausdrücklicher Freigabe an der Registrierung.
 */
final class LearningLtiPlatformService {
    /** Hinweise leben nur so lange, wie ein Login-Ablauf dauert. */
    private const HINT_TTL_SECONDS = 600;

    /** Die Auswahl beim Tool darf dauern — eine Autorin blättert dort im Katalog. */
    private const DEEP_LINKING_TTL_SECONDS = 1800;

    private const CONTEXT_TYPE_COURSE = 'http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering';

    public function __construct(
        private readonly LearningLtiKeyService $keys,
        private readonly LearningLtiRemoteKeys $remoteKeys,
        private readonly LearningLtiHint $hints,
        private readonly LearningLtiNonceStore $nonces,
    ) {}

    public static function issuer(): string {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Das Tool, wie die Plattform es registriert hat. Schlüssel werden nur geladen,
     * wo ein Token des Tools zu prüfen ist.
     *
     * @throws LtiException
     */
    public function registration(LearningLtiTool $tool, bool $withKeys = false, bool $freshKeys = false): Registration {
        $keySet = new JWKSet([]);

        if ($withKeys) {
            $keySet = $tool->jwks_url !== null && $tool->jwks_url !== ''
                ? $this->remoteKeys->keySet($tool->jwks_url, $freshKeys)
                : Keys::keySet((string) $tool->public_jwks);
        }

        return new Registration(self::issuer(), $tool->client_id, $keySet, [$tool->deployment_id], null, $tool->redirect_uris);
    }

    /**
     * Login-Anstoß für den Start einer LTI-Einheit (LTI Core 4.1).
     *
     * @throws LtiException
     */
    public function launchLoginUrl(LearningEnrollment $enrollment, LearningLtiLink $link, LearningLtiTool $tool, User $learner): string {
        return LoginInitiation::toolLoginUrl(
            $tool->login_url,
            $this->registration($tool),
            $this->hints->issue(LearningLtiHint::PURPOSE_LOGIN, ['u' => $learner->id], self::HINT_TTL_SECONDS),
            $link->url ?? $tool->launch_url,
            $this->hints->issue(LearningLtiHint::PURPOSE_MESSAGE, ['t' => 'launch', 'l' => $link->id, 'e' => $enrollment->id], self::HINT_TTL_SECONDS),
            $tool->deployment_id,
        );
    }

    /**
     * Login-Anstoß, damit eine Autorin beim Tool Inhalte auswählt (Deep Linking 2.0).
     *
     * @throws LtiException
     */
    public function deepLinkingLoginUrl(LearningUnit $unit, LearningLtiTool $tool, User $author): string {
        return LoginInitiation::toolLoginUrl(
            $tool->login_url,
            $this->registration($tool),
            $this->hints->issue(LearningLtiHint::PURPOSE_LOGIN, ['u' => $author->id], self::HINT_TTL_SECONDS),
            $tool->deep_linking_url ?? $tool->launch_url,
            $this->hints->issue(LearningLtiHint::PURPOSE_MESSAGE, ['t' => 'deep_linking', 'n' => $unit->id, 'o' => $tool->id], self::HINT_TTL_SECONDS),
            $tool->deployment_id,
        );
    }

    /**
     * Deep-Linking-Antwort des Tools (Deep Linking 2.0, 4.2): Zustand prüfen, die
     * Antwort gegen die Schlüssel des Tools prüfen und den gewählten Inhalt verknüpfen.
     *
     * @return array{unit: LearningUnit, link: LearningLtiLink|null} `link` ist `null`, wenn nichts gewählt wurde
     *
     * @throws LtiException
     */
    public function completeDeepLinking(string $state, string $jwt, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $claims = $this->hints->verify(LearningLtiHint::PURPOSE_DEEP_LINKING, $state, $now);

        // TENANT-BYPASS: sitzungsloser Rücksprung, gebunden über den signierten Zustand.
        $tool = is_int($claims['o'] ?? null) ? LearningLtiTool::query()->withoutGlobalScopes()->find($claims['o']) : null;
        $unit = is_int($claims['n'] ?? null) ? LearningUnit::query()->withoutGlobalScopes()->find($claims['n']) : null;

        if ($tool === null || $unit === null || ! $tool->is_active || $unit->kind !== LearningUnitKind::Lti || $unit->organization_id !== $tool->organization_id) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'data');
        }

        $validate = fn (bool $fresh): array => DeepLinkingResponse::validate($jwt, $this->registration($tool, true, $fresh), self::issuer(), $tool->deployment_id, $this->deepLinkingSettings($state), $this->nonces, $now);

        try {
            $result = $validate(false);
        } catch (LtiException $e) {
            // Unbekannter kid: Die Gegenseite hat womöglich getauscht — einmal frisch laden.
            if ($e->reason !== LtiException::UNKNOWN_KEY || $tool->jwks_url === null || $tool->jwks_url === '') {
                throw $e;
            }

            $result = $validate(true);
        }

        $item = $result['items'][0] ?? null;

        if ($item === null) {
            return ['unit' => $unit, 'link' => null];
        }

        $url = is_string($item['url'] ?? null) && WebLinkHelper::origin($item['url']) !== null ? mb_substr($item['url'], 0, 2000) : null;
        $title = is_string($item['title'] ?? null) ? mb_substr($item['title'], 0, 255) : null;
        $custom = [];

        foreach (is_array($item['custom'] ?? null) ? $item['custom'] : [] as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $custom[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            }
        }

        return ['unit' => $unit, 'link' => $this->saveLink($unit, $tool, $title, $url, $custom)];
    }

    /**
     * Verknüpfung einer Einheit mit einem Tool anlegen oder ändern. Ein anderes Tool
     * heißt eine andere Ressource — die alte Kennung gehört dem alten Tool.
     *
     * @param  array<string, string>  $custom
     */
    public function saveLink(LearningUnit $unit, LearningLtiTool $tool, ?string $title, ?string $url, array $custom): LearningLtiLink {
        return DB::transaction(function () use ($unit, $tool, $title, $url, $custom): LearningLtiLink {
            $link = LearningLtiLink::query()->where('learning_unit_id', $unit->id)->first();

            if ($link !== null && $link->learning_lti_tool_id !== $tool->id) {
                $link->delete();
                $link = null;
            }

            $link ??= new LearningLtiLink([
                'organization_id' => $unit->organization_id,
                'learning_unit_id' => $unit->id,
                'learning_lti_tool_id' => $tool->id,
                'resource_link_id' => Str::uuid()->toString(),
            ]);

            $link->fill(['title' => $title, 'url' => $url, 'custom' => $custom === [] ? null : $custom])->save();

            return $link;
        });
    }

    /**
     * Antwort auf die Authentifizierungsanfrage (Security Framework 5.1.1.3): Tool,
     * Redirect-URI und beide Hinweise gegen die angemeldete Person prüfen, dann das
     * ID-Token signieren.
     *
     * @param  array<mixed>  $parameters
     * @return array{action: string, fields: array<string, string>}
     *
     * @throws LtiException
     */
    public function authenticate(array $parameters, User $user, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $clientId = is_string($parameters['client_id'] ?? null) ? $parameters['client_id'] : '';
        $tool = LearningLtiTool::query()->where('client_id', $clientId)->where('is_active', true)->first();

        // Ohne Organisationsbindung am Endpunkt ist die eigene Organisation die Grenze.
        if ($tool === null || $tool->organization_id !== $user->organization_id) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'client_id');
        }

        $registration = $this->registration($tool);
        $request = AuthenticationRequest::fromParameters($parameters, $registration);

        $login = $this->hints->verify(LearningLtiHint::PURPOSE_LOGIN, $request->loginHint, $now);

        if ($login === null || ($login['u'] ?? null) !== $user->id) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'login_hint');
        }

        $message = $request->messageHint !== null
            ? $this->hints->verify(LearningLtiHint::PURPOSE_MESSAGE, $request->messageHint, $now)
            : null;

        $key = $this->keys->active()->privateKey();

        $idToken = match ($message['t'] ?? null) {
            'launch' => $this->resourceLinkToken($tool, $registration, $request, $message, $user, $key, $now),
            'deep_linking' => $this->deepLinkingToken($tool, $registration, $request, $message, $user, $key, $now),
            default => throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'lti_message_hint'),
        };

        return ['action' => $request->redirectUri, 'fields' => IdTokenBuilder::responseFields($idToken, $request)];
    }

    /** @param  array<mixed>  $message */
    private function resourceLinkToken(LearningLtiTool $tool, Registration $registration, AuthenticationRequest $request, array $message, User $user, JWK $key, CarbonImmutable $now): string {
        $link = is_int($message['l'] ?? null) ? LearningLtiLink::query()->with('unit.course')->find($message['l']) : null;
        $enrollment = is_int($message['e'] ?? null) ? LearningEnrollment::query()->find($message['e']) : null;
        $unit = $link?->unit;

        if ($link === null || $unit === null || $enrollment === null
            || $link->learning_lti_tool_id !== $tool->id
            || $enrollment->user_id !== $user->id
            || $unit->learning_course_id !== $enrollment->learning_course_id) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'lti_message_hint');
        }

        return IdTokenBuilder::resourceLink(
            self::issuer(),
            $registration,
            $tool->deployment_id,
            $request,
            $user->sqid,
            [Roles::LEARNER],
            $link->url ?? $tool->launch_url,
            $link->resource_link_id,
            $key,
            $now,
            $this->commonClaims($tool, $user, $unit->course, $link->custom ?? [], route('learning.my.show', $enrollment)),
            $link->title ?? $unit->title,
        );
    }

    /** @param  array<mixed>  $message */
    private function deepLinkingToken(LearningLtiTool $tool, Registration $registration, AuthenticationRequest $request, array $message, User $user, JWK $key, CarbonImmutable $now): string {
        $unit = is_int($message['n'] ?? null) ? LearningUnit::query()->with('course')->find($message['n']) : null;
        $course = $unit?->course;

        if ($unit === null || $course === null || ($message['o'] ?? null) !== $tool->id
            || $unit->kind !== LearningUnitKind::Lti
            || ! Gate::forUser($user)->allows('update', $course)) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'lti_message_hint');
        }

        $state = $this->hints->issue(LearningLtiHint::PURPOSE_DEEP_LINKING, ['u' => $user->id, 'n' => $unit->id, 'o' => $tool->id], self::DEEP_LINKING_TTL_SECONDS);

        return IdTokenBuilder::deepLinking(
            self::issuer(),
            $registration,
            $tool->deployment_id,
            $request,
            $user->sqid,
            [Roles::INSTRUCTOR, Roles::CONTENT_DEVELOPER],
            $this->deepLinkingSettings($state),
            $tool->deep_linking_url ?? $tool->launch_url,
            $key,
            $now,
            $this->commonClaims($tool, $user, $course, [], route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])),
        );
    }

    /**
     * Genau ein LTI-Link zurück. Der Zustand steht als `data` im Token UND in der
     * Rückkehr-Adresse: Nur so lässt er sich prüfen, bevor die Antwort geprüft ist.
     */
    private function deepLinkingSettings(string $state): DeepLinkingSettings {
        return new DeepLinkingSettings(
            route('learning.lti.deep-linking.return', ['zustand' => $state]),
            ['ltiResourceLink'],
            ['window'],
            acceptMultiple: false,
            data: $state,
        );
    }

    /**
     * @param  array<string, string>  $custom
     * @return array<string, mixed>
     */
    private function commonClaims(LearningLtiTool $tool, User $user, ?LearningCourse $course, array $custom, string $returnUrl): array {
        $claims = [
            Claims::TOOL_PLATFORM => ['guid' => self::issuer(), 'name' => (string) config('app.name')],
            Claims::LAUNCH_PRESENTATION => ['document_target' => 'window', 'return_url' => $returnUrl],
        ];

        if ($course !== null) {
            $claims[Claims::CONTEXT] = ['id' => $course->sqid, 'title' => $course->title, 'type' => [self::CONTEXT_TYPE_COURSE]];
        }

        if ($custom !== []) {
            $claims[Claims::CUSTOM] = $custom;
        }

        if ($tool->share_name) {
            $claims['name'] = $user->name;
        }

        if ($tool->share_email && $user->email !== '') {
            $claims['email'] = $user->email;
        }

        return $claims;
    }
}
