<?php

namespace Academe\Proj4Php\Defs;

use Academe\Proj4Php\Proj4;

class Epsg27700 {
    public function init() {
        // Add this entry to the static global. Not a good way to handle it when this is essentially just a
        // lumnp of string data.

        Proj4::$defs["EPSG:27700"] = "+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs";
    }
}

