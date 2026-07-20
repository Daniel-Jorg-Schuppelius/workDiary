{{-- Projektzeilen der Kunden-Detailseite. Erwartet: $items (Collection<Project>) --}}
<ul class="divide-y divide-base-300">
    @foreach ($items as $project)
        <li class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                <a class="link link-hover truncate" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                @if ($project->is_default)
                    <x-icon name="star" class="text-primary" :filled="true" :title="__('Standardprojekt')" />
                @endif
            </div>
            <x-status-badge :tone="$project->statusTone()">{{ $project->statusLabel() }}</x-status-badge>
        </li>
    @endforeach
</ul>
