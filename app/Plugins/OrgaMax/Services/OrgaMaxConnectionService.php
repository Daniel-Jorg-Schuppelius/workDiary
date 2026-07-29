<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxConnectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Models\{OrgaMaxConnection, Organization, User};
use App\Plugins\OrgaMax\Api\{OrgaMaxClientFactory, OrgaMaxTokenService};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Str;
use Orgamax\API\Endpoints\Settings\AccountSettingEndpoint;
use RuntimeException;

/**
 * Verbindungsaufbau (Feature 077, MVP-306):
 *
 * 1. Ein angemeldeter Org-Admin startet die Verbindungsabsicht (Intent-Token,
 *    befristet). Der spätere `iid`-Callback allein aktiviert NICHTS.
 * 2. Callback mit `iid` + gültigem State-Token tauscht die ownershipId über
 *    POST /auth/token (HTTP Basic Key/Secret) gegen einen JWT und liest
 *    GET /setting/account → Status `pending_confirmation`.
 * 3. Der Admin bestätigt das erkannte Konto ausdrücklich; erst dann prüft der
 *    Scope-Preflight und die Verbindung wird `active` (oder `blocked`).
 *
 * So kann ein fremdes `iid` nie unbemerkt an eine Organisation gebunden
 * werden. Secrets bleiben verschlüsselt und redigiert.
 */
class OrgaMaxConnectionService {
    public function __construct(
        private readonly OrgaMaxClientFactory $clients,
        private readonly OrgaMaxTokenService $tokens,
        private readonly OrgaMaxScopePreflight $preflight,
    ) {}

    /**
     * Verbindungsabsicht starten. Privater Pilotmodus speichert Key/Secret
     * je Org verschlüsselt; Marketplace-Modus nutzt das Betreibergeheimnis.
     *
     * @return string Einmaliges State-Token für die Callback-URL.
     */
    public function startIntent(Organization $organization, User $admin, string $mode, ?string $apiKey, ?string $apiSecret): string {
        if ($mode === OrgaMaxConnection::MODE_PRIVATE && ($apiKey === null || $apiKey === '' || $apiSecret === null || $apiSecret === '')) {
            throw new RuntimeException((string) __('orgamax.error.credentials_required'));
        }
        if ($mode === OrgaMaxConnection::MODE_MARKETPLACE && (string) config('plugins.orgamax.operator_api_key', '') === '') {
            throw new RuntimeException((string) __('orgamax.error.operator_secret_missing'));
        }

        $intent = Str::random(40);

        $connection = OrgaMaxConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->fill([
            'organization_id' => $organization->id,
            'mode' => $mode,
            'status' => OrgaMaxConnection::STATUS_PENDING_CALLBACK,
            'blocked_reason' => null,
            'intent_token_hash' => CryptoHelper::hash($intent),
            'intent_expires_at' => now()->addMinutes((int) config('plugins.orgamax.intent_ttl_minutes', 30)),
            'connected_by' => $admin->id,
            'confirmed_at' => null,
            'account_snapshot' => null,
        ]);
        if ($mode === OrgaMaxConnection::MODE_PRIVATE) {
            $connection->api_key = $apiKey;
            $connection->api_secret = $apiSecret;
        }
        // Sicherer Standard: alles lesend/manuell, nichts aktiviert (MVP-305).
        $connection->capabilities ??= collect(OrgaMaxConnection::CAPABILITIES)
            ->mapWithKeys(fn(string $cap) => [$cap => ['enabled' => false, 'leader' => 'manual_review']])
            ->all();
        $connection->save();
        $connection->audit('orgamax_intent_started', ['mode' => $mode]);

        return $intent;
    }

    /**
     * `iid`-Callback verarbeiten: nur mit offener, unabgelaufener Absicht und
     * korrektem State-Token. Danach Tokenaustausch + Kontoerkennung.
     */
    public function handleCallback(Organization $organization, string $iid, string $state): OrgaMaxConnection {
        $connection = OrgaMaxConnection::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $intentValid = $connection->status === OrgaMaxConnection::STATUS_PENDING_CALLBACK
            && $connection->intent_token_hash !== null
            && hash_equals($connection->intent_token_hash, CryptoHelper::hash($state))
            && $connection->intent_expires_at !== null
            && $connection->intent_expires_at->isFuture();
        if (! $intentValid) {
            throw new RuntimeException((string) __('orgamax.error.intent_invalid'));
        }

        // Token-Bezug über die ownershipId des Callbacks; der TokenService
        // speichert Token und Ablauf und blockiert bei Auth-Fehlern.
        $connection->ownership_id = $iid;
        $connection->save();
        $this->tokens->refresh($connection);

        // Rohantwort: der Scope-Preflight wertet Felder aus, welche die
        // OpenAPI nicht dokumentiert (MVP-306).
        $snapshot = (new AccountSettingEndpoint($this->clients->for($connection)))->raw();
        $connection->forceFill([
            'status' => OrgaMaxConnection::STATUS_PENDING_CONFIRMATION,
            'account_snapshot' => $this->redactAccount($snapshot),
            'granted_scopes' => array_values(array_map('strval', (array) ($snapshot['scopes'] ?? []))),
            'intent_token_hash' => null,
            'intent_expires_at' => null,
        ])->save();
        $connection->audit('orgamax_callback_processed', [
            'account' => (string) ($snapshot['name'] ?? $snapshot['companyType'] ?? $snapshot['senderEmailName'] ?? ''),
        ]);

        return $connection;
    }

    /** Ausdrückliche Kontobestätigung durch den Admin → Scope-Preflight → aktiv. */
    public function confirm(OrgaMaxConnection $connection, User $admin): OrgaMaxConnection {
        if ($connection->status !== OrgaMaxConnection::STATUS_PENDING_CONFIRMATION) {
            throw new RuntimeException((string) __('orgamax.error.nothing_to_confirm'));
        }

        $missing = $this->preflight->missing($connection);
        if ($missing !== []) {
            $connection->forceFill([
                'status' => OrgaMaxConnection::STATUS_BLOCKED,
                'blocked_reason' => 'missing_scopes: ' . implode(', ', $missing),
            ])->save();
            $connection->audit('orgamax_scopes_missing', ['missing' => $missing]);

            return $connection;
        }

        $connection->forceFill([
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
            'confirmed_at' => now(),
            'blocked_reason' => null,
        ])->save();
        $connection->audit('orgamax_account_confirmed', ['by' => $admin->id]);

        return $connection;
    }

    /**
     * Datenführerschaft je Capability setzen (MVP-305): `orgamax_wins`/
     * `workdiary_wins`/`manual_review` sind capabilitybezogene Regeln, keine
     * Gesamtverbindungsoption. Nach Änderung erneuter Scope-Preflight.
     *
     * @param array<string, array{enabled?: bool, leader?: string}> $capabilities
     */
    public function updateCapabilities(OrgaMaxConnection $connection, array $capabilities): OrgaMaxConnection {
        $clean = [];
        foreach (OrgaMaxConnection::CAPABILITIES as $cap) {
            $raw = (array) ($capabilities[$cap] ?? []);
            $leader = in_array($raw['leader'] ?? null, ['orgamax', 'workdiary', 'manual_review'], true)
                ? (string) $raw['leader']
                : 'manual_review';
            $clean[$cap] = ['enabled' => (bool) ($raw['enabled'] ?? false), 'leader' => $leader];
        }
        // Expense-Belegübergabe bleibt bis zum bestätigten Receipt-Pilot
        // blockiert (MVP-312) — sichtbarer Blocked-State statt stiller Calls.
        if (! (bool) config('plugins.orgamax.expense_receipt_contract_confirmed', false)) {
            $clean['expenses']['enabled'] = false;
        }

        $connection->capabilities = $clean;

        $missing = $this->preflight->missing($connection);
        if ($missing !== [] && $connection->isActive()) {
            $connection->status = OrgaMaxConnection::STATUS_BLOCKED;
            $connection->blocked_reason = 'missing_scopes: ' . implode(', ', $missing);
        } elseif ($missing === [] && $connection->status === OrgaMaxConnection::STATUS_BLOCKED
            && str_starts_with((string) $connection->blocked_reason, 'missing_scopes')) {
            $connection->status = OrgaMaxConnection::STATUS_ACTIVE;
            $connection->blocked_reason = null;
        }
        $connection->save();
        $connection->audit('orgamax_capabilities_updated', ['capabilities' => $clean]);

        return $connection;
    }

    public function disconnect(OrgaMaxConnection $connection): void {
        $connection->forceFill([
            'status' => OrgaMaxConnection::STATUS_DISCONNECTED,
            'bearer_token' => null,
            'token_expires_at' => null,
            'intent_token_hash' => null,
            'intent_expires_at' => null,
        ])->save();
        $connection->audit('orgamax_disconnected', []);
    }

    /**
     * Konto-Snapshot ohne sensible Felder — er erscheint in Admin-UI und
     * Audit; ownershipId/Token gehören nicht hinein.
     *
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function redactAccount(array $account): array {
        unset($account['ownershipId'], $account['token'], $account['apiKey'], $account['secret']);

        return $account;
    }
}
