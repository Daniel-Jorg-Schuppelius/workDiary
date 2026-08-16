<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPhase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * GAEB-DA-XML-Austauschphase (Feature 049, MVP-081). Der Code entspricht dem
 * DA-Kürzel der Datei (z. B. X81 = Leistungsverzeichnis ohne Preise) und ist
 * nicht immer rein numerisch: die rechnungsbegründende Unterlage ist `89B`, die
 * Zeitvertragsphasen tragen ein `Z`. Werte und Namen sind identisch zum
 * Toolkit-Enum {@see \ERechnungToolkit\Enums\GaebPhase} — die Brücke läuft
 * über `::from($x->value)`. Die Beta-Phasen von 3.3 (X61, X84P, X98/X99) fehlen
 * bewusst, solange sie nicht freigegeben sind.
 */
enum GaebPhase: string implements HasLabel {
    use HasOptions;

    case QuantitySurvey = '31';          // Mengenermittlung (REB-VB 23.003)
    case CostCatalogue = '50';           // Baukostenkatalog
    case CostEstimate = '51';            // Kostenermittlung
    case CalculationData = '52';         // Kalkulationsdaten
    case Universal = '80';               // Universelle LV-Daten
    case Lv = '81';                      // Leistungsverzeichnis (ohne Preise)
    case Estimate = '82';                // Kostenansatz
    case RequestForBid = '83';           // Angebotsaufforderung
    case Bid = '84';                     // Angebotsabgabe
    case SideBid = '85';                 // Nebenangebot
    case Award = '86';                   // Auftragserteilung
    case AwardConfirmation = '87';       // Auftragsbestätigung
    case Invoice = '89';                 // Rechnung
    case InvoiceAttachment = '89B';      // Rechnungsbegründende Unterlage
    case FrameworkRequestForBid = '83Z'; // Zeitvertrag: Angebotsaufforderung
    case FrameworkBid = '84Z';           // Zeitvertrag: Angebotsabgabe
    case FrameworkCallOff = '86ZE';      // Zeitvertrag: Einzelauftrag
    case FrameworkAgreement = '86ZR';    // Zeitvertrag: Rahmenauftrag
    case PriceInquiry = '93';            // Handel: Preisanfrage
    case PriceOffer = '94';              // Handel: Preisangebot
    case Order = '96';                   // Handel: Bestellung
    case OrderConfirmation = '97';       // Handel: Auftragsbestätigung

    public function label(): string {
        return __('gaeb.phase.' . $this->value);
    }

    /** Gehört die Phase zu den LV-Daten (X80 bis X87)? */
    public function isBillOfQuantity(): bool {
        return match ($this) {
            self::Universal, self::Lv, self::Estimate, self::RequestForBid,
            self::Bid, self::SideBid, self::Award, self::AwardConfirmation => true,
            default => false,
        };
    }

    /** Trägt diese Phase verbindliche Einheits-/Gesamtpreise? */
    public function carriesPrices(): bool {
        return match ($this) {
            self::Lv, self::RequestForBid, self::FrameworkRequestForBid,
            self::QuantitySurvey, self::PriceInquiry => false,
            default => true,
        };
    }

    /**
     * Trägt diese Phase die Texte des Leistungsverzeichnisses? Die Angebotsabgabe
     * liefert Preise zu einem bereits bekannten LV zurück — Bezeichnungen sowie
     * Kurz- und Langtexte haben dort keinen Platz, nur die vom Bieter gefüllten
     * Textergänzungen (GAEB DA XML 3.3, X84-Schema).
     */
    public function carriesTexts(): bool {
        return match ($this) {
            self::Bid, self::FrameworkBid => false,
            default => true,
        };
    }

    /**
     * Müssen Menge und Einheit an der Position stehen? Die Angebotsabgabe ist
     * die einzige Phase, in der sie entfallen dürfen — der Bieter überträgt
     * dort nur Preise und Textergänzungen zum bereits bekannten LV
     * (GAEB DA XML 3.3, Regeln für X80 bis X86, Objekt Item, Regel 7).
     */
    public function carriesQuantities(): bool {
        return match ($this) {
            self::Bid, self::FrameworkBid => false,
            default => true,
        };
    }

    /** Tolerant aus dem DP-/Phasen-Attribut der Datei ableiten (z. B. "84"). */
    public static function fromCode(?string $code): ?self {
        if ($code === null) {
            return null;
        }

        // Nur den führenden Formatbuchstaben abschneiden: der Code selbst darf
        // auf einen Buchstaben enden (89B, 86ZR).
        $normalised = strtoupper(trim($code));
        if (preg_match('/^[XDP](\d.*)$/', $normalised, $matches) === 1) {
            $normalised = $matches[1];
        }

        return self::tryFrom($normalised);
    }
}
