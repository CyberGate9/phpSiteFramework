<?php
/**
* Fitzwilliam Museum specific sub-functions
*
* OPTIONAL (not required for normal SF installations)
*
* This file contains sub function used specifically at The Fitzwilliam Museum
*
*
* @package SiteFramework
* @author Shaun Osborne (smo30@cam.ac.uk)
* @link http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/
* @access public 
* @license http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/licences.html GPL
* @copyright The Fitzwilliam Museum, University of Cambridge, UK
* @version n/a
*/

/* globals for opac */
$SF_opacdrivepath='e:/wwwopac/opac/';
$SF_opacwebpath='http://'.$_SERVER['HTTP_HOST'].'/opac/';


function SF_GenerateObjectRelatedResources($objnumber,$excluderesourcetype='',$returnvalue=false)
{
  global $SF_opacdrivepath;
  global $SF_opacwebpath;
  
  $opacurl=$SF_opacwebpath.'wwwopac.exe?database=collect>intern&brieffields=IN,AG,OB,OC,collection,LT,ST&search=IN=';
  $returnstring='';
  $resourcefile = file($SF_opacdrivepath.'resources.csv');

  #get the objects xml
  $results = file_get_contents($opacurl.$objnumber);
  if(!$results){$returnstring="ERROR(load_related_resources): file_get_contents failed!"; return($returnstring);}
  $xml = simplexml_load_string($results);
  /* if we get no result from opac simply return */
  if(!strcmp('0',$xml->diagnostic->hits))
  {return;}
  
  $resource_keys = array(
      $xml->recordList->record->object_number, 
      $xml->recordList->record->object_name->term, 
      $xml->recordList->record->object_category->term, 
      $xml->recordList->record->collection->term,
      $xml->recordList->record->location_type->term,
      $xml->recordList->record->location->term
      ); 
  
  foreach($resourcefile as $resourceline)
  {
    $resource = split(',',$resourceline);
    foreach($resource_keys as $resource_key) 
    {
     if($resource[0] == trim($resource_key) and $resource[1] != $excluderesourcetype)
     {
      $returnstring = $returnstring . "<li><a href='" . $resource[2] . "'>" . $resource[1] . "</a></li>";
     }
    }
  }
  $returnstring = '<li><a href="'.$SF_opacwebpath.'catalogue_detail.php?search=IN='.$objnumber.'">Catalogue Record</a></li>'.$returnstring;
  /* this is for location display - currently turned off 
  if(!strcmp($resource_keys[4],'gallery') and strcmp($resource_keys[5],''))
  {
  $returnstring = $returnstring.'<li><a href="http://www.fitzmuseum.cam.ac.uk/visitor_info/FRM/index-floorplan.htm">Gallery Floorplan</a> (this object on display in '.$resource_keys[5].')</li>';
  }
  */
  $returnstring = '<p>Related Resources:</p><ul>'.$returnstring.'</ul>';
  if($returnvalue)
    {return ($returnstring);}
  else
    {echo $returnstring;}
}
   
?>
