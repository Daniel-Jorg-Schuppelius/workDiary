{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recent-emergencies.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Aktuelle Notdienste')">
    @if ($emergencies->isNotEmpty())
        <ul class="space-y-1 text-sm">
            @foreach ($emergencies as $em)
                <li class="flex items-center gap-2">
                    <x-icon name="priority_high" />
                    <span>{{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-muted">{{ __('Keine anstehenden Notdienste.') }}</p>
    @endif
</x-card>
