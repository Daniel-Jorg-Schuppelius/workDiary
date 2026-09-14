<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchTextNormalizerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\Search\SearchTextNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Feature 153: Index und Anfrage zerlegen Text identisch — sonst finden sie
 * sich nicht. Die `w`-Kodierung hält Zwei-Buchstaben- und Stoppwörter im
 * MariaDB-Volltextindex.
 */
final class SearchTextNormalizerTest extends TestCase {
    private SearchTextNormalizer $normalizer;

    protected function setUp(): void {
        parent::setUp();
        $this->normalizer = new SearchTextNormalizer;
    }

    public function test_umlauts_and_case_are_folded(): void {
        $this->assertSame(['postfaecher', 'strasse', 'oresund'], $this->normalizer->tokens('Postfächer STRASSE Øresund'));
        $this->assertSame(['postfaecher'], $this->normalizer->tokens('Postfaecher'));
    }

    public function test_compound_words_yield_parts_and_joined_form(): void {
        $this->assertSame(['smtp', 'relay', 'smtprelay'], $this->normalizer->tokens('SMTP-Relay'));
        $this->assertSame(['exchange', '2019', 'exchange2019'], $this->normalizer->tokens('Exchange2019'));
        $this->assertSame(['srv', 'ex', '01', 'srvex01'], $this->normalizer->tokens('SRV-EX01'));
    }

    public function test_single_characters_are_not_indexed_but_stay_in_the_joined_form(): void {
        $this->assertSame(['365', 'm365'], $this->normalizer->tokens('M365'));
        $this->assertSame([], $this->normalizer->tokens('a - b'));
    }

    public function test_encoding_frames_prefixed_words_and_decodes_back(): void {
        $encoded = $this->normalizer->encode(['ad', 'it', 'smtp']);

        $this->assertSame(' wad wit wsmtp ', $encoded);
        $this->assertSame(['ad', 'it', 'smtp'], $this->normalizer->decode($encoded));
    }

    public function test_encoding_is_cut_at_a_word_boundary(): void {
        // Die Obergrenze gilt inklusive Rahmen-Leerzeichen; kein Wort wird zerteilt.
        $this->assertSame(' walpha wbeta ', $this->normalizer->encode(['alpha', 'beta', 'gamma'], 14));
        $this->assertSame(' walpha ', $this->normalizer->encode(['alpha', 'beta', 'gamma'], 13));
    }

    public function test_key_is_the_space_separated_word_sequence(): void {
        $this->assertSame('smtp relay', $this->normalizer->key('SMTP-Relay'));
        $this->assertSame('send connector', $this->normalizer->key('  Send   Connector '));
        $this->assertSame('', $this->normalizer->key('—'));
    }
}
