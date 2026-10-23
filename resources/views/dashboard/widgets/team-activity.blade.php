{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : team-activity.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Letzte Team-Aktivität" (Feature 183, Recht des Audit-Protokolls) — Daten: TeamActivityWidget.
--}}
<x-card :title="__('Letzte Team-Aktivität')" icon="groups">
    @include('timeline._items', ['items' => $items])
    @if ($items !== [])
        <a class="link link-hover mt-2 block text-sm" href="{{ route('activity.index') }}">{{ __('activity.title') }} →</a>
    @endif
</x-card>
