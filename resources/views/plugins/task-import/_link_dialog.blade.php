{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog „Projekt zuordnen“ der Aufgabenimporte. Variablen: $provider,
  $sourceNoun, $routePrefix, $remoteProjects (noch nicht zugeordnete
  Quellprojekte), $projects (WorkDiary-Projekte der Organisation).
--}}
@php($r = ['provider' => $provider, 'source' => $sourceNoun])
<x-modal
    :title="__('integration.task_import.links.add')"
    :eyebrow="$provider"
    icon="add_link"
    tone="primary"
    :action="route($routePrefix . '.links.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('integration.task_import.links.add_submit')">

    <x-form-group :legend="__('integration.task_import.links.dialog_legend')" :description="__('integration.task_import.links.dialog_hint', $r)" icon="account_tree">
        @if ($remoteProjects === [])
            <p class="text-sm text-muted">{{ __('integration.task_import.links.no_remote_projects', $r) }}</p>
        @else
            <x-select-field name="source_project_id" :label="__('integration.task_import.links.col.source', $r)" required>
                @foreach ($remoteProjects as $remote)
                    <option value="{{ $remote['id'] }}" @selected(old('source_project_id') === $remote['id'])>{{ $remote['name'] }}@if ($remote['key'] !== '') ({{ $remote['key'] }})@endif</option>
                @endforeach
            </x-select-field>
        @endif
        <x-project-select :projects="$projects" name="project" :label="__('integration.task_import.links.col.target')" :selected="(string) old('project', '')" :placeholder="null" searchable required />
    </x-form-group>
</x-modal>
