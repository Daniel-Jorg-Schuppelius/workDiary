<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine Ausgangsrechnungsposition des Resellers, quellneutral. `headerOnly`
 * markiert Belege, von denen nur der Kopf bekannt ist (Buchungsbelege ohne
 * Positionen): sie decken nie eine Produktperiode, höchstens den Betrag.
 * `voucherText` trägt Titel/Einleitung/Schlusstext des Belegs, `recipient`
 * den Rechnungsempfänger laut Beleg.
 */
final readonly class InvoiceLine {
    public function __construct(
        public string $voucherId,
        public string $voucherNumber,
        public CarbonImmutable $voucherDate,
        public string $voucherType,
        public string $contactId,
        public int $position,
        public string $name,
        public string $description,
        public float $quantity,
        public Money $unitNet,
        public bool $headerOnly = false,
        public string $voucherText = '',
        public string $recipient = '',
        public string $articleId = '',
    ) {}

    /** Artikelnummer aus eckigen Klammern am Anfang des Positionsnamens. */
    public function articleNumber(): string {
        return ArticleCatalog::numberFromText($this->name);
    }

    /** Positionstext (Name + Beschreibung). */
    public function text(): string {
        return trim($this->name . ' ' . $this->description);
    }

    /**
     * Positionstext plus Belegtexte (Titel, Einleitung, Schlusstext) — bei
     * Partnerrechnungen steht der Endkunde meist dort und nicht in der Position.
     */
    public function fullText(): string {
        return trim($this->text() . ' ' . $this->voucherText);
    }

    public function netTotal(): Money {
        return $this->unitNet->times($this->quantity);
    }

    public function key(): string {
        return $this->voucherId . '#' . $this->position;
    }
}
