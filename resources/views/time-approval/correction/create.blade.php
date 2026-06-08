@extends('layouts.app')

@section('title', __('Korrekturantrag anlegen'))
@section('nav-title', __('Korrekturantrag anlegen'))

@section('content')
    <x-index-page :subtitle="__('Tagesbezug, Begründung und mindestens ein Item angeben.')">
        <form method="POST" action="{{ route('corrections.store') }}" class="space-y-4">
            @csrf

            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul class="list-disc ml-4">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                <label class="form-control">
                    <span class="label-text">{{ __('Bezugsdatum') }}</span>
                    <input type="date" name="scope_date"
                           value="{{ old('scope_date', $scopeDate->format('Y-m-d')) }}"
                           class="input input-sm input-bordered" required />
                </label>
            </div>

            <label class="form-control">
                <span class="label-text">{{ __('Begründung (≥ 20 Zeichen)') }}</span>
                <textarea name="reason" rows="3" minlength="20" maxlength="4000"
                          class="textarea textarea-bordered" required>{{ old('reason') }}</textarea>
            </label>

            <div class="card bg-base-200" id="items-card" data-target-types='@json($targetTypes)' data-actions='@json($actions)'>
                <div class="card-body space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="card-title text-base">{{ __('Items') }}</h3>
                        <x-icon-btn id="add-item" type="button" icon="add" size="sm" tone="ghost" show-label>
                            {{ __('Item hinzufügen') }}
                        </x-icon-btn>
                    </div>
                    <div id="items-list" class="space-y-3"></div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-icon-btn icon="close" size="sm" tone="ghost" :href="route('corrections.index')" show-label>
                    {{ __('Abbrechen') }}
                </x-icon-btn>
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="material-symbols-outlined">save</span>{{ __('Als Entwurf speichern') }}
                </button>
            </div>
        </form>

        <template id="item-template">
            <div class="border border-base-300 rounded-md p-3 space-y-2 item-row">
                <div class="grid md:grid-cols-3 gap-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('Ziel-Typ') }}</span>
                        <select name="items[__IDX__][target_type]" class="select select-sm select-bordered" required>
                            @foreach ($targetTypes as $cls => $label)
                                <option value="{{ $cls }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Ziel-ID (leer für create)') }}</span>
                        <input type="number" name="items[__IDX__][target_id]" class="input input-sm input-bordered" />
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Aktion') }}</span>
                        <select name="items[__IDX__][action]" class="select select-sm select-bordered" required>
                            @foreach ($actions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid md:grid-cols-2 gap-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('Vorher (JSON, optional)') }}</span>
                        <textarea name="items[__IDX__][before]" rows="3"
                                  class="textarea textarea-bordered font-mono text-xs"
                                  placeholder='{"minutes": 60}'></textarea>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Nachher (JSON)') }}</span>
                        <textarea name="items[__IDX__][after]" rows="3"
                                  class="textarea textarea-bordered font-mono text-xs"
                                  placeholder='{"minutes": 90}'></textarea>
                    </label>
                </div>
                <div class="text-right">
                    <button type="button" class="btn btn-xs btn-ghost remove-item">
                        <span class="material-symbols-outlined">delete</span>{{ __('Item entfernen') }}
                    </button>
                </div>
            </div>
        </template>
    </x-index-page>

    @push('scripts')
        <script @cspNonce>
            (function () {
                const list = document.getElementById('items-list');
                const tpl = document.getElementById('item-template');
                let idx = 0;

                function addItem() {
                    const html = tpl.innerHTML.replace(/__IDX__/g, String(idx++));
                    const wrap = document.createElement('div');
                    wrap.innerHTML = html.trim();
                    const node = wrap.firstElementChild;
                    node.querySelector('.remove-item').addEventListener('click', () => node.remove());
                    list.appendChild(node);
                }
                document.getElementById('add-item').addEventListener('click', addItem);
                addItem();
            })();
        </script>
    @endpush
@endsection
