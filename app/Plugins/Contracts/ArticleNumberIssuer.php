<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleNumberIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

/**
 * Warenwirtschaft, die den Artikelstamm führen kann (Feature 048, MVP-1583):
 * Vater-/Kind-SKU und GTIN kommen dann aus ihr. Nur solche Programme stehen
 * auf der Seite „Nummernkreise“ für den Kreis „Artikel“ zur Wahl; geschrieben
 * wird über den Abgleich des Plugins, nicht über das Artikelformular.
 */
interface ArticleNumberIssuer {}
