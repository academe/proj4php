<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Square concrete.
 */

class Square implements SquareInterface {
    /**
     * The square is defined by two lat/long points - bottom left and top right.
     */

    protected $bottom_left;
    protected $top_right;

    /**
     * Set the bottom left coordinate of the square.
     */
    public function setBottomLeft(LatLongInterface $coordinate) {
        $this->bottom_left = $coordinate;
    }

    /**
     * Get the bottom left coordinate of the square.
     */
    public function getBottomLeft() {
        return $this->bottom_left;
    }

    /**
     * Set the top right coordinate of the square.
     */
    public function setTopRight(LatLongInterface $coordinate) {
        $this->top_right = $coordinate;
    }

    /**
     * Get the top right coordinate of the square.
     */
    public function getTopRight() {
        return $this->top_right;
    }

    /**
     * You must construct with a pair of LatLong objects.
     */
    public function __construct(LatLongInterface $lat_long_bottom_left, LatLongInterface $lat_long_top_right) {
        $this->setBottomLeft($lat_long_bottom_left);
        $this->setTopRight($lat_long_top_right);
    }
}

