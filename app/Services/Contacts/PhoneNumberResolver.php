<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneNumberResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\Platform\Organization;

/**
 * Erweiterungspunkt für Rückwärts-Telefonauskünfte: Punkt-Abfrage einer
 * einzelnen Rufnummer (E.164 → Anschlussinhaber). Anders als
 * {@see ExternalPhoneContactSource} (aufzählbares eigenes Verzeichnis) fragt ein
 * Resolver je Nummer einen externen Dienst und liefert einen reinen Namens-
 * Hinweis ohne verknüpftes lokales Ziel — der Aggregator nutzt ihn nur, wenn die
 * aufzählbaren Verzeichnisse nichts finden.
 */
interface PhoneNumberResolver {
    public function id(): string;

    public function label(): string;

    public function isAvailable(Organization $organization): bool;

    /** E.164 → Kontakt-Hinweis (Name/Firma) oder null bei keinem Treffer. */
    public function resolve(Organization $organization, string $e164): ?ExternalPhoneContact;
}
