{{--
  Portal-Objektakte Detail (Feature 027, Rang 50): kundensichtbarer Schnitt —
  Stammdaten, Prüf-/Wartungstermine, abgeschlossene Wartungen, freigegebene
  Protokolle. Interne Defekt-Details bleiben bewusst draußen.
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold">{{ $asset->name }}</h1>
    <p class="mb-4 text-sm opacity-70">
        @if ($asset->serial_number){{ __('Seriennummer') }}: {{ $asset->serial_number }}@endif
    </p>

    <div class="space-y-6">
        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Prüf- & Wartungstermine') }}</h2>
            @if ($asset->maintenancePlans->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">event</span>' :title="__('Keine Termine hinterlegt.')" compact />
            @else
                <x-table>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Bezeichnung') }}</x-table.th>
                            <x-table.th>{{ __('Nächste Fälligkeit') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($asset->maintenancePlans as $plan)
                        <tr>
                            <td>{{ $plan->title }}</td>
                            <td class="tabular-nums">{{ optional($plan->next_due_on)->fdate() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Abgeschlossene Wartungen') }}</h2>
            @if ($timeline === [])
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">build</span>' :title="__('Noch keine abgeschlossenen Wartungen.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($timeline as $event)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $event['payload']['title'] ?? __('Wartung abgeschlossen') }}</span>
                            <span class="opacity-70 tabular-nums">{{ $event['occurred_at'] !== null ? \Illuminate\Support\Carbon::parse($event['occurred_at'])->fdate() : '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Protokolle') }}</h2>
            @if ($asset->protocols->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>' :title="__('Keine freigegebenen Protokolle.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($asset->protocols as $protocol)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $protocol->title }}</span>
                            <span class="opacity-70">{{ $protocol->status->label() }} · {{ optional($protocol->occurred_at)->fdate() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
