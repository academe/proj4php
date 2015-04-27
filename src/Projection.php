<?php

namespace Academe\Proj4Php;

/**
 * Author : Julien Moquet, Jason Judge
 *
 * Inspired by Proj4js from Mike Adair madairATdmsolutions.ca
 *                      and Richard Greenwood rich@greenwoodmap.com
 * License: LGPL as per: http://www.gnu.org/copyleft/lesser.html
 */
class Projection {

    /**
     * Property: readyToUse
     * Flag to indicate if initialization is complete for $this Proj object
     */
    public $readyToUse = false;

    /**
     * Property: title
     * The title to describe the projection
     */
    public $title = null;

    /**
     * Property: projName
     * The projection class for $this projection, e.g. lcc (lambert conformal conic,
     * or merc for mercator).  These are exactly equivalent to their Proj4
     * counterparts.
     */
    public $projName = null;

    /**
     * Property: projection
     * The projection object for $this projection. */
    public $projection = null;

    /**
     * Property: units
     * The units of the projection.  Values include 'm' and 'degrees'
     */
    public $units = null;

    /**
     * Property: datum
     * The datum specified for the projection
     */
    public $datum = null;

    /**
     * Property: x0
     * The x coordinate origin
     */
    public $x0 = 0;

    /**
     * Property: y0
     * The y coordinate origin
     */
    public $y0 = 0;

    /**
     * Property: localCS
     * Flag to indicate if the projection is a local one in which no transforms
     * are required.
     */
    public $localCS = false;

    /**
     *
     * @var type
     */
    protected $wktRE = '/^(\w+)\[(.*)\]$/';

    /**
     * Constructor: initialize
     * Constructor for Proj4php::Proj objects
     *
     * Parameters:
     * $srsCode - a code for map projection definition parameters.  These are usually
     * (but not always) EPSG codes.
     * TODO: also support the raw def string being passed in and parsed here.
     */
    public function __construct($srsCode) {
        $this->srsCodeInput = $srsCode;

        //check to see if $this is a WKT string
        if (
            (strpos($srsCode, 'GEOGCS') !== false)
            || (strpos($srsCode, 'GEOCCS') !== false)
            || (strpos($srsCode, 'PROJCS') !== false)
            || (strpos($srsCode, 'LOCAL_CS') !== false)
        ) {
            $this->parseWKT($srsCode);
            $this->deriveConstants();
            $this->loadProjCode($this->projName);
            return;
        }

        // DGR 2008-08-03 : support urn and url
        if (strpos($srsCode, 'urn:') === 0) {
            //urn:ORIGINATOR:def:crs:CODESPACE:VERSION:ID
            $urn = explode(':', $srsCode);
            if (
                ($urn[1] == 'ogc' || $urn[1] == 'x-ogc')
                && ($urn[2] == 'def')
                && ($urn[3] == 'crs')
            ) {
                $srsCode = $urn[4] . ':' . $urn[strlen($urn) - 1];
            }
        } elseif (strpos($srsCode, 'http://') === 0) {
            //url#ID
            $url = explode('#', $srsCode);
            if (preg_match('/epsg.org/', $url[0])) {
                // http://www.epsg.org/#
                $srsCode = 'EPSG:' . $url[1];
            } elseif (preg_match('/RIG.xml/', $url[0])) {
                //http://librairies.ign.fr/geoportail/resources/RIG.xml#
                //http://interop.ign.fr/registers/ign/RIG.xml#
                $srsCode = 'IGNF:' . $url[1];
            }
        }

        $this->srsCode = strtoupper($srsCode);

        if (strpos($this->srsCode, 'EPSG') === 0) {
            $this->srsCode = $this->srsCode;
            $this->srsAuth = 'epsg';
            $this->srsProjNumber = substr($this->srsCode, 5);
            // DGR 2007-11-20 : authority IGNF
        } elseif (strpos($this->srsCode, 'IGNF') === 0) {
            $this->srsCode = $this->srsCode;
            $this->srsAuth = 'IGNF';
            $this->srsProjNumber = substr($this->srsCode, 5);
            // DGR 2008-06-19 : pseudo-authority CRS for WMS
        } elseif (strpos($this->srsCode, 'CRS') === 0) {
            $this->srsCode = $this->srsCode;
            $this->srsAuth = 'CRS';
            $this->srsProjNumber = substr($this->srsCode, 4);
        } else {
            $this->srsAuth = '';
            $this->srsProjNumber = $this->srsCode;
        }

        $this->loadProjDefinition();
    }

    /**
     * Function: loadProjDefinition
     *    Loads the coordinate system initialization string if required.
     *    Note that dynamic loading happens asynchronously so an application must
     *    wait for the readyToUse property is set to true.
     *    To prevent dynamic loading, include the defs through a script tag in
     *    your application.
     * @todo We really should be reading these definitions from one or more data
     * sources and not having to create a class for each one.
     *
     */
    public function loadProjDefinition() {
        // Check if in memory.
        if (array_key_exists($this->srsCode, Proj4::$defs)) {
            $this->defsLoaded();
            return;
        }

        // Otherwise check for def on the server
        //$filename = dirname(__FILE__) . '/defs/' . strtoupper( $this->srsAuth ) . $this->srsProjNumber . '.php';
        $classname = '\\Academe\\Proj4Php\\Defs\\' . ucfirst(strtolower($this->srsAuth)) . $this->srsProjNumber;

        try {
            //Proj4::loadScript($filename);
            $classname::init();
            $this->defsLoaded(); // success
        } catch (Exception $e) {
            $this->loadFromService(); // fail
        }
    }

    /**
     * Function: loadFromService
     *    Creates the REST URL for loading the definition from a web service and
     *    loads it.
     *
     * DO IT AGAIN. : SHOULD PHP CODE BE GET BY WEBSERVICES?
     */
    public function loadFromService() {
        //else load from web service
        $url = Proj4::$defsLookupService . '/' . $this->srsAuth . '/' . $this->srsProjNumber . '/proj4/';

        try {
            Proj4::$defs[strtoupper($this->srsAuth) . ":" . $this->srsProjNumber] = Proj4::loadScript($url);
        } catch (Exception $e) {
            $this->defsFailed();
        }
    }

    /**
     * Function: defsLoaded
     * Continues the Proj object initilization once the def file is loaded
     *
     */
    public function defsLoaded() {
        $this->parseDefs();
        $this->loadProjCode($this->projName);
    }

    /**
     * Function: checkDefsLoaded
     *    $this is the loadCheck method to see if the def object exists
     *
     */
    public function checkDefsLoaded() {
        return isset(Proj4::$defs[$this->srsCode]) && !empty(Proj4::$defs[$this->srsCode]);
    }

    /**
     * Function: defsFailed
     *    Report an error in loading the defs file, but continue on using WGS84
     *
     */
    public function defsFailed() {
        Proj4::reportError('failed to load projection definition for: ' . $this->srsCode);
        // set it to something so it can at least continue
        Proj4::$defs[$this->srsCode] = Proj4::$defs['WGS84'];
        $this->defsLoaded();
    }

    /**
     * Function: loadProjCode
     *    Loads projection class code dynamically if required.
     *     Projection code may be included either through a script tag or in
     *     a built version of proj4php
     *
     * An exception occurs if the projection is not found.
     */
    public function loadProjCode($projName) {
        if (array_key_exists($projName, Proj4::$proj)) {
            $this->initTransforms();
            return;
        }

        // Must use a fully-qualified namespace when construction as a string.
        $class = __NAMESPACE__ . '\\Projections\\' . ucfirst($projName);

        if (class_exists($class)) {
            Proj4::$proj[$projName] = new $class();
            $this->loadProjCodeSuccess($projName);
            return;
        }

        // The filename for the projection code
        $filename = dirname(__FILE__) . '/projCode/' . $projName . '.php';

        try {
            Proj4::loadScript($filename);
            $this->loadProjCodeSuccess($projName);
        } catch (Exception $e) {
            $this->loadProjCodeFailure($projName);
        }
    }

    /**
     * Function: loadProjCodeSuccess
     *    Loads any proj dependencies or continue on to final initialization.
     *
     */
    public function loadProjCodeSuccess($projName) {
        if (isset(Proj4::$proj[$projName]->dependsOn) && !empty(Proj4::$proj[$projName]->dependsOn)) {
            $this->loadProjCode(Proj4::$proj[$projName]->dependsOn);
        } else {
            $this->initTransforms();
        }
    }

    /**
     * Function: defsFailed
     *    Report an error in loading the proj file.  Initialization of the Proj
     *    object has failed and the readyToUse flag will never be set.
     *
     */
    public function loadProjCodeFailure($projName) {
        Proj4::reportError("failed to find projection file for: " . $projName);
        //TBD initialize with identity transforms so proj will still work?
    }

    /**
     * Function: checkCodeLoaded
     *    $this is the loadCheck method to see if the projection code is loaded
     *
     */
    public function checkCodeLoaded($projName) {
        return isset(Proj4::$proj[$projName]) && !empty(Proj4::$proj[$projName]);
    }

    /**
     * Function: initTransforms
     *    Finalize the initialization of the Proj object
     *
     */
    public function initTransforms() {
        $this->projection = clone(Proj4::$proj[$this->projName]);
        Proj4::extend($this->projection, $this);
        $this->init();

        // initiate depending class
        if (
            false !== (
            $dependsOn = isset($this->projection->dependsOn) && !empty($this->projection->dependsOn)
                ? $this->projection->dependsOn
                : false
            )
        ) {
            Proj4::extend(Proj4::$proj[$dependsOn], $this->projection) &&
            Proj4::$proj[$dependsOn]->init() &&
            Proj4::extend($this->projection, Proj4::$proj[$dependsOn]);
        }

        $this->readyToUse = true;
    }

    /**
     *
     */
    public function init() {
        $this->projection->init();
    }

    /**
     *
     * @param type $pt
     * @return type
     */
    public function forward($pt) {
        return $this->projection->forward($pt);
    }

    /**
     *
     * @param type $pt
     * @return type
     */
    public function inverse($pt) {
        return $this->projection->inverse($pt);
    }

    /**
     * Function: parseWKT
     * Parses a WKT string to get initialization parameters
     *
     */
    public function parseWKT($wkt) {
        if (false === ($match = preg_match($this->wktRE, $wkt, $wktMatch))) {
            return;
        }

        $wktObject = $wktMatch[1];
        $wktContent = $wktMatch[2];
        $wktTemp = explode(",", $wktContent);

        $wktName = (strtoupper($wktObject) == "TOWGS84") ? "TOWGS84" : array_shift($wktTemp);
        $wktName = preg_replace('/^\"/', "", $wktName);
        $wktName = preg_replace('/\"$/', "", $wktName);

        /*
          $wktContent = implode(",",$wktTemp);
          $wktArray = explode("],",$wktContent);
          for ($i=0; i<sizeof($wktArray)-1; ++$i) {
          $wktArray[$i] .= "]";
          }
         */

        $wktArray = array();
        $bkCount = 0;
        $obj = "";

        foreach ($wktTemp as $token) {
            $bkCount = substr_count($token, "[") - substr_count($token, "]");

            // ???
            $obj .= $token;
            if ($bkCount === 0) {
                array_push($wktArray, $obj);
                $obj = '';
            } else {
                $obj .= ',';
            }
        }

        // do something based on the type of the wktObject being parsed
        // add in variations in the spelling as required

        switch ($wktObject) {
            case 'LOCAL_CS':
                $this->projName = 'identity';
                $this->localCS = true;
                $this->srsCode = $wktName;
                break;
            case 'GEOGCS':
                $this->projName = 'longlat';
                $this->geocsCode = $wktName;
                if (!$this->srsCode) {
                    $this->srsCode = $wktName;
                }
                break;
            case 'PROJCS':
                $this->srsCode = $wktName;
                break;
            case 'GEOCCS':
                break;
            case 'PROJECTION':
                $this->projName = Proj4::$wktProjections[$wktName];
                break;
            case 'DATUM':
                $this->datumName = $wktName;
                break;
            case 'LOCAL_DATUM':
                $this->datumCode = 'none';
                break;
            case 'SPHEROID':
                $this->ellps = $wktName;
                $this->a = floatval(array_shift($wktArray));
                $this->rf = floatval(array_shift($wktArray));
                break;
            case 'PRIMEM':
                // to radians?
                $this->from_greenwich = floatval(array_shift($wktArray));
                break;
            case 'UNIT':
                $this->units = $wktName;
                $this->unitsPerMeter = floatval(array_shift($wktArray));
                break;
            case 'PARAMETER':
                $name = strtolower($wktName);
                $value = floatval(array_shift($wktArray));

                //there may be many variations on the wktName values, add in case
                //statements as required

                switch ($name) {
                    case 'false_easting':
                        $this->x0 = $value;
                        break;
                    case 'false_northing':
                        $this->y0 = $value;
                        break;
                    case 'scale_factor':
                        $this->k0 = $value;
                        break;
                    case 'central_meridian':
                        $this->long0 = $value * Proj4::$common->D2R;
                        break;
                    case 'latitude_of_origin':
                        $this->lat0 = $value * Proj4::$common->D2R;
                        break;
                    case 'more_here':
                        break;
                    default:
                        break;
                }
                break;
            case 'TOWGS84':
                $this->datum_params = $wktArray;
                break;
            //DGR 2010-11-12: AXIS
            case 'AXIS':
                $name = strtolower($wktName);
                $value = array_shift($wktArray);
                switch ($value) {
                    case 'EAST' :
                        $value = 'e';
                        break;
                    case 'WEST' :
                        $value = 'w';
                        break;
                    case 'NORTH':
                        $value = 'n';
                        break;
                    case 'SOUTH':
                        $value = 's';
                        break;
                    case 'UP' :
                        $value = 'u';
                        break;
                    case 'DOWN' :
                        $value = 'd';
                        break;
                    case 'OTHER':
                    default :
                        $value = ' ';
                        break; //FIXME
                }

                if (!$this->axis) {
                    $this->axis = 'enu';
                }

                switch ($name) {
                    case 'X':
                        $this->axis = $value . substr($this->axis, 1, 2);
                        break;
                    case 'Y':
                        $this->axis = substr($this->axis, 0, 1) . $value . substr($this->axis, 2, 1);
                        break;
                    case 'Z':
                        $this->axis = substr($this->axis, 0, 2) . $value;
                        break;
                    default :
                        break;
                }
            case 'MORE_HERE':
                break;
            default:
                break;
        }

        foreach ($wktArray as $wktArrayContent) {
            $this->parseWKT($wktArrayContent);
        }
    }

    /**
     * Function: parseDefs
     * Parses the PROJ.4 initialization string and sets the associated properties.
     *
     */
    public function parseDefs() {
        $this->defData = Proj4::$defs[$this->srsCode];
        #$paramName;
        #$paramVal;

        if (!$this->defData) {
            return;
        }

        $paramArray = explode('+', $this->defData);

        for ($prop = 0; $prop < sizeof($paramArray); $prop++) {
            if (strlen($paramArray[$prop]) == 0) {
                continue;
            }

            $property = explode("=", $paramArray[$prop]);
            $paramName = strtolower($property[0]);

            if (sizeof($property) >= 2) {
                $paramVal = $property[1];
            }

            // trim out spaces
            switch (trim($paramName)) {
                case '':
                    // throw away nameless parameter
                    break;
                case 'title':
                    $this->title = $paramVal;
                    break;
                case 'proj':
                    $this->projName = trim($paramVal);
                    break;
                case 'units':
                    $this->units = trim($paramVal);
                    break;
                case 'datum':
                    $this->datumCode = trim($paramVal);
                    break;
                case 'nadgrids':
                    $this->nagrids = trim($paramVal);
                    break;
                case 'ellps':
                    $this->ellps = trim($paramVal);
                    break;
                case 'a':
                    // semi-major radius
                    $this->a = floatval($paramVal);
                    break;
                case 'b':
                    // semi-minor radius
                    $this->b = floatval($paramVal);
                    break;
                // DGR 2007-11-20
                case 'rf':
                    // inverse flattening rf= a/(a-b)
                    $this->rf = floatval(paramVal);
                    break;
                case 'lat_0':
                    // phi0, central latitude
                    $this->lat0 = $paramVal * Proj4::$common->D2R;
                    break;
                case 'lat_1':
                    $this->lat1 = $paramVal * Proj4::$common->D2R;
                    break;        //standard parallel 1
                case 'lat_2':
                    //standard parallel 2
                    $this->lat2 = $paramVal * Proj4::$common->D2R;
                    break;
                case 'lat_ts':
                    // used in merc and eqc
                    $this->lat_ts = $paramVal * Proj4::$common->D2R;
                    break;
                case 'lon_0':
                    // lam0, central longitude
                    $this->long0 = $paramVal * Proj4::$common->D2R;
                    break;
                case 'alpha':
                    //for somerc projection
                    $this->alpha = floatval($paramVal) * Proj4::$common->D2R;
                    break;
                case 'lonc':
                    //for somerc projection
                    $this->longc = paramVal * Proj4::$common->D2R;
                    break;
                case 'x_0':
                    // false easting
                    $this->x0 = floatval($paramVal);
                    break;
                case 'y_0':
                    // false northing
                    $this->y0 = floatval($paramVal);
                    break;
                case 'k_0':
                    // projection scale factor
                    $this->k0 = floatval($paramVal);
                    break;
                case 'k':
                    // both forms returned
                    $this->k0 = floatval($paramVal);
                    break;
                case 'r_a':
                    // sphere--area of ellipsoid
                    $this->R_A = true;
                    break;
                case 'zone':
                    // UTM Zone
                    $this->zone = intval($paramVal, 10);
                    break;
                case 'south':
                    // UTM north/south
                    $this->utmSouth = true;
                    break;
                case 'towgs84':
                    $this->datum_params = explode(',', $paramVal);
                    break;
                case 'to_meter':
                    // cartesian scaling
                    $this->to_meter = floatval($paramVal);
                    break;
                case 'from_greenwich':
                    $this->from_greenwich = $paramVal * Proj4::$common->D2R;
                    break;
                // DGR 2008-07-09 : if pm is not a well-known prime meridian take
                // the value instead of 0.0, then convert to radians
                case 'pm':
                    $paramVal = trim($paramVal);
                    $this->from_greenwich = Proj4::$primeMeridian[$paramVal] ? Proj4::$primeMeridian[$paramVal] : floatval($paramVal);
                    $this->from_greenwich *= Proj4::$common->D2R;
                    break;
                // DGR 2010-11-12: axis
                case 'axis':
                    $paramVal = trim($paramVal);
                    $legalAxis = "ewnsud";

                    if (
                        strlen(paramVal) == 3
                        && strpos($legalAxis, substr($paramVal, 0, 1)) !== false
                        && strpos($legalAxis, substr($paramVal, 1, 1)) !== false
                        && strpos($legalAxis, substr($paramVal, 2, 1)) !== false
                    ) {
                        $this->axis = $paramVal;
                    } //FIXME: be silent ?
                    break;
                case 'no_defs':
                    break;
                default:
                    // The spec says that unrecognised parameters should be ignored.
                    //alert("Unrecognized parameter: " . paramName);
            } // switch()
        } // for paramArray

        $this->deriveConstants();
    }

    /**
     * Function: deriveConstants
     * Sets several derived constant values and initialization of datum and ellipse parameters.
     *
     */
    public function deriveConstants() {
        if (isset($this->nagrids) && $this->nagrids == '@null') {
            $this->datumCode = 'none';
        }

        if (isset($this->datumCode) && $this->datumCode != 'none') {
            $datumDef = Proj4::$datum[$this->datumCode];

            if (is_array($datumDef)) {
                $this->datum_params = array_key_exists('towgs84', $datumDef) ? explode(',', $datumDef['towgs84']) : null;
                $this->ellps = $datumDef['ellipse'];
                $this->datumName = array_key_exists('datumName', $datumDef) ? $datumDef['datumName'] : $this->datumCode;
            }
        }

        // do we have an ellipsoid?
        if (!isset($this->a)) {
            if (!isset($this->ellps) || strlen($this->ellps) == 0 || !array_key_exists($this->ellps, Proj4::$ellipsoid)) {
                $ellipse = Proj4::$ellipsoid['WGS84'];
            } else {
                $ellipse = Proj4::$ellipsoid[$this->ellps];
            }

            Proj4::extend($this, $ellipse);
        }

        if (isset($this->rf) && !isset($this->b)) {
            $this->b = (1.0 - 1.0 / $this->rf) * $this->a;
        }

        if ((isset($this->rf) && $this->rf === 0) || abs($this->a - $this->b) < Proj4::$common->EPSLN) {
            $this->sphere = true;
            $this->b = $this->a;
        }

        // used in geocentric
        $this->a2 = $this->a * $this->a;

        // used in geocentric
        $this->b2 = $this->b * $this->b;

        // e ^ 2
        $this->es = ($this->a2 - $this->b2) / $this->a2;

        // eccentricity
        $this->e = sqrt($this->es);

        if (isset($this->R_A)) {
            $this->a *= 1. - $this->es * (Proj4::$common->SIXTH + $this->es * (Proj4::$common->RA4 + $this->es * Proj4::$common->RA6));
            $this->a2 = $this->a * $this->a;
            $this->b2 = $this->b * $this->b;
            $this->es = 0.0;
        }

        // used in geocentric
        $this->ep2 = ($this->a2 - $this->b2) / $this->b2;

        if (!isset($this->k0)) {
            //default value
            $this->k0 = 1.0;
        }

        //DGR 2010-11-12: axis
        if (!isset($this->axis)) {
            $this->axis = 'enu';
        }

        $this->datum = new Datum($this);
    }
}

