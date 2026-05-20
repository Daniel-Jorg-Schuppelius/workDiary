<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ui.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * UI-only tuning that does not affect persisted business data.
 */

return [
    'calendar' => [
        /** Minute granularity for new entries created via the week view. */
        'slot_minutes' => (int) env('UI_CALENDAR_SLOT_MINUTES', 30),
    ],
    'dashboard' => [
        /** Number of most-recent entries shown on the dashboard. */
        'recent_limit' => (int) env('UI_DASHBOARD_RECENT_LIMIT', 5),
    ],
    'search' => [
        /** Default result limit for type-ahead / quick search endpoints. */
        'results_limit' => (int) env('UI_SEARCH_RESULTS_LIMIT', 20),
    ],
];
