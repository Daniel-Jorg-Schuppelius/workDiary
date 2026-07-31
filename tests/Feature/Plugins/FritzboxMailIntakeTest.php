<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxMailIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, EmailConnection, IntegrationInboxItem, PluginSetting, TimeEntry, User};
use App\Plugins\Fritzbox\FritzboxPlugin;
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Postfach-Abgriff der monatlichen FRITZ!Box-Telefonberichte: CSV-Anhänge
 * eines Telefonbericht-Postfachs laufen in den Anruflisten-Import; alles
 * andere fällt in die normale Mail-Inbox durch.
 */
final class FritzboxMailIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    private function enablePlugin(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => FritzboxPlugin::ID,
            'enabled' => true,
            'settings' => ['min_call_minutes' => '2'],
        ]);
    }

    private function connection(bool $callreportIntake = true): EmailConnection {
        return EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Berichte',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'berichte@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
            'callreport_intake' => $callreportIntake,
        ]);
    }

    private function reportCsv(): string {
        $utf8 = implode("\r\n", [
            'sep=;',
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '1;20.07.26 10:45;Andreas Fichter;01709024670;;ISDN Gerät;97911585;0:10',
            '2;20.07.26 14:37;;024339392801;Hückelhoven;;97911585;0:00',
        ]);

        return mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
    }

    private function message(string $messageId, array $attachments): ParsedMessage {
        return new ParsedMessage(
            messageId: $messageId,
            uid: 7,
            fromEmail: 'fritzbox@example.com',
            fromName: 'FRITZ!Box',
            subject: 'FRITZ!Box-Info: Anrufliste',
            body: 'Anbei die Anrufliste.',
            receivedAt: Carbon::parse('2026-08-01 03:00:00'),
            attachments: $attachments,
        );
    }

    private function intake(): MailIntakeService {
        return app(MailIntakeService::class);
    }

    public function test_call_report_attachment_is_imported(): void {
        $this->enablePlugin();
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0170 9024670',
        ]);

        // Push-Mails liefern CSV oft als octet-stream — Erkennung ist inhaltsbasiert.
        $result = $this->intake()->intake($this->organization, $this->connection(), $this->message('<r1@fb>', [
            new MailAttachment('FRITZ!Box_Anrufliste.csv', 'application/octet-stream', $this->reportCsv()),
        ]));

        $this->assertSame('callreport', $result);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
        // Kein generisches Mail-Inbox-Item für konsumierte Berichte.
        $this->assertSame(0, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
    }

    public function test_redelivered_report_is_skipped_without_duplicates(): void {
        $this->enablePlugin();
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0170 9024670',
        ]);

        $this->intake()->intake($this->organization, $this->connection(), $this->message('<r1@fb>', [
            new MailAttachment('report.csv', 'text/csv', $this->reportCsv()),
        ]));
        $again = $this->intake()->intake($this->organization, $this->connection(), $this->message('<r2@fb>', [
            new MailAttachment('report.csv', 'text/csv', $this->reportCsv()),
        ]));

        $this->assertSame('skipped', $again);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_flag_off_falls_through_to_generic_inbox(): void {
        $this->enablePlugin();

        $result = $this->intake()->intake($this->organization, $this->connection(callreportIntake: false), $this->message('<r3@fb>', [
            new MailAttachment('report.csv', 'text/csv', $this->reportCsv()),
        ]));

        $this->assertSame('created', $result);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_plugin_disabled_falls_through(): void {
        $result = $this->intake()->intake($this->organization, $this->connection(), $this->message('<r4@fb>', [
            new MailAttachment('report.csv', 'text/csv', $this->reportCsv()),
        ]));

        $this->assertSame('created', $result);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
    }

    public function test_non_report_csv_falls_through(): void {
        $this->enablePlugin();

        $result = $this->intake()->intake($this->organization, $this->connection(), $this->message('<r5@fb>', [
            new MailAttachment('zeiten.csv', 'text/csv', "Date,Client,Project,Duration\n2026-01-01,Acme,Web,1:00"),
        ]));

        $this->assertSame('created', $result);
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
    }
}
