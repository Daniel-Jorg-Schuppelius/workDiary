<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxCursor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Mail\EmailConnection;
use Illuminate\Support\Carbon;

/**
 * Abrufstand eines Postfachs (MVP-1139): bis wohin verarbeitet ist, unabhängig
 * vom Lesestatus. IMAP führt UIDVALIDITY und die höchste UID, Graph die
 * Ordner-ID und den nächsten Delta-Link. Ein Stand gilt nur für Ordner und
 * Postfachadresse, für die er entstand. `catchingUp` bleibt gesetzt, bis ein
 * Durchgang den aktuellen Stand erreicht; `processed` zählt seine Nachrichten.
 */
final class MailboxCursor {
    public function __construct(
        public readonly ?string $folder = null,
        public readonly ?string $mailbox = null,
        public readonly ?int $uidValidity = null,
        public readonly ?int $lastUid = null,
        public readonly ?string $folderId = null,
        public readonly ?string $link = null,
        public readonly ?string $resumeFrom = null,
        public readonly bool $catchingUp = false,
        public readonly bool $backfill = false,
        public readonly int $processed = 0,
    ) {}

    public static function of(EmailConnection $connection): self {
        $cursor = self::fromArray((array) ($connection->sync_cursor ?? []));

        return $cursor->belongsTo($connection) ? $cursor : self::start($connection);
    }

    /** Neuer Durchgang ab `fetch_since`; `backfill` markiert ein ausdrückliches Nachholen. */
    public static function start(EmailConnection $connection, bool $backfill = false): self {
        return new self(
            folder: (string) $connection->folder,
            mailbox: self::normalizedMailbox($connection->mailbox_address),
            catchingUp: true,
            backfill: $backfill,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        $int = static fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
        $string = static fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '' ? (string) $data[$key] : null;

        return new self(
            folder: $string('folder'),
            mailbox: $string('mailbox'),
            uidValidity: $int('uid_validity'),
            lastUid: $int('last_uid'),
            folderId: $string('folder_id'),
            link: $string('link'),
            resumeFrom: $string('resume_from'),
            catchingUp: (bool) ($data['catching_up'] ?? false),
            backfill: (bool) ($data['backfill'] ?? false),
            processed: $int('processed') ?? 0,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'folder' => $this->folder,
            'mailbox' => $this->mailbox,
            'uid_validity' => $this->uidValidity,
            'last_uid' => $this->lastUid,
            'folder_id' => $this->folderId,
            'link' => $this->link,
            'resume_from' => $this->resumeFrom,
            'catching_up' => $this->catchingUp,
            'backfill' => $this->backfill,
            'processed' => $this->processed,
        ];
    }

    public function belongsTo(EmailConnection $connection): bool {
        return $this->folder !== null
            && $this->folder === (string) $connection->folder
            && $this->mailbox === self::normalizedMailbox($connection->mailbox_address);
    }

    /**
     * Stand verloren (UIDVALIDITY gewechselt, Delta-Link verfallen): neuer
     * Durchgang ab dem letzten erfolgreichen Abruf minus einem Tag, nie vor
     * `fetch_since`. Doppelte fängt der Dublettenschutz der Handler.
     */
    public function restarted(EmailConnection $connection): self {
        $resume = $connection->last_polled_at?->copy()->subDay()->startOfDay();

        return $this->with(['uidValidity' => null, 'lastUid' => null, 'link' => null, 'resumeFrom' => $resume?->toDateString(), 'catchingUp' => true]);
    }

    /** Frühester Empfangstag, den der laufende Durchgang noch verarbeitet. */
    public function since(EmailConnection $connection): ?Carbon {
        $since = $connection->fetch_since?->copy()->startOfDay();
        if ($this->resumeFrom !== null) {
            $resume = Carbon::parse($this->resumeFrom)->startOfDay();
            $since = $since === null || $resume->greaterThan($since) ? $resume : $since;
        }

        return $since;
    }

    /**
     * Kopie mit geänderten Feldern; ein Schlüssel mit `null` leert das Feld.
     *
     * @param array{uidValidity?: ?int, lastUid?: ?int, folderId?: ?string, link?: ?string, resumeFrom?: ?string, catchingUp?: bool, processed?: int} $changes
     */
    public function with(array $changes): self {
        $pick = fn (string $key): mixed => array_key_exists($key, $changes) ? $changes[$key] : $this->{$key};

        return new self(
            folder: $this->folder,
            mailbox: $this->mailbox,
            uidValidity: $pick('uidValidity'),
            lastUid: $pick('lastUid'),
            folderId: $pick('folderId'),
            link: $pick('link'),
            resumeFrom: $pick('resumeFrom'),
            catchingUp: (bool) $pick('catchingUp'),
            backfill: $this->backfill,
            processed: (int) $pick('processed'),
        );
    }

    private static function normalizedMailbox(?string $address): ?string {
        $address = mb_strtolower(trim((string) $address));

        return $address !== '' ? $address : null;
    }
}
