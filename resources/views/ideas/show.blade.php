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
        <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div><span class="opacity-60">{{ __('ideas.col.owner') }}:</span> <strong>{{ $map->owner?->name }}</strong></div>
            <div><span class="opacity-60">{{ __('ideas.col.visibility') }}:</span> <span class="badge badge-sm">{{ $map->visibility->label() }}</span></div>
            @if ($map->isArchived())
                <div><span class="badge badge-warning badge-sm">{{ __('ideas.filter.archived') }}</span></div>
            @endif
        </div>
        @if ($map->description)
            <p class="text-sm opacity-80 mt-2">{{ $map->description }}</p>
        @endif
    </x-card>

    {{-- Freigaben (MVP-107): verwaltet ausschließlich der Eigentümer --}}
    @if ($canShare)
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('ideas.share.title') }}</h2>

            @if ($shares->isEmpty())
                <p class="text-sm opacity-60 mb-3">{{ __('ideas.share.none') }}</p>
            @else
                <ul class="mb-3 space-y-1">
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
                <form method="POST" action="{{ route('ideas.shares.store', $map) }}" class="flex flex-wrap items-end gap-2">
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
                    <button type="submit" class="btn btn-sm">{{ __('ideas.share.add') }}</button>
                    <p class="text-xs opacity-60 basis-full">{{ __('ideas.share.hint') }}</p>
                </form>
            @endunless
        </x-card>
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
            'urls' => [
                'tree' => route('ideas.maps.tree', $map),
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
                'convert_task' => __('ideas.editor.convert_task'),
                'convert_project' => __('ideas.editor.convert_project'),
                'convert_knowledge' => __('ideas.editor.convert_knowledge'),
            ],
            'colors' => collect(\App\Enums\Ideas\IdeaNodeColor::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ];
    @endphp
    <script type="application/json" id="idea-editor-config">{!! json_encode($editorConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <x-card x-data="ideaEditor('idea-editor-config')" x-on:pointermove.window="onPointerMove($event)" x-on:pointerup.window="endPointer()">
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
            <span class="text-xs opacity-60 ml-auto hidden md:inline">{{ __('ideas.editor.keys_hint') }}</span>
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
                    <span class="w-2 h-2 rounded-full shrink-0" :data-node-color="node(sqid)?.color" aria-hidden="true"
                          :class="{
                              'bg-base-300': node(sqid)?.color === 'default',
                              'bg-primary': node(sqid)?.color === 'primary',
                              'bg-success': node(sqid)?.color === 'success',
                              'bg-warning': node(sqid)?.color === 'warning',
                              'bg-error': node(sqid)?.color === 'error',
                              'bg-info': node(sqid)?.color === 'info',
                          }"></span>
                    <template x-if="editingTitle === sqid">
                        <input type="text" class="input input-xs input-bordered grow"
                               :data-title-input="sqid"
                               :value="node(sqid)?.title"
                               x-on:keydown.enter.stop.prevent="saveTitle(sqid, $event.target.value)"
                               x-on:keydown.escape.stop="editingTitle = null"
                               x-on:blur="saveTitle(sqid, $event.target.value)">
                    </template>
                    <template x-if="editingTitle !== sqid">
                        <span class="text-sm grow cursor-text" x-text="node(sqid)?.title"
                              x-on:dblclick="startRename(sqid)"></span>
                    </template>
                    <span class="badge badge-xs" x-show="node(sqid)?.node_status" x-text="node(sqid)?.node_status"></span>
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
                            <button type="button" class="btn btn-ghost btn-xs px-1" x-on:click.stop="selected = sqid; detailOpen = true"
                                    aria-label="{{ __('ideas.editor.details') }}" title="{{ __('ideas.editor.details') }}">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">tune</span>
                            </button>
                            <template x-if="!node(sqid)?.is_root">
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

        {{-- Canvas: Pan/Zoom/Drag über denselben Baum-State --}}
        <div x-show="view === 'canvas'" x-cloak>
            <div class="flex items-center gap-1 mb-2">
                <button type="button" class="btn btn-xs" x-on:click="zoomOut()" aria-label="{{ __('ideas.editor.zoom_out') }}">−</button>
                <span class="text-xs tabular-nums w-12 text-center" x-text="Math.round(zoom * 100) + '%'"></span>
                <button type="button" class="btn btn-xs" x-on:click="zoomIn()" aria-label="{{ __('ideas.editor.zoom_in') }}">+</button>
            </div>
            <div class="relative overflow-hidden border border-base-300 rounded-box bg-base-100 touch-none select-none"
                 style="height: 60vh" x-on:pointerdown="startPan($event)">
                <div class="absolute origin-top-left" :style="'transform:' + canvasTransform()">
                    <svg class="absolute pointer-events-none" width="4000" height="4000" aria-hidden="true">
                        <template x-for="edge in edges()" :key="edge.key">
                            <line :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2" stroke="currentColor" stroke-opacity="0.25"></line>
                        </template>
                    </svg>
                    <template x-for="n in canvasNodes()" :key="n.sqid">
                        <div class="absolute w-[180px] rounded-box border border-base-300 bg-base-200 px-2 py-1 text-sm shadow-sm cursor-grab"
                             data-canvas-node
                             :class="selected === n.sqid ? 'ring-2 ring-primary' : ''"
                             :style="'left:' + n.pos_x + 'px; top:' + n.pos_y + 'px'"
                             x-on:pointerdown.stop="selected = n.sqid; startNodeDrag($event, n.sqid)"
                             x-on:dblclick="detailOpen = true">
                            <span class="line-clamp-2" x-text="n.title"></span>
                            <span class="badge badge-xs mt-0.5" x-show="n.node_status" x-text="n.node_status"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Knoten-Detail (Notiz, Farbe, Status) --}}
        <template x-if="detailOpen && node(selected)">
            <div class="mt-4 border-t border-base-200 pt-3" role="group" aria-label="{{ __('ideas.editor.details') }}">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-sm" x-text="node(selected)?.title"></h3>
                    <button type="button" class="btn btn-ghost btn-xs" x-on:click="detailOpen = false" aria-label="{{ __('Schließen') }}">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">close</span>
                    </button>
                </div>
                <div class="grid md:grid-cols-3 gap-3">
                    <label class="fieldset md:col-span-3">
                        <span class="fieldset-label">{{ __('ideas.editor.note') }}</span>
                        <textarea class="textarea textarea-bordered textarea-sm w-full" rows="3"
                                  :disabled="!cfg.can_update"
                                  :value="node(selected)?.note"
                                  x-on:change="patchNode(selected, { note: $event.target.value })"></textarea>
                    </label>
                    <label class="fieldset">
                        <span class="fieldset-label">{{ __('ideas.editor.color') }}</span>
                        <select class="select select-sm select-bordered" :disabled="!cfg.can_update"
                                x-on:change="patchNode(selected, { color: $event.target.value })">
                            <template x-for="c in cfg.colors" :key="c.value">
                                <option :value="c.value" :selected="node(selected)?.color === c.value" x-text="c.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="fieldset">
                        <span class="fieldset-label">{{ __('ideas.editor.status') }}</span>
                        <input type="text" class="input input-sm input-bordered" maxlength="24"
                               :disabled="!cfg.can_update"
                               :value="node(selected)?.node_status"
                               x-on:change="patchNode(selected, { node_status: $event.target.value || null })">
                    </label>
                </div>

                {{-- Überführung + Rückreferenzen (MVP-109) --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <template x-for="ref in (node(selected)?.references || [])" :key="ref.label + ref.kind">
                        <a class="badge badge-outline badge-sm gap-1" :href="ref.url" target="_blank">
                            <span class="material-symbols-outlined text-xs" aria-hidden="true" x-text="ref.kind === 'converted' ? 'east' : 'link'"></span>
                            <span x-text="ref.type + ': ' + ref.label"></span>
                        </a>
                    </template>
                    <template x-if="cfg.can_update && !node(selected)?.is_root">
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
