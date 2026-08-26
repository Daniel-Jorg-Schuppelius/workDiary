<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Organization;
use App\Plugins\Support\Sms\SmsSendResult;

/**
 * Anbieterneutraler SMS-Versand (Feature 147, MVP-730) — Pflicht-Interface
 * der Fähigkeit {@see PluginCapability::SmsGateway}.
 *
 * Der Kern kennt nur dieses Interface; welcher Anbieter dahinter steht,
 * entscheidet die Organisation, indem sie genau ein Gateway-Plugin aktiviert.
 * Ausgeliefert sind zwei EU-Anbieter (seven.io, sipgate) — Twilio/Vonage
 * bewusst nicht, weil dort ohne Zusatzvertrag ein Drittland-Transfer der
 * Rufnummern entstünde (Bewertung in Feature 070, Vollscan G12).
 */
interface SmsProvider {
    /**
     * Verschickt EINE Kurznachricht.
     *
     * @param  string  $recipientE164  Rufnummer in E.164-Normalform (+49…)
     * @param  string  $text  fertig gekürzter Text (≤ 1 Segment, siehe SmsText)
     * @param  string  $reference  stabile Referenz des Versands (Dispatch-Log-Zeile);
     *                             Anbieter mit eigenem Fremdschlüsselfeld hängen sie
     *                             an, damit Zustellquittungen zuordenbar bleiben
     */
    public function sendSms(Organization $organization, string $recipientE164, string $text, string $reference): SmsSendResult;

    /** Anzeigename des Anbieters für Protokoll/Audit (stabil, klein geschrieben). */
    public function smsProviderId(): string;
}
