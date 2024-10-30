<?php
/*
Settings Tab
*/
defined ('IPV_URL') or die();

global $ipv_notices, $ipv, $wpdb;

// echo '<pre>';
// print_r($_SERVER);
// print_r($_REQUEST);
// echo '</pre>';

function ipv_save_changes() {

	global $ipv, $ipv_notices, $wpdb;


	$update_htaccess = false;


	$input = sanitize_text_field( $_POST['update_keep'] );
	$log_keep_days = abs( intval( $input )) ;

	if ( $log_keep_days != get_option('ipv_log_keep_days') ) {

			update_option( 'ipv_log_keep_days', $log_keep_days );

			$ipv->clear_logs();
	}


	$modus = sanitize_text_field( $_POST['modus'] );


	if ( $modus !== get_option('ipv_modus') ) {

		if ( get_option('ipv_modus') === 'hard' ) $ipv->maybe_update_htaccess();

		update_option( 'ipv_modus', $modus );

		if ( $modus === 'hard' ) $update_htaccess = true;

	}


	$input = sanitize_textarea_field( $_POST['request_includes'] );
	$input = array_filter(preg_split( '/\r\n|[\r\n]/', $input ));

	if ( $input !== get_option('ipv_request_includes') ) {
		update_option( 'ipv_request_includes', $input );
		$ipv_notices[] = array(
			'msg' => __('Request includes updated.', 'ipv'),
			'classes' => 'success auto-dismiss'
		);
		$update_htaccess = true;
	}


	$input = sanitize_textarea_field( $_POST['request_excludes'] );
	$input = array_filter(preg_split( '/\r\n|[\r\n]/', $input ));

	if ( $input !== get_option('ipv_request_excludes') ) {
		update_option( 'ipv_request_excludes', $input );
		$ipv_notices[] = array(
			'msg' => __('Request excludes updated.', 'ipv'),
			'classes' => 'success auto-dismiss'
		);
		$update_htaccess = true;
	}


	$input = sanitize_title($_POST['auth_slug']);

	if ( $input !== get_option('ipv_auth_slug') ) {
		update_option( 'ipv_auth_slug', $input );
		$ipv_notices[] = array(
			'msg' => __('Authentication page slug updated.', 'ipv'),
			'classes' => 'success auto-dismiss'
		);
		$update_htaccess = true;
	}


	$do_gdpr = isset( $_POST['gdpr_ips'] ) ? 'on' : 'off';

	if ($do_gdpr !== get_option('ipv_gdpr_ips')) {
		update_option( 'ipv_gdpr_ips', $do_gdpr );
	}


	$do_use_asn = isset( $_POST['use_asn'] ) ? 'on' : 'off';

	if ($do_use_asn !== get_option('ipv_use_asn')) {
		update_option( 'ipv_use_asn', $do_use_asn );
	}


	if ( isset($_POST['home_path']) ) {

			$home_path = sanitize_text_field( $_POST['home_path'] );

			if ( $home_path !== get_option('ipv_home_path') ) {

				$ipv_notices[] = array(
					'msg' => 'htaccess path changed from '.get_option('ipv_home_path').' to '.$home_path,
					'classes' => 'success auto-dismiss'
				);

				$ipv->maybe_update_htaccess();
				update_option( 'ipv_home_path', $home_path );
				$update_htaccess = true;
			}

	}


	if ($update_htaccess) {

		$ipv->maybe_update_htaccess( get_option('ipv_whitelist') );

	}


} // end save_changes()



if ( isset($_POST['submit']) ) {

	ipv_save_changes();

}



if ( isset($_POST['clear_registered_logs']) ) {

	$ips = sprintf("'%s'", implode("','", array_keys( get_option('ipv_whitelist') ) ) );

	$wpdb->query(
			"
			 DELETE FROM ".IPV_TABLE_LOGS."
			 WHERE ip IN (". $ips .")
			"
	);
	
	$ipv_notices[] = array(
		'msg' => 'User log entries cleared.',
		'classes' => 'success auto-dismiss'
	);

}



// $ip = $ipv->get_ip();
// $http = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,message,countryCode,city,as");
// $data = wp_remote_retrieve_body( $http );
// echo('<pre>'.$data.'</pre>');


?>


		<div>
		<?php foreach ( (array) $ipv_notices as $key => $value ) : ?>
			<div class="notice notice-<?php echo $value['classes'] ?>">
				<p><?php echo $value['msg'] ?></p>
			</div>
		<?php endforeach; ?>
		</div>

		<pre>
			<?php 
			// echo 'modus = ' . get_option('ipv_modus') . '<br>' . $_POST['modus'];
			// print_r($_POST); 
			?>
		</pre>

			<form method="post">

				<h2>Firewall Settings</h2>

				<table class="form-table" role="presentation"><tbody>

				<tr>
				<th scope="row">
					<label for="modus" class="label">Plugin state</label>
				<td>
					<select id="modus" name="modus">
						<option value="off" <?php if (get_option('ipv_modus') == 'off') echo "selected" ?>>Off – 2FA is disabled</option>
						<option value="soft" <?php if (get_option('ipv_modus') == 'soft') echo "selected" ?>>Soft</option>
					    <option value="hard" <?php if (get_option('ipv_modus') == 'hard') echo "selected" ?>>Hard (depreciated)</option>
				  	</select>
					  <p>Soft : redirects unauthorized requests using <em>url_rewrite</em>. Very reliable. Default state.</p>
					  <p>Hard : uses <em>.htaccess</em>. This state saves ressources as redirects happen before WP Core is loaded. May not work on all installs.</p>
				</td>
				</tr>

				<tr>
				<th scope="row">
					<label for="use_asn" class="label">Whitelist ASNs</label>
				<td>
					<input type="checkbox" name="use_asn" <?php if (get_option('ipv_use_asn') === 'on') echo "checked" ?> class=""> Use ASN.
					<p>Experimental feature : Extend whitelist to authorized IP addresses' entire ASN. Useful in case your IP address is changing frequently. Less secure. Default: uncheck.</p>
				</td>
				</tr>

				<?php if (get_option('ipv_modus') === 'hard') : ?>
				<tr>
				<th scope="row"><label for="home_path" class="label">Main <em>.htaccess</em> Path</label></th>
				<td>
					<input type="text" name="home_path" id="home_path" value="<?php echo esc_url (get_option('ipv_home_path')) ?>" class="regular-text code">
					<input type="submit" name="set_path" data-path="<?php echo $this->get_home_path(); ?>" id="set_home_path" class="button action" value="Set to Site Home" />
					<input type="submit" name="set_path" data-path="<?php echo ABSPATH; ?>" id="set_wp_path" class="button action" value="Set to WordPress" />
					<p>Path to main <code>.htaccess</code> file.</p>
					<br>
				</td>
				</tr>
				<?php endif; ?>



				<tr>
				<th scope="row"><label for="request_includes" class="label">Restrict access to</label><p class="description">Files and folders to REJECT public access from, one per line.</p></th>
				<td>
					<textarea rows="4" class="regular-text code" name="request_includes"><?php echo esc_textarea (implode("\n", get_option('ipv_request_includes')) ) ?></textarea>
					<p>Note: Partial names are allowed, e.g. <code>.php</code> will block access to all PHP files. <code>/wp-</code> will block access to all files and folders starting with <code>wp-</code>. 
					Default: <code>.php</code> & <code>wp-admin/</code>
					<p>


				</td>
				</tr>

				<tr>
				<th scope="row"><label for="request_excludes" class="label">Exceptions</label><p class="description">Files and folders within restricted folders to ALLOW public access from, one per line.</p></th>
				<td>
					<textarea rows="4" class="regular-text code" name="request_excludes"><?php echo esc_textarea( implode("\n", get_option('ipv_request_excludes')) ) ?></textarea>
				</td>
				</tr>

				<tr>
					<th><h2>Misc Settings</h2></th>
				</tr>


				<tr>
				<th scope="row">
					<label for="auth_slug" class="label">Authentication page slug</label></th>
				<td>
					<input type="text" name="auth_slug" value="<?php echo get_option('ipv_auth_slug') ?>" class="regular-text code">
					<p class="description">Check link:
						<a href="<?php echo home_url( get_option('ipv_auth_slug') ) ?>" target="_blank">
						<?php echo home_url( get_option('ipv_auth_slug') ) ?>
						</a>
					</p>
				</td>
				</tr>


				<tr>
				<th scope="row"><label for="update_keep" class="label">Keep logs for</label></th>
				<td><input type="number" name="update_keep" value="<?php echo get_option('ipv_log_keep_days') ?>" class="short-text" min="0" max="356"> days
				<p class="description">To disable logging, enter 0 (zero).</p>
				<br>
				<form method="post">
					<input type="submit" name="clear_registered_logs" class="button action" value="Clear registered user’s log entries." />
				</form>

				</td>



				</tr>

				<tr>
				<th scope="row"><label for="gdpr_ips" class="label">Anonymize IPs</label>
				<td><input type="checkbox" name="gdpr_ips" <?php if (get_option('ipv_gdpr_ips') === 'on') echo "checked" ?> class=""> Check me for GDPR compliancy.
				</td>
				</tr>


				</tbody></table>


				<?php submit_button() ?>

			</form>

			<script type="text/javascript">
				var set_path_buttons = document.getElementsByName("set_path");
				set_path_buttons.forEach((item) => {
					item.onclick = function(event) {
						event.preventDefault();
						document.getElementById("home_path").value = event.target.getAttribute('data-path');
					};
				});
			</script>



	<?php
