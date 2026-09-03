<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceLinePool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Services\Reselling\Contracts\InvoiceLineSource;
use Carbon\CarbonImmutable;

/**
 * Lädt Rechnungspositionen je Kontakt genau einmal und hält sie für alle
 * Schritte eines Laufs vor (Fremdkunden-Erkennung über Belegtexte und
 * Abgleich). Ein Kontakt wird mit dem Vereinigungsfenster aller Anfragen
 * geladen; eine spätere, engere Anfrage kommt aus dem Cache.
 */
final class InvoiceLinePool {
    /** @var array<string, list<InvoiceLine>> */
    private array $lines = [];

    /** @var array<string, array{from: CarbonImmutable, to: CarbonImmutable}> */
    private array $windows = [];

    /** @var array<string, string> Kontakt → Fehlermeldung */
    private array $errors = [];

    public function __construct(private readonly InvoiceLineSource $source) {}

    /**
     * @return list<InvoiceLine>
     *
     * @throws \Throwable bei Lesefehlern der Quelle (der Aufrufer entscheidet)
     */
    public function linesFor(string $contactId, CarbonImmutable $from, CarbonImmutable $to): array {
        $window = $this->windows[$contactId] ?? null;
        if ($window === null || $from->lessThan($window['from']) || $to->greaterThan($window['to'])) {
            $from = $window !== null && $window['from']->lessThan($from) ? $window['from'] : $from;
            $to = $window !== null && $window['to']->greaterThan($to) ? $window['to'] : $to;
            $this->lines[$contactId] = $this->source->linesForContact($contactId, $from, $to);
            $this->windows[$contactId] = ['from' => $from, 'to' => $to];
        }

        return array_values(array_filter($this->lines[$contactId], static fn(InvoiceLine $line): bool => ! $line->voucherDate->lessThan($from) && ! $line->voucherDate->greaterThan($to)));
    }

    /**
     * Wie linesFor(), aber Lesefehler werden gemerkt statt geworfen.
     *
     * @return list<InvoiceLine>
     */
    public function tryLinesFor(string $contactId, CarbonImmutable $from, CarbonImmutable $to): array {
        try {
            return $this->linesFor($contactId, $from, $to);
        } catch (\Throwable $e) {
            $this->errors[$contactId] = $e->getMessage();

            return [];
        }
    }

    /**
     * Alle bisher geladenen Positionen aller Kontakte.
     *
     * @return list<InvoiceLine>
     */
    public function all(): array {
        $all = [];
        foreach ($this->lines as $lines) {
            array_push($all, ...$lines);
        }

        return $all;
    }

    /**
     * @return list<string>
     */
    public function contactIds(): array {
        return array_keys($this->lines);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array {
        return $this->errors;
    }
}
