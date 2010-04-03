<?php
//Load all files in config folder
if(file_exists($dir = APP . 'plugins' . DS . 'acl_plus' . DS . 'config' . DS . 'autoload')) {
    $files = scandir($dir);
    foreach ($files as $file) {
	if (substr($file, 0, 1) != '.') {
	    if (is_file($include = $dir . DS . $file)) {
		require_once $include;
	    }
	}
    }
}

//Manually include files.
require_once 'routes.php';
?>