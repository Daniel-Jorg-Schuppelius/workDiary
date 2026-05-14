<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountPasswordController extends Controller {
    public function edit(Request $request): View {
        return view('account._password_dialog', [
            'mustChange' => (bool) (Auth::user()->must_change_password ?? false),
            'isDialog' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $mustChange = (bool) ($user->must_change_password ?? false);

        $rules = [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        // Wenn der User sein Passwort regulär ändern will (nicht erzwungen),
        // verlangen wir das aktuelle Passwort.
        if (! $mustChange) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $request->validate($rules);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('success', __('Passwort wurde aktualisiert.'));
    }
}
