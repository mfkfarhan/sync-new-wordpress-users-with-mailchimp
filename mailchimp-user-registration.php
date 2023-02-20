<?php
/*
Plugin Name: Sync new users / updated user profiles with MailChimp 
Plugin URI: https://github.com/mfkfarhan/sync-new-wordpress-users-with-mailchimp
Description: Sends new WordPress user registrations to Mailchimp and also updates to existing users. Made specially for LJU by EMAK Solution
Version: 1.0
Author: Muhamamd Farhan Khan
Author URI: https://github.com/mfkfarhan
License: GPL2
*/

function mailchimp_settings_page()
{
    if (isset($_POST['mailchimp_settings_submit'])) {
        update_option('mailchimp_api_key', $_POST['mailchimp_api_key']);
        update_option('mailchimp_list_id', $_POST['mailchimp_list_id']);
    }
    $api_key = get_option('mailchimp_api_key');
    $list_id = get_option('mailchimp_list_id');
    ?>
    <div class="wrap">
        <h2>Mailchimp Settings</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" name="mailchimp_api_key" value="<?php echo $api_key; ?>" class="regular-text">
                        <p class="description">Enter your Mailchimp API key.</p>
                    </td>
                </tr>
                <tr>
                    <th>Audience ID</th>
                    <td>
                        <input type="text" name="mailchimp_list_id" value="<?php echo $list_id; ?>" class="regular-text">
                        <p class="description">Enter the ID of the Mailchimp audience where you want to add the new subscribers.</p>
                    </td>
                </tr>
            </table>
            <?php wp_nonce_field('mailchimp_settings', 'mailchimp_settings_nonce'); ?>
            <p class="submit">
                <input type="submit" name="mailchimp_settings_submit" class="button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}

// Add the Mailchimp settings page to the WordPress admin menu
add_action('admin_menu', 'register_mailchimp_settings_page');
function register_mailchimp_settings_page() {
  add_options_page('Mailchimp Settings', 'Mailchimp', 'manage_options', 'mailchimp-settings', 'mailchimp_settings_page');
}

// Save the Mailchimp API key and list ID to the WordPress options table
function save_mailchimp_settings() {
  if (isset($_POST['mailchimp_api_key']) && isset($_POST['mailchimp_list_id'])) {
    update_option('mailchimp_api_key', $_POST['mailchimp_api_key']);
    update_option('mailchimp_list_id', $_POST['mailchimp_list_id']);
  }
}
add_action('admin_post_save_mailchimp_settings', 'save_mailchimp_settings');

// Send new user registration data to Mailchimp
function register_user_to_mailchimp($user_id) {
  $user = get_userdata($user_id);
  $fname = $user->first_name;
  $lname = $user->last_name;
  $bdate = get_user_meta($user_id, 'birth_date', true);
  $bdate = date("m/d", strtotime($bdate));
  $email = $user->user_email;
  $audience_id = get_option('mailchimp_list_id');
  $api_key = get_option('mailchimp_api_key');
  $url = 'https://' . substr($api_key, strpos($api_key, '-') + 1) . '.api.mailchimp.com/3.0/lists/' . $audience_id . '/members';
  $dc = substr($api_key, strpos($api_key, '-') + 1);
  $log_file = dirname(__FILE__) . '/mailchimp_log.txt';
  $log_data = date('Y-m-d H:i:s') . " - " . $fname . " " . $lname . " " . $email . " " . $bdate . "\n";
  $data = array(
    'email_address' => $email,
    'status' => 'subscribed',
    'merge_fields' => array(
      'FNAME' => $fname,
      'LNAME' => $lname,
'BIRTHDAY' => $bdate,
    ),
  );
  $json_data = json_encode($data);
  $args = array(
    'method' => 'POST',
    'timeout' => 45,
    'headers' => array(
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
    ),
    'body' => $json_data,
  );
  $response = wp_remote_request($url, $args);
  if (is_wp_error($response)) {
    error_log("Error: " . $response->get_error_message(), 0);
    $log_data .= "Error: " . $response->get_error_message() . "\n\n";
  } else {
    $log_data .= "Success: " . $response['body'] . "\n\n";
$sent_users = get_option('mailchimp_sent_users', array());
    if (!in_array($user_id, $sent_users)) {
      $sent_users[] = $user_id;
      update_option('mailchimp_sent_users', $sent_users);
  }
  }
  file_put_contents($log_file, $log_data, FILE_APPEND);
}
add_action('user_register', 'register_user_to_mailchimp');

function update_user_to_mailchimp($user_id) {
  error_log("Email: " . $email, 0);
  $user = get_userdata($user_id);
  $fname = $user->first_name;
  $lname = $user->last_name;
  $bdate = get_user_meta($user_id, 'birth_date', true);
  $bdate = date("m/d", strtotime($bdate));
  $email = $user->user_email;
  $audience_id = get_option('mailchimp_list_id');
  $api_key = get_option('mailchimp_api_key');
  $url = 'https://' . substr($api_key, strpos($api_key, '-') + 1) . '.api.mailchimp.com/3.0/lists/' . $audience_id . '/members';
  $dc = substr($api_key, strpos($api_key, '-') + 1);
  $log_file = dirname(__FILE__) . '/mailchimp_log.txt';
  $log_data = date('Y-m-d H:i:s') . " - " . $fname . " " . $lname . " " . $email . " " . $bdate . "\n";
  $data = array(
    'merge_fields' => array(
      'FNAME' => $fname,
      'LNAME' => $lname,
'BIRTHDAY' => $bdate,
    ),
  );
  $json_data = json_encode($data);
  $args = array(
    'method' => 'PUT',
    'timeout' => 45,
    'headers' => array(
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
    ),
    'body' => $json_data,
  );
  $response = wp_remote_request($url, $args);
  if (is_wp_error($response)) {
    error_log("Error: " . $response->get_error_message(), 0);
    $log_data .= "Error: " . $response->get_error_message() . "\n\n";
  } else {
    $log_data .= "Success: " . $response['body'] . "\n\n";
$sent_users = get_option('mailchimp_sent_users', array());
    if (!in_array($user_id, $sent_users)) {
      $sent_users[] = $user_id;
      update_option('mailchimp_sent_users', $sent_users);
  }
  }
  file_put_contents($log_file, $log_data, FILE_APPEND);
}
add_action('profile_update', 'update_user_to_mailchimp', 10, 2);

// Add admin notice for Mailchimp sent users count
function mailchimp_sent_users_count_notice() {
  $sent_users_count = count(get_option('mailchimp_sent_users', array()));
  if ($sent_users_count > 0) {
    printf('<div class="notice notice-success"><p>%d users have been successfully sent and updated to Mailchimp.</p></div>', $sent_users_count);
  }
}
add_action('admin_notices', 'mailchimp_sent_users_count_notice');
