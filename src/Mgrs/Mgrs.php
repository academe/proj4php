<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Ported from https://github.com/proj4js/mgrs/blob/master/mgrs.js
 * Good converter site for testing against:
 * http://www.earthpoint.us/convert.aspx
 * Description of the UTM/MGRS grid systems:
 * http://www.luomus.fi/en/utm-mgrs-atlas-florae-europaeae
 * TODO: expose more of the functionality to make MGRS and UTM available formats.
 * TODO: interfaces needed for passing Lat/long, MGRS and UTM objects in and out.
 */

class Mgrs extends Utm
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

    // ASCII values of letters.
    // There will be a much less clumsy way of doing this.

    const A = 65; // A
    const I = 73; // I
    const O = 79; // O
    const V = 86; // V
    const Z = 90; // Z

    /**
     * The minumum northing for a zone letter.
     */

    protected static $zone_min_northing = array(
        'C' => 1100000.0,
        'D' => 2000000.0,
        'E' => 2800000.0,
        'F' => 3700000.0,
        'G' => 4600000.0,
        'H' => 5500000.0,
        'J' => 6400000.0,
        'K' => 7300000.0,
        'L' => 8200000.0,
        'M' => 9100000.0,
        'N' => 0.0,
        'P' => 800000.0,
        'Q' => 1700000.0,
        'R' => 2600000.0,
        'S' => 3500000.0,
        'T' => 4400000.0,
        'U' => 5300000.0,
        'V' => 6200000.0,
        'W' => 7000000.0,
        'X' => 7900000.0,
    );

    /**
     * Conversion of lat/lon to MGRS reference.
     *
     * @param {object} ll Object literal with lat and lon properties on a
     *     WGS84 ellipsoid.
     * @param {int} accuracy Accuracy in digits (5 for 1 m, 4 for 10 m, 3 for
     *      100 m, 4 for 1000 m or 5 for 10000 m). Optional, default is 5.
     * @return {string} the MGRS string for the given location and accuracy.
     *
     * TODO: this should be static if it is doing this full conversion in one step.
     */
    public static function forward($lat_long, $accuracy = null)
    {
        // default accuracy 1m
        $accuracy = $accuracy || static::MAX_ACCURACY;

        // This handles $lat_long being in any of a number of different formats.
        $mgrs = static::fromLatLong($lat_long);

        return static::encode($mgrs, $accuracy);
    }

    /**
     * Conversion of MGRS reference to a lat/lon bounding box.
     *
     * @param {string} mgrs MGRS string.
     * @return {array} An array with left (longitude), bottom (latitude), right
     *     (longitude) and top (latitude) values in WGS84, representing the
     *     bounding box for the provided MGRS reference.
     * We actually want to return a Square.
     */
    public static function inverse($mgrs_string)
    {
        $mgrs = static::decode(strtoupper($mgrs_string));
        return $mgrs->toSquare();
    }

    /**
     * Convert an MGRS coordinate reference string to a LatLong coordinate.
     * The point is the centre of the square, according to the accuracy that the
     * reference carries (the number of digits it uses).
     */
    public static function toPoint($mgrs_string)
    {
        $lat_long_bounding_box = static::inverse($mgrs_string);

        // Return the centre of the box as a LatLong object.

        return new LatLong(
            ($lat_long_bounding_box->getBottomLeft()->getLatitude() + $lat_long_bounding_box->getTopRight()->getLatitude()) / 2,
            ($lat_long_bounding_box->getBottomLeft()->getLongitude() + $lat_long_bounding_box->getTopRight()->getLongitude()) / 2
        );
    }

    /**
     * Conversion from degrees to radians.
     *
     * @private
     * @param {number} deg the angle in degrees.
     * @return {number} the angle in radians.
     */
    protected function degToRad($deg)
    {
        return deg2rad($deg);
    }

    /**
     * Conversion from radians to degrees.
     *
     * @private
     * @param {number} rad the angle in radians.
     * @return {number} the angle in degrees.
     */
    protected function radToDeg($rad)
    {
        return rad2deg($rad);
    }

    /**
     * Encodes a UTM location as MGRS string.
     *
     * @private
     * @param {object} utm An object literal with easting, northing,
     *     zoneLetter, zoneNumber
     * @param {number} accuracy Accuracy in digits (1-5).
     * @return {string} MGRS string for the given UTM location.
     * @todo Test this on easting/northing strings that are less than 5 digits long.
     */
    protected static function encode($utm, $accuracy)
    {
        $seasting = (string)$utm->easting;
        $snorthing = (string)$utm->northing;

        return $utm->zone_number
            . $utm->zone_letter
            . static::get100kID($utm->easting, $utm->northing, $utm->zone_number)
            . substr($seasting, strlen($seasting) - static::MAX_ACCURACY, $accuracy)
            . substr($snorthing, strlen($snorthing) - static::MAX_ACCURACY, $accuracy);
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
    protected static function get100kID($easting, $northing, $zoneNumber)
    {
        $setParm = static::get100kSetForZone($zoneNumber);
        $setColumn = floor($easting / 100000);
        $setRow = floor($northing / 100000) % 20;
        return static::getLetter100kID($setColumn, $setRow, $setParm);
    }

    /**
     * Given a UTM zone number, figure out the MGRS 100K set it is in.
     *
     * @private
     * @param {number} i An UTM zone number.
     * @return {number} the 100k set the UTM zone is in.
     */
    protected static function get100kSetForZone($i)
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
    protected function getLetter100kID($column, $row, $parm)
    {
        // colOrigin and rowOrigin are the letters at the origin of the set
        $index = $parm - 1;
        $colOrigin = ord(substr(static::SET_ORIGIN_COLUMN_LETTERS, $index, 1));
        $rowOrigin = ord(substr(static::SET_ORIGIN_ROW_LETTERS, $index, 1));

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

        if (
            (($rowInt === static::O) || (($rowOrigin < static::O) && ($rowInt > static::O)))
            || ((($rowInt > static::O) || ($rowOrigin < static::O)) && $rollover)
        ) {
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
    protected static function decode($mgrsString)
    {
        if ($mgrsString && strlen($mgrsString) === 0) {
            throw new \Exception("MGRSPoint converting from nothing");
        }

        $length = strlen($mgrsString);

        $hunK = null;
        $sb = "";
        $i = 0;

        // get Zone number

        // What does this do?
        // It appears to take UP TO two digits from the front of the string.
        // If there are more than two digits, then it throws an exception.
        // If there are less than two digits, then it seems to be happy (though
        // en exception is raised later if there are no digits).

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

        $zoneLetter = substr($mgrsString, $i, 1);
        $i += 1;

        // Should we check the zone letter here? Why not.
        // These are a handful of zone letters that are not allowed.
        // CHECKME: A, B, Y and Z all cover polar regions, so this may not be strictly correct.
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

        // PHP substr functions work differently to JS substring.
        //hunK = mgrsString.substring(i, i += 2);
        $hunK = substr($mgrsString, $i, 2);
        $i += 2;

        $set = static::get100kSetForZone($zoneNumber);

        $east100k = static::getEastingFromChar(substr($hunK, 0, 1), $set);
        $north100k = static::getNorthingFromChar(substr($hunK, 1, 1), $set);

        // We have a bug where the northing may be 2000000 too low.

        // How do we know when to roll over?

        while ($north100k < static::getMinNorthing($zoneLetter)) {
            $north100k += 2000000;
        }

        // calculate the char index for easting/northing separator
        $remainder = $length - $i;

        // Remaining string must be dividable into two equal halves.
        if ($remainder % 2 !== 0) {
            throw new \Exception("MGRSPoint has to have an even number \nof digits after the zone letter and two 100km letters - front \nhalf for easting meters, second half for \nnorthing meters" . $mgrsString);
        }

        $sep = $remainder / 2;

        $sepEasting = 0.0;
        $sepNorthing = 0.0;
        //$accuracyBonus, sepEastingString, sepNorthingString, easting, northing;

        if ($sep > 0) {
            $accuracyBonus = 100000.0 / pow(10, $sep);
            $sepEastingString = substr($mgrsString, $i, $sep);
            $sepEasting = (float)$sepEastingString * $accuracyBonus;
            $sepNorthingString = substr($mgrsString, $i + $sep);
            $sepNorthing = (float)$sepNorthingString * $accuracyBonus;
        }

        $easting = $sepEasting + $east100k;
        $northing = $sepNorthing + $north100k;

        // Return a new Mgrs object.

        $mgrs = new static(
            $easting,
            $northing,
            $zoneLetter,
            $zoneNumber
        );

        return $mgrs;
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

    protected static function getEastingFromChar($e, $set)
    {
        // colOrigin is the letter at the origin of the set for the column.
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

    protected function getNorthingFromChar($n, $set)
    {
        if ($n > 'V') {
            throw new \Exception("MGRSPoint given invalid Northing " . $n);
        }

        // rowOrigin is the letter at the origin of the set for the column.
        $curRow = ord(substr(static::SET_ORIGIN_ROW_LETTERS, $set - 1, 1));
        $northingValue = 0.0;
        $rewindMarker = false;

        while ($curRow !== ord(substr($n, 0, 1))) {
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
     * The function getMinNorthing returns the minimum northing value of a MGRS zone.
     *
     * Ported from Geotrans' c Lattitude_Band_Value structure table.
     *
     * @private
     * @param {char} zoneLetter The MGRS zone to get the min northing for.
     * @return {number}
     */

    protected static function getMinNorthing($zone_letter)
    {
        if (isset(static::$zone_min_northing[$zone_letter])) {
            return static::$zone_min_northing[$zone_letter];
        }

        throw new \Exception("Invalid zone letter: " . $zone_letter);
    }
}

