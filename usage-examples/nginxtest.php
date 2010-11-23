<?php
if ($argc == 1) {
  die("Syntax: ".$argv[0]." [filename.conf]\n");
}
$conffile = $argv[1];

// defines and load the magic.. WPIMEX
define( 'WPIMEX_DIR', '/var/www/wp3/' );
require_once("WPIMEX.class.php");

$conf = WPIMEX::loadconf($argv[1]);
if (!$conf) { die("Error loading conf-file\n"); }

// bootstrap the WordPress environment
$_SERVER['HTTP_HOST'] = $conf['MAIN_BLOG'];
require_once( WPIMEX_DIR."wp-load.php");
require_once( WPIMEX_DIR."wp-includes/registration.php");

global $switched, $wpdb;
switch_to_blog($conf['SWITCH_TO_BLOG']);

echo WPIMEX::get_nginx_conf();

