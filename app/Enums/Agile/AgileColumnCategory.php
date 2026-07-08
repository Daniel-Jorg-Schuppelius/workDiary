<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileColumnCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Agile;

/**
 * Spaltenkategorie eines Projektboards (Feature 064) — mappt auf den
 * Task-Status (open/in_progress/done) für den beidseitigen Status-Sync.
 */
enum AgileColumnCategory: string {
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('Offen'),
            self::InProgress => (string) __('In Arbeit'),
            self::Done => (string) __('Erledigt'),
        };
    }
}
