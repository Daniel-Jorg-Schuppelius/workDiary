<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainHolderWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Contracts;

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\DomainProjection;
use App\Models\Platform\User;

/**
 * Halter einer Domain in der Domainverwaltung schreiben und Vorschläge dafür
 * lesen (Feature 152, Sammelzuordnung 2026-10-27). Der Halter einer Domain
 * lebt in der Projektion; das Register spiegelt ihn nur. Ohne Domain-Modul
 * antwortet die Null-Bindung neutral: nichts geschrieben, keine Vorschläge.
 */
interface DomainHolderWriter {
    /** Kunde (optional mit Endkunde) als Halter der Domain setzen. Liefert, ob die Projektion geschrieben wurde. */
    public function assign(DomainProjection $projection, Customer $customer, ?User $actor = null, ?ForeignCustomer $foreignCustomer = null): bool;

    /** Domain als eigenen Bestand kennzeichnen. Liefert, ob die Projektion geschrieben wurde. */
    public function markOwn(DomainProjection $projection): bool;

    /**
     * Nachvollziehbare Kundenvorschläge für eine Domain ohne Halter.
     *
     * @return list<array{customer: Customer, reason: string}>
     */
    public function suggestions(DomainProjection $projection): array;
}
