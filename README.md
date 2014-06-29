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

In here is a LatLong class that holds a latitude and longitude. The Square class holds two LatLong
classes to mark the opposite corners of the bounding box. The Utm coordinate can be used by itself.
The Mgrs coordinate extends Utm with its set of reference conversion methods.

None of these classes can be created without valid coordinates or references passed in to the
constructor.
