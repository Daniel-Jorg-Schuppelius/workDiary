@extends('layouts.app')

@section('title', __('Krisenübungen'))
@section('nav-title', __('Krisenübungen'))

@section('content')
<x-index-page :subtitle="__('Übungen und Tests — verbessern Playbooks, verfälschen nie echte Krisenakten.')">
    @if ($canManage)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('crisis.exercises.create')"
                        show-label>{{ __('Übung planen') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
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
                    <td class="text-right">
                        @if ($canManage && $exercise->exercised_at === null)
                            <x-icon-btn icon="fact_check" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('crisis.exercises.document.form', $exercise)"
                                        show-label>{{ __('Dokumentieren') }}</x-icon-btn>
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
