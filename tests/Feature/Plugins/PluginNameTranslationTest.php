<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginNameTranslationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\Fritzbox\FritzboxPlugin;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use Tests\TestCase;

/**
 * Pluginnamen, die keine Marke sind, folgen der Sprache; Google Kalender heißt
 * in der Plugin-Liste wie auf seiner Seite (Phase 137).
 */
final class PluginNameTranslationTest extends TestCase {
    public function test_descriptive_plugin_names_follow_the_locale(): void {
        app()->setLocale('de');
        $this->assertSame('FRITZ!Box-Anrufliste', (new FritzboxPlugin)->name());
        $this->assertSame('Google Kalender', (new GoogleCalendarPlugin)->name());
        $this->assertSame(__('google_calendar::google_calendar.title'), (new GoogleCalendarPlugin)->name());
        $this->assertSame('Fernwartung', (new RemoteSupportPlugin)->name());

        app()->setLocale('en');
        $this->assertSame('FRITZ!Box call list', (new FritzboxPlugin)->name());
        $this->assertSame('Google Calendar', (new GoogleCalendarPlugin)->name());
        $this->assertSame('Remote maintenance', (new RemoteSupportPlugin)->name());
    }
}
