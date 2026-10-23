<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Plugins\Support\MatchingTimeImportService;

/**
 * Das Toggl-Produkt, aus dem das Plugin liest und in das es schreibt (Feature 178).
 *
 * Genau eine Quelle ist aktiv: Track und das neue Toggl gleichen sich über die
 * Live-Synchronisation von Toggl selbst ab, zwei Anschlüsse würden jeden Eintrag
 * doppelt sehen und doppelt schreiben. Die IDs beider Produkte sind getrennte
 * Zahlenräume — Schlüssel und ID-Referenzen tragen deshalb die Quelle im Namen.
 */
enum TogglSource: string implements HasLabel {
    use HasOptions;

    case Track = 'track';
    /** Das neue Toggl (vormals Toggl 2.0 / Toggl Focus), API unter focus.toggl.com. */
    case Focus = 'focus';

    public function label(): string {
        return (string) __('toggl::enums.toggl.source.' . $this->value);
    }

    /** Präfix der Eintragsschlüssel; `toggl:` ist der Bestand aus der Zeit vor der Quellenwahl. */
    public function entryKeyPrefix(): string {
        return match ($this) {
            self::Track => 'toggl:',
            self::Focus => 'focus:',
        };
    }

    public function entryKey(int|string $id): string {
        return $this->entryKeyPrefix() . $id;
    }

    /** Gehört der Schlüssel zu dieser Quelle? CSV-Hashes gehören zu keiner. */
    public function ownsEntryKey(string $entryKey): bool {
        return str_starts_with($entryKey, $this->entryKeyPrefix());
    }

    public function clientIdType(): string {
        return match ($this) {
            self::Track => MatchingTimeImportService::EXT_TYPE_CLIENT_ID,
            self::Focus => 'focus_client_id',
        };
    }

    public function projectIdType(): string {
        return match ($this) {
            self::Track => MatchingTimeImportService::EXT_TYPE_PROJECT_ID,
            self::Focus => 'focus_project_id',
        };
    }

    /** Meldung, wenn für diese Quelle kein Zugang hinterlegt ist. */
    public function missingAccessMessage(): string {
        return match ($this) {
            self::Track => (string) __('Kein Toggl API-Token hinterlegt.'),
            self::Focus => (string) __('Kein API-Schlüssel für das neue Toggl hinterlegt.'),
        };
    }

    public function other(): self {
        return $this === self::Track ? self::Focus : self::Track;
    }
}
