<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsConnectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Enums\Finance\EbicsConnectionStatus;
use App\Models\Finance\{BankAccount, EbicsConnection};
use App\Models\Platform\User;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Throwable;

/**
 * Einrichtung eines EBICS-Zugangs (MVP-124): Zugangsdaten, Schlüssel, INI/HIA
 * mit Brief, Bankschlüssel nach der Freischaltung, Sperre. Jeder Schritt
 * prüft die Statusfolge und steht im Journal; Fehler der Bank ebenso.
 */
class EbicsConnectionService {
    public function __construct(private readonly EbicsGateway $gateway) {}

    /** @param array{host_url: string, ebics_host: string, ebics_partner: string, ebics_user: string} $data */
    public function save(BankAccount $account, array $data, User $actor): EbicsConnection {
        $connection = EbicsConnection::query()->firstOrNew(['bank_account_id' => $account->id]);
        $changed = ! $connection->exists || array_diff_assoc($data, $connection->only(array_keys($data))) !== [];
        // Geänderte Zugangsdaten passen nicht mehr zu ausgetauschten Schlüsseln.
        if ($connection->exists && $changed && $connection->status !== EbicsConnectionStatus::Draft) {
            throw new EbicsException('locked_after_keys');
        }
        $connection->fill($data + [
            'organization_id' => $account->organization_id,
            'status' => $connection->status ?? EbicsConnectionStatus::Draft,
            'created_by' => $connection->created_by ?? $actor->id,
        ])->save();
        $connection->record('ebics_setup_saved', ['host' => $data['ebics_host'], 'partner' => $data['ebics_partner']], $actor);

        return $connection;
    }

    public function createKeys(EbicsConnection $connection, User $actor): void {
        $this->step($connection, EbicsConnectionStatus::KeysCreated, $actor, 'ebics_keys_created', function () use ($connection): void {
            $this->gateway->createKeys($connection);
            $connection->keys_created_at = now();
        });
    }

    public function initialize(EbicsConnection $connection, User $actor): void {
        $this->step($connection, EbicsConnectionStatus::Initialized, $actor, 'ebics_initialized', function () use ($connection): void {
            $this->gateway->sendInitialization($connection);
            $connection->initialized_at = now();
        });
    }

    /**
     * Initialisierungsbrief erst nach INI/HIA — er trägt die gesendeten Hashwerte.
     *
     * @return list<array{type: string, version: string, hash: string, certificate_created_at: ?string}>
     */
    public function letterKeys(EbicsConnection $connection, User $actor): array {
        if (! in_array($connection->status, [EbicsConnectionStatus::Initialized, EbicsConnectionStatus::Active], true)) {
            throw new EbicsException('not_initialized');
        }
        $keys = $this->gateway->letterKeys($connection);
        $connection->record('ebics_letter_printed', [], $actor);

        return $keys;
    }

    public function activate(EbicsConnection $connection, User $actor): void {
        $this->step($connection, EbicsConnectionStatus::Active, $actor, 'ebics_activated', function () use ($connection): void {
            $this->gateway->fetchBankKeys($connection);
            $connection->activated_at = now();
        });
    }

    public function suspend(EbicsConnection $connection, User $actor): void {
        $this->step($connection, EbicsConnectionStatus::Suspended, $actor, 'ebics_suspended', function () use ($connection): void {
            $this->gateway->suspend($connection);
            // Gesperrte Schlüssel sind wertlos; die Einrichtung beginnt neu.
            $connection->forceFill(['keyring' => null, 'keyring_secret' => null, 'initialized_at' => null, 'activated_at' => null]);
        });
    }

    private function step(EbicsConnection $connection, EbicsConnectionStatus $target, User $actor, string $event, callable $work): void {
        if (! $connection->status->canTransitionTo($target)) {
            throw new EbicsException('invalid_step');
        }
        try {
            $work();
        } catch (Throwable $e) {
            $this->fail($connection, $actor, $event, $e);

            throw $e instanceof EbicsException ? $e : new EbicsException('failed', null, class_basename($e));
        }
        $connection->forceFill(['status' => $target, 'last_error' => null])->save();
        $connection->record($event, [], $actor);
    }

    public function fail(EbicsConnection $connection, ?User $actor, string $event, Throwable $e): void {
        $code = $e instanceof EbicsException ? $e->ebicsCode : null;
        $connection->forceFill(['last_error' => mb_substr(($code !== null ? $code . ' ' : '') . $e->getMessage(), 0, 300)])->save();
        $connection->record('ebics_failed', ['step' => $event, 'code' => $code, 'reason' => $e instanceof EbicsException ? $e->reason : class_basename($e)], $actor);
    }
}
