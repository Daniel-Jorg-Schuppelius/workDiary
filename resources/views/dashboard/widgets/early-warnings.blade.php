{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : early-warnings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Auffälligkeiten" — Daten: EarlyWarningsWidget (stündlich zwischengespeichert).
--}}
<x-card :title="__('reporting.warning.widget.title')" icon="crisis_alert" :count="count($warnings)">
    @if ($warnings === [])
        <x-empty-state compact icon="check_circle" :title="__('reporting.warning.widget.none')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach (array_slice($warnings, 0, 6) as $warning)
                <li class="rounded-box border border-warning/40 bg-warning/5 px-3 py-2">
                    @if ($warning['url'])
                        <a href="{{ $warning['url'] }}" class="link link-hover font-medium">{{ __($warning['title'], $warning['params']) }}</a>
                    @else
                        <span class="font-medium">{{ __($warning['title'], $warning['params']) }}</span>
                    @endif
                    <p class="text-xs text-muted">{{ __($warning['detail'], $warning['params']) }}</p>
                    <p class="mt-1 text-xs"><x-icon name="lightbulb" class="text-sm" /> {{ __($warning['recommendation']) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
