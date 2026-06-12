<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationEventTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Notification\NotificationEvent;
use Tests\TestCase;

final class NotificationEventTest extends TestCase {
    public function test_all_events_have_labels_in_every_supported_locale(): void {
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);

            foreach (NotificationEvent::cases() as $event) {
                $translationKey = 'enums.notification.event.' . $event->value;

                $this->assertNotSame($translationKey, $event->label(), "$locale: $translationKey");
            }
        }
    }
}
