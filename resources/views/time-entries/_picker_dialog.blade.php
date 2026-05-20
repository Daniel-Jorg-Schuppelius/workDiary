{{-- Erwartet: $roots, $childrenByParent, $customers, $isDialog
     Picker-Dialog für die Sidebar-Aktion „Zeiteintrag". Roots werden als
     Karten gerendert, Sub-Projekte als eingerückte Unterzeilen in derselben
     Karte — so bleibt der Baum sichtbar und Subprojekte sind nicht verstreut. --}}
<x-modal
    :title="__('Zeiteintrag erfassen')"
    :eyebrow="__('Zeiterfassung')"
    icon="timer"
    tone="primary"
>
    @if ($roots->isEmpty())
        <x-empty-state compact
                       :title="__('Keine aktiven Projekte')"
                       :message="__('Lege zuerst ein Projekt an, um Stunden buchen zu können.')">
            <x-slot:action>
                <x-icon-btn icon="add" tone="primary" :href="route('projects.create')" show-label>{{ __('Projekt') }}</x-icon-btn>
            </x-slot:action>
        </x-empty-state>
    @else
        <div data-filter-scope>
            <p class="mb-3 text-sm text-base-content/70">
                {{ __('Wähle ein Projekt, auf das die Stunden gebucht werden sollen.') }}
            </p>

            <div class="mb-3 flex flex-wrap gap-2">
                <input type="search"
                       data-filter-search
                       placeholder="{{ __('Projekt suchen…') }}"
                       class="input input-bordered input-sm flex-1 min-w-40"
                       autofocus>
                @if ($customers->count() > 1)
                    <select data-filter-customer class="select select-bordered select-sm min-w-44">
                        <option value="">{{ __('Alle Kunden') }}</option>
                        @foreach ($customers as $c)
                            <option value="{{ data_get($c, 'id') }}">{{ data_get($c, 'name') }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <ul data-filter-list class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                @foreach ($roots as $root)
                    @php
                        $rootHaystack = strtolower($root->name . ' ' . ($root->customer?->name ?? ''));
                        $rootCust = $root->customer_id ?? 0;
                        $children = $childrenByParent->get((int) $root->id, collect());
                    @endphp
                    <li data-card class="rounded-box border border-base-300 bg-base-100 overflow-hidden">
                        {{-- Hauptprojekt-Zeile --}}
                        <a href="{{ route('projects.time-entries.create', $root) }}"
                           data-entry-modal-trigger
                           data-haystack="{{ $rootHaystack }}"
                           data-customer="{{ $rootCust }}"
                           class="flex items-center gap-3 px-3 py-2.5 hover:bg-primary/10 transition cursor-pointer">
                            <span class="inline-block h-3 w-3 shrink-0 rounded-full"
                                  style="background:{{ $root->color ?: '#94a3b8' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium truncate">{{ $root->name }}</span>
                                @if ($root->customer)
                                    <span class="block text-xs text-base-content/60 truncate">{{ $root->customer->name }}</span>
                                @else
                                    <span class="block text-xs text-base-content/40 truncate">{{ __('Intern (ohne Kunde)') }}</span>
                                @endif
                            </span>
                            <x-icon name="chevron_right" class="text-base-content/40" />
                        </a>

                        @if ($children->isNotEmpty())
                            <ul class="border-t border-base-200 bg-base-200/30">
                                @foreach ($children as $child)
                                    @php
                                        $childHaystack = strtolower($child->name . ' ' . ($child->customer?->name ?? '') . ' ' . $root->name);
                                        $childCust = $child->customer_id ?? 0;
                                    @endphp
                                    <li data-haystack="{{ $childHaystack }}" data-customer="{{ $childCust }}">
                                        <a href="{{ route('projects.time-entries.create', $child) }}"
                                           data-entry-modal-trigger
                                           class="flex items-center gap-2 pl-9 pr-3 py-1.5 hover:bg-primary/10 transition cursor-pointer border-t border-base-200 first:border-t-0">
                                            <x-icon name="subdirectory_arrow_right" class="text-base-content/40 text-sm shrink-0" />
                                            <span class="inline-block h-2 w-2 shrink-0 rounded-full"
                                                  style="background:{{ $child->color ?: '#cbd5e1' }}"></span>
                                            <span class="min-w-0 flex-1 text-sm truncate">{{ $child->name }}</span>
                                            <x-icon name="chevron_right" class="text-base-content/30 text-sm" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p data-filter-empty class="hidden mt-3 text-sm text-base-content/60 text-center">
                {{ __('Keine Projekte passen zu den Filtern.') }}
            </p>
        </div>
    @endif
</x-modal>
