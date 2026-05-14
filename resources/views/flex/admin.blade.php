@extends('layouts.app')
@section('title', __('Gleitzeit – Team'))
@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Gleitzeit – Team') }}</h1>
    <form method="GET" class="flex flex-wrap gap-2">
        <select name="user" class="select select-sm select-bordered" onchange="this.form.submit()">
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected($user->id===$u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="number" name="year"  value="{{ $year }}"  class="input input-sm input-bordered w-24">
        <input type="number" name="month" value="{{ $month }}" class="input input-sm input-bordered w-20">
        <button class="btn btn-sm">{{ __('Anzeigen') }}</button>
    </form>
    @include('flex.index', ['isAdmin' => true])
@endsection
