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

// bootstrap the WordPress environment
$_SERVER['HTTP_HOST'] = $conf['MAIN_BLOG'];
require_once( WPIMEX_DIR."wp-load.php");
require_once( WPIMEX_DIR."wp-includes/registration.php");

WPIMEX::logger("Loading file ".$conf['XML_BLOG_DUMP'], LOG_INFO, null, null);
$xml = simplexml_load_file($conf['XML_BLOG_DUMP']);
WPIMEX::logger("Loaded file ".$conf['XML_BLOG_DUMP'], LOG_INFO, null, null);

global $switched, $wpdb;
switch_to_blog($conf['SWITCH_TO_BLOG']);

if ( count($xml->post) > 0 ) {

	foreach( $xml->post as $p ) {
		$mem_start = memory_get_usage();
		if ($p['status'] == "publish" and $p->type=="post") {
			$cat = array();
			$tags = null;
			echo "=============\n";
			echo "==> status=".$p['status']." type=".$p->type." comments=".$p->comments['count']." title=".$p->title."\n";

			foreach ( $p->categories->category as $c) {
				$cat[] = WPIMEX::create_category((string)$c, $cat);
			}

			foreach ($p->tags->tag as $t) {
				$tags .= $t.",";
			}
			unset($t);
			$tags = ','.substr($tags, 0, -1).',';
			WPIMEX::logger("Tags: $tags", LOG_INFO, null, null);
	
			// TODO: Kolla om fälten innehåller data
			$userdata = array(
				'user_login' => (string)$p->author->name,
				'user_nicename' => (string)$p->author->name,
				'user_email' => (string)$p->author->email,
			);
			$post = array(
				'menu_order' => (string)$p['menu_order'],
				'comment_status' => (string)$p->comments['status'],
				'ping_status' => (string)$p->ping['status'],
				'pinged' => (string)$p->ping['pinged'],
				'post_author' => WPIMEX::insert_user($userdata),
				'post_category' => $cat,
				'post_content' => (string)$p->content,
				'post_date' => (string)$p['date'],
				'post_date_gmt' => (string)$p['date_gmt'],
				'post_excerpt' => (string)$p->excerpt,
				'post_password' => (string)$p->password,
				'post_status' => (string)$p['status'],
				'post_title' => (string)$p->title,
				'post_type' => (string)$p->type,
				'tags_input' => $tags,
				'to_ping' => (string)$p->ping['to_ping'],
			);
			$postid = @wp_insert_post($post);
			WPIMEX::logger("Created post with id $postid", LOG_INFO, null, null);
			add_post_meta($postid, '_wpimex_import_id', (string)$p['ID']);
			// Comments
			foreach ( $p->comments->comment as $c ) {
				$comment = array(
					'comment_post_ID' => $postid,
					'comment_author' => (string)$c->author,
					'comment_author_email' => (string)$c->author['email'],
					'comment_author_url' => (string)$c->author['url'],
					'comment_content' => (string)$c->content,
					'comment_type' => (string)$c->type,
					'comment_parent' => 0,
					'user_id' => 0,
					'comment_author_IP' => (string)$c->author['IP'],
					'comment_agent' => (string)$c->agent,
					'comment_date' => (string)$c['date'],
					'comment_date_gmt' => (string)$c['date_gmt'],
					'comment_approved' => (string)$c['approved'],
				);
				$com = WPIMEX::create_comment($comment);
				WPIMEX::logger("Created comment with id $com", LOG_INFO, null, null);
			}
		}
	}
} else {
	echo "No posts.\n";
}
