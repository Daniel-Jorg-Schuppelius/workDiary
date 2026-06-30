@extends('layouts.app')
@section('title', __('Zuordnungs-Inbox'))
@section('nav-title', __('Zuordnungs-Inbox'))

@php
    use App\Models\IntegrationInboxItem;

    $caseLabels = [
        IntegrationInboxItem::CASE_UNMATCHED => __('Nicht zugeordnet'),
        IntegrationInboxItem::CASE_CONFLICT  => __('Feld-Konflikt'),
        IntegrationInboxItem::CASE_AMBIGUOUS => __('Mehrdeutig'),
    ];
    $statusLabels = [
        IntegrationInboxItem::STATUS_OPEN              => __('Offen'),
        IntegrationInboxItem::STATUS_RESOLVED_LINKED   => __('Zugeordnet'),
        IntegrationInboxItem::STATUS_RESOLVED_CREATED  => __('Neu angelegt'),
        IntegrationInboxItem::STATUS_RESOLVED_LOCAL    => __('Lokal behalten'),
        IntegrationInboxItem::STATUS_RESOLVED_REMOTE   => __('Remote übernommen'),
        IntegrationInboxItem::STATUS_DISMISSED         => __('Verworfen'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Eingegangene Importe, die nicht automatisch zugeordnet werden konnten. Pro Eintrag entscheidest du: einem bestehenden Datensatz zuordnen, neu anlegen oder verwerfen — nichts wird blind angelegt.')">
    <x-slot:actions>
        <a href="{{ route('admin.integration.mappings.index') }}" class="btn btn-sm btn-outline">{{ __('Zuordnungen verwalten') }}</a>
        <form method="GET" action="{{ route('admin.integration.inbox') }}" class="flex flex-wrap items-center gap-2">
            <select name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
                <option value="all" @selected($filters['status'] === 'all')>{{ __('Alle') }}</option>
            </select>
            <select name="case" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($filters['case'] === 'all')>{{ __('Alle Typen') }}</option>
                @foreach ($caseLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['case'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="plugin" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($filters['plugin'] === 'all')>{{ __('Alle Quellen') }}</option>
                @foreach ($plugins as $p)
                    <option value="{{ $p }}" @selected($filters['plugin'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
            <select name="target" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($filters['target'] === 'all')>{{ __('Alle Entitäten') }}</option>
                @foreach ($targets as $type => $label)
                    <option value="{{ $type }}" @selected($filters['target'] === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>

    @php $customerOptions = $assignTargets[\App\Models\Customer::class] ?? (reset($assignTargets) ?: []); @endphp

    @if (! $groups->isEmpty())
        <div class="mb-4 space-y-4">
            <h3 class="text-sm font-semibold text-base-content/70">{{ __('Zeit-Import-Gruppen') }}</h3>
            @foreach ($groups as $g)
                @php $form = $g['form'] ?? 'customer_project'; @endphp
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm badge-info">{{ $g['plugin_id'] }}</span>
                        @if ($form === 'asset')
                            <span class="font-semibold">{{ $g['alias'] ?: $g['remote_id'] }}</span>
                            <span class="text-sm text-base-content/60">· {{ $g['provider'] }}</span>
                        @else
                            <span class="font-semibold">{{ $g['project_name'] ?: __('(ohne Projekt)') }}</span>
                            @if ($g['client_name'] ?? null)<span class="text-sm text-base-content/60">· {{ $g['client_name'] }}</span>@endif
                        @endif
                        <span class="ml-auto text-xs text-base-content/50">
                            {{ trans_choice(':count Eintrag|:count Einträge', $g['count'], ['count' => $g['count']]) }} · {{ $g['minutes'] }} {{ __('Min') }}
                        </span>
                    </div>

                    @if ($form === 'asset')
                        {{-- Fernwartung: unbekanntes Gerät an ein bestehendes Asset binden --}}
                        <form method="POST" action="{{ route('admin.integration.inbox.group.book') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                            <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">
                            <select name="asset" required class="select select-sm select-bordered">
                                <option value="">{{ __('… Gerät auswählen') }}</option>
                                @foreach (($assignTargets[\App\Models\Asset::class] ?? []) as $sqid => $label)
                                    <option value="{{ $sqid }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('An Gerät binden & buchen') }}</button>
                            <a href="{{ route('admin.remote-support.pending.index') }}" class="btn btn-sm btn-ghost">{{ __('Neues Gerät / Mehrkundengerät …') }}</a>
                        </form>
                    @else
                    <form method="POST" action="{{ route('admin.integration.inbox.group.book') }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                        <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">

                        @if ($form === 'customer_project')
                        <fieldset class="rounded-box border border-base-300 p-3">
                            <legend class="px-1 text-xs font-semibold">{{ __('Kunde') }}</legend>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="customer_mode" value="existing" class="radio radio-sm" checked>
                                <select name="customer" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('… auswählen') }}</option>
                                    @foreach ($customerOptions as $sqid => $label)
                                        <option value="{{ $sqid }}" @selected(($g['suggested_customer_sqid'] ?? null) === $sqid)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="customer_mode" value="new" class="radio radio-sm">
                                <input type="text" name="new_customer_name" class="input input-sm input-bordered w-full"
                                       placeholder="{{ __('neuer Kunde') }}" value="{{ $g['client_name'] ?? '' }}">
                            </label>
                        </fieldset>
                        @endif

                        <fieldset class="rounded-box border border-base-300 p-3 @if (($g['form'] ?? '') !== 'customer_project') md:col-span-2 @endif">
                            <legend class="px-1 text-xs font-semibold">{{ __('Projekt') }}</legend>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="project_mode" value="existing" class="radio radio-sm" checked>
                                <select name="project" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('… auswählen') }}</option>
                                    @foreach ($projects as $p)
                                        <option value="{{ $p['sqid'] }}" @selected(($g['suggested_project_sqid'] ?? null) === $p['sqid'])>{{ $p['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="project_mode" value="new" class="radio radio-sm">
                                <input type="text" name="new_project_name" class="input input-sm input-bordered w-full"
                                       placeholder="{{ __('neues Projekt') }}" value="{{ $g['project_name'] }}">
                            </label>
                        </fieldset>

                        <div class="md:col-span-2 flex justify-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Gruppe buchen') }}</button>
                        </div>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('admin.integration.inbox.group.dismiss') }}" class="mt-2 flex justify-end"
                          onsubmit="return confirm(@js(__('Diese Gruppe verwerfen?')));">
                        @csrf
                        <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                        <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">
                        <button type="submit" class="btn btn-xs btn-ghost">{{ __('Gruppe verwerfen') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @if ($items->isEmpty())
        @if ($groups->isEmpty())
            <p class="rounded-box border border-base-300 bg-base-100 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Einträge im gewählten Filter. 🎉') }}
            </p>
        @endif
    @else
        <div class="space-y-4">
            @foreach ($items as $item)
                @php
                    $diff = $item->diff_fields ?? [];
                    $candidates = $item->candidate_ids ?? [];
                    $targetLabel = $targets[$item->target_type] ?? class_basename($item->target_type);
                @endphp
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm
                            @if ($item->case_type === IntegrationInboxItem::CASE_CONFLICT) badge-warning
                            @elseif ($item->case_type === IntegrationInboxItem::CASE_AMBIGUOUS) badge-info
                            @else badge-ghost @endif">{{ $caseLabels[$item->case_type] ?? $item->case_type }}</span>
                        <span class="badge badge-sm badge-outline">{{ $item->plugin_id }}</span>
                        <span class="badge badge-sm badge-outline">{{ $targetLabel }}</span>
                        @unless ($item->isOpen())
                            <span class="badge badge-sm badge-success">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                        @endunless
                        <span class="ml-auto text-xs text-base-content/50">{{ optional($item->created_at)->format('d.m.Y H:i') }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="font-semibold">{{ $item->display_title ?: __('(ohne Titel)') }}</div>
                        @if ($item->display_subtitle)
                            <div class="text-sm text-base-content/60">{{ $item->display_subtitle }}</div>
                        @endif
                    </div>

                    @if ($item->case_type === IntegrationInboxItem::CASE_CONFLICT && $diff !== [])
                        <div class="mb-3 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="mb-1 font-semibold">{{ __('Lokal') }}</div>
                                <pre class="whitespace-pre-wrap rounded bg-base-200/50 p-2">{{ json_encode(array_intersect_key($item->local_snapshot ?? [], array_flip($diff)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div>
                                <div class="mb-1 font-semibold">{{ __('Remote') }}</div>
                                <pre class="whitespace-pre-wrap rounded bg-base-200/50 p-2">{{ json_encode(array_intersect_key($item->mapped_snapshot ?? [], array_flip($diff)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @endif

                    @if ($item->isOpen())
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if ($item->case_type === IntegrationInboxItem::CASE_CONFLICT)
                                <form method="POST" action="{{ route('admin.integration.inbox.accept-remote', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">{{ __('Remote übernehmen') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.integration.inbox.keep-local', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline">{{ __('Lokal behalten') }}</button>
                                </form>
                            @else
                                @foreach ($candidates as $cand)
                                    <form method="POST" action="{{ route('admin.integration.inbox.assign', $item) }}">
                                        @csrf
                                        <input type="hidden" name="target" value="{{ $cand['sqid'] ?? '' }}">
                                        <button class="btn btn-sm btn-primary">{{ __('Zuordnen:') }} {{ $cand['label'] ?? '?' }}</button>
                                    </form>
                                @endforeach

                                @if (($assignTargets[$item->target_type] ?? []) !== [])
                                    <form method="POST" action="{{ route('admin.integration.inbox.assign', $item) }}" class="join">
                                        @csrf
                                        <select name="target" required class="join-item select select-sm select-bordered">
                                            <option value="">{{ __('… bestehendem zuordnen') }}</option>
                                            @foreach ($assignTargets[$item->target_type] as $sqid => $label)
                                                <option value="{{ $sqid }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="join-item btn btn-sm">{{ __('Zuordnen') }}</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.integration.inbox.create', $item) }}"
                                      onsubmit="return confirm(@js(__('Als neuen Datensatz anlegen?')));">
                                    @csrf
                                    <button class="btn btn-sm btn-outline">{{ __('Neu anlegen') }}</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.integration.inbox.dismiss', $item) }}">
                                @csrf
                                <button class="btn btn-sm btn-ghost">{{ __('Verwerfen') }}</button>
                            </form>
                        </div>
                    @else
                        <div class="text-right text-xs text-base-content/50">
                            {{ optional($item->resolved_at)->format('d.m.Y H:i') }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $items->links() }}</div>
    @endif
</x-index-page>
@endsection
