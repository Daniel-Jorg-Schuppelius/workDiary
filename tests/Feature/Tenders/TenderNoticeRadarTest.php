<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeRadarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Tenders;

use App\Models\Tenders\{TenderFilterProfile, TenderNotice, TenderNoticeMatch};
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Tenders\{TenderNoticeImporter, TenderNoticeMatcher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeTenderNoticeHttpFactory;
use Tests\TestCase;
use ZipArchive;

/**
 * Bekanntmachungs-Radar (MVP-629/630): Abruf der offenen Bundesdaten und
 * Abgleich gegen die Suchprofile.
 */
final class TenderNoticeRadarTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Ersetzt den Guzzle-Transport. `Http::fake()` genügt nicht — der Client
     * fährt an Laravels HTTP-Fassade vorbei.
     *
     * @param list<\GuzzleHttp\Psr7\Response> $responses
     */
    private function fakeTransport(array $responses): void {
        $this->app->instance(PluginHttpFactory::class, new FakeTenderNoticeHttpFactory($responses));
    }

    /**
     * Baut ein Tagespaket, wie es die Quelle liefert: ein ZIP mit
     * OCDS-Releases.
     *
     * @param list<array<string, mixed>> $releases
     */
    private function zipWith(array $releases): string {
        $path = tempnam(sys_get_temp_dir(), 'notice-test-');
        $archive = new ZipArchive;
        $archive->open((string) $path, ZipArchive::OVERWRITE);
        $archive->addFromString('releases.json', (string) json_encode(['releases' => $releases]));
        $archive->close();

        $content = (string) file_get_contents((string) $path);
        unlink((string) $path);

        return $content;
    }

    /** @return array<string, mixed> */
    private function release(string $id, string $title, string $cpv = '45210000', string $nuts = 'DEA22', ?float $value = 250000.0): array {
        return [
            'ocid' => 'ocds-mnwr74-' . $id,
            'id' => $id,
            'date' => '2026-08-17T09:00:00+02:00',
            'tag' => ['tender'],
            'buyer' => ['name' => 'Stadt Bonn'],
            'tender' => [
                'title' => $title,
                'description' => 'Ausschreibung ' . $title,
                'procurementMethod' => 'open',
                'value' => $value === null ? null : ['amount' => $value, 'currency' => 'EUR'],
                'tenderPeriod' => ['endDate' => '2026-09-15T12:00:00+02:00'],
                'items' => [[
                    'classification' => ['scheme' => 'CPV', 'id' => $cpv],
                    'deliveryAddresses' => [['region' => $nuts]],
                ]],
            ],
        ];
    }

    /** Der Abruf legt jede Fassung einmal ab — auch bei wiederholtem Lauf. */
    public function test_import_is_repeatable(): void {
        // Zwei Antworten: Der Test ruft zweimal ab.
        $zip = $this->zipWith([
            $this->release('notice-1', 'Neubau Kita — Rohbau'),
            $this->release('notice-2', 'Sanierung Turnhalle'),
        ]);
        $this->fakeTransport([FakeTenderNoticeHttpFactory::zip($zip), FakeTenderNoticeHttpFactory::zip($zip)]);

        $first = app(TenderNoticeImporter::class)->importDay(now()->subDay());
        $second = app(TenderNoticeImporter::class)->importDay(now()->subDay());

        $this->assertSame(2, $first['stored']);
        // Beim zweiten Lauf ist nichts neu: dieselbe Notice-ID in derselben
        // Fassung ist dieselbe Bekanntmachung.
        $this->assertSame(0, $second['stored']);
        $this->assertSame(2, TenderNotice::query()->count());
    }

    /** Titel, Käufer, CPV, NUTS, Wert und Frist kommen an. */
    public function test_notice_fields_are_normalised(): void {
        $this->fakeTransport([FakeTenderNoticeHttpFactory::zip($this->zipWith([$this->release('notice-1', 'Neubau Kita')]))]);

        app(TenderNoticeImporter::class)->importDay(now()->subDay());
        $notice = TenderNotice::query()->firstOrFail();

        $this->assertSame('Neubau Kita', $notice->title);
        $this->assertSame('Stadt Bonn', $notice->buyer_name);
        $this->assertSame(['45210000'], $notice->cpv_codes);
        $this->assertSame('DEA22', $notice->nuts_code);
        $this->assertSame('250000.00', $notice->estimated_value);
        $this->assertSame('2026-09-15', $notice->submission_deadline?->toDateString());
        // Die Rohfassung bleibt erhalten.
        $this->assertIsArray($notice->payload);
    }

    /** Ein leeres Tagespaket ist ein gültiges Ergebnis, kein Fehler. */
    public function test_empty_day_is_not_an_error(): void {
        $this->fakeTransport([FakeTenderNoticeHttpFactory::zip($this->zipWith([]))]);

        $result = app(TenderNoticeImporter::class)->importDay(now()->subDay());

        $this->assertSame(0, $result['fetched']);
    }

    private function notice(string $id, string $title, string $cpv = '45210000', string $nuts = 'DEA22', ?float $value = 250000.0): TenderNotice {
        return TenderNotice::query()->create([
            'notice_id' => $id,
            'version' => '1',
            'title' => $title,
            'summary' => 'Ausschreibung ' . $title,
            'buyer_name' => 'Stadt Bonn',
            'cpv_codes' => [$cpv],
            'nuts_code' => $nuts,
            'estimated_value' => $value,
            'currency' => 'EUR',
            'published_on' => now()->subDay()->toDateString(),
        ]);
    }

    private function profile(array $attributes = []): TenderFilterProfile {
        return TenderFilterProfile::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'name' => 'Hochbau in NRW',
            'active' => true,
            'cpv_codes' => ['45'],
            'nuts_codes' => ['DEA'],
        ], $attributes));
    }

    /**
     * CPV und NUTS sind Hierarchien: Wer `45` sucht, meint alle Bauleistungen,
     * wer `DEA` sucht, ganz Nordrhein-Westfalen.
     */
    public function test_codes_match_by_prefix(): void {
        $notice = $this->notice('n1', 'Neubau Kita');
        $profile = $this->profile();

        $this->assertTrue(app(TenderNoticeMatcher::class)->matches($notice, $profile));

        // Andere Region: kein Treffer.
        $elsewhere = $this->notice('n2', 'Neubau Schule', '45210000', 'DE300');
        $this->assertFalse(app(TenderNoticeMatcher::class)->matches($elsewhere, $profile));
    }

    /** Alle gesetzten Kriterien müssen zutreffen — „Bau in Bonn" meint beides. */
    public function test_all_criteria_must_hold(): void {
        $notice = $this->notice('n1', 'Lieferung Büromöbel', '39130000');

        $this->assertFalse(app(TenderNoticeMatcher::class)->matches($notice, $this->profile()));
    }

    /** Ein Ausschlusswort verwirft, auch wenn alles andere passt. */
    public function test_excluded_keyword_wins(): void {
        $notice = $this->notice('n1', 'Abbruch Sporthalle');
        $profile = $this->profile(['excluded_keywords' => ['abbruch']]);

        $this->assertFalse(app(TenderNoticeMatcher::class)->matches($notice, $profile));
    }

    /**
     * Ohne Wertangabe darf keine Wertgrenze ausschließen — sonst verschwinden
     * Ausschreibungen, die ihren Wert nicht nennen.
     */
    public function test_missing_value_is_not_filtered_out(): void {
        $notice = $this->notice('n1', 'Neubau Kita', '45210000', 'DEA22', null);
        $profile = $this->profile(['min_value' => '100000']);

        $this->assertTrue(app(TenderNoticeMatcher::class)->matches($notice, $profile));
    }

    /** Der Abgleich legt je Organisation einen Inbox-Eintrag an. */
    public function test_match_creates_one_inbox_entry_per_organisation(): void {
        $notice = $this->notice('n1', 'Neubau Kita');
        $this->profile();
        $this->profile(['name' => 'Zweites Profil', 'cpv_codes' => ['452']]);

        $created = app(TenderNoticeMatcher::class)->match([$notice]);

        $this->assertSame(1, $created);
        $this->assertSame(1, TenderNoticeMatch::query()->count());
        $this->assertSame(TenderNoticeMatch::STATE_NEW, TenderNoticeMatch::query()->firstOrFail()->state);
    }

    /** Der Befehl holt den Vortag und meldet, was neu ist. */
    public function test_command_fetches_and_matches(): void {
        $this->fakeTransport([FakeTenderNoticeHttpFactory::zip($this->zipWith([$this->release('notice-1', 'Neubau Kita')]))]);
        $this->profile();

        $this->artisan('tenders:fetch-notices', ['--day' => now()->subDay()->toDateString()])
            ->assertSuccessful();

        $this->assertSame(1, TenderNotice::query()->count());
        $this->assertSame(1, TenderNoticeMatch::query()->count());
    }
}
