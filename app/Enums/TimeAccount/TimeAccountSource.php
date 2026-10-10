<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeAccount;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bebuchungsquelle einer Zeitkonto-Regel (MVP-526). Die Quellen sind
 * bewusst der Bestand — das Framework rechnet nichts Eigenes:
 *  - wage_type: `time_rule_results`-Minuten je Lohnart-Muster (513)
 *  - attendance_net: Anwesenheits-Nettominuten je Tag
 *  - absence: genehmigte Abwesenheitstage (Urlaubsart bzw. `sick`)
 *  - shift_type_count: 1 je geleistetem Dienst eines Schichttyps
 *  - external_item: Mengen aus `external_wage_items` je Lohnart-Muster
 */
enum TimeAccountSource: string implements HasLabel {
    use HasOptions;

    case WageType = 'wage_type';
    case AttendanceNet = 'attendance_net';
    case Absence = 'absence';
    case ShiftTypeCount = 'shift_type_count';
    case ExternalItem = 'external_item';

    public function label(): string {
        return match ($this) {
            self::WageType       => __('enums.time_account.time_account_source.wage_type'),
            self::AttendanceNet  => __('enums.time_account.time_account_source.attendance_net'),
            self::Absence        => __('enums.time_account.time_account_source.absence'),
            self::ShiftTypeCount => __('enums.time_account.time_account_source.shift_type_count'),
            self::ExternalItem   => __('enums.time_account.time_account_source.external_item'),
        };
    }
}
