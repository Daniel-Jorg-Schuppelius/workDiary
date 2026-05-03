<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller {
    public function index(Request $request): View {
        $tokens = $request->user()->tokens()->orderByDesc('created_at')->get();
        return view('profile.api-tokens', [
            'tokens' => $tokens,
            'newToken' => session('newToken'),
            'newTokenName' => session('newTokenName'),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);
        $token = $request->user()->createToken($data['name']);
        return redirect()
            ->route('profile.api-tokens.index')
            ->with('newToken', $token->plainTextToken)
            ->with('newTokenName', $data['name']);
    }

    public function destroy(Request $request, int $id): RedirectResponse {
        $request->user()->tokens()->where('id', $id)->delete();
        return redirect()->route('profile.api-tokens.index')
            ->with('success', __('Token widerrufen.'));
    }
}
