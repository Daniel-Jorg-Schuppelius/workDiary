<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Platform\Organization;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use Illuminate\Http\Request;

/**
 * Zahlungsanbieter für Rechnungen (MVP-1067, Fähigkeit
 * {@see PluginCapability::OnlinePayment}). Der Kern kennt keinen Anbieter: Er
 * legt beim Klick auf den Zahlungslink einen Checkout über den dann offenen
 * Betrag an und liest den Stand ausschließlich über {@see fetchPayment()} —
 * ein Webhook ist nur der Anstoß, sein Inhalt wird nie geglaubt.
 */
interface OnlinePaymentProvider {
    /** Stabile Kennung (die Plugin-ID); steht an jeder Zahlung. */
    public function onlinePaymentProviderId(): string;

    /** Bezahlseite beim Anbieter für genau diesen Betrag anlegen. */
    public function createCheckout(Organization $organization, OnlinePaymentRequest $request): OnlinePaymentCheckout;

    /** Zahlung beim Anbieter nachschlagen — mit den Zugangsdaten der Organisation. */
    public function fetchPayment(Organization $organization, string $providerReference): OnlinePaymentSnapshot;

    /** Kennung der Zahlung aus einem Webhook-Aufruf, ohne seinem Inhalt sonst zu trauen. */
    public function webhookReference(Request $request): ?string;
}
