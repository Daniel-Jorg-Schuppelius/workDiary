{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _project_list_items.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Projektzeilen der Kunden-Detailseite. Erwartet: $items (Collection<Project>).
     Optional: $showForeign (bool) — hängt den Endkunden-Namen an, um gleichnamige
     Projekte (z. B. „Wartungen") in der flachen Liste unterscheidbar zu machen. --}}
@php $showForeign = $showForeign ?? false; @endphp
<ul class="divide-y divide-base-300">
    @foreach ($items as $project)
        <li class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                <a class="link link-hover truncate" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                @if ($showForeign && $project->foreignCustomer)
                    <span class="truncate text-xs text-base-content/50">— {{ $project->foreignCustomer->name }}</span>
                @endif
                @if ($project->is_default)
                    <x-icon name="star" class="text-primary" :filled="true" :title="__('Standardprojekt')" />
                @endif
            </div>
            <x-status-badge :tone="$project->statusTone()">{{ $project->statusLabel() }}</x-status-badge>
        </li>
    @endforeach
</ul>
