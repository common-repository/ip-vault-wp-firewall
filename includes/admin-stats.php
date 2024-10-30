<?php
/*
Logs & Stats Tab
*/
defined ('IPV_URL') or die();

global $wpdb;
$ip = $this->get_ip();
$ipv_log_keep_days = get_option('ipv_log_keep_days');

$now = current_time( 'timestamp', true );

// $day = $_GET('day') || $now;
?>

<?php

include 'admin-chart.php';



$where = isset($_GET['day']) ? ' WHERE DATE(time) = DATE(NOW() - INTERVAL '.$_GET['day'].' DAY)' : '';
// echo $where;


$posts = $wpdb->get_results("SELECT * FROM " . IPV_TABLE_LOGS . $where . " ORDER BY id DESC LIMIT 250");
if (!empty($posts)) :
?>
  <br>
  <h2>Hits on <?php echo get_date_from_gmt ( $posts[0]->time, get_option('date_format') ); ?></h2>
  <table class="wp-list-table widefat fixed striped ips plugins">
    <thead>
      <tr>
        <th scope="col" id="Time" class="column-time">Time</th>
        <th scope="col" id="User" class="column-ip">Origin</th>
        <th scope="col" id="Request" class="column-request">Request</th>
        <th scope="col" id="Log" class="column-log hide-on-mobile">Log</th>
      </tr>
    </thead>
    <tbody>

  <?php
  // print_r($posts[0]);
  foreach( $posts as $post) :
    $datetime = get_date_from_gmt ( $post->time, get_option('time_format') );
    $active = ($ip === $post->ip) ? 'active' : 'inactive';
  ?>
    <tr class="<?php echo $active ?>">
      <th class="check-column">
        <?php printf( __( '%s ago' ), human_time_diff( $now, strtotime( $post->time ) ) ); ?>
        <?php echo '<div class="muted text-small">'.$datetime.'</div>'; ?>
      </th>
      <td>
        <?php if ($post->city) echo $post->city . ', ' ?>
        <?php echo Locale::getDisplayRegion("-".$post->country_code, get_locale()) ?>
        <?php echo '<div class="muted text-small">'.$this->gdpr_ip($post->ip.' – '.$post->as_number).'</div>'; ?>
      </td>
      <td class="column-request">
        <?php echo $post->request; ?>
        <?php echo '<div class="muted text-small hide-on-mobile">'.$post->headers.'</div>'; ?>
      </td>
      <td class="column-log hide-on-mobile">
        <?php echo $post->log; ?>
        <?php echo '<div class="muted text-small">'.$post->post.'</div>'; ?>
      </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
<?php endif; ?>
<?php $this->gdpr_notice(); ?>
