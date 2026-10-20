{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dashboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Startseite des Kundenportals (MVP-511, Phase 154). Erwartet: $user, $customer, $stats, $notices, $areas. --}}
@extends('customer.layout')

@section('title', __('Übersicht'))

@section('content')
    @php
        $year = (int) now()->year;
        $hours = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    @endphp
    <x-page-toolbar :title="__('Willkommen') . ', ' . $user->name" :subtitle="$customer?->name" class="mb-4" />

    @if (($notices ?? []) !== [])
        <x-portal-notices :notices="$notices" class="mb-6" />
    @endif

    {{-- Kacheln nur für freigegebene Bereiche (MVP-511); ohne Freigaben ein
         erklärter Leerzustand statt automatischer Vollsicht. --}}
    @if ($stats === [] && $areas === [])
        <x-empty-state framed icon="visibility_off"
                       :title="__('Für Ihren Zugang sind noch keine Bereiche freigegeben.')"
                       :message="__('Bitte wenden Sie sich an Ihre Ansprechperson, um Inhalte freischalten zu lassen.')" />
    @else
        @if ($stats !== [])
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @isset($stats['diary'])
                    <x-kpi-tile :label="__('Auftragsbuch-Einträge')" :value="$stats['diary']" tone="primary" :href="route('customer.diary.index')" />
                @endisset
                @isset($stats['time_minutes'])
                    <x-kpi-tile :label="__('customer_portal.dashboard.time_tile', ['year' => $year])" :value="$hours($stats['time_minutes'])" format="text" tone="info"
                                :hint="$stats['time_entries'] > 0 ? __('customer_portal.dashboard.time_entries_hint', ['count' => $stats['time_entries']]) : null"
                                :href="route('customer.time-entries.index')" />
                @endisset
                @isset($stats['invoices'])
                    <x-kpi-tile :label="__('Rechnungen')" :value="$stats['invoices']" :tone="($stats['invoices_open'] ?? 0) > 0 ? 'warning' : 'success'"
                                :hint="($stats['invoices_open'] ?? 0) > 0 ? __('customer_portal.dashboard.invoices_open_hint', ['count' => $stats['invoices_open']]) : __('customer_portal.dashboard.invoices_settled_hint')"
                                :href="route('customer.invoices.index')" />
                @endisset
                @isset($stats['open_issues'])
                    <x-kpi-tile :label="__('open-issue.title.index')" :value="$stats['open_issues']" :tone="$stats['open_issues'] > 0 ? 'warning' : 'neutral'" :href="route('customer.open-issues.index')" />
                @endisset
                @isset($stats['intakes'])
                    <x-kpi-tile :label="__('customer_intake.portal.dashboard_tile')" :value="$stats['intakes']" :tone="$stats['intakes_action'] > 0 ? 'warning' : 'neutral'"
                                :hint="$stats['intakes_action'] > 0 ? __('customer_intake.portal.dashboard_action', ['count' => $stats['intakes_action']]) : null"
                                :href="route('customer.intakes.index')" />
                @endisset
                @isset($stats['subscriptions'])
                    <x-kpi-tile :label="__('resale_portal.dashboard.tile')" :value="$stats['subscriptions']" :href="route('customer.subscriptions.index')" />
                @endisset
            </div>
        @endif

        @if (($insights ?? null) !== null)
            @include('customer.insights._dashboard_row', ['insights' => $insights, 'year' => $year])
        @endif

        @if ($areas !== [])
            {{-- Bereiche aus derselben Liste wie die Navigation (E5): Symbol, Titel, Kurztext. --}}
            <section class="mt-6" aria-labelledby="portal-areas-title">
                <h2 id="portal-areas-title" class="mb-3 text-base font-semibold">{{ __('customer_portal.dashboard_areas.title') }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($areas as $area)
                        <x-card as="a" padding="p-4" href="{{ route($area['route']) }}" class="flex items-start gap-3 transition hover:border-primary hover:shadow-md">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                                <x-icon :name="$area['icon']" class="text-xl" />
                            </span>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $area['label'] }}</span>
                                <span class="block text-sm text-muted">{{ $area['description'] }}</span>
                            </span>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endsection
