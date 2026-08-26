{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', $customer->name . ' — ' . __('Kunde'))
@section('nav-title', $customer->name)

@section('content')
<x-page-shell>
    {{-- Header --}}
    <x-entity-header :title="$customer->name" :color="$customer->color"
                     :back-route="route('customers.index')"
                     :edit-route="route('customers.edit', $customer)"
                     :archived="$customer->isArchived()"
                     :restore-route="route('customers.restore', $customer)"
                     :archive-route="route('customers.archive', $customer)"
                     :can-manage="auth()->user()->can('update', $customer)">
        <x-slot:badges>
            @if ($customer->isArchived())
                <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
            @endif
            @unless ($customer->billable)
                <x-status-badge tone="warning">{{ __('nicht abrechenbar') }}</x-status-badge>
            @endunless
        </x-slot:badges>
        <x-slot:meta>
            @if ($customer->company){{ $customer->company }} · @endif
            @if ($customer->number){{ __('Nr.') }} {{ $customer->number }} · @endif
            {{ $customer->currency->value }}
        </x-slot:meta>
        @if ($tags->isNotEmpty())
            <x-slot:tags>
                @foreach ($tags as $tag)
                    <x-tag-badge :tag="$tag" />
                @endforeach
            </x-slot:tags>
        @endif
    </x-entity-header>

    {{-- Plugin-Aktionen (View-Slot, z. B. Outlook-Kontakt-Push, Feature 102 D) --}}
    @php
        $pluginActions = app(\App\Plugins\PluginManager::class)->renderSlot('customer-show.actions', $customer);
    @endphp
    @if ($pluginActions !== '')
        <div class="mb-3 flex flex-wrap items-center gap-2">{!! $pluginActions !!}</div>
    @endif

    <x-identifier-issues :issues="$identifierIssues ?? []" />

    {{-- KPI — zeitbezogene Werte folgen dem globalen Header-Zeitraum (AGENTS.md §8);
         Gesamt- bzw. kalkulatorische Werte stehen als kleiner Zusatz. --}}
    @php
        $cur = $customer->currency->value;
        $timeRange = \App\Support\Formats::duration($rangeMinutes, 'clock');
        $timeTotal = \App\Support\Formats::duration($totalMinutes, 'clock');
        $fmtMoney = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' ' . $cur;
        $margin = $invoicedRange > 0.0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($profitRange / $invoicedRange * 100, 1) . ' %' : null;
    @endphp
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Projekte')" :value="$projects->count()" tone="neutral" />
        <x-kpi-tile :label="__('Erfasste Zeit')" :value="$timeRange" tone="neutral"
                    :hint="$statsRangeLabel . ' · ' . __('gesamt :value', ['value' => $timeTotal])" />
        {{-- Umsatz = tatsächlich fakturiert (Buchhaltung: Lexoffice-Belege + lokale
             Rechnungen); der kalkulatorische Wert aus erfassten Zeiten × Satz nur
             als kleiner Zusatz — er ist ohne gepflegte Stundensätze wenig aussagekräftig. --}}
        <x-kpi-tile :label="__('Umsatz')" :value="$fmtMoney($invoicedRange)" tone="neutral"
                    :hint="$statsRangeLabel . ' · ' . __('kalk. :value', ['value' => $fmtMoney($rangeRate)])" />
        {{-- Gewinn = fakturierter Umsatz − zugeordnete Materialkosten im Zeitraum. --}}
        <x-kpi-tile :label="__('Gewinn (kalk.)')" :value="$fmtMoney($profitRange)"
                    :tone="$profitRange >= 0.0 ? 'success' : 'error'"
                    :hint="$margin !== null ? $statsRangeLabel . ' · ' . __('Marge :value', ['value' => $margin]) : $statsRangeLabel" />
    </div>

    {{-- Kompakte Monats-Trends (12 Monate): Zeiteinsatz & fakturierter Umsatz —
         zwei nach Monat ausgerichtete Diagramme als Gegenüberstellung. --}}
    <div class="chart-grid grid grid-cols-1 gap-3 xl:grid-cols-2">
        <x-charts.stacked-bar :title="__('Zeiteinsatz je Monat')" unit="h"
                              :series="$chartHours"
                              :bands="[
                                  ['key' => 'billable', 'label' => __('abrechenbar')],
                                  ['key' => 'nonbillable', 'label' => __('nicht abrechenbar'), 'hatch' => true],
                              ]"
                              :x-label="__('Monat')"
                              :compare-label="__('Vorjahr')"
                              :note="__('Erfasste Stunden der letzten 12 Monate; gestrichelt = Vorjahres-Gesamt.')" />
        <x-charts.bar :title="__('Umsatz je Monat (fakturiert)')" unit="€"
                      :series="$chartRevenue"
                      :x-label="__('Monat')" y-label="{{ __('Umsatz') }}" :y2-label="__('Materialkosten')"
                      :compare-label="__('Vorjahr')"
                      :note="__('Fakturierte Belege (Lexoffice + lokale Rechnungen) vs. zugeordnete Materialkosten, letzte 12 Monate; gestrichelt = Vorjahr.')" />
    </div>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card :title="__('Kontakt')" icon="contacts">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Ansprechpartner')" :value="$customer->contact_name" />
                <x-detail-grid.row :label="__('E-Mail')">@if ($customer->email)<a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@endif</x-detail-grid.row>
                {{-- Click-to-Dial (W4.5): Knopf erscheint nur bei eingerichteter Anlage. --}}
                <x-detail-grid.row :label="__('Telefon')"><x-phone-value :number="$customer->phone" /></x-detail-grid.row>
                <x-detail-grid.row :label="__('Mobil')"><x-phone-value :number="$customer->mobile" /></x-detail-grid.row>
                <x-detail-grid.row :label="__('Homepage')">@if ($customer->homepage)<a class="link" href="{{ $customer->homepage }}" target="_blank" rel="noopener">{{ $customer->homepage }}</a>@endif</x-detail-grid.row>
                @if ($customer->address_street || $customer->address_zip || $customer->address_city)
                    {{-- Newline via echo, nicht als Template-Whitespace: PHP schluckt ein \n direkt nach `?>` --}}
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{{ $customer->address_street }}@if($customer->address_street){{ "\n" }}@endif{{ trim(($customer->address_zip ?? '').' '.($customer->address_city ?? '')) }}</x-detail-grid.row>
                @elseif ($customer->address)
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{{ $customer->address }}</x-detail-grid.row>
                @endif
                {{-- intl statt CountryCode::getLabel(): Toolkit-Labels sind nur Deutsch, Anzeige muss der UI-Locale folgen --}}
                <x-detail-grid.row :label="__('Land')" :value="$customer->country ? \Locale::getDisplayRegion('-' . $customer->country, app()->getLocale()) : null" />
            </x-detail-grid>
            <x-contact-persons :persons="$customer->contact_persons" />
        </x-card>

        <x-card :title="__('Abrechnung')" icon="receipt_long">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Abrechenbar')" :value="$customer->billable ? __('Ja') : __('Nein')" />
                <x-detail-grid.row :label="__('USt-IdNr.')" :value="$customer->vat_id" />
                @if ($customer->peppol_participant_id)
                    {{-- Peppol (Feature 066, MVP-734): Kennung plus der zuletzt
                         gespeicherte SMP-Befund — die Prüfung löst neu auf. --}}
                    <x-detail-grid.row :label="__('peppol.field.participant_id')">
                        <span class="tabular-nums">{{ $customer->peppol_participant_id }}</span>
                        <div class="text-xs text-muted">
                            @if ($peppolLookup === null)
                                {{ __('peppol.status.never_checked') }}
                            @else
                                {{ $peppolLookup->registered
                                    ? __('peppol.status.registered', ['smp' => (string) $peppolLookup->smp_base_url, 'count' => count($peppolLookup->document_types ?? [])])
                                    : __('peppol.status.not_registered') }}
                                — {{ __('peppol.status.checked_at', ['at' => $peppolLookup->checked_at->fdatetime()]) }}
                            @endif
                        </div>
                        @can('update', $customer)
                            <form method="POST" action="{{ route('customers.peppol.check', $customer) }}" class="pt-1">
                                @csrf
                                <x-icon-btn icon="travel_explore" tone="ghost" size="xs" type="submit" show-label>{{ __('peppol.action.check') }}</x-icon-btn>
                            </form>
                        @endcan
                    </x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('Währung')" :value="$customer->currency->value" />
                <x-detail-grid.row :label="__('Zeitzone')" :value="$customer->timezone" />
                @if ($customer->hourly_rate !== null)
                    <x-detail-grid.row :label="__('Stundensatz')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($customer->hourly_rate?->toFloat() ?? 0.0), 2, withThousandsSeparator: true).' '.$customer->currency->value" />
                @endif
                @if ($customer->internal_rate !== null)
                    <x-detail-grid.row :label="__('Interner Satz')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($customer->internal_rate?->toFloat() ?? 0.0), 2, withThousandsSeparator: true).' '.$customer->currency->value" />
                @endif
            </x-detail-grid>
            @if ($customer->invoice_text)
                <div class="pt-2 text-sm">
                    <div class="text-muted">{{ __('Rechnungstext') }}</div>
                    <p class="whitespace-pre-line">{{ $customer->invoice_text }}</p>
                </div>
            @endif
            @php $bank = $customer->bankDetails(); @endphp
            @if ($bank['has_any'])
                <div class="pt-3 border-t border-base-300">
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Bankverbindung') }}</h3>
                    <x-detail-grid>
                        <x-detail-grid.row :label="__('Kontoinhaber')" :value="$bank['holder']" />
                        <x-detail-grid.row :label="__('IBAN')" :value="$bank['iban']" class="tabular-nums" />
                        <x-detail-grid.row :label="__('BIC')" :value="$bank['bic']" />
                        <x-detail-grid.row :label="__('Bank')" :value="$bank['bank']" />
                    </x-detail-grid>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Projekte & Fremdkunden — als Tabs; je Bereich die aktivsten 10 (nach
         erfassten Stunden), der Rest aufklappbar. --}}
    @php
        // Stunden je Projekt aus der Gesamt-Statistik (Sortierung „aktivste zuerst").
        $projectMinutes = collect($statsTotal['by_project'] ?? [])
            ->mapWithKeys(fn ($r) => [(int) $r['project_id'] => (int) $r['minutes']]);
        $projectsByHours = $projects->sortByDesc(fn ($p) => $projectMinutes[$p->id] ?? 0)->values();
        $topProjects = $projectsByHours->take(10);
        $restProjects = $projectsByHours->slice(10)->values();

        $foreignCustomers = $customer->foreignCustomers()
            ->whereNull('archived_at')
            ->withCount('projects')
            ->get();
        // Stunden je Fremdkunde = Summe der Stunden seiner Projekte.
        $foreignMinutes = $projects->whereNotNull('foreign_customer_id')
            ->groupBy('foreign_customer_id')
            ->map(fn ($grp) => $grp->sum(fn ($p) => $projectMinutes[$p->id] ?? 0));
        $foreignByHours = $foreignCustomers->sortByDesc(fn ($fc) => $foreignMinutes[$fc->id] ?? 0)->values();
        $topForeign = $foreignByHours->take(10);
        $restForeign = $foreignByHours->slice(10)->values();
    @endphp
    <x-card x-data="tabs('projects')" data-tab-allowed="projects,foreign">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div role="tablist" class="tabs tabs-box tabs-sm">
                <button role="tab" class="tab" :class="tabClass('projects')" @click="setTab('projects')">
                    {{ __('Projekte') }}<span class="ml-1 font-normal opacity-60">{{ $projects->count() }}</span>
                </button>
                <button role="tab" class="tab" :class="tabClass('foreign')" @click="setTab('foreign')">
                    {{ __('Fremdkunden') }}<span class="ml-1 font-normal opacity-60">{{ $foreignCustomers->count() }}</span>
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div x-show="isTab('projects')" x-cloak>
                    @isset($defaultProject)
                        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                                    :href="route('projects.timesheets.create', $defaultProject)"
                                    show-label>{{ __('Stundenzettel') }}</x-icon-btn>
                    @endisset
                </div>
                <div x-show="isTab('foreign')" x-cloak>
                    @can('create', App\Models\ForeignCustomer::class)
                        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                                    :href="route('foreign-customers.create', ['customer' => $customer->sqid])"
                                    show-label>{{ __('Fremdkunde anlegen') }}</x-icon-btn>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Projekte --}}
        <div x-show="isTab('projects')" x-cloak>
            @isset($defaultProject)
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-box border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
                    <div class="flex items-center gap-2 min-w-0">
                        <x-icon name="star" class="text-primary" :filled="true" />
                        <a class="link link-hover truncate font-medium" href="{{ route('projects.show', $defaultProject) }}">{{ $defaultProject->name }}</a>
                        <x-status-badge tone="ghost">{{ __('Standardprojekt') }}</x-status-badge>
                    </div>
                    <span class="text-xs text-muted">{{ __('Auto-Bucket für Ad-hoc-/Notfalleinsätze') }}</span>
                </div>
            @endisset

            @if ($projects->isEmpty())
                <x-empty-state compact icon="folder_off"
                               :title="__('Keine Projekte')"
                               :message="__('Diesem Kunden sind noch keine Projekte zugeordnet.')" />
            @else
                @include('customers._project_list_items', ['items' => $topProjects, 'showForeign' => true])
                @if ($restProjects->isNotEmpty())
                    <details class="mt-2">
                        <summary class="cursor-pointer select-none rounded-md px-1 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted hover:bg-base-200">
                            {{ __('Alle anzeigen') }} <span class="font-normal">(+{{ $restProjects->count() }})</span>
                        </summary>
                        <div class="pt-1">
                            @include('customers._project_list_items', ['items' => $restProjects, 'showForeign' => true])
                        </div>
                    </details>
                @endif
            @endif
        </div>

        {{-- Fremdkunden (Endkunden dieser Firma) --}}
        <div x-show="isTab('foreign')" x-cloak>
            @if ($foreignCustomers->isEmpty())
                <p class="text-sm text-muted">{{ __('Keine Fremdkunden. Endkunden dieser Firma hier erfassen, um Zeiten/Abrechnung pro Endkunde zu trennen.') }}</p>
            @else
                <ul class="divide-y divide-base-200">
                    @foreach ($topForeign as $fc)
                        <li class="flex items-center justify-between py-1.5 text-sm">
                            <a class="link link-hover" href="{{ route('foreign-customers.show', $fc) }}">{{ $fc->name }}</a>
                            <span class="text-muted tabular-nums">{{ trans_choice(':count Projekt|:count Projekte', $fc->projects_count, ['count' => $fc->projects_count]) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($restForeign->isNotEmpty())
                    <details class="mt-2">
                        <summary class="cursor-pointer select-none rounded-md px-1 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted hover:bg-base-200">
                            {{ __('Alle anzeigen') }} <span class="font-normal">(+{{ $restForeign->count() }})</span>
                        </summary>
                        <ul class="mt-1 divide-y divide-base-200">
                            @foreach ($restForeign as $fc)
                                <li class="flex items-center justify-between py-1.5 text-sm">
                                    <a class="link link-hover" href="{{ route('foreign-customers.show', $fc) }}">{{ $fc->name }}</a>
                                    <span class="text-muted tabular-nums">{{ trans_choice(':count Projekt|:count Projekte', $fc->projects_count, ['count' => $fc->projects_count]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        </div>
    </x-card>

    {{-- Auswertung pro Kunde --}}
    @isset($statsTotal)
        <x-card x-data="tabs('month')">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="analytics" class="text-muted" /> {{ __('Auswertung') }}
                </h2>
                <div role="tablist" class="tabs tabs-box tabs-sm">
                    {{-- Zeitraum-Tab folgt dem global gewählten Header-Zeitraum. --}}
                    <button role="tab" class="tab" :class="tabClass('month')" @click="setTab('month')">{{ $statsRangeLabel }}</button>
                    <button role="tab" class="tab" :class="tabClass('total')" @click="setTab('total')">{{ __('Gesamt') }}</button>
                </div>
            </div>
            @foreach (['month' => $statsRange, 'total' => $statsTotal] as $key => $set)
                <div x-show="isTab('{{ $key }}')" x-cloak>
                    <div class="mb-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-muted">{{ __('Stunden gesamt') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($set['total_minutes'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-muted">{{ __('davon abrechenbar') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($set['billable_minutes'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                    </div>
                    @php
                        // Aktivste Projekte zuerst; darüber hinaus aufklappbar.
                        $statRows = collect($set['by_project'])->sortByDesc('minutes')->values();
                        $statTop = $statRows->take(10);
                        $statRest = $statRows->slice(10)->values();
                    @endphp
                    @if ($statRows->isNotEmpty())
                        <x-table table-sort="client" bare>
                            <x-slot:head>
                                <tr>
                                    <x-table.th sort>{{ __('Projekt') }}</x-table.th>
                                    <x-table.th sort type="number" align="right">{{ __('Stunden') }}</x-table.th>
                                    <x-table.th sort type="number" align="right">{{ __('abrechenbar') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($statTop as $row)
                                <tr>
                                    <td>
                                        @if ($row['is_default'])
                                            <x-icon name="star" class="text-primary align-middle" :filled="true" size="1rem" />
                                        @endif
                                        {{ $row['name'] }}
                                        @if (! empty($row['foreign_customer']))
                                            <span class="text-muted">— {{ $row['foreign_customer'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right" data-sort-value="{{ (float) $row['minutes'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['minutes'] / 60, 2, withThousandsSeparator: true) }}</td>
                                    <td class="text-right" data-sort-value="{{ (float) $row['billable_minutes'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['billable_minutes'] / 60, 2, withThousandsSeparator: true) }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                        @if ($statRest->isNotEmpty())
                            <details class="mt-2">
                                <summary class="cursor-pointer select-none rounded-md px-1 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted hover:bg-base-200">
                                    {{ __('Alle anzeigen') }} <span class="font-normal">(+{{ $statRest->count() }})</span>
                                </summary>
                                <div class="pt-1">
                                    <x-table table-sort="client" bare>
                                        <x-slot:head>
                                            <tr>
                                                <x-table.th sort>{{ __('Projekt') }}</x-table.th>
                                                <x-table.th sort type="number" align="right">{{ __('Stunden') }}</x-table.th>
                                                <x-table.th sort type="number" align="right">{{ __('abrechenbar') }}</x-table.th>
                                            </tr>
                                        </x-slot:head>
                                        @foreach ($statRest as $row)
                                            <tr>
                                                <td>
                                                    @if ($row['is_default'])
                                                        <x-icon name="star" class="text-primary align-middle" :filled="true" size="1rem" />
                                                    @endif
                                                    {{ $row['name'] }}
                                                    @if (! empty($row['foreign_customer']))
                                                        <span class="text-muted">— {{ $row['foreign_customer'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-right" data-sort-value="{{ (float) $row['minutes'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['minutes'] / 60, 2, withThousandsSeparator: true) }}</td>
                                                <td class="text-right" data-sort-value="{{ (float) $row['billable_minutes'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['billable_minutes'] / 60, 2, withThousandsSeparator: true) }}</td>
                                            </tr>
                                        @endforeach
                                    </x-table>
                                </div>
                            </details>
                        @endif
                    @endif
                </div>
            @endforeach
        </x-card>
    @endisset

    {{-- Anhänge --}}
    <x-attachments-section :attachments="$attachments" upload-type="customer"
                           :upload-id="$customer->sqid" :can-upload="auth()->user()->can('update', $customer)" />

    {{-- Änderungsverlauf (Audit) — abgegrenzt vom Aktivitäts-„Verlauf" unten --}}
    @if ($auditLogs->isNotEmpty())
    <x-card :title="__('Änderungsverlauf')" icon="history">
        <x-audit-log-list :logs="$auditLogs" />
    </x-card>
    @endif

    @if ($lexofficePlugin && $lexofficePlugin->isEnabled())
        @can('update', $customer)
        <x-card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="sync" class="text-muted" /> {{ __('Lexoffice') }}
                </h2>
                @if ($lexofficeContactRef)
                    <x-status-badge tone="success">{{ __('Kontakt verknüpft') }} · {{ Str::limit($lexofficeContactRef->external_id, 8, '…') }}</x-status-badge>
                @else
                    <x-status-badge tone="ghost">{{ __('Noch nicht verknüpft') }}</x-status-badge>
                @endif
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('customers.lexoffice.contact', $customer) }}"
                      class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                    @csrf
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="contacts" class="text-muted" /> {{ __('Kontakt') }}
                    </div>
                    <p class="text-sm text-base-content/70">
                        {{ __('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.') }}
                    </p>
                    <div class="mt-auto pt-1">
                        <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>
                            {{ $lexofficeContactRef ? __('Kontakt aktualisieren') : __('Kontakt anlegen') }}
                        </x-icon-btn>
                    </div>
                </form>

                <form method="POST" action="{{ route('customers.lexoffice.time-export', $customer) }}"
                      class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                    @csrf
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="receipt_long" class="text-muted" /> {{ __('Zeiten als Beleg') }}
                    </div>
                    <p class="text-sm text-base-content/70">
                        {{ __('Abrechenbare, noch nicht übertragene Zeiten als Beleg übertragen.') }}
                    </p>
                    <x-date-range
                        :from="now()->startOfMonth()->toDateString()"
                        :to="now()->endOfMonth()->toDateString()"
                        :required="true"
                    />
                    <div class="mt-auto pt-1">
                        <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('Zeiten übertragen') }}</x-icon-btn>
                    </div>
                </form>
            </div>

            @if ($lexofficeVouchers->isNotEmpty())
                <div class="border-t border-base-300 pt-3">
                    <h3 class="mb-2 text-sm font-semibold">{{ __('Letzte Belege') }}</h3>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($lexofficeVouchers as $ref)
                            <li class="flex items-center justify-between gap-2 py-1.5">
                                <code class="text-xs text-base-content/80">{{ $ref->external_id }}</code>
                                <span class="text-xs text-muted">{{ optional($ref->synced_at)->fdatetime() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>
        @endcan
    @endif

    @include('partials._documents', [
        'invoices' => $localInvoices,
        'vouchers' => $lexofficeVoucherCache,
        'plugin' => $lexofficePlugin,
        'contactRef' => $lexofficeContactRef,
        'range' => $lexofficeVoucherRange,
        'syncRoute' => route('customers.lexoffice.sync-vouchers', $customer),
        'placeholder' => true,
    ])

    @include('customers._domains_panel', ['customer' => $customer, 'customerDomains' => $customerDomains])

    {{-- Portalzugänge (MVP-510) — nur mit customerPortal.access.manage. --}}
    @include('customers._portal_access_panel', [
        'customer' => $customer,
        'portalUsers' => $portalUsers,
        'portalLastLogins' => $portalLastLogins,
    ])

    {{-- Portal-Sichtbarkeit (MVP-511) — nur mit customerPortal.visibility.manage. --}}
    @include('customers._portal_visibility_panel', ['customer' => $customer])

    {{-- Sonderkonditionen & Abrechnungskonto (Feature 098) — nur mit update-Recht. --}}
    @can('update', $customer)
        @include('customers._billing_panel')
    @endcan

    {{-- Materialkosten & Gewinn (Umsatz − Materialkosten) — nur mit update-Recht. --}}
    @can('update', $customer)
        @include('customers._material_panel')
    @endcan

    @include('customers._timeline_panel', ['customer' => $customer])

    @include('communication-notes._panel', ['notable' => $customer, 'notableKind' => 'customer'])

    @include('documents._panel', ['documentable' => $customer, 'documentableKind' => 'customer'])
</x-page-shell>
@endsection
