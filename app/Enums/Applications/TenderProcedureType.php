<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderProcedureType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use ERechnungToolkit\Enums\GaebAwardCategory;

/**
 * Vergabeart eines Vergabevorgangs — entlang des Rechts, nicht entlang GAEB.
 *
 * Die Schwellenwertlage entscheidet, welches Regelwerk gilt: **unterschwellig**
 * die VOB/A Abschnitt 1 (Bau) bzw. die UVgO (Liefer- und Dienstleistungen),
 * **oberschwellig** die VgV und die VOB/A-EU. Dieselbe Sache heißt dort anders —
 * die Öffentliche Ausschreibung entspricht dem Offenen Verfahren —, und beide
 * Begriffe nebeneinander zu führen wäre falsch: Es sind unterschiedliche
 * Rechtsgrundlagen mit unterschiedlichen Fristen.
 *
 * **GAEB ist nur eine Projektion.** Das Format ist VOB-zentriert und kennt die
 * UVgO-Arten Verhandlungsvergabe und Direktauftrag nicht; {@see toGaeb()} gibt
 * für sie `null` zurück, statt sie auf einen ähnlich klingenden Wert zu
 * verbiegen.
 */
enum TenderProcedureType: string {
    // ── Unterschwellig: VOB/A Abschnitt 1 ────────────────────────────────
    case PublicInvitation = 'public_invitation';                 // Öffentliche Ausschreibung
    case RestrictedInvitation = 'restricted_invitation';         // Beschränkte Ausschreibung ohne Teilnahmewettbewerb
    case RestrictedInvitationWithCall = 'restricted_invitation_call'; // … mit Teilnahmewettbewerb
    case DirectContract = 'direct_contract';                     // Freihändige Vergabe

    // ── Unterschwellig: UVgO (kennt GAEB nicht) ──────────────────────────
    case NegotiatedAward = 'negotiated_award';                   // Verhandlungsvergabe ohne Teilnahmewettbewerb
    case NegotiatedAwardWithCall = 'negotiated_award_call';      // … mit Teilnahmewettbewerb
    case DirectOrder = 'direct_order';                           // Direktauftrag

    // ── Oberschwellig: VgV / VOB/A-EU ────────────────────────────────────
    case OpenProcedure = 'open_procedure';                       // Offenes Verfahren
    case RestrictedProcedure = 'restricted_procedure';           // Nichtoffenes Verfahren
    case NegotiatedProcedure = 'negotiated_procedure';           // Verhandlungsverfahren ohne Teilnahmewettbewerb
    case NegotiatedProcedureWithCall = 'negotiated_procedure_call'; // … mit Teilnahmewettbewerb
    case CompetitiveDialogue = 'competitive_dialogue';           // Wettbewerblicher Dialog
    case InnovationPartnership = 'innovation_partnership';       // Innovationspartnerschaft

    public function label(): string {
        return (string) __('values.procedure_' . $this->value);
    }

    /**
     * Gilt diese Art oberhalb der EU-Schwellenwerte? Unterhalb und oberhalb
     * gelten verschiedene Regelwerke — eine Öffentliche Ausschreibung
     * oberschwellig auszuschreiben ist ein Verfahrensfehler, kein Synonym.
     */
    public function isAboveThreshold(): bool {
        return match ($this) {
            self::OpenProcedure, self::RestrictedProcedure,
            self::NegotiatedProcedure, self::NegotiatedProcedureWithCall,
            self::CompetitiveDialogue, self::InnovationPartnership => true,
            default => false,
        };
    }

    /**
     * Ging dem Verfahren ein Teilnahmewettbewerb voraus? Davon hängen Fristen
     * und Bieterkreis ab.
     */
    public function hasCallForParticipation(): bool {
        return match ($this) {
            self::RestrictedInvitationWithCall, self::NegotiatedAwardWithCall,
            self::RestrictedProcedure, self::NegotiatedProcedureWithCall,
            self::CompetitiveDialogue, self::InnovationPartnership => true,
            default => false,
        };
    }

    /**
     * Entsprechung im GAEB-Datenaustausch. `null` heißt: Das Format kennt diese
     * Art nicht — dann bleibt das Feld in der Datei leer, statt eine falsche
     * Verfahrensart zu behaupten.
     */
    public function toGaeb(): ?GaebAwardCategory {
        return match ($this) {
            self::PublicInvitation => GaebAwardCategory::PublicInvitation,
            self::RestrictedInvitation => GaebAwardCategory::RestrictedInvitation,
            self::RestrictedInvitationWithCall => GaebAwardCategory::RestrictedInvitationWithCall,
            self::DirectContract => GaebAwardCategory::DirectContract,
            self::OpenProcedure => GaebAwardCategory::OpenProcedure,
            self::RestrictedProcedure => GaebAwardCategory::RestrictedProcedure,
            self::NegotiatedProcedure => GaebAwardCategory::NegotiatedProcedure,
            self::NegotiatedProcedureWithCall => GaebAwardCategory::NegotiatedProcedureWithCall,
            self::CompetitiveDialogue => GaebAwardCategory::CompetitiveDialogue,
            self::InnovationPartnership => GaebAwardCategory::InnovationPartnership,
            // Verhandlungsvergabe und Direktauftrag stammen aus der UVgO.
            self::NegotiatedAward, self::NegotiatedAwardWithCall, self::DirectOrder => null,
        };
    }

    /**
     * Die zur Schwellenwertlage zulässigen Arten. Die Filterung ist keine
     * Bequemlichkeit: Eine oberschwellige Vergabe im falschen Verfahren ist
     * angreifbar.
     *
     * @return list<self>
     */
    public static function forThreshold(bool $aboveThreshold): array {
        return array_values(array_filter(
            self::cases(),
            static fn (self $case): bool => $case->isAboveThreshold() === $aboveThreshold
        ));
    }
}
