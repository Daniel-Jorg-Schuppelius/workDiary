<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BookmarksWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Models\User;
use Illuminate\Contracts\View\View;

class BookmarksWidget extends Widget {
    public function key(): string {
        return 'bookmarks';
    }

    public function label(): string {
        return (string) __('Lesezeichen');
    }

    public function icon(): string {
        return 'bookmarks';
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.bookmarks', [
            'bookmarks' => $user->bookmarks()->get(),
        ]);
    }
}
