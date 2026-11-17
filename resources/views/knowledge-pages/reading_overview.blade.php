{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reading_overview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Pflichtlektüre einer Seite (Feature 191, MVP-1666): für wen sie gilt und
  wer welche Fassung bestätigt hat. Variablen: $page, $rows, $roles, $teams,
  $members.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.reading.overview_title') . ' — ' . $page->title)
@section('nav-title', __('knowledge_pages.reading.overview_title'))

@section('content')
@php
    $confirmedCount = count(array_filter($rows, static fn (array $row): bool => $row['current']));
@endphp
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="$page->title" :back="route('knowledge.pages.show', $page)" />
    </x-slot:toolbar>

    <x-card :title="__('knowledge_pages.reading.assignments')" icon="group_add" :count="$page->assignments->count()">
        @if ($page->assignments->isEmpty())
            <x-empty-state compact icon="group_add" :title="__('knowledge_pages.reading.no_assignments')" />
        @else
            <ul class="mb-3 space-y-1 text-sm" role="list">
                @foreach ($page->assignments as $assignment)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-icon :name="$assignment->kind->icon()" class="text-muted" />
                            {{ $assignment->kind->label() }}:
                            <span class="font-medium">{{ match ($assignment->kind) {
                                \App\Enums\Knowledge\KnowledgeAudienceKind::All => __('knowledge_pages.reading.all_members'),
                                \App\Enums\Knowledge\KnowledgeAudienceKind::Role => \App\Enums\User\UserRole::tryFrom((string) $assignment->role_name)?->label() ?? $assignment->role_name,
                                \App\Enums\Knowledge\KnowledgeAudienceKind::Team => $assignment->team?->name ?? '—',
                                \App\Enums\Knowledge\KnowledgeAudienceKind::User => $assignment->user?->name ?? '—',
                            } }}</span>
                            @if ($assignment->is_onboarding)
                                <x-status-badge tone="info" size="xs" icon="school">{{ __('knowledge_pages.reading.onboarding') }}</x-status-badge>
                            @endif
                        </span>
                        <x-action-form :action="route('knowledge.pages.assignments.destroy', [$page, $assignment])" method="DELETE"
                                       :confirm="__('knowledge_pages.confirm.unassign')" confirm-icon="group_remove" confirm-tone="warning"
                                       :confirm-label="__('knowledge_pages.reading.unassign')">
                            <x-icon-btn icon="group_remove" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.reading.unassign')" />
                        </x-action-form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('knowledge.pages.assignments.store', $page) }}" class="grid grid-cols-1 gap-2 border-t border-base-300 pt-3 md:grid-cols-5">
            @csrf
            <x-select-field name="kind" :label="__('knowledge_pages.reading.kind')" required>
                @foreach (\App\Enums\Knowledge\KnowledgeAudienceKind::cases() as $kindCase)
                    <option value="{{ $kindCase->value }}">{{ $kindCase->label() }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="role_name" :label="__('knowledge_pages.reading.role')">
                <option value="">—</option>
                @foreach ($roles as $roleName)
                    <option value="{{ $roleName }}">{{ \App\Enums\User\UserRole::tryFrom($roleName)?->label() ?? $roleName }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="team" :label="__('knowledge_pages.reading.team')">
                <option value="">—</option>
                @foreach ($teams as $team)
                    <option value="{{ \App\Support\Sqid::encode(\App\Models\Platform\Team::class, (int) $team->id) }}">{{ $team->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="user" :label="__('knowledge_pages.reading.person')">
                <option value="">—</option>
                @foreach ($members as $member)
                    <option value="{{ \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id) }}">{{ $member->name }}</option>
                @endforeach
            </x-select-field>
            <div class="flex flex-col justify-end gap-2">
                <x-checkbox-field name="is_onboarding" :label="__('knowledge_pages.reading.onboarding')" :toggle="false" />
                <x-icon-btn icon="group_add" tone="primary" size="sm" type="submit" show-label>{{ __('knowledge_pages.reading.assign') }}</x-icon-btn>
            </div>
        </form>
    </x-card>

    <x-card :title="__('knowledge_pages.reading.confirmations')" icon="fact_check" padding="p-0"
            :subtitle="$page->approved_version !== null ? __('knowledge_pages.reading.summary', ['done' => $confirmedCount, 'total' => count($rows), 'version' => $page->approved_version]) : __('knowledge_pages.reading.not_published')">
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('knowledge_pages.reading.person') }}</th>
                    <th>{{ __('knowledge_pages.reading.state') }}</th>
                    <th>{{ __('knowledge_pages.reading.confirmed_at') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td class="font-medium">{{ $row['user']->name }}</td>
                    <td>
                        @if ($row['current'])
                            <x-status-badge tone="success" size="sm">{{ __('knowledge_pages.reading.state_current', ['version' => $row['version']]) }}</x-status-badge>
                        @elseif ($row['version'] !== null)
                            <x-status-badge tone="warning" size="sm">{{ __('knowledge_pages.reading.state_older', ['version' => $row['version']]) }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('knowledge_pages.reading.state_open') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-sm">{{ $row['confirmed_at']?->fdatetime() ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty icon="group" :colspan="3" :title="__('knowledge_pages.reading.no_readers')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
