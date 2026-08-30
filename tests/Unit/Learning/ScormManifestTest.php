<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormManifestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Learning;

use App\Services\Learning\Scorm\ScormManifest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Manifest-Parser für SCORM-Pakete (Feature 149, MVP-743).
 *
 * Reine Parser-Logik ohne Framework — deshalb ein Unit-Test. Die Fixtures
 * bilden echte Paketformen ab: SCORM 1.2 mit `adlcp:scormtype`, SCORM 2004
 * mit `adlcp:scormType` und anderem Namespace.
 */
class ScormManifestTest extends TestCase {
    private function scorm12(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST-1" version="1.0"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="ORG-1">
    <organization identifier="ORG-1">
      <title>Brandschutzunterweisung</title>
      <item identifier="ITEM-1" identifierref="RES-1">
        <title>Grundlagen</title>
      </item>
      <item identifier="ITEM-2" identifierref="RES-2">
        <title>Merkblatt</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES-1" type="webcontent" adlcp:scormtype="sco" href="start.html">
      <file href="start.html"/>
    </resource>
    <resource identifier="RES-2" type="webcontent" adlcp:scormtype="asset" href="merkblatt.pdf">
      <file href="merkblatt.pdf"/>
    </resource>
  </resources>
</manifest>
XML;
    }

    private function scorm2004(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST-2" version="1.0"
          xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>2004 4th Edition</schemaversion>
  </metadata>
  <organizations default="ORG-A">
    <organization identifier="ORG-A">
      <title>Datenschutz</title>
      <item identifier="I1" identifierref="R1">
        <title>Modul 1</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="R1" type="webcontent" adlcp:scormType="sco" href="index.html"/>
  </resources>
</manifest>
XML;
    }

    public function test_erkennt_scorm_12_mit_titel_und_startdatei(): void {
        $manifest = ScormManifest::fromXml($this->scorm12());

        $this->assertSame(ScormManifest::VERSION_12, $manifest->version);
        $this->assertSame('Brandschutzunterweisung', $manifest->title);
        $this->assertSame('start.html', $manifest->launchHref);
        $this->assertSame('API', $manifest->apiObjectName());
        $this->assertSame('cmi.core.lesson_status', $manifest->completionKey());
    }

    public function test_unterscheidet_sco_von_asset(): void {
        $manifest = ScormManifest::fromXml($this->scorm12());

        $this->assertCount(2, $manifest->items);
        $this->assertTrue($manifest->items[0]['is_sco'], 'Nur ein SCO meldet Fortschritt zurück.');
        $this->assertFalse($manifest->items[1]['is_sco'], 'Ein Asset ist Beiwerk, kein Lernobjekt.');
    }

    public function test_erkennt_scorm_2004_trotz_anderem_namespace_und_schreibweise(): void {
        $manifest = ScormManifest::fromXml($this->scorm2004());

        $this->assertSame(ScormManifest::VERSION_2004, $manifest->version);
        $this->assertSame('Datenschutz', $manifest->title);
        $this->assertSame('index.html', $manifest->launchHref);
        $this->assertSame('API_1484_11', $manifest->apiObjectName());
        $this->assertSame('cmi.completion_status', $manifest->completionKey());
        $this->assertTrue($manifest->items[0]['is_sco'], 'adlcp:scormType mit großem T muss ebenso greifen.');
    }

    public function test_kommt_ohne_organisation_aus(): void {
        $xml = <<<'XML'
<?xml version="1.0"?>
<manifest xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
  <metadata><schema>ADL SCORM</schema><schemaversion>2004 3rd Edition</schemaversion></metadata>
  <organizations/>
  <resources>
    <resource identifier="R" type="webcontent" adlcp:scormType="sco" href="run.html"/>
  </resources>
</manifest>
XML;

        $manifest = ScormManifest::fromXml($xml);

        // Manche Pakete führen nur Ressourcen — dann gilt die erste startbare.
        $this->assertSame('run.html', $manifest->launchHref);
    }

    public function test_waehlt_die_als_default_markierte_organisation(): void {
        $xml = <<<'XML'
<?xml version="1.0"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ZWEITE">
    <organization identifier="ERSTE">
      <title>Nicht diese</title>
      <item identifier="A" identifierref="RA"><title>A</title></item>
    </organization>
    <organization identifier="ZWEITE">
      <title>Diese</title>
      <item identifier="B" identifierref="RB"><title>B</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RA" adlcp:scormtype="sco" href="a.html"/>
    <resource identifier="RB" adlcp:scormtype="sco" href="b.html"/>
  </resources>
</manifest>
XML;

        $manifest = ScormManifest::fromXml($xml);

        $this->assertSame('Diese', $manifest->title);
        $this->assertSame('b.html', $manifest->launchHref);
    }

    public function test_ungueltiges_xml_wird_abgewiesen(): void {
        $this->expectException(RuntimeException::class);
        ScormManifest::fromXml('<manifest><nicht geschlossen>');
    }

    public function test_xml_base_verschiebt_den_einstiegspfad(): void {
        // Ein häufiges Autorenwerkzeug-Muster: die Dateien liegen unter
        // scormcontent/, das Manifest nennt nur `index.html`.
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="M" version="1"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
          xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ORG"><organization identifier="ORG">
    <title>Kurs</title>
    <item identifier="I" identifierref="R"><title>Teil</title></item>
  </organization></organizations>
  <resources xml:base="scormcontent/">
    <resource identifier="R" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;

        $manifest = ScormManifest::fromXml($xml);

        $this->assertSame('scormcontent/index.html', $manifest->launchHref);
    }
}
