<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ExternalParticipant;

use App\Enums\ExternalParticipant\{ExternalAbility, ExternalParty};
use App\Models\{Comment, ExternalParticipant, ExternalParticipantEvent, User};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Request;

/**
 * Kontextbezogene externe Einladungen (Feature 033). Token-Muster strikt
 * analog {@see \App\Services\Protocol\ProtocolSignatureTokenService} /
 * {@see \App\Services\Isms\AuditPackageService}: NUR der SHA-256-Hash wird
 * persistiert, der Klartext-Token wird genau EINMAL bei der Einladung
 * zurückgegeben (und sonst nirgends gespeichert).
 *
 * Alle externen Aktionen (Zugriff, Kommentar, Upload, Bestätigung) werden in
 * external_participant_events nachweisbar protokolliert.
 */
class ExternalParticipantService {
    public const DEFAULT_TTL_DAYS = 14;

    public const MIN_TTL_DAYS = 1;

    public const MAX_TTL_DAYS = 180;

    public const MAX_UPLOAD_BYTES = 25 * 1024 * 1024; // 25 MB

    /** @var list<string> */
    public const UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    /** @var list<string> */
    public const UPLOAD_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    /**
     * Lädt einen Externen an ein Subject ein. Gibt den Klartext-Token zurück
     * (wird sonst nirgends gespeichert — nur der Hash landet in der DB).
     *
     * @param  Model  $subject  DiaryEntry|Protocol|Document — muss organization_id besitzen
     * @param  array{name: string, email?: ?string, role?: ?string, party?: string, abilities?: list<string>, ttl_days?: int}  $data
     * @return array{token: string, model: ExternalParticipant}
     */
    public function invite(Model $subject, User $actor, array $data): array {
        $token = Str::random(48);

        $party = ExternalParty::tryFrom((string) ($data['party'] ?? '')) ?? ExternalParty::Other;
        $abilities = $this->sanitizeAbilities($data['abilities'] ?? []);

        $ttl = (int) ($data['ttl_days'] ?? self::DEFAULT_TTL_DAYS);
        $ttl = max(self::MIN_TTL_DAYS, min(self::MAX_TTL_DAYS, $ttl));

        $model = ExternalParticipant::query()->create([
            'organization_id' => $subject->getAttribute('organization_id'),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'name' => trim((string) $data['name']),
            'email' => isset($data['email']) && $data['email'] !== '' ? trim((string) $data['email']) : null,
            'role' => isset($data['role']) && $data['role'] !== '' ? trim((string) $data['role']) : null,
            'party' => $party->value,
            'token_hash' => CryptoHelper::hash($token),
            'abilities' => $abilities,
            'expires_at' => Carbon::now()->addDays($ttl),
            'invited_by_user_id' => $actor->id,
            'created_at' => Carbon::now(),
        ]);

        // Interner Nachweis (org-AuditLog) der Einladung am Subject.
        if (method_exists($subject, 'audit')) {
            $subject->audit('external.participant.invited', [
                'participant_id' => $model->id,
                'name' => $model->name,
                'party' => $party->value,
                'abilities' => $abilities,
                'expires_at' => $model->expires_at->toIso8601String(),
            ]);
        }

        return ['token' => $token, 'model' => $model];
    }

    /**
     * Begrenzt die übergebenen Aktionsrechte auf die zusätzlich wählbaren
     * (View ist implizit und wird nie als Flag gespeichert).
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function sanitizeAbilities(array $abilities): array {
        $allowed = array_map(static fn(ExternalAbility $a): string => $a->value, ExternalAbility::selectable());

        return array_values(array_unique(array_filter(
            $abilities,
            static fn(string $a): bool => \in_array($a, $allowed, true),
        )));
    }

    /**
     * Löst einen Klartext-Token für den öffentlichen Zugriff auf: Hash-Match
     * + nicht widerrufen + nicht abgelaufen — sonst null (Controller
     * antwortet 404, keine Detail-Preisgabe). Scope-frei, da der öffentliche
     * Zugriff keine Org-Session besitzt.
     */
    public function resolveUsable(string $plainToken): ?ExternalParticipant {
        $record = ExternalParticipant::query()
            ->withoutGlobalScopes()
            ->where('token_hash', CryptoHelper::hash($plainToken))
            ->first();

        return $record !== null && $record->isUsable() ? $record : null;
    }

    /** Markiert den ersten/letzten Zugriff und protokolliert ihn. */
    public function registerAccess(ExternalParticipant $participant): void {
        $now = Carbon::now();
        $fill = ['last_access_at' => $now];
        if ($participant->accepted_at === null) {
            $fill['accepted_at'] = $now;
        }
        $participant->forceFill($fill)->save();

        $this->log($participant, 'accessed');
    }

    /** Externer Kommentar (erfordert ability comment). */
    public function addComment(ExternalParticipant $participant, Model $subject, string $body): Comment {
        $comment = Comment::query()->create([
            'organization_id' => $participant->organization_id,
            'commentable_type' => $subject->getMorphClass(),
            'commentable_id' => $subject->getKey(),
            'user_id' => null,
            'external_participant_id' => $participant->id,
            'body' => $body,
        ]);

        $this->log($participant, 'commented', ['comment_id' => $comment->id]);

        return $comment;
    }

    /**
     * Externer Datei-/Foto-Upload (erfordert ability upload). Datei wird am
     * Subject als Attachment ohne user_id (extern) abgelegt.
     *
     * @return array{ok: bool, error?: string}
     */
    public function addUpload(ExternalParticipant $participant, Model $subject, UploadedFile $file): array {
        if (! method_exists($subject, 'attachments')) {
            return ['ok' => false, 'error' => 'unsupported'];
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        if (! in_array($ext, self::UPLOAD_EXTENSIONS, true)) {
            return ['ok' => false, 'error' => 'type'];
        }

        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($serverMime, self::UPLOAD_MIMES, true)) {
            return ['ok' => false, 'error' => 'type'];
        }

        $folder = 'attachments/external/' . Carbon::now()->format('Y/m');
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs($folder, $filename, 'local');

        /** @var \App\Models\Attachment $attachment */
        $attachment = $subject->attachments()->create([
            'user_id' => null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
            'mime' => $serverMime,
            'size' => $file->getSize(),
        ]);

        $this->log($participant, 'uploaded', [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ]);

        return ['ok' => true];
    }

    /** Externe Bestätigung/Abnahme (erfordert ability confirm). */
    public function confirm(ExternalParticipant $participant, string $note = ''): void {
        $this->log($participant, 'confirmed', $note !== '' ? ['note' => $note] : []);
    }

    /** Widerruft eine Einladung (idempotent) und protokolliert den Widerruf. */
    public function revoke(ExternalParticipant $participant, User $actor): ExternalParticipant {
        if ($participant->revoked_at === null) {
            $participant->forceFill(['revoked_at' => Carbon::now()])->save();

            $subject = $participant->subject()->first();
            if ($subject !== null && method_exists($subject, 'audit')) {
                $subject->audit('external.participant.revoked', [
                    'participant_id' => $participant->id,
                    'actor_user_id' => $actor->id,
                ]);
            }
        }

        return $participant;
    }

    /**
     * Append-only Nachweis einer externen Aktion. Akteur ist der externe
     * Beteiligte (über external_participant_id), nicht ein interner User.
     *
     * @param  array<string, mixed>  $payload
     */
    public function log(ExternalParticipant $participant, string $event, array $payload = []): ExternalParticipantEvent {
        return ExternalParticipantEvent::query()->create([
            'external_participant_id' => $participant->id,
            'event' => $event,
            'payload' => $payload !== [] ? $payload : null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => Carbon::now(),
        ]);
    }

    private function sanitizeFilename(string $name): string {
        $name = basename($name);
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name) ?? 'datei';

        return Str::limit($name, 200, '');
    }
}
