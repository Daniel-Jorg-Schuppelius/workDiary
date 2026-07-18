<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiInvocationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Ai\AiProviderConnection;
use App\Models\{AuditLog, Organization};
use App\Services\Ai\Contracts\{AiRequestInterface, LlmProviderInterface, TranslatesTextInterface};
use App\Services\Ai\Dto\{AiCapability, AiClassificationResult, AiFindResult, AiInvocationResult, AiTextResult, AiTranslationResult, ClassifyRequest, ExplainRequest, FindRequest, FormulateRequest, SummarizeRequest, TranslateRequest};
use App\Services\Ai\Exceptions\{AiException, AiUnavailableException};
use Illuminate\Support\Facades\{Auth, Cache};
use Throwable;

/**
 * Ausführung eines KI-Aufrufs (Feature 025, MVP-399): Routing →
 * Budget-Vorab-Check → Ergebnis-Cache → Provider-Aufruf mit
 * Fallback-Kette. Jeder Kandidat wurde vom Resolver bereits durch das
 * Sensibilitäts-/Profil-Gate gefiltert — die Fallback-Kette kann daher
 * keine Grenze überschreiten. Fehlgeschlagene Verbindungen werden im
 * Health-Tracking vermerkt (Auto-Disable nach Schwelle); erfolgreiche
 * Aufrufe schreiben Verbrauch und ein Audit-Ereignis OHNE
 * Prompt-/Antwort-Klartext.
 */
class AiInvocationService {
    public function __construct(
        private readonly AiCapabilityRegistry $registry,
        private readonly AiRoutingResolver $resolver,
        private readonly AiBudgetService $budget,
        private readonly AiProviderFactory $providers,
    ) {}

    public function invoke(
        Organization $organization,
        string $capabilityKey,
        AiRequestInterface $request,
        ?int $requestedConnectionId = null,
    ): AiInvocationResult {
        $capability = $this->registry->get($capabilityKey);

        if ($request->verb() !== $capability->verb) {
            throw new AiException(sprintf(
                'Request-Verb "%s" passt nicht zur Capability "%s" (erwartet "%s").',
                $request->verb()->value,
                $capabilityKey,
                $capability->verb->value
            ));
        }

        $candidates = $this->resolver->resolveCandidates($organization, $capabilityKey, $requestedConnectionId);

        $lastError = null;
        foreach ($candidates as $index => $connection) {
            $cacheKey = $this->cacheKey($organization, $capability, $connection, $request);

            $cached = Cache::get($cacheKey);
            if ($cached instanceof AiTextResult
                || $cached instanceof AiClassificationResult
                || $cached instanceof AiFindResult
                || $cached instanceof AiTranslationResult) {
                return $this->wrap($organization, $capability, $connection, $cached, $index > 0, true);
            }

            $this->budget->assertWithinBudget($organization, $connection->family, $request->estimatedUnits());

            try {
                $provider = $this->providers->make($connection);
                $result = $this->perform($provider, $request);
            } catch (Throwable $e) {
                // Redigiert: gekürzte, lokalisierte Meldung, nie Prompt-Inhalte.
                $lastError = $e;
                $connection->recordConnectionFailure(AiException::describe($e));

                continue;
            }

            $connection->recordConnectionSuccess();

            $units = $result->usage->unitsFor($connection->family);
            $this->budget->recordUsage(
                $organization,
                $connection->family,
                $units > 0 ? $units : $request->estimatedUnits()
            );

            Cache::put($cacheKey, $result, now()->addDays(max(1, (int) config('ai.cache_ttl_days', 7))));

            return $this->wrap($organization, $capability, $connection, $result, $index > 0, false);
        }

        if ($lastError !== null && ! $lastError instanceof AiException) {
            report($lastError); // Unerwartete Fehlerklassen sichtbar machen.
        }

        throw AiUnavailableException::allProvidersFailed($capabilityKey);
    }

    private function perform(
        object $provider,
        AiRequestInterface $request,
    ): AiTextResult|AiClassificationResult|AiFindResult|AiTranslationResult {
        if ($request instanceof TranslateRequest) {
            if (! $provider instanceof TranslatesTextInterface) {
                throw new AiException('Provider unterstützt das Verb Übersetzen nicht.');
            }

            return $provider->translate($request);
        }

        if (! $provider instanceof LlmProviderInterface) {
            throw new AiException('Provider unterstützt nur das Verb Übersetzen.');
        }

        return match (true) {
            $request instanceof FormulateRequest => $provider->formulate($request),
            $request instanceof SummarizeRequest => $provider->summarize($request),
            $request instanceof ClassifyRequest => $provider->classify($request)->onlyFromCatalog($request->catalog),
            $request instanceof ExplainRequest => $provider->explain($request),
            $request instanceof FindRequest => $provider->find($request),
            default => throw new AiException('Unbekannter KI-Request-Typ: ' . $request::class),
        };
    }

    private function wrap(
        Organization $organization,
        AiCapability $capability,
        AiProviderConnection $connection,
        AiTextResult|AiClassificationResult|AiFindResult|AiTranslationResult $result,
        bool $fallbackUsed,
        bool $fromCache,
    ): AiInvocationResult {
        // Audit ohne Prompt-/Antwort-Klartext (Feature 025, Leitprinzip 8);
        // Cache-Treffer erzeugen keinen neuen Provider-Kontakt und werden
        // nicht erneut auditiert.
        if (! $fromCache) {
            $actor = Auth::user();
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id' => $actor instanceof \App\Models\User ? (int) $actor->getKey() : null,
                'event' => 'ai.invoked',
                'auditable_type' => $connection->getMorphClass(),
                'auditable_id' => $connection->getKey(),
                'changes' => [
                    'capability' => $capability->key,
                    'verb' => $capability->verb->value,
                    'provider' => $connection->provider->value,
                    'prompt_version' => $capability->promptVersion,
                    'fallback_used' => $fallbackUsed,
                ],
            ]);
        }

        return new AiInvocationResult(
            capability: $capability->key,
            connectionId: (int) $connection->id,
            provider: $connection->provider,
            result: $result,
            fallbackUsed: $fallbackUsed,
            fromCache: $fromCache,
        );
    }

    private function cacheKey(
        Organization $organization,
        AiCapability $capability,
        AiProviderConnection $connection,
        AiRequestInterface $request,
    ): string {
        return sprintf(
            'ai:result:%d:%s:v%d:c%d:%s',
            $organization->id,
            $capability->key,
            $capability->promptVersion,
            $connection->id,
            $request->fingerprint()
        );
    }
}
