{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recipe.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rezeptur / Materialbedarf (MVP-455): branchenneutrale Positionen am Draft,
     unveränderlich nach Veröffentlichung; Partyservice-Aufsatz nur im
     Profil-Kontext. Eigenständige Formulare — bewusst AUSSERHALB des
     Stammdaten-Formulars der Seite. --}}
@php
    /** @var \App\Models\ProcedureTemplateVersion|null $recipeVersion */
    $recipeEditable = $draft !== null && $recipeVersion !== null && $recipeVersion->id === $draft->id;
    $kindOptions = [
        \App\Enums\Manufacturing\QuantityKind::Fixed->value => __('recipes.kind.fixed'),
        \App\Enums\Manufacturing\QuantityKind::PerUnit->value => __('recipes.kind.per_unit'),
        \App\Enums\Manufacturing\QuantityKind::Ratio->value => __('recipes.kind.ratio'),
    ];
@endphp

<div id="rezeptur" class="mt-6 space-y-4">
    <x-card>
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.title.materials') }}</h2>
            @if ($recipeVersion !== null)
                <span class="text-xs text-muted">
                    {{ __('recipes.hint.version', ['version' => $recipeVersion->version]) }}
                    @unless ($recipeEditable) · {{ __('recipes.hint.readonly') }} @endunless
                </span>
            @endif
        </div>
        <p class="mb-3 text-xs text-muted">{{ __('recipes.hint.materials') }}</p>

        @if ($recipeVersion === null)
            <p class="text-sm text-muted">{{ __('recipes.empty.no_version') }}</p>
        @else
            <x-table :bare="true" :empty-title="__('recipes.empty.no_materials')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('recipes.field.position') }}</th>
                        <th>{{ __('recipes.field.article') }}</th>
                        <th>{{ __('recipes.field.kind') }}</th>
                        <th class="text-right">{{ __('recipes.field.quantity') }}</th>
                        <th>{{ __('recipes.field.unit') }}</th>
                        <th class="text-right">{{ __('recipes.field.waste') }}</th>
                        <th>{{ __('recipes.field.tool') }}</th>
                        @if ($recipeEditable)<th class="text-right">{{ __('recipes.field.actions') }}</th>@endif
                    </tr>
                </x-slot:head>
                            @foreach ($recipeRequirements as $req)
                                <tr class="hover">
                                    <td><code class="text-xs">{{ $req->position_code }}</code></td>
                                    <td>{{ $req->article?->number }} — {{ $req->article?->name }}</td>
                                    <td>{{ $kindOptions[$req->quantity_kind->value] ?? $req->quantity_kind->value }}</td>
                                    <td class="text-right">
                                        {{ $req->quantity_kind === \App\Enums\Manufacturing\QuantityKind::Ratio ? $req->ratio_part : $req->quantity?->getNumericValue() }}
                                    </td>
                                    <td>{{ $req->unit }}</td>
                                    <td class="text-right">{{ $req->waste_surcharge !== null ? $req->waste_surcharge . ' %' : '—' }}</td>
                                    <td>{{ $req->is_tool ? __('recipes.field.tool_yes') : '—' }}</td>
                                    @if ($recipeEditable)
                                        <td class="text-right">
                                            <div class="flex justify-end">
                                                <form method="POST" action="{{ route('procedures.materials.destroy', [$template, $recipeVersion, $req]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('recipes.action.remove') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
            </x-table>

            @if ($recipeEditable)
                <form method="POST" action="{{ route('procedures.materials.store', [$template, $recipeVersion]) }}" class="mt-4 grid gap-2 md:grid-cols-7 items-end">
                    @csrf
                    <label class="form-control md:col-span-2">
                        <span class="label-text">{{ __('recipes.field.article') }}</span>
                        <select name="article" required class="select select-bordered select-sm">
                            <option value="">{{ __('recipes.field.article_placeholder') }}</option>
                            @foreach ($recipeArticles as $article)
                                <option value="{{ $article->sqid }}" @selected(old('article') === $article->sqid)>{{ $article->number }} — {{ $article->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.kind') }}</span>
                        <select name="quantity_kind" class="select select-bordered select-sm">
                            @foreach ($kindOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('quantity_kind', 'per_unit') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.quantity_or_ratio') }}</span>
                        <input type="number" name="quantity" step="0.0001" min="0" value="{{ old('quantity') }}" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.unit') }}</span>
                        <input type="text" name="unit" maxlength="20" required value="{{ old('unit', 'Stk') }}" class="input input-bordered input-sm w-24">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.waste') }}</span>
                        <input type="number" name="waste_surcharge" step="0.001" min="0" max="100" value="{{ old('waste_surcharge') }}" class="input input-bordered input-sm w-24">
                    </label>
                    <div class="flex items-center gap-2">
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="is_tool" value="1" class="checkbox checkbox-sm" @checked(old('is_tool'))>
                            <span class="label-text">{{ __('recipes.field.tool') }}</span>
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('recipes.action.add') }}</button>
                    </div>
                    <p class="text-xs text-muted md:col-span-7">{{ __('recipes.hint.ratio_input') }}</p>
                </form>
            @endif
        @endif
    </x-card>

    @if ($recipePartyActive && $recipeVersion !== null)
        {{-- Partyservice: Grundausbeute, Skalierung, Plankosten --}}
        <x-card>
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.title.party') }}</h2>
                <a href="{{ route('recipe-menus.index') }}" class="btn btn-xs btn-ghost">{{ __('recipes.menu.title') }} →</a>
            </div>
            <p class="mb-3 text-xs text-muted">{{ __('recipes.hint.party') }}</p>

            <form method="POST" action="{{ route('procedures.recipe-profile.save', [$template, $recipeVersion]) }}" class="grid gap-2 md:grid-cols-5 items-end">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.field.base_portions') }}</span>
                    <input type="number" name="base_portions" step="0.01" min="0.01" required value="{{ old('base_portions', $recipeProfile?->base_portions ?? '1') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.field.base_yield') }}</span>
                    <input type="number" name="base_yield_qty" step="0.001" min="0.001" value="{{ old('base_yield_qty', $recipeProfile?->base_yield_qty) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.field.yield_unit') }}</span>
                    <input type="text" name="yield_unit" maxlength="20" value="{{ old('yield_unit', $recipeProfile?->yield_unit) }}" class="input input-bordered input-sm w-24">
                </label>
                <div class="md:col-span-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('recipes.action.save_profile') }}</button>
                </div>

                {{-- Allergen-Abweichungen (mit Begründung, auditiert) --}}
                <fieldset class="md:col-span-5 grid gap-2 md:grid-cols-3 rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-xs text-muted">{{ __('recipes.title.allergen_overrides') }}</legend>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.allergen_added') }}</span>
                        <select name="allergen_added[]" multiple size="4" class="select select-bordered select-sm">
                            @foreach ($recipeAllergenOptions as $option)
                                <option value="{{ $option->code }}" @selected(in_array($option->code, $recipeProfile?->addedAllergens() ?? [], true))>{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.allergen_removed') }}</span>
                        <select name="allergen_removed[]" multiple size="4" class="select select-bordered select-sm">
                            @foreach ($recipeAllergenOptions as $option)
                                <option value="{{ $option->code }}" @selected(in_array($option->code, $recipeProfile?->removedAllergens() ?? [], true))>{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.field.override_reason') }}</span>
                        <textarea name="override_reason" maxlength="500" rows="4" class="textarea textarea-bordered textarea-sm">{{ old('override_reason', $recipeProfile?->overrideReason()) }}</textarea>
                    </label>
                </fieldset>
            </form>
        </x-card>

        {{-- Allergenlage --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.title.allergens') }}</h2>
            @error('allergens')
                <div class="alert alert-error mb-2 text-sm">{{ $message }}</div>
            @enderror
            @if ($recipeAllergens !== null)
                <div class="mb-2 flex flex-wrap gap-1">
                    @forelse ($recipeAllergens['effective'] as $code)
                        <span class="badge badge-warning badge-sm">{{ $recipeAllergenOptions->firstWhere('code', $code)?->label ?? $code }}</span>
                    @empty
                        <span class="text-sm text-muted">{{ __('recipes.allergens.none') }}</span>
                    @endforelse
                </div>
                @if ($recipeAllergens['unresolved'] !== [])
                    <div class="alert alert-warning text-sm">
                        <div>
                            <p class="font-medium">{{ __('recipes.allergens.unresolved_heading') }}</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($recipeAllergens['unresolved'] as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    {{-- Zutaten-Allergene direkt pflegen --}}
                    @foreach ($recipeRequirements->filter(fn($r) => ! $r->is_tool && $r->article !== null && in_array((int) $r->article_id, $recipeAllergens['unresolved_ids'], true))->unique('article_id') as $req)
                        <form method="POST" action="{{ route('procedures.ingredient-allergens.save', [$template, $recipeVersion, $req->article]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                            @csrf
                            <span class="text-sm">{{ $req->article->number }} — {{ $req->article->name }}:</span>
                            <select name="allergens[]" multiple size="3" class="select select-bordered select-sm">
                                @foreach ($recipeAllergenOptions as $option)
                                    <option value="{{ $option->code }}">{{ $option->label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-xs btn-primary">{{ __('recipes.action.save_allergens') }}</button>
                        </form>
                    @endforeach
                @endif
            @endif
        </x-card>

        {{-- Skalierung + Plankosten --}}
        <x-card>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.title.plan') }}</h2>
                <form method="GET" action="{{ route('procedures.edit', $template) }}" class="flex items-center gap-2">
                    <label class="text-sm" for="recipe-portions">{{ __('recipes.field.portions') }}</label>
                    <input id="recipe-portions" type="number" name="portions" step="0.01" min="0.01" value="{{ $recipePortions }}" class="input input-bordered input-sm w-28">
                    <button type="submit" class="btn btn-sm">{{ __('recipes.action.scale') }}</button>
                </form>
            </div>
            @if ($recipePlan !== null)
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('recipes.field.article') }}</th>
                            <th class="text-right">{{ __('recipes.field.demand') }}</th>
                            <th>{{ __('recipes.field.unit') }}</th>
                            <th class="text-right">{{ __('recipes.field.cost') }}</th>
                        </tr>
                    </x-slot:head>
                    <x-slot:foot>
                        <tr>
                            <th colspan="3" class="text-right">{{ __('recipes.plan.total') }}</th>
                            <th class="text-right">{{ $recipePlan['total']?->format() ?? '—' }}</th>
                        </tr>
                        <tr>
                            <th colspan="3" class="text-right">{{ __('recipes.plan.per_portion') }}</th>
                            <th class="text-right">{{ $recipePlan['per_portion']?->format() ?? '—' }}</th>
                        </tr>
                    </x-slot:foot>
                    @foreach ($recipePlan['lines'] as $line)
                        <tr class="hover">
                            <td>{{ $line['label'] }}</td>
                            <td class="text-right">{{ $line['demand'] }}</td>
                            <td>{{ $line['unit'] }}</td>
                            <td class="text-right">{{ $line['cost']?->format() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
                @if ($recipePlan['incomplete'] !== [])
                    <p class="mt-2 text-xs text-warning">{{ implode(' · ', $recipePlan['incomplete']) }}</p>
                @endif
            @endif
        </x-card>
    @endif
</div>
