{{-- Bereitschaft: Tabelle --}}
<div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
    <table class="table table-zebra table-pin-rows">
        <thead class="bg-base-200">
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Beginn') }}</th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Notiz') }}</th>
                <th class="w-24 text-right">{{ __('Aktion') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shifts as $shift)
                <tr class="hover">
                    <td>{{ $shift->user?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $shift->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $shift->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="max-w-xs truncate">{{ $shift->note ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('shifts.edit', $shift) }}" data-entry-modal-trigger
                           class="btn btn-ghost btn-xs" title="{{ __('Bearbeiten') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($shifts->total() > 0)
    @include('duties._pagination', ['paginator' => $shifts])
@endif
