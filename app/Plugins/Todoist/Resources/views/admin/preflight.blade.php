@extends('layouts.app')
@section('title', __('todoist.preflight.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Todoist')

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('todoist.preflight.title') . ': ' . ($link->todoist_project_name ?? $link->todoist_project_id)">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.todoist.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Kennzahlen (MVP-112): was der Import vorfindet und was gesondert behandelt wird --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('todoist.preflight.counters') }}</h2>
        <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div>{{ __('todoist.preflight.tasks') }}: <strong>{{ $result['tasks'] }}</strong></div>
            <div>{{ __('todoist.preflight.subtasks') }}: <strong>{{ $result['subtasks'] }}</strong></div>
            <div>{{ __('todoist.preflight.recurring') }}: <strong>{{ $result['recurring'] }}</strong></div>
            <div>{{ __('todoist.preflight.timed_due') }}: <strong>{{ $result['timed_due'] }}</strong></div>
            <div @class(['text-warning' => $result['unassignable'] > 0])>{{ __('todoist.preflight.unassignable') }}: <strong>{{ $result['unassignable'] }}</strong></div>
            <div>{{ __('todoist.preflight.referenced') }}: <strong>{{ $result['referenced'] }}</strong></div>
        </div>
        <p class="text-xs opacity-60 mt-2">{{ __('todoist.preflight.hint') }}</p>
    </x-card>

    {{-- Kollaborator-Zuordnung: E-Mail-Gleichheit ist nur ein VORSCHLAG --}}
    <x-card padding="p-0">
        <h2 class="font-semibold p-4 pb-0">{{ __('todoist.preflight.collaborators') }}</h2>
        <x-table bare class="table-sm">
            <x-slot:head>
                <tr>
                    <th>{{ __('todoist.preflight.col.collaborator') }}</th>
                    <th>{{ __('todoist.preflight.col.email') }}</th>
                    <th>{{ __('todoist.preflight.col.mapped') }}</th>
                    <th class="text-right">{{ __('todoist.preflight.col.assign') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($result['collaborators'] as $collaborator)
                <tr>
                    <td>{{ $collaborator['name'] }}</td>
                    <td class="text-sm">{{ $collaborator['email'] ?: '—' }}</td>
                    <td class="text-sm">
                        @if ($collaborator['mapped_user'])
                            <span class="badge badge-success badge-sm">{{ $collaborator['mapped_user'] }}</span>
                        @elseif ($collaborator['suggestion'])
                            <span class="badge badge-info badge-sm">{{ __('todoist.preflight.suggestion') }}: {{ $collaborator['suggestion'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        <form method="POST" action="{{ route('admin.todoist.collaborators.assign') }}" class="flex items-center justify-end gap-1">
                            @csrf
                            <input type="hidden" name="collaborator_id" value="{{ $collaborator['id'] }}">
                            <select name="user" class="select select-xs select-bordered">
                                <option value="">{{ __('todoist.preflight.unassign') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-xs">{{ __('Speichern') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="group" :title="__('todoist.preflight.no_collaborators')" />
            @endforelse
        </x-table>
    </x-card>

    {{-- Abschnitts→Status-Zuordnung: nicht zugeordnet = Status unangetastet --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('todoist.preflight.sections') }}</h2>
        @if ($sections === [])
            <p class="text-sm opacity-60">{{ __('todoist.preflight.no_sections') }}</p>
        @else
            @php($sectionLinkMap = $link->sectionLinks->keyBy('todoist_section_id'))
            <form method="POST" action="{{ route('admin.todoist.links.sections', $link) }}" class="space-y-2">
                @csrf
                @foreach ($sections as $section)
                    @php($sid = (string) ($section['id'] ?? ''))
                    <div class="flex items-center gap-3">
                        <span class="text-sm w-64 truncate">{{ $section['name'] ?? $sid }}</span>
                        <input type="hidden" name="sections[{{ $sid }}][name]" value="{{ $section['name'] ?? '' }}">
                        <select name="sections[{{ $sid }}][status]" class="select select-sm select-bordered">
                            <option value="">{{ __('todoist.preflight.section_unmapped') }}</option>
                            <option value="open" @selected($sectionLinkMap->get($sid)?->task_status === 'open')>{{ __('todoist.preflight.section_open') }}</option>
                            <option value="in_progress" @selected($sectionLinkMap->get($sid)?->task_status === 'in_progress')>{{ __('todoist.preflight.section_in_progress') }}</option>
                        </select>
                    </div>
                @endforeach
                <button type="submit" class="btn btn-sm">{{ __('Speichern') }}</button>
            </form>
        @endif
    </x-card>
</x-page-shell>
@endsection
