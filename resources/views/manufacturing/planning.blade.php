@extends('layouts.app')
@section('title', __('manufacturing.planning.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.planning.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('manufacturing.planning.subtitle')" />
    </x-slot:toolbar>

    <x-card>
        <form method="GET" action="{{ route('manufacturing-planning.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="fieldset min-w-0 grow">
                <label class="fieldset-label" for="plan-article">{{ __('Artikel') }}</label>
                <select id="plan-article" name="article" class="select select-sm select-bordered w-full" onchange="this.form.submit()">
                    @foreach ($articles as $a)
                        <option value="{{ $a->sqid }}" @selected($article && $article->id === $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label" for="plan-qty">{{ __('manufacturing.order.field.target_qty') }}</label>
                <input id="plan-qty" name="qty" type="number" step="0.0001" min="0.0001" value="{{ $qty }}"
                       class="input input-sm input-bordered w-28">
            </div>
            <div class="fieldset">
                {{-- Unsichtbarer Label-Platzhalter → Button bündig zu den Feldern. --}}
                <label class="fieldset-label invisible select-none hidden sm:block" aria-hidden="true">&nbsp;</label>
                <x-icon-btn icon="account_tree" tone="primary" size="sm" type="submit" show-label>{{ __('manufacturing.planning.explode') }}</x-icon-btn>
            </div>
        </form>
    </x-card>

    @if ($article)
        <x-card padding="p-0">
            <div class="flex items-center gap-2 p-4 pb-0">
                <span class="grid size-9 shrink-0 place-items-center rounded-box bg-primary/10 text-primary"><x-icon name="account_tree" class="text-xl" /></span>
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('manufacturing.planning.requirements') }}</h2>
            </div>
            @if (empty($lines))
                <div class="p-4"><x-empty-state :title="__('manufacturing.planning.no_bom')" /></div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Artikel') }}</th>
                            <th class="text-center">{{ __('manufacturing.planning.level') }}</th>
                            <th>{{ __('manufacturing.planning.source') }}</th>
                            <th class="text-right">{{ __('manufacturing.planning.gross') }}</th>
                            <th class="text-right">{{ __('manufacturing.planning.net') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($lines as $line)
                        <tr>
                            <td style="padding-left: {{ 0.5 + ($line['level'] - 1) * 1.25 }}rem">{{ $articleNames[$line['article_id']] ?? $line['article_id'] }}</td>
                            <td class="text-center tabular-nums">{{ $line['level'] }}</td>
                            <td>
                                <span class="badge badge-sm {{ $line['source'] === 'make' ? 'badge-primary' : '' }}">
                                    {{ __('manufacturing.planning.' . $line['source']) }}
                                </span>
                            </td>
                            <td class="text-right tabular-nums">{{ $line['gross'] }}</td>
                            <td class="text-right tabular-nums">{{ $line['net'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        @if ($metrics)
            <x-card>
                <div class="mb-3 flex items-center gap-2">
                    <span class="grid size-9 shrink-0 place-items-center rounded-box bg-primary/10 text-primary"><x-icon name="verified" class="text-xl" /></span>
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('manufacturing.planning.quality') }}</h2>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div><div class="opacity-60">{{ __('manufacturing.order.field.produced') }}</div><div class="text-lg tabular-nums">{{ $metrics['produced'] }}</div></div>
                    <div><div class="opacity-60">{{ __('manufacturing.planning.yield') }}</div><div class="text-lg tabular-nums">{{ $metrics['yield'] }}</div></div>
                    <div><div class="opacity-60">{{ __('manufacturing.planning.scrap_rate') }}</div><div class="text-lg tabular-nums">{{ $metrics['scrap_rate'] }}</div></div>
                    <div><div class="opacity-60">{{ __('manufacturing.planning.rework_rate') }}</div><div class="text-lg tabular-nums">{{ $metrics['rework_rate'] }}</div></div>
                </div>
            </x-card>
        @endif

        @if (! empty($spc))
            <x-card padding="p-0">
                <div class="flex items-center gap-2 p-4 pb-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-box bg-primary/10 text-primary"><x-icon name="monitoring" class="text-xl" /></span>
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('manufacturing.planning.spc') }}</h2>
                </div>
                <x-table bare class="table-sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('manufacturing.planning.measurement') }}</th>
                            <th class="text-right">n</th>
                            <th class="text-right">x̄</th>
                            <th class="text-right">σ</th>
                            <th class="text-right">Cp</th>
                            <th class="text-right">Cpk</th>
                            <th class="text-right">{{ __('manufacturing.planning.out_of_spec') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($spc as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['metrics']['count'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['metrics']['mean'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['metrics']['std_dev'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['metrics']['cp'] ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $row['metrics']['cpk'] ?? '—' }}</td>
                            <td class="text-right tabular-nums {{ $row['metrics']['out_of_spec'] > 0 ? 'text-error font-semibold' : '' }}">{{ $row['metrics']['out_of_spec'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
