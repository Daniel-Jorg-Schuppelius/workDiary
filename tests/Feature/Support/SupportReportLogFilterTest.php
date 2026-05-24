<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportLogFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Services\Support\SupportReportLogFilter;
use Tests\TestCase;

class SupportReportLogFilterTest extends TestCase {
    public function test_filter_redacts_email_addresses(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('User contact: alice@example.com failed to login');

        $this->assertStringNotContainsString('alice@example.com', $out);
        $this->assertStringContainsString('<redacted:email>', $out);
    }

    public function test_filter_redacts_iban(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('Payment from DE89370400440532013000 received');

        $this->assertStringNotContainsString('DE89370400440532013000', $out);
        $this->assertStringContainsString('<redacted:iban>', $out);
    }

    public function test_filter_redacts_ipv4(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('Request from 192.168.42.17 denied');

        $this->assertStringNotContainsString('192.168.42.17', $out);
        $this->assertStringContainsString('<redacted:ipv4>', $out);
    }

    public function test_filter_redacts_ipv6(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('Origin 2001:db8:85a3:0:0:8a2e:370:7334 hit rate limit');

        $this->assertStringNotContainsString('2001:db8:85a3:0:0:8a2e:370:7334', $out);
        $this->assertStringContainsString('<redacted:ipv6>', $out);
    }

    public function test_filter_redacts_phone_numbers(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('Customer phone +49 30 1234567 logged');

        $this->assertStringNotContainsString('1234567', $out);
        $this->assertStringContainsString('<redacted:phone>', $out);
    }

    public function test_filter_redacts_jwt(): void {
        $filter = new SupportReportLogFilter();

        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $out = $filter->filter('Bearer ' . $jwt);

        $this->assertStringNotContainsString($jwt, $out);
        $this->assertStringContainsString('<redacted:jwt>', $out);
    }

    public function test_filter_surrogates_entity_ids_consistently(): void {
        $filter = new SupportReportLogFilter();

        $first = $filter->filter('Loaded user_42 from customer_99');
        $second = $filter->filter('Updated user_42 again');
        $third = $filter->filter('Loaded user_7');

        $this->assertStringContainsString('user_1', $first);
        $this->assertStringContainsString('customer_1', $first);
        // Identische ID → identisches Surrogat in späterer Zeile.
        $this->assertStringContainsString('user_1', $second);
        // Andere ID → neues Surrogat.
        $this->assertStringContainsString('user_2', $third);
    }

    public function test_filter_passes_through_neutral_text(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('[2026-05-24 09:00:00] local.INFO: Queue worker started');

        $this->assertStringContainsString('Queue worker started', $out);
        $this->assertStringNotContainsString('<redacted', $out);
    }

    public function test_filter_handles_multi_line_input(): void {
        $filter = new SupportReportLogFilter();

        $lines = [
            'alice@example.com from 10.0.0.5',
            'bob@example.com from 10.0.0.6',
        ];
        $out = $filter->filterMany($lines);

        $this->assertSame(2, count($out));
        foreach ($out as $line) {
            $this->assertStringNotContainsString('@example.com', $line);
            $this->assertStringNotContainsString('10.0.0.', $line);
        }
    }

    public function test_filter_does_not_redact_short_number_sequences_as_phone(): void {
        $filter = new SupportReportLogFilter();

        $out = $filter->filter('Took 42 ms to process 3 records');

        $this->assertSame('Took 42 ms to process 3 records', $out);
    }
}
