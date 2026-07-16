<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCommandService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderCommandStatus};
use App\Models\Domain\{DomainProviderCommand, DomainProviderConnection};
use App\Models\User;
use App\Plugins\Support\Domain\{DomainCapabilityBlockedException, DomainProviderException};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Carbon, Str};
use RuntimeException;

/**
 * Command-Outbox für schreibende Provider-Befehle (Feature 083, MVP-388–391).
 * Trennt Anlegen → Freigabe (Vier-Augen bei Hochrisiko) → Dispatch →
 * Reconciliation. Ein unklarer Ausgang (fehlendes `EOF`) wird NIE blind
 * wiederholt, sondern als `Unknown` reconciled.
 */
class DomainCommandService {
    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * Legt eine Command-Zeile an. Hochrisikoaktionen starten als `Draft`
     * (Freigabe nötig), andere direkt als `Approved`.
     *
     * @param  array<string, scalar|null>  $payload
     * @param  array<string, mixed>|null  $preflightSnapshot
     */
    public function create(
        DomainProviderConnection $connection,
        DomainCapabilityArea $area,
        string $command,
        ?string $target = null,
        array $payload = [],
        bool $requiresSecondApproval = false,
        ?Model $subject = null,
        ?int $customerId = null,
        ?array $preflightSnapshot = null,
        ?User $requestedBy = null,
    ): DomainProviderCommand {
        return DomainProviderCommand::query()->create([
            'organization_id' => $connection->organization_id,
            'connection_id' => $connection->id,
            'command_id' => (string) Str::uuid(),
            'capability' => $area->value,
            'command' => $command,
            'target' => $target,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'customer_id' => $customerId,
            'payload' => $this->redactPayload($payload),
            'preflight_snapshot' => $preflightSnapshot,
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
            'status' => $requiresSecondApproval ? DomainProviderCommandStatus::Draft : DomainProviderCommandStatus::Approved,
            'requires_second_approval' => $requiresSecondApproval,
            'requested_by_user_id' => $requestedBy?->id,
        ]);
    }

    /**
     * Vier-Augen-Freigabe: Freigeber muss ein anderer sein als der
     * Antragsteller. Nur aus `Draft` möglich.
     */
    public function approve(DomainProviderCommand $command, User $approver): DomainProviderCommand {
        if ($command->status !== DomainProviderCommandStatus::Draft) {
            throw new RuntimeException('Nur Entwürfe können freigegeben werden.');
        }
        if ($command->requested_by_user_id !== null && $command->requested_by_user_id === $approver->id) {
            throw new RuntimeException('Vier-Augen-Prinzip: Freigabe muss von einer anderen Person erfolgen.');
        }

        $command->forceFill([
            'approved_by_user_id' => $approver->id,
            'approved_at' => Carbon::now(),
            'status' => DomainProviderCommandStatus::Approved,
        ])->save();

        return $command;
    }

    /**
     * Sendet einen freigegebenen Befehl an den Provider und bewertet den
     * Ausgang. `EOF`-los → `Unknown`; Provider-Fehlercode → `Failed`.
     */
    public function dispatch(DomainProviderCommand $command): DomainProviderCommand {
        if ($command->status !== DomainProviderCommandStatus::Approved) {
            throw new RuntimeException('Nur freigegebene Befehle können ausgeführt werden.');
        }
        if ($command->requires_second_approval && ! $command->hasFourEyesApproval()) {
            throw new RuntimeException('Vier-Augen-Freigabe fehlt.');
        }

        $command->forceFill([
            'status' => DomainProviderCommandStatus::Pending,
            'dispatched_at' => Carbon::now(),
            'attempts' => $command->attempts + 1,
        ])->save();

        try {
            $adapter = $this->resolver->for($command->providerConnection());
            /** @var array<string, scalar|null> $payload */
            $payload = $command->payload ?? [];
            $response = $adapter->execute($command->command, $payload, DomainCapabilityArea::from($command->capability));

            $command->forceFill([
                'status' => $response->isSuccess() ? DomainProviderCommandStatus::Confirmed : DomainProviderCommandStatus::Failed,
                'provider_code' => (string) $response->code,
                'provider_response' => $this->redactResponse($response->code, $response->description),
                'confirmed_at' => $response->isSuccess() ? Carbon::now() : null,
                'last_error' => $response->isSuccess() ? null : 'code_' . $response->code,
            ])->save();
        } catch (DomainProviderException $e) {
            // Fehlendes EOF/Timeout: Ausgang unklar → reconcilen, nicht wiederholen.
            $command->forceFill([
                'status' => $e->incomplete ? DomainProviderCommandStatus::Unknown : DomainProviderCommandStatus::Failed,
                'last_error' => $e->incomplete ? 'incomplete' : 'transport',
            ])->save();
        } catch (DomainCapabilityBlockedException $e) {
            $command->forceFill([
                'status' => DomainProviderCommandStatus::Failed,
                'last_error' => 'capability_blocked_' . $e->area->value,
            ])->save();
        }

        return $command;
    }

    /**
     * Legt an und dispatcht in einem Schritt (für nicht-hochrisikobehaftete
     * Befehle mit sofortiger Ausführung).
     *
     * @param  array<string, scalar|null>  $payload
     * @param  array<string, mixed>|null  $preflightSnapshot
     */
    public function createAndDispatch(
        DomainProviderConnection $connection,
        DomainCapabilityArea $area,
        string $command,
        ?string $target = null,
        array $payload = [],
        ?Model $subject = null,
        ?int $customerId = null,
        ?array $preflightSnapshot = null,
        ?User $requestedBy = null,
    ): DomainProviderCommand {
        $row = $this->create($connection, $area, $command, $target, $payload, false, $subject, $customerId, $preflightSnapshot, $requestedBy);

        return $this->dispatch($row);
    }

    /**
     * Redaktion: nie Passwort/Auth-Code in der gespeicherten Payload.
     *
     * @param  array<string, scalar|null>  $payload
     * @return array<string, scalar|null>
     */
    private function redactPayload(array $payload): array {
        foreach (['password', 'auth', 'authcode', 'pw'] as $secret) {
            if (array_key_exists($secret, $payload)) {
                $payload[$secret] = '[redacted]';
            }
        }

        return $payload;
    }

    private function redactResponse(int $code, string $description): string {
        return mb_substr(sprintf('%d %s', $code, $description), 0, 300);
    }
}
