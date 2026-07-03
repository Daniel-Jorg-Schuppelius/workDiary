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

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueVisibility};
use App\Enums\Protocol\{ProtocolEventType, ProtocolSignatureMethod, ProtocolSignatureRole};
use App\Models\{Protocol, ProtocolEvent, ProtocolSignatureToken, User};
use App\Services\OpenIssue\OpenIssueService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
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
    public const DEFAULT_TTL_DAYS = 7;

    public function __construct(private readonly ProtocolService $protocols) {}

    /**
     * Erstellt einen Token; gibt das Klartext-Token zurück (es wird sonst
     * nirgends gespeichert).
     *
     * @param  array<string, mixed>  $data
     * @return array{token: string, model: ProtocolSignatureToken}
     */
    public function issue(Protocol $protocol, User $actor, array $data): array {
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

        ProtocolEvent::query()->create([
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::SignatureRequested,
            'actor_user_id' => $actor->id,
            'payload' => [
                'token_id' => $model->id,
                'role' => $role->value,
                'expires_at' => $expiresAt->toIso8601String(),
                'signer_email' => $model->signer_email,
            ],
            'created_at' => Carbon::now(),
        ]);

        return ['token' => $token, 'model' => $model];
    }

    public function find(string $token): ?ProtocolSignatureToken {
        return ProtocolSignatureToken::query()
            ->where('token_hash', CryptoHelper::hash($token))
            ->first();
    }

    public function open(string $token): ProtocolSignatureToken {
        $record = $this->ensureUsable($token);

        if ($record->opened_at === null) {
            $record->update(['opened_at' => Carbon::now()]);
            ProtocolEvent::query()->create([
                'protocol_id' => $record->protocol_id,
                'event' => ProtocolEventType::SignatureLinkOpened,
                'actor_user_id' => $record->created_by_user_id,
                'payload' => ['token_id' => $record->id],
                'created_at' => Carbon::now(),
            ]);
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
            $actor = $protocol->creator()->firstOrFail(); // Token-Inhaber agiert in Vertretung

            $signatureData = array_merge([
                'role' => $record->role->value,
                'method' => ProtocolSignatureMethod::EmailLink->value,
                'signer_name' => $data['signer_name'] ?? $record->signer_name ?? 'Anonym',
                'signer_email' => $data['signer_email'] ?? $record->signer_email,
                'signature_image_path' => $data['signature_image_path'] ?? null,
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ], $data);

            $signature = $this->protocols->addSignature($protocol, $actor, $signatureData);

            $record->update([
                'used_at' => Carbon::now(),
                'decision' => ProtocolSignatureToken::DECISION_APPROVED,
                'decided_at' => Carbon::now(),
                'signed_signature_id' => $signature->id,
            ]);

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
            $actor = $protocol->creator()->firstOrFail(); // Token-Inhaber agiert in Vertretung

            $record->update([
                'used_at' => Carbon::now(),
                'decision' => ProtocolSignatureToken::DECISION_REJECTED,
                'decision_reason' => $reason,
                'decided_at' => Carbon::now(),
            ]);

            // Offene Punkte am betroffenen Auftrag (Protokoll-Subjekt) bzw.
            // ersatzweise am Protokoll selbst erfassen.
            $issueSubject = $protocol->subject instanceof Model ? $protocol->subject : $protocol;
            $issues = array_values(array_filter(array_map(
                static fn($line): string => trim((string) $line),
                $data['issues'] ?? []
            ), static fn(string $line): bool => $line !== ''));

            if ($issues === []) {
                // Mindestens ein Offener Punkt aus der Pflicht-Begründung.
                $issues = [$reason];
            }

            $openIssues = app(OpenIssueService::class);
            foreach ($issues as $line) {
                $openIssues->create($issueSubject, $actor, [
                    'organization_id' => $protocol->organization_id,
                    'source_type' => OpenIssueSource::CustomerRejection->value,
                    'source_ref_id' => $protocol->id,
                    'title' => Str::limit($line, 200, ''),
                    'description' => __('protocol.signature.rejectIssueDescription', [
                        'protocol' => $protocol->title,
                        'name' => $data['signer_name'] ?? $record->signer_name ?? __('protocol.signature.customer'),
                    ]),
                    'severity' => OpenIssueSeverity::Medium->value,
                    'visibility' => OpenIssueVisibility::Customer->value,
                ]);
            }

            ProtocolEvent::query()->create([
                'protocol_id' => $protocol->id,
                'event' => ProtocolEventType::SignatureRejected,
                'actor_user_id' => $actor->id,
                'payload' => [
                    'token_id' => $record->id,
                    'role' => $record->role->value,
                    'signer_name' => $data['signer_name'] ?? $record->signer_name,
                    'reason' => $reason,
                    'issue_count' => count($issues),
                    'ip' => $data['ip'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $record->refresh();
        });
    }

    private function ensureUsable(string $token): ProtocolSignatureToken {
        $record = $this->find($token);
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
