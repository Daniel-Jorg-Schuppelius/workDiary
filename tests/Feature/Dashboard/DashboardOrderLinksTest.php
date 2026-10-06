<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardOrderLinksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Dashboard;

use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Kacheln „Neue Kommentare“, „Team-Aktivität“ und „Neue Anhänge“ verlinkten
 * den Auftrag mit der rohen ID; die Route bindet über die Sqid und antwortete
 * mit 404 (Konsolidierungs-Audit 2026-10, k4-02).
 */
final class DashboardOrderLinksTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_widgets_link_orders_by_sqid(): void {
        $this->setUpOrganization();
        $user = $this->orgAdmin();
        $order = DiaryEntry::factory()->for($user)->create(['organization_id' => $this->organization->id]);
        $order->comments()->create(['user_id' => $user->id, 'body' => 'Rückfrage zum Termin']);
        Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $order->getMorphClass(),
            'attachable_id' => $order->id,
            'disk' => 'local',
            'path' => 'attachments/plan.pdf',
            'original_name' => 'plan.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by_user_id' => $user->id,
        ]);
        $this->actingAs($user);
        $dashboard = app(DashboardService::class);
        $url = route('diary.show', $order);

        $views = [
            view('dashboard.widgets.recent-comments', ['comments' => $dashboard->recentComments($user)])->render(),
            view('dashboard.widgets.team-activity', ['comments' => $dashboard->teamActivity()])->render(),
            view('dashboard.widgets.recent-attachments', ['attachments' => $dashboard->recentAttachments($user)])->render(),
        ];

        foreach ($views as $html) {
            $this->assertStringContainsString($url . '#', $html);
            $this->assertStringNotContainsString('/diary/' . $order->id . '#', $html);
        }
        $this->get($url)->assertOk();
    }
}
