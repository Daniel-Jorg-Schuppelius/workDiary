<!doctype html>
<html lang="de" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Protokoll unterschreiben') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4">
    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ $protocol->title }}</h1>
        <div class="mt-1 text-sm text-base-content/70">
            {{ $protocol->type->label() }} · {{ $protocol->occurred_at?->fdatetime() }}
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error mb-4"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($protocol->description)
        <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
            <h2 class="text-sm font-semibold">{{ __('protocol.field.description') }}</h2>
            <p class="whitespace-pre-line text-sm">{{ $protocol->description }}</p>
        </div>
    @endif

    @if($protocol->items->isNotEmpty())
        <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
            <h2 class="mb-2 text-sm font-semibold">{{ __('protocol.pdf.items') }}</h2>
            <table class="table table-sm">
                <thead><tr><th>#</th><th>{{ __('protocol.pdf.col.label') }}</th><th>{{ __('protocol.pdf.col.result') }}</th></tr></thead>
                <tbody>
                @foreach($protocol->items->sortBy('sort_order') as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->label ?: $item->description }}</td>
                        <td>{{ $item->result?->label() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="POST" action="{{ route('protocols.public-sign.submit', ['token' => $token]) }}" class="rounded-box bg-base-100 p-4 shadow">
        @csrf
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('Name des Unterzeichners') }}</span></label>
            <input type="text" name="signer_name" class="input input-bordered" value="{{ old('signer_name', $record->signer_name) }}" required maxlength="120">
        </div>
        <input type="hidden" name="signature_image_path" value="{{ old('signature_image_path') }}">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="accept" value="1" class="checkbox" required>
            <span class="label-text">{{ __('Ich bestätige die Richtigkeit der Angaben.') }}</span>
        </label>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('Unterschreiben') }}</button>
        </div>
    </form>
</main>
</body>
</html>
