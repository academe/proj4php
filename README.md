Proj4JS Port to PHP5.3
======================

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

The LatLong class holds a latitude and longitude.

    $latitude = 53.0;
    $longitude = -5.5;
    
    $lat_long = new LatLong($latitude, $longitude);
    $lat_long = new LatLong(array($latitude, $longitude));

The Square class holds two LatLong classes to mark the opposite corners of the bounding box.

    $square = new Square($lat_long_bottom_left, $lat_long_top_right);

The Utm class holds a UTM coordinate.

    $utm = new Utm($northing, $easting, $zone_number, $zone_letter);
    $utm = Utm::fromLatLong($latitude, $longitude);
    $utm = Utm::fromLatLong($lat_long);

The Mgrs coordinate extends Utm with its set of reference conversion methods.

None of these classes can be created without valid coordinates or references passed in to the
constructor.
