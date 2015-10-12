Proj4JS Port to PHP5.3
======================

*Note: I'll fix any issues found here by Pull Request, and keep this package here, but it is not
being actively developed. There is a full [Proj4 PHP port here](https://github.com/proj4php/proj4php)
with some great composer-based features, that is derived from [proj4JS](https://github.com/proj4js/proj4js).
I am also experimenting with [some alternative approaches here](https://github.com/judgej/Proj4) that
will hopefully feed into [Proj4php](https://github.com/proj4php/proj4php).*

I just wanted some simple conversions and transforms, and ended up with this. I must be a masachist.

Proj4JS has split out the MGRS handling to a separate module, possibly due to licensing issues. It is
included in this library for the time-being, but has had a substantial refactor and rewrite from the
original.

The main Mgrs classes have a mix of static methods that return new objects (Mgrs, LatLong, Square, Utm
objects) and methods that operate on the current object. I intend to make it a little clearer which
method does what. It has just inherited much of this from the JavaScript library, and partly from
my learning curve in how the JavaScript library works.

Mgrs
----

namespace: Academe\Proj4Php\Mgrs

The LatLong and Square classes implement minimal interfaces to support the UTM and MGRS classes
as a standalone module (nothing in Academe\Proj4Php\Mgrs depends on anything else). This may change,
depending on whether Mgrs is split off into a separate library, or coupled more tightly with the
other coordinate classes on the main Proj4Php library. It will probably depend on inherited licenses.

The LatLong class holds a latitude and longitude.

    $latitude = 53.0;
    $longitude = -5.5;
    
    $lat_long = new LatLong($latitude, $longitude);
    $lat_long = new LatLong(array($latitude, $longitude));

The Square class holds two LatLong classes to mark the opposite corners of the bounding box.

    $square = new Square($lat_long_bottom_left, $lat_long_top_right);

The Utm class holds a UTM coordinate.

    // From base UTM values.
    $utm = new Utm($northing, $easting, $zone_number, $zone_letter);
    
    // From latitude/longitude coordinates (WGS84 ellipsoid).
    $utm = Utm::fromLatLong($latitude, $longitude);
    $utm = Utm::fromLatLong($lat_long);
    
    // Back to lat/long.
    $lat_long = $utm->toLatLong();
    
    // To a UTM grid reference string.
    $grid_reference = $utm->toGridReference(); // '39L 198447 8893330'
    $grid_reference = (string)$utm;
    $grid_reference = $utm->toGridReference('%z$l%EE%NN'); // '39L0198447E8893330N'

The UTM grid reference formatting fields are:

* %z Zone number
* %l Zone letter
* %h Hemisphere letter (N or S)
* %e Easting
* %n Northing
* %E Easting left-padded to 7 digits
* %N Northing left-padded to 7 digits
    
The Mgrs class extends Utm with its set of reference conversion methods.

    // Create from base UTM values.
    $mgrs = new Mgrs($northing, $easting, $zone_number, $zone_letter);
    
    // From lat/long (same as for Utm)
    $mgrs = Mgrs::fromLatLong($latitude, $longitude);
    $mgrs = Mgrs::fromLatLong($lat_long);
    
    // From a MGRS grid reference.
    // The accuracy of the reference is noted and stored with the reference.
    $mgrs = Mgrs::fromGridReference($mgrs_grid_reference);

    // To a MGRS grid reference string.
    // Template is optional, defaulting to '%z%l%k%e%n'.
    // The accuracy is optional 0 to 5, defaulting to 5.
    $grid_reference = $mgrs->toGridReference($template, $accuracy);
    
    // To a single lat/long coordinate in the *centre* of the square according to
    // teh accuracy, to one metre.
    // $accuracy is optional, and defaults to the accuracy of the current coordinate.
    $lat_long = $mgrs->toPoint($accuracy);
    
    // The bottom left coordinate, disregarding the accuracy (like toPoint with the
    // maximum accuracy of 5).
    $lat_long = $mgrs->toLatLong();

    // To a Square region.
    // The accuracy is optional 0 to 5, defaulting to 5.
    $square = $mgrs->toSquare($accuracy);

The MGRS grid reference formatting fields are:

* %z Zone number
* %l Zone letter
* %k 100km zone ID (two letters)
* %e Easting, to the current accuracy
* %n Northing, to the current accuracy

Instantiating a Utm, Mgrs, LatLong or Square class always requires a valid coordiate in some form.

References
==========

* http://www.luomus.fi/en/utm-mgrs-atlas-florae-europaeae  
  Description of the UTM/MGRS grid systems
* http://www.earthpoint.us/convert.aspx  
  Good converter site for testing against
* http://en.wikipedia.org/wiki/Military_grid_reference_system  
  MGRS grid reference format definition and examples.
* http://therucksack.tripod.com/MiBSAR/LandNav/UTM/UTM.htm  
  Great description of how the UTM grid reference works, including the polar regions.
  Has an emphasis on how the system is used my map readers and users.
