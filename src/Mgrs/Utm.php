<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Universal Transverse Mercator (UTM)
 * Provides conversion to and from Lat/Long, using the WGS84 ellipsoid.
 */

class Utm
{
    /**
     * The parts of a UTM coordinate.
     */

    public $northing;
    public $easting;
    public $zone_number;
    public $zone_letter;

    // Equatorial radius, GRS80 ellipsoid (meters).
    protected static $a = 6378137.0;

    // Scale factor of central meridian.
    protected static $k0 = 0.9996;

    // Eccentricity squared.
    protected static $ecc_squared = 0.006694380023;

    /**
     * The default number of digits to use in output formatting.
     * The value is the number of digits used, ranging from 0 to 5.
     * 5 is an accuracy of 1m, 4 is 10m, 3 is 100m and so on.
     */

    protected $accuracy = 5;

    /**
     * Constructor.
     * TODO: also accept an array, maybe even a lat/long object.
     */
    public function __construct($northing, $easting, $zone_number, $zone_letter)
    {
        $this->northing = $northing;
        $this->easting = $easting;
        $this->zone_number = $zone_number;
        $this->zone_letter = $zone_letter;
    }

    /**
     * Convert from Lat/Long.
     * Returns a Utm object, either a new object or the current object, depending
     * on whether called staticly or not.
     */
    public static function fromLatLong($latitude, $longitude = null)
    {
        // Accept various inputs.

        if ( ! isset($longitude)) {
            // One parameter only supplied
            // TODO: this could be an array with numeric or names keys, or an object.
            // ...
        } else {
            // Coordinates supplied as separate values.
            $lat = $latitude;
            $long = $longitude;
        }

        // Convert to radians. (too early - do this later)
        $lat_rad = deg2rad($lat);
        $long_rad = deg2rad($long);

        // Calculate the zone number.
        $zone_number = static::getZoneNumber($lat, $long);

        // +3 puts origin in middle of zone
        $long_origin = ($zone_number - 1) * 6 - 180 + 3;
        $long_origin_rad = deg2rad($long_origin);

        $ecc_prime_squared = (static::$ecc_squared) / (1 - static::$ecc_squared);

        $N = static::$a / sqrt(1 - static::$ecc_squared * pow(sin($lat_rad), 2));
        $T = pow(tan($lat_rad), 2);
        $C = $ecc_prime_squared * pow(cos($lat_rad), 2);
        $A = cos($lat_rad) * ($long_rad - $long_origin_rad);

        $M = static::$a * (
            (1 - static::$ecc_squared / 4 - 3 * pow(static::$ecc_squared, 2) / 64 - 5 * pow(static::$ecc_squared, 3) / 256) * $lat_rad
            - (3 * static::$ecc_squared / 8 + 3 * pow(static::$ecc_squared, 2) / 32 + 45 * pow(static::$ecc_squared, 3) / 1024) * sin(2 * $lat_rad)
            + (15 * pow(static::$ecc_squared, 2) / 256 + 45 * pow(static::$ecc_squared, 3) / 1024) * sin(4 * $lat_rad)
            - (35 * pow(static::$ecc_squared, 3) / 3072) * sin(6 * $lat_rad)
        );

        $utm_easting = (static::$k0 * $N * ($A + (1 - $T + $C) * pow($A, 3) / 6.0 + (5 - 18 * pow($T, 3) + 72 * $C - 58 * $ecc_prime_squared) * pow($A, 5) / 120.0) + 500000.0);

        $utm_northing = (static::$k0 * ($M + $N * tan($lat_rad) * ($A * $A / 2 + (5 - $T + 9 * $C + 4 * pow($C, 2)) * pow($A, 4) / 24.0 + (61 - 58 * pow($T, 3) + 600 * $C - 330 * $ecc_prime_squared) * pow($A, 6) / 720.0)));

        if ($lat < 0.0) {
            // 10,000,000 meter offset for southern hemisphere
            $utm_northing += 10000000.0;
        }

        $northing = round($utm_northing);
        $easting = round($utm_easting);
        $zone_number = $zone_number;
        $zone_letter = static::getLetterDesignator($lat);

        if ( isset($this) && get_class($this) == __CLASS__) {
            // Not static - set the current object values.

            // Maybe controversial - should this be trunacted and not rounded?
            $this->northing = $northing;
            $this->easting = $easting;
            $this->zone_number = $zone_number;
            $this->zone_letter = $zone_letter;

            return $this;
        } else {
            // Called statically. Instantiate a new object.

            return new static(
                $northing,
                $easting,
                $zone_number,
                $zone_letter
            );
        }
    }

    /**
     * Get the zone number for a lat/long
     */
    public static function getZoneNumber($lat, $long)
    {
        // Convert 0 to 360 to -180 to +180
        // Might just replace this with an if-statement, as that would be clearer.
        $long = ($long + 180) - floor(($long + 180) / 360) * 360 - 180;

        // The basic zone number, before exceptions.
        $zone_number = floor(($long + 180) / 6) + 1;

        // Make sure the longitude 180.00 is in Zone 60
        if ($long === 180) {
            $zone_number = 60;
        }

        // Special zone for Norway.
        if ($lat >= 56.0 && $lat < 64.0 && $long >= 3.0 && $long < 12.0) {
            $zone_number = 32;
        }

        // Special zones for Svalbard.
        if ($lat >= 72.0 && $lat < 84.0) {
            if ($long >= 0.0 && $long < 9.0) {
                $zone_number = 31;
            } else if ($long >= 9.0 && $long < 21.0) {
                $zone_number = 33;
            } else if ($long >= 21.0 && $long < 33.0) {
                $zone_number = 35;
            } else if ($long >= 33.0 && $long < 42.0) {
                $zone_number = 37;
            }
        }

        return $zone_number;
    }

    /**
     * Calculates the MGRS letter designator for the given latitude.
     *
     * @private
     * @param {number} lat The latitude in WGS84 to get the letter designator
     *     for.
     * @return {char} The letter designator.
     */
    protected static function getLetterDesignator($lat)
    {
        // I'm sure we can turn this into a simple formula, perhaps with a string lookup.
        if ((84 >= $lat) && ($lat >= 72)) {
            $letter_designator = 'X';
        } else if ((72 > $lat) && ($lat >= 64)) {
            $letter_designator = 'W';
        } else if ((64 > $lat) && ($lat >= 56)) {
            $letter_designator = 'V';
        } else if ((56 > $lat) && ($lat >= 48)) {
            $letter_designator = 'U';
        } else if ((48 > $lat) && ($lat >= 40)) {
            $letter_designator = 'T';
        } else if ((40 > $lat) && ($lat >= 32)) {
            $letter_designator = 'S';
        } else if ((32 > $lat) && ($lat >= 24)) {
            $letter_designator = 'R';
        } else if ((24 > $lat) && ($lat >= 16)) {
            $letter_designator = 'Q';
        } else if ((16 > $lat) && ($lat >= 8)) {
            $letter_designator = 'P';
        } else if ((8 > $lat) && ($lat >= 0)) {
            $letter_designator = 'N';
        } else if ((0 > $lat) && ($lat >= -8)) {
            $letter_designator = 'M';
        } else if ((-8 > $lat) && ($lat >= -16)) {
            $letter_designator = 'L';
        } else if ((-16 > $lat) && ($lat >= -24)) {
            $letter_designator = 'K';
        } else if ((-24 > $lat) && ($lat >= -32)) {
            $letter_designator = 'J';
        } else if ((-32 > $lat) && ($lat >= -40)) {
            $letter_designator = 'H';
        } else if ((-40 > $lat) && ($lat >= -48)) {
            $letter_designator = 'G';
        } else if ((-48 > $lat) && ($lat >= -56)) {
            $letter_designator = 'F';
        } else if ((-56 > $lat) && ($lat >= -64)) {
            $letter_designator = 'E';
        } else if ((-64 > $lat) && ($lat >= -72)) {
            $letter_designator = 'D';
        } else if ((-72 > $lat) && ($lat >= -80)) {
            $letter_designator = 'C';
        } else {
            // This is here as an error flag to show that the Latitude is
            // outside MGRS limits

            $letter_designator = 'Z';
        }

        return $letter_designator;
    }

    /**
     * Converts UTM coords to lat/long, using the WGS84 ellipsoid. This is a convenience
     * class where the Zone can be specified as a single string eg."60N" which
     * is then broken down into the ZoneNumber and ZoneLetter.
     *
     * @private
     * @param {object} utm An object literal with northing, easting, zone_number
     *     and zone_letter properties. If an optional accuracy property is
     *     provided (in meters), a bounding box will be returned instead of
     *     latitude and longitude.
     * @return {object} An object literal containing either lat and lon values
     *     (if no accuracy was provided), or top, right, bottom and left values
     *     for the bounding box calculated according to the provided accuracy.
     *     Returns null if the conversion failed.
     */
    public function toLatLong()
    {
        $utm_northing = $this->northing;
        $utm_easting = $this->easting;
        $zone_letter = $this->zone_letter;
        $zone_number = $this->zone_number;

        // Check the ZoneNummber is valid.
        // CHECKME: do we want to raise an exception?
        if ($zone_number < 0 || $zone_number > 60) {
            return null;
        }

        $e1 = (1 - sqrt(1 - static::$ecc_squared)) / (1 + sqrt(1 - static::$ecc_squared));

        // Remove 500,000 meter offset for longitude
        $x = $utm_easting - 500000.0;
        $y = $utm_northing;

        // We must know somehow if we are in the Northern or Southern
        // hemisphere, this is the only time we use the letter So even
        // if the Zone letter isn't exactly correct it should indicate
        // the hemisphere correctly.

        if ($zone_letter < 'N') {
            // remove 10,000,000 meter offset used for southern hemisphere
            $y -= 10000000.0;
        }

        // There are 60 zones with zone 1 being at West -180 to -174
        // +3 puts origin in middle of zone.
        $LongOrigin = ($zone_number - 1) * 6 - 180 + 3; 

        $eccPrimeSquared = (static::$ecc_squared) / (1 - static::$ecc_squared);

        $M = $y / static::$k0;
        $mu = $M / (static::$a * (1 - static::$ecc_squared / 4 - 3 * pow(static::$ecc_squared, 2) / 64 - 5 * pow(static::$ecc_squared, 3) / 256));

        $phi1Rad = $mu + (3 * $e1 / 2 - 27 * pow($e1, 3) / 32) * sin(2 * $mu) + (21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32) * sin(4 * $mu) + (151 * pow($e1, 3) / 96) * sin(6 * $mu);
        // double phi1 = ProjMath.radToDeg(phi1Rad);

        $N1 = static::$a / sqrt(1 - static::$ecc_squared * pow(sin($phi1Rad), 2));
        $T1 = pow(tan($phi1Rad), 2);
        $C1 = $eccPrimeSquared * pow(cos($phi1Rad), 2);
        $R1 = static::$a * (1 - static::$ecc_squared) / pow(1 - static::$ecc_squared * pow(sin($phi1Rad), 2), 1.5);
        $D = $x / ($N1 * static::$k0);

        $lat = $phi1Rad
            - ($N1 * tan($phi1Rad) / $R1) * (pow($D, 2) / 2 - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 * $C1 - 9 * $eccPrimeSquared) * pow($D, 4) / 24 + (61 + 90 * $T1 + 298 * $C1 + 45 * pow($T1, 2) - 252 * $eccPrimeSquared - 3 * pow($C1, 2)) * pow($D, 6) / 720);
        $lat = rad2deg($lat);

        $long = ($D - (1 + 2 * $T1 + $C1) * pow($D, 3) / 6 + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 * $C1 + 8 * $eccPrimeSquared + 24 * $T1 * $T1) * pow($D, 5) / 120) / cos($phi1Rad);
        $long = $LongOrigin + rad2deg($long);

        // Returning a LatLong object.

        $result = new LatLong();
        $result->setLatitude($lat);
        $result->setLongitude($long);

        return $result;
    }

    /**
     * The square bounded by the accuracy.
     */
    public function toSquare($accuracy = null)
    {
        // Get the lat/long coordinates - the bottom left corner of the square.
        $lat_long = $this->toLatLong();

        // TODO: we need a Square object that can manage the bounds.
        // Maybe it just has two LatLong classes injected?
        $result = new \stdClass();

        $top_right = new static(
            $this->northing + $this->getSize($accuracy),
            $this->easting + $this->getSize($accuracy),
            $this->zone_number,
            $this->zone_letter
        );

        $top_right_lat_long = $top_right->toLatLong();

        $result->top = $top_right_lat_long->getLatitude();
        $result->right = $top_right_lat_long->getLongitude();
        $result->bottom = $lat_long->getLatitude();
        $result->left = $lat_long->getLongitude();

        return $result;
    }

    /**
     * Get the size of the square in metres.
     */
    public function getSize($accuracy = null)
    {
        // Use the current accuracy, if not provided.
        if ( ! isset($accuracy)) {
            $accuracy = $this->accuracy;
        }

        // The size of the square is 1m for an accuracy of 5 (10^0)
        return pow(10, 5 - $accuracy);
    }
}

