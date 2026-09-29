<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PhoneDirectory;

use App\Models\Customer\Customer;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{SettingsField, SlotRenderer};
use App\Plugins\Support\PluginOrgContext;

/**
 * Telefonauskunft (Feature: Rückwärts-Rufnummernauflösung). Löst unbekannte
 * Rufnummern über selbst gestellte HTTP-Auskunftsdienste in Namen auf und speist
 * sie als Vorschlag/Anreicherung in Anruf-Import, CTI-Popup und Stammdaten —
 * über den gemeinsamen Rufnummern-Aggregator, nie automatisch buchend.
 *
 * Anbieterneutral: der Betreiber hinterlegt einen oder mehrere Endpunkte, die
 * die Nummer in E.164 erhalten und `{"name": "…"}` zurückgeben. Datenschutz
 * liegt beim Betreiber (Versand der Anrufernummer an Dritte → AV-Vertrag).
 */
class PhoneDirectoryPlugin extends AbstractPlugin implements SlotRenderer {
    public const ID = 'phonedirectory';

    public const SERVICE_PROVIDER = PhoneDirectoryServiceProvider::class;

    public function name(): string {
        return (string) __('Telefonauskunft');
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Löst unbekannte Rufnummern über selbst gestellte HTTP-Auskunftsdienste in Namen auf und füllt die fehlenden Kontaktdaten an den passenden Stellen (Anruf-Import, CTI, Stammdaten) — als Vorschlag, nie automatisch.');
    }

    public function capabilities(): array {
        return [];
    }

    /** @return list<array<string, mixed>> */
    public function settingsSchema(): array {
        return [
            SettingsField::textarea(
                'endpoints',
                (string) __('Auskunfts-Endpunkte'),
                help: (string) __('Ein Dienst je Zeile: HTTPS-URL, optional gefolgt von „|Token" (Bearer). Die Rufnummer wird in E.164 gesendet — über den Platzhalter {number} in der URL oder als Query-Parameter number. Erwartete Antwort: JSON {"name": "…"}; leer = kein Treffer. Nur einsetzen, wenn der Versand der Anrufernummer an diese Dienste datenschutzrechtlich gedeckt ist.')
            )->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $org = PluginOrgContext::currentOrNull();
        if ($org === null) {
            return PluginHealth::ok(__('Kein Organisationskontext.'));
        }

        $config = PhoneDirectoryConfig::resolve((int) $org->id);
        if ($config['endpoints'] === []) {
            return PluginHealth::degraded(__('Kein Auskunfts-Endpunkt hinterlegt.'), 'not_configured');
        }

        return PluginHealth::ok(__(':count Auskunfts-Endpunkt(e) konfiguriert.', ['count' => count($config['endpoints'])]));
    }

    /**
     * Stammdaten-Anreicherung: Button „Aus Telefonauskunft füllen" in der
     * Kundenakte, wenn eine Rufnummer vorliegt und Name oder Firma fehlt. Die
     * Aktion füllt das fehlende Feld über den Aggregator ({@see \App\Plugins\PhoneDirectory\Http\Controllers\PhoneDirectoryController}).
     */
    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'customer-show.actions' || ! $context instanceof Customer || ! $this->isEnabled()) {
            return null;
        }

        $phone = trim((string) ($context->phone ?: $context->mobile));
        $hasGap = trim((string) $context->name) === '' || trim((string) $context->company) === '';
        if ($phone === '' || ! $hasGap) {
            return null;
        }
        if (PhoneDirectoryConfig::resolve((int) $context->organization_id)['endpoints'] === []) {
            return null;
        }

        $url = e(route('customers.phonedirectory.fill', $context));
        $csrf = e(csrf_token());
        $label = e((string) __('Aus Telefonauskunft füllen'));

        return <<<HTML
            <form method="POST" action="{$url}" class="inline">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" class="btn btn-sm btn-ghost">
                    <span class="material-symbols-outlined" aria-hidden="true">contact_phone</span>
                    <span>{$label}</span>
                </button>
            </form>
        HTML;
    }
}
