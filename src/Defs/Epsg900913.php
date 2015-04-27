<?php

namespace Academe\Proj4Php\Defs;

use Academe\Proj4Php\Proj4;

class Epsg900913 {
    public function init() {
        // Add this entry to the static global. Not a good way to handle it when this is essentially just a
        // lumnp of string data.

        Proj4::$defs["EPSG:900913"] = "+title= Google Mercator EPSG:900913 +proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +no_defs";
    }
}

