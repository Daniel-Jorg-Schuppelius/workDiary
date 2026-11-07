<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportingDocumentCarrier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Customer\Customer;
use App\Models\Invoicing\InvoiceSupportingDocument;
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Träger rechnungsbegründender Unterlagen (Feature 196): eine lokale Rechnung
 * oder eine extern ausgestellte Rechnung. Unterlagen, Leistungsnachweis und
 * Einfrieren laufen für beide über denselben Dienst.
 */
interface SupportingDocumentCarrier {
    /** @return HasMany<InvoiceSupportingDocument, covariant \Illuminate\Database\Eloquent\Model> */
    public function supportingDocuments(): HasMany;

    /** Nummer, auf die sich die Kennungen der Unterlagen (BT-122) beziehen. */
    public function supportingReferenceNumber(): string;

    /** Grund, warum keine Unterlagen mehr hinzukommen oder neu entstehen dürfen; null = erlaubt. */
    public function supportingDocumentsLockedReason(): ?string;

    public function carrierOrganizationId(): int;

    public function carrierCustomer(): ?Customer;

    public function carrierOrganization(): ?Organization;
}
