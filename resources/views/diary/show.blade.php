@extends('layouts.app')
@section('title', truncate($diary->content, 60) . ' — WorkDiary')
@section('nav-title', __('Eintrag') . ' #' . $diary->id)

@section('content')
    <div class="mx-auto flex h-full min-h-0 w-full max-w-4xl flex-col gap-4">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
            <div class="h-full overflow-auto p-6 md:p-8 space-y-6">
                @include('diary._show_body', ['isDialog' => false])
                <div class="text-center">
                    <a href="{{ route('diary.index') }}" class="text-sm text-primary hover:opacity-80 transition">← {{ __('Zurück zur Liste') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
