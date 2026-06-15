<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingBatchPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\DatevBookingBatch;
use App\Models\User;

/**
 * Policy für DATEV-Buchungsstapel (Feature 045, Priorität 2): Lesen, Anlegen,
 * Finalisieren und Herunterladen laufen über finance.booking.export
 * (Buchhaltung + Admin). Die Buchhaltungs-Konfiguration (Konten/Steuerschlüssel/
 * Beraternummer) ist separat über finance.config geschützt — siehe
 * {@see updateConfig()} im Controller.
 */
class DatevBookingBatchPolicy {
    public function viewAny(User $user): bool {
        return $user->can(P::FinanceBookingExport->value)
            || $user->can(P::FinanceViewAny->value);
    }

    public function view(User $user, DatevBookingBatch $batch): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::FinanceBookingExport->value);
    }

    public function finalize(User $user, DatevBookingBatch $batch): bool {
        return $user->can(P::FinanceBookingExport->value) && ! $batch->isFinal();
    }

    /** Erzeugte CSV herunterladen (pfadsicher zusätzlich im Controller geprüft). */
    public function download(User $user, DatevBookingBatch $batch): bool {
        return $user->can(P::FinanceBookingExport->value);
    }

    /** Buchhaltungs-Konfiguration verwalten (Admin). */
    public function configure(User $user): bool {
        return $user->can(P::FinanceConfig->value);
    }
}
