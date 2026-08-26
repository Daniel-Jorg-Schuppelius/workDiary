{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Auftragsdetail (Feature 012, Rang 54/55): read-only — kundensichtbare
  Fotos mit Bestätigen/Beanstanden, Materialliste ohne Preise, kundensichtbare
  Protokolle, Fallakte-PDF über signierten 24-h-Link.
--}}
@extends('customer.layout')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">{{ $diary->title }}</h1>
            <p class="text-sm opacity-70">{{ optional($diary->start_at)->fdate() }} · {{ $diary->status }}</p>
        </div>
        <a href="{{ $pdfUrl }}" class="btn btn-outline btn-sm">{{ __('Fallakte als PDF') }}</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
    @endif
    <x-validation-errors first class="mb-3" />

    <div class="space-y-6">
        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Fotos') }}</h2>
            @if ($photos->isEmpty())
                <x-empty-state icon="photo_library" :title="__('Keine freigegebenen Fotos.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($photos as $photo)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="min-w-0 truncate">{{ $photo->original_name }}</span>
                            <span class="flex items-center gap-2">
                                @if ($confirmedByMe->has($photo->id))
                                    <span class="badge badge-success badge-sm">{{ __('Bestätigt am :date', ['date' => $confirmedByMe[$photo->id]->confirmed_at->fdate()]) }}</span>
                                @else
                                    <form method="POST" action="{{ route('customer.diary.photos.confirm', [$diary, $photo]) }}">
                                        @csrf
                                        <x-button type="submit" tone="primary" size="sm">{{ __('Bestätigen') }}</x-button>
                                    </form>
                                    <details>
                                        <summary class="btn btn-ghost btn-sm">{{ __('Beanstanden') }}</summary>
                                        <form method="POST" action="{{ route('customer.diary.photos.complain', [$diary, $photo]) }}" class="mt-2 flex gap-2">
                                            @csrf
                                            <input type="text" name="note" required minlength="3" maxlength="2000"
                                                   class="input input-sm input-bordered w-64"
                                                   placeholder="{{ __('Was stimmt nicht?') }}">
                                            <x-button type="submit" tone="warning" size="sm">{{ __('Senden') }}</x-button>
                                        </form>
                                    </details>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Material') }}</h2>
            @if ($materials->isEmpty())
                <x-empty-state icon="inventory_2" :title="__('Kein Material erfasst.')" compact />
            @else
                <x-table>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Bezeichnung') }}</x-table.th>
                            <x-table.th>{{ __('Menge') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($materials as $usage)
                        <tr>
                            <td>{{ $usage->description }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim((string) $usage->quantity, '0'), '.') }} {{ $usage->unit }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Protokolle') }}</h2>
            @if ($protocols->isEmpty())
                <x-empty-state icon="description" :title="__('Keine freigegebenen Protokolle.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($protocols as $protocol)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $protocol->title }}</span>
                            <span class="opacity-70">{{ $protocol->status->label() }} · {{ optional($protocol->occurred_at)->fdate() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Vollaudit 2026-07 (H9): freigegebene Kommunikationsnotizen (Spec §4/§11.4). --}}
        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Kommunikation') }}</h2>
            @if ($notes->isEmpty())
                <x-empty-state icon="forum" :title="__('Keine freigegebenen Notizen.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($notes as $note)
                        <li class="py-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ $note->subject ?? $note->type->label() }}</span>
                                <span class="opacity-70">{{ optional($note->occurred_at)->fdatetime() }}</span>
                            </div>
                            @if (filled($note->body))
                                <p class="mt-1 whitespace-pre-line text-base-content/80">{{ $note->body }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Rückfragen und Kommentare (MVP-512): nur mit queries-Capability;
             die Capability erweitert diesen freigegebenen Bereich, macht aber
             selbst nichts sichtbar. Interne Kommentare erscheinen hier nie. --}}
        @php
            $portalQueryUser = auth('customer')->user();
            $portalQueryCustomer = $portalQueryUser?->customer;
            $canQuery = $portalQueryCustomer !== null
                && app(\App\Services\CustomerPortal\PortalVisibility::class)->allows($portalQueryCustomer, \App\Enums\CustomerPortal\PortalCapability::Queries);
        @endphp
        @if ($canQuery)
            @php
                $diaryQueries = \App\Models\CustomerQuery::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $portalQueryUser->organization_id)
                    ->where('customer_id', $portalQueryUser->customer_id)
                    ->where('subject_type', $diary->getMorphClass())
                    ->where('subject_id', $diary->getKey())
                    ->with('answeredBy:id,name')
                    ->orderByDesc('created_at')
                    ->get();
            @endphp
            <section class="rounded-box border border-base-300 bg-base-100 p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="font-semibold">{{ __('Rückfragen und Kommentare') }}</h2>
                    <a href="{{ route('customer.queries.create', ['subject_type' => 'diary', 'subject' => $diary->sqid]) }}"
                       class="btn btn-primary btn-sm">{{ __('Rückfrage stellen') }}</a>
                </div>
                @if ($diaryQueries->isEmpty())
                    <x-empty-state icon="contact_support" :title="__('Noch keine Rückfragen zu diesem Auftrag.')" compact />
                @else
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($diaryQueries as $query)
                            <li class="py-2">
                                <p class="text-xs font-semibold text-muted">{{ $query->asker_name ?? __('Sie') }} ({{ __('Kunde') }}) · {{ $query->created_at?->fdatetime() }}</p>
                                <p class="whitespace-pre-line">{{ $query->question }}</p>
                                @if ($query->answer !== null)
                                    <div class="mt-2 rounded-box bg-base-200/60 p-2">
                                        <p class="text-xs font-semibold text-muted">{{ $query->answeredBy?->name ?? __('Service-Team') }} ({{ __('Team') }})@if ($query->answered_at) · {{ $query->answered_at->fdatetime() }}@endif</p>
                                        <p class="whitespace-pre-line">{{ $query->answer }}</p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
