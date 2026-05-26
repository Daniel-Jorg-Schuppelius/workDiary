<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMatchPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

/**
 * Strategie bei Daten-Konflikten zwischen Lexoffice (Remote) und workDiary
 * (Local) während eines Pull-Syncs. Push überschreibt Lexoffice in jedem Fall.
 */
enum LexofficeMatchPolicy: string {
    /** Lexoffice ist Quelle der Wahrheit — überschreibt lokale Felder. */
    case LexofficeWins = 'lexoffice_wins';

    /** Lokal pflegt der Anwender — Pull legt nur Links/Neuanlagen an, ändert aber keine existierenden Felder. */
    case LocalWins = 'local_wins';

    /** Konflikt wird in pending_external_conflicts geschrieben und braucht manuelle Auflösung. */
    case ManualReview = 'manual_review';

    public function label(): string {
        return match ($this) {
            self::LexofficeWins => (string) __('Lexoffice gewinnt (Remote überschreibt lokal)'),
            self::LocalWins => (string) __('Lokal gewinnt (Pull legt nur neu an)'),
            self::ManualReview => (string) __('Manuelle Prüfung (Konflikt-Inbox)'),
        };
    }

    public static function fromSetting(?string $value): self {
        return self::tryFrom((string) $value) ?? self::ManualReview;
    }
}
