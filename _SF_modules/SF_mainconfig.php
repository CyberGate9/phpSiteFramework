<?php
/**
* This file is Site Framework's global configuration file
*
* Edit at least $SF_sitewebpath
*
* @package SiteFramework
* @author Shaun Osborne (smo30@cam.ac.uk)
* @link http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/
* @license http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/licences.html GPL
* @access public 
* @copyright The Fitzwilliam Museum, University of Cambridge, UK
*/
        

/****************************************************************************
Site Framework (SF) Main Configuration Settings
note: leading and trailing slashes should be used  (just / for root is OK though)
*/
$SF_sitewebpath='/testsite/';

/* optionals */
$SF_sitetitle='SiteTitle';
$SF_contentpreprocessor=false;
$SF_defaultindexfile='index.html';





/* end of Main Configuration Settings */

/****************************************************************************
Global derived configuration values - shouldn't need to change these */
$SF_modulesdirname='_SF_modules/';
$SF_moduleswebpath=$SF_sitewebpath.$SF_modulesdirname;
$SF_modulesdrivepath=$_SERVER['DOCUMENT_ROOT'].$SF_moduleswebpath;
$SF_sitedrivepath=$_SERVER['DOCUMENT_ROOT'].$SF_sitewebpath;
$SF_subsitewebpath=$SF_sitewebpath;
$SF_subsitedrivepath=$SF_sitedrivepath;
$SF_phpselfdrivepath=$_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'];
$SF_sitelogo=$SF_moduleswebpath.'images/sflogo_sml.jpg';
/****************************************************************************
Global default values - shouldn't need to change these */

/* data files */
$defaultmenudatafile=$SF_modulesdrivepath.'SF_config_menu.csv';
$defaultdirconfigfile=$SF_modulesdrivepath.'SF_config_dir.csv';
$defaultsiteconfigfile=$SF_modulesdrivepath.'SF_config_site.csv';

/* site default header, footer and css */
$defaultheaderfile=$SF_modulesdrivepath.'SF_defaultheader.html';
$defaultfooterfile=$SF_modulesdrivepath.'SF_defaultfooter.html';
$defaultcssfile=$SF_moduleswebpath.'SF_default.css';

/*metadata files if used*/
$defaultmetadatafile=$SF_modulesdrivepath."SF_defaultmetadata.html";

/* print view files */
$defaultprintheaderfile=$SF_modulesdrivepath.'SF_defaultprintheader.html';
$defaultprintfooterfile=$SF_modulesdrivepath.'SF_defaultprintfooter.html';
$defaultprintcssfile=$SF_moduleswebpath.'SF_defaultprint.css';

$menutoplevelidentifier='_toplevel';

?>
