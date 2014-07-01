<?php

namespace Academe\Proj4Php\Mgrs;

/**
 * Square interface.
 */

interface SquareInterface
{
    /**
     * Set the bottom left coordinate of the square.
     */
    public function setBottomLeft(LatLongInterface $coordinate);

    /**
     * Get the bottom left coordinate of the square.
     */
    public function getBottomLeft();

    /**
     * Set the top right coordinate of the square.
     */
    public function setTopRight(LatLongInterface $coordinate);

    /**
     * Get the top right coordinate of the square.
     */
    public function getTopRight();
}

