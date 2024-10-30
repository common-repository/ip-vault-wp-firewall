<?php
/* Template Name: IPVault Auth Page */

defined ('IPV_URL') or die();

ipv_check_submission();


function ipv_check_submission() {

  global $msg, $msg_classes, $show_form, $action, $error, $ipv;

  // Honeypot : bots like to set a value for pwd.
  // pwd set ? get out ! ಠ‿ಠ
  if (isset($_POST['pwd']) && $_POST['pwd'] !== '') {
    $ipv->log('pwdSet');
    exit;
  }

  // javaScript : most bots don't execute js.
  // no js ? get out ! ಠ‿ಠ
  // if (isset($_GET['nojs'])) {
  //   $ipv->log('no js');
  //   wp_die( __('We use javaScript to filter out bad bots. Please enable javaScript in your browser.', 'ipv') );
  // }

  // Form submitted more than 12 hrs ago ?
  if ( $_POST && (!isset($_POST['ipvnonce']) || wp_verify_nonce($_POST['ipvnonce'], 'ipv_auth_form_submit') !== 1 ) ) {
    $msg = __( 'Nonce invalid or expired.', 'ipv' );
    $msg_classes = 'error';
    print_page();
    $ipv->log('invalidNonce');
    return;
  }
  
  // Register form requested ?
  if ( isset($_POST['form']) && $_POST['form'] === 'register' ) {

    $ipv->log('register');

    function catch_register_errors( $user_login, $user_email, $error ) {

      global $msg, $msg_classes, $show_form;

      if( is_wp_error( $error ) && ! empty( $error->errors ) ) {
        $msg = $error->get_error_message();
        $msg_classes = 'error';
        $show_form = 'register';
        print_page();
        exit;
      }
    }
    add_action( 'register_post', 'catch_register_errors', 10, 3 );

    include ABSPATH . 'wp-login.php';
    exit;

  }

  if ( isset($_GET['action']) && $_GET['action'] === 'register' ) {
    $show_form = 'register';
    print_page();
    return;
  }

    

  // GOT PIN ?
  if ( isset( $_POST['user_pin_1'] ) ) {
      
    $user_pin = sanitize_text_field( $_POST['user_pin_1'] . $_POST['user_pin_2'] . $_POST['user_pin_3'] . $_POST['user_pin_4'] );

    ipv_verify_key( $user_pin, sanitize_text_field( $_POST['pin'] ), sanitize_text_field( $_POST['origin'] ), sanitize_text_field( $_POST['user_login'] ) );      

               
  }

  // Login Form submitted ?
  elseif( isset( $_POST['log'] ) ) {
      
    $username = sanitize_text_field($_POST['log']);

    $user = get_user_by( 'login', $username );

    if (!$user)
      $user = get_user_by( 'email', $username );

    if (!$user) {
      $msg = 'User unknown.';
      $msg_classes = 'error';
      $show_form = 'login';
      print_page();
      $ipv->log('invalidUser');
      exit;

    } else {
      ipv_send_auth_mail($user);
      $ipv->log('mail sent to: '.$user->user_email);
      exit;
    }

  }

  // Show login form
  else {

    $show_form = 'login';
    // $msg = '<div>' . __('Please verify your account.', 'ipv') . '</div>';
    print_page();
    // $ipv->log('auth');
    exit;
  }

}


function ipv_verify_key($user_pin, $pin, $origin, $user_login) {

  global $msg, $msg_classes, $ipv, $show_form;

  // $user_login = isset($_POST['user_login']) ? $_POST['user_login'] : $user_login;

  $ip = $ipv->get_ip();
  $whitelist = get_option('ipv_whitelist');
  $user = get_user_by( 'login', $user_login );

  if (!$user || is_wp_error($user)) {

    if ( ! $ipv->is_whitelisted($ip, $whitelist) ) {

      $msg = __('User unknown. ip:'.$ip.' user_login:'.$user_login.' user:'.$user, 'ipv');
      $msg_classes = 'error';

      print_page();
      $ipv->log('invalidUser');
      exit;
    }

  }
      
  if ( $user_pin !== $pin ) {
      
      $msg = __("Invalid Pin.", 'ipv');
      $show_form = 'pin';
      
  } else {
      
      $msg = __("User <b>$user_login</b> validated.", 'ipv');

      $whitelist = $ipv->add_ip_to_whitelist($ip, $whitelist, $user_login, 'authMail');

      $ipv->maybe_update_htaccess($whitelist);

      $redirect = ( $origin !== '' ) ? get_home_url() . $origin : get_dashboard_url($user->ID);

      function redirect_after_5() {
        global $redirect;
        echo '<meta http-equiv="refresh" content="2;url='.$redirect.'" />';
        echo '<style>.logo-container { animation: rotate 2s normal forwards ease-in-out; }</style>';
      }
      add_action('auth_head', 'redirect_after_5');     
      
  }

  print_page();
  exit;

}


function ipv_send_auth_mail($user) {

  global $msg, $msg_classes, $show_form, $user_login, $pin;

  // $unlock_code = get_password_reset_key( $user );
  // $pin = substr($unlock_code, -4);
  $pin = substr( str_shuffle( str_repeat( 'ABCDEFGHJKLMNOPQRSTUVWXYZ0123456789', 4 ) ), 0, 4 );

  $to =  isset($user->first_name) ? $user->first_name .' ' : '';
  $to .= isset($user->last_name) ? $user->last_name .' ' : '';
  $to .= '<'.$user->user_email.'>';
    

  // $headers[] = "From: ".get_option('admin_email');
  $headers[] = "Content-Type: text/plain; charset=UTF-8";

  $subject =  __('Your pin code is ', 'ipv') . $pin;

  $body  = __('Your temporary pin code is ', 'ipv') . $pin ."\r\n";
  $body .= "\r\n";
  $body .= "-- \r\n";
  $body .= __('Powered by two-factor authentication for WordPress.', 'ipv');

  $mailresult = wp_mail( $to, $subject, $body, $headers );

  if ($mailresult)
  {
    $msg = '<div style="display:flex; align-items: center;">' .
      '<div style="margin-right:1em; margin-top: 6px;"><img width="50em" src="'.IPV_URL.'assets/images/envelope.svg" /></div>' .
      '<div>' . __('Authentication mail has been sent. Please check your inbox. ', 'ipv') . '</div>' .
      '</div>';
    $show_form = 'pin';
    $user_login = $user->user_login;
  } else
  {
    $msg = __('Error occured while sending mail', 'ipv');
    $msg_classes = 'error';
  }
  print_page();
  exit;
}



// function ipvauth_enqueue_style() {
//   // $wp_styles->queue = array();
//   // $wp_scripts->queue = array();
//   wp_enqueue_style( 'ipvauth', IPV_URL . 'assets/css/auth-page.css', false, IPV_VERSION );
// }


function print_page() {

  global $msg, $msg_classes, $show_form, $user_login, $origin, $pin;

  // add_action( 'login_enqueue_scripts', 'ipvauth_enqueue_style' );

  $origin = isset($_POST['origin']) ? sanitize_text_field($_POST['origin']) : $_SERVER['REQUEST_URI'];
  $pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : $pin;

  ob_start();

  ?><!DOCTYPE html>
  <html <?php language_attributes(); ?>>

  	<head>
      <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />

      <title>2FA &lsaquo; <?php echo get_bloginfo() ?></title>

  	  <meta name='robots' content='noindex, nofollow' />
  	  <meta name='referrer' content='strict-origin-when-cross-origin' />
      <meta name="viewport" content="width=device-width" />

      <style media="screen">
        <?php include IPV_PATH . 'assets/css/auth-page.css' ?>
      </style>

      <?php do_action('auth_head') ?>

  	</head>

  	<body class="login login-action-login wp-core-ui">
      <?php //do_action('auth_header'); print_r($r) ?>
      <div id="login">

          <div class="lockring-top">
          </div>

          
        <?php if ($show_form === 'login') : ?>
          

          <!-- <p class="title"><?php // echo $ipv->get_ip() ?></p> -->

            <form name="loginform" id="loginform" method="post">

                <p class="site-title">
                  <a href="<?php echo get_home_url() ?>"><?php echo get_bloginfo() ?></a>
                </p>

                <p><?php _e('The requested page is protected. Please enter your username to continue.') ?></p>

                <input type="text" name="log" id="user_login" autofocus aria-describedby="login_error" class="input" value="" size="20" placeholder="<?php _e('Username or E-mail:') ?>" />
                
                <input type="hidden" name="pwd" />
                
                <?php wp_nonce_field( 'ipv_auth_form_submit', 'ipvnonce' ); ?>
                
                <?php do_action( 'auth_form' ) ?>
                
                <button disabled type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large">
                    <?php _e('Send me the Key !', 'ipv') ?>
                </button>

                <p id="backtoblog">
                  <div class="">
                    <small>
                    <a href="<?php echo get_home_url() ?>"><?php echo __('Back to Home', 'ipv') ?></a>
                      <?php if ( get_option( 'users_can_register' ) ) echo ' | <a href="?action=register">'.__('Register').'</a>'; ?>
                    </small>
                  </div>
                </p>

            </form>

        
        <?php elseif ($show_form === 'pin') : // $show_form ?>
        

          <form name="loginform" id="loginform" method="post">
              
            <input
              name="user_pin_1" id="user_pin_1" autofocus aria-describedby="pin_error"
              class="input pin"
              style="font-size: 3em; text-align: center; width:19%"
              value=""
              size="1">
            </input>
            <input
              name="user_pin_2" id="user_pin_2" aria-describedby="pin_error"
              class="input pin"
              style="font-size: 3em; text-align: center; width:19%"
              value=""
              size="1">
            </input>
            <input
              name="user_pin_3" id="user_pin_3" aria-describedby="pin_error"
              class="input pin"
              style="font-size: 3em; text-align: center; width:19%"
              value=""
              size="1">
            </input>
            <input
              name="user_pin_4" id="user_pin_4" aria-describedby="pin_error"
              class="input pin"
              style="font-size: 3em; text-align: center; width:19%"
              value=""
              size="1">
            </input>
            <input type="hidden" name="pwd" />
            <input type="hidden" name="pin" value="<?php echo $pin ?>"></input>
            <input type="hidden" name="origin" value="<?php echo $origin ?>"></input>
            <input type="hidden" name="user_login" value="<?php echo $user_login ?>"></input>

            <?php wp_nonce_field( 'ipv_auth_form_submit', 'ipvnonce' ); ?>

            <button disabled type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large">
              <?php _e('Request access !', 'ipv') ?>
            </button>

          </form>



        <?php elseif ($show_form === 'register') : // $show_form ?>


          <p class="title"><?php _e( 'Register For This Site' ) ?></p>

          <form name="registerform" id="registerform" method="post">

            <input type="text" name="user_login" id="user_login" autofocus class="input" placeholder="<?php _e('Username') ?>" value="<?php if (isset($_POST['user_login'])) echo esc_attr( wp_unslash( $_POST['user_login'] ) ); ?>" />
            <input type="email" name="user_email" id="user_email" class="input" placeholder="<?php _e('E-mail') ?>" value="<?php if (isset($_POST['user_email'])) echo esc_attr( wp_unslash( $_POST['user_email'] ) ); ?>" />
            <input type="hidden" name="pwd" />
            <?php wp_nonce_field( 'ipv_auth_form_submit', 'ipvnonce' ); ?>
            <input type="hidden" name="redirect_to" value="<?php echo get_option('ipv_auth_slug', 'ipvauth') ?>?checkemail=registered" />
            <input type="hidden" name="form" value="register" />
            <?php

            /**
             * Fires following the 'Email' field in the user registration form.
             *
             * @since 2.1.0
             */
            do_action( 'register_form' );

            ?>
            <button type="submit" class="button button-primary button-large" id="register">
              <?php _e( 'Register', 'ipv' ); ?>
            </button>

            <p id="backtoblog">
              <?php _e('Already got an account? ', 'ipv') ?><a href="?action=login"><?php _e('Login', 'ipv') ?></a>
            </p>
          </form>    


        <?php endif; // $show_form ?>


        <?php if ($msg != '') echo '<div class="message '.$msg_classes.'">'.$msg.'</div>'; ?>

  		</div>

      <?php do_action( 'auth_footer' ) ?>

  	</body>

    <script type="text/javascript">

      const buttonElm = document.querySelector('button#wp-submit')

      const inputElm = document.querySelector('input#user_login')
      inputElm && inputElm.addEventListener('input', e => {
        buttonElm.disabled = e.target.value.length < 2
      })

      const pinElms = document.querySelectorAll('.input.pin')
      pinElms && pinElms.forEach((pinElm) => {

        pinElm.addEventListener('input', e => {

          pinElm.value = pinElm.value.charAt(0)

          let pincode = ''
          pinElms.forEach((item, i) => {
            pincode += item.value
          });
          // console.log(pincode);
          buttonElm.disabled = pincode.length !== 4

          // console.log(e);
          const nextElm = e.inputType === 'deleteContentBackward'
            ? e.target.previousElementSibling
            : pincode.length < 4 ?
              e.target.nextElementSibling
              : e
          nextElm.focus()
        })

      });
    </script>

  </html>

  <?php
  ob_end_flush();
}

?>
