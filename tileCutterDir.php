<?php

/* tilecutter.php - tilecutter for google style map tiles                      */

/* Written by Tim Norris - 2008 - tibben@ocf.berkeley.edu                      */

/* Rip tiles for various zoom levels for the google maps interface.
   You must first project all of your data into the google projection
   and then create MapServer map files for the zoom levels that you 
   want to create. Once everything is ready it is best to run this
   as a command line application.                                              */


/* **************************************************************
                    ENTER THE DRAW OPTIONS HERE
   change the variables to meet your draw needs, save the file,
   and then run at the command lines as: 
   > php tileCutterDir.php
   **************************************************************              */

$_zoomstart = 15;                     /* start drawing at this zoom level                                      */
$_zoomend =15;                        /* end drawing at this level                                             */
$_area = 1;                           /* area to be drawn:
                                         1: some defined area (see below)                                      */
$_type = 1;                           /* 1: regular map
                                         2: hybrid map                                                         */
$_renderLayers = array(9,10,11,12,13,14,15,16,17,18,19,20);  /* list of layers to actually draw                */

// local write directories
$write_dir = "<your_tile_directory>";
$_statfile = "<folder_location_for_render_stats>";
$_errorfile = "<folder_location_for_render_log>\\tileCutter.log";
$_mapfiledir = "<folder_location_of_mapfiles>";

// set map type (including the top level folder name for the tiles)
if ($_type == 1) { $s = ''; $write_dir = $write_dir."tiles/"; } else { $s = 's'; $write_dir = $write_dir."stiles/"; }

// the bounding tiles for the renders as (MIN_Y, MIN_X, MAX_Y, MAX_X)
$bounds = array(); // the bounds as tile names for the render (zoom)(f_y,f_x,l_y,l_x)
switch($_area) {
  case 1:
        /* each zoom level rendered needs an array defined as written 
           these bounds can be calculated using the BoundsCalculator.xlsx
           TODO: build the BoundsCalculator into this script so that these 
                 extents are automatically calculated from WGS84 lat long coords */
	$bounds[<zoomLevel>] = array(<MIN_Y>,<MIN_X>,<MAX_Y>,<MAX_X>);
        break;
}

// NOTE: don't forget to make yout MapServer MapFiles (see their documentation) enter the <base> name here
$mapFileBase = "<your_MapFile_baseName>"; 

/* **************************************************************
                        END OF DRAW OPTIONS
   ************************************************************** */

include ("googleTiles.php"); // get the google tools

// set up a file for debugging feedback
$errors = fopen($_errorfile, "a");
fwrite ($errors," ----------------------------------------------------------------\n");

// set up local variables
$refZoom     = 12;
$muestra     = false;
$tileSize    = 256 ;
$parwho      = 'Whole';
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

 if (in_array($zoom,$_renderLayers)) {

 $value	= Google_Tile_Factors($zoom, $tileSize) ; // Calculate Tile Factors
 $ms_map = ms_newMapObj($_mapfiledir.$mapFileBase.$s.'_'.$zoom.'.map'); // load the correct map

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
 print "$htm_column_end$htm_column_start";
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
    $path = "";
    // j=4 sets the top level directory to the fourth level
    for ($j=4;$j<=$_directoryLevels;$j++) { 
       $zl = $j*3-1; 
       $nx = floor(($tx+$first['NAMEX'])/(pow(2,$zoom-$zl))); 
       $ny = floor(($ty+$first['NAMEY'])/(pow(2,$zoom-$zl))); 
       $lpath = $zl."_".$nx."_".$ny;
       $path = $path.$lpath."/";
    }
    $img_map->saveImage($write_dir.$path.$zoom."_".($tx+$first['NAMEX'])."_".($ty+$first['NAMEY']).".png");
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
  foreach ($quads as $x => $item) print "\t$x: $item$html_br\n";
  print "$html_br\n";

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
   print "NEW First$html_br\n";
   foreach ($first as $x => $item) print "\t$x: $item$html_br\n";
   print "SAME last$html_br\n";
   foreach ($last as $x => $item) print "\t$x: $item$html_br\n";
  }
  if ($quads['YDIM']*$quads['YCOUNT']>$gridHeight || $quads['XDIM']*$quads['XCOUNT']>$gridWidth) {
   // if refined adjust end tile
   $pixpair = &Google_Tile_to_Pix( $value, $last['NAMEY'], $last['NAMEX'] ) ;
   $last['PYN'] =& $pixpair[0] ;
   $last['PXW'] =& $pixpair[1] ;
   $coordpair = &PixtoCoordinate( $value, $last['PYN']+$tileSize, $last['PXW']+$tileSize ) ; // new bottom right
   $last['LATS'] =& $coordpair[0] ;
   $last['LNGE'] =& $coordpair[1] ;
   print "NEW Last$html_br\n";
   foreach ($last as $x => $item) print "\t$x: $item$html_br\n";
   print "SAME First$html_br\n";
   foreach ($first as $x => $item) print "\t$x: $item$html_br\n";
  }

  $gridHeight = ($last['NAMEY']-$first['NAMEY']);
  $gridWidth = ($last['NAMEX']-$first['NAMEX']);
  print "Height: ".$gridHeight."$html_br\n";
  print "Width: ".$gridWidth."$html_br$html_br\n";

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
  fwrite ($errors, "start draw of ".$qc." quads\n");
  print "start draw of ".$qc." quads for zoom level ".$zoom."\n";

  if ($zoom==16) {$istart=0;} else {$istart=0;}
  for ($i=$istart;$i<$qc;$i++) {

   // calculate extent in meters and set
   $rect_extent = ms_newRectObj();
   $rect_extent->setextent($quadFirst[$i]['LNGW'],$quadLast[$i]['LATS'],$quadLast[$i]['LNGE'],$quadFirst[$i]['LATN']);
   $rect_extent->project($proj_latlng,$proj_google);
   fwrite ($errors, "draw quad ".$i." of ".$qc."\n"); 
   print "zoom: ".$zoom." - quad ".($i+1)." of ".$qc."\n";
   fwrite ($errors, "extent: ".(($quads['XDIM'])*$tileSize)." ".(($quads['YDIM'])*$tileSize)."\n");
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
     $path = "";
     // j=4 sets the top level directory to the fourth level
     for ($j=4;$j<=$_directoryLevels;$j++) { 
        $zl = $j*3-1; 
        $nx = floor(($tx+$quadFirst[$i]['NAMEX'])/(pow(2,$zoom-$zl))); 
        $ny = floor(($ty+$quadFirst[$i]['NAMEY'])/(pow(2,$zoom-$zl))); 
        $lpath = $zl."_".$nx."_".$ny;
        $path = $path.$lpath."/";
     }
     $img_map->saveImage($write_dir.$path.$zoom."_".($tx+$quadFirst[$i]['NAMEX'])."_".($ty+$quadFirst[$i]['NAMEY']).".png");
     unset($img_map);
    }
   }  
   
   unset($whole_map); 

  } 
  print "done zoom ".$zoom."\n\n";

 }

 }

}

die;

?>
