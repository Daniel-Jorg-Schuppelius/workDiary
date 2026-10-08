<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Models\Platform\{HelpTopic, User};
use App\Services\Help\HelpSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suchdienst der Hilfe (MVP-1079): Wortsuche über Titel, Suchbegriffe,
 * Überschriften und Text, Gewichtung, Schreibvarianten, Tippfehler und
 * Teiltreffer — Sichtbarkeit bleibt beim Resolver.
 */
class HelpSearchTest extends TestCase {
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function makeTopic(string $topic, array $overrides = []): HelpTopic {
        return HelpTopic::query()->create(array_merge([
            'topic' => $topic,
            'locale' => 'de',
            'title' => 'Titel ' . $topic,
            'keywords' => [],
            'audience' => [],
            'modules' => [],
            'version' => 1,
            'body_md' => 'Inhalt von ' . $topic,
            'body_html' => '<p>Inhalt von ' . $topic . '</p>',
            'related' => [],
            'headings' => [],
        ], $overrides));
    }

    /** @return list<string> */
    private function topicsFor(string $query, ?User $user = null, string $locale = 'de'): array {
        $search = app(HelpSearch::class);

        return $search->search($search->prepare($query, $locale), $user)->topics->pluck('topic')->all();
    }

    public function test_keyword_finds_topic_and_is_reported(): void {
        $this->makeTopic('navigation.interface', ['title' => 'Bedienoberfläche und Darstellung', 'keywords' => ['Darkmode', 'Nachtmodus']]);
        $this->makeTopic('customers.overview', ['title' => 'Kundenstamm']);

        $search = app(HelpSearch::class);
        $result = $search->search($search->prepare('Darkmode', 'de'), null);

        $this->assertSame(['navigation.interface'], $result->topics->pluck('topic')->all());
        $this->assertSame('Darkmode', $result->topics->first()->getAttribute('search_keyword'));
        // Kein Langtext in den Treffern.
        $this->assertArrayNotHasKey('search_text', $result->topics->first()->getAttributes());
    }

    public function test_spelling_variants_and_compounds_match(): void {
        $this->makeTopic('navigation.interface', ['keywords' => ['Darkmode', 'Dunkelmodus']]);

        $this->assertSame(['navigation.interface'], $this->topicsFor('Dark Mode'));
        $this->assertSame(['navigation.interface'], $this->topicsFor('dark-mode'));
        $this->assertSame(['navigation.interface'], $this->topicsFor('DARKMODE'));
        // Wortteil ab vier Zeichen: „modus" steckt in „Dunkelmodus".
        $this->assertSame(['navigation.interface'], $this->topicsFor('modus'));
    }

    public function test_words_need_not_be_adjacent(): void {
        $this->makeTopic('invoices.cancel', ['title' => 'Rechnungen korrigieren', 'body_md' => 'Eine ausgestellte Rechnung lässt sich stornieren.', 'body_html' => '<p>Eine ausgestellte Rechnung lässt sich stornieren.</p>']);
        $this->makeTopic('invoices.create', ['title' => 'Rechnung schreiben']);

        // Volltreffer zuerst, der starke Teiltreffer „Rechnung" danach.
        $this->assertSame(['invoices.cancel', 'invoices.create'], $this->topicsFor('Rechnung stornieren'));
    }

    public function test_exact_keyword_ranks_first(): void {
        $this->makeTopic('a.reset', ['title' => 'Anmeldung zurücksetzen', 'keywords' => ['2FA zurücksetzen']]);
        $this->makeTopic('b.two-factor', ['title' => 'Zwei-Faktor', 'keywords' => ['2FA']]);

        $this->assertSame(['b.two-factor', 'a.reset'], $this->topicsFor('2FA'));
    }

    public function test_accents_and_umlauts_are_folded(): void {
        $this->makeTopic('teams.overview', ['locale' => 'fr', 'title' => 'Gérer l’équipe']);
        $this->makeTopic('mail.boxes', ['title' => 'Postfächer verwalten']);

        $this->assertSame(['teams.overview'], $this->topicsFor('equipe', null, 'fr'));
        $this->assertSame(['mail.boxes'], $this->topicsFor('postfaecher'));
        $this->assertSame(['mail.boxes'], $this->topicsFor('Postfächer'));
    }

    public function test_title_hits_rank_before_body_hits(): void {
        $this->makeTopic('a.body', ['title' => 'Allgemeines', 'body_md' => 'Hier steht auch Stempeluhr.', 'body_html' => '<p>Hier steht auch Stempeluhr.</p>']);
        $this->makeTopic('b.title', ['title' => 'Stempeluhr bedienen']);
        $this->makeTopic('c.keyword', ['title' => 'Anwesenheit', 'keywords' => ['Stempeluhr']]);

        $this->assertSame(['b.title', 'c.keyword', 'a.body'], $this->topicsFor('Stempeluhr'));
    }

    public function test_typo_is_corrected_from_titles_and_keywords(): void {
        $this->makeTopic('invoices.create', ['title' => 'Rechnung schreiben']);

        $search = app(HelpSearch::class);
        $query = $search->prepare('Rechnugn', 'de');
        $result = $search->search($query, null);

        $this->assertSame(['invoices.create'], $result->topics->pluck('topic')->all());
        $this->assertSame('rechnung', $query->correctedText());
    }

    public function test_partial_hits_when_no_topic_has_all_words(): void {
        $this->makeTopic('invoices.create', ['title' => 'Rechnung schreiben']);
        $this->makeTopic('quotes.create', ['title' => 'Angebot schreiben']);

        $search = app(HelpSearch::class);
        $result = $search->search($search->prepare('Rechnung Angebot', 'de'), null);

        $this->assertTrue($result->partial);
        $this->assertEqualsCanonicalizing(['invoices.create', 'quotes.create'], $result->topics->pluck('topic')->all());
    }

    public function test_unknown_words_find_nothing(): void {
        $this->makeTopic('invoices.create', ['title' => 'Rechnung schreiben']);

        $this->assertSame([], $this->topicsFor('xyzzy-nichts'));
        $this->assertSame([], $this->topicsFor('   '));
    }

    public function test_frequent_words_are_not_required(): void {
        for ($i = 1; $i <= 6; $i++) {
            $this->makeTopic('filler.t' . $i, ['body_md' => 'Wie Sie das machen', 'body_html' => '<p>Wie Sie das machen</p>']);
        }
        $this->makeTopic('navigation.interface', ['title' => 'Darstellung', 'keywords' => ['Darkmode'], 'body_md' => 'Text', 'body_html' => '<p>Text</p>']);

        // „wie" steht in sechs von sieben Themen und ist damit kein Muss.
        $this->assertSame('navigation.interface', $this->topicsFor('wie darkmode')[0] ?? null);
    }

    public function test_hidden_topics_stay_out(): void {
        $this->makeTopic('time-entries.start', ['title' => 'Stempeluhr bedienen']);
        $this->makeTopic('admin.backups', ['title' => 'Stempeluhr geheim', 'audience' => ['admin']]);

        $user = User::factory()->user()->create();

        $this->assertSame(['time-entries.start'], $this->topicsFor('Stempeluhr', $user));
    }

    public function test_search_text_follows_content_on_save(): void {
        $topic = $this->makeTopic('navigation.interface', ['title' => 'Darstellung']);
        $this->assertSame([], $this->topicsFor('Nachtmodus'));

        $topic->update(['keywords' => ['Nachtmodus']]);

        $this->assertSame(['navigation.interface'], $this->topicsFor('Nachtmodus'));
    }

    public function test_snippet_highlights_first_body_hit(): void {
        $this->makeTopic('sample.snippet', [
            'title' => 'Beispiel',
            'body_md' => 'Vorspann. Die Rechnung lässt sich stornieren, solange sie offen ist.',
            'body_html' => '<p>Vorspann. Die Rechnung lässt sich stornieren, solange sie offen ist.</p>',
        ]);

        $search = app(HelpSearch::class);
        $page = $search->paginate($search->search($search->prepare('Rechnung stornieren', 'de'), null), 20, 1);

        $snippet = $page->items()[0]->getAttribute('search_snippet');
        $this->assertSame('stornieren', $snippet[1]);
        $this->assertStringContainsString('Rechnung lässt sich', $snippet[0]);
    }
}
