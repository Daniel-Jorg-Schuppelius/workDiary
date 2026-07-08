{{--
  Portal-Auftragsdetail (Feature 012, Rang 54/55): read-only — kundensichtbare
  Fotos mit Bestätigen/Beanstanden, Materialliste ohne Preise, kundensichtbare
  Protokolle, Fallakte-PDF über signierten 24-h-Link.
--}}
@extends('customer.layout')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">{{ $diary->title }}</h1>
            <p class="text-sm opacity-70">{{ optional($diary->start_at)->fdate() }} · {{ $diary->status }}</p>
        </div>
        <a href="{{ $pdfUrl }}" class="btn btn-outline btn-sm">{{ __('Fallakte als PDF') }}</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="space-y-6">
        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Fotos') }}</h2>
            @if ($photos->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">photo_library</span>' :title="__('Keine freigegebenen Fotos.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($photos as $photo)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="min-w-0 truncate">{{ $photo->original_name }}</span>
                            <span class="flex items-center gap-2">
                                @if ($confirmedByMe->has($photo->id))
                                    <span class="badge badge-success badge-sm">{{ __('Bestätigt am :date', ['date' => $confirmedByMe[$photo->id]->confirmed_at->fdate()]) }}</span>
                                @else
                                    <form method="POST" action="{{ route('customer.diary.photos.confirm', [$diary, $photo]) }}">
                                        @csrf
                                        <x-button type="submit" tone="primary" size="sm">{{ __('Bestätigen') }}</x-button>
                                    </form>
                                    <details>
                                        <summary class="btn btn-ghost btn-sm">{{ __('Beanstanden') }}</summary>
                                        <form method="POST" action="{{ route('customer.diary.photos.complain', [$diary, $photo]) }}" class="mt-2 flex gap-2">
                                            @csrf
                                            <input type="text" name="note" required minlength="3" maxlength="2000"
                                                   class="input input-sm input-bordered w-64"
                                                   placeholder="{{ __('Was stimmt nicht?') }}">
                                            <x-button type="submit" tone="warning" size="sm">{{ __('Senden') }}</x-button>
                                        </form>
                                    </details>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Material') }}</h2>
            @if ($materials->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' :title="__('Kein Material erfasst.')" compact />
            @else
                <x-table>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Bezeichnung') }}</x-table.th>
                            <x-table.th>{{ __('Menge') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($materials as $usage)
                        <tr>
                            <td>{{ $usage->description }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim((string) $usage->quantity, '0'), '.') }} {{ $usage->unit }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 font-semibold">{{ __('Protokolle') }}</h2>
            @if ($protocols->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>' :title="__('Keine freigegebenen Protokolle.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($protocols as $protocol)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $protocol->title }}</span>
                            <span class="opacity-70">{{ $protocol->status->label() }} · {{ optional($protocol->occurred_at)->fdate() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
