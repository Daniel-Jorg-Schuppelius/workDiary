<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterfacesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Enums\Numbering\NumberScope;
use App\Models\Accounting\{AccountingProfile, AccountingSovereigntyPeriod};
use App\Models\Integration\WebhookEndpoint;
use App\Models\{Organization, PluginState};
use App\Plugins\PluginManager;
use App\Services\Accounting\AccountingSovereigntyResolver;
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use App\Services\Numbering\NumberAuthority;

/**
 * Schnittstellen der Organisation: aktive Plugins (mit Healthcheck-Stand aus
 * plugin_states), Buchhaltungshoheit (aktuell + Historie der Abschnitte),
 * externe Nummernhoheit und Webhook-Ziele (URL ohne Query — Secrets und
 * Plugin-Einstellungen werden nie ausgegeben).
 */
final class InterfacesSection implements ProcedureSection {
    use FormatsSectionValues;

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly AccountingSovereigntyResolver $sovereignty,
        private readonly NumberAuthority $authority,
    ) {}

    public function key(): string {
        return 'interfaces';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.interfaces');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $orgId = (int) $organization->id;

        $states = PluginState::query()->where('organization_id', $orgId)->get()->keyBy('plugin_id');
        $pluginRows = [];
        foreach ($this->enabledPluginsFor($organization) as $plugin) {
            /** @var PluginState|null $state */
            $state = $states->get($plugin->id());
            $pluginRows[] = [
                $plugin->name(),
                $plugin->id(),
                $this->text($plugin->version()),
                $this->yesNo($plugin->isPerOrganization()),
                $this->text($state?->last_health_status),
                $this->dateTime($state?->last_health_check_at),
            ];
        }

        $periods = [];
        foreach (AccountingSovereigntyPeriod::query()->withoutGlobalScopes()->where('organization_id', $orgId)->orderBy('valid_from')->get() as $period) {
            $periods[] = [
                $this->date($period->valid_from),
                $this->date($period->valid_to),
                $period->sovereignty->label(),
                $this->text($period->external_provider),
                $this->text($period->reason),
            ];
        }

        $externalScopes = [];
        foreach (NumberScope::cases() as $scope) {
            if ($this->authority->isExternal($orgId, $scope)) {
                $externalScopes[] = $scope->label();
            }
        }

        $webhooks = [];
        foreach (WebhookEndpoint::query()->withoutGlobalScopes()->where('organization_id', $orgId)->orderBy('label')->get() as $endpoint) {
            $rawEvents = $endpoint->getAttribute('events');
            $events = is_array($rawEvents) ? array_map(static fn (mixed $e): string => (string) $e, $rawEvents) : [];
            $webhooks[] = [
                $this->text($endpoint->label),
                $this->redactUrl((string) $endpoint->url),
                $events === [] ? '—' : implode(', ', $events),
                $this->yesNo((bool) $endpoint->active),
            ];
        }

        /** @var AccountingProfile|null $profile */
        $profile = AccountingProfile::query()->withoutGlobalScopes()->where('organization_id', $orgId)->first();

        return [
            'fields' => [
                'sovereignty' => $this->field('procedure-documentation.interfaces.sovereignty', $this->sovereignty->at($organization)->label()),
                'external_provider' => $this->field('procedure-documentation.interfaces.external_provider', $profile?->external_provider),
                'external_numbering' => $this->field('procedure-documentation.interfaces.external_numbering', $externalScopes === [] ? null : implode(', ', $externalScopes)),
            ],
            'tables' => [
                'plugins' => [
                    'title' => (string) __('procedure-documentation.interfaces.table.plugins'),
                    'columns' => [
                        (string) __('procedure-documentation.interfaces.col.plugin'),
                        (string) __('procedure-documentation.interfaces.col.id'),
                        (string) __('procedure-documentation.interfaces.col.version'),
                        (string) __('procedure-documentation.interfaces.col.per_org'),
                        (string) __('procedure-documentation.interfaces.col.health'),
                        (string) __('procedure-documentation.interfaces.col.checked'),
                    ],
                    'rows' => $pluginRows,
                ],
                'sovereignty' => [
                    'title' => (string) __('procedure-documentation.interfaces.table.sovereignty'),
                    'columns' => [
                        (string) __('procedure-documentation.interfaces.col.from'),
                        (string) __('procedure-documentation.interfaces.col.to'),
                        (string) __('procedure-documentation.interfaces.col.sovereignty'),
                        (string) __('procedure-documentation.interfaces.col.provider'),
                        (string) __('procedure-documentation.interfaces.col.reason'),
                    ],
                    'rows' => $periods,
                ],
                'webhooks' => [
                    'title' => (string) __('procedure-documentation.interfaces.table.webhooks'),
                    'columns' => [
                        (string) __('procedure-documentation.interfaces.col.label'),
                        (string) __('procedure-documentation.interfaces.col.target'),
                        (string) __('procedure-documentation.interfaces.col.events'),
                        (string) __('procedure-documentation.interfaces.col.active'),
                    ],
                    'rows' => $webhooks,
                ],
            ],
        ];
    }

    /**
     * PluginManager::enabled() liest den Org-Kontext aus `currentOrganization`
     * — für die Dauer der Abfrage auf die dokumentierte Org umschalten.
     *
     * @return list<\App\Plugins\Contracts\Plugin>
     */
    private function enabledPluginsFor(Organization $organization): array {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $organization);
        try {
            return array_values($this->plugins->enabled()->all());
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }

    /** Nur Schema/Host/Pfad — Query-Strings können Tokens tragen. */
    private function redactUrl(string $url): string {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return '—';
        }

        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . ($parts['path'] ?? '');
    }
}
