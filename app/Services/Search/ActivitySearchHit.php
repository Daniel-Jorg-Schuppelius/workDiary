<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchHit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Search\SearchSourceType;
use App\Support\{CarbonFmt, Formats};
use Carbon\CarbonInterface;

/** Ein Treffer der Tätigkeitsrecherche, fertig für Liste, Palette und KI. */
final class ActivitySearchHit {
    /**
     * @param  list<array{0: string, 1: bool}>  $titleSegments
     * @param  list<array{0: string, 1: bool}>  $snippet
     */
    public function __construct(
        public readonly SearchSourceType $type,
        public readonly int $sourceId,
        public readonly string $title,
        public readonly array $titleSegments,
        public readonly array $snippet,
        public readonly ?CarbonInterface $occurredAt,
        public readonly bool $dateOnly,
        public readonly ?int $minutes,
        public readonly ?string $userName,
        public readonly ?int $customerId,
        public readonly ?string $customerName,
        public readonly ?int $foreignCustomerId,
        public readonly ?string $foreignCustomerName,
        public readonly ?int $projectId,
        public readonly ?string $projectName,
        public readonly ?string $url,
        public readonly ?string $excerpt,
    ) {}

    /** Bei Zeiten und Stundenzetteln ist der Titel nur die erste Textzeile — dann genügt der Auszug. */
    public function showsTitle(): bool {
        return $this->snippet === [] || ! in_array($this->type, [SearchSourceType::TimeEntry, SearchSourceType::Timesheet], true);
    }

    public function dateLabel(): ?string {
        if ($this->occurredAt === null) {
            return null;
        }

        // Reine Datumswerte stehen als 00:00 UTC im Index — nicht verschieben.
        return $this->dateOnly ? CarbonFmt::fdate($this->occurredAt) : CarbonFmt::fdate(CarbonFmt::orgTz($this->occurredAt));
    }

    public function timeLabel(): ?string {
        return $this->occurredAt === null || $this->dateOnly ? null : CarbonFmt::ftime(CarbonFmt::orgTz($this->occurredAt));
    }

    /** „Partner › Endkunde" bzw. nur der Kunde. */
    public function customerLabel(): ?string {
        $label = implode(' › ', array_filter([$this->customerName, $this->foreignCustomerName], static fn(?string $v): bool => $v !== null && $v !== ''));

        return $label !== '' ? $label : null;
    }

    public function durationLabel(): ?string {
        return $this->minutes !== null && $this->minutes > 0 ? Formats::duration($this->minutes, 'clock') : null;
    }
}
