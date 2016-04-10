<?php
// May be included multiple times per request.
// Ensure that every expression is static.
// Do not close the PHP tag else lots of extra whitespace may be produced.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

// This is where flags are stored so that they can't be accessed
// by viewing the source. Do not attempt to access this directory.
$GLOBALS['FLAGS_DIR'] = 'flagsflagsflagsflagsflagsflagsflagsflags';