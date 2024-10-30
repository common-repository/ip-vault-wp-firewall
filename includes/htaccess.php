<?php
/*
.htaccess functions
*/

function get_htaccess_file($home_path) {

  global $ipv_notices, $ipv_notices_classes;

  $htaccess_file = trailingslashit($home_path) . '.htaccess';

  if ( ! file_exists( $htaccess_file ) && ! is_writable( $home_path ) ) {
    $ipv_notices[] = array(
      'msg' => 'no <pre>.htaccess</pre> file not found and home path not writable.',
      'classes' => 'warning'
    );
    return false;
  }

  if ( file_exists( $htaccess_file ) && ! is_writable( $htaccess_file ) ) {
    $ipv_notices[] = array(
      'msg' => '<pre>.htaccess</pre> file in home directory not writable.',
      'classes' => 'warning'
    );
    return false;
  }
  return $htaccess_file;
}


function updateHtaccess( $whitelist = [] ) {

  global $ipv, $ipv_notices;

  if ( get_option('ipv_modus') == 'soft' && !empty($whitelist) ) return true;

  if ( is_multisite() ) {
    $ipv_notices[] = array(
      'msg' => 'htaccess for multisite not yet supported',
      'classes' => 'error'
    );
    return false;
  }

  $home_path = get_option('ipv_home_path', $ipv->get_home_path() );
  $htaccess_file = get_htaccess_file($home_path);
  if ( !$htaccess_file ) return false;


  // got_mod_rewrite, insert_with_markers
  require_once ABSPATH . 'wp-admin/includes/misc.php';

  if ( empty($whitelist) ) return insert_with_markers( $htaccess_file, 'IPVault', array() );




  $mod_rewrite_rules = [
    // '<IfModule mod_rewrite.c>',
    'RewriteEngine On',
  ];

  function preg_quote_ex($str) {
    return '.*' . preg_quote( trim( $str ), '/' );
  }

  $request_includes = get_option('ipv_request_includes');
  $request_excludes = get_option('ipv_request_excludes');

  if ( !empty($request_includes) ) {
    $includes = implode( '|', array_map( 'preg_quote_ex', $request_includes ) );
    $mod_rewrite_rules[] = "RewriteCond %{REQUEST_URI} ($includes)";
  }
  if ( !empty($request_excludes) ) {
    $excludes = implode( '|', array_map( 'preg_quote_ex', $request_excludes ) );
    $mod_rewrite_rules[] = "RewriteCond %{REQUEST_URI} ^(?!$excludes)";
  }


  foreach( $whitelist as $entry ) {
    if (isset($entry['ip'])) {
      $mod_rewrite_rules[] = 'RewriteCond %{REMOTE_ADDR} !^' . preg_quote($entry['ip']);
    }
  }
  $redirect = get_option('ipv_auth_slug', 'ipvauth');
  $mod_rewrite_rules[] = 'RewriteRule . /'.$redirect.'?origin=%{REQUEST_URI} [R=307,L,QSA]';
  // $mod_rewrite_rules[] = '</IfModule>';


  if (!$htaccess_file) {

    $ipv_notices[] = array(
      'msg' => 'Unable to write to <code>'.$home_path.'.htaccess</code>.<br>These lines could not be written :<pre>'.implode('<br>', $mod_rewrite_rules).'</pre>',
      'classes' => 'warning'
    );
    return false;
  }

  if (!got_mod_rewrite()) {

    $ipv_notices[] = array(
      'msg' => 'Mod_rewrite not supported',
      'classes' => 'error'
    );
    return false;
  }

  return insert_with_markers( $htaccess_file, 'IPVault', $mod_rewrite_rules );

}

?>
