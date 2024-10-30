<?php
/*
 * @package ip-vault
 */
/*
Plugin Name: Two-factor authentication (formerly IP Vault)
Plugin URI: https://youtag.lu/ip-vault
Description: 2FA protects your WordPress installation against exploits by monitoring requests to sensitive files and folders and resticting access to whitelisted IP addresses.
Version: 2.1
Author: Paul C. Schroeder
Author URI: https://youtag.lu/
License: GPLv2 or later
Text Domain: ip-vault
*/

defined ('ABSPATH') or die('Nope!');

global $wpdb;

define ( 'IPV_VERSION', '2.1' );
define ( 'IPV_URL', plugin_dir_url( __FILE__ ) );
define ( 'IPV_PATH', plugin_dir_path( __FILE__ ) );
define ( 'IPV_TABLE_LOGS', $wpdb->prefix . 'ipv_logs' );

class IPVault
{
	function __construct() {
		add_action( 'admin_menu', array( $this, 'options_page' ));
		// add_action( 'admin_init', array( $this, 'register_settings' ));
		add_action( 'login_footer', array( $this, 'action_login_footer' ));
		add_action( 'wp_login', array( $this, 'set_last_login' ));
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widgets' ));
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_style' ));
		// add_action('wp_enqueue_scripts', 'ipv_load_scripts');
		add_action( 'parse_request', array( $this, 'ipv_slugs' ));
		add_action( 'plugins_loaded', array( $this, 'redirect_if_request_needs_identification' ));
		add_action( 'register_new_user', array( $this, 'on_user_register' ));
		add_action( 'ipv_cron', array( $this, 'daily_tasks' ));

		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'ipv_add_plugin_page_settings_link' ));

	}
	function activate() {

		$request_includes = ['.php', '/wp-admin/'];
		$request_excludes = ['/wp-admin/admin-ajax.php'];
		$logs_keep_days = 7;

		add_option( 'ipv_request_includes', $request_includes);
		add_option( 'ipv_request_excludes', $request_excludes);
		add_option( 'ipv_auth_slug', '2fa' );
		add_option( 'ipv_log_keep_days', $logs_keep_days);
		add_option( 'ipv_home_path', $this->get_home_path() );
		add_option( 'ipv_gdpr_ips', 'off' );
		add_option( 'ipv_use_asn', 'off' );
		add_option( 'ipv_modus', 'soft' );
		add_option( 'ipv_count_blocked', 0 );

		$ip = $this->get_ip();
		$currentuser = wp_get_current_user();

		$whitelist = get_option('ipv_whitelist', []);

		$action = 'User address – generated on activation';
		$whitelist = $this->add_ip_to_whitelist($ip, $whitelist, $currentuser->user_login, $action);

		$action = 'Server address – generated on activation';
		$whitelist = $this->add_ip_to_whitelist($_SERVER['SERVER_ADDR'], $whitelist, $currentuser->user_login, $action);

		if (get_option('ipv_modus') === 'hard') $this->updateHtaccess($whitelist);

		if (! wp_next_scheduled ( 'ipv_cron' )) {
			wp_schedule_event( time(), 'daily', 'ipv_cron' );
		}

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE ".IPV_TABLE_LOGS." (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			ip varchar(45) NOT NULL,
			country_code varchar(2) NOT NULL,
			city varchar(20) NOT NULL,
			as_number varchar(50) NOT NULL,
			log varchar(100) DEFAULT '' NOT NULL,
			request varchar(100) DEFAULT '' NOT NULL,
			headers varchar(100) DEFAULT '' NOT NULL,
			post varchar(100) DEFAULT '' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	}


	function deactivate() {
		// remove rules from htaccess
		if (get_option('ipv_modus') === 'hard') $this->updateHtaccess();

		wp_clear_scheduled_hook( 'ipv_cron' );

		// Remove auth page
		// still here for compatibility -- cleaning up after v0.4
		if ( get_option('ipv_auth_page_id') ) {
			wp_delete_post( $ipv_auth_page_id, true );
			delete_option( 'ipv_auth_page_id' );
		}
	}


	function ipv_add_plugin_page_settings_link( $links ) {
		array_unshift($links, '<a href="' . admin_url( 'admin.php?page=ipvault' ) . '">' . __('Settings') . '</a>');
		return $links;
	}


	function clear_logs() {

		global $wpdb;

		$count = $wpdb->query(
				"
				 DELETE FROM ".IPV_TABLE_LOGS."
				 WHERE DATE(time) < DATE(NOW() - INTERVAL ".get_option('ipv_log_keep_days')." DAY)
				"
		);
		if ($count > 0) {
			$total = $count + get_option('ipv_count_blocked');
			update_option('ipv_count_blocked', $total);
		}

		$wpdb->query("OPTIMIZE TABLE ".IPV_TABLE_LOGS);
	}


	function daily_tasks() {

		if (get_option('ipv_log_keep_days') > 0)
			$this->clear_logs();
	}


	function scheduled_tasks() {

	    add_action( 'ipv_cron', array( $this, 'run_cron' ) );

	    if (! wp_next_scheduled ( 'ipv_cron' )) {
	        wp_schedule_event( time(), 'daily', 'ipv_cron' );
	    }
	}


	function on_user_register($user_id){
		$ip = $this->get_ip();
		$user = get_user_by('id', $user_id);
		$whitelist = get_option('ipv_whitelist', []);
		$action = 'User registration';
		$whitelist = $this->add_ip_to_whitelist($ip, $whitelist, $user->user_login, $action);
	}


	function ipv_slugs($wp) {

		if ( $wp->request === get_option( 'ipv_auth_slug' ) ) {
			http_response_code(404);
			include plugin_dir_path( __FILE__ ) . 'includes/auth-page.php';
			exit;
		}
	}


	function str_in_arr($str, array $arr) {
		foreach($arr as $a) {
			if (stripos($str, $a) !== false) return true;
		}
		return false;
	}


	function is_whitelisted($ip, $whitelist) {

		if ( get_option('ipv_use_asn') === 'on' ) {

			$as = $this->ipinfo($ip)->as;

			foreach( $whitelist as $entry) {

				$white_as = isset( $entry['ipinfo'] ) ? $entry['ipinfo']->as : 'ipinfo not set';

				if ( $white_as === $as ) return true;

			}

			return false;

		}

		$white_ips = array_keys($whitelist);

		foreach ($white_ips as $white_ip) {
			if ( substr( $ip, 0, strlen($white_ip) ) === $white_ip ) {
				return true;
			}
		}

		return false;
	}


	function redirect_if_request_needs_identification() {

		// using htaccess for validation ? return !
		if (get_option( 'ipv_modus' !== 'soft' )) return;


		$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$includes = get_option('ipv_request_includes');
		$excludes = get_option('ipv_request_excludes');

		// request allowed ? return !
		if ( ! $this->str_in_arr($request, $includes) || $this->str_in_arr($request, $excludes) ) return;

		// request is restricted !
		// we need to check if IP is whitelisted
		// do_action( 'qm/debug', 'request is restricted ! '.$request );

		$ip = $this->get_ip();
		$whitelist = get_option('ipv_whitelist');

		// IP or partial IP in whitelist ? return !
		if ( $this->is_whitelisted( $ip, $whitelist ) ) return;


		// echo $ip.'<pre>';
		// print_r($whitelist);
		// exit;
		// request not whitelisted !
		// do_action( 'qm/debug', 'request not whitelisted ! '.$ip );

		// redirect request to auth page

		http_response_code(404);
		include plugin_dir_path( __FILE__ ) . 'includes/auth-page.php';
		exit;

	}


	function options_page() {
		$icon = get_option('ipv_modus') === 'off' ? 'dashicons-unlock' : 'dashicons-lock';
		add_menu_page( 'Two-factor authentication', '2FA', 'manage_options', 'ipvault', [$this, 'options_page_html'], $icon, 76);
	}


	function load_admin_style(){

		global $pagenow;
		if ( !in_array( $pagenow, ['index.php', 'admin.php'] ) ) return;

		wp_register_style( 'custom_wp_admin_css', IPV_URL . 'assets/css/admin.css', false, IPV_VERSION );
		wp_enqueue_style( 'custom_wp_admin_css' );
	}


	function action_login_footer() {
	    echo '
			<div style="text-align: center; padding: 3rem 0; margin-top: 3rem;">
				<small>' . __('Protected by Two-factor authentication for WordPress', 'ipv') . '</small>
			</div>
			';
	}


	function get_ip() {

		$ip = $_SERVER['REMOTE_ADDR'];

		// if ip is in IPv6 format, map it to IPv4 format
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

			$key = 'ipv_ipv4_'.sanitize_title($ip);
			$transient = get_transient( $key );

			if ( !empty( $transient ) ) return $transient;

			$ip = file_get_contents('https://api.ipify.org');
			set_transient( $key, $ip, DAY_IN_SECONDS );

		}

		return $ip;
	}


	function gdpr_ip( $ip ) {

		if ( get_option('ipv_gdpr_ips') !== 'on' )
			return $ip;

		if ( preg_match('/:/', $ip) ) {
			// found char : 								looks like an IPv6 address
			// replace part after last : char
			return preg_replace('/([^:]+$)/', '*', $ip);
		}
		// assuming it is IPv4
		// replace part after last . char
		return preg_replace('/([^\.]+$)/', '*', $ip);
	}


	function log($log) {

		global $wpdb;

		$logs_keep_days = get_option('ipv_log_keep_days');
		if ($logs_keep_days == 0) {
			// no logging : just update counter and return
			update_option( 'ipv_count_blocked', get_option('ipv_count_blocked') + 1 );
			return;
		}

		$ip = $this->get_ip();

		$ipinfo = $this->ipinfo($ip);
		$country_code = isset($ipinfo->countryCode) ? $ipinfo->countryCode : '_P';
		$city = isset($ipinfo->city) ? $ipinfo->city : '';
		$as_number = isset($ipinfo->as) ? $ipinfo->as : '';

		$headers = $_SERVER['HTTP_USER_AGENT'] ?? '';

		$request =
			isset($_GET['origin']) ? esc_url( $_GET['origin'] ) : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		$request = urldecode($request);

		$post = '';
		foreach ($_POST as $n => $v) {
			$n = preg_replace( '/[^a-zA-Z0-9?=&_-]/', '', $n );
			$v = preg_replace( '/[^a-zA-Z0-9?=&_-]/', '', $v );
	  	$post .= "$n=$v ";
		}


		$time = current_time( 'mysql', true );

		$query = array(
						'time' 				=> $time,
						'ip' 				=> $ip,
						'country_code' 		=> substr($country_code, 0, 2),
						'city' 				=> substr($city, 0, 20),
						'as_number' 		=> substr($as_number, 0, 50),
						'log' 				=> substr($log, 0, 100),
						'request' 			=> substr($request, 0, 100),
						'headers' 			=> substr($headers, 0, 100),
						'post' 				=> substr($post, 0, 100)
					);

		$result_check = $wpdb->insert(
				IPV_TABLE_LOGS,
				$query
		);
		// echo '<br>insert id:'.$wpdb->insert_id;
		// echo '<br>result:'.$result_check;
		// $wpdb->print_error();
		// var_dump( $wpdb->last_error);
		// var_dump( $wpdb->last_query);

	}


	function get_home_path() {

	  // Ensure get_home_path() is declared.
	  require_once ABSPATH . 'wp-admin/includes/file.php';

	  $home_path     = get_home_path();

	  if ($home_path == '/') {
	    $home_path = $_SERVER['DOCUMENT_ROOT'];
	  }

	  return $home_path;
	}


	function maybe_update_htaccess($whitelist = []) {

		global $msg, $msg_classes;

		// echo '<pre>'; print_r($msg); echo '</pre>';

		if (get_option('ipv_modus') === 'hard') {

			include_once plugin_dir_path( __FILE__ ) . "includes/htaccess.php";

			$success = updateHtaccess($whitelist);

			if ($success) {

				$msg = '.htaccess updated !';
				$msg_classes = 'success auto-dismiss';

			} else {

				$msg = '.htaccess update failed. Try <em>soft rewrite</em> mode instead.';
				$msg_classes = 'error';

			}

			if ( is_admin() ) {

				global $ipv_notices;
				$ipv_notices[] = array( 'msg' => $msg, 'classes' => $msg_classes );

			}

		}

	}


	function ipinfo($IPaddress) {

		$transient = get_transient( 'ipv_ip_'.sanitize_title($IPaddress) );

		if ( !empty( $transient ) )
	    return $transient;

		$http = wp_remote_get("http://ip-api.com/json/{$IPaddress}?fields=countryCode,as,city");
		$json = wp_remote_retrieve_body( $http );
		$ipinfo = json_decode( $json );

		set_transient( 'ipv_ip_'.$IPaddress, $ipinfo, DAY_IN_SECONDS );

		return $ipinfo;
	}


	function add_ip_to_whitelist($ip, $whitelist, $user_login, $action) {

		if ( $ip !== '' && !array_key_exists($ip, $whitelist) ) {
			// add submitted ip to whitelist
			$whitelist[$ip] = array(
				'ip'			=> $ip,
				'user'			=> $user_login,
				'date_added'	=> current_time('mysql', true),
				'auth'			=> $action,
				'ipinfo'		=> $this->ipinfo($ip)
			);
			update_option('ipv_whitelist', $whitelist);
		}
		return $whitelist;
	}


	function admin_header( $tabs, $current_tab = 'settings' ) {

		?>
		<div id="header-logo">
			<h1>Two-factor authentication <span class="muted"><?php echo IPV_VERSION ?></span></h1>
		</div>

		<?php global $request; echo $request; ?>

	  <h2 class="nav-tab-wrapper">
			<?php
		  foreach( $tabs as $tab => $name ){
		      $class = ( $tab == $current_tab ) ? ' nav-tab-active' : '';
		      echo "<a class='nav-tab$class' href='?page=ipvault&tab=$tab'>$name</a>";

		  }
			?>
		</h2>
		<br>
		<?php
	}


	function options_page_html() {

		if (!current_user_can('manage_options')) {
			return;
		}

		$tabs = array( 'settings' => 'Settings', 'whitelist' => 'Whitelist', 'stats' => 'Logs & Stats' );

		$current_tab = isset($_GET['tab']) && array_key_exists( $_GET['tab'], $tabs ) ? $_GET['tab'] : 'settings';

		ob_start();

		$this->admin_header($tabs, $current_tab);

		echo '<div class="wrap">';

		include "includes/admin-$current_tab.php";

		echo '</div>';

		ob_end_flush();
	}


	function gdpr_notice() {

		if ( get_option('ipv_gdpr_ips') === 'on' ) echo '<p class="muted">' . __('The last part of IP addresses are hidden for GDPR privacy compliancy.', 'ipv') . '</p>';

	}


	function dashboard_widgets() {
		global $wp_meta_boxes;
		wp_add_dashboard_widget('ipv_widget', 'Two-factor authentication', [$this, 'dashboard_help']);
	}


	function dashboard_help() {
		include 'includes/admin-chart.php';
	}


	function set_last_login($login) {

		$user = get_user_by('login', $login);
		$whitelist = get_option('ipv_whitelist');
		$userMeta = get_user_meta($user->ID, 'session_tokens', true);

		if (!empty($userMeta)) {

			$lastLoginMeta = array_pop($userMeta);
			$ip = @$lastLoginMeta['ip'];

			if (!empty($whitelist[$ip])) {
				// get tokens from last session
				$lastLoginMeta = array_pop($userMeta);
				$lastLoginMeta['user'] = $login;
				$whitelist[$ip]['last_session'] = $lastLoginMeta;
				update_option('ipv_whitelist', $whitelist);
			}
			// update_usermeta( $user->ID, 'last_login', current_time('timestamp', 1) );
		}
	}


}



if ( class_exists('IPVault') ) {
	$ipv = new IPVault();
}

// activation
register_activation_hook( __FILE__, array( $ipv, 'activate') );

// deactivation
register_deactivation_hook( __FILE__, array( $ipv, 'deactivate') );

// uninstall
// See uninstall.php
