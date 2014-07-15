<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Universal Transverse Mercator (UTM)
 * Provides conversion to and from Lat/Long, using the WGS84 ellipsoid.
 * Uses the Transverse Mercator projection.
 * @todo Implement a fromGridReference() supporting UTM grid references. Note that
 * many formats are ambiguous, so reference strings will need modifiers to remove
 * that ambiguity.
 * @todo UTM is just one variation of the general Transverse Mercator projection.
 * Pull th underlying TM projection out to a base class, then other projections (with
 * their own grid reference formats and rules) can be applied.
 */

class Utm
{
    /**
     * The parts of a UTM coordinate.
     */

    protected $northing;
    protected $easting;
    protected $zone_number;
    protected $zone_letter;

    /**
     * Constants for converting between ellipsoids.
     */

    // Equatorial radius, GRS80 ellipsoid (meters).
    protected static $a = 6378137.0;

    // Scale factor of central meridian.
    protected static $k0 = 0.9996;

    // Eccentricity squared.
    protected static $ecc_squared = 0.006694380023;

    /**
     * The letter designator range from latitude -80 to 84
     * A, B, Y and Z are handled as an exception.
     * I and O are skipped to avoid ambiguity.
     */

    const LETTER_DESIGNATORS = 'CDEFGHJKLMNPQRSTUVWX';

    /**
     * Constructor.
     * @todo Validate values.
     */
    public function __construct($northing, $easting, $zone_number, $zone_letter)
    {
        $this->northing = $northing;
        $this->easting = $easting;
        $this->zone_number = $zone_number;
        $this->zone_letter = $zone_letter;
    }

    /**
     * Get the current value elements.
     */

    public function getNorthing()
    {
        return $this->northing;
    }

    public function getEasting()
    {
        return $this->easting;
    }

    public function getZoneNumber()
    {
        return $this->zone_number;
    }

    public function getZoneLetter()
    {
        return $this->zone_letter;
    }

    /**
     * Instantiate a Utm onject from Lat/Long coordinates or a LatLong object.
     * Returns a new Utm object.
     */
    public static function fromLatLong($latitude, $longitude = null)
    {
        // Accept various inputs.

        if ( ! isset($longitude)) {
            // One parameter only supplied. If this is not already a LatLong object,
            // then convert it into one.

            if ( ! is_a($latitude, 'Academe\\Proj4Php\\Mgrs\\LatLongInterface')) {
                // If some form of array, then let LatLong work out how to interpret it.
                $latitude = new LatLong($latitude);
            }

            $lat = $latitude->getLatitude();
            $long = $latitude->getLongitude();
        } else {
            // Coordinates supplied as separate values.
            $lat = $latitude;
            $long = $longitude;
        }

        // TODO: validate lat and long ranges, assuming they have been set, and throw exception if necessary.
        // lat: -180 to +180; long: -90 to +90
        /*
        if (...) {
            // Exception here.
            throw new \InvalidArgumentException(
                'error...'
            );
        );
        */

        // Convert to radians.
        $lat_rad = deg2rad($lat);
        $long_rad = deg2rad($long);

        // Calculate the zone number.
        $zone_number = static::calcZoneNumber($lat, $long);

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
        $zone_letter = static::calcLetterDesignator($lat);

        // Return a new object instatiation.

        return new static(
            $northing,
            $easting,
            $zone_number,
            $zone_letter
        );
    }

    /**
     * Calculate the zone number for a lat/long pair.
     * The zone number largely identifies the longitude, in 6 degree increments, but
     * has some exceptions at certain latitudes.
     */
    public static function calcZoneNumber($latitude, $longitude)
    {
        // Convert 0 to 360 to -180 to +180
        // Might just replace this with an if-statement, as that would be clearer.
        $longitude = ($longitude + 180) - floor(($longitude + 180) / 360) * 360 - 180;

        // The basic zone number, before exceptions.
        $zone_number = floor(($longitude + 180) / 6) + 1;

        // Make sure the longitude 180.00 is in Zone 60
        if ($longitude === 180) {
            $zone_number = 60;
        }

        // Special zone for Norway.
        if ($latitude >= 56.0 && $latitude < 64.0 && $longitude >= 3.0 && $longitude < 12.0) {
            $zone_number = 32;
        }

        // Special zones for Svalbard.
        if ($latitude >= 72.0 && $latitude < 84.0) {
            if ($longitude >= 0.0 && $longitude < 9.0) {
                $zone_number = 31;
            } else if ($longitude >= 9.0 && $longitude < 21.0) {
                $zone_number = 33;
            } else if ($longitude >= 21.0 && $longitude < 33.0) {
                $zone_number = 35;
            } else if ($longitude >= 33.0 && $longitude < 42.0) {
                $zone_number = 37;
            }
        }

        return $zone_number;
    }

    /**
     * Calculate the MGRS letter designator, sometimes know as the row letter, for
     * the given latitude.
     *
     * @private
     * @param number latitude The latitude in WGS84 to get the letter designator for.
     * @return char The letter designator.
     */
    protected static function calcLetterDesignator($latitude)
    {
        // I'm sure we can turn this into a simple formula, perhaps with a string lookup.
        // It basically splits the latitudes into 8 degree bands, and leaves out O and I in
        // the lettering sequence.
        // Note that A, B, Y and Z *do* exist, and cover an East or West half of each pole.
        // But strictly the poles are covered by the Universal Polar Stereograpic (UPS) coordinate
        // system.

        if ((84 >= $latitude) && ($latitude >= 72)) {
            $letter_designator = 'X';
        } else if ((72 > $latitude) && ($latitude >= 64)) {
            $letter_designator = 'W';
        } else if ((64 > $latitude) && ($latitude >= 56)) {
            $letter_designator = 'V';
        } else if ((56 > $latitude) && ($latitude >= 48)) {
            $letter_designator = 'U';
        } else if ((48 > $latitude) && ($latitude >= 40)) {
            $letter_designator = 'T';
        } else if ((40 > $latitude) && ($latitude >= 32)) {
            $letter_designator = 'S';
        } else if ((32 > $latitude) && ($latitude >= 24)) {
            $letter_designator = 'R';
        } else if ((24 > $latitude) && ($latitude >= 16)) {
            $letter_designator = 'Q';
        } else if ((16 > $latitude) && ($latitude >= 8)) {
            $letter_designator = 'P';
        } else if ((8 > $latitude) && ($latitude >= 0)) {
            $letter_designator = 'N';
        } else if ((0 > $latitude) && ($latitude >= -8)) {
            $letter_designator = 'M';
        } else if ((-8 > $latitude) && ($latitude >= -16)) {
            $letter_designator = 'L';
        } else if ((-16 > $latitude) && ($latitude >= -24)) {
            $letter_designator = 'K';
        } else if ((-24 > $latitude) && ($latitude >= -32)) {
            $letter_designator = 'J';
        } else if ((-32 > $latitude) && ($latitude >= -40)) {
            $letter_designator = 'H';
        } else if ((-40 > $latitude) && ($latitude >= -48)) {
            $letter_designator = 'G';
        } else if ((-48 > $latitude) && ($latitude >= -56)) {
            $letter_designator = 'F';
        } else if ((-56 > $latitude) && ($latitude >= -64)) {
            $letter_designator = 'E';
        } else if ((-64 > $latitude) && ($latitude >= -72)) {
            $letter_designator = 'D';
        } else if ((-72 > $latitude) && ($latitude >= -80)) {
            $letter_designator = 'C';
        } else {
            // This is here as an error flag to show that the Latitude is
            // outside MGRS limits.
            // We might just want to throw an exception here instead.

            $letter_designator = 'Z';
        }

        return $letter_designator;
    }

    /**
     * Get the hemisphere indicator - N or S.
     */
    protected static function calcHemisphereLetter($northing)
    {
        return (ord($northing) >= ord('N') ? 'N' : 'S');
    }

    /**
     * Converts UTM coords to lat/long, using the WGS84 ellipsoid. This is a convenience
     * class where the Zone can be specified as a single string eg."60N" which
     * is then broken down into the ZoneNumber and ZoneLetter.
     *
     * @private
     * @param {object} utm An object literal with northing, easting, zone_number
     *     and zone_letter properties.
     * @return {object} An object literal containing either lat and lon values
     *     Returns null if the conversion failed.
     */
    public function toLatLong()
    {
        $zone_letter = $this->getZoneLetter();
        $zone_number = $this->getZoneNumber();

        // Check the ZoneNummber is valid.
        // CHECKME: do we want to raise an exception?

        if ($zone_number < 0 || $zone_number > 60) {
            return null;
        }

        $e1 = (1 - sqrt(1 - static::$ecc_squared)) / (1 + sqrt(1 - static::$ecc_squared));

        // Remove 500,000 meter offset for longitude
        $x = $this->getEasting() - 500000.0;
        $y = $this->getNorthing();

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
        $long_origin = ($zone_number - 1) * 6 - 180 + 3; 

        $ecc_prime_squared = (static::$ecc_squared) / (1 - static::$ecc_squared);

        $M = $y / static::$k0;
        $mu = $M / (static::$a * (1 - static::$ecc_squared / 4 - 3 * pow(static::$ecc_squared, 2) / 64 - 5 * pow(static::$ecc_squared, 3) / 256));

        $phi1_rad = $mu + (3 * $e1 / 2 - 27 * pow($e1, 3) / 32) * sin(2 * $mu) + (21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32) * sin(4 * $mu) + (151 * pow($e1, 3) / 96) * sin(6 * $mu);

        $N1 = static::$a / sqrt(1 - static::$ecc_squared * pow(sin($phi1_rad), 2));
        $T1 = pow(tan($phi1_rad), 2);
        $C1 = $ecc_prime_squared * pow(cos($phi1_rad), 2);
        $R1 = static::$a * (1 - static::$ecc_squared) / pow(1 - static::$ecc_squared * pow(sin($phi1_rad), 2), 1.5);
        $D = $x / ($N1 * static::$k0);

        $lat = $phi1_rad
            - ($N1 * tan($phi1_rad) / $R1) * (pow($D, 2) / 2 - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 * $C1 - 9 * $ecc_prime_squared) * pow($D, 4) / 24 + (61 + 90 * $T1 + 298 * $C1 + 45 * pow($T1, 2) - 252 * $ecc_prime_squared - 3 * pow($C1, 2)) * pow($D, 6) / 720);
        $lat = rad2deg($lat);

        $long = ($D - (1 + 2 * $T1 + $C1) * pow($D, 3) / 6 + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 * $C1 + 8 * $ecc_prime_squared + 24 * $T1 * $T1) * pow($D, 5) / 120) / cos($phi1_rad);
        $long = $long_origin + rad2deg($long);

        // Returning a LatLong object.

        $lat_long = new LatLong($lat, $long);

        return $lat_long;
    }

    /**
     * Format the coordinate as a UTM string.
     * @param string format Optional template to format the reference.
     */
    public function toGridReference($template = null)
    {
        // Set the default format if not overridden.
        if ( ! isset($template)) {
            $template = '%z%l %e %n';
        }

        // Get the substitution values for the template.
        $fields = array(
            '%z' => $this->getZoneNumber(),
            '%l' => $this->getZoneLetter(),

            // Hemisphere is 'N' or 'S'.
            '%h' => $this->calcHemisphereLetter($this->getNorthing()),

            // Easting/northing.
            '%e' => $this->getEasting(),
            '%n' => $this->getNorthing(),

            // Easting/northing left-padded to 7 digits.
            '%E' => str_pad($this->getEasting(), 7, '0', \STR_PAD_LEFT),
            '%N' => str_pad($this->getNorthing(), 7, '0', \STR_PAD_LEFT),
        );

        return str_replace(array_keys($fields), array_values($fields), $template);
    }

    /**
     * Default cast to string.
     */
    public function __toString()
    {
        return $this->toGridReference();
    }
}

