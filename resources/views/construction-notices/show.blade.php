{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  VOB/B-Schreiben (Feature 062, MVP-728): Inhalt, Wetterlage des Anlasstags und
  die Zugangsnachweise aus dem Belegversand.
--}}

@extends('layouts.app')

@section('title', $notice->displayNo())
@section('nav-title', $notice->displayNo() . ' — ' . $notice->kind->label())

@section('content')
    <x-index-page :subtitle="$notice->subject">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('construction-notices.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm"
                        :href="route('construction-notices.pdf', $notice)" show-label>{{ __('construction.action.pdf') }}</x-icon-btn>
            @if ($notice->isEditable())
                <x-icon-btn icon="edit" tone="ghost" size="sm"
                            data-entry-modal-trigger
                            :href="route('construction-notices.edit', $notice)" show-label>{{ __('construction.action.edit') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="mail" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('construction-notices.mail.form', $notice)" show-label>{{ __('construction.action.send') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid md:grid-cols-3 gap-4">
            <x-card class="md:col-span-2">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :tone="$notice->isEditable() ? 'ghost' : 'success'" size="sm">{{ $notice->status->label() }}</x-status-badge>
                        <span class="text-sm text-base-content/70">{{ $notice->occurred_on?->format('d.m.Y') }}</span>
                        @if ($notice->legal_reference)
                            <span class="text-sm text-base-content/70">{{ $notice->legal_reference }}</span>
                        @endif
                    </div>
                    <p class="whitespace-pre-line"><span class="font-semibold">{{ __('construction.field.facts') }}:</span><br>{{ $notice->facts }}</p>
                    @if ($notice->impact_schedule)
                        <p class="whitespace-pre-line"><span class="font-semibold">{{ __('construction.field.impact_schedule') }}:</span><br>{{ $notice->impact_schedule }}</p>
                    @endif
                    @if ($notice->impact_cost)
                        <p class="whitespace-pre-line"><span class="font-semibold">{{ __('construction.field.impact_cost') }}:</span><br>{{ $notice->impact_cost }}</p>
                    @endif
                    @if ($notice->claims_time_extension)
                        <div class="alert alert-warning text-sm">
                            <span>{{ __('construction.note.time_extension') }}</span>
                        </div>
                    @endif
                    <p class="text-xs text-muted">{{ __('construction.disclaimer') }}</p>
                </div>
            </x-card>

            <aside class="space-y-3">
                <x-card>
                    <h3 class="text-sm font-semibold mb-1">{{ __('construction.section.context') }}</h3>
                    <dl class="text-sm space-y-1">
                        <div><dt class="inline font-medium">{{ __('construction.column.project') }}:</dt> <dd class="inline">{{ $notice->project?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('construction.field.site') }}:</dt> <dd class="inline">{{ $notice->site?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('construction.field.customer') }}:</dt> <dd class="inline">{{ $notice->customer?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('construction.field.diary_entry') }}:</dt> <dd class="inline">
                            @if ($notice->diaryEntry !== null)
                                <a class="link" href="{{ route('diary.show', $notice->diaryEntry) }}">{{ $notice->diaryEntry->title }}</a>
                            @else — @endif
                        </dd></div>
                    </dl>
                </x-card>

                @if ($notice->weatherSnapshot !== null)
                    <x-card>
                        <h3 class="text-sm font-semibold mb-1">{{ __('construction.section.weather') }}</h3>
                        <p class="text-sm">
                            {{ $notice->weatherSnapshot->temp_min }} – {{ $notice->weatherSnapshot->temp_max }} °C ·
                            {{ $notice->weatherSnapshot->precipitation_mm }} mm ·
                            {{ $notice->weatherSnapshot->wind_gust_kmh }} km/h
                        </p>
                        <p class="text-xs text-muted">{{ $notice->weatherSnapshot->provider }} — {{ $notice->weatherSnapshot->fetched_at?->format('d.m.Y H:i') }}</p>
                    </x-card>
                @endif

                <x-card>
                    <h3 class="text-sm font-semibold mb-1">{{ __('construction.section.delivery') }}</h3>
                    @forelse ($dispatches as $dispatch)
                        <p class="text-sm">
                            <span class="font-medium">{{ $dispatch->channel }}</span> ·
                            {{ $dispatch->recipient }} ·
                            {{ $dispatch->created_at?->format('d.m.Y H:i') }}
                            <x-status-badge :tone="$dispatch->status === 'sent' ? 'success' : 'ghost'" size="sm">{{ $dispatch->status }}</x-status-badge>
                        </p>
                    @empty
                        <p class="text-sm text-muted">{{ __('construction.delivery.none') }}</p>
                    @endforelse

                    <form method="post" action="{{ route('construction-notices.delivery', $notice) }}" class="mt-3 space-y-1">
                        @csrf
                        <x-input-field name="method" :label="__('construction.delivery.method')">
                            <select id="method" name="method" class="select select-bordered select-sm w-full">
                                @foreach ($deliveryMethods as $method)
                                    <option value="{{ $method }}">{{ __('construction.delivery.method_' . $method) }}</option>
                                @endforeach
                            </select>
                        </x-input-field>
                        <x-input-field name="delivered_at" type="date" required
                                       :label="__('construction.delivery.delivered_at')"
                                       :value="now()->toDateString()" />
                        <x-input-field name="recipient" type="text" maxlength="200" required
                                       :label="__('construction.delivery.recipient')"
                                       :value="$notice->recipient_name ?? ''" />
                        <x-input-field name="reference" type="text" maxlength="120"
                                       :label="__('construction.delivery.reference')" />
                        <x-icon-btn icon="how_to_reg" tone="ghost" size="sm" type="submit" show-label class="w-full">{{ __('construction.delivery.record') }}</x-icon-btn>
                    </form>
                </x-card>

                @if (! $notice->isEditable())
                    <x-card>
                        <h3 class="text-sm font-semibold mb-1">{{ __('construction.section.acknowledge') }}</h3>
                        @if ($notice->acknowledged_at)
                            <p class="text-sm text-success">{{ $notice->acknowledged_at->format('d.m.Y') }} — {{ $notice->acknowledged_note ?: '—' }}</p>
                        @else
                            <form method="post" action="{{ route('construction-notices.acknowledge', $notice) }}" class="space-y-1">
                                @csrf
                                <x-input-field name="acknowledged_note" type="text" maxlength="500"
                                               :label="__('construction.field.acknowledged_note')" />
                                <x-icon-btn icon="task_alt" tone="ghost" size="sm" type="submit" show-label class="w-full">{{ __('construction.action.acknowledge') }}</x-icon-btn>
                            </form>
                        @endif
                    </x-card>
                @endif
            </aside>
        </div>
    </x-index-page>
@endsection
