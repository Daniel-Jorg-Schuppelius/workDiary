<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiInvocationJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Models\Organization;
use App\Services\Ai\{AiInvocationService, AiRoutingResolver};
use App\Services\Ai\Contracts\{AiRequestInterface, AiResultHandlerInterface};
use App\Services\Ai\Exceptions\{AiBudgetExceededException, AiUnavailableException};
use App\Services\Licensing\ModuleStatusResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Asynchroner KI-Aufruf (Feature 025, MVP-399). Idempotent: der
 * Ergebnis-Cache im {@see AiInvocationService} macht Wiederholungen
 * provider-frei, und Handler sind vertraglich idempotent
 * ({@see AiResultHandlerInterface}). Modul-Gate wird zusätzlich VOR der
 * Wirkung geprüft (MVP-052 §5) — Deaktivierung zwischen Dispatch und
 * Ausführung lässt den Job wirkungslos enden. Terminale Zustände
 * (Capability aus, Budget erschöpft, Verbindung nicht erlaubt) gehen
 * ohne Retry an den Handler; nur „alle Provider fehlgeschlagen" wird
 * über die Queue erneut versucht.
 */
class AiInvocationJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    /**
     * @param class-string<AiResultHandlerInterface> $handlerClass
     * @param array<string, mixed> $context fachlicher Kontext inkl. Idempotenz-Key
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly string $capability,
        public readonly AiRequestInterface $request,
        public readonly string $handlerClass,
        public readonly array $context = [],
        public readonly ?int $requestedConnectionId = null,
    ) {}

    public function handle(AiInvocationService $service, ModuleStatusResolver $modules): void {
        $organization = Organization::query()->withoutGlobalScopes()->find($this->organizationId);
        if ($organization === null) {
            return;
        }

        // MVP-052 §5: Modulstatus VOR der Wirkung prüfen — org-explizit,
        // nicht über den Request-Container.
        if (! $modules->isActiveFor($organization, AiRoutingResolver::MODULE)) {
            return; // Modul aus → kein Vorschlag, kein Fehler, kein Retry.
        }

        $handler = $this->resolveHandler();

        try {
            $result = $service->invoke(
                $organization,
                $this->capability,
                $this->request,
                $this->requestedConnectionId
            );
        } catch (AiBudgetExceededException) {
            $handler->handleAiFailure('budget_exceeded', $this->context);

            return;
        } catch (AiUnavailableException $e) {
            if ($e->isRetryable() && $this->attempts() < $this->tries) {
                throw $e; // Queue-Retry mit Backoff.
            }

            $handler->handleAiFailure($e->reason, $this->context);

            return;
        }

        $handler->handleAiResult($result, $this->context);
    }

    public function failed(?Throwable $e): void {
        try {
            $this->resolveHandler()->handleAiFailure(
                AiUnavailableException::REASON_ALL_FAILED,
                $this->context
            );
        } catch (Throwable) {
            // failed() darf nie selbst eskalieren.
        }
    }

    private function resolveHandler(): AiResultHandlerInterface {
        $handler = app($this->handlerClass);

        if (! $handler instanceof AiResultHandlerInterface) {
            throw new \RuntimeException(sprintf(
                'KI-Handler %s implementiert AiResultHandlerInterface nicht.',
                $this->handlerClass
            ));
        }

        return $handler;
    }
}
