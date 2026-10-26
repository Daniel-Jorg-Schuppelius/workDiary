<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fakturierungsweg einer Organisation bzw. eines Kunden (Feature 045,
 * Abschnitt „Führendes System"): genau EIN Programm hat die Rechnungshoheit.
 * Bei externer Hoheit (lexoffice/datev) erzeugt WorkDiary keine eigenen
 * Rechnungen, sondern übergibt Positionen per {@see \App\Models\Finance\BillingTransfer}.
 */
enum BillingMode: string implements HasLabel {
    use HasOptions;

    case Workdiary = 'workdiary';
    case Lexoffice = 'lexoffice';
    case Datev = 'datev';
    case OrgaMax = 'orgamax';
    case SevDesk = 'sevdesk';
    case Easybill = 'easybill';

    public function label(): string {
        return (string) __('enums.finance.billing-mode.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Workdiary => 'ghost',
            self::Lexoffice => 'info',
            self::Datev => 'warning',
            self::OrgaMax => 'success',
            self::SevDesk => 'secondary',
            self::Easybill => 'accent',
        };
    }

    /** Programmname ohne „führt“ (Seite „Nummernkreise“, MVP-1149). */
    public function programName(): string {
        return match ($this) {
            self::Workdiary => 'workDiary',
            self::Lexoffice => 'Lexoffice',
            self::Datev => 'DATEV',
            self::OrgaMax => 'orgaMAX',
            self::SevDesk => 'sevDesk',
            self::Easybill => 'easybill',
        };
    }

    /**
     * Führt ein externes Programm die Fakturierung? Dann ist die lokale
     * Rechnungserstellung gesperrt (Hoheitsprinzip).
     */
    public function isExternal(): bool {
        return $this !== self::Workdiary;
    }

    /**
     * Erstes Übergabeziel der Hoheit (Feature 045): das Programm selbst, für
     * DATEV und workDiary das Datei-Paket.
     */
    public function transferTarget(): TransferTarget {
        return match ($this) {
            self::Lexoffice => TransferTarget::Lexoffice,
            self::OrgaMax => TransferTarget::OrgaMax,
            self::SevDesk => TransferTarget::SevDesk,
            self::Easybill => TransferTarget::Easybill,
            self::Datev, self::Workdiary => TransferTarget::File,
        };
    }
}
