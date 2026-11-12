<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureTokenService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Enums\Protocol\{ProtocolEventType, ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus};
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolEvent, ProtocolSignatureToken};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Verwaltet Einmal-Tokens für `emailLink`-Signaturen (MVP-022 §3.3).
 *
 * Tokens haben eine 7-tägige Default-Gueltigkeit, sind URL-sicher, werden
 * ausschliesslich als SHA-256-Hash persistiert und sind nach einmaliger
 * Nutzung verbraucht.
 */
class ProtocolSignatureTokenService {
    public const int DEFAULT_TTL_DAYS = 7;

    public function __construct(private readonly ProtocolService $protocols) {}

    /**
     * Erstellt einen Token; gibt das Klartext-Token zurück (es wird sonst
     * nirgends gespeichert).
     *
     * @param  array<string, mixed>  $data
     * @return array{token: string, model: ProtocolSignatureToken}
     */
    public function issue(Protocol $protocol, User $actor, array $data): array {
        $this->assertAwaitingSignature($protocol);
        $role = $this->parseRole($data['role'] ?? ProtocolSignatureRole::Customer->value);
        $token = Str::random(48);
        $hash = CryptoHelper::hash($token);

        $ttlDays = (int) ($data['ttl_days'] ?? self::DEFAULT_TTL_DAYS);
        $expiresAt = Carbon::now()->addDays(max(1, $ttlDays));

        $model = ProtocolSignatureToken::query()->create([
            'protocol_id' => $protocol->id,
            'role' => $role->value,
            'signer_name' => $data['signer_name'] ?? null,
            'signer_email' => $data['signer_email'] ?? null,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'created_by_user_id' => $actor->id,
        ]);

        ProtocolEvent::log(null, ProtocolEventType::SignatureRequested, [
            'token_id' => $model->id,
            'role' => $role->value,
            'expires_at' => $expiresAt->toIso8601String(),
            'signer_email' => $model->signer_email,
        ], $actor->id, extra: ['protocol_id' => $protocol->id]);

        return ['token' => $token, 'model' => $model];
    }

    /**
     * Widerruf eines externen Signatur-Links (Feature 012 MVP; Vollaudit
     * 2026-07, M6): expires_at wird auf jetzt gesetzt — isUsable() greift
     * damit überall ohne neue Spalte; der Widerruf ist als ProtocolEvent
     * nachvollziehbar. Eingelöste Links sind nicht widerrufbar.
     */
    public function revoke(ProtocolSignatureToken $token, User $actor): ProtocolSignatureToken {
        if ($token->used_at !== null) {
            throw new \RuntimeException((string) __('Dieser Link wurde bereits eingelöst und kann nicht widerrufen werden.'));
        }

        return DB::transaction(function () use ($token, $actor): ProtocolSignatureToken {
            $token->forceFill(['expires_at' => Carbon::now()])->save();

            ProtocolEvent::log(null, ProtocolEventType::SignatureLinkRevoked, [
                'token_id' => $token->id,
                'role' => $token->role->value,
                'signer_email' => $token->signer_email,
            ], $actor->id, extra: ['protocol_id' => $token->protocol_id]);

            return $token;
        });
    }

    public function open(string $token): ProtocolSignatureToken {
        $record = $this->ensureUsable($token);
        $this->assertAwaitingSignature($record->protocol()->firstOrFail());

        if ($record->opened_at === null) {
            $record->update(['opened_at' => Carbon::now()]);
            ProtocolEvent::log(null, ProtocolEventType::SignatureLinkOpened, ['token_id' => $record->id], $record->created_by_user_id, extra: ['protocol_id' => $record->protocol_id]);
        }

        return $record;
    }

    /**
     * Loest den Token ein und erstellt die Signatur via ProtocolService.
     *
     * @param  array<string, mixed>  $data
     */
    public function redeem(string $token, array $data): ProtocolSignatureToken {
        $record = $this->ensureUsable($token);

        return DB::transaction(function () use ($record, $data): ProtocolSignatureToken {
            $protocol = $record->protocol()->firstOrFail();
            $this->assertAwaitingSignature($protocol);
            $actor = $protocol->creator()->firstOrFail(); // Token-Inhaber agiert in Vertretung

            // Feste Schlüssel statt Durchreichen: Rolle, Methode und Uploader bestimmt der Link, nicht die Eingabe.
            $signatureData = [
                'role' => $record->role->value,
                'method' => ProtocolSignatureMethod::EmailLink->value,
                'signer_name' => $data['signer_name'] ?? $record->signer_name ?? 'Anonym',
                'signer_email' => $data['signer_email'] ?? $record->signer_email,
                'signature_image' => $data['signature_image'] ?? null,
                'uploaded_by_user_id' => null,
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ];

            $signature = $this->protocols->addSignature($protocol, $actor, $signatureData);

            $record->update([
                'used_at' => Carbon::now(),
                'decision' => ProtocolSignatureToken::DECISION_APPROVED,
                'decided_at' => Carbon::now(),
                'signed_signature_id' => $signature->id,
            ]);

            // Wer den Link ausgestellt hat, erfährt von der Unterschrift.
            $this->protocols->notifyCustomerSigned($protocol, $record->creator, $signature->signer_name);

            return $record->refresh();
        });
    }

    /**
     * Lehnt den vorgelegten Vorgang ab (Reject-Pfad zum Signaturlink).
     *
     * Pflicht-Begründung; je gemeldetem Mangel wird ein Offener Punkt
     * (OpenIssue, Quelle „customerRejection", Sichtbarkeit „customer") am
     * betroffenen Auftrag/Protokoll angelegt. Der Token wird verbraucht und
     * die Entscheidung revisionssicher als ProtocolEvent protokolliert.
     *
     * @param  array{reason?: string|null, signer_name?: string|null, issues?: list<string>, ip?: string|null, user_agent?: string|null}  $data
     */
    public function reject(string $token, array $data): ProtocolSignatureToken {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Begründung ist bei einer Ablehnung Pflicht.');
        }

        $record = $this->ensureUsable($token);

        return DB::transaction(function () use ($record, $data, $reason): ProtocolSignatureToken {
            $protocol = $record->protocol()->firstOrFail();
            $this->assertAwaitingSignature($protocol);
            $actor = $protocol->creator()->firstOrFail(); // Token-Inhaber agiert in Vertretung

            $record->update([
                'used_at' => Carbon::now(),
                'decision' => ProtocolSignatureToken::DECISION_REJECTED,
                'decision_reason' => $reason,
                'decided_at' => Carbon::now(),
            ]);

            $this->protocols->rejectByCustomer($protocol, $actor, $record->creator, [
                'reason' => $reason,
                'signer_name' => $data['signer_name'] ?? $record->signer_name,
                'issues' => $data['issues'] ?? [],
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'token_id' => $record->id,
                'role' => $record->role->value,
                'channel' => ProtocolSignatureMethod::EmailLink->value,
            ]);

            return $record->refresh();
        });
    }

    /**
     * Unterschrieben und abgelehnt wird nur am eingefrorenen Inhalt („in
     * Prüfung“): danach ist das Protokoll abgeschlossen, davor noch änderbar.
     */
    private function assertAwaitingSignature(Protocol $protocol): void {
        if ($protocol->status !== ProtocolStatus::InReview) {
            throw new RuntimeException((string) __('protocol.signature.notAwaiting'));
        }
    }

    private function ensureUsable(string $token): ProtocolSignatureToken {
        $record = ProtocolSignatureToken::findByAccessToken($token);
        if ($record === null) {
            throw new RuntimeException('Signaturlink ist unbekannt oder wurde widerrufen.');
        }
        if (! $record->isUsable()) {
            throw new RuntimeException('Signaturlink ist abgelaufen oder bereits eingelöst.');
        }
        return $record;
    }

    private function parseRole(string $value): ProtocolSignatureRole {
        $role = ProtocolSignatureRole::tryFrom($value);
        if (! $role instanceof ProtocolSignatureRole) {
            throw new InvalidArgumentException(sprintf('Unbekannte Signatur-Rolle „%s".', $value));
        }
        return $role;
    }
}
