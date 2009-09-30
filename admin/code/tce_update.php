<?php
//============================================================+
// File name   : gso_update.php
// Begin       : 2009-09-14
// Last Update : 2009-09-30
// 
// Description : Automatic updates.
//
// Author: Nicola Asuni
//
// (c) Copyright 2004-2009:
//               Nicola Asuni
//               Tecnick.com S.r.l.
//               ITALY
//               www.tecnick.com
//               info@tecnick.com
//
// License: 
//    Copyright (C) 2004-2009  Nicola Asuni - Tecnick.com S.r.l.
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * Automatic updates.
 * @package com.tecnick.gasoffice.shared
 * @author Nicola Asuni
 * @copyright Copyright &copy; 2008-2009, Nicola Asuni - Tecnick.com S.r.l. - ITALY - www.tecnick.com - info@tecnick.com
 * @since 2009-09-14
 */

require_once('../config/tce_config.php');
$pagelevel = K_AUTH_ADMINISTRATOR;
require_once(dirname(__FILE__).'/gso_authorization.php');
$thispage_title = 'UPDATE';
require_once('../code/tce_page_header.php');


/**
 * Updating server
 */
define ('K_UPDATE_SERVER', 'http://updates.tecnick.com');

/**
 * UPDATES PASSKEY
 */
define ('K_UPDATE_PASSKEY', '');

echo '<div class="container">';

$continue = true;

// install all updates
while ($continue) {
	
	// get current version date
	$vdate = file_get_contents(K_PATH_CACHE.'date.txt');

	// get remote update
	$update = file_get_contents(K_UPDATE_SERVER.'?k='.K_UPDATE_PASSKEY.'&d='.urlencode($vdate));

	if ($update === false) {
		echo '<h2>Connection error to update server, retry later.</h2>';
		$continue = false;
		break;
	}

	if (substr($update, 0, 7) == 'MESSAGE') {
		echo '<h2>'.substr($update, 8).'</h2>';
		$continue = false;
		break;
	}

	// save updating file
	$f = fopen(K_PATH_CACHE.'update.tar.gz', 'wb');
	if ($f) {
		fwrite($f, $update, strlen($update));
	}
	fclose($f);
	
	// *** start installation procedure ***

	chdir(K_PATH_CACHE);

	// extract files
	exec('gzip -dc update.tar.gz | tar xf -');
	exec('rm update.tar.gz');
	
	$version = file_get_contents(K_PATH_CACHE.'version.txt');

	if (file_exists(K_PATH_CACHE.'patch.sql')) {
		// update database
		$command = 'mysql -h'.K_DATABASE_HOST.' -u'.K_DATABASE_USER_NAME.' -p'.K_DATABASE_USER_PASSWORD.' '.K_DATABASE_NAME.' < '.K_PATH_CACHE.'patch.sql';
		exec($command);
		echo exec('rm patch.sql');
	}

	// apply patch
	chdir(K_PATH_MAIN);
	exec('patch < '.K_PATH_CACHE.'patch.diff');
	echo '<h2>'.$version.': update completed.</h2>';
	exec('rm '.K_PATH_CACHE.'patch.diff');
	
	// restore current dir
	chdir(K_PATH_MAIN.'code/');
}

echo '</div>'.K_NEWLINE;
require_once(dirname(__FILE__).'/gso_page_footer.php');

//============================================================+
// END OF FILE                                                 
//============================================================+
?>