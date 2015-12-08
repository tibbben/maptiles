<?php

/* maptiles.php - tilecutter for web mapping applications                                                      */

/* Written by Tim Norris - 2008 - tibben@ocf.berkeley.edu                 
   published under the GNU general public license: 
   http://www.gnu.org/licenses/gpl-3.0.en.html                                                                 */

/* Rip tiles for various zoom levels for a web mercator online map interface.
   You must first project all of your data into the web mercator projection
   ESPG 3857 and then create MapServer one map file for all of the zoom levels
   you want to create. 

   NOTE: it is very important that you include the correct extent in the mapfile 
   as the extent for the tile rendering will be calculated from the extent in
   the mapfile

   Once everything is ready run this script from the command line as follows:
   
   php maptiles.php <mapFile.map> <startZoomLevel> <endZoomLevel>

   The tiles will be placed in a folder called 'tiles' in the current directory.
   The directory structure is based on the slippy map tilenames: /zoom/x/y.png                                 */

include ("googleTiles.php"); // get the google tools

/* get the command line args                                                                                   */
$_mapfile = $argv[1];
$_zoomstart = $argv[2];
$_zoomend = $argv[3];

/* local write directories                                                                                     */
$write_dir = "tiles/";
if (!file_exists($write_dir)) { mkdir($write_dir); }

/* get the extent from the mapfile                                                                             */
$_mapfileLines = file($_mapfile);
foreach ($_mapfileLines as $_lineNum => $_mapfileLine) {
  if (strpos($_mapfileLine,"EXTENT")>0) {
    $_extent = explode(" ",$_mapfileLine); 
    $_mapminLong = floatval($_extent[3]);
    $_mapminLat = floatval($_extent[4]);
    $_mapmaxLong = floatval($_extent[5]);
    $_mapmaxLat = floatval($_extent[6]);
    break;
  }
}

print "extent: $_mapminLong $_mapminLat $_mapmaxLong $_mapmaxLat\n";

/* set the bounding tiles for the renders as (MIN_Y, MIN_X, MAX_Y, MAX_X)                                      */
$_minlongFactor = ($_mapminLong+180)/360;
$_maxlongFactor = ($_mapmaxLong+180)/360;
$_minlatFactor = (1-(log(tan(deg2rad($_mapminLat))+(1/cos(deg2rad($_mapminLat))))/pi()))/2;
$_maxlatFactor = (1-(log(tan(deg2rad($_mapmaxLat))+(1/cos(deg2rad($_mapmaxLat))))/pi()))/2;

for ($zoom=$_zoomstart; $zoom<=$_zoomend; $zoom++) {
  $minTileY = floor(pow(2,$zoom)*$_maxlatFactor);
  $minTileX = floor(pow(2,$zoom)*$_minlongFactor);
  $maxTileY = floor(pow(2,$zoom)*$_minlatFactor);
  $maxTileX = floor(pow(2,$zoom)*$_maxlongFactor);
  print "bounds[$zoom] = ($minTileY,$minTileX,$maxTileY,$maxTileX)\n";
  $bounds[$zoom] = array($minTileY,$minTileX,$maxTileY,$maxTileX);
}

// set up local variables
$refZoom     = 12 ;
$muestra     = false ;
$tileSize    = 256 ;
$parwho      = 'Whole' ;
$x           = '' ;
$tile        = array( ) ;
$i           = 0 ;

$ret	=  array() ;
$first	=  array() ;				// First Results Hash
$last	=  array() ;				// Last Results Hash

// set the projection strings for mapServer
$proj_latlng = ms_newprojectionobj('+proj=latlong +ellps=WGS84');
$proj_google = ms_newprojectionobj('+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs');

// start the loop for each zoom level
for ($zoom=$_zoomstart; $zoom<=$_zoomend; $zoom++) {

 // create the zoom level directory if needed
 if (!file_exists($write_dir."/".$zoom)) { mkdir($write_dir."/".$zoom); }

 $value	= Google_Tile_Factors($zoom, $tileSize) ; // Calculate Tile Factors
 $ms_map = ms_newMapObj($_mapfile); // load the correct map

 // get the tile names
 $first['NAMEY'] = $bounds[$zoom][0];
 $first['NAMEX'] = $bounds[$zoom][1]; 
 // Convert Tile Name to Pixels...
 $pixpair = &Google_Tile_to_Pix( $value, $first['NAMEY'], $first['NAMEX'] ) ;
 $first['PYN'] =& $pixpair[0] ;
 $first['PXW'] =& $pixpair[1] ;
 // get lat lon coords
 $coordpair = &PixtoCoordinate( $value, $first['PYN'], $first['PXW'] ) ; // top left
 $first['LATN'] =& $coordpair[0] ;
 $first['LNGW'] =& $coordpair[1] ;

 // get the tile names
 $last['NAMEY'] = $bounds[$zoom][2]; 
 $last['NAMEX'] = $bounds[$zoom][3]; 
 // Convert Tile Name to Pixels...
 $pixpair = &Google_Tile_to_Pix( $value, $last['NAMEY'], $last['NAMEX'] ) ;
 $last['PYS'] =& $pixpair[0];
 $last['PXE'] =& $pixpair[1];
 // get lat lon coords
 $coordpair = &PixtoCoordinate( $value, ($last['PYS']+$tileSize), ($last['PXE']+$tileSize) ) ;
 $last['LATS'] =& $coordpair[0] ;
 $last['LNGE'] =& $coordpair[1] ;

 // print out the results
 print "Zoom: $zoom\n";
 print "First\n";
 foreach ($first as $x => $item)
 {
  print "\t$x: $item\n" ;
 }
 print "Last\n";
 foreach ($last as $x => $item)
 {
  print "\t$x: $item\n" ;
 }
 $gridHeight = ($last['NAMEY']-$first['NAMEY']);
 $gridWidth = ($last['NAMEX']-$first['NAMEX']);
 print "Height: ".$gridHeight."\n";
 print "Width: ".$gridWidth."\n";

 if ($gridHeight*$gridWidth<257) {

  // render entire map and cut tiles if it is small enough

  // calculate extent in meters and set
  $rect_extent = ms_newRectObj();
  $rect_extent->setextent($first['LNGW'],$last['LATS'],$last['LNGE'],$first['LATN']);
  $rect_extent->project($proj_latlng,$proj_google);
  $ms_map->setSize((($gridWidth+1)*$tileSize),(($gridHeight+1)*$tileSize));
  $ms_map->setExtent($rect_extent->minx,$rect_extent->miny,$rect_extent->maxx,$rect_extent->maxy); 

  // draw entire map
  $whole_map = $ms_map->draw();

  $_directoryLevels = floor($zoom/3);

  // cut whole image into tiles
  $ms_map->setSize($tileSize,$tileSize);
  for ($tx=0;$tx<=$gridWidth;$tx++) {
   for ($ty=0;$ty<=$gridHeight;$ty++) {
    $img_map = $ms_map->prepareImage();
    $img_map->pasteImage($whole_map,-1,(-1*$tileSize*$tx),(-1*$tileSize*$ty));

    // create the x directory if needed
    if (!file_exists($write_dir."/".$zoom."/".($tx+$first['NAMEX']))) { mkdir($write_dir."/".$zoom."/".($tx+$first['NAMEX'])); }

    // save tile as /zoom/x/y.png
    $img_map->saveImage($write_dir.$zoom."/".($tx+$first['NAMEX'])."/".($ty+$first['NAMEY']).".png");
    unset($img_map);
   }
  } 

  unset($whole_map); 
  print "done zoom ".$zoom."\n\n";

 } else {

  // break the map into pieces and render tiles in groups

  // calculate a rough grid for smaller pieces

  $quads['XCOUNT'] = round($gridWidth/16);
  $quads['YCOUNT'] = round($gridHeight/16);
  $quads['XDIM'] = round($gridWidth/$quads['XCOUNT']);
  $quads['YDIM'] = round($gridHeight/$quads['YCOUNT']);
  foreach ($quads as $x => $item) print "\t$x: $item\n";
  print "\n";

  // refine grid
  if ($quads['XDIM']*$quads['XCOUNT']<$gridWidth) $first['NAMEX'] = $first['NAMEX'] - ($quads['XDIM']*$quads['XCOUNT']-$gridWidth);
  if ($quads['YDIM']*$quads['YCOUNT']<$gridHeight) $first['NAMEY'] = $first['NAMEY'] - ($quads['YDIM']*$quads['YCOUNT']-$gridHeight);
  if ($quads['XDIM']*$quads['XCOUNT']>$gridWidth) $last['NAMEX'] = $last['NAMEX'] + ($quads['XDIM']*$quads['XCOUNT']-$gridWidth);
  if ($quads['YDIM']*$quads['YCOUNT']>$gridHeight) $last['NAMEY'] = $last['NAMEY'] + ($quads['YDIM']*$quads['YCOUNT']-$gridHeight);

  if ($quads['YDIM']*$quads['YCOUNT']<$gridHeight || $quads['XDIM']*$quads['XCOUNT']<$gridWidth) {
   // if refined adjust start tile
   $pixpair = &Google_Tile_to_Pix( $value, $first['NAMEY'], $first['NAMEX'] ) ;
   $first['PYN'] =& $pixpair[0] ;
   $first['PXW'] =& $pixpair[1] ;
   $coordpair = &PixtoCoordinate( $value, $first['PYN'], $first['PXW'] ) ; // new top left
   $first['LATN'] =& $coordpair[0] ;
   $first['LNGW'] =& $coordpair[1] ;
   print "NEW First\n";
   foreach ($first as $x => $item) print "\t$x: $item\n";
   print "SAME last\n";
   foreach ($last as $x => $item) print "\t$x: $item\n";
  }
  if ($quads['YDIM']*$quads['YCOUNT']>$gridHeight || $quads['XDIM']*$quads['XCOUNT']>$gridWidth) {
   // if refined adjust end tile
   $pixpair = &Google_Tile_to_Pix( $value, $last['NAMEY'], $last['NAMEX'] ) ;
   $last['PYN'] =& $pixpair[0] ;
   $last['PXW'] =& $pixpair[1] ;
   $coordpair = &PixtoCoordinate( $value, $last['PYN']+$tileSize, $last['PXW']+$tileSize ) ; // new bottom right
   $last['LATS'] =& $coordpair[0] ;
   $last['LNGE'] =& $coordpair[1] ;
   print "NEW Last\n";
   foreach ($last as $x => $item) print "\t$x: $item\n";
   print "SAME First\n";
   foreach ($first as $x => $item) print "\t$x: $item\n";
  }

  $gridHeight = ($last['NAMEY']-$first['NAMEY']);
  $gridWidth = ($last['NAMEX']-$first['NAMEX']);
  print "Height: ".$gridHeight."\n";
  print "Width: ".$gridWidth."\n";

  // calculate individual extents for pieces
  $qc=0;
  for ($qx=0;$qx<$quads['XCOUNT'];$qx++) {
   for ($qy=0;$qy<$quads['YCOUNT'];$qy++) {

    // top left tile in quadrangle
    $quadFirst[$qc]['NAMEX'] = $first['NAMEX'] + $quads['XDIM']*$qx - 1;
    $quadFirst[$qc]['NAMEY'] = $first['NAMEY'] + $quads['YDIM']*$qy - 1;
    $pixpair = &Google_Tile_to_Pix( $value, $quadFirst[$qc]['NAMEY'], $quadFirst[$qc]['NAMEX'] ) ;
    $quadFirst[$qc]['PYN'] =& $pixpair[0] ;
    $quadFirst[$qc]['PXW'] =& $pixpair[1] ;
    $coordpair = &PixtoCoordinate( $value, $quadFirst[$qc]['PYN'], $quadFirst[$qc]['PXW'] ) ; // top left
    $quadFirst[$qc]['LATN'] =& $coordpair[0] ;
    $quadFirst[$qc]['LNGW'] =& $coordpair[1] ;
    $coordpair = &PixtoCoordinate( $value, $quadFirst[$qc]['PYN']+$tileSize, $quadFirst[$qc]['PXW']+$tileSize ) ; // top left
    $quadFirst[$qc]['LATS'] =& $coordpair[0] ;
    $quadFirst[$qc]['LNGE'] =& $coordpair[1] ;

    // bottom right tile in quadrangle
    if (($first['NAMEX']+ $quads['XDIM']*(1+$qx))<$last['NAMEX']) {
     $quadLast[$qc]['NAMEX'] = $first['NAMEX']+ $quads['XDIM']*($qx+1) + 1;
    } else {
     $quadLast[$qc]['NAMEX'] = $last['NAMEX'] + 1;
    } 
    if (($first['NAMEY']+ $quads['YDIM']*(1+$qy))<$last['NAMEY']) {
     $quadLast[$qc]['NAMEY'] = $first['NAMEY']+ $quads['YDIM']*($qy+1) + 1;
    } else {
     $quadLast[$qc]['NAMEY'] = $last['NAMEY'] + 1;
    } 
    $pixpair = &Google_Tile_to_Pix( $value, $quadLast[$qc]['NAMEY'], $quadLast[$qc]['NAMEX'] ) ;
    $quadLast[$qc]['PYN'] =& $pixpair[0] ;
    $quadLast[$qc]['PXW'] =& $pixpair[1] ;
    $coordpair = &PixtoCoordinate( $value, $quadLast[$qc]['PYN'], $quadLast[$qc]['PXW'] ) ; // top left
    $quadLast[$qc]['LATN'] =& $coordpair[0] ;
    $quadLast[$qc]['LNGW'] =& $coordpair[1] ;
    $coordpair = &PixtoCoordinate( $value, $quadLast[$qc]['PYN']+$tileSize, $quadLast[$qc]['PXW']+$tileSize ) ; // top left
    $quadLast[$qc]['LATS'] =& $coordpair[0] ;
    $quadLast[$qc]['LNGE'] =& $coordpair[1] ;
    $qc++;
   }
  }

  $_pnum = 0;
  $_directoryLevels = floor($zoom/3);
  $paths = array();
  for ($j=3;$j<=$_directoryLevels;$j++) $paths[$j][0] = 'none';

  // draw the quads and split into tiles
  print "start draw of ".$qc." quads for zoom level ".$zoom."\n";

  if ($zoom==16) {$istart=0;} else {$istart=0;}
  for ($i=$istart;$i<$qc;$i++) {

   // calculate extent in meters and set
   $rect_extent = ms_newRectObj();
   $rect_extent->setextent($quadFirst[$i]['LNGW'],$quadLast[$i]['LATS'],$quadLast[$i]['LNGE'],$quadFirst[$i]['LATN']);
   $rect_extent->project($proj_latlng,$proj_google);
   print "zoom: ".$zoom." - quad ".($i+1)." of ".$qc."\n";
   $ms_map->setSize((($quads['XDIM']+3)*$tileSize),(($quads['YDIM']+3)*$tileSize));
   $ms_map->setExtent($rect_extent->minx,$rect_extent->miny,$rect_extent->maxx,$rect_extent->maxy); 

   // draw the quadrangle
   $whole_map = $ms_map->draw();

   // cut whole quadrangle image into tiles
   $ms_map->setSize($tileSize,$tileSize);
   for ($tx=1;$tx<=($quads['XDIM']);$tx++) {
    for ($ty=1;$ty<=($quads['YDIM']);$ty++) {
     $img_map = $ms_map->prepareImage();
     $img_map->pasteImage($whole_map,-1,(-1*$tileSize*$tx),(-1*$tileSize*$ty));

     // create the x directory if needed
     if (!file_exists($write_dir."/".$zoom."/".($tx+$quadFirst[$i]['NAMEX']))) { mkdir($write_dir."/".$zoom."/".($tx+$quadFirst[$i]['NAMEX'])); }

     // save the tile as zoom/x/y.png
     $img_map->saveImage($write_dir.$zoom."/".($tx+$quadFirst[$i]['NAMEX'])."/".($ty+$quadFirst[$i]['NAMEY']).".png");
     unset($img_map);
    }
   }  
   
   unset($whole_map); 

  } 
  print "done zoom ".$zoom."\n\n";

 }

}

die;

?>
