<?php

$fStartTime = explode(' ', microtime());
$fStartTime = $fStartTime[1] + $fStartTime[0];
set_time_limit(0);

/* ----- PACKAGE VARIABLES ----- */

define('PKG_NAME', 'ImportUsersX');
define('PKG_NAME_LOWER', 'importusersx');
define('PKG_VERSION', '1.0');
define('PKG_RELEASE', 'beta1');

/* ----- ---------------- ----- */

/* ----- BUILD PATHS ----- */

$sRootDirectory = dirname(dirname(__FILE__)).'/';
$aSources = array(
		'root' => $sRootDirectory,
		'build' => $sRootDirectory . '_build/',
		'data' => $sRootDirectory . '_build/data/',
		'resolvers' => $sRootDirectory . '_build/resolvers/',
		'chunks' => $sRootDirectory.'core/components/'.PKG_NAME_LOWER.'/chunks/',
		'lexicon' => $sRootDirectory . 'core/components/'.PKG_NAME_LOWER.'/lexicon/',
		'docs' => $sRootDirectory.'core/components/'.PKG_NAME_LOWER.'/docs/',
		'elements' => $sRootDirectory.'core/components/'.PKG_NAME_LOWER.'/elements/',
		'source_assets' => $sRootDirectory.'assets/components/'.PKG_NAME_LOWER,
		'source_core' => $sRootDirectory.'core/components/'.PKG_NAME_LOWER,
		);
unset($sRootDirectory);

/* ----- ---------- ----- */

require_once $aSources['build'].'build.config.php';
require_once (MODX_CORE_PATH . 'model/modx/modx.class.php');

$modx = new modX();
$modx->initialize('mgr');

echo "<pre>";
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$modx->loadClass('transport.modPackageBuilder', "", false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER, false, true, '{core_path}components/' .PKG_NAME_LOWER. '/');
	
//Creating new category for the extra
$category= $modx->newObject('modCategory');
$category->set('category',PKG_NAME);

//Adding chunks
$modx->log(modX::LOG_LEVEL_INFO, 'Packaging in chunks...');
$chunks = include $aSources['data'].'transport.chunks.php';
if (empty($chunks)) $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in chunks.');
$category->addMany($chunks);

	/*Adding the snippet
	$modx->log(modX::LOG_LEVEL_INFO,'Packaging in snippets...');
	$snippets = include $sources['data'].'transport.snippets.php';
	if (empty($snippets)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in snippets.');
	$category->addMany($snippets);*/

/* ----- CATEGORY ATTRIBUTES ----- */	

$aAttr = array(
	xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
    	'Snippets' => array(
       		xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
   	),
);
		
/* ----- ------------------- ----- */

//Adding category and its attributes into a vehicle		
$vehicle = $builder->createVehicle($category,$aAttr);
	
$modx->log(modX::LOG_LEVEL_INFO,'Adding package attributes and setup options...');
$builder->setPackageAttributes(array(
	'license' => file_get_contents($aSources['docs'] . 'license.txt'),
    'readme' => file_get_contents($aSources['docs'] . 'readme.txt'),
    'changelog' => file_get_contents($aSources['docs'] . 'changelog.txt'),
	
	//We don't need setup options for now
    /*'setup-options' => array(
    	'source' => $sources['build'].'setup.options.php',
   		),*/
		
));
		
//Set the package up 
$vehicle->resolve('php', array(
	'source' => $aSources['build'].'setup.importusersx.php',
));	

$modx->log(modX::LOG_LEVEL_INFO,'Adding file resolvers to category...');

/* ----- ADDING SOURCES ----- */

$vehicle->resolve('file',array(
	'source' => $aSources['source_assets'],
	'target' => "return MODX_ASSETS_PATH . 'components/';",
));

$vehicle->resolve('file',array(
	'source' => $aSources['source_core'],
	'target' => "return MODX_CORE_PATH . 'components/';",
));
	
/* ----- -------------- ----- */
	
$builder->putVehicle($vehicle);
unset($vehicle);
	
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in menu...');
$menu = include $aSources['data'].'transport.menu.php';
	
if (empty($menu)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in menu.');

$vehicle= $builder->createVehicle($menu,array (
	xPDOTransport::PRESERVE_KEYS => true,
   	xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::UNIQUE_KEY => 'text',
   	xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
    	'Action' => array (
        	xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => array ('namespace','controller'),
        ),
    ),
));

$modx->log(modX::LOG_LEVEL_INFO,'Adding in PHP resolvers...');
$builder->putVehicle($vehicle);
unset($vehicle,$menu);

//Zip package up
$modx->log(modX::LOG_LEVEL_INFO,'Packing up transport package zip...');
$builder->pack();
	
//End of Building
$fEndTime = explode(' ', microtime());
$fEndTime = $fEndTime[0] + $fEndTime[1];
$fTime = sprintf("%2.4f seconds", ($fEndTime - $fStartTime));
$modx->log(modX::LOG_LEVEL_INFO,"Package built in {$fTime}");

exit();
?>