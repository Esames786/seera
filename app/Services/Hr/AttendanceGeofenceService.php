<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

/**
 * Server-side geofence evaluation for location-validated attendance.
 *
 * The browser only reports a position (latitude, longitude, accuracy). The
 * server resolves the site's stored coordinates and radius, computes the
 * great-circle distance (Haversine) and applies the site's own policy. No
 * client-calculated distance or status is ever trusted, and no hidden
 * tolerance is added to the configured radius.
 */
final class AttendanceGeofenceService
{
    /** Mean Earth radius in metres (IUGG). */
    public const EARTH_RADIUS_METERS = 6371008.8;

    /** A reported accuracy worse than this is treated as unusable rather than "imperfect". */
    public const MAX_USABLE_ACCURACY_METERS = 2000.0;

    /**
     * @return array{latitude: float, longitude: float, accuracy: float|null}
     *
     * @throws ValidationException when a coordinate is missing or impossible
     */
    public function validatedPosition(mixed $latitude, mixed $longitude, mixed $accuracy): array
    {
        $errors = [];
        if (! is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90) {
            $errors['latitude'] = __('mobile_attendance.invalid_latitude');
        }
        if (! is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
            $errors['longitude'] = __('mobile_attendance.invalid_longitude');
        }
        if ($accuracy !== null && $accuracy !== '' && (! is_numeric($accuracy) || (float) $accuracy < 0)) {
            $errors['accuracy'] = __('mobile_attendance.invalid_accuracy');
        }
        if ($errors === [] && is_numeric($accuracy) && (float) $accuracy > self::MAX_USABLE_ACCURACY_METERS) {
            $errors['accuracy'] = __('mobile_attendance.accuracy_unusable', ['limit' => (int) self::MAX_USABLE_ACCURACY_METERS]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'latitude' => round((float) $latitude, 7),
            'longitude' => round((float) $longitude, 7),
            'accuracy' => is_numeric($accuracy) ? round((float) $accuracy, 2) : null,
        ];
    }

    /** Great-circle distance in metres between two WGS-84 positions. */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return round(2 * self::EARTH_RADIUS_METERS * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * Evaluate a reported position against the site's configured policy.
     *
     * @return array{status: string, distance: float|null, radius: int, enforced: bool, inside_only: bool, blocked: bool, site_located: bool, reason: string|null}
     */
    public function evaluate(Site $site, float $latitude, float $longitude): array
    {
        $radius = (int) $site->geofence_radius;
        $enforced = (bool) $site->geofence_enabled;
        $insideOnly = (bool) $site->attendance_inside_only;
        $siteLocated = $site->latitude !== null && $site->longitude !== null;

        $distance = $siteLocated
            ? $this->distanceMeters($latitude, $longitude, (float) $site->latitude, (float) $site->longitude)
            : null;

        if (! $enforced) {
            // Location is recorded for evidence, the geofence policy is not applied.
            return $this->result(AttendanceRecord::GEOFENCE_NOT_ENFORCED, $distance, $radius, false, $insideOnly, false, $siteLocated, null);
        }

        if (! $siteLocated) {
            // The site has no coordinates: the distance cannot be judged. Inside-only sites block
            // rather than silently accept; other sites record the position with this status.
            return $this->result(AttendanceRecord::GEOFENCE_LOCATION_UNAVAILABLE, null, $radius, true, $insideOnly, $insideOnly, false,
                $insideOnly ? __('mobile_attendance.site_not_located') : null);
        }

        $inside = $distance <= $radius;
        $status = $inside ? AttendanceRecord::GEOFENCE_INSIDE : AttendanceRecord::GEOFENCE_OUTSIDE;
        $blocked = ! $inside && $insideOnly;

        return $this->result($status, $distance, $radius, true, $insideOnly, $blocked, true,
            $blocked ? __('mobile_attendance.outside_blocked', ['distance' => number_format($distance), 'radius' => $radius]) : null);
    }

    private function result(string $status, ?float $distance, int $radius, bool $enforced, bool $insideOnly, bool $blocked, bool $siteLocated, ?string $reason): array
    {
        return compact('status', 'distance', 'radius', 'enforced', 'blocked', 'reason') + ['inside_only' => $insideOnly, 'site_located' => $siteLocated];
    }
}
