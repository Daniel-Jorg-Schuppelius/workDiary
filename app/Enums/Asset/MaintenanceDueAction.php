<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceDueAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Asset;

/**
 * Was der Fälligkeits-Scanner (`maintenance:scan-due`) bei einem fälligen
 * Wartungsplan erzeugt (Feature 010 → Rang 43). `None` = nur Audit-Trail (das
 * bisherige Verhalten, Default), `Ticket` = ein Service-Ticket je Fälligkeit
 * (idempotent). Ein DiaryEntry-Entwurf ist als Folgeausbau vorgesehen (braucht
 * eine klare Eigentümer-/Owner-Semantik für den systemgenerierten Entwurf).
 */
enum MaintenanceDueAction: string {
    case None = 'none';
    case Ticket = 'ticket';

    public function label(): string {
        return match ($this) {
            self::None => __('enums.maintenance.due_action.none'),
            self::Ticket => __('enums.maintenance.due_action.ticket'),
        };
    }
}
