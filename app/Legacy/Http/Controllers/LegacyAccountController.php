<?php

namespace App\Legacy\Http\Controllers;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LegacyAccountController extends Controller
{
    public function editPassword(Request $request): View
    {
        return view('legacy.account._password_dialog', [
            'isDialog' => true,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        $user = Auth::user();

        if (! $user instanceof User || ! Hash::check($data['current_password'], (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'Altes Passwort ist nicht korrekt.',
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        $legacyUserId = (int) ($user->legacy_user_id ?? 0);

        if ($legacyUserId > 0 && filled(config('database.connections.legacy.database'))) {
            try {
                DB::connection('legacy')
                    ->table('user')
                    ->where('id', $legacyUserId)
                    ->update([
                        'userpw' => $data['password'],
                    ]);
            } catch (\Throwable) {
                return back()->with('success', __('Lokales Passwort geändert. Legacy-Passwort konnte nicht synchronisiert werden.'));
            }
        }

        return back()->with('success', __('Passwort erfolgreich geändert.'));
    }
}
