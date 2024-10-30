	<?php
	/*
	Bar Chart
	*/
	defined ('IPV_URL') or die();

	global $wpdb, $pagenow;
	$period = isset($ipv_log_keep_days) ? $ipv_log_keep_days : 14;

	?>

		<?php
		$posts = $wpdb->get_results(
			"SELECT time FROM ".IPV_TABLE_LOGS.
			// " WHERE DATE(time) > DATE(NOW() - INTERVAL ".$period." DAY)".
			" ORDER BY time DESC");
		$total_hits = count($posts) + get_option('ipv_count_blocked', 0);

		echo '<p>';
		if ($total_hits < 1) {
			if ($period == 0) {
				echo 'Logging disabled. ';
			} else {
				echo 'Blocked requests will show up here. ';
			};
			if ( $pagenow === 'index.php')
				echo '<a href="' . admin_url( 'admin.php?page=ipvault' ) . '">' . __('Settings') . '</a>';
			return;
		} else {
			echo '<big>' . $total_hits . '</big> malicious requests blocked. ';
			if ( $pagenow === 'index.php') echo '<a href="' . admin_url( 'admin.php?page=ipvault&tab=stats' ) . '">' . __('More info') . '</a>';
		}
		echo '</p>';

		$stats = [];
		$max_hits_per_day = 0;

		foreach( $posts as $post) {
		  $date = $post->time;

			$date = get_date_from_gmt($post->time, 'Y-m-d');

			if (!array_key_exists($date, $stats)) {
				$stats[$date] = 0;
			}
			$stats[$date] += 1;

		  $max_hits_per_day = max($max_hits_per_day, (int) @$stats[$date]);
		  $total_hits += 1;
		}
		// $width = round(100/count($stats));
		?>


	<div class="chart-container" style="padding:5px 3px 0 2px">
	  <div class="chart-grid">
			<div></div>
			<div></div>
			<div></div>
	  </div>
	  <?php
		$i=0;
	  foreach( $stats as $day => $hits ) {
	    $height = (int) (105 * (int) $hits / $max_hits_per_day);
	    // $hits = ''; // $height > 15 ? $hits : '';
			echo '<a
							class="bar"
							style="height:'.$height.'%;"
							title="'. date_i18n('l d/m', strtotime($day)) .' : '.$hits.' '.__('hits', 'ipv').'"
							href="'.admin_url( 'admin.php?page=ipvault&tab=stats&day='.$i ).'">';
			// echo '<div>'. $stats[$day] . '</div>';
			echo "</a>";
			$i++;
	  }
	  ?>
	</div>
	<div class="chart-xaxis">
	  <?php foreach( $stats as $day => $hits) : ?>
	    <div><?php echo date('d', strtotime($day))  ?></div>
	  <?php endforeach; ?>
	</div>
