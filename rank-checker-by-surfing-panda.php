<?php
/*
Plugin Name: Rank Checker by Surfing Panda
Plugin URI: http://surfingpanda.com/rank-checker-wordpress-plugin/
Description: This plugin will track your Search Engine ranking for all the keywords which bring visitors to your website.
Version: 0.22
Author: Michael Smith
Author URI: http://surfingpanda.com/about-me/
*/

global $sp_rc_db_version;
$sp_rc_db_version = "1.1";

register_activation_hook( __FILE__, 'sp_rc_install' );
register_deactivation_hook(__FILE__, 'sp_rc_deactivate');
add_action('sp_rc_daily_event_hook', 'sp_rc_daily_tasks');
add_action('admin_enqueue_scripts', 'sp_rc_admin_css');
add_action('admin_menu', 'sp_rc_menu');
add_action('admin_init', 'sp_rc_register_settings');
add_action('wp_head', 'sp_rc_display_script');
add_action('init', 'sp_rc_request_handler');

function sp_rc_display_script() {
    $current_time = time();
    $jquery_url = includes_url( "/js/jquery/jquery.js");
    echo '
    <script type="text/javascript" src="' . $jquery_url . '"></script>
    <script type="text/javascript">
    jQuery(document).ready(function($){
      $.ajax({
        type : "GET",
        url : "index.php",
        data : { sp_rc_action : "get_search_keyword_data", 
                 sp_rc_ref : encodeURIComponent(document.referrer),
                 sp_rc_url : window.location.pathname},
        success : function(response){
        }
      });
    });
    </script>
    ';
}

function sp_rc_menu() {
    add_menu_page('Rank Checker', 
            'Rank Checker', 'administrator',
            'rank-checker-by-surfing-panda', 'sp_rc_dashboard_page', 
            plugins_url( 'rank-checker-by-surfing-panda/images/surfing-panda-icon-16x16.png' ));
    
    add_options_page('rank-checker-by-surfing-panda', 'Dashboard', 'Dashboard', 
            'administrator', 'rank-checker-by-surfing-panda', 'sp_rc_dashboard_page');
    add_submenu_page('rank-checker-by-surfing-panda', 'Keyword Report', 'Keyword Report', 
            'administrator', 'keyword_report', 'sp_rc_keyword_report');
}

function sp_rc_register_settings() {
    register_setting('sp_rc_option_group', 'array_key', 'sp_rc_check_num_days');
		
    add_settings_section(
        'setting_section_id',
        'Settings',
        'sp_rc_print_section_info',
        'rank-checker-by-surfing-panda'
    );
		
    add_settings_field(
    'num_days', 
    'Specify how many days of ranking data to maintain (0 will keep all data)',
    'sp_rc_create_num_days_field', 
    'rank-checker-by-surfing-panda',
    'setting_section_id'			
    );	
    
    //Remove duplicates introduced in 0.1. This function can be phased out
    //after the unique keyword index is introduced in a future version.
    global $sp_rc_db_version;
    $installed_ver = get_option( "sp_rc_db_version" );
    if( $installed_ver != $sp_rc_db_version ) {
        sp_rc_remove_duplicates();
        update_option( "sp_rc_db_version", $sp_rc_db_version );
    }        
}

function sp_rc_check_num_days($input){
    if(is_numeric($input['num_days'])){
        $days_value = $input['num_days'];			
        if(get_option('sp_rc_num_days') === FALSE){
            add_option('sp_rc_num_days', $days_value);
        } else{
            update_option('sp_rc_num_days', $days_value);
        }
        //Run the daily cron job again after the settings update
        sp_rc_daily_tasks();
    }else{
        $days_value = '';
    }
    return $days_value;
}
	
function sp_rc_print_section_info(){
    print 'You can customize the plugin behavior using the settings below:';
}
	
function sp_rc_create_num_days_field(){
    ?><input type="text" id="input_whatever_unique_id_I_want" 
    name="array_key[num_days]" value="<?=get_option('sp_rc_num_days');?>" />
    <?php
}

function sp_rc_get_search_keyword_data() {
    //Initialize $query variable
    $query = '';
      
    //sp_rc_log("DEBUG: Starting sp_rc_get_search_keyword_data . . .");
    $referrer_url = urldecode($_GET['sp_rc_ref']);
    $parsed_query = parse_url( $referrer_url, PHP_URL_QUERY );
    $parsed_domain = parse_url($referrer_url, PHP_URL_HOST);
    parse_str( $parsed_query, $query );    
        
    //sp_rc_log("Parsed Domain: " . $parsed_domain);
    //sp_rc_log("Parsed Query: " . implode("|", $query));
    if (sp_rc_starts_with($parsed_domain, "www.google.") || 
            sp_rc_starts_with($parsed_domain, "google.") ) {
        sp_rc_log("Google Referrer String: " . $referrer_url);
        $sp_rc_ranking = 0;
        $url_of_current_page = htmlspecialchars(stripslashes( (
                        (substr(get_bloginfo('url'), -1) == '/') ? substr(
                            get_bloginfo('url'), 0, -1) : 
                            get_bloginfo('url')) . 
                            $_GET['sp_rc_url']));
        if (array_key_exists('q', $query)) {
            $sp_rc_searched_keyword = htmlspecialchars(stripslashes(
                strtolower(trim($query['q']))), ENT_QUOTES);
            if ($sp_rc_searched_keyword !== "") {
                if (array_key_exists('cd', $query)) {
                    $sp_rc_ranking = htmlspecialchars(stripslashes(
                        strtolower(trim($query['cd']))), ENT_QUOTES); 
                }
            } else {
                $sp_rc_searched_keyword = "(not provided)";
            }
        } else {
            $sp_rc_searched_keyword = "(not provided)";
        }
            
        //sp_rc_log("URL of visited page: " . $url_of_current_page);
        //sp_rc_log("Keyword being searched: " . $sp_rc_searched_keyword);
        //sp_rc_log("Keyword ranking: " . $sp_rc_ranking);
        sp_rc_save_keyword_data ($sp_rc_searched_keyword,
                $url_of_current_page, $parsed_domain, $sp_rc_ranking);
    }
}

function sp_rc_remove_duplicates() {
    // This function removes duplicate keyword entries. Duplicates were being
    // generated as a result of a bug in version 0.1. Eventually a unique
    // index will be introduced and this function can be phased out
    global $wpdb;
    $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
    $duplicate_results = $wpdb->get_results( "
        SELECT MIN(id) AS oldest_id, keyword, url, search_engine, date
        FROM $sp_rc_db_tablename
        GROUP BY keyword, url, search_engine, date
        HAVING count( * ) > 1");
    if (!$duplicate_results) {
        // No duplicates found
    } else {
        foreach ( $duplicate_results as $duplicate_result ) 
        {
            $remove_result = $wpdb->query( 
                $wpdb->prepare(
                    "DELETE FROM $sp_rc_db_tablename
                    WHERE  keyword = %s AND
                        url = %s AND
                        date = %s AND
                        search_engine = %s AND
                        id != %d
                    "
                    , $duplicate_result->keyword
                    , $duplicate_result->url
                    , $duplicate_result->date
                    , $duplicate_result->search_engine
                    , $duplicate_result->oldest_id)
                );
            sp_rc_log("$remove_result rows deleted for keyword " .
                    $duplicate_result->keyword);
        }
    }
}

function sp_rc_save_keyword_data ($keyword, $url, $search_engine, $rank) {
    global $wpdb;
    $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
    $date = $today = date("Y-m-d");
    // First update the # of visits and rank if this keyword exists for this
    // url, search engine & date
    if ($rank > 0) {
        $update_results = $wpdb->query( 
            $wpdb->prepare(
		"UPDATE $sp_rc_db_tablename
		 SET visits = visits + 1,
                 rank = %d
                 WHERE  keyword = %s AND
                        url = %s AND
                        date = %s AND
                        search_engine = %s
		"
                , $rank
                , $keyword
                , $url
                , $date
                , $search_engine)
            );
    // If rank isn't available just update the number of visits
    } else {
        $update_results = $wpdb->query( 
            $wpdb->prepare(
		"UPDATE $sp_rc_db_tablename
		 SET visits = visits + 1
                 WHERE  keyword = %s AND
                        url = %s AND
                        date = %s AND
                        search_engine = %s
		"
                , $keyword
                , $url
                , $date
                , $search_engine)
            );
    }
    // If the keyword doesn't exist, add it to the database
    if ( $update_results == 0 ) {
        $insert_result = $wpdb->insert ( $sp_rc_db_tablename,
                array ('keyword' => $keyword,
                       'url' => $url,
                       'search_engine' => $search_engine,
                       'date' => $date,
                       'rank' => $rank,
                       'visits' => 1),
                array ('%s',
                       '%s',
                       '%s',
                       '%s',
                       '%d',
                       '%d')
                );
        if (!$insert_result) {
            sp_rc_log("Inserting keyword to table failed.");
        }
    }
}

function sp_rc_install() {
    global $sp_rc_db_version;
    global $wpdb;
    $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
      
    $sql = "CREATE TABLE $sp_rc_db_tablename (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword tinytext NOT NULL,
            url tinytext NOT NULL,
            search_engine tinytext NOT NULL,
            date date NOT NULL DEFAULT '0000-00-00',
            rank smallint(5) NOT NULL,
            visits mediumint(9) NOT NULL,
            UNIQUE KEY id (id)
            ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    
   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
 
   add_option( "sp_rc_db_version", $sp_rc_db_version );
   
   wp_schedule_event( time(), 'daily', 'sp_rc_daily_event_hook');
}

function sp_rc_daily_tasks() {
    $num_days = get_option('sp_rc_num_days');
    if ($num_days > 0) {
        global $wpdb;
        $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
        
        $wpdb->query( 
            $wpdb->prepare(
		"DELETE FROM $sp_rc_db_tablename
		 WHERE date < DATE_SUB(NOW(), INTERVAL %d DAY)
		"
                , $num_days )
        );
    }
}

function sp_rc_dashboard_page() {
    echo '<div class="wrap">';
    screen_icon('rank-checker-by-surfing-panda');
    echo "<h2>Rank Checker by Surfing Panda</h2>";
    
    global $wpdb;
    $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
    $keyword_results = $wpdb->get_results( "
        SELECT DISTINCT keyword FROM $sp_rc_db_tablename WHERE RANK > 0");
    if (!$keyword_results) {
        echo "You don't have any keyword data yet.  Please check again later.";
        echo "<br>Note: You must receive traffic from search engines before any" .
                " data will appear. Not all visits from search engines will " .
                " result in data as sometimes Google suppresses results for " .
                " logged in users.";
    } else {
        echo "You have received traffic for " . $wpdb->num_rows . " keywords.";
        $keyword_results = $wpdb->get_results( "
            SELECT DISTINCT search_engine FROM $sp_rc_db_tablename WHERE RANK > 0");
        echo " You haven gotten traffic from " . $wpdb->num_rows . " search " .
                "engine(s).";
    }
    ?>
    <form method="post" action="options.php">
    <?php
        settings_fields('sp_rc_option_group');	
            do_settings_sections('rank-checker-by-surfing-panda');
    ?>
    <?php submit_button(); ?>
    </form> <?php
    echo '</div>';
}

function sp_rc_keyword_report() {
    echo '<div class="wrap">';
    screen_icon('rank-checker-by-surfing-panda');
    echo "<h2>Rank Checker Keyword Report</h2>";
    
    global $wpdb;
    $sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
    $keyword_results = $wpdb->get_results( "
        SELECT o1.keyword, o1.url, o1.search_engine, o1.last_traffic_date, 
            o1.total_visits, o1.best_rank, o2.rank AS last_rank
        FROM        
            (SELECT keyword, url, search_engine, MAX(DATE) AS last_traffic_date, 
                SUM(visits) AS total_visits, MIN(rank) AS best_rank 
                FROM $sp_rc_db_tablename
                WHERE rank > 0
                GROUP BY keyword, url, search_engine ) o1
        JOIN $sp_rc_db_tablename o2 ON (o2.keyword=o1.keyword AND
                                        o2.keyword=o1.keyword AND
                                        o2.search_engine=o1.search_engine AND 
                                        o1.last_traffic_date=o2.date)
        ORDER BY o1.total_visits DESC ");
    if (!$keyword_results) {
        echo "You don't have any keyword data yet.  Please check again later.";
        echo "<br>Note: You must receive traffic from search engines before any" .
             " data will appear.  Not all search engine traffic will " .
             " result in keyword data as sometimes Google suppresses search " .
             " data from logged in users.";
    } else {
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '  <thead>';
        echo '  <tr>';
        echo '      <tr>';
        echo '          <th id="columnname" class="manage-column column-columnname" scope="col">Search Engine</th>';
        echo '          <th id="columnname" class="manage-column column-columnname" scope="col">Keyword</th>';
        echo '          <th id="columnname" class="manage-column column-columnname" scope="col">URL</th>';
        echo '          <th id="columnname" class="manage-column column-columnname num" scope="col">Total Visits</th>';
        echo '          <th id="columnname" class="manage-column column-date sortable asc" scope="col">Last Traffic Date</th>';
        echo '          <th id="columnname" class="manage-column column-columnname num" scope="col">Best Rank</th>';
        echo '          <th id="columnname" class="manage-column column-columnname num" scope="col">Last Rank</th>';
        echo '      </tr>';
        echo '  </tr>';
        echo '  </thead>';
        echo '  <tbody>';
        
        foreach ( $keyword_results as $keyword_result ) 
        {
            echo '  <tr class="alternate">';
            echo '      <td class="column-columnname">';
            echo $keyword_result->search_engine;
            echo '      </td>';
            echo '      <td class="column-columnname">';
            echo $keyword_result->keyword;
            echo '      </td>';
            echo '      <td class="column-columnname">';
            if (parse_url($keyword_result->url, PHP_URL_QUERY)) {
                $output_url = parse_url($keyword_result->url, PHP_URL_PATH) .
                        "?" . parse_url($keyword_result->url, PHP_URL_QUERY);
            } else {
                $output_url = parse_url($keyword_result->url, PHP_URL_PATH);
            }             
            echo $output_url;
            echo '      </td>';
            echo '      <td class="column-columnname num">';
            echo $keyword_result->total_visits;
            echo '      </td>';
            echo '      <td class="column-date">';
            echo $keyword_result->last_traffic_date;
            echo '      </td>';
            echo '      <td class="column-columnname num">';
            if ($keyword_result->best_rank > 0 ) echo $keyword_result->best_rank;
            echo '      </td>';
            echo '      <td class="column-columnname num">';
            if ($keyword_result->last_rank > 0 ) echo $keyword_result->last_rank;
            echo '      </td>';
            echo '  </tr>';
        }
        
        echo '  </tbody>';
        echo '</table>';
    }
    $num_days = get_option('sp_rc_num_days');
    if ($num_days > 0) {
        echo "<br>This report covers traffic from the last $num_days days.\n";
    }
    echo '</div>';
}

function sp_rc_admin_css() {
    wp_enqueue_style( 'sp-rc-admin-css', plugins_url('css/style.css', __FILE__) );
    //wp_register_style($handle = 'sp-rc-admin-css', $src = plugins_url('css/style.css', __FILE__), $deps = array(), $ver = '1.0.0', $media = 'all');
    //wp_enqueue_style('sp-rc-admin-css');
}

function sp_rc_log( $message ) {
    if( WP_DEBUG === true ){
      if( is_array( $message ) || is_object( $message ) ){
        error_log( print_r( $message, true ) );
      } else {
        error_log( $message );
      }
    }
}

function sp_rc_deactivate () {
    wp_clear_scheduled_hook('sp_rc_daily_event_hook');
}

function sp_rc_starts_with ($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

function sp_rc_request_handler() {
  sp_rc_log("DEBUG: inside sp_rc_request_handler");
  if ( isset($_GET['sp_rc_action']) && $_GET['sp_rc_action'] == 'get_search_keyword_data' ) {
    //sp_rc_log("DEBUG: calling sp_rc_get_search_keyword_data");
    sp_rc_get_search_keyword_data();
    exit();
  }
}
?>
