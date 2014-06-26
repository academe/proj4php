<?php

namespace Academe\Proj4Php;

/**
 * Ported from https://github.com/proj4js/mgrs/blob/master/mgrs.js
 */

class Mgrs
{
    /**
     * UTM zones are grouped, and assigned to one of a group of 6
     * sets.
     *
     * {int} @private
     */
    const NUM_100K_SETS = 6;

    /**
     * The column letters (for easting) of the lower left value, per
     * set.
     *
     * {string} @private
     */
    const SET_ORIGIN_COLUMN_LETTERS = 'AJSAJS';

    /**
     * The row letters (for northing) of the lower left value, per
     * set.
     *
     * {string} @private
     */
    const SET_ORIGIN_ROW_LETTERS = 'AFAFAF';

    // ASCII valuesm of letters.
    // There will be a much less clumsy way of doing this.

    const A = 65; // A
    const I = 73; // I
    const O = 79; // O
    const V = 86; // V
    const Z = 90; // Z

    /**
     * Conversion of lat/lon to MGRS.
     *
     * @param {object} ll Object literal with lat and lon properties on a
     *     WGS84 ellipsoid.
     * @param {int} accuracy Accuracy in digits (5 for 1 m, 4 for 10 m, 3 for
     *      100 m, 4 for 1000 m or 5 for 10000 m). Optional, default is 5.
     * @return {string} the MGRS string for the given location and accuracy.
     */
    public function forward($ll, $accuracy = null)
    {
        $accuracy = $accuracy || 5; // default accuracy 1m

        $p1 = new \stdClass();
        $p1->lat = $ll[1];
        $p1->lon = $ll[0];

        return $this->encode($this->LLtoUTM($p1), $accuracy);
    }

    /**
     * Conversion of MGRS to lat/lon.
     *
     * @param {string} mgrs MGRS string.
     * @return {array} An array with left (longitude), bottom (latitude), right
     *     (longitude) and top (latitude) values in WGS84, representing the
     *     bounding box for the provided MGRS reference.
     */
    public function inverse($mgrs)
    {
        $bbox = $this->UTMtoLL($this->decode(strtoupper($mgrs)));
        return array(
            $bbox->left,
            $bbox->bottom,
            $bbox->right,
            $bbox->top,
        );
    }

    public function toPoint($mgrsStr)
    {
        $llbbox = $this->inverse($mgrsStr);

        // Return the centre of the box.
        return array(
            ($llbbox[2] + $llbbox[0]) / 2,
            ($llbbox[3] + $llbbox[1]) / 2,
        );
    }


    // Everything below is possibly protected and not public.

    /**
     * Conversion from degrees to radians.
     *
     * @private
     * @param {number} deg the angle in degrees.
     * @return {number} the angle in radians.
     */
    public function degToRad($deg)
    {
        return deg2rad($deg);
        //return ($deg * (pi() / 180.0));
    }

    /**
     * Conversion from radians to degrees.
     *
     * @private
     * @param {number} rad the angle in radians.
     * @return {number} the angle in degrees.
     */
    public function radToDeg($rad)
    {
        return rad2deg($rad);
        //return (180.0 * ($rad / pi()));
    }

    /**
     * Converts a set of Longitude and Latitude co-ordinates to UTM
     * using the WGS84 ellipsoid.
     *
     * @private
     * @param {object} ll Object literal with lat and lon properties
     *     representing the WGS84 coordinate to be converted.
     * @return {object} Object literal containing the UTM value with easting,
     *     northing, zoneNumber and zoneLetter properties, and an optional
     *     accuracy property in digits. Returns null if the conversion failed.
     */
    public function LLtoUTM($ll)
    {
        $Lat = $ll->lat;
        $Long = $ll->lon;
        $a = 6378137.0; //ellip.radius;
        $eccSquared = 0.00669438; //ellip.eccsq;
        $k0 = 0.9996;
        //$LongOrigin; // ?
        //$eccPrimeSquared; // ?
        //$N, $T, $C, $A, $M; // ?
        $LatRad = $this->degToRad($Lat);
        $LongRad = $this->degToRad($Long);
        $LongOriginRad;
        $ZoneNumber;
        // (int)
        $ZoneNumber = floor(($Long + 180) / 6) + 1;

        //Make sure the longitude 180.00 is in Zone 60
        if ($Long === 180) {
            $ZoneNumber = 60;
        }

        // Special zone for Norway
        if ($Lat >= 56.0 && $Lat < 64.0 && $Long >= 3.0 && $Long < 12.0) {
            $ZoneNumber = 32;
        }

        // Special zones for Svalbard
        if ($Lat >= 72.0 && $Lat < 84.0) {
            if ($Long >= 0.0 && $Long < 9.0) {
                $ZoneNumber = 31;
            } else if ($Long >= 9.0 && $Long < 21.0) {
                $ZoneNumber = 33;
            } else if ($Long >= 21.0 && $Long < 33.0) {
                $ZoneNumber = 35;
            } else if ($Long >= 33.0 && $Long < 42.0) {
                $ZoneNumber = 37;
            }
        }

        $LongOrigin = ($ZoneNumber - 1) * 6 - 180 + 3; //+3 puts origin
        // in middle of
        // zone
        $LongOriginRad = $this->degToRad($LongOrigin);

        $eccPrimeSquared = ($eccSquared) / (1 - $eccSquared);

        $N = $a / sqrt(1 - $eccSquared * pow(sin($LatRad), 2));
        $T = pow(tan($LatRad), 2);
        $C = $eccPrimeSquared * pow(cos($LatRad), 2);
        $A = cos($LatRad) * ($LongRad - $LongOriginRad);

        $M = $a * (
            (1 - $eccSquared / 4 - 3 * pow($eccSquared, 2) / 64 - 5 * pow($eccSquared, 3) / 256) * $LatRad
            - (3 * $eccSquared / 8 + 3 * pow($eccSquared, 2) / 32 + 45 * pow($eccSquared, 3) / 1024) * sin(2 * $LatRad)
            + (15 * pow($eccSquared, 2) / 256 + 45 * pow($eccSquared, 3) / 1024) * sin(4 * $LatRad)
            - (35 * pow($eccSquared, 3) / 3072) * sin(6 * $LatRad)
        );

        $UTMEasting = ($k0 * $N * ($A + (1 - $T + $C) * pow($A, 3) / 6.0 + (5 - 18 * pow($T, 3) + 72 * $C - 58 * $eccPrimeSquared) * pow($A, 5) / 120.0) + 500000.0);

        $UTMNorthing = ($k0 * ($M + $N * tan($LatRad) * ($A * $A / 2 + (5 - $T + 9 * $C + 4 * pow($C, 2)) * pow($A, 4) / 24.0 + (61 - 58 * pow($T, 3) + 600 * $C - 330 * $eccPrimeSquared) * pow($A, 6) / 720.0)));

        if ($Lat < 0.0) {
            $UTMNorthing += 10000000.0; //10000000 meter offset for
            // southern hemisphere
        }

        $return = new \stdClass();

        $return->northing = round($UTMNorthing);
        $return->easting = round($UTMEasting);
        $return->zoneNumber = $ZoneNumber;
        $return->zoneLetter = $this->getLetterDesignator($Lat);

        return $return;
    }

    /**
     * Converts UTM coords to lat/long, using the WGS84 ellipsoid. This is a convenience
     * class where the Zone can be specified as a single string eg."60N" which
     * is then broken down into the ZoneNumber and ZoneLetter.
     *
     * @private
     * @param {object} utm An object literal with northing, easting, zoneNumber
     *     and zoneLetter properties. If an optional accuracy property is
     *     provided (in meters), a bounding box will be returned instead of
     *     latitude and longitude.
     * @return {object} An object literal containing either lat and lon values
     *     (if no accuracy was provided), or top, right, bottom and left values
     *     for the bounding box calculated according to the provided accuracy.
     *     Returns null if the conversion failed.
     */
    public function UTMtoLL($utm)
    {
        $UTMNorthing = $utm->northing;
        $UTMEasting = $utm->easting;
        $zoneLetter = $utm->zoneLetter;
        $zoneNumber = $utm->zoneNumber;

        // check the ZoneNummber is valid
        if ($zoneNumber < 0 || $zoneNumber > 60) {
            return null;
        }

        $k0 = 0.9996;
        $a = 6378137.0; //ellip.radius;
        $eccSquared = 0.00669438; //ellip.eccsq;
        //$eccPrimeSquared;
        $e1 = (1 - sqrt(1 - $eccSquared)) / (1 + sqrt(1 - $eccSquared));
        //N1, T1, C1, R1, D, M;
        //$LongOrigin;
        //$mu, $phi1Rad;

        // remove 500,000 meter offset for longitude
        $x = $UTMEasting - 500000.0;
        $y = $UTMNorthing;

        // We must know somehow if we are in the Northern or Southern
        // hemisphere, this is the only time we use the letter So even
        // if the Zone letter isn't exactly correct it should indicate
        // the hemisphere correctly
        if ($zoneLetter < 'N') {
            $y -= 10000000.0; // remove 10,000,000 meter offset used
            // for southern hemisphere
        }

        // There are 60 zones with zone 1 being at West -180 to -174
        $LongOrigin = ($zoneNumber - 1) * 6 - 180 + 3; // +3 puts origin
        // in middle of
        // zone

        $eccPrimeSquared = ($eccSquared) / (1 - $eccSquared);

        $M = $y / $k0;
        $mu = $M / ($a * (1 - $eccSquared / 4 - 3 * $eccSquared * $eccSquared / 64 - 5 * $eccSquared * $eccSquared * $eccSquared / 256));

        $phi1Rad = $mu + (3 * $e1 / 2 - 27 * $e1 * $e1 * $e1 / 32) * sin(2 * $mu) + (21 * $e1 * $e1 / 16 - 55 * $e1 * $e1 * $e1 * $e1 / 32) * sin(4 * $mu) + (151 * $e1 * $e1 * $e1 / 96) * sin(6 * $mu);
        // double phi1 = ProjMath.radToDeg(phi1Rad);

        $N1 = $a / sqrt(1 - $eccSquared * pow(sin($phi1Rad), 2));
        $T1 = pow(tan($phi1Rad), 2);
        $C1 = $eccPrimeSquared * pow(cos($phi1Rad), 2);
        $R1 = $a * (1 - $eccSquared) / pow(1 - $eccSquared * pow(sin($phi1Rad), 2), 1.5);
        $D = $x / ($N1 * $k0);

        $lat = $phi1Rad
            - ($N1 * tan($phi1Rad) / $R1) * (pow($D, 2) / 2 - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 * $C1 - 9 * $eccPrimeSquared) * pow($D, 4) / 24 + (61 + 90 * $T1 + 298 * $C1 + 45 * pow($T1, 2) - 252 * $eccPrimeSquared - 3 * pow($C1, 2)) * pow($D, 6) / 720);
        $lat = $this->radToDeg($lat);

        $lon = ($D - (1 + 2 * $T1 + $C1) * pow($D, 3) / 6 + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 * $C1 + 8 * $eccPrimeSquared + 24 * $T1 * $T1) * pow($D, 5) / 120) / cos($phi1Rad);
        $lon = $LongOrigin + $this->radToDeg($lon);

        //var result;
        $result = new \stdClass();

        if (isset($utm->accuracy)) {
            $topRightValues = new \stdClass;

            $topRightValues->northing = $utm->northing + $utm->accuracy;
            $topRightValues->easting = $utm->easting + $utm->accuracy;
            $topRightValues->zoneLetter = $utm->zoneLetter;
            $topRightValues->zoneNumber = $utm->zoneNumber;

            $topRight = $this->UTMtoLL($topRightValues);

            $result->top = $topRight->lat;
            $result->right = $topRight->lon;
            $result->bottom = $lat;
            $result->left = $lon;
        } else {
            $result->lat = $lat;
            $result->lon = $lon;
        }

        return $result;
    }

    /**
     * Calculates the MGRS letter designator for the given latitude.
     *
     * @private
     * @param {number} lat The latitude in WGS84 to get the letter designator
     *     for.
     * @return {char} The letter designator.
     */
    public function getLetterDesignator($lat)
    {
        //This is here as an error flag to show that the Latitude is
        //outside MGRS limits

        $LetterDesignator = 'Z';

        // I'm sure we can turn this into a simple formula, perhaps with a string lookup.
        if ((84 >= $lat) && ($lat >= 72)) {
            $LetterDesignator = 'X';
        } else if ((72 > $lat) && ($lat >= 64)) {
            $LetterDesignator = 'W';
        } else if ((64 > $lat) && ($lat >= 56)) {
            $LetterDesignator = 'V';
        } else if ((56 > $lat) && ($lat >= 48)) {
            $LetterDesignator = 'U';
        } else if ((48 > $lat) && ($lat >= 40)) {
            $LetterDesignator = 'T';
        } else if ((40 > $lat) && ($lat >= 32)) {
            $LetterDesignator = 'S';
        } else if ((32 > $lat) && ($lat >= 24)) {
            $LetterDesignator = 'R';
        } else if ((24 > $lat) && ($lat >= 16)) {
            $LetterDesignator = 'Q';
        } else if ((16 > $lat) && ($lat >= 8)) {
            $LetterDesignator = 'P';
        } else if ((8 > $lat) && ($lat >= 0)) {
            $LetterDesignator = 'N';
        } else if ((0 > $lat) && ($lat >= -8)) {
            $LetterDesignator = 'M';
        } else if ((-8 > $lat) && ($lat >= -16)) {
            $LetterDesignator = 'L';
        } else if ((-16 > $lat) && ($lat >= -24)) {
            $LetterDesignator = 'K';
        } else if ((-24 > $lat) && ($lat >= -32)) {
            $LetterDesignator = 'J';
        } else if ((-32 > $lat) && ($lat >= -40)) {
            $LetterDesignator = 'H';
        } else if ((-40 > $lat) && ($lat >= -48)) {
            $LetterDesignator = 'G';
        } else if ((-48 > $lat) && ($lat >= -56)) {
            $LetterDesignator = 'F';
        } else if ((-56 > $lat) && ($lat >= -64)) {
            $LetterDesignator = 'E';
        } else if ((-64 > $lat) && ($lat >= -72)) {
            $LetterDesignator = 'D';
        } else if ((-72 > $lat) && ($lat >= -80)) {
            $LetterDesignator = 'C';
        }

        return $LetterDesignator;
    }

    /**
     * Encodes a UTM location as MGRS string.
     *
     * @private
     * @param {object} utm An object literal with easting, northing,
     *     zoneLetter, zoneNumber
     * @param {number} accuracy Accuracy in digits (1-5).
     * @return {string} MGRS string for the given UTM location.
     */
    public function encode($utm, $accuracy)
    {
        $seasting = (string)$utm->easting;
        $snorthing = (string)$utm->northing;

        return $utm->zoneNumber
            . $utm->zoneLetter
            . $this->get100kID($utm->easting, $utm->northing, $utm->zoneNumber)
            . substr($seasting, strlen($seasting) - 5, $accuracy)
            //. $seasting.substr($seasting.length - 5, $accuracy)
            . substr($snorthing, strlen($snorthing) - 5, $accuracy);
            //. $snorthing.substr($snorthing.length - 5, $accuracy);
    }

    /**
     * Get the two letter 100k designator for a given UTM easting,
     * northing and zone number value.
     *
     * @private
     * @param {number} easting
     * @param {number} northing
     * @param {number} zoneNumber
     * @return the two letter 100k designator for the given UTM location.
     */
    public function get100kID($easting, $northing, $zoneNumber)
    {
        $setParm = $this->get100kSetForZone($zoneNumber);
        $setColumn = floor($easting / 100000);
        $setRow = floor($northing / 100000) % 20;
        return $this->getLetter100kID($setColumn, $setRow, $setParm);
    }

    /**
     * Given a UTM zone number, figure out the MGRS 100K set it is in.
     *
     * @private
     * @param {number} i An UTM zone number.
     * @return {number} the 100k set the UTM zone is in.
     */
    public function get100kSetForZone($i)
    {
        $setParm = $i % static::NUM_100K_SETS;

        if ($setParm === 0) {
            $setParm = static::NUM_100K_SETS;
        }

        return $setParm;
    }

    /**
     * Get the two-letter MGRS 100k designator given information
     * translated from the UTM northing, easting and zone number.
     *
     * @private
     * @param {number} column the column index as it relates to the MGRS
     *        100k set spreadsheet, created from the UTM easting.
     *        Values are 1-8.
     * @param {number} row the row index as it relates to the MGRS 100k set
     *        spreadsheet, created from the UTM northing value. Values
     *        are from 0-19.
     * @param {number} parm the set block, as it relates to the MGRS 100k set
     *        spreadsheet, created from the UTM zone. Values are from
     *        1-60.
     * @return two letter MGRS 100k code.
     */
    public function getLetter100kID($column, $row, $parm)
    {
        // colOrigin and rowOrigin are the letters at the origin of the set
        $index = $parm - 1;
        $colOrigin = ord(substr(static::SET_ORIGIN_COLUMN_LETTERS, $index, 1));
        //$colOrigin = SET_ORIGIN_COLUMN_LETTERS.charCodeAt(index);
        $rowOrigin = ord(substr(static::SET_ORIGIN_ROW_LETTERS, $index, 1));
        //$rowOrigin = SET_ORIGIN_ROW_LETTERS.charCodeAt(index);

        // colInt and rowInt are the letters to build to return
        $colInt = $colOrigin + $column - 1;
        $rowInt = $rowOrigin + $row;
        $rollover = false;

        if ($colInt > static::Z) {
            $colInt = $colInt - static::Z + static::A - 1;
            $rollover = true;
        }

        if (
            $colInt === static::I
            || ($colOrigin < static::I && $colInt > static::I)
            || (($colInt > static::I || $colOrigin < static::I) && $rollover)
        ) {
            $colInt++;
        }

        if (
            $colInt === static::O
            || ($colOrigin < static::O && $colInt > static::O)
            || (($colInt > static::O || $colOrigin < static::O) && $rollover)
        ) {
            $colInt++;

            if ($colInt === static::I) {
                $colInt++;
            }
        }

        if ($colInt > static::Z) {
            $colInt = $colInt - static::Z + static::A - 1;
        }

        if ($rowInt > static::V) {
            $rowInt = $rowInt - static::V + static::A - 1;
            $rollover = true;
        } else {
            $rollover = false;
        }

        if (
            (($rowInt === static::I) || (($rowOrigin < static::I) && ($rowInt > static::I)))
            || ((($rowInt > static::I) || ($rowOrigin < static::I)) && $rollover)
        ) {
            $rowInt++;
        }

        if ((($rowInt === static::O) || (($rowOrigin < static::O) && ($rowInt > static::O))) || ((($rowInt > static::O) || ($rowOrigin < static::O)) && $rollover)) {
            $rowInt++;

            if ($rowInt === static::I) {
                $rowInt++;
            }
        }

        if ($rowInt > static::V) {
            $rowInt = $rowInt - static::V + static::A - 1;
        }

        $twoLetter = chr($colInt) . chr($rowInt);

        return $twoLetter;
    }



    /**
     * Decode the UTM parameters from a MGRS string.
     *
     * @private
     * @param {string} mgrsString an UPPERCASE coordinate string is expected.
     * @return {object} An object literal with easting, northing, zoneLetter,
     *     zoneNumber and accuracy (in meters) properties.
     */

    public function decode($mgrsString)
    {
        if ($mgrsString && strlen($mgrsString) === 0) {
            throw new \Exception("MGRSPoint coverting from nothing");
        }

        $length = strlen($mgrsString);

        $hunK = null;
        $sb = "";
        //$testChar;
        $i = 0;

        // get Zone number

        // What does this do?
        // It appears to take UP TO two digits from the front of the string.
        // If there are more than two digits, then it throws an exception.
        // If there are less than two digits, then it seems to be happy (though
        // en exception is raised later if there are no digits).

        //while (!(/[A-Z]/).test(testChar = mgrsString.charAt(i))) { // FIX
        while ( ! preg_match('/[A-Z]/', substr($mgrsString, $i, 1))) {
            if ($i >= 2) {
                throw new \Exception("MGRSPoint bad conversion from: " . $mgrsString);
            }

            $sb .= substr($mgrsString, $i, 1);
            $i++;
        }

        if ($i === 0 || $i + 3 > $length) {
            // A good MGRS string has to be 4-5 digits long,
            // ##AAA/#AAA at least.
            throw new \Exception("MGRSPoint bad conversion from: " . $mgrsString);
        }

        $zoneNumber = (int)$sb;

        $zoneLetter = substr($mgrsString, ($i++), 1);

        // Should we check the zone letter here? Why not.
        // These are a handfull of zone letters that are not allowed.
        if (
            $zoneLetter <= 'A'
            || $zoneLetter === 'B'
            || $zoneLetter === 'Y'
            || $zoneLetter >= 'Z'
            || $zoneLetter === 'I'
            || $zoneLetter === 'O'
        ) {
            throw new \Exception("MGRSPoint zone letter " . $zoneLetter . " not handled: " . $mgrsString);
        }

        // PHP substr functions slightly differently to JS substring.
        //hunK = mgrsString.substring(i, i += 2);
        $hunK = substr($mgrsString, $i, 2);
        $i += 2;

        $set = $this->get100kSetForZone($zoneNumber);

        $east100k = $this->getEastingFromChar(substr($hunK, 0, 1), $set);
        $north100k = $this->getNorthingFromChar(substr($hunK, 1, 1), $set);

        // We have a bug where the northing may be 2000000 too low.

        // How
        // do we know when to roll over?

        while ($north100k < $this->getMinNorthing($zoneLetter)) {
            $north100k += 2000000;
        }

        // calculate the char index for easting/northing separator
        $remainder = $length - $i;

        if ($remainder % 2 !== 0) {
            throw new \Exception("MGRSPoint has to have an even number \nof digits after the zone letter and two 100km letters - front \nhalf for easting meters, second half for \nnorthing meters" . $mgrsString);
        }

        $sep = $remainder / 2;

        $sepEasting = 0.0;
        $sepNorthing = 0.0;
        //$accuracyBonus, sepEastingString, sepNorthingString, easting, northing;
        if ($sep > 0) {
            $accuracyBonus = 100000.0 / pow(10, $sep);
            $sepEastingString = substr($mgrsString, $i, $i + $sep);
            $sepEasting = (float)$sepEastingString * $accuracyBonus;
            $sepNorthingString = substr($mgrsString, $i + $sep);
            $sepNorthing = (float)$sepNorthingString * $accuracyBonus;
        }

        $easting = $sepEasting + $east100k;
        $northing = $sepNorthing + $north100k;

        $return = new \stdClass;

        $return->easting = $easting;
        $return->northing = $northing;
        $return->zoneLetter = $zoneLetter;
        $return->zoneNumber = $zoneNumber;
        $return->accuracy = $accuracyBonus;

        return $return;
    }

    /**
     * Given the first letter from a two-letter MGRS 100k zone, and given the
     * MGRS table set for the zone number, figure out the easting value that
     * should be added to the other, secondary easting value.
     *
     * @private
     * @param {char} e The first letter from a two-letter MGRS 100´k zone.
     * @param {number} set The MGRS table set for the zone number.
     * @return {number} The easting value for the given letter and set.
     */

    public function getEastingFromChar($e, $set)
    {
        // colOrigin is the letter at the origin of the set for the
        // column
        $curCol = substr(static::SET_ORIGIN_COLUMN_LETTERS, $set - 1, 1);
        $eastingValue = 100000.0;
        $rewindMarker = false;

        while ($curCol !== substr($e, 0, 1)) {
            $curCol++;

            if ($curCol === static::I) {
                $curCol++;
            }

            if ($curCol === static::O) {
                $curCol++;
            }

            if ($curCol > static::Z) {
                if ($rewindMarker) {
                    throw new \Exception("Bad character: " . $e);
                }

                $curCol = static::A;
                $rewindMarker = true;
            }

            $eastingValue += 100000.0;
        }

        return $eastingValue;
    }


/**
     * Given the second letter from a two-letter MGRS 100k zone, and given the
     * MGRS table set for the zone number, figure out the northing value that
     * should be added to the other, secondary northing value. You have to
     * remember that Northings are determined from the equator, and the vertical
     * cycle of letters mean a 2000000 additional northing meters. This happens
     * approx. every 18 degrees of latitude. This method does *NOT* count any
     * additional northings. You have to figure out how many 2000000 meters need
     * to be added for the zone letter of the MGRS coordinate.
     *
     * @private
     * @param {char} n Second letter of the MGRS 100k zone
     * @param {number} set The MGRS table set number, which is dependent on the
     *     UTM zone number.
     * @return {number} The northing value for the given letter and set.
     */

    public function getNorthingFromChar($n, $set)
    {
        if ($n > 'V') {
            throw new \Exception("MGRSPoint given invalid Northing " . $n);
        }

        // rowOrigin is the letter at the origin of the set for the
        // column
        $curRow = substr(static::SET_ORIGIN_ROW_LETTERS, $set - 1, 1);
        $northingValue = 0.0;
        $rewindMarker = false;

        while ($curRow !== substr($n, 0, 1)) {
            $curRow++;

            if ($curRow === static::I) {
                $curRow++;
            }

            if ($curRow === static::O) {
                $curRow++;
            }

            // fixing a bug making whole application hang in this loop
            // when 'n' is a wrong character
            if ($curRow > static::V) {
                if ($rewindMarker) { // making sure that this loop ends
                    throw new \Exception("Bad character: " . $n);
                }
                $curRow = static::A;
                $rewindMarker = true;
            }

            $northingValue += 100000.0;
        }

        return $northingValue;
    }


    /**
     * The function getMinNorthing returns the minimum northing value of a MGRS
     * zone.
     *
     * Ported from Geotrans' c Lattitude_Band_Value structure table.
     *
     * @private
     * @param {char} zoneLetter The MGRS zone to get the min northing for.
     * @return {number}
     */

    public function getMinNorthing($zoneLetter)
    {
        //var northing;
        switch ($zoneLetter) {
            case 'C':
                $northing = 1100000.0;
                break;
            case 'D':
                $northing = 2000000.0;
                break;
            case 'E':
                $northing = 2800000.0;
                break;
            case 'F':
                $northing = 3700000.0;
                break;
            case 'G':
                $northing = 4600000.0;
                break;
            case 'H':
                $northing = 5500000.0;
                break;
            case 'J':
                $northing = 6400000.0;
                break;
            case 'K':
                $northing = 7300000.0;
                break;
            case 'L':
                $northing = 8200000.0;
                break;
            case 'M':
                $northing = 9100000.0;
                break;
            case 'N':
                $northing = 0.0;
                break;
            case 'P':
                $northing = 800000.0;
                break;
            case 'Q':
                $northing = 1700000.0;
                break;
            case 'R':
                $northing = 2600000.0;
                break;
            case 'S':
                $northing = 3500000.0;
                break;
            case 'T':
                $northing = 4400000.0;
                break;
            case 'U':
                $northing = 5300000.0;
                break;
            case 'V':
                $northing = 6200000.0;
                break;
            case 'W':
                $northing = 7000000.0;
                break;
            case 'X':
                $northing = 7900000.0;
                break;
            default:
                $northing = -1.0;
        }

        if ($northing >= 0.0) {
            return $northing;
        } else {
            throw new \Exception("Invalid zone letter: " . $zoneLetter);
        }
    }
}

