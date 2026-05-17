@extends('layouts.app')
@section('title', $customer->name . ' — ' . __('Kunde'))
@section('nav-title', $customer->name)

@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $customer->color ?: '#94a3b8' }}"></span>
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold truncate">{{ $customer->name }}</h1>
                @if ($customer->isArchived())
                    <span class="badge badge-ghost">{{ __('archiviert') }}</span>
                @endif
            </div>
            <p class="text-sm text-base-content/60">
                @if ($customer->company){{ $customer->company }} · @endif
                @if ($customer->number){{ __('Nr.') }} {{ $customer->number }} · @endif
                {{ $customer->currency }}
            </p>
            @if ($tags->isNotEmpty())
                <div class="mt-1 flex flex-wrap gap-1">
                    @foreach ($tags as $tag)
                        <span class="badge badge-sm" style="background:{{ $tag->color ?? '#e5e7eb' }};color:#000">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('update', $customer)
                <a href="{{ route('customers.edit', $customer) }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('Bearbeiten') }}</a>
                @if ($customer->isArchived())
                    <form method="POST" action="{{ route('customers.restore', $customer) }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost">{{ __('Wiederherstellen') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('customers.archive', $customer) }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost">{{ __('Archivieren') }}</button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('customers.index') }}" class="btn btn-sm btn-ghost">← {{ __('Zurück') }}</a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs text-base-content/60">{{ __('Projekte') }}</div>
            <div class="font-['Space_Grotesk'] text-2xl font-semibold">{{ $projects->count() }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs text-base-content/60">{{ __('Erfasste Zeit') }}</div>
            <div class="font-['Space_Grotesk'] text-2xl font-semibold tabular-nums">
                {{ intdiv($totalMinutes, 60) }}:{{ str_pad($totalMinutes % 60, 2, '0', STR_PAD_LEFT) }} h
            </div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs text-base-content/60">{{ __('Umsatz (kalk.)') }}</div>
            <div class="font-['Space_Grotesk'] text-2xl font-semibold tabular-nums">
                {{ number_format($totalRate, 2, ',', '.') }} {{ $customer->currency }}
            </div>
        </div>
    </div>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-2">
            <h2 class="font-semibold">{{ __('Kontakt') }}</h2>
            <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                @if ($customer->contact_name)<dt class="text-base-content/60">{{ __('Ansprechpartner') }}</dt><dd>{{ $customer->contact_name }}</dd>@endif
                @if ($customer->email)<dt class="text-base-content/60">{{ __('E-Mail') }}</dt><dd><a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></dd>@endif
                @if ($customer->phone)<dt class="text-base-content/60">{{ __('Telefon') }}</dt><dd>{{ $customer->phone }}</dd>@endif
                @if ($customer->mobile)<dt class="text-base-content/60">{{ __('Mobil') }}</dt><dd>{{ $customer->mobile }}</dd>@endif
                @if ($customer->homepage)<dt class="text-base-content/60">{{ __('Homepage') }}</dt><dd><a class="link" href="{{ $customer->homepage }}" target="_blank" rel="noopener">{{ $customer->homepage }}</a></dd>@endif
                @if ($customer->address_street || $customer->address_zip || $customer->address_city)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt>
                    <dd class="whitespace-pre-line">{!! e($customer->address_street) !!}@if($customer->address_street)
@endif{{ trim(($customer->address_zip ?? '').' '.($customer->address_city ?? '')) }}</dd>
                @elseif ($customer->address)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt><dd class="whitespace-pre-line">{{ $customer->address }}</dd>
                @endif
                @if ($customer->country)<dt class="text-base-content/60">{{ __('Land') }}</dt><dd>{{ $customer->country }}</dd>@endif
            </dl>
            @php $contactPersons = is_array($customer->contact_persons) ? array_values(array_filter($customer->contact_persons, fn($r) => is_array($r) && trim((string)($r['name'] ?? '')) !== '')) : []; @endphp
            @if ($contactPersons !== [])
                <div class="pt-3">
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Ansprechpartner') }}</h3>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($contactPersons as $cp)
                            <li class="py-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-medium">{{ $cp['name'] ?? '' }}</span>
                                @if (! empty($cp['primary']))<span class="badge badge-primary badge-xs">{{ __('Primär') }}</span>@endif
                                @if (! empty($cp['email']))<a class="link link-hover" href="mailto:{{ $cp['email'] }}">{{ $cp['email'] }}</a>@endif
                                @if (! empty($cp['phone']))<span class="text-base-content/70">{{ $cp['phone'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-2">
            <h2 class="font-semibold">{{ __('Abrechnung') }}</h2>
            <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-base-content/60">{{ __('Abrechenbar') }}</dt>
                <dd>{{ $customer->billable ? __('Ja') : __('Nein') }}</dd>
                @if ($customer->vat_id)<dt class="text-base-content/60">{{ __('USt-IdNr.') }}</dt><dd>{{ $customer->vat_id }}</dd>@endif
                <dt class="text-base-content/60">{{ __('Währung') }}</dt><dd>{{ $customer->currency }}</dd>
                @if ($customer->timezone)<dt class="text-base-content/60">{{ __('Zeitzone') }}</dt><dd>{{ $customer->timezone }}</dd>@endif
                @if ($customer->hourly_rate !== null)
                    <dt class="text-base-content/60">{{ __('Stundensatz') }}</dt>
                    <dd>{{ number_format((float) $customer->hourly_rate, 2, ',', '.') }} {{ $customer->currency }}</dd>
                @endif
                @if ($customer->internal_rate !== null)
                    <dt class="text-base-content/60">{{ __('Interner Satz') }}</dt>
                    <dd>{{ number_format((float) $customer->internal_rate, 2, ',', '.') }} {{ $customer->currency }}</dd>
                @endif
            </dl>
            @if ($customer->invoice_text)
                <div class="pt-2 text-sm">
                    <div class="text-base-content/60">{{ __('Rechnungstext') }}</div>
                    <p class="whitespace-pre-line">{{ $customer->invoice_text }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Projekte --}}
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold">{{ __('Projekte') }} ({{ $projects->count() }})</h2>
            @isset($defaultProject)
                <a href="{{ route('projects.timesheets.create', $defaultProject) }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                    <span class="material-symbols-outlined text-base">add</span>
                    {{ __('Stundenzettel') }}
                </a>
            @endisset
        </div>

        @isset($defaultProject)
            <div class="mb-3 flex items-center justify-between rounded-box border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-primary text-base">star</span>
                    <a class="link link-hover truncate font-medium" href="{{ route('projects.show', $defaultProject) }}">{{ $defaultProject->name }}</a>
                    <span class="badge badge-sm">{{ __('Standardprojekt') }}</span>
                </div>
                <span class="text-xs text-base-content/60">{{ __('Auto-Bucket für Ad-hoc-/Notfalleinsätze') }}</span>
            </div>
        @endisset

        @if ($projects->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Diesem Kunden sind noch keine Projekte zugeordnet.') }}</p>
        @else
            <ul class="divide-y divide-base-300">
                @foreach ($projects as $project)
                    <li class="flex items-center justify-between py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                            <a class="link link-hover truncate" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            @if ($project->is_default)
                                <span class="material-symbols-outlined text-primary text-base" title="{{ __('Standardprojekt') }}">star</span>
                            @endif
                        </div>
                        <span class="badge badge-sm badge-{{ $project->statusTone() }}">{{ $project->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Auswertung pro Kunde --}}
    @isset($statsTotal)
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs" x-data="{ tab: 'month' }">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Auswertung') }}</h2>
                <div role="tablist" class="tabs tabs-boxed tabs-sm">
                    <button role="tab" class="tab" :class="{ 'tab-active': tab === 'month' }" @click="tab = 'month'">{{ __('Aktueller Monat') }}</button>
                    <button role="tab" class="tab" :class="{ 'tab-active': tab === 'total' }" @click="tab = 'total'">{{ __('Gesamt') }}</button>
                </div>
            </div>
            @foreach (['month' => $statsMonth, 'total' => $statsTotal] as $key => $set)
                <div x-show="tab === '{{ $key }}'" x-cloak>
                    <div class="mb-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-base-content/60">{{ __('Stunden gesamt') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ number_format($set['total_minutes'] / 60, 2, ',', '.') }} h</div>
                        </div>
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-base-content/60">{{ __('davon abrechenbar') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ number_format($set['billable_minutes'] / 60, 2, ',', '.') }} h</div>
                        </div>
                    </div>
                    @if (count($set['by_project']) > 0)
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Projekt') }}</th>
                                    <th class="text-right">{{ __('Stunden') }}</th>
                                    <th class="text-right">{{ __('abrechenbar') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($set['by_project'] as $row)
                                    <tr>
                                        <td>
                                            @if ($row['is_default'])
                                                <span class="material-symbols-outlined text-primary text-sm align-middle">star</span>
                                            @endif
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="text-right">{{ number_format($row['minutes'] / 60, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($row['billable_minutes'] / 60, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    @endisset

    {{-- Anhänge --}}
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h2 class="mb-3 font-semibold">{{ __('Anhänge') }} ({{ $attachments->count() }})</h2>
        @if ($attachments->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Keine Anhänge.') }}</p>
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($attachments as $att)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0 truncate">
                            <a class="link link-hover" href="{{ URL::signedRoute('attachments.download', $att) }}">{{ $att->original_name }}</a>
                            <span class="text-base-content/60">· {{ number_format($att->size / 1024, 0, ',', '.') }} KB</span>
                        </div>
                        @can('delete', $att)
                        <form method="POST" action="{{ route('attachments.destroy', $att) }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Anhang löschen?') }}"
                              data-confirm-icon="delete"
                              data-confirm-tone="error"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                        </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
        @can('update', $customer)
        <form method="POST" action="{{ route('attachments.store', ['type' => 'customer', 'id' => $customer->id]) }}" enctype="multipart/form-data" class="mt-3 flex items-center gap-2">
            @csrf
            <input type="file" name="file" required class="file-input file-input-sm file-input-bordered">
            <button class="btn btn-sm btn-primary">{{ __('Hochladen') }}</button>
        </form>
        @endcan
    </div>

    {{-- Verlauf --}}
    @if ($auditLogs->isNotEmpty())
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h2 class="mb-3 font-semibold">{{ __('Verlauf') }}</h2>
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($auditLogs as $log)
                <li class="flex items-center justify-between gap-2 py-2">
                    <span><span class="badge badge-ghost badge-sm">{{ $log->event }}</span> {{ optional($log->user)->name ?? '—' }}</span>
                    <span class="text-base-content/60">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($lexofficePlugin && $lexofficePlugin->isEnabled())
        @can('update', $customer)
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">{{ __('Lexoffice') }}</h2>
                @if ($lexofficeContactRef)
                    <span class="badge badge-success badge-sm">{{ __('Kontakt verknüpft') }} · {{ Str::limit($lexofficeContactRef->external_id, 8, '…') }}</span>
                @else
                    <span class="badge badge-ghost badge-sm">{{ __('Noch nicht verknüpft') }}</span>
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('customers.lexoffice.contact', $customer) }}" class="space-y-2">
                    @csrf
                    <div class="text-sm text-base-content/70">
                        {{ __('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.') }}
                    </div>
                    <button class="btn btn-sm btn-primary">
                        {{ $lexofficeContactRef ? __('Kontakt aktualisieren') : __('Kontakt anlegen') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('customers.lexoffice.time-export', $customer) }}" class="space-y-2">
                    @csrf
                    <div class="text-sm text-base-content/70">
                        {{ __('Abrechenbare, noch nicht übertragene Zeiten als Beleg übertragen.') }}
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('Von') }}</span>
                            <input type="date" name="from" value="{{ now()->startOfMonth()->toDateString() }}" required class="input input-sm input-bordered">
                        </label>
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('Bis') }}</span>
                            <input type="date" name="to" value="{{ now()->endOfMonth()->toDateString() }}" required class="input input-sm input-bordered">
                        </label>
                    </div>
                    <button class="btn btn-sm btn-primary">{{ __('Zeiten übertragen') }}</button>
                </form>
            </div>

            @if ($lexofficeVouchers->isNotEmpty())
                <div>
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Letzte Belege') }}</h3>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($lexofficeVouchers as $ref)
                            <li class="flex items-center justify-between py-1">
                                <code class="text-xs">{{ $ref->external_id }}</code>
                                <span class="text-base-content/60">{{ optional($ref->synced_at)->format('d.m.Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        @endcan
    @endif
</div>
@endsection
