<?php
/**
* This file is Site Framework's main module
*
* !!!Important!!! 
* this module should never output anything itself when in production
*
* Note: Shouldn't need to change values in here, default settings for SF in:
*
* 1) SF_mainconfig.php - for SF directory paths etc
*
* 2) SF_config_site.csv (csv text file) for defining site and subsite 'config_dir' files
*
* 3) SF_config_dir.csv (csv text file) for per directory configuration (menu,css,header,footer)
*
* 4) SF_config_menu.csv (csv text file) for menu configuration data
*
* CHANGE HISTORY
*
* 1.3  (26Oct05)  cleaned up querystring logic in SF_autoappend.php, added debug=x querystring ability and made changes
*                 required for that to work
*
* 1.2c  (25Oct2005) if no order information now given in menu config they will still display (just in no particular order)
*
*                   fixed bug in SF_GenerateContentFromURL where it wasn't cleaning http path properly
*
* 1.2b (24Oct2005) fixed sf_f=force in autoprepend.php to properly handle updating cached copy of page
*
*                  fixed caching so sf_f=time|force themselves do not create new cache copies
*
* 1.2a (23Oct2005) textonly rearrangement commands changed to SF_command:content:begins and SF_command:content:ends
*
* 1.2 (22Oct2005) removed autoappend.php altogether moving all functionality into autoprepend.php. Has not only benefit of simplifying configuration but allowing pre-processing of 'commands' from the 'content' file (not sure about efficiency of this but we'll see).
*
*                 new gloabls $SF_phpselfdrivepath and array $SF_commands
*              
*                 implemented ability to turn pre-processing on and off via config_dir
*
*                 implemented caching using Cache_Lite (can be turned on/off, config'd in SF_cacheconfig.php)   
*        
*                 some minor cleanup in SF_LoadMenuData() loops          
*
* 1.1e (20Oct05) query strings for SF now case-insentive
*
* 1.1d (19Oct05) breadcrumb lines were not being htmlspecialchar'd - fixed
*
*                'print' link wasn't working from text only - fixed
*
* 1.1c (18Oct05) minor changes - global $SF_sitetitle, GPT_* constants introduced
*
* 1.1 (18Oct05) added SF_LoadSiteConfigData() and made adjustments throughout framework to cope with this.
* This allows all the configuration for a directory running under SF to be delegated.
* Functionally it means the directory is declared and its 'config_dir' file named and then all configuration
* for that directory is contained in that 'config_dir' file and its associated 'config_menu' files
*
* 1.0b fixed SF_GenerateContentFromURL() so it fixes no http:// relative references properly
*
* 1.0a added a few trim's to SF_LoadMenuData() so config file formatting is more forgiving
*
* @package SiteFramework
* @access public
* @license http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/licences.html GPL
* @copyright The Fitzwilliam Museum, University of Cambridge, UK
* @link http://www.fitzmuseum.cam.ac.uk/projects/phpsiteframework/
* @author Shaun Osborne (smo30@cam.ac.uk)
* @version 1.3 (2005-10-26)
*/

/**
* brings in global paths and default values for variables
*/
require_once('SF_mainconfig.php');

/**
* Site Framework (as a whole) version number
*/
$sfversion='1.3 (2005-10-26)';
#error_reporting(1); /* only report errors */

/****************************************************************************
Global variables */
$currentmenuarray=array();
$menudataarray=array();
$menuitemidentifier='0';  /*don't change this starting value*/
$menuitemtitle='';
$dirconfigarray = array();
$siteconfigarray = array();
$SF_commands=array();
$textonlyqs='sf_function=textonly'; /* query string to append to get textonly version */
$printlayoutqs='sf_function=print'; /* query string to append to get print layout version */
/* Global Constants */
/*GetPageTitle (GPT) constants */
define("GPT_PAGE",0);
define("GPT_SITEnPAGE",1);
define("GPT_BREADCRUMB",2);
define("GPT_SITEnBREADCRUMB",3);



/**** BEGINNING OF SF_mainmodule.php main() ****/
if($sfdebug >= 1){SF_DebugMsg($SF_sitedrivepath.'SF_mainmodule.php, Version: ['.$sfversion.'] loaded');}
SF_PageInitialise();
/*****   END OF SF_mainmodule.php main()  *****/


/**
* This (based on the page which called it) initialises everything for Site Framework
*
* It calls:
*
* SF_LoadSiteConfigData (which determines file for SF_LoadDirConfigData())
*
* SF_LoadDirConfigData() (which determines menudata, css, header and footer to load)
*
* SF_LoadMenuData() to initialise everything for this page (menu wise)
*
* Also
*
* @see SF_LoadMenuData
* @see SF_LoadDirConfigData
* @see SF_LoadSiteConfigData
* @access private
*/
function SF_PageInitialise()
/****************************************************************************/
{
  SF_LoadSiteConfigData();
  SF_LoadDirConfigData();
  SF_LoadMenuData();
}


/**
* Gets the config_dir filename 
*
* Load the SF_config_site.csv file and based on our current path extracts
* the appropriate config_dir filename. Result loaded into global array
* $siteconfigarray
*
* @access private
*/
function SF_LoadSiteConfigData()
{
global $siteconfigarray;
global $sfdebug;
#global $SF_modulesdrivepath;
global $defaultsiteconfigfile;
global $defaultdirconfigfile;
global $SF_sitewebpath;
global $SF_sitedrivepath;
global $SF_subsitewebpath;
global $SF_subsitedrivepath;
$filelinesarray=array();
$datafile=$defaultsiteconfigfile;

if(count($siteconfigarray) >= 1)
{
  if($sfdebug>=1){SF_DebugMsg('WARNING: SF_LoadSiteConfigData() has already run - skipping');}
  return;
}

$adjustedSFsitewebpath=removeleadingslash($SF_sitewebpath);
$currentpath=getpath(preg_replace("~$adjustedSFsitewebpath~i","",$_SERVER['PHP_SELF']));

if($sfdebug>=1)
{
SF_DebugMsg('SF_LoadSiteConfigData(SF_sitewebpath:['.$SF_sitewebpath.'])');
SF_DebugMsg('SF_LoadSiteConfigData(currentpath:'.$currentpath.' (will match '.$currentpath.' or '.removeleadingslash($currentpath).'), DATAFILE:'.$datafile.')');
}

$filelinesarray = file($datafile);
if(!$filelinesarray){SF_ErrorExit('SF_LoadSiteConfigData()','no data from file '.$datafile);}

$atroot=false;
while(!array_key_exists('dirconfigfile',$siteconfigarray) and $atroot==false)
     {
      foreach($filelinesarray as $line)
             {                
                $line=preg_replace("@\"@",'',$line);  /* remove any "'s from data */
                $values=split(',',$line);
                if(preg_match("@^/@",$values[0])) /* if path begins with a forward slash */
                  {$comparepath=$values[0];}      /* just use it */
                else
                  {$comparepath="/".$values[0];}  /* else add the forward slash */
                if($sfdebug >=3)
                  {SF_DebugMsg('SF_LoadSiteConfigData(COMPARE: Config:['.$comparepath.'] Current Path:['.$currentpath.']');}  
                if(!strcasecmp($comparepath,$currentpath))
                {
                  if($sfdebug >=2)
                  {SF_DebugMsg('SF_LoadSiteConfigData(MATCHED: Config:['.$comparepath.'] Current Path:['.$currentpath.']');}
                  if(trim($values[1])!="")
                  {
                     if($values[1][0] == '/')
                     {
                       $siteconfigarray['dirconfigfile']=$_SERVER['DOCUMENT_ROOT'].trim($values[1]);
                       $siteconfigarray['dirconfigpath']='';               
                     }
                     else
                     {
                       $siteconfigarray['dirconfigpath']=removeleadingslash($currentpath);
                       $siteconfigarray['dirconfigfile']=$SF_sitedrivepath.$siteconfigarray['dirconfigpath'].removeleadingslash($values[1]);                   
                     }
                  }  
                  break;
                }
            }
      if($currentpath=='/')
      {$atroot=true;}
      $currentpath=previousdir($currentpath);
     }
/* if weve got back to root (i.e here) and some values have not been set then use global defaults */
if(!array_key_exists('dirconfigfile',$siteconfigarray))
{
$siteconfigarray['dirconfigfile']=$defaultdirconfigfile;
$siteconfigarray['dirconfigpath']='';
}

$SF_subsitewebpath=$SF_sitewebpath.$siteconfigarray['dirconfigpath'];
$SF_subsitedrivepath=$SF_sitedrivepath.$siteconfigarray['dirconfigpath'];

if($sfdebug >=1){SF_DebugMsg('SF_LoadSiteConfigData('.print_r($siteconfigarray,true).')');}

return;  
}


/**
* Gets the settings for menu, css, header and footer files
*
* Load the dir_config file (determined by SF_LoadSiteConfigData()) and based 
* on our current path extracts the appropriate config_menu, css , header and 
* footer filenames.
*
* @access private
*/
function SF_LoadDirConfigData()
{
global $siteconfigarray;
global $dirconfigarray;
global $sfdebug;
global $SF_modulesdrivepath;
global $SF_sitedrivepath;
global $defaultmenudatafile;
global $defaultdirconfigfile;
global $defaultheaderfile;
global $defaultfooterfile;
global $defaultcssfile;
global $SF_sitewebpath;
$filelinesarray=array();

if(count($dirconfigarray) >= 1)
{
  if($sfdebug>=1){SF_DebugMsg('WARNING: SF_LoadDirConfigData() has already run - skipping');}
  return;
}

if(array_key_exists('dirconfigfile',$siteconfigarray))
{
$datafile=$siteconfigarray['dirconfigfile'];
}
else
{
$datafile=$defaultdirconfigfile;
}

$adjustedSFsitewebpath=removeleadingslash($SF_sitewebpath);

$currentpath=getpath(preg_replace("~$adjustedSFsitewebpath~i","",$_SERVER['PHP_SELF']));

if($sfdebug>=1){SF_DebugMsg('SF_LoadDirConfigData(currentpath:'.$currentpath.' (will match '.$currentpath.' or '.removeleadingslash($currentpath).'), DATAFILE:'.$datafile.')');}

$filelinesarray = file($datafile);
if(!$filelinesarray){SF_ErrorExit('SF_LoadDirConfigData()','no data from file '.$datafile);}

$atroot=false;
while(configdataisincomplete() and $atroot==false)
     {
      foreach($filelinesarray as $line)
             {                
                $line=preg_replace("@\"@","",$line);  /* remove any "'s from data */
                $values=split(",",$line);
                foreach($values as $key=>$junk){$values[$key]=trim($values[$key]);}
                if(preg_match("@^/@",$values[0])) /* if path begins with a forward slash */
                  {$comparepath=$values[0];}      /* just use it */
                else
                  {$comparepath='/'.$siteconfigarray['dirconfigpath'].$values[0];}  /* else add the forward slash */
                if($sfdebug>=3){SF_DebugMsg('SF_LoadDirConfigData(COMPARE: currentpath:['.$currentpath.'] comparepath:['.$comparepath.']');}
                if(!strcasecmp($comparepath,$currentpath))
                {
                  if($sfdebug >=2)
                  {SF_DebugMsg('SF_LoadConfigData(MATCHED: Config:['.$comparepath.'] Current Path:['.$currentpath.']');}
                  if($values[1]!="" and !array_key_exists('menudatafile',$dirconfigarray))
                  {
                    if($values[1][0] == '/')
                    $dirconfigarray['menudatafile']=$_SERVER['DOCUMENT_ROOT'].$values[1];
                    else
                    $dirconfigarray['menudatafile']=$SF_sitedrivepath.$siteconfigarray['dirconfigpath'].removeleadingslash($values[1]);          
                  }
                  if($values[2]!="" and !array_key_exists('menu',$dirconfigarray))
                  {
                  $dirconfigarray['menu']=$values[2];
                  }
                  if(trim($values[3]) != "" and !array_key_exists('menukey',$dirconfigarray))
                  {
                  $dirconfigarray['menukey']=$values[3];
                  }
                  if(trim($values[4]) != "" and !array_key_exists('cssfile',$dirconfigarray))
                  {
                    if($values[4][0] == '/')
                    $dirconfigarray['cssfile']=$_SERVER['DOCUMENT_ROOT'].$values[4];
                    else
                    $dirconfigarray['cssfile']=$SF_sitewebpath.$siteconfigarray['dirconfigpath'].removeleadingslash($values[4]);          
                  }
                  if($values[5] != "" and !array_key_exists('headerfile',$dirconfigarray))
                  {
                    if($values[5][0] == '/')
                    $dirconfigarray['headerfile']=$_SERVER['DOCUMENT_ROOT'].$values[5];
                    else
                    $dirconfigarray['headerfile']=$SF_sitedrivepath.$siteconfigarray['dirconfigpath'].removeleadingslash($values[5]);          
                  }
                  if($values[6] != "" and !array_key_exists('footerfile',$dirconfigarray))
                  {
                    if($values[6][0] == '/')
                    $dirconfigarray['footerfile']=$_SERVER['DOCUMENT_ROOT'].$values[6];
                    else
                    $dirconfigarray['footerfile']=$SF_sitedrivepath.$siteconfigarray['dirconfigpath'].removeleadingslash($values[6]);          
                  }
                  if($values[7] != '' and !array_key_exists('contentpp',$dirconfigarray))
                  {
                    $dirconfigarray['contentpp']=strtolower($values[7]);          
                  }
                  break;
                }
            }
      if($currentpath=='/')
      {$atroot=true;}
      $currentpath=previousdir($currentpath);
     }
/* if weve got back to root (i.e here) and some values have not been set then use global defaults */
if(!array_key_exists('menudatafile',$dirconfigarray))
{$dirconfigarray['menudatafile']=$defaultmenudatafile;}
if(!array_key_exists('cssfile',$dirconfigarray))
{$dirconfigarray['cssfile']=$defaultcssfile;}
if(!array_key_exists('headerfile',$dirconfigarray))
{$dirconfigarray['headerfile']=$defaultheaderfile;}
if(!array_key_exists('footerfile',$dirconfigarray))
{$dirconfigarray['footerfile']=$defaultfooterfile;}

if($sfdebug >=1)
  {SF_DebugMsg('SF_LoadDirConfigData('.print_r($dirconfigarray,true).')');}
return;  
}


/**
* Loads the menu data for the page we are on
*
* Load the menu_config file (determined by SF_LoadDirConfigData()) and based 
* on our current path figure out what menu item we are on.  Popoulates two
* globals arrays; $menudataarray (contains the whole menu data file) and 
* contains a sort sorted list of current menu items to display
*
* @access private
*/
function SF_LoadMenuData()
{
global $sfdebug;
global $menutoplevelidentifier;
global $currentmenuarray;
global $menudataarray;
global $dirconfigarray;
global $siteconfigarray;
global $defaultmenudatafile;
global $menuitemidentifier;
global $menuitemtitle;
global $SF_sitedrivepath;
global $SF_sitewebpath;
global $SF_defaultindexfile;

$datafile=$dirconfigarray['menudatafile'];
$menu=$dirconfigarray['menu'];
$menukey=$dirconfigarray['menukey'];

if(count($menudataarray) >= 1)
{
  if($sfdebug>=1){SF_DebugMsg('WARNING: SF_LoadMenuData() has already run - skipping');}
  return;
}

if($sfdebug >=1){SF_DebugMsg("SF_LoadMenuData($datafile,$menu,$menukey)");}

$filelinesarray = file($datafile);
if(!$filelinesarray){SF_ErrorExit('SF_LoadMenuData()','no data from menu file '.$datafile);}

/*get the toplevel and requested level values into new array keyed by order value from csv file*/
$filelinesarray=preg_replace("@\"@","",$filelinesarray);  /* remove any "'s from csv data */
$menudataarray=$filelinesarray;
array_shift($menudataarray); /* remove the header line from array*/

foreach($menudataarray as $key=>$item)
       {
        $item=preg_replace("@,\/@",",",$item); /* this is a bit of a cludge to remove leading slashes in paths */
        $subitem=split(',',trim($item));
        if(!strcmp($menu,trim($subitem[0])) and (!strcmp($menutoplevelidentifier,trim($subitem[1])) or !strcmp($menukey,trim($subitem[1])) ))
          {
          if(!strcmp('',$subitem[3])){$cmkey=$key;}else{$cmkey=$subitem[3];} /* this covers if no ordering info given */
          $currentmenuarray[$cmkey]=$subitem;
          }
       }
       
/*sort the currentmenuarray on keys (order value)*/
ksort($currentmenuarray,SORT_STRING);
$tpath=$SF_sitewebpath.$siteconfigarray['dirconfigpath'];
$currentpath=preg_replace("@^$tpath@i","",$_SERVER['PHP_SELF']);
if($sfdebug >=2){SF_DebugMsg('SF_LoadMenuData(currentpath:'.$currentpath.')');}
/*find what order number we are at e.g. 1, 1.1, 1.3.1
first pass, this will match exacts or dir+index.htm(l)'s or dire+filename+.anything.htm(l)*/
foreach($currentmenuarray as $item)
       {
        /* if we get a direct match, or match on value+index.html, or match on altered path
        e.g gettinghere.2.html becomes gettinghere.html 
        then remember it as the current item */
        $item[4]=trim($item[4]);
        if(!strcasecmp($currentpath,$item[4]) or !strcasecmp($currentpath,$item[4].$SF_defaultindexfile) or !strcasecmp(preg_replace("/\.[0-9a-zA-Z].*\.htm/",".htm",$currentpath),$item[4]))
        {
          $menuitemidentifier=$item[3];
          $menuitemtitle=$item[2];
        }
       }
       
/*second pass if nothing from first - on just paths if we got nothing from previous foreach*/
if($menuitemidentifier == '0')
  {
  foreach($currentmenuarray as $item)
       {
        if(!strcasecmp(getpath($currentpath),getpath(trim($item[4]))))
        {
         $menuitemidentifier=trim($item[3]);
         $menuitemtitle=trim($item[2]);
         break; /*stop when we find the first one*/
        }
       }
  }
if($sfdebug >=1){SF_DebugMsg("SF_LoadMenuData(has chosen MENUID:$menuitemidentifier, TITLE:$menuitemtitle)");}
return;
}


/**
* Ouput the HTML for the current menu
* 
* Use global array $currentmenuarray to ouput the currently selected menu set
* as determined was by SF_LoadMenuData().
*
* Menu block is surrounded by a <div id=SF_"menuarea" class="menuarea">
* Menu level 1's are tagged as <p class="SF_menu_level_1">
* Menu level 2's are tagged as <p class="SF_menu_level_1">
*
* @see SF_LoadMenuData()
* @params bool controls whether we tag what menuitem is selected true=on [default], false=off (CSS=SF_menu_level_1_highlight and SF_menu_level_2_highlight)
* @params bool controls whether we tag items that are off site links (start with http://), true=on [default], false=off (CSS=SF_offsite_link)
* @params integer control whether to show all menu levels (0), only menu level 1's (1) or only menu level 2's (2)
*/
function SF_GenerateNavigationMenu($menuhighlight=true,$dooffsitelinktags=true,$showonlylevel=0)

{
global $SF_modulesdrivepath;
global $sfdebug;
global $SF_sitewebpath;
global $dirconfigarray;
global $siteconfigarray;
global $defaultmenudatafile;
global $menuitemidentifier;
global $currentmenuarray;
global $menutoplevelidentifier;

#get the values we want from global config
foreach($dirconfigarray as $key=>$value)
        {
          switch($key){
          case 'menukey':
                        $menukey=$value;
                         break;
          case 'menu':
                        $menu=$value;
                         break;
          default:
                        break;
          }
        }
if($sfdebug >=3){SF_DebugMsg("SF_GenerateNavigationMenu($menu,$menukey,hl:$menuhighlight,mi:$menuitemidentifier)<br/>"); }
# create navigation menu div
echo('<div id="SF_menuarea" class="SF_menuarea">');
#print out the menu from the global array
foreach($currentmenuarray as $item)
       {
        if(!strcmp($menutoplevelidentifier,$item[1]))
          {
            if(!strcmp($menuitemidentifier,$item[3]) and $menuhighlight)$cssclass='SF_menu_level_1_highlight';
            else $cssclass='SF_menu_level_1';
          }
        else
          {
            if(!strcmp($menuitemidentifier,$item[3]) and $menuhighlight)$cssclass='SF_menu_level_2_highlight';
            else $cssclass='SF_menu_level_2';
          }
        if(preg_match("/^http:\/\/.*/",$item[4]) and $dooffsitelinktags)
        {
        $menuitemhtml = '<p class="'.$cssclass.'"><span class="SF_offsite_link"><a href="'.$item[4].'" target="_blank">'.htmlspecialchars($item[2])."</a></span></p>\n";
        }
        else
        {
        $menuitemhtml = '<p class="'.$cssclass.'"><a href="'.$SF_sitewebpath.$siteconfigarray['dirconfigpath'].$item[4].'">'.htmlspecialchars($item[2])."</a></p>\n";
        }
        /* deal with $showonlylevels */
        if($showonlylevel == 0)
          {echo $menuitemhtml;}
        elseif($showonlylevel==1 and !strcmp($menutoplevelidentifier,$item[1]))
          {echo $menuitemhtml;}
        elseif($showonlylevel==2 and strcmp($menutoplevelidentifier,$item[1]))
          {echo $menuitemhtml;}

       }
/* end SF_menuarea div */
echo("</div>");           
return;
}


/**
* Output breadcrumb html for current page
*
* CSS styles are: SF_breadcrumbarea, SF_breadcrumb_line, SF_breadcrumb_title and SF_breadcrumb_item
*
* @access public
* @param string the lead text for the breadcrumbline
* @param string the separator between each breadcrumb item
* @param bool output (true) html or return result from function as string
*
*/
function SF_GenerateBreadCrumbLine($breadcrumbleadtext="You are in: ",$breadcrumbseparator=" > ",$output=true)
{
global $SF_modulesdrivepath;
global $sfdebug;
global $SF_sitewebpath;
global $siteconfigarray;
global $menuitemidentifier;
global $menudataarray;
global $menutoplevelidentifier;
$breadcrumbs=array();


#take a copy of this global
$tempmii=$menuitemidentifier;

#do sublevels eg 1.1 10.3.1 etc
while(preg_match("/\./",$tempmii))
     {
      foreach($menudataarray as $item)
       {
        $subitem=split(',',trim($item));
        if(!strcmp($tempmii,$subitem[3]))
        {
         $breadcrumbs[$subitem[3]]=$subitem;
        }
       }

     /* strip numbers from the right if any ie 1.1 becomes 1*/
     for($x=strlen($tempmii)-1; $tempmii[$x] != '.' and $x>=0; $x--)
        {
         $tempmii[$x]=' ';
        }
      $tempmii[$x++]=' '; #strip decimal ie 1. becomes 1
      $tempmii = trim($tempmii); #trim off spaces
     }

#do top level e.g 1-xx
foreach($menudataarray as $item)
       {
        $subitem=split(',',trim($item));
        if(!strcmp($tempmii,$subitem[3]))
        {
        $breadcrumbs[$subitem[3]]=$subitem;
        }
       }
#do home e.g 0
foreach($menudataarray as $item)
       {
        $subitem=split(',',trim($item));
        if(!strcmp('0',$subitem[3]))
        {
        $breadcrumbs[$subitem[3]]=$subitem;
        }
       }

#sort the breadcrumbs back into order
ksort($breadcrumbs,SORT_STRING);

#print out the menu in the newly sorted order
if($output)
{ 
  if($sfdebug >=3){SF_DebugMsg('SF_GenerateBreadCrumbLine(mi:'.$menuitemidentifier.')'); }
  echo('<div id="SF_breadcrumbarea" class="SF_breadcrumbarea"><p class="SF_breadcrumb_line">');
  echo('<span class="SF_breadcrumb_title">'.$breadcrumbleadtext.'</span>');
  $x=1;
  foreach($breadcrumbs as $key=>$item)
         {
           echo('<span class="SF_breadcrumb_item">');
           echo('<a href="'.$SF_sitewebpath.$siteconfigarray['dirconfigpath'].$item[4].'">'.htmlspecialchars($item[2]).'</a>');
           echo('</span>');
           if($x++ < count($breadcrumbs)){echo $breadcrumbseparator;}
         }
  echo('</p></div>');
}
else /* $output == FALSE */
{
  $breadcrumbstring='';
  $x=1;
  foreach($breadcrumbs as $key=>$item)
  {
   $breadcrumbstring = $breadcrumbstring.htmlspecialchars($item[2]);
   if($x++ < count($breadcrumbs)){$breadcrumbstring = $breadcrumbstring.$breadcrumbseparator;}
  }
  return $breadcrumbstring;
}
}


/**
* returns a string representing the current page
*
* Argument is following types:
*
* GPT_PAGE = Current Page Title (as determined via menu data)
*
* GPT_SITEnPAGE = $SFsitetitle + Current Page Title 
*
* GPT_BREADCRUMB = current breadcrumb (as determined via menu data) line separated by '|'s
*
* GPT_SITE+BREADCRUMB = $SFsitetitle + current breadcrumb
*
* @param integer GPT_PAGE, GPT_SITEnPAGE, GPT_BREADCRUMB, GPT_SITE+BREADCRUMB
*/
function SF_GetPageTitle($titletype=GPT_BREADCRUMB)
{
global $menuitemtitle;
global $SF_sitetitle;

switch($titletype)
  {
  case GPT_PAGE:
                return $menuitemtitle;
                break;
  case GPT_SITEnPAGE:
                return $SF_sitetitle." : ".$menuitemtitle;
                break;
  case GPT_BREADCRUMB:
                return SF_GenerateBreadCrumbLine(""," | ",false);
                break;
  case GPT_SITEnBREADCRUMB:
                return $SF_sitetitle.': '.SF_GenerateBreadCrumbLine(""," | ",false); 
                break;
       default:
                return '(no page title)';
                break;
  }
}


/**
* Returns CSS path and file name based on 'config_dir' settings
*
*/
function SF_GetCSSFilename()
{
global $sfdebug;
global $dirconfigarray;
#get the css value from dir config array */
if($sfdebug >= 1){SF_DebugMsg('SF_GetCSSFilename('.$dirconfigarray['cssfile'].')'); }
return $dirconfigarray['cssfile']; 

}
/**
* Returns CSS path and file name of SF's default CSS file
*
*/
function SF_GetDefaultCSSFilename()
{
global $defaultcssfile;
return $defaultcssfile;
}


/**
* Output site map html from current menu data
*
* [tobedone] description
*
* @param bool turns on (true) or off (false) showing of level identifiers in the outputted HTML
*/
function SF_GenerateSiteMap($showlevels=false)
/*****************************************************************************/
{
global $SF_modulesdrivepath;
global $sfdebug;
global $SF_sitewebpath;
global $menuitemidentifier;
global $menudataarray;
global $menutoplevelidentifier;
global $siteconfigarray;
$sitemaparray=array();

if($sfdebug>=3){SF_DebugMsg('SF_GenerateSiteMap()');}
#take a copy of this global
$tempmii=$menuitemidentifier;
foreach($menudataarray as $item)
       {
        $subitem=split(',',trim($item));
        $sitemaparray[$subitem[3]]=$item;
       }

ksort($sitemaparray,SORT_STRING);

echo('<div id="SF_sitemaparea" class="SF_sitemaparea">');
foreach($sitemaparray as $item)
       {
        $subitem=split(",",trim($item));
        switch(preg_match_all("/\./",$subitem[3],$dontcarearray))
        {
          case 0:
                 $cssclass='SF_map_level_1';
                 break;
          case 1:
                 $cssclass='SF_map_level_2';
                 break;
          case 2:
                 $cssclass='SF_map_level_3';
                 break;
          case 3:
                 $cssclass='SF_map_level_4';
                 break;
          case 4:
                 $cssclass='SF_map_level_5';
                 break;
          case 5:
                 $cssclass='SF_map_level_6';
                 break;
        }
        /* now output this item classed correctly */
        echo '<p class="'.$cssclass.'">';
        if(preg_match("@^http:\/\/.*@",$subitem[4]))
        {echo '<a href="'.$subitem[4].'">';}
        else
        {echo '<a href="'.$SF_sitewebpath.$siteconfigarray['dirconfigpath'].$subitem[4].'">';}  
        echo $subitem[2].'</a>';
        if($showlevels == true)
        {echo ' ('.$subitem[3].')';}
        echo "</p>\n";
      }
echo('</div>');
}


/**
* Global error exit function for Site Framework
*
* Outputs CSS link, Error text supplied and does a hard exit
*
* @param string intented to identify who called the exit, file or function
* @param string error message you want to output with the exit
*/
function SF_ErrorExit($caller='nocaller', $msg='nomsg')
{
global $SF_moduleswebpath;
echo '<link href="'.SF_GetDefaultCSSFilename().'" rel="stylesheet" type="text/css">';
echo '<br/><p class="SF_error_text">SF Fatal Error: from=['.$caller.']<br/>error=['.$msg.']</p><br/>';
exit;
}


/**
* Global debug message function for Site Framework
*
* Outputs CSS link, and debug text you supply
*
* @param string debug message you want to output
*/
function SF_DebugMsg($msg='nomsg')
{
global $SF_moduleswebpath;
global $defaultcssfile;
echo '<link href="'.SF_GetDefaultCSSFilename().'" rel="stylesheet" type="text/css">';
echo '<span class="SF_debug_text">SF_debug: '.$msg.'</span></br>';
}


/**
* Gets the contents of named file/URL and output it
*
* Passed a filename/URL it determines to:
*
* http:// just get it
*
* anything else assume some sort of relative path so convert it
* to an absolute file reference starting at webserver DOCUMENT_ROOT 
*
* so these should all be ok:
*
* file.html (reference in current dir)
*
* ../about/index.html (some sort of relative reference)
*
* /about/index.html (absolute reference (from root) on this server)
*
*
* @param string URL you want to get
*/
function SF_GenerateContentFromURL($url)
{
global $SF_sitedrivepath;

/* figure out if this is a http (get it) or fix the path up for getting off the local filesystem */
$url=sfnormaliseurl($url,'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

$contents=file_get_contents($url);
if(!$contents)
  {SF_ErrorExit('SF_generateContentFromURL','Failed to open file ['.$url.']');}

echo $contents;

return;
}


/**
* template title
*
* template desc
*
* @access public
*/
function SF_GetPageModifiedDate($filename='',$dateformat='jMY h:i')
{
if($filename == '')
{
 $filename=$_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'];
}
return date($dateformat, filemtime($filename));
}


/**
* template title
*
* template desc
*
* @access public
*/
function SF_GetTextOnlyURL()
{
global $textonlyqs;
if(preg_match("@\?@",$_SERVER['REQUEST_URI'])){$sep='&amp;';}else{$sep='?';}
return $_SERVER['REQUEST_URI'].$sep.$textonlyqs;
}


/**
* template title
*
* template desc
*
* @access public
*/
function SF_GetPrintURL()
{
global $printlayoutqs;
if(preg_match("@\?@",$_SERVER['REQUEST_URI'])){$sep='&amp;';}else{$sep='?';}
return $_SERVER['REQUEST_URI'].$sep.$printlayoutqs;
}


/**
* template title
*
* template desc
*
* @access private
*/
function getcurrentpath()
{
global $sfdebug;
$currentpath = getpath($_SERVER['PHP_SELF']);
#$currentpath = $_SERVER['PHP_SELF'];
if($sfdebug){SF_DebugMsg('getcurrentpath('.$currentpath.')'); }
return $currentpath;
}


/**
* template title
*
* template desc
*
* @access private
*/
function getpath($urlstring)
{
  $urlstring=trim($urlstring); 
  /*if we are at the root just return with root */
  if(!strcmp('/',$urlstring))
  {return $urlstring;}
  /* search from right of string and remove all characters back to the next right-most '/' - effect is clipping to path*/
  for($x=strlen($urlstring)-1; $x>=0 and $urlstring[$x] != '/'; $x--)
  {$urlstring[$x]=' ';}
  return trim($urlstring);
}


/**
* template title
*
* template desc
*
* @access private
*/
function removetrailingslash($pathstring)
{
  $pathstringlength=strlen($pathstring)-1;
  if($pathstring[$pathstringlength] == '/')
    {
    $pathstring[$pathstringlength]=' ';
    }
 return trim($pathstring);
}


/**
* template title
*
* template desc
*
* @access private
*/
function removeleadingslash($pathstring)
{
  if($pathstring[0] == '/')
    {
    $pathstring[0]=' ';
    }
 return trim($pathstring);
}


/**
* template title
*
* template desc
*
* @access private
*/
function previousdir($pathstring)
{
  global $sfdebug;
  $pathstring=trim($pathstring); 
  /*if we are at the root just return with root */
  if(!strcmp("/",$pathstring))
    {return $pathstring;}
  /*remove initial trailing slash */
  $pathstring=removetrailingslash($pathstring);
  /* search from right of string and remove all characters back to the next right-most '/' - effect is clipping the path to previous directory*/
  for($x=strlen($pathstring)-1; $pathstring[$x] != '/' and $x>=0; $x--)
  {$pathstring[$x]=' ';}
  $pathstring=trim($pathstring); /* remove whitepspace */
  return $pathstring;
}


/**
* template title
*
* template desc
*
* @access private
*/
function getpreviouspath($urlstring)
/*************************************************************************/
{
  $urlstring=trim($urlstring); 
  /*if we are at the root just return with root */
  if(!strcmp("/",$urlstring))
  {return $urlstring;}
  /* always knock of the first / if there is one */
  if($urlstring[(strlen($urlstring)-1)] == '/')
  {
  $urlstring[strlen($urlstring)-1] = ' ';
  }
  /* search from right of string and remove all characters back to the next right-most '/' - effect is clipping to path*/
  for($x=strlen($urlstring)-1; $urlstring[$x] != '/' and $x>=0; $x--)
  {$urlstring[$x]=' ';}
  return trim($urlstring);
}


/**
*  Create a useable file or HTTP reference from whatever we are passed
*
*
* @access private
* @param string the reference we want to normalise
* @param string the url we are currently at
*/
function sfnormaliseurl($url_ref,$url)
{
global $SF_defaultindexfile;
$adjusted_url="";

$url=preg_replace("@$SF_defaultindexfile$@","",$url);
if(preg_match("@^http@",$url_ref))
  {
  $adjusted_url=$url_ref;
  }
else
  {
    if(preg_match("@^\/@",$url_ref))
    {  
      $adjusted_url=$_SERVER['DOCUMENT_ROOT'].$url_ref;
    }
    else
    {
      if(preg_match("@^[0-9a-z]@i",$url_ref))
      {
        $adjusted_url=$_SERVER['DOCUMENT_ROOT'].getpath(preg_replace("@http:\/\/".$_SERVER['HTTP_HOST']."@","",$url)).$url_ref;
      }
      else
      {
      $thttphost='http://'.$_SERVER['HTTP_HOST'];
      $turl=preg_replace("@$thttphost@",'',getpath($url));
      $texurl=$url_ref;
        while(preg_match("@^\.\.\/@",$texurl))
            {
            $texurl=preg_replace("@^\.\.\/@","",$texurl);
            $turl=getpreviouspath($turl);
            }
      $adjusted_url=$_SERVER['DOCUMENT_ROOT'].$turl.$texurl;
      }
    }
  }

 $adjusted_url=preg_replace("@#.*$@","",$adjusted_url); /* remove anything on end of url after a # */
 $adjusted_url=preg_replace("@\?.*$@","",$adjusted_url); /* remove anything on end of url after a ? */
  
  /*echo"[U]".$url."\n";
  echo"[S]".$url_ref."\n";
  echo"[F]".$adjusted_url."\n";*/
 return $adjusted_url; 
}


/**
* template title
*
* template desc
*
* @access private
*/
function configdataisincomplete()
{
  global $sfdebug;
  global $dirconfigarray;
  if($sfdebug >=3){SF_DebugMsg('configdataisincomplete() - config currently is: '.print_r($dirconfigarray,true)); }
  if(array_key_exists('menudatafile',$dirconfigarray) and array_key_exists('cssfile',$dirconfigarray) and array_key_exists('headerfile',$dirconfigarray) and array_key_exists('footerfile',$dirconfigarray))
  {return false;}
  else
  {return true;}
  
}



/**
* template title
*
* template desc
*
* @access private
*/
function rearrangepagefortextonly($inputhtml)
{
$h=preg_match("@^.*<!-- SF_Command:content:begins -->@s",$inputhtml,$header);
$b=preg_match("@<!-- SF_Command:content:begins -->.*<!-- SF_Command:content:ends -->@s",$inputhtml,$body);
$f=preg_match("@<!-- SF_Command:content:ends -->.*$@s",$inputhtml,$footer);

/* if we get a sucessful transformation return it else return what we got in */
if($h==1 and $b==1 and $f==1)
  {return $body[0].$header[0].$footer[0];}
else
 {return $inputhtml;}  
}


/**
* Generate text only html from input html
*
* removes tables, images and css refs
*
* @access public
*/
function SF_GenerateTextOnlyHTML($url,$output=true)
{
$search = array(
                "@<table.*?>|</table>|<tr.*?>|</tr>|<td.*?>|</td>|<hr.*?>|<link.*?>@i",
                "@(<img.+alt=)(\"[^<].+?\")([^<].*?>)@i", /* replace img's with alt text first */
                "@(<img[^<].+?>)@i" /* then those without (which wouldn't have s&r'd by previous) */
               );
$replace = array(
                 "",
                 "\nImage[$2]<br/>\n",
                 "\nImage[no alt text]<br/>\n"
                 );


$resulthtml=preg_replace($search,$replace,file_get_contents($url));
$resulthtml=rewriteurlsfortextonly($resulthtml);
$resulthtml=rearrangepagefortextonly($resulthtml);


if($output)
 {echo $resulthtml;}
else
 {return $resulthtml;}
}

/**
* template title
*
* template desc
*
* @access private
*/
function rewriteurlsfortextonly($inputhtml)
{
global $textonlyqs;

$search = array(
                "@<a href=@",   
                "@(<a href=\")([^\"][0-9\.\/a-zA-Z]+?)(\".*>)@i", /*except those beginning with h(ttp) */
                );
$replace = array(
                 "\n<a href=",   /* force each new href to be on a newline */
                 "$1$2?$textonlyqs$3",    /* now append onto our urls */
                 );
$resulthtml=preg_replace($search,$replace,$inputhtml);
return $resulthtml;
}

?>
