<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgatePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sipgate;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{PluginCapability, SettingsField, SmsProvider};
use App\Plugins\Sipgate\Api\SipgateSmsClient;
use App\Plugins\Support\Sms\SmsSendResult;
use Throwable;

/**
 * sipgate als SMS-Gateway (Feature 147, MVP-730).
 *
 * Der zweite ausgelieferte EU-Anbieter: deutscher Telefonieanbieter,
 * Datenhaltung in Deutschland. Interessant vor allem, wenn die Organisation
 * sipgate ohnehin für Telefonie nutzt — dann trägt der bestehende AVV auch
 * die Alarmierung, und es kommt keine weitere Auftragsverarbeitung dazu.
 *
 * Anmeldung über einen Personal Access Token (Token-ID + Token) mit dem
 * Scope „sessions:sms:write"; Konfiguration je Organisation.
 */
class SipgatePlugin extends AbstractPlugin implements SmsProvider {
    public const ID = 'sipgate';

    public const SERVICE_PROVIDER = SipgateServiceProvider::class;

    public function name(): string {
        return 'sipgate';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Versendet Alarm-SMS über die sipgate-REST-API (EU-Hosting, Personal Access Token).');
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
        $config = SipgateConfig::resolve((int) $organization->id);
        if ((string) ($config['token_id'] ?? '') === '' || (string) ($config['token'] ?? '') === '') {
            return SmsSendResult::failed('not_configured');
        }

        try {
            return (new SipgateSmsClient($config))->send($recipientE164, $text, $reference);
        } catch (Throwable $e) {
            return SmsSendResult::failed(mb_substr(class_basename($e), 0, 64));
        }
    }

    /** @return list<array<string, mixed>> */
    public function settingsSchema(): array {
        return [
            SettingsField::text('token_id', (string) __('Token-ID'), required: true,
                help: (string) __('Aus dem sipgate-Konto unter „Personal Access Token“ (Scope sessions:sms:write).'))->toArray(),
            SettingsField::password('token', (string) __('Token'), required: true)->toArray(),
            SettingsField::text('sms_id', (string) __('SMS-Erweiterung'),
                help: (string) __('Kennung der SMS-Erweiterung des Kontos, üblicherweise „s0“.'))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        $config = SipgateConfig::resolve((int) $organization->id);
        if ((string) ($config['token_id'] ?? '') === '' || (string) ($config['token'] ?? '') === '') {
            return PluginHealth::degraded(__('Kein Personal Access Token hinterlegt.'), code: 'not_configured');
        }

        return PluginHealth::pingHealth(
            ping: fn (): bool => (new SipgateSmsClient($config))->checkCredentials(),
            unreachableMessage: (string) __('sipgate lehnt den Token ab.'),
            okMessage: (string) __('Verbunden — Gateway erreichbar.'),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }
}
