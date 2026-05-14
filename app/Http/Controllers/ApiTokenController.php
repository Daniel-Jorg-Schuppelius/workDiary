<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        return view('profile.api-tokens', [
            'tokens' => $tokens,
            'newToken' => session('newToken'),
            'newTokenName' => session('newTokenName'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);
        $token = $user->createToken($data['name']);

        return redirect()
            ->route('profile.api-tokens.index')
            ->with('newToken', $token->plainTextToken)
            ->with('newTokenName', $data['name']);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('id', $id)->delete();

        return redirect()->route('profile.api-tokens.index')
            ->with('success', __('Token widerrufen.'));
    }
}
