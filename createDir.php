<?php

/* createDir.php - creates directory structure for tileCutterDir.php      */

/* Written by Tim Norris - 2008 - tibben@ocf.berkeley.edu                 
   published under the GNU general public license: 
   http://www.gnu.org/licenses/gpl-3.0.en.html                            */

/* Create a directory structure to rip tiles into. You may need to 
   create the very top level folders manually depending at what level
   you start this script

   Once everything is ready it is best to run this script from 
   the command line as a php application.

   TODO: fix this script and include directly in the tileCutter           */


/* **************************************************************
                    ENTER THE DRAW OPTIONS HERE
   change the variables to meet your draw needs, save the file,
   and then run at the command line as: 
   >php createDir.php
   **************************************************************         */

$_zoomstart = 14;                      /* start drawing at this zoom level    */
$_zoomend = 20;                        /* end drawing at this level           */
$_area = 1;                            /* area to be drawn:
                                          1: Some area                        */
$_type = 1;                            /* 1: regular map
                                          2: hybrid map                       */
$_renderLayers = array(8,11,14,17);    /* list of layers to actually draw     */

// local write directories
$write_dir = "<your_tile_directory>";
$_statfile = "<folder_location_for_render_stats>";
$_errorfile = "<folder_location_for_render_log>\\tileCutter.log";
$_mapfiledir = "<folder_location_of_mapfiles>";

// set map type (including the top level folder name for the tiles)
if ($_type == 1) { $s = ''; $write_dir = $write_dir."tiles/"; } else { $s = 's'; $write_dir = $write_dir."stiles/"; }

// the bounding tiles for the renders as (MIN_Y, MIN_X, MAX_Y, MAX_X)
$bounds = array(); // the bounds as tile names for the render (zoom)(f_y,f_x, l_y, l_x)
switch($_area) {
  case 1:
        /* each zoom level rendered needs an array defined as written 
           these bounds can be calculated using the BoundsCalculator.xlsx
           TODO: build the BoundsCalculator into this script so that these 
                 extents are automatically calculated from WGS84 lat long coords */
	$bounds[<zoomLevel>] = array(<MIN_Y>,<MIN_X>,<MAX_Y>,<MAX_X>);
}

/* **************************************************************
                        END OF DRAW OPTIONS
   ************************************************************** */

include ("googleTiles.php"); // get the google tools

// set up a file for debugging feedback
$errors = fopen($_errorfile, "a");
fwrite ($errors," ----------------------------------------------------------------\n");

// set up local variables
$refZoom     = 12 ;
$muestra     = false ;
$tileSize    = 256 ;
$parwho      = 'Whole';
$x           = '' ;
$tile        = array( ) ;
$i           = 0 ;
$html_br     = '' ;
$html_column_end = '' ;
$html_column_start = '' ;
$html_row_end = '' ; 
$html_table_end = '' ;

$ret	=  array() ;
$first	=  array() ;				// First Results Hash
$last	=  array() ;				// Last Results Hash

// set the projection strings for mapServer
$proj_latlng = ms_newprojectionobj('+proj=latlong +ellps=WGS84');
$proj_google = ms_newprojectionobj('+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs');

// start the loop for each zoom level
for ($zoom=$_zoomstart; $zoom<=$_zoomend; $zoom++) {

 if (in_array($zoom,$_renderLayers)) {

 $value	= Google_Tile_Factors($zoom, $tileSize) ; // Calculate Tile Factors

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
 print "Zoom: $zoom$html_br\n";
 print "First$html_br\n";
 foreach ($first as $x => $item)
 {
  print "\t$x: $item$html_br\n" ;
 }
 print "$html_column_end$html_column_start";
 print "Last$html_br\n";
 foreach ($last as $x => $item)
 {
  print "\t$x: $item$html_br\n" ;
 }
 print "$html_row_end$html_table_end";
 $gridHeight = ($last['NAMEY']-$first['NAMEY']);
 $gridWidth = ($last['NAMEX']-$first['NAMEX']);
 print "Height: ".$gridHeight."$html_br\n";
 print "Width: ".$gridWidth."$html_br$html_br\n";

  // render entire map and cut tiles if it is small enough

  $_directoryLevels = floor($zoom/3);

  // cut whole image into tiles
  for ($tx=0;$tx<=$gridWidth;$tx++) {
   for ($ty=0;$ty<=$gridHeight;$ty++) {
    // $path must be set if not starting at zoom level 1
    $path = "";
    // j=4 means the top level is the fourth level directory
    for ($j=4;$j<=$_directoryLevels;$j++) { 
       $zl = $j*3-1; 
       $nx = floor(($tx+$first['NAMEX'])/(pow(2,$zoom-$zl))); 
       $ny = floor(($ty+$first['NAMEY'])/(pow(2,$zoom-$zl))); 
       $lpath = $zl."_".$nx."_".$ny;
       $path = $path.$lpath."/";
    }
     fwrite ($errors,$write_dir.$path.$zoom."_".($tx+$first['NAMEX'])."_".($ty+$first['NAMEY'])."\n");
     mkdir($write_dir.$path.$zoom."_".($tx+$first['NAMEX'])."_".($ty+$first['NAMEY']));
   }
  } 

  print "done zoom ".$zoom."\n\n";

  }

}

die;

?>
