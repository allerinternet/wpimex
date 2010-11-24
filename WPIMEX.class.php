<?php
define("DEBUG", true);

if (!defined('LOG_INFO')) define('LOG_INFO', 0);
if (!defined('LOG_WARN')) define('LOG_WARN', 1);
if (!defined('LOG_ERR')) define('LOG_ERR', 2);
if (!defined('LOG_DEBUG')) define('LOG_DEBUG', 3);
if (!defined('LOG_NOTICE')) define('LOG_NOTICE', 4);

if ( (int)ini_get('memory_limit') < 512 ) {
  $msg = "Please note that you might have problems with WPIMEX if you dont change your memory_limit to at least 512 MB in your php.ini file. Check with your Linux distribution how this can be solved.";
  WPIMEX::logger($msg, LOG_NOTICE, null, "Loading WPIMEX.class");
}

class WPIMEX {

  /**
    Logs events

    @param $message The message to log
    @param $type The log type, LOG_INFO, LOG_WARN, LOG_ERR, LOG_DEBUG, LOG_NOTICE
    @param $destination Where to log, NOT IMPLEMENTED YET
    @param $function The function name that logs

    @return string The log message
  */
  public static function logger($message, $type = LOG_INFO, $destination = null, $function = null) {
    if (mb_strlen($message) <= 0) return false;
    $output = null;
    switch ($type) {
      case LOG_INFO:
        $output = "ii> "; break;
      case LOG_WARN:
        $output = "ww> "; break;
      case LOG_ERR:
        $output = "ee> "; break;
      case LOG_DEBUG:
        $output = "dd> "; break;
      case LOG_NOTICE:
        $output = "nn> "; break;
    }
    if ($function) $output .= "(".$function.") ";
    $output .= $message;
    
    // here we gonna fix with destination
    print $output."\n";

    return $output."\n";
  }

  public static function get_user_by_mail(&$mail ) {
	global $wpdb;
	$r = $wpdb->get_row("SELECT ID FROM $wpdb->users WHERE user_email='$email' LIMIT 1");
	return $r->ID;
  }

  public static function insert_user(&$userdata) {
	global $wpdb;
	return wp_insert_user($userdata);
  }

  public static function exist_category($category) {
    global $wpdb;
    $sql = sprintf("SELECT term_id FROM %s WHERE name='%s' LIMIT 1", $wpdb->terms, $category);
    $res = $wpdb->get_row($sql);
    return $res->term_id;
  }

  public static function create_category($category) {
    global $wpdb;

    $c = self::exist_category($category);
    
    if (!$c) {
      $ic = wp_insert_term($category, 'category');
      if ($ic->error_data) {
        $c = $ic->error_data['term_exists'];
          self::logger("Term $category exists!", LOG_WARN, null, __METHOD__);
      } else {
        $c = $ic['term_id'];
        $tmp = "Term $category (id=$c) created"; 
        self::logger($tmp, LOG_INFO, null, __METHOD__);
      }
    }
    return $c;
  }

  public static function create_comment(&$c) {
	return wp_insert_comment($c);
  }

  /**
    Check if a table do exist in db

    @param $table The name of the table
    @return bool [true|false]
  */
  public static function db_check_table($table) {
    global $wpdb;
    $sql = sprintf("SHOW TABLES LIKE '%s'", $table);
    if (DEBUG) self::logger($sql, LOG_INFO, null, __METHOD__);
    $t = $wpdb->get_results($sql);
    return ($t) ? true : false;
  }

  /**
    Do replace in db fields
    $ra = array( 'table' => '', 'field' => '', 'find' => '', 'replace' => '' )

    @param $ra
    @return bool

  */
  public static function db_replace($ra) {
    foreach ($ra as $r) {
    	if (!$r['table'] || !$r['field'] || !$r['find'] || !$r['replace']) return false;
	$sql = sprintf("UPDATE %s SET %s=REPLACE(%s, %s, %s)", $r['table'], $r['field'], $r['field'], $r['find'], $r['replace']);
	if (DEBUG) self::logger($sql, LOG_INFO, null, __METHOD__);
	if (!db_check_table($r['table'])) {
	  self::logger("No such table ".$r['table'], LOG_ERR, null, __METHOD__);
	  return false;
	}
    }
  }

  /**
    Get blog ID

    @param $blog [int|string] The ID or blogname to find ID for
    @return int blog_id
  */
  public static function get_blog($blog) {
    global $wpdb;
    if (is_int($blog)) return $blog;
    $sql = sprintf("SELECT blog_id FROM %s WHERE domain='%s' AND deleted='0' AND archived='0' LIMIT 1", $wpdb->blogs, $blog);
    if (DEBUG) self::logger($sql, LOG_INFO, null, __METHOD__);
    $b = $wpdb->get_results($sql);
    return $b->blog_id;
  }

  /**
  * Clean up (delete) the imported posts
  *
  */
  public static function cleanup($blog) {
    global $wpdb;
    $sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wpimex_import'";
    $posts = $wpdb->get_results($sql);
    foreach ($posts as $p) {
      self::logger("Deleting postmeta for ".$p->post_id, LOG_INFO, null, __METHOD__);
      delete_post_meta($p->post_id, '_wpimex_import');
      delete_post_meta($p->post_id, '_wpimex_import_id');
      self::logger("Deleting post ".$p->post_id, LOG_INFO, null, __METHOD__);
      wp_delete_post($p->post_id, true);
    }

  }

  /**
  * Clean up all post of post_type post in the database
  * for the blog
  *
  * @return void 
  */
  public static function susclean() {
    global $wpdb;
    $sql = "select ID from $wpdb->posts where post_type='post'";
    $sus = $wpdb->get_results($sql);
    foreach ($sus as $s){
      if (DEBUG) printf("id=%s\n", $s->ID);
      wp_delete_post($s->ID, true);
     }
  }

  /**
        * Load the configuration file
        *
        * @param $conf_file The configuration file to load
        * @return array with conf or false
  */
  function loadconf($conf_file) {
        global $version;
        $fp = @fopen($conf_file, "r");
        if (!$fp) return FALSE;
        while (!feof($fp)) {
                $line = trim(fgets($fp));
                if ($line && !ereg("^#", $line)) {
                        $content = explode("=", $line, 2);
                        $option = trim($content[0]);
                        $value = trim($content[1]);
                        $conf[$option] = $value;
                }
        }
        fclose($fp);
        return $conf;
  }

  /**
    Export WP-polls to XML-format
  */
  public static function polls_export() {
    global $wpdb;
    $sql = sprintf("SELECT * FROM %s ORDER BY pollq_id", $wpdb->prefix."_pollsq");
    self::logger($sql, LOG_INFO, null, __METHOD__);
    $pollsq = $wpdb->get_results($sql);
    var_dump($pollsq);
    if ($pollsq > 0) {
      foreach ($pollsq as $pq) {
        printf("%s\n", $pq->pollsq_question);
      }
    } else {
      self::logger(_("There is no polls"), LOG_INFO, null, null);
    }
  }

  /**
  * Update all posts in WordPress database table
  *
  * @return int Number of found posts
  */
  public static function update_posts() {
    global $wpdb;
    $sql = sprintf("SELECT ID, post_content FROM %s", $wpdb->posts);
    $q = $wpdb->get_results($sql);
    $count = count($q);

    foreach ($q as $p) {
      $m = array();
      $m['ID'] = $p->ID;
      $m['post_content'] = (string)$p->post_content;

      if (!wp_update_post($m)) {
        $msg = sprintf("Error updating post %s in blog %s", $p->ID, $wpdb->blogid);
        self::logger($msg, LOG_ERR, null, __METHOD__);
      } else {
        $msg = sprintf("Updated post %s in blog %s", $p->ID, $wpdb->blogid);
        self::logger($msg, LOG_INFO, null, __METHOD__);
      }
    }
    return $count;
  }

  /**
  * Get a nginx conf file for your installed sites/blogs in WordPress
  *
  * @params $ip Your IP-adress on server
  *
  * @return [string|int] String with configuration or int with fail code
  */
  public static function get_nginx_conf($ip = "127.0.0.1", $port = 80, $root = "", 
      $errorlog = "", $location = "", $location_root = "", $includes = null) {
    global $wpdb;
    if ($ip == null) return -1;

    $sql = sprintf("SELECT blog_id, domain FROM %s", $wpdb->blogs);
    $res = $wpdb->get_results($sql);
    if (count($res) > 0) {
       $conf = null;
       foreach ($res as $serv) {
         $conf .= "server {\n";
         $conf .= sprintf("\tlisten\t\t%s:%s;\n", $ip, $port);
         $conf .= sprintf("\tserver_name\t%s;\n", $serv->domain);
         $conf .= sprintf("\troot\t\t%s;\n", $root);
         $conf .= sprintf("\terror_log\t%s%s error;\n", $errorlog, $serv->domain);
         $conf .= sprintf("\tlocation %s {\n", $location);
         $conf .= sprintf("\t\troot %s%s/;\n", $location_root, $serv->blog_id);
         $conf .= "\t}\n";
	 // TODO: Use an array as param and foreach them here..
         $conf .= "\tinclude wordpress.conf;\n";
         $conf .= "\tinclude timthumb.conf;\n";
         $conf .= "}\n";
         $conf .= "\n";    
       }
    } else {
      return -2;
    }
    return $conf;
  } // get_nginx_conf()

}


