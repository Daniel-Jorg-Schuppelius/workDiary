<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pagination.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Default page sizes per entity. Organizations may override individual
 * values via Organization::settings['pagination']. All values can also be
 * tuned via environment variables for instance-wide defaults.
 */

return [
    'timesheets' => (int) env('PAGINATION_TIMESHEETS', 20),
    'duty_plans' => (int) env('PAGINATION_DUTY_PLANS', 20),
    'customers' => (int) env('PAGINATION_CUSTOMERS', 25),
    'organizations' => (int) env('PAGINATION_ORGANIZATIONS', 25),
    'tours' => (int) env('PAGINATION_TOURS', 25),
    'vehicles' => (int) env('PAGINATION_VEHICLES', 25),
    'tags' => (int) env('PAGINATION_TAGS', 50),
    'archive' => (int) env('PAGINATION_ARCHIVE', 25),
    'notifications' => (int) env('PAGINATION_NOTIFICATIONS', 25),
    'remote_pending_groups' => (int) env('PAGINATION_REMOTE_PENDING_GROUPS', 10),
    'remote_shared_devices' => (int) env('PAGINATION_REMOTE_SHARED_DEVICES', 8),
    'remote_shared_sessions' => (int) env('PAGINATION_REMOTE_SHARED_SESSIONS', 30),
];
