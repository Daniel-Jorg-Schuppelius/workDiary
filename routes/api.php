<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : api.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Api\{ArticleApiController, AssetStatusVisibilityController, AssetTimelineController, AttachmentController, AttendanceController, CommentController, CustomerController, DashboardController, DiaryController, EmergencyAssignmentController, FlexController, HookController, InventoryApiController, LocationController, MaterialController, MeController, OnCallShiftController, ProjectController, ProtocolApiController, PurchaseOrderApiController, PushSubscriptionController, StopwatchController, SupplierApiController, TagController, TaskController, TimesheetController, TimesheetEntryController, TimesheetMaterialController, VehicleApiController};
use App\Http\Middleware\{DeprecatedApiAlias, EnforcePlanModules};
use Illuminate\Support\Facades\Route;

// Siehe routes/web.php: Projekt-Bindung akzeptiert ID/Sqid oder
// "<kunde>/<projekt>". [A-Za-z0-9]+ setzt ein alphanumerisches Sqid-Alphabet
// voraus (abgesichert via SqidRoutePatternTest).
Route::pattern('project', '[A-Za-z0-9]+|[a-z0-9-]+/[a-z0-9-]+');

// Standort-Ingest von Geräte-Apps (OwnTracks/Traccar). Auth über Pro-Gerät-Token
// im Pfad statt Sanctum – die Apps können sich nicht interaktiv anmelden.
// Geräte-Scan der Wächterrundgänge (Feature 089): NFC-/Scanner-Gerät meldet
// den Checkpoint-Token, authentifiziert über das Standort-Geräte-Token.
Route::post('patrol/scan/{token}', [\App\Http\Controllers\Api\PatrolScanController::class, 'scan'])
    ->middleware('throttle:60,1')
    ->name('api.patrol.scan');

Route::match(['get', 'post'], 'location/ingest/{token}', [LocationController::class, 'ingest'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:webhook-ingest')
    ->name('api.location.ingest');

// CTI-Webhook (Feature 056, MVP-118): Telefonanlagen/Provider (sipgate u. a.)
// POSTen Anruf-Ereignisse. Auth über einen Token im Pfad; nur Metadaten,
// nie Gesprächsinhalte.
Route::match(['get', 'post'], 'cti/webhook/{token}', \App\Http\Controllers\Api\CtiWebhookController::class)
    ->where('token', '[A-Za-z0-9_]+')
    ->middleware('throttle:webhook-ingest')
    ->name('api.cti.webhook');

// Terminal-Ingest (Feature 061, MVP-130): Hardware-Stempelterminals POSTen
// Badge-Scans. Auth über einen Gerätetoken im Pfad (Muster location/ingest).
Route::post('terminal/ingest/{token}', \App\Http\Controllers\Api\TerminalIngestController::class)
    ->where('token', '[A-Za-z0-9_]+')
    // MVP-516: eigener Limiter (IP + Gerätetoken) statt nur IP.
    ->middleware('throttle:terminal-ingest')
    ->name('api.terminal.ingest');

// ── Versionierte Sanctum-API (MVP-717, Vollscan J10) ─────────────────────────
// Kanonisch sind die Routen unter `/api/v1/…` (Namen `api.*`). Die
// unversionierten Pfade bleiben als Kompatibilitäts-Alias (Namen
// `api.legacy.*`) mit `Deprecation`/`Sunset`-Header (DeprecatedApiAlias)
// erreichbar; Ingest-/Webhook-Endpunkte oben sind davon ausgenommen.
$sanctumRoutes = static function (): void {
    Route::get('me', MeController::class)->name('me');

    // Punktueller Browser-Standort-Stempel (navigator.geolocation).
    Route::post('location/stamp', [LocationController::class, 'stamp'])->middleware('ability:location:write')->name('location.stamp');

    // Aufträge (Feature 008 → Rang 60): Lesen vs. Schreiben getrennt gescopt.
    // Bestandstokens (`*`) matchen weiterhin jede Ability.
    Route::get('diary', [DiaryController::class, 'index'])->middleware('ability:diary:read')->name('diary.index');
    Route::get('diary/{diary}', [DiaryController::class, 'show'])->middleware('ability:diary:read')->name('diary.show');
    Route::post('diary', [DiaryController::class, 'store'])->middleware('ability:diary:write')->name('diary.store');
    Route::put('diary/{diary}', [DiaryController::class, 'update'])->middleware('ability:diary:write')->name('diary.update');
    Route::patch('diary/{diary}', [DiaryController::class, 'update'])->middleware('ability:diary:write')->name('diary.patch');
    Route::delete('diary/{diary}', [DiaryController::class, 'destroy'])->middleware('ability:diary:write')->name('diary.destroy');
    Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->middleware('ability:diary:write')->name('diary.archive');
    Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->middleware('ability:diary:write')->name('diary.restore');

    // Ticketeingang (Feature 065, MVP-152): minimal, org-gebunden.
    Route::post('tickets', [\App\Http\Controllers\Api\TicketController::class, 'store'])->middleware('ability:tickets:write')->name('tickets.store');

    Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->middleware('ability:comments:write')->name('diary.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->middleware('ability:comments:write')->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->middleware('ability:comments:write')->name('comments.destroy');

    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['diary', 'comment', 'shift', 'assignment', 'asset'])
        ->middleware('ability:attachments:write')
        ->name('attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->middleware('ability:attachments:read')->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->middleware('ability:attachments:write')->name('attachments.destroy');

    Route::get('tags', [TagController::class, 'index'])->middleware('ability:tags:read')->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->middleware('ability:tags:write')->name('tags.store');
    Route::put('tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:write')->name('tags.update');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:write')->name('tags.patch');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware('ability:tags:write')->name('tags.destroy');

    Route::get('shifts', [OnCallShiftController::class, 'index'])->middleware('ability:shifts:read')->name('shifts.index');
    Route::get('shifts/{shift}', [OnCallShiftController::class, 'show'])->middleware('ability:shifts:read')->name('shifts.show');

    Route::get('assignments', [EmergencyAssignmentController::class, 'index'])->middleware('ability:assignments:read')->name('assignments.index');
    Route::get('assignments/{assignment}', [EmergencyAssignmentController::class, 'show'])->middleware('ability:assignments:read')->name('assignments.show');

    Route::get('dashboard', DashboardController::class)->middleware('ability:dashboard:read')->name('dashboard');

    Route::get('assets/{asset}/timeline', AssetTimelineController::class)
        ->middleware('ability:assets:read')->name('assets.timeline');

    Route::get('assets/{asset}/status-visibility', AssetStatusVisibilityController::class)
        ->middleware('ability:assets:read')->name('assets.status-visibility');

    Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('push.vapid');
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->middleware('ability:push:write')->name('push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware('ability:push:write')->name('push.unsubscribe');

    // ── Stundenzettel / Material / Flex / Stoppuhr ─────────────────────────
    Route::get('timesheets', [TimesheetController::class, 'index'])->middleware('ability:timesheets:read')->name('timesheets.index');
    Route::post('projects/{project}/timesheets', [TimesheetController::class, 'store'])->middleware('ability:timesheets:write')->name('timesheets.store');
    Route::get('timesheets/{timesheet}', [TimesheetController::class, 'show'])->middleware('ability:timesheets:read')->name('timesheets.show');
    Route::put('timesheets/{timesheet}', [TimesheetController::class, 'update'])->middleware('ability:timesheets:write')->name('timesheets.update');
    Route::delete('timesheets/{timesheet}', [TimesheetController::class, 'destroy'])->middleware('ability:timesheets:write')->name('timesheets.destroy');
    Route::post('timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])->middleware('ability:timesheets:write')->name('timesheets.submit');
    Route::post('timesheets/{timesheet}/sign', [TimesheetController::class, 'sign'])->middleware('ability:timesheets:write')->name('timesheets.sign');
    Route::get('timesheets/{timesheet}/pdf', [TimesheetController::class, 'pdf'])->middleware('ability:timesheets:read')->name('timesheets.pdf');

    Route::get('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'index'])->middleware('ability:timesheets:read')->name('timesheets.entries.index');
    Route::post('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'store'])->middleware('ability:timesheets:write')->name('timesheets.entries.store');
    Route::put('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'update'])->middleware('ability:timesheets:write')->name('timesheets.entries.update');
    Route::delete('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'destroy'])->middleware('ability:timesheets:write')->name('timesheets.entries.destroy');

    Route::get('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'index'])->middleware('ability:timesheets:read')->name('timesheets.materials.index');
    Route::post('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'store'])->middleware('ability:timesheets:write')->name('timesheets.materials.store');
    Route::put('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'update'])->middleware('ability:timesheets:write')->name('timesheets.materials.update');
    Route::delete('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'destroy'])->middleware('ability:timesheets:write')->name('timesheets.materials.destroy');

    Route::get('materials', [MaterialController::class, 'index'])->middleware('ability:materials:read')->name('materials.index');

    Route::get('stopwatch', [StopwatchController::class, 'current'])->middleware('ability:stopwatch:read')->name('stopwatch.current');
    Route::post('stopwatch/start', [StopwatchController::class, 'start'])->middleware('ability:stopwatch:write')->name('stopwatch.start');
    Route::post('stopwatch/stop', [StopwatchController::class, 'stop'])->middleware('ability:stopwatch:write')->name('stopwatch.stop');

    Route::get('attendance/current', [AttendanceController::class, 'current'])->middleware('ability:attendance:read')->name('attendance.current');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->middleware('ability:attendance:write')->name('attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->middleware('ability:attendance:write')->name('attendance.clock-out');

    Route::get('flex/summary', [FlexController::class, 'summary'])->middleware('ability:flex:read')->name('flex.summary');

    // ── Customers / Projects / Tasks (Kimai-Parity) ────────────────────────
    // apiResource in Einzelrouten aufgelöst, um Lesen/Schreiben getrennt zu scopen.
    Route::get('customers', [CustomerController::class, 'index'])->middleware('ability:customers:read')->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('ability:customers:read')->name('customers.show');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('ability:customers:write')->name('customers.store');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('ability:customers:write')->name('customers.update');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->middleware('ability:customers:write')->name('customers.patch');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('ability:customers:write')->name('customers.destroy');

    Route::get('projects', [ProjectController::class, 'index'])->middleware('ability:projects:read')->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('ability:projects:read')->name('projects.show');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('ability:projects:write')->name('projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('ability:projects:write')->name('projects.update');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('ability:projects:write')->name('projects.patch');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('ability:projects:write')->name('projects.destroy');
    Route::get('tasks', [TaskController::class, 'index'])->middleware('ability:tasks:read')->name('tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('ability:tasks:read')->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('ability:tasks:write')->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('ability:tasks:write')->name('tasks.destroy');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->middleware('ability:tasks:write')->name('tasks.store');

    // ── Kernobjekte Abwesenheiten/Spesen/Rechnungen/Schichtplan ────────────
    // (Feature 008 MVP; Vollaudit 2026-07, M3) — read-first: Erzeugung bleibt
    // den Web-Workflows vorbehalten (Genehmigung, GoBD).
    Route::get('absences', [\App\Http\Controllers\Api\AbsenceController::class, 'index'])->middleware('ability:absences:read')->name('absences.index');
    Route::get('expenses', [\App\Http\Controllers\Api\ExpenseApiController::class, 'index'])->middleware('ability:expenses:read')->name('expenses.index');
    Route::get('expenses/{expense}', [\App\Http\Controllers\Api\ExpenseApiController::class, 'show'])->middleware('ability:expenses:read')->name('expenses.show');
    Route::get('invoices', [\App\Http\Controllers\Api\InvoiceApiController::class, 'index'])->middleware('ability:invoices:read')->name('invoices.index');
    Route::get('invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceApiController::class, 'show'])->middleware('ability:invoices:read')->name('invoices.show');
    Route::get('scheduled-shifts', [\App\Http\Controllers\Api\ScheduledShiftApiController::class, 'index'])->middleware('ability:scheduled-shifts:read')->name('scheduled-shifts.index');

    // ── REST-Hooks für n8n/Make/Zapier (Feature 008 → Rang 61) ─────────────
    // Eigene Ability `hooks:manage`; Zustellung/Signatur/Auto-Disable liegen in
    // der bestehenden Webhook-Infrastruktur. `events` VOR `{hook}` registrieren.
    Route::middleware('ability:hooks:manage')->group(function (): void {
        Route::get('hooks', [HookController::class, 'index'])->name('hooks.index');
        Route::get('hooks/events', [HookController::class, 'events'])->name('hooks.events');
        Route::post('hooks', [HookController::class, 'store'])->name('hooks.store');
        Route::post('hooks/{hook}/test', [HookController::class, 'test'])->name('hooks.test');
        Route::delete('hooks/{hook}', [HookController::class, 'destroy'])->name('hooks.destroy');
    });

    // ── Read-only-Resources Kernentitäten (MVP-718, Vollscan J11) ──────────
    // Lesen bleibt bewusst read-first: Anlage/Statuswechsel laufen über die
    // Web-Workflows (GoBD, Freigaben). Plan-/Modul-Gating wie im Web über
    // config('plans.routes') (api.articles.* → module.lager usw.).
    Route::get('articles', [ArticleApiController::class, 'index'])->middleware('ability:articles:read')->name('articles.index');
    Route::get('articles/{article}', [ArticleApiController::class, 'show'])->middleware('ability:articles:read')->name('articles.show');
    Route::get('articles/{article}/variants', [ArticleApiController::class, 'variants'])->middleware('ability:articles:read')->name('articles.variants');
    Route::get('inventory', [InventoryApiController::class, 'index'])->middleware('ability:inventory:read')->name('inventory.index');
    Route::get('inventory/warehouses', [InventoryApiController::class, 'warehouses'])->middleware('ability:inventory:read')->name('inventory.warehouses');
    Route::get('purchase-orders', [PurchaseOrderApiController::class, 'index'])->middleware('ability:purchase_orders:read')->name('purchase-orders.index');
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderApiController::class, 'show'])->middleware('ability:purchase_orders:read')->name('purchase-orders.show');
    Route::get('suppliers', [SupplierApiController::class, 'index'])->middleware('ability:suppliers:read')->name('suppliers.index');
    Route::get('suppliers/{supplier}', [SupplierApiController::class, 'show'])->middleware('ability:suppliers:read')->name('suppliers.show');
    Route::get('protocols', [ProtocolApiController::class, 'index'])->middleware('ability:protocols:read')->name('protocols.index');
    Route::get('protocols/{protocol}', [ProtocolApiController::class, 'show'])->middleware('ability:protocols:read')->name('protocols.show');
    Route::get('vehicles', [VehicleApiController::class, 'index'])->middleware('ability:vehicles:read')->name('vehicles.index');
    Route::get('vehicles/{vehicle}', [VehicleApiController::class, 'show'])->middleware('ability:vehicles:read')->name('vehicles.show');
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceApiController::class, 'pdf'])->middleware('ability:invoices:read')->name('invoices.pdf');
};

Route::middleware(['auth:sanctum', EnforcePlanModules::class])->prefix('v1')->name('api.')->group($sanctumRoutes);
Route::middleware(['auth:sanctum', EnforcePlanModules::class, DeprecatedApiAlias::class])->name('api.legacy.')->group($sanctumRoutes);
