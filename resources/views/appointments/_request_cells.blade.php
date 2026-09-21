{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _request_cells.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gemeinsame Zellen Termin · Kunde · Leistung beider Anfrage-Tabellen. Variable: $request --}}
@php
    $start = $request->start_at?->orgTz();
    $customerUrl = \App\Support\EntityUrl::for($request->customer);
    $contact = collect([$request->customer !== null ? $request->invitee_name : null, $request->invitee_email])->filter()->implode(' · ');
@endphp
<td class="whitespace-nowrap">
    <div class="font-medium tabular-nums">{{ $start !== null ? $start->translatedFormat('D') . ', ' . $start->fdate() : '—' }}</div>
    @if ($start !== null)
        <div class="text-xs text-muted tabular-nums">{{ $start->ftime() }}–{{ $request->end_at?->ftime() }}</div>
    @endif
</td>
<td class="max-w-64">
    <div class="truncate font-medium">
        @if ($customerUrl !== null)
            <a href="{{ $customerUrl }}" class="link link-hover">{{ $request->customer->name }}</a>
        @else
            {{ $request->invitee_name ?? '—' }}
        @endif
    </div>
    @if ($contact !== '')
        <div class="truncate text-xs text-muted" title="{{ $contact }}">{{ $contact }}</div>
    @endif
</td>
<td>{{ $request->service_label ?? $request->bookableService?->title ?? '—' }}</td>
