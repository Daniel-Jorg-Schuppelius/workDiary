<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Services\Routing\GeocodingException;
use App\Services\Routing\NominatimGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal AJAX endpoint used by Blade forms (TravelLog/Customer) to
 * resolve a free-form address to lat/lng without leaking the geocoder
 * to anonymous callers.
 */
class GeocodeController extends Controller {
    public function __construct(private readonly NominatimGeocoder $geocoder) {
    }

    public function __invoke(Request $request): JsonResponse {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $result = $this->geocoder->geocode($data['query']);
        } catch (GeocodingException $e) {
            return response()->json([
                'error' => 'geocoder_unavailable',
                'message' => $e->getMessage(),
            ], 503);
        }

        if ($result === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json($result->toArray());
    }
}
