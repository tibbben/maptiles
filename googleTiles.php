<?php

/* googleTiles.php is adapted by Tim Norris 07/18/2008 from the perl script googleTiles.pl 
   published under the GNU general public license: http://www.gnu.org/licenses/gpl-3.0.en.html

   The original perl script was found here: http://www.usnaviguide.com/google-tiles.htm
   The documentation below is from the original perl script
 
 Calculate tile characteristics given a bounding box of coordinates and a zoom...
 Author. John D. Coryat 01/2008...
 USNaviguide LLC.
 Published under Apache 2.0 license.
 Adapted from: Google Maps API Javascript...

 In order to correctly locate objects of interest on a Custom Map Overlay Google Maps, 
 the characteristics of each tile to build are required.

 Google_Tiles					# Calculate all tiles for a bounding box and zoom
 Google_Tile_Factors				# Calculate the factors needed ( Zoom, Tilesize )
 Google_Tile_Calc				# Calculate a single tile features from a tile name and zoom
 Google_Tile_to_Pix				# Calculate tile name to pixel
 Google_Coord_to_Pix				# Calculate coordinate to Pixel
 Google_Pix_to_Tile				# Calculate a tile name from a pixel location and zoom */

/* Google_Tiles:
 Call as: <array of Hashes> = &Google_Tiles(<LatitudeS>, <LongitudeW>, <LatitudeN>, <LongitudeE>, <Zoom>, [<option: tileSize>], [<option: Partial/Whole>]) ;
 Partial/Whole option: (Default: Partial)
	Partial: Include the edge to create partial tiles
       Whole: Include only tiles that are contained by the bounds

          Returned Array Specifications:
            Each element is a reference to a Hash:
              NAMEY - Tile Name y
              NAMEX - Tile Name x
              PYS - Pixel South
              PXW - Pixel West
              PYN - Pixel North
              PXE - Pixel East
              LATS - South Latitude
              LNGW - West Longitude
              LATN - North Latitude
              LNGE - East Longitude

          Note: X is width, Y is height...                               */

function Google_Tiles($latS,$lngW,$latN,$lngE,$zoom,$tileSize,$parwho) {

 $ty		= 0 ;
 $tx		= 0 ;
 $ret	=  array() ;
 $first	=  array() ;				// First Results Hash
 $last	=  array() ;				// Last Results Hash

 $value	= Google_Tile_Factors($zoom, $tileSize) ; # Calculate Tile Factors

 // NW: Convert Coordinates to Pixels...

 $pixpair = &Google_Coord_to_Pix( $value, $latN, $lngW );
 print "firstpix: ".$pixpair[0]." : ".$pixpair[1]."\n";
 $first['NORTH'] =& $pixpair[0] ;
 $first['WEST'] =& $pixpair[1] ;

 // Convert Pixels to Tile Name...

 $namepair = &PixtoTileName( $value, $first['NORTH'], $first['WEST'], 'N', 'W', $parwho ) ;
 print "firstName: ".$namepair[0]." : ".$namepair[1]."\n";
 $first['NAMEY'] =& $namepair[0];
 $first['NAMEX'] =& $namepair[1];

 // SE: Convert Coordinates to Pixels...

 $pixpair = &Google_Coord_to_Pix( $value, $latS, $lngE ) ;
 print "lastpix: ".$pixpair[0]." : ".$pixpair[1]."\n";
 $last['SOUTH'] =& $pixpair[0] ;
 $last['EAST'] =& $pixpair[1] ;

 // Convert Pixels to Tile Name...

 $namepair = &PixtoTileName( $value, $last['SOUTH'], $last['EAST'], 'S', 'E', $parwho ) ;
 print "lastName: ".$namepair[0]." : ".$namepair[1]."\n";
 $last['NAMEY'] =& $namepair[0];
 $last['NAMEX'] =& $namepair[1];

 // Calculate tile values for all tiles...

 if ( $first['NAMEX'] > $last['NAMEX'] )			// Across the date line
 {
  for ( $ty = $first['NAMEY'] ; $ty <= $last['NAMEY'] ; $ty++ )
  {
   for ( $tx = $first['NAMEX'] ; $tx <= $$value['max'] ; $tx++ )
   {
    $ret[] = &Google_Tile_Calc( $value, $ty, $tx) ;
   }
   for ( $tx = 0 ; $tx <= $last['NAMEX'] ; $tx++ )
   {
    $ret[] = &Google_Tile_Calc( $value, $ty, $tx) ;
   }
  }
 } else
 {
  for ( $ty = $first['NAMEY'] ; $ty <= $last['NAMEY'] ; $ty++ )
  {
   for ( $tx = $first['NAMEX'] ; $tx <= $last['NAMEX'] ; $tx++ )
   {
    $ret[] = &Google_Tile_Calc( $value, $ty, $tx) ;
   }
  }
 }

 $ret[0]['NORTH'] = $first['NORTH'] ;
 $ret[0]['WEST'] = $first['WEST'] ;

 $totalTile = count($ret)-1;

 $ret[$totalTile]['SOUTH'] = $last['SOUTH'] ;
 $ret[$totalTile]['EAST'] = $last['EAST'] ;
 
 return ( $ret ) ;
}

// Calculate Tile Factors...

function Google_Tile_Factors ($zoom,$tileSize) {

 $value	= array() ;

 // Calculate Values...

 $value['zoom']	= $zoom ;
 $value['PI']	= 3.1415926536 ;
 $value['bc']	= 2 * $value['PI'] ;
 $value['Wa']	= $value['PI'] / 180 ;
 $value['cp']	= pow(2,($value['zoom'] + 8)) ;
 $value['max']	= pow(2,$value['zoom']) - 1 ;		// Maximum Tile Number
 $value['pixLngDeg']= $value['cp'] / 360;
 $value['pixLngRad']= $value['cp'] / $value['bc'] ;
 $value['bmO']	= $value['cp'] / 2 ;
 $value['tileSize'] = $tileSize ;

 return $value ;
}

// Calculate tile values from Name...

function Google_Tile_Calc ($value,$nameY,$nameX) {
 $result	= array() ;
 $result['NAMEY'] = $nameY ;
 $result['NAMEX'] = $nameX ;

 # Convert Tile Name to Pixels...

 $pixpair = &Google_Tile_to_Pix( $value, $result['NAMEY'], $result['NAMEX'] ) ;
 $result['PYN'] =& $pixpair[0] ;
 $result['PXW'] =& $pixpair[1] ;

 # Convert Pixels to Coordinates (Upper Left Corner)...

 $coordpair = &PixtoCoordinate( $value, $result['PYN'], $result['PXW'] ) ;
 $result['LATN'] =& $coordpair[0] ;
 $result['LNGW'] =& $coordpair[1] ;

 $result['PYS'] = $result['PYN'] + 255 ;
 $result['PXE'] = $result['PXW'] + 255 ;

 # Convert Pixels to Coordinates (Lower Right Corner)...

 $coordpair = &PixtoCoordinate( $value, $result['PYS'], $result['PXE'] ) ;
 $result['LATS'] =& $coordpair[0] ;
 $result['LNGE'] =& $coordpair[1] ;

 return $result ;
}

// Calculate a tile name from a pixel location and zoom...

function Google_Pix_to_Tile ($value,$ty,$tx) {

 // Convert Pixels to Tile Name...

 $res = array(PixtoTileName( $value, $ty, $tx, 'N', 'W', 'Partial' )) ;

 return $res ;

}

// Translate a coordinate to a pixel location...

function Google_Coord_to_Pix ($value,$lat,$lng) {
 
 $e		= 0 ;

 $d[1] = sprintf("%0.0f", $value['bmO'] + $lng * $value['pixLngDeg'] ) ;

 $e = sin($lat * $value['Wa']) ;

 if( $e > 0.99999 )
 {
  $e = 0.99999 ;
 }

 if( $e < -0.99999 )
 {
  $e = -0.99999 ;
 }

 $d[0] = sprintf("%0.0f", $value['bmO'] + 0.5 * log((1 + $e) / (1 - $e)) * (-1) * $value['pixLngRad'] ) ;

 return ($d) ;
}

// Translate a pixel location to a tile name...

function PixtoTileName ($value,$y,$x,$yd,$xd,$parwho) {

 $yn		= 0 ;					// Y Name
 $xn		= 0 ;					// X Name

 $yn = intval( $y / $value['tileSize'] ) ;		// Round Down
 $xn = intval( $x / $value['tileSize'] ) ;		// Round Down

 if ( $parwho != 'Partial' )
 {
  if ( $yd == 'N' )
  {
   $yn++ ;
  } else
  {
   $yn-- ;
  }
  if ( $xd == 'W' )
  {
   $xn++ ;
  } else
  {
   $xn-- ;
  }
 }

 // Make sure tile numbers are sane...

 if ( $yn > $value['max'] )
 {
  $yn = $value['max'] ;
 } elseif ( $yn < 0 )
 {
  $yn = 0 ;
 }

 if ( $xn > $value['max'] )
 {
  $xn = $value['max'] ;
 } elseif ( $xn < 0 )
 {
  $xn = 0 ;
 }

 return array ($yn, $xn) ; 
}

// Translate a tile name to a pixel location...

function Google_Tile_to_Pix ($value,$y,$x) {

 return array (sprintf("%0.0f", $y * $value['tileSize'] ), sprintf("%0.0f", $x * $value['tileSize'] )) ; 

}

// Translate a pixel location to a coordinate...

function PixtoCoordinate ($value,$y,$x) {

 $d = array();

 $e = (($y-$value['bmO'])/$value['pixLngRad'])*(-1);

 $d[1] = sprintf("%0.6f",($x-$value['bmO'])/$value['pixLngDeg']) ;
 $d[0] = sprintf("%0.6f", (2 * atan(exp($e)) - $value['PI']/ 2) / $value['Wa']) ;

 return $d;

}

?>
