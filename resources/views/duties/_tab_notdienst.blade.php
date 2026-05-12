{{-- Notdienst: Tabelle --}}
<div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
    <table class="table table-zebra table-pin-rows">
        <thead class="bg-base-200">
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Beginn') }}</th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Bereitschaft') }}</th>
                <th>{{ __('Grund') }}</th>
                <th class="w-24 text-right">{{ __('Aktion') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assignments as $a)
                <tr class="hover">
                    <td>{{ $a->user?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $a->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $a->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="text-base-content/60 whitespace-nowrap text-xs">
                        @if ($a->shift)
                            {{ $a->shift->start_at?->format('d.m.') }}–{{ $a->shift->end_at?->format('d.m.') }}
                        @else — @endif
                    </td>
                    <td class="max-w-xs truncate">{{ $a->reason ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('assignments.edit', $a) }}" data-entry-modal-trigger
                           class="btn btn-ghost btn-xs" title="{{ __('Bearbeiten') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($assignments->total() > 0)
    @include('duties._pagination', ['paginator' => $assignments])
@endif
