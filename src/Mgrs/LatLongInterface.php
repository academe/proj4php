<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Interface for setting and getting lat/long values.
 */

interface LatLongInterface {
    /**
     * Set the latitude.
     *
     * @param double $latitude
     */

    public function setLatitude($latitude);

    /**
     * Get the latitude.
     *
     * @return double
     */

    public function getLatitude();

    /**
     * Set the longitude.
     *
     * @param double $longitude
     */

    public function setLongitude($longitude);

    /**
     * Get the longitude.
     *
     * @return double
     */

    public function getLongitude();
}

