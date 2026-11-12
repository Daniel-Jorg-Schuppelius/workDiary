<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCommandReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderCommandStatus, DomainRenewalMode};
use App\Models\Domain\{DomainProjection, DomainProviderCommand, DomainProviderConnection};
use App\Models\Platform\User;
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Support\Domain\{DomainCapabilityBlockedException, DomainProviderException, DomainResponse};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Abgleich unklarer Provider-Befehle (Feature 083 §Synchronisation, MVP-1614).
 *
 * Ein Befehl ohne vollständige Antwort bleibt `Unknown` und wird nie
 * wiederholt. Der Sync-Lauf liest den Anbieterzustand über `StatusDomain`
 * (Handbuch 2.3.2.5: RENEWALMODE, REGISTRATIONEXPIRATIONDATE, STATUS mit
 * `clientTransferProhibited`, Code 545 „Object not found“) und setzt den
 * Befehl auf bestätigt, fehlgeschlagen oder Konflikt. Befehle, deren Wirkung
 * `StatusDomain` nicht zeigt (Transfer, DNS, Hochrisiko), bleiben unklar mit
 * dem Hinweis „von Hand prüfen“ und lassen sich von Hand abschließen.
 */
class DomainCommandReconciler {
    /** Je Lauf höchstens so viele Befehle — jeder kostet einen Anbieteraufruf. */
    private const int BATCH = 50;

    /** Handbuch 2.3.1: 545 „Object not found“. */
    private const int CODE_NOT_FOUND = 545;

    public function __construct(
        private readonly DomainProviderResolver $resolver,
        private readonly DomainRegistrationService $registrations,
    ) {}

    public function reconcileOpen(DomainProviderConnection $connection, ?DomainProviderAdapter $adapter = null): int {
        $commands = DomainProviderCommand::query()
            ->withoutGlobalScopes()
            ->where('connection_id', $connection->id)
            ->where('status', DomainProviderCommandStatus::Unknown->value)
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();
        if ($commands->isEmpty()) {
            return 0;
        }

        $adapter ??= $this->resolver->for($connection);
        $settled = 0;
        foreach ($commands as $command) {
            $command->setRelation('connection', $connection);
            if ($this->reconcile($command, $adapter)->status !== DomainProviderCommandStatus::Unknown) {
                $settled++;
            }
        }

        return $settled;
    }

    public function reconcile(DomainProviderCommand $command, DomainProviderAdapter $adapter): DomainProviderCommand {
        if ($command->status !== DomainProviderCommandStatus::Unknown) {
            return $command;
        }
        if (! $this->readable($command)) {
            return $this->note($command, ['code' => 'manual_check']);
        }

        try {
            $response = $adapter->execute('StatusDomain', ['domain' => (string) $command->target], DomainCapabilityArea::Domains);
        } catch (DomainProviderException $e) {
            return $this->note($command, ['code' => 'status_unavailable', 'detail' => $e->incomplete ? 'incomplete' : 'transport']);
        } catch (DomainCapabilityBlockedException) {
            return $this->note($command, ['code' => 'status_unavailable', 'detail' => 'capability_blocked']);
        }

        if ($response->isComplete() && $response->code === self::CODE_NOT_FOUND) {
            return $command->command === 'AddDomain'
                ? $this->settle($command, DomainProviderCommandStatus::Failed, ['code' => 'not_registered'])
                : $this->settle($command, DomainProviderCommandStatus::Conflict, ['code' => 'not_in_portfolio']);
        }
        if (! $response->isSuccess()) {
            return $this->note($command, ['code' => 'status_unavailable', 'detail' => 'code_' . $response->code]);
        }

        return $this->compare($command, $response);
    }

    /**
     * Abschluss von Hand, wenn der Anbieter keinen Statusabruf erlaubt oder
     * ein Konflikt geklärt wurde — mit Begründung und Audit.
     */
    public function resolveManually(DomainProviderCommand $command, bool $done, string $note, User $actor): DomainProviderCommand {
        if (! $command->status->needsReconciliation()) {
            throw new RuntimeException('Nur unklare Befehle oder Konflikte lassen sich von Hand abschließen.');
        }
        $from = $command->status;
        $status = $done ? DomainProviderCommandStatus::Confirmed : DomainProviderCommandStatus::Failed;
        $note = mb_substr(trim($note), 0, 200);
        $command->forceFill([
            'status' => $status,
            'confirmed_at' => $done ? Carbon::now() : $command->confirmed_at,
            'reconciled_at' => Carbon::now(),
            'reconciliation_note' => JsonHelper::encode(['code' => $done ? 'manual_confirmed' : 'manual_failed', 'note' => $note], JSON_UNESCAPED_UNICODE),
        ])->save();
        $command->audit('domain.commandResolved', ['from' => $from->value, 'to' => $status->value, 'note' => $note, 'by_user_id' => $actor->id]);

        return $command;
    }

    /** Befehle, deren Wirkung `StatusDomain` zeigt. */
    private function readable(DomainProviderCommand $command): bool {
        $payload = $command->payload ?? [];

        return match ($command->command) {
            'AddDomain' => true,
            'SetDomainRenewalMode' => array_key_exists('renewalmode', $payload),
            // Altbefehle vor MVP-1619 setzten den Modus noch über ModifyDomain.
            'ModifyDomain' => array_key_exists('renewalmode', $payload) || array_key_exists('transferlock', $payload),
            'RenewDomain' => is_string($command->preflight_snapshot['expiration_at'] ?? null),
            default => false,
        };
    }

    private function compare(DomainProviderCommand $command, DomainResponse $response): DomainProviderCommand {
        $payload = $command->payload ?? [];

        if ($command->command === 'AddDomain') {
            $settled = $this->settle($command, DomainProviderCommandStatus::Confirmed, ['code' => 'registered']);
            try {
                $this->registrations->materializeConfirmed($settled);
            } catch (Throwable $e) {
                report($e); // Die Projektion holt der Listen-Sync nach.
            }

            return $settled;
        }

        if ($command->command === 'RenewDomain') {
            $before = Carbon::parse((string) ($command->preflight_snapshot['expiration_at'] ?? ''));
            $after = $this->date($response->first('registrationexpirationdate'));

            // Tagesgenau: die Projektion führt das Ablaufdatum ohne Uhrzeit.
            return $after !== null && $after->toDateString() > $before->toDateString()
                ? $this->settle($command, DomainProviderCommandStatus::Confirmed, ['code' => 'renewed', 'provider' => $after->toDateString()])
                : $this->settle($command, DomainProviderCommandStatus::Conflict, ['code' => 'expiration_unchanged', 'provider' => $after?->toDateString() ?? '—', 'expected' => $before->toDateString()]);
        }

        if (array_key_exists('renewalmode', $payload)) {
            $expected = (string) $payload['renewalmode'];
            $provider = DomainRenewalMode::fromProvider($response->first('renewalmode'));
            if ($provider?->value !== $expected) {
                return $this->settle($command, DomainProviderCommandStatus::Conflict, ['code' => 'renewal_mode_differs', 'provider' => $provider->value ?? '—', 'expected' => $expected]);
            }
            $this->projection($command)?->forceFill(['renewal_mode' => $provider->value])->save();

            return $this->settle($command, DomainProviderCommandStatus::Confirmed, ['code' => 'renewal_mode_set']);
        }

        $expected = (string) $payload['transferlock'] === '1';
        $provider = $this->transferLock($response);
        if ($provider !== $expected) {
            return $this->settle($command, DomainProviderCommandStatus::Conflict, ['code' => 'transferlock_differs', 'provider' => $provider ? '1' : '0', 'expected' => $expected ? '1' : '0']);
        }
        $this->projection($command)?->forceFill(['transferlock' => $provider])->save();

        return $this->settle($command, DomainProviderCommandStatus::Confirmed, ['code' => 'transferlock_set']);
    }

    /** Transfersperre = Status `clientTransferProhibited` (Handbuch 2.3.2.4); TRANSFERLOCK, wenn der Gateway es liefert. */
    private function transferLock(DomainResponse $response): bool {
        $flag = $response->first('transferlock');
        if ($flag !== null && $flag !== '') {
            return $flag === '1';
        }

        return in_array('clienttransferprohibited', array_map('strtolower', $response->property('status')), true);
    }

    /** @param array<string, string> $note */
    private function settle(DomainProviderCommand $command, DomainProviderCommandStatus $status, array $note): DomainProviderCommand {
        $command->forceFill([
            'status' => $status,
            'confirmed_at' => $status === DomainProviderCommandStatus::Confirmed ? Carbon::now() : $command->confirmed_at,
            'reconciled_at' => Carbon::now(),
            'reconciliation_note' => JsonHelper::encode($note, JSON_UNESCAPED_UNICODE),
        ])->save();
        $command->audit('domain.commandReconciled', ['to' => $status->value] + $note);

        return $command;
    }

    /**
     * Bleibt unklar; nur der Hinweis wechselt.
     *
     * @param  array<string, string>  $note
     */
    private function note(DomainProviderCommand $command, array $note): DomainProviderCommand {
        $encoded = JsonHelper::encode($note, JSON_UNESCAPED_UNICODE);
        if ($command->reconciliation_note !== $encoded) {
            $command->forceFill(['reconciliation_note' => $encoded])->save();
        }

        return $command;
    }

    private function projection(DomainProviderCommand $command): ?DomainProjection {
        return DomainProjection::query()->withoutGlobalScopes()
            ->where('connection_id', $command->connection_id)
            ->where('external_domain', $command->target)
            ->first();
    }

    private function date(?string $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }
}
