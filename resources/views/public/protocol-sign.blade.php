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

    @if($record->used_at)
        <div class="alert alert-info mb-4">
            {{ __('protocol.signature.alreadyDecided') }}
        </div>
    @else
    <form method="POST" action="{{ route('protocols.public-sign.submit', ['token' => $token]) }}" class="mb-4 rounded-box bg-base-100 p-4 shadow">
        @csrf
        <h2 class="mb-2 text-sm font-semibold">{{ __('protocol.signature.approveHeading') }}</h2>
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

    <form method="POST" action="{{ route('protocols.public-sign.reject', ['token' => $token]) }}" class="mb-4 rounded-box border border-error/30 bg-base-100 p-4 shadow">
        @csrf
        <h2 class="mb-2 text-sm font-semibold text-error">{{ __('protocol.signature.rejectHeading') }}</h2>
        <p class="mb-3 text-xs text-base-content/70">{{ __('protocol.signature.rejectHint') }}</p>
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('Name') }}</span></label>
            <input type="text" name="signer_name" class="input input-bordered" value="{{ old('signer_name', $record->signer_name) }}" maxlength="120">
        </div>
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('protocol.signature.rejectReason') }}</span></label>
            <textarea name="reason" class="textarea textarea-bordered" rows="3" required minlength="3" maxlength="2000">{{ old('reason') }}</textarea>
        </div>
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('protocol.signature.rejectIssues') }}</span></label>
            <textarea name="issues[]" class="textarea textarea-bordered" rows="2" maxlength="200" placeholder="{{ __('protocol.signature.rejectIssuesPlaceholder') }}"></textarea>
            <textarea name="issues[]" class="textarea textarea-bordered mt-2" rows="2" maxlength="200"></textarea>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-outline btn-error">{{ __('protocol.signature.rejectSubmit') }}</button>
        </div>
    </form>
    @endif

    <form method="POST" action="{{ route('protocols.public-sign.query', ['token' => $token]) }}" class="mb-4 rounded-box bg-base-100 p-4 shadow">
        @csrf
        <h2 class="mb-2 text-sm font-semibold">{{ __('protocol.signature.queryHeading') }}</h2>
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('Name') }}</span></label>
            <input type="text" name="asker_name" class="input input-bordered" value="{{ old('asker_name', $record->signer_name) }}" maxlength="120">
        </div>
        <div class="form-control mb-3">
            <label class="label"><span class="label-text">{{ __('protocol.signature.queryQuestion') }}</span></label>
            <textarea name="question" class="textarea textarea-bordered" rows="3" required minlength="3" maxlength="2000">{{ old('question') }}</textarea>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-secondary">{{ __('protocol.signature.querySubmit') }}</button>
        </div>
    </form>

    @if($queries->isNotEmpty())
        <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
            <h2 class="mb-2 text-sm font-semibold">{{ __('protocol.signature.queryHistory') }}</h2>
            <ul class="space-y-3">
                @foreach($queries as $q)
                    <li class="rounded-box bg-base-200 p-3">
                        <div class="text-sm font-medium">{{ $q->question }}</div>
                        @if($q->answer)
                            <div class="mt-2 border-l-2 border-primary/40 pl-3 text-sm text-base-content/80">
                                <span class="text-xs uppercase text-base-content/50">{{ __('protocol.signature.queryAnswer') }}</span><br>
                                {{ $q->answer }}
                            </div>
                        @else
                            <div class="mt-1 text-xs text-base-content/50">{{ __('protocol.signature.queryPending') }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</main>
</body>
</html>
