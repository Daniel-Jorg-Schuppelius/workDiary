{{--
  Created on   : Sat Jul 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $map->title . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('ideas.title.index'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$map->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('ideas.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
                @can('export', $map)
                    <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('ideas.export.pdf', $map)" target="_blank" show-label>PDF</x-icon-btn>
                    <x-icon-btn icon="account_tree" size="sm" :href="route('ideas.export.opml', $map)" show-label>OPML</x-icon-btn>
                    <x-icon-btn icon="notes" size="sm" :href="route('ideas.export.md', $map)" show-label>Markdown</x-icon-btn>
                    <x-icon-btn icon="data_object" size="sm" :href="route('ideas.export.json', $map)" show-label>JSON</x-icon-btn>
                @endcan
                @if ($canUpdate && ! $map->isArchived())
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                :href="route('ideas.edit', $map)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
            <div><span class="opacity-60">{{ __('ideas.col.owner') }}:</span> <strong>{{ $map->owner?->name }}</strong></div>
            <div><span class="opacity-60">{{ __('ideas.col.visibility') }}:</span> <span class="badge badge-sm">{{ $map->visibility->label() }}</span></div>
            @if ($map->isArchived())
                <div><span class="badge badge-warning badge-sm">{{ __('ideas.filter.archived') }}</span></div>
            @endif
            {{-- Freigaben-Verwaltung als Dialog (nur Eigentümer); spart vertikalen Platz fürs Canvas --}}
            @if ($canShare)
                <button type="button" class="btn btn-sm btn-ghost gap-1 ml-auto"
                        data-open-dialog="ideas-shares-dialog">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">group</span>
                    {{ __('ideas.share.title') }}
                    @if ($shares->isNotEmpty())
                        <span class="badge badge-sm">{{ $shares->count() }}</span>
                    @endif
                </button>
            @endif
        </div>
        @if ($map->description)
            <p class="text-sm opacity-80 mt-2">{{ $map->description }}</p>
        @endif
    </x-card>

    {{-- Freigaben (MVP-107): verwaltet ausschließlich der Eigentümer; als Dialog
         (MVP-135), Trigger in der Eigentümer-Karte oben. Kein `action` am Modal —
         der Body enthält mehrere eigene Forms (Anlegen + Entziehen). --}}
    @if ($canShare)
        <x-modal id="ideas-shares-dialog" :embedded="false" icon="group"
                 :eyebrow="__('ideas.title.index')" :title="__('ideas.share.title')" tone="primary">
            @if ($shares->isEmpty())
                <p class="text-sm opacity-60">{{ __('ideas.share.none') }}</p>
            @else
                <ul class="space-y-1">
                    @foreach ($shares as $share)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-base opacity-60" aria-hidden="true">{{ $share->team_id ? 'groups' : 'person' }}</span>
                            <span>{{ $share->team?->name ?? $share->user?->name ?? '—' }}</span>
                            <span class="badge badge-sm">{{ $share->role->label() }}</span>
                            <form method="POST" action="{{ route('ideas.shares.destroy', [$map, $share]) }}" class="ml-auto">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="close" size="xs" tone="error" type="submit" :title="__('ideas.share.revoke')" />
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            @unless ($map->isArchived())
                <form method="POST" action="{{ route('ideas.shares.store', $map) }}" class="flex flex-wrap items-end gap-2 border-t border-base-200 pt-4">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('ideas.share.user') }}</label>
                        <select name="user" class="select select-sm select-bordered">
                            <option value="">—</option>
                            @foreach ($shareUsers as $user)
                                <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('ideas.share.team') }}</label>
                        <select name="team" class="select select-sm select-bordered">
                            <option value="">—</option>
                            @foreach ($shareTeams as $team)
                                <option value="{{ $team->sqid }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('ideas.share.role') }}</label>
                        <select name="role" class="select select-sm select-bordered">
                            <option value="viewer">{{ __('ideas.share_role.viewer') }}</option>
                            <option value="editor">{{ __('ideas.share_role.editor') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('ideas.share.add') }}</button>
                    <p class="text-xs opacity-60 basis-full">{{ __('ideas.share.hint') }}</p>
                </form>
            @endunless
        </x-modal>
    @endif

    {{-- Editor (MVP-106/108): Gliederung + Canvas über denselben Baum-State --}}
    @php
        $editorConfig = [
            'can_update' => $canUpdate && ! $map->isArchived(),
            'nodes' => $map->nodes->map(fn ($n) => [
                'sqid' => $n->sqid,
                'parent' => $n->parent_id !== null ? app(\App\Services\SqidEncoder::class)->encode(\App\Models\IdeaNode::class, (int) $n->parent_id) : null,
                'is_root' => (bool) $n->is_root,
                'title' => $n->title,
                'note' => $n->note,
                'color' => $n->color->value,
                'node_status' => $n->node_status,
                'pos_x' => $n->pos_x,
                'pos_y' => $n->pos_y,
                'sort_order' => (int) $n->sort_order,
                'lock_version' => (int) $n->lock_version,
            ])->values(),
            // Karten-Kopf inkl. karten-weiter lock_version (Whole-Map-Sync des Canvas, MVP-136).
            'map' => [
                'sqid' => $map->sqid,
                'title' => $map->title,
                'lock_version' => (int) $map->lock_version,
            ],
            // Querverbindungen (MVP-137): Endpunkte als Knoten-Sqid.
            'links' => $map->links->map(fn ($l) => [
                'from' => app(\App\Services\SqidEncoder::class)->encode(\App\Models\IdeaNode::class, (int) $l->source_node_id),
                'to' => app(\App\Services\SqidEncoder::class)->encode(\App\Models\IdeaNode::class, (int) $l->target_node_id),
                'label' => $l->label,
                'color' => $l->color,
            ])->values(),
            // Boundaries (MVP-137): Elternknoten als Sqid, Bereich start..end.
            'summaries' => $map->summaries->map(fn ($s) => [
                'parent' => app(\App\Services\SqidEncoder::class)->encode(\App\Models\IdeaNode::class, (int) $s->parent_node_id),
                'start' => (int) $s->start_index,
                'end' => (int) $s->end_index,
                'label' => $s->label,
            ])->values(),
            'urls' => [
                'tree' => route('ideas.maps.tree', $map),
                'sync' => route('ideas.maps.sync', $map),
                'store' => route('ideas.nodes.store', $map),
                'update' => route('ideas.nodes.update', [$map, '__NODE__']),
                'move' => route('ideas.nodes.move', [$map, '__NODE__']),
                'reorder' => route('ideas.nodes.reorder', [$map, '__NODE__']),
                'destroy' => route('ideas.nodes.destroy', [$map, '__NODE__']),
                'restore' => route('ideas.nodes.restore', [$map, '__NODE__']),
                'presence' => route('ideas.maps.presence', $map),
                'history' => route('ideas.maps.history', $map),
                'convert' => route('ideas.nodes.convert', [$map, '__NODE__']),
            ],
            // Überführungsziele (MVP-109): nur anbieten, was lizenziert UND erlaubt ist.
            'convert_targets' => array_values(array_filter([
                app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.kanban') && auth()->user()->can('create', \App\Models\Task::class) ? 'task' : null,
                auth()->user()->can('create', \App\Models\Project::class) ? 'project' : null,
                app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.knowledge') && auth()->user()->can('create', \App\Models\KnowledgeArticle::class) ? 'knowledge' : null,
            ])),
            'labels' => [
                'new_node' => __('ideas.editor.new_node'),
                'confirm_delete_node' => __('ideas.editor.confirm_delete_node'),
                'delete' => __('ideas.editor.delete'),
                'convert_task' => __('ideas.editor.convert_task'),
                'convert_project' => __('ideas.editor.convert_project'),
                'convert_knowledge' => __('ideas.editor.convert_knowledge'),
            ],
            'colors' => collect(\App\Enums\Ideas\IdeaNodeColor::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
            'statuses' => collect(\App\Enums\Ideas\IdeaNodeStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
        ];
    @endphp
    <script type="application/json" id="idea-editor-config">{!! json_encode($editorConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <x-card x-data="ideaEditor('idea-editor-config')">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <div role="tablist" class="tabs tabs-boxed tabs-sm">
                <button type="button" role="tab" class="tab" :class="view === 'outline' ? 'tab-active' : ''"
                        x-on:click="view = 'outline'">{{ __('ideas.editor.outline') }}</button>
                <button type="button" role="tab" class="tab" :class="view === 'canvas' ? 'tab-active' : ''"
                        x-on:click="view = 'canvas'">{{ __('ideas.editor.canvas') }}</button>
            </div>
            <span class="text-xs opacity-60" x-show="busy">{{ __('ideas.editor.saving') }}</span>
            <span class="text-xs text-error" x-show="error" x-text="error"></span>
            <template x-if="lastDeleted">
                <button type="button" class="btn btn-xs" x-on:click="undoDelete()">{{ __('ideas.editor.undo_delete') }}</button>
            </template>
            <button type="button" class="btn btn-ghost btn-xs" x-on:click="toggleHistory()"
                    :aria-expanded="historyOpen ? 'true' : 'false'">{{ __('ideas.editor.history') }}</button>
            <span class="badge badge-info badge-sm" x-show="editing.length > 0" aria-live="polite"
                  x-text="editing.join(', ') + ' {{ __('ideas.editor.presence_suffix') }}'"></span>
            <span class="text-xs opacity-60 ml-auto hidden md:inline" x-show="view === 'outline'">{{ __('ideas.editor.keys_hint') }}</span>
            <span class="text-xs opacity-60 ml-auto hidden md:inline" x-show="view === 'canvas'" x-cloak>{{ __('ideas.editor.canvas_keys_hint') }}</span>
        </div>

        {{-- Änderungsverlauf (MVP-108): Person, Zeitpunkt, Aktion, Betreff --}}
        <template x-if="historyOpen">
            <div class="mb-3 border border-base-200 rounded-box p-3 max-h-64 overflow-y-auto" role="log" aria-label="{{ __('ideas.editor.history') }}">
                <template x-if="history.length === 0">
                    <p class="text-sm opacity-60">{{ __('ideas.editor.history_empty') }}</p>
                </template>
                <ul class="space-y-1">
                    <template x-for="(entry, idx) in history" :key="idx">
                        <li class="text-xs flex flex-wrap gap-x-2">
                            <span class="opacity-60 tabular-nums" x-text="entry.at"></span>
                            <span class="font-medium" x-text="entry.user || '—'"></span>
                            <span x-text="entry.event"></span>
                            <span class="opacity-70" x-text="entry.subject"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        {{-- Konfliktdialog (MVP-108): nie stilles Last-write-wins --}}
        <template x-if="conflict">
            <div class="alert alert-warning mb-3" role="alertdialog" aria-live="assertive">
                <span class="material-symbols-outlined" aria-hidden="true">sync_problem</span>
                <div class="grow">
                    <p class="font-medium">{{ __('ideas.editor.conflict_title') }}</p>
                    <p class="text-sm" x-text="conflict && conflict.current ? conflict.current.title : ''"></p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-sm" x-on:click="conflictTakeServer()">{{ __('ideas.editor.conflict_take_server') }}</button>
                    <button type="button" class="btn btn-sm btn-primary" x-on:click="conflictRetryMine()">{{ __('ideas.editor.conflict_retry_mine') }}</button>
                </div>
            </div>
        </template>

        {{-- Gliederung: vollständig per Tastatur bedienbar --}}
        <div x-show="view === 'outline'" role="tree" aria-label="{{ __('ideas.editor.outline') }}">
            <template x-for="sqid in order" :key="sqid">
                <div class="flex items-center gap-1 py-0.5 rounded px-1"
                     :class="selected === sqid ? 'bg-base-200' : ''"
                     :style="'margin-left:' + (depthOf(sqid) * 20) + 'px'"
                     role="treeitem" tabindex="0"
                     :aria-selected="selected === sqid ? 'true' : 'false'"
                     :data-node-row="sqid"
                     x-on:click="selected = sqid"
                     x-on:keydown="onKeydown($event, sqid)">
                    <button type="button" class="btn btn-ghost btn-xs px-0.5"
                            :aria-label="collapsed[sqid] ? '{{ __('ideas.editor.expand') }}' : '{{ __('ideas.editor.collapse') }}'"
                            x-show="childrenOf(sqid).length > 0"
                            x-on:click.stop="toggleCollapse(sqid)">
                        <span class="material-symbols-outlined text-base" aria-hidden="true" x-text="collapsed[sqid] ? 'chevron_right' : 'expand_more'"></span>
                    </button>
                    <span class="w-2 h-2 rounded-full shrink-0" :data-node-color="nodeColor(sqid)" aria-hidden="true"
                          :class="{
                              'bg-base-300': nodeColor(sqid) === 'default',
                              'bg-primary': nodeColor(sqid) === 'primary',
                              'bg-success': nodeColor(sqid) === 'success',
                              'bg-warning': nodeColor(sqid) === 'warning',
                              'bg-error': nodeColor(sqid) === 'error',
                              'bg-info': nodeColor(sqid) === 'info',
                          }"></span>
                    <template x-if="editingTitle === sqid">
                        <input type="text" class="input input-xs input-bordered grow"
                               :data-title-input="sqid"
                               :value="nodeTitle(sqid)"
                               x-on:keydown.enter.stop.prevent="saveTitle(sqid, $event.target.value)"
                               x-on:keydown.escape.stop="editingTitle = null"
                               x-on:blur="saveTitle(sqid, $event.target.value)">
                    </template>
                    <template x-if="editingTitle !== sqid">
                        <span class="text-sm grow cursor-text" x-text="nodeTitle(sqid)"
                              x-on:dblclick="startRename(sqid)"></span>
                    </template>
                    <span class="badge badge-xs" x-show="nodeStatus(sqid)" x-text="nodeStatus(sqid)"></span>
                    <template x-if="cfg.can_update">
                        <div class="flex items-center gap-0.5 opacity-0 hover:opacity-100 focus-within:opacity-100"
                             :class="selected === sqid ? 'opacity-100' : ''">
                            <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="addChild(sqid)"
                                    aria-label="{{ __('ideas.editor.add_child') }}" title="{{ __('ideas.editor.add_child') }}">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">add</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="startRename(sqid)"
                                    aria-label="{{ __('ideas.editor.rename') }}" title="{{ __('ideas.editor.rename') }}">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="openDetails(sqid)"
                                    aria-label="{{ __('ideas.editor.details') }}" title="{{ __('ideas.editor.details') }}">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">tune</span>
                            </button>
                            <template x-if="!isRoot(sqid)">
                                <span class="flex items-center gap-0.5">
                                    <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="moveUp(sqid)"
                                            aria-label="{{ __('ideas.editor.move_up') }}" title="{{ __('ideas.editor.move_up') }}">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_upward</span>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="moveDown(sqid)"
                                            aria-label="{{ __('ideas.editor.move_down') }}" title="{{ __('ideas.editor.move_down') }}">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_downward</span>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="outdent(sqid)"
                                            aria-label="{{ __('ideas.editor.outdent') }}" title="{{ __('ideas.editor.outdent') }}">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">format_indent_decrease</span>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="indent(sqid)"
                                            aria-label="{{ __('ideas.editor.indent') }}" title="{{ __('ideas.editor.indent') }}">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">format_indent_increase</span>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs px-1 text-error" x-on:click.stop="removeNode(sqid)"
                                            aria-label="{{ __('ideas.editor.delete') }}" title="{{ __('ideas.editor.delete') }}">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">delete</span>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Canvas: Mind-Elixir-Mindmap (MVP-136). Eigenes Alpine-Component
             (ideaCanvas); `view` kommt per Alpine-Scope-Vererbung aus dem
             umschließenden ideaEditor. Mind Elixir wird lazy geladen, sobald
             der Tab sichtbar wird (vorher hat der Container keine Maße). Der
             Canvas speichert die GANZE Karte über den Sync-Endpunkt; die
             barrierefreie Bearbeitung bleibt die Gliederung. --}}
        <div x-show="view === 'canvas'" x-cloak
             x-data="ideaCanvas('idea-editor-config')">
            <div class="flex items-center gap-2 mb-2 text-xs min-h-5" aria-live="polite">
                <span x-show="busy" class="opacity-60">{{ __('ideas.editor.saving') }}</span>
                <span x-show="error" class="text-error" x-text="error"></span>
                <div class="ml-auto flex items-center gap-1">
                    {{-- Bild-Export (MVP-138): Mind Elixir rendert clientseitig --}}
                    <button type="button" class="btn btn-ghost btn-xs gap-1" x-on:click="exportSvg()"
                            title="{{ __('ideas.editor.export_svg') }}">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">image</span>SVG
                    </button>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" x-on:click="exportPng()"
                            title="{{ __('ideas.editor.export_png') }}">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">photo</span>PNG
                    </button>
                    <span class="opacity-60 hidden sm:inline">{{ __('ideas.editor.canvas_a11y_hint') }}</span>
                </div>
            </div>
            <template x-if="conflict">
                <div class="alert alert-warning mb-2" role="alertdialog" aria-live="assertive">
                    <span class="material-symbols-outlined" aria-hidden="true">sync_problem</span>
                    <span class="grow">{{ __('ideas.editor.conflict_title') }}</span>
                    <button type="button" class="btn btn-sm" x-on:click="reloadFromConflict()">{{ __('ideas.editor.conflict_take_server') }}</button>
                </div>
            </template>
            {{-- Höhe wird per JS auf den Restplatz bis zum Viewport-Ende gesetzt
                 (fitHeight); min-h-96 ist die Smartphone-Untergrenze/Fallback. --}}
            <div x-ref="meHost"
                 class="rounded-box border border-base-300 bg-base-100 overflow-hidden min-h-96"
                 role="application" aria-label="{{ __('ideas.editor.canvas') }}"></div>
        </div>

        {{-- Knoten-Detail (Notiz, Farbe, Status) --}}
        {{-- Knoten-Detail (MVP-135): Notiz + Status werden erst beim „Speichern"
             (oder Schließen) gesichert — nicht mehr per change-Event, das beim
             Schließen verloren ging. Farbe bleibt Sofort-Auswahl. --}}
        <template x-if="detailOpen && node(selected)">
            <div class="mt-4 border-t border-base-200 pt-3" role="group" aria-label="{{ __('ideas.editor.details') }}">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-sm" x-text="nodeTitle(selected)"></h3>
                    <div class="flex items-center gap-1">
                        <template x-if="cfg.can_update">
                            <button type="button" class="btn btn-primary btn-xs gap-1"
                                    x-on:click="saveDetailsFromRefs()">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">save</span>
                                {{ __('Speichern') }}
                            </button>
                        </template>
                        <button type="button" class="btn btn-ghost btn-xs"
                                x-on:click="closeDetailsFromRefs()" aria-label="{{ __('Schließen') }}">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">close</span>
                        </button>
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-3">
                    <label class="fieldset md:col-span-3">
                        <span class="fieldset-label">{{ __('ideas.editor.note') }}</span>
                        <textarea class="textarea textarea-bordered textarea-sm w-full" rows="3"
                                  x-ref="detailNote"
                                  :disabled="!cfg.can_update"
                                  :value="selectedNote()"></textarea>
                    </label>
                    <div class="fieldset" role="radiogroup" aria-label="{{ __('ideas.editor.color') }}">
                        <span class="fieldset-label">{{ __('ideas.editor.color') }}</span>
                        <div class="flex items-center gap-1.5 pt-1.5">
                            <template x-for="c in cfg.colors" :key="c.value">
                                <button type="button" class="w-6 h-6 rounded-full border border-base-300"
                                        :class="swatchClass(c.value) + (selectedColor() === c.value ? ' ring-2 ring-primary ring-offset-1' : '')"
                                        role="radio" :aria-checked="selectedColor() === c.value ? 'true' : 'false'"
                                        :aria-label="c.label" :title="c.label"
                                        :disabled="!cfg.can_update"
                                        x-on:click="patchNode(selected, { color: c.value })"></button>
                            </template>
                        </div>
                    </div>
                    <label class="fieldset">
                        <span class="fieldset-label">{{ __('ideas.editor.status') }}</span>
                        <select class="select select-sm select-bordered" x-ref="detailStatus"
                                :disabled="!cfg.can_update">
                            <option value="">{{ __('ideas.editor.status_none') }}</option>
                            <template x-for="s in cfg.statuses" :key="s.value">
                                <option :value="s.value" :selected="selectedStatus() === s.value" x-text="s.label"></option>
                            </template>
                        </select>
                    </label>
                </div>

                {{-- Überführung + Rückreferenzen (MVP-109) --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <template x-for="ref in selectedReferences()" :key="ref.label + ref.kind">
                        <a class="badge badge-outline badge-sm gap-1" :href="ref.url" target="_blank">
                            <span class="material-symbols-outlined text-xs" aria-hidden="true" x-text="ref.kind === 'converted' ? 'east' : 'link'"></span>
                            <span x-text="ref.type + ': ' + ref.label"></span>
                        </a>
                    </template>
                    <template x-if="cfg.can_update && !isRoot(selected)">
                        <span class="flex flex-wrap gap-1 ml-auto">
                            <template x-for="target in cfg.convert_targets" :key="target">
                                <button type="button" class="btn btn-xs btn-outline" x-on:click="convertNode(target)"
                                        x-text="(cfg.labels['convert_' + target] || target)"></button>
                            </template>
                        </span>
                    </template>
                </div>
                <template x-if="convertResult">
                    <div class="alert alert-sm mt-2" :class="convertResult.existing ? 'alert-warning' : 'alert-success'">
                        <span x-text="convertResult.existing ? '{{ __('ideas.convert.already') }}' : '{{ __('ideas.convert.done') }}'"></span>
                        <a class="link" :href="convertResult.reference.url" target="_blank" x-text="convertResult.reference.label"></a>
                    </div>
                </template>
            </div>
        </template>

        {{-- Fallback ohne JavaScript: read-only Gliederung --}}
        <noscript>
            @if ($root !== null)
                @php
                    $byParent = $map->nodes->groupBy('parent_id');
                    $renderNode = function ($node, $depth) use (&$renderNode, $byParent) {
                        echo '<li class="py-0.5" style="margin-left:' . ($depth * 16) . 'px">';
                        echo '<span class="text-sm">' . e($node->title) . '</span>';
                        echo '</li>';
                        foreach ($byParent->get($node->id, collect()) as $child) {
                            $renderNode($child, $depth + 1);
                        }
                    };
                @endphp
                <ul class="list-none">
                    @php $renderNode($root, 0); @endphp
                </ul>
            @endif
        </noscript>
    </x-card>
</x-page-shell>
@endsection
