<?php
if ($argc == 1) {
  die("Syntax: ".$argv[0]." [filename.conf]\n");
}
$conffile = $argv[1];
// defines and load the magic.. WPIMEX
define( 'WPIMEX_DIR', '/var/www/wp3/' );
require_once("WPIMEX.class.php");

$conf = WPIMEX::loadconf($conffile);
if (!$conf) { die("Error loading conf-file\n"); }

mysql_connect($conf['EX_DBHOST'], $conf['EX_DBUSER'], $conf['EX_DBPASS']);
mysql_select_db($conf['EX_DBBASE']) or die("Could not select db!");
$blog_ID = $conf['EX_BLOG_PREFIX'];

function comments($blog_id, $post_ID) {
	$str = null;
	$sql = sprintf("SELECT * FROM %s_comments WHERE comment_post_ID='%s'", $blog_id, $post_ID);
	$q = mysql_query($sql);
	if ( mysql_num_rows($q) > 0 ) {
		while ($r = mysql_fetch_array($q)) {
			$str .= sprintf("<comment ID='%s' post_ID='%s' date='%s' date_gmt='%s' approved='%s' parent='%s'>\n", $r['comment_ID'], $r['comment_post_ID'], $r['comment_date'], $r['comment_date_gmt'], $r['comment_approved'], $r['comment_parent']);
			$str .= sprintf("<author user_id='%s' email='%s' url='%s' IP='%s'><![CDATA[%s]]></author>\n", $r['user_id'], $r['comment_author_email'], $r['comment_author_url'], $r['comment_author_IP'], $r['comment_author'] );
			$str .= sprintf("<content><![CDATA[%s]]></content>\n", $r['comment_content']);
			$str .= sprintf("<karma><![CDATA[%s]]></karma>\n", $r['comment_karma'] );
			$str .= sprintf("<agent><![CDATA[%s]]></agent>\n", $r['comment_agent'] );
			$str .= sprintf("<type><![CDATA[%s]]></type>\n", $r['comment_type'] );
			$str .= sprintf("</comment>\n");
		}
	}
	return $str;
}

function tags($blog_id, $post_ID) {
	$str = "<tags>\n";
	$sql = sprintf("SELECT %s_terms.term_id, %s_terms.slug, %s_terms.name FROM %s_term_taxonomy INNER JOIN %s_terms ON %s_terms.term_id=%s_term_taxonomy.term_id WHERE %s_term_taxonomy.taxonomy='post_tag' AND %s_term_taxonomy.term_taxonomy_id IN (SELECT term_taxonomy_id FROM %s_term_relationships WHERE object_id=%s)", $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $post_ID);
	$q = mysql_query($sql);
	if (mysql_num_rows($q) > 0) {
		while ($r = mysql_fetch_array($q)) {
			$str .= sprintf("\t\t<tag id='%s' slug='%s'><![CDATA[%s]]></tag>\n", $r['term_id'], $r['slug'], $r['name']);
		}
	}
	$str .= "</tags>\n";
	return $str;
}

function categories($blog_id, $post_ID) {
	$str = "<categories>\n";
	$sql = sprintf("SELECT %s_terms.term_id, %s_terms.slug, %s_terms.name FROM %s_term_taxonomy INNER JOIN %s_terms ON %s_terms.term_id=%s_term_taxonomy.term_id WHERE %s_term_taxonomy.taxonomy='category' AND %s_term_taxonomy.term_taxonomy_id IN (SELECT term_taxonomy_id FROM %s_term_relationships WHERE object_id=%s)", $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $blog_id, $post_ID);
	$q = mysql_query($sql);
	if (mysql_num_rows($q) > 0) {
	  while ($r = mysql_fetch_array($q)) {
			$str .= sprintf("\t\t<category id='%s' slug='%s'><![CDATA[%s]]></category>\n", $r['term_id'], $r['slug'], $r['name']);
		}
	}
	$str .= "</categories>\n";
	return $str;
}

function get_username($user_ID) {
	$str = null;
	$sql = sprintf("SELECT user_nicename FROM wp_users WHERE ID='%s' LIMIT 1", $user_ID);
	$q = mysql_query($sql);
	if (mysql_num_rows($q) > 0) {
		$r = mysql_fetch_array($q);
		return $r['user_nicename'];
	} else {
		return null;
	}
}

function get_usermail($user_ID) {
  $str = null;
	$sql = sprintf("SELECT user_email FROM wp_users WHERE ID='%s' LIMIT 1", $user_ID);
	$q = mysql_query($sql);
	if (mysql_num_rows($q) > 0) {
		$r = mysql_fetch_array($q);
		return $r['user_email'];
	} else {
		return null;
	}
}

function post($blog_id, $post_ID) {
	$str = null;
	$sql = sprintf("SELECT * FROM %s_posts WHERE ID='%s' LIMIT 1", $blog_id, $post_ID);
	$q = mysql_query($sql);
	if (mysql_num_rows($q) > 0) {
		$r = mysql_fetch_array($q);
		$str .= sprintf("<post ID='%s' name='%s' status='%s' date='%s' date_gmt='%s' modified='%s' modified_gmt='%s' parent='%s' menu_order='%s'>\n", $r['ID'], $r['post_name'], $r['post_status'], $r['post_date'], $r['post_date_gmt'], $r['post_modified'], $r['post_modified_gmt'], $r['post_parent'], $r['menu_order']);
		$str .= sprintf("\t<title><![CDATA[%s]]></title>\n", $r['post_title']);
		$str .= sprintf("\t<author id='%s'>\n", $r['post_author']);
		$str .= sprintf("\t\t<name><![CDATA[%s]]></name>\n", get_username($r['post_author']));
		$str .= sprintf("\t\t<email><![CDATA[%s]]></email>\n", get_usermail($r['post_author']));
		$str .= sprintf("\t</author>\n");
		$str .= sprintf("\t<ping status='%s' pinged='%s' to_ping='%s' />\n", $r['ping_status'], $r['pinged'], $r['to_ping']);
		$str .= sprintf("\t<content><![CDATA[%s]]></content>\n", $r['post_content']);
		$str .= sprintf("\t<filtered><![CDATA[%s]]></filtered>\n", $r['post_content_filtered']);
		$str .= sprintf("\t<excerpt><![CDATA[%s]]></excerpt>\n", $r['post_excerpt']);
		$str .= sprintf("\t<guid><![CDATA[%s]]></guid>\n", $r['guid']);
		$str .= sprintf("\t<password><![CDATA[%s]]></password>\n", $r['post_password']);
		$str .= sprintf("\t<type mime='%s'><![CDATA[%s]]></type>\n", $r['post_mime_type'], $r['post_type']);
		$str .= categories($blog_id, $post_ID);
		$str .= tags($blog_id, $post_ID);
		$str .= sprintf("\t<comments status='%s' count='%s'>\n", $r['comment_status'], $r['comment_count']); 
		$str .= comments($blog_id, $post_ID);
		$str .= sprintf("\t</comments>\n");
		$str .= sprintf("</post>\n");
	}
	return $str;
}

// Exportera alla poster inklusive taggar, kategorier och kommentarer.
$sql = sprintf("SELECT ID FROM %s_posts ORDER BY ID", $blog_ID);
$q = mysql_query($sql);
if ( mysql_num_rows($q) > 0) {
	echo "<posts>\n";
	while ($r = mysql_fetch_array($q)) {
		echo post($blog_ID, $r['ID']);
	}
	echo "</posts>\n";
}
echo "\n";
