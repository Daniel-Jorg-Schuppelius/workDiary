<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationCenterController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Notification-Center (MVP-018): Liste der eigenen Database-Notifications,
 * Einzeln-/Alle-als-gelesen-markieren. Keine Permission nötig — jeder User
 * sieht ausschließlich seine eigenen Benachrichtigungen.
 */
class NotificationCenterController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = $this->authUser();

        $notifications = $user->notifications()
            ->paginate((int) Setting::get('pagination.notifications', 25));

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'readCount' => $user->readNotifications()->count(),
        ]);
    }

    /** Einzelne Benachrichtigung als gelesen markieren (und ggf. zum Ziel springen). */
    public function read(Request $request, string $id): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        /** @var \Illuminate\Notifications\DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) data_get($notification->data, 'url', '');
        // Open-Redirect-Schutz: nur interne Ziele zulassen (Notification-URLs
        // werden serverseitig via route() erzeugt; fremde Hosts werden ignoriert).
        if ($request->boolean('follow') && $url !== '' && \App\Support\UrlSafety::isSameOriginOrRelative($url, $request->getHost())) {
            return redirect()->to($url);
        }

        return redirect()->back(fallback: route('notifications.index'));
    }

    public function readAll(): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back(fallback: route('notifications.index'))
            ->with('success', __('notification.flash.all_read'));
    }

    /** Einzelne Benachrichtigung löschen (nur die eigene — findOrFail scoped). */
    public function destroy(string $id): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        $user->notifications()->findOrFail($id)->delete();

        return redirect()->back(fallback: route('notifications.index'))
            ->with('success', __('notification.flash.deleted'));
    }

    /** Alle bereits gelesenen Benachrichtigungen des Users löschen. */
    public function destroyRead(): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        $deleted = (int) $user->readNotifications()->delete();

        return redirect()->back(fallback: route('notifications.index'))
            ->with('success', __('notification.flash.read_deleted', ['count' => $deleted]));
    }
}
