<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevenIoPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevenIo;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{PluginCapability, SettingsField, SmsProvider};
use App\Plugins\SevenIo\Api\SevenIoSmsClient;
use App\Plugins\Support\Sms\SmsSendResult;
use Throwable;

/**
 * seven.io als SMS-Gateway (Feature 147, MVP-730).
 *
 * Einer der beiden ausgelieferten EU-Anbieter: Betrieb und Datenhaltung in
 * Deutschland, AVV nach Art. 28 DSGVO direkt im Kundenkonto — damit ist die
 * Rufnummernübermittlung ohne Drittland-Konstrukt abbildbar. Twilio und
 * Vonage sind bewusst NICHT dabei (Bewertung Feature 070, Vollscan G12).
 *
 * Konfiguration je Organisation (`plugin_settings`, verschlüsselt); der
 * Healthcheck läuft deshalb per Organisation ({@see isPerOrganization()}).
 */
class SevenIoPlugin extends AbstractPlugin implements SmsProvider {
    public const ID = 'sevenio';

    public const SERVICE_PROVIDER = SevenIoServiceProvider::class;

    public function name(): string {
        return 'seven.io';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Versendet Alarm-SMS über das deutsche Gateway seven.io (EU-Hosting, AVV nach Art. 28 DSGVO).');
    }

    public function capabilities(): array {
        return [
            PluginCapability::SmsGateway,
        ];
    }

    public function smsProviderId(): string {
        return self::ID;
    }

    public function sendSms(Organization $organization, string $recipientE164, string $text, string $reference): SmsSendResult {
        $config = SevenIoConfig::resolve((int) $organization->id);
        if ((string) ($config['api_key'] ?? '') === '') {
            return SmsSendResult::failed('not_configured');
        }

        try {
            return (new SevenIoSmsClient($config))->send($recipientE164, $text, $reference);
        } catch (Throwable $e) {
            // Transportfehler nach ausgeschöpften Vor-Send-Versuchen: als
            // Fehlschlag melden statt den Alarmierungslauf zu reißen.
            return SmsSendResult::failed(mb_substr(class_basename($e), 0, 64));
        }
    }

    /** @return list<array<string, mixed>> */
    public function settingsSchema(): array {
        return [
            SettingsField::password('api_key', (string) __('API-Key'), required: true,
                help: (string) __('Aus dem seven.io-Konto unter „Entwickler“ → „API-Keys“.'))->toArray(),
            SettingsField::text('from', (string) __('Absenderkennung'),
                help: (string) __('Alphanumerisch bis 11 Zeichen (keine Antwort möglich) oder eigene Rufnummer in E.164.'))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        $config = SevenIoConfig::resolve((int) $organization->id);
        if ((string) ($config['api_key'] ?? '') === '') {
            return PluginHealth::degraded(__('Kein API-Key hinterlegt.'), code: 'not_configured');
        }

        return PluginHealth::pingHealth(
            ping: fn (): bool => (new SevenIoSmsClient($config))->checkCredentials(),
            unreachableMessage: (string) __('seven.io lehnt den API-Key ab.'),
            okMessage: (string) __('Verbunden — Gateway erreichbar.'),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }
}
