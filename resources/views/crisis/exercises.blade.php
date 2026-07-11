@extends('layouts.app')

@section('title', __('Krisenübungen'))
@section('nav-title', __('Krisenübungen'))

@section('content')
<x-index-page :subtitle="__('Übungen und Tests — verbessern Playbooks, verfälschen nie echte Krisenakten.')">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($canManage)
        <x-card :title="__('Übung planen')">
            <form method="POST" action="{{ route('crisis.exercises.store') }}" class="grid gap-2 sm:grid-cols-2">
                @csrf
                <input name="title" required maxlength="200" class="input input-sm input-bordered" placeholder="{{ __('Titel') }}">
                <select name="playbook_template_id" class="select select-sm select-bordered">
                    <option value="">{{ __('— Playbook/Prozedur (optional) —') }}</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->sqid }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <textarea name="scenario" required rows="2" class="textarea textarea-bordered textarea-sm sm:col-span-2" placeholder="{{ __('Szenario') }}"></textarea>
                <input name="next_due_on" type="date" class="input input-sm input-bordered" aria-label="{{ __('Geplant für') }}">
                <div><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Planen') }}</x-icon-btn></div>
            </form>
        </x-card>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Übung') }}</th>
                    <th>{{ __('Playbook') }}</th>
                    <th>{{ __('Durchgeführt') }}</th>
                    <th>{{ __('Wirksamkeit') }}</th>
                    <th>{{ __('Nächste fällig') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($exercises as $exercise)
                <tr>
                    <td>
                        <span class="font-medium">{{ $exercise->title }}</span>
                        <div class="text-xs text-base-content/60">{{ \Illuminate\Support\Str::limit($exercise->scenario, 80) }}</div>
                    </td>
                    <td>{{ $exercise->playbookTemplate->name ?? '—' }}</td>
                    <td>{{ optional($exercise->exercised_at)->fdatetime() ?? '—' }}</td>
                    <td>{{ $exercise->effectiveness !== null ? __("values.{$exercise->effectiveness}") : '—' }}</td>
                    <td>{{ optional($exercise->next_due_on)->fdate() ?? '—' }}</td>
                    <td class="w-96">
                        @if ($canManage && $exercise->exercised_at === null)
                            <form method="POST" action="{{ route('crisis.exercises.document', $exercise) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <input name="observations" maxlength="10000" class="input input-xs input-bordered w-40" placeholder="{{ __('Beobachtungen') }}">
                                <select name="effectiveness" class="select select-xs select-bordered">
                                    <option value="effective">{{ __('values.effective') }}</option>
                                    <option value="partly">{{ __('values.partly') }}</option>
                                    <option value="ineffective">{{ __('values.ineffective') }}</option>
                                </select>
                                <button type="submit" class="btn btn-xs">{{ __('Dokumentieren') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">model_training</span>' :colspan="6" :title="__('Noch keine Übungen geplant.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$exercises" standing />
</x-index-page>
@endsection
