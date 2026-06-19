<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

/**
 * Definiert die in MVP-025 unterstuetzten Schritt-Typen einer
 * Prozedurvorlage (siehe docs/prozedurvorlagen.md §5).
 */
enum ProcedureStepType: string {
    case Confirm = 'confirm';
    case Text = 'text';
    case Number = 'number';
    case Choice = 'choice';
    case Photo = 'photo';
    case File = 'file';
    case Backup = 'backup';
    case Signature = 'signature';
    case Material = 'material';
    case Dienstmittel = 'dienstmittel';
    case Freigabe = 'freigabe';
    case Messreihe = 'messreihe';
    case LinkProtocol = 'link_protocol';
    case LinkTest = 'link_test';
    case Wait = 'wait'; // Warte-/Trocken-/Aushärtezeit (serverseitig, Feature 047 MVP-064)

    public function label(): string {
        return (string) __('enums.procedure.step-type.' . $this->value);
    }
}
