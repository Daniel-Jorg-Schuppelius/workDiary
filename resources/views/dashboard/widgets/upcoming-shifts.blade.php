{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : upcoming-shifts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Anstehende Schichten')">
    @if ($today->isNotEmpty())
        <p class="text-xs uppercase tracking-wider text-muted">{{ __('Heute') }}</p>
        <ul class="mt-1 space-y-1 text-sm">
            @foreach ($today as $shift)
                <li class="flex items-center gap-2">
                    <x-icon name="event" />
                    <span>{{ $shift->start_at->ftime() }} – {{ $shift->end_at->ftime() }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($shifts->isNotEmpty())
        <p class="mt-3 text-xs uppercase tracking-wider text-muted">{{ __('Kommende Tage') }}</p>
        <ul class="mt-1 space-y-1 text-sm">
            @foreach ($shifts as $shift)
                <li class="flex items-center gap-2">
                    <x-icon name="schedule" />
                    <span>{{ $shift->start_at->format('d.m. H:i') }} – {{ $shift->end_at->format('d.m. H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($today->isEmpty() && $shifts->isEmpty())
        <p class="text-sm text-muted">{{ __('Keine anstehenden Schichten.') }}</p>
    @endif
</x-card>
