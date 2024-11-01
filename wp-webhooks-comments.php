<?php
/**
 * Plugin Name: WP Webhooks - Comments
 * Plugin URI: https://ironikus.com/downloads/wp-webhooks-comments/
 * Description: A WP Webhooks and WP Webhooks Pro extension for handling comments
 * Version: 1.1.0
 * Author: Ironikus
 * Author URI: https://ironikus.com/
 * License: GPL2
 *
 * You should have received a copy of the GNU General Public License.
 * If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'WP_Webhooks_Comments' ) ){

	class WP_Webhooks_Comments{

		private $wpc_use_new_filter = null;

		public function __construct() {

			if( $this->wpwh_use_new_action_filter() ){
				add_filter( 'wpwhpro/webhooks/add_webhook_actions', array( $this, 'add_webhook_actions' ), 20, 4 );
			} else {
				add_action( 'wpwhpro/webhooks/add_webhooks_actions', array( $this, 'add_webhook_actions' ), 20, 3 );
			}
			add_filter( 'wpwhpro/webhooks/get_webhooks_actions', array( $this, 'add_webhook_actions_content' ), 20 );

			// Setup triggers
			add_action( 'plugins_loaded', array( $this, 'add_webhook_triggers' ), 20 );
			add_filter( 'wpwhpro/webhooks/get_webhooks_triggers', array( $this, 'add_webhook_triggers_content' ), 20 );
		}

		/**
		 * ######################
		 * ###
		 * #### HELPERS
		 * ###
		 * ######################
		 */

		public function wpwh_use_new_action_filter(){

			if( $this->wpc_use_new_filter !== null ){
				return $this->wpc_use_new_filter;
			}

			$return = false;
			$version_current = '0';
			$version_needed = '0';
	
			if( defined( 'WPWHPRO_VERSION' ) ){
				$version_current = WPWHPRO_VERSION;
				$version_needed = '4.1.0';
			}
	
			if( defined( 'WPWH_VERSION' ) ){
				$version_current = WPWH_VERSION;
				$version_needed = '3.1.0';
			}
	
			if( version_compare( (string) $version_current, (string) $version_needed, '>=') ){
				$return = true;
			}

			$this->wpr_use_new_filter = $return;

			return $return;
		}

		/**
		 * ######################
		 * ###
		 * #### WEBHOOK ACTIONS
		 * ###
		 * ######################
		 */

		/*
		 * Register all available action webhooks here
		 *
		 * This function will add your webhook to our globally registered actions array
		 * You can add a webhook by just adding a new line item here.
		 */
		public function add_webhook_actions_content( $actions ){

			$actions[] = $this->action_create_comment_content();
			$actions[] = $this->action_update_comment_content();
			$actions[] = $this->action_trash_comment_content();
			$actions[] = $this->action_delete_comment_content();

			return $actions;
		}

		/*
		 * Add the callback function for a defined action
		 *
		 * We call the default get_active_webhooks function to grab
		 * all of the currently activated triggers.
		 *
		 * We always send three different properties with the defined wehook.
		 * @param $action - the defined action defined within the action_delete_user_content function
		 * @param $webhook - The webhook itself
		 * @param $api_key - an api_key if defined
		 */
		public function add_webhook_actions( $response, $action, $webhook, $api_key = '' ){

			//Backwards compatibility prior 4.1.0 (wpwhpro) or 3.1.0 (wpwh)
			if( ! $this->wpwh_use_new_action_filter() ){
				$api_key = $webhook;
				$webhook = $action;
				$action = $response;

				$active_webhooks = WPWHPRO()->settings->get_active_webhooks();
				$available_actions = $active_webhooks['actions'];

				if( ! isset( $available_actions[ $action ] ) ){
					return $response;
				}
			}

			$return_data = null;

			switch( $action ){
				case 'create_comment':
					$return_data = $this->action_create_comment();
					break;
				case 'update_comment':
					$return_data = $this->action_create_comment( true );
					break;
				case 'trash_comment':
					$return_data = $this->action_trash_comment( true );
					break;
				case 'delete_comment':
					$return_data = $this->action_delete_comment( true );
					break;
			}

			//Make sure we only fire the response in case the old logic is used
			if( $return_data !== null && ! $this->wpwh_use_new_action_filter() ){
				WPWHPRO()->webhook->echo_response_data( $return_data );
				die();
			}

			if( $return_data !== null ){
				$response = $return_data;
			}
			
			return $response;
		}

		public function action_create_comment_content(){

			$parameter = array(
				'comment_agent' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The HTTP user agent of the comment_author when the comment was submitted. Default empty.', 'action-create_comment-content' ) ),
				'comment_approved' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int|string) Whether the comment has been approved. Default 1.', 'action-create_comment-content' ) ),
				'comment_author' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The name of the author of the comment. Default empty.', 'action-create_comment-content' ) ),
				'comment_author_email' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The email address of the $comment_author. Default empty.', 'action-create_comment-content' ) ),
				'comment_author_IP' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The IP address of the $comment_author. Default empty.', 'action-create_comment-content' ) ),
				'comment_author_url' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The URL address of the $comment_author. Default empty.', 'action-create_comment-content' ) ),
				'comment_content' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The content of the comment. Default empty.', 'action-create_comment-content' ) ),
				'comment_date' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The date the comment was submitted. To set the date manually, comment_date_gmt must also be specified. Default is the current time.', 'action-create_comment-content' ) ),
				'comment_date_gmt' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The date the comment was submitted in the GMT timezone. Default is comment_date in the site\'s GMT timezone.', 'action-create_comment-content' ) ),
				'comment_karma' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) The karma of the comment. Default 0.', 'action-create_comment-content' ) ),
				'comment_parent' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of this comment\'s parent, if any. Default 0.', 'action-create_comment-content' ) ),
				'comment_post_ID' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of the post that relates to the comment, if any. Default 0.', 'action-create_comment-content' ) ),
				'comment_type' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) Comment type. Default empty.', 'action-create_comment-content' ) ),
				'comment_meta' => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) Optional. Array of key/value pairs to be stored in commentmeta for the new comment. More info within the description.', 'action-create_comment-content' ) ),
				'user_id' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of the user who submitted the comment. Default 0.', 'action-create_comment-content' ) ),
			);

			$returns = array(
				'success'        => array( 'short_description' => WPWHPRO()->helpers->translate( '(Bool) True if the action was successful, false if not. E.g. array( \'success\' => true )', 'action-create_comment-content' ) ),
				'data'           => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) The data related to the comment, as well as the user and the post object, incl. the meta values.', 'action-create_comment-content' ) ),
				'msg'            => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) A message with more information about the current request. E.g. array( \'msg\' => "This action was successful." )', 'action-create_comment-content' ) ),
			);

			ob_start();
			?>
            <pre>
$return_args = array(
	'success' => false,
	'msg' => '',
	'data' => array(
		'comment_id'   => 0,
		'comment_data'  => array(),
		'comment_meta'  => array(),
		'current_post_id' => 0,
		'current_post_data' => array(),
		'current_post_data_meta' => array(),
		'user_id' => 0,
		'user_data' => array(),
		'user_data_meta' => array()
	),
);
        </pre>
			<?php
			$returns_code = ob_get_clean();

			ob_start();
			?>
                <p><?php echo WPWHPRO()->helpers->translate( "This hook enables you to create a comment with all of its settings.", "action-create_comment-content" ); ?></p>
				<p><?php echo WPWHPRO()->helpers->translate( 'You can also add custom post meta. Here is an example on how this would look like using the simple structure (We also support json):', 'action-create_comment-content' ); ?></p>
				<br><br>
				<pre>meta_key_1,meta_value_1;my_second_key,add_my_value</pre>
				<br><br>
				<?php echo WPWHPRO()->helpers->translate( 'To separate the meta from the value, you can use a comma ",". To separate multiple meta settings from each other, easily separate them with a semicolon ";" (It is not necessary to set a semicolon at the end of the last one)', 'action-create_comment-content' ); ?>
				<br><br>
				<?php echo WPWHPRO()->helpers->translate( 'This is an example on how you can include the post meta using JSON.', 'action-create_comment-content' ); ?>
				<br>
				<pre>
{
  "meta_key_1": "This is my meta value 1",
  "another_meta_key": "This is my second meta key!"
}
				</pre>
				<p><?php echo WPWHPRO()->helpers->translate( "For security reasons, we don't send the password within the webhook response. To send the password as well, you can check out the following filter: wpwhpro/webhooks/action_create_comment_restrict_user_values", "action-create_comment-content" ); ?></p>
            <?php
			$description = ob_get_clean();

			return array(
				'action'            => 'create_comment', //required
				'parameter'         => $parameter,
				'returns'           => $returns,
				'returns_code'      => $returns_code,
				'short_description' => WPWHPRO()->helpers->translate( 'Creates a comment using a webhook.', 'action-create_comment-content' ),
				'description'       => $description
			);

		}

		public function action_update_comment_content(){

			$parameter = array(
				'comment_ID' => array( 'required' => true, 'short_description' => WPWHPRO()->helpers->translate( '(string) The HTTP user agent of the comment_author when the comment was submitted. Default empty.', 'action-update_comment-content' ) ),
				'comment_agent' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The HTTP user agent of the comment_author when the comment was submitted. Default empty.', 'action-update_comment-content' ) ),
				'comment_approved' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int|string) Whether the comment has been approved. Default 1.', 'action-update_comment-content' ) ),
				'comment_author' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The name of the author of the comment. Default empty.', 'action-update_comment-content' ) ),
				'comment_author_email' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The email address of the $comment_author. Default empty.', 'action-update_comment-content' ) ),
				'comment_author_IP' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The IP address of the $comment_author. Default empty.', 'action-update_comment-content' ) ),
				'comment_author_url' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The URL address of the $comment_author. Default empty.', 'action-update_comment-content' ) ),
				'comment_content' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The content of the comment. Default empty.', 'action-update_comment-content' ) ),
				'comment_date' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The date the comment was submitted. To set the date manually, comment_date_gmt must also be specified. Default is the current time.', 'action-update_comment-content' ) ),
				'comment_date_gmt' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) The date the comment was submitted in the GMT timezone. Default is comment_date in the site\'s GMT timezone.', 'action-update_comment-content' ) ),
				'comment_karma' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) The karma of the comment. Default 0.', 'action-update_comment-content' ) ),
				'comment_parent' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of this comment\'s parent, if any. Default 0.', 'action-update_comment-content' ) ),
				'comment_post_ID' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of the post that relates to the comment, if any. Default 0.', 'action-update_comment-content' ) ),
				'comment_type' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) Comment type. Default empty.', 'action-update_comment-content' ) ),
				'comment_meta' => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) Optional. Array of key/value pairs to be stored in commentmeta for the new comment. More info within the description.', 'action-update_comment-content' ) ),
				'user_id' => array( 'short_description' => WPWHPRO()->helpers->translate( '(int) ID of the user who submitted the comment. Default 0.', 'action-update_comment-content' ) ),
			);

			$returns = array(
				'success'        => array( 'short_description' => WPWHPRO()->helpers->translate( '(Bool) True if the action was successful, false if not. E.g. array( \'success\' => true )', 'action-update_comment-content' ) ),
				'data'           => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) The data related to the comment, as well as the user and the post object, incl. the meta values.', 'action-update_comment-content' ) ),
				'msg'            => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) A message with more information about the current request. E.g. array( \'msg\' => "This action was successful." )', 'action-update_comment-content' ) ),
			);

			ob_start();
			?>
            <pre>
$return_args = array(
	'success' => false,
	'msg' => '',
	'data' => array(
		'comment_id'   => 0,
		'comment_data'  => array(),
		'comment_meta'  => array(),
		'current_post_id' => 0,
		'current_post_data' => array(),
		'current_post_data_meta' => array(),
		'user_id' => 0,
		'user_data' => array(),
		'user_data_meta' => array()
	),
);
        </pre>
			<?php
			$returns_code = ob_get_clean();

			ob_start();
			?>
                <p><?php echo WPWHPRO()->helpers->translate( "This hook enables you to update a comment with all of its settings.", "action-update_comment-content" ); ?></p>
				<p><?php echo WPWHPRO()->helpers->translate( 'You can also add custom post meta. Here is an example on how this would look like using the simple structure (We also support json):', 'action-update_comment-content' ); ?></p>
				<br><br>
				<pre>meta_key_1,meta_value_1;my_second_key,add_my_value</pre>
				<br><br>
				<?php echo WPWHPRO()->helpers->translate( 'To separate the meta from the value, you can use a comma ",". To separate multiple meta settings from each other, easily separate them with a semicolon ";" (It is not necessary to set a semicolon at the end of the last one)', 'action-update_comment-content' ); ?>
				<br><br>
				<?php echo WPWHPRO()->helpers->translate( 'This is an example on how you can include the post meta using JSON. (If you set the meta value to "ironikus-delete", it will remove the existing value.)', 'action-update_comment-content' ); ?>
				<br>
				<pre>
{
  "meta_key_1": "This is my meta value 1",
  "another_meta_key": "This is my second meta key!",
  "another_meta_key_1": "ironikus-delete"
}
				</pre>
				<p><?php echo WPWHPRO()->helpers->translate( "For security reasons, we don't send the password within the webhook response. To send the password as well, you can check out the following filter: wpwhpro/webhooks/action_update_comment_restrict_user_values", "action-update_comment-content" ); ?></p>
            <?php
			$description = ob_get_clean();

			return array(
				'action'            => 'update_comment', //required
				'parameter'         => $parameter,
				'returns'           => $returns,
				'returns_code'      => $returns_code,
				'short_description' => WPWHPRO()->helpers->translate( 'Updates a comment using a webhook.', 'action-update_comment-content' ),
				'description'       => $description
			);

		}

		public function action_trash_comment_content(){

			$parameter = array(
				'comment_id' => array( 'required' => true, 'short_description' => WPWHPRO()->helpers->translate( '(int) The comment id of the comment you want to trash.', 'action-trash_comment-content' ) ),
			);

			$returns = array(
				'success'        => array( 'short_description' => WPWHPRO()->helpers->translate( '(Bool) True if the action was successful, false if not. E.g. array( \'success\' => true )', 'action-trash_comment-content' ) ),
				'data'           => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) The comment id as comment_id.', 'action-trash_comment-content' ) ),
				'msg'            => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) A message with more information about the current request. E.g. array( \'msg\' => "This action was successful." )', 'action-trash_comment-content' ) ),
			);

			ob_start();
			?>
            <pre>
$return_args = array(
	'success' => false,
	'msg' => '',
	'data' => array(
		'comment_id'   => 0
	),
);
        </pre>
			<?php
			$returns_code = ob_get_clean();

			ob_start();
			?>
                <p><?php echo WPWHPRO()->helpers->translate( "This hook enables you to trash a comment with all of its settings.", "action-trash_comment-content" ); ?></p>
				<p><?php echo WPWHPRO()->helpers->translate( 'We only support the comment id. Json objects are not allowed.', 'action-trash_comment-content' ); ?></p>
            <?php
			$description = ob_get_clean();

			return array(
				'action'            => 'trash_comment', //required
				'parameter'         => $parameter,
				'returns'           => $returns,
				'returns_code'      => $returns_code,
				'short_description' => WPWHPRO()->helpers->translate( 'Trash a comment using a webhook.', 'action-trash_comment-content' ),
				'description'       => $description
			);

		}

		public function action_delete_comment_content(){

			$parameter = array(
				'comment_id' => array( 'required' => true, 'short_description' => WPWHPRO()->helpers->translate( '(int) The comment id of the comment you want to delete.', 'action-delete_comment-content' ) ),
				'force_delete' => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) Wether you want to bypass the trash or not. You can set this value to "yes" or "no". Default "no"', 'action-delete_comment-content' ) ),
			);

			$returns = array(
				'success'        => array( 'short_description' => WPWHPRO()->helpers->translate( '(Bool) True if the action was successful, false if not. E.g. array( \'success\' => true )', 'action-delete_comment-content' ) ),
				'data'           => array( 'short_description' => WPWHPRO()->helpers->translate( '(array) The comment id as comment_id and the force_delete status.', 'action-delete_comment-content' ) ),
				'msg'            => array( 'short_description' => WPWHPRO()->helpers->translate( '(string) A message with more information about the current request. E.g. array( \'msg\' => "This action was successful." )', 'action-delete_comment-content' ) ),
			);

			ob_start();
			?>
            <pre>
$return_args = array(
	'success' => false,
	'msg' => '',
	'data' => array(
		'comment_id'   => 0,
		'force_delete'   => 'no'
	),
);
        </pre>
			<?php
			$returns_code = ob_get_clean();

			ob_start();
			?>
                <p><?php echo WPWHPRO()->helpers->translate( "This hook enables you to delete a comment with all of its settings.", "action-delete_comment-content" ); ?></p>
				<p><?php echo WPWHPRO()->helpers->translate( 'Please note, that this function, by default, pushes the comment to the trash. In case you want to fully delete it, set force_delete to "yes".', 'action-delete_comment-content' ); ?></p>
            <?php
			$description = ob_get_clean();

			return array(
				'action'            => 'delete_comment', //required
				'parameter'         => $parameter,
				'returns'           => $returns,
				'returns_code'      => $returns_code,
				'short_description' => WPWHPRO()->helpers->translate( 'Delete a comment using a webhook.', 'action-delete_comment-content' ),
				'description'       => $description
			);

		}

		public function action_create_comment( $update = false ) {

			if( $update ){
				$textdomain_context = 'update_comment';
			} else {
				$textdomain_context = 'create_comment';
			}

			$response_body = WPWHPRO()->helpers->get_response_body();
			$return_args = array(
				'success' => false,
                'msg' => '',
                'data' => array(
					'comment_id'   => 0,
					'comment_data'  => array(),
					'comment_meta'  => array(),
					'current_post_id' => 0,
					'current_post_data' => array(),
					'current_post_data_meta' => array(),
					'user_id' => 0,
					'user_data' => array(),
					'user_data_meta' => array(),
				),
			);

			$comment_agent        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_agent' );
			$comment_approved        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_approved' );
			$comment_author        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_author' );
			$comment_author_email        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_author_email' );
			$comment_author_IP        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_author_IP' );
			$comment_author_url        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_author_url' );
			$comment_content        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_content' );
			$comment_date        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_date' );
			$comment_date_gmt        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_date_gmt' );
			$comment_karma        = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_karma' ) );
			$comment_parent        = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_parent' ) );
			$comment_post_ID        = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_post_ID' ) );
			$comment_type        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_type' );
			$comment_meta        = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_meta' );
			$user_id        = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'user_id' ));
			$comment_ID        = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_ID' ));

			$do_action      = sanitize_title( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'do_action' ) );

			$commentdata = array();

			if( $update ){
				if( ! empty( $comment_ID ) ){
					$commentdata['comment_ID'] = $comment_ID;
				} else {
					$return_args['msg'] = WPWHPRO()->helpers->translate("A comment id is required to update the comment.", 'action-' . $textdomain_context );

					return $return_args;
				}
			}

			if( empty( $comment_agent ) ){
				$commentdata['comment_agent'] = '';
			} else {
				$commentdata['comment_agent'] = $comment_agent;
			}

			if( $comment_approved == 0 ){
				$commentdata['comment_approved'] = 0;
			} else {
				$commentdata['comment_approved'] = $comment_approved;
			}

			if( empty( $comment_author ) ){
				$commentdata['comment_author'] = '';
			} else {
				$commentdata['comment_author'] = $comment_author;
			}

			if( empty( $comment_author_email ) ){
				$commentdata['comment_author_email'] = '';
			} else {
				if( is_email( $comment_author_email ) ){
					$commentdata['comment_author_email'] = $comment_author_email;
				}
			}

			if( empty( $comment_author_IP ) ){
				$commentdata['comment_author_IP'] = '';
			} else {
				$commentdata['comment_author_IP'] = $comment_author_IP;
			}

			if( empty( $comment_author_url ) ){
				$commentdata['comment_author_url'] = '';
			} else {
				$commentdata['comment_author_url'] = $comment_author_url;
			}

			if( empty( $comment_date ) ){
				$commentdata['comment_date'] = current_time( 'mysql' );
			} else {
				$commentdata['comment_date'] = $comment_date;
			}

			if( empty( $comment_date_gmt ) ){
				if( ! empty( $commentdata['comment_date'] ) ){
					$commentdata['comment_date_gmt'] = $commentdata['comment_date'];
				} else {
					$commentdata['comment_date_gmt'] = current_time( 'mysql' );
				}
			} else {
				$commentdata['comment_date_gmt'] = $comment_date_gmt;
			}

			if( empty( $comment_content ) ){
				$commentdata['comment_content'] = '';
			} else {
				$commentdata['comment_content'] = $comment_content;
			}

			if( empty( $comment_karma ) ){
				$commentdata['comment_karma'] = 0;
			} else {
				$commentdata['comment_karma'] = $comment_karma;
			}

			if( empty( $comment_parent ) ){
				$commentdata['comment_parent'] = 0;
			} else {
				$commentdata['comment_parent'] = $comment_parent;
			}

			if( empty( $comment_post_ID ) ){
				$commentdata['comment_post_ID'] = 0;
			} else {
				$commentdata['comment_post_ID'] = $comment_post_ID;
			}

			if( empty( $comment_type ) ){
				$commentdata['comment_type'] = '';
			} else {
				$commentdata['comment_type'] = $comment_type;
			}

			if( empty( $user_id ) ){
				$commentdata['user_id'] = 0;
			} else {
				$commentdata['user_id'] = $user_id;
			}

			//Filter comment meta
			if( $update ){
				$commentdata = apply_filters( 'wpwhpro/webhooks/trigger_update_comment_commentdata', $commentdata );
			} else {
				$commentdata = apply_filters( 'wpwhpro/webhooks/trigger_create_comment_commentdata', $commentdata );
			}

			if( $update ){
				add_action( 'edit_comment', array( $this, 'create_update_comment_add_meta' ), 8, 1 );
				$comment_id = wp_update_comment( $commentdata );
				remove_action( 'edit_comment', array( $this, 'create_update_comment_add_meta' ), 8 );
			} else {
				add_action( 'wp_insert_comment', array( $this, 'create_update_comment_add_meta' ), 8, 1 );
				$comment_id = wp_insert_comment( $commentdata );
				remove_action( 'wp_insert_comment', array( $this, 'create_update_comment_add_meta' ), 8 );
			}
 
			if ( ! empty( $comment_id ) ) {
				$return_args['success'] = true;
				$return_args['data']['comment_id'] = $comment_id;
				$return_args['data']['comment_data'] = get_comment( $comment_id );
				$return_args['data']['comment_meta'] = get_comment_meta( $comment_id );

				if( $update ){
					$return_args['msg'] = WPWHPRO()->helpers->translate( "Comment updated successfully.", 'action-' . $textdomain_context );
				} else {
					$return_args['msg'] = WPWHPRO()->helpers->translate( "Comment created successfully.", 'action-' . $textdomain_context );
				}

				$comment = get_comment( $comment_id );

				if( isset( $comment->comment_post_ID ) ){
					$post_id = $comment->comment_post_ID;
					if( ! empty( $post_id ) ){
						$return_args['data']['current_post_id'] = $post_id;
						$return_args['data']['current_post_data'] = get_post( $post_id );
						$return_args['data']['current_post_data_meta'] = get_post_meta( $post_id );
					}
				}
	
				if( isset( $comment->comment_author_email ) && is_email( $comment->comment_author_email ) ){
					$user = get_user_by( 'email', sanitize_email( $comment->comment_author_email ) );
					if( ! empty( $user ) && ! is_wp_error( $user ) ){
						$return_args['data']['user_id'] = $user->data->ID;
						$return_args['data']['user_data'] = $user;
						$return_args['data']['user_data_meta'] = get_user_meta( $user->data->ID );
	
						//Restrict password
						if( $update ){
							$restrict = apply_filters( 'wpwhpro/webhooks/action_update_comment_restrict_user_values', array( 'user_pass' ) );
						} else {
							$restrict = apply_filters( 'wpwhpro/webhooks/action_create_comment_restrict_user_values', array( 'user_pass' ) );
						}
						
						if( is_array( $restrict ) && ! empty( $restrict ) ){
	
							foreach( $restrict as $data_key ){
								if( ! empty( $return_args['data']['user_data'] ) && isset( $return_args['data']['user_data']->data ) && isset( $return_args['data']['user_data']->data->{$data_key} )){
									unset( $return_args['data']['user_data']->data->{$data_key} );
								}
							}
							
						}
	
					}
				}

			} else {
				if( $update ){
					$return_args['msg'] = WPWHPRO()->helpers->translate( "The comment was not updated. this either happens because there was an issue or because there were o changes made to the comment.", 'action-' . $textdomain_context );
				} else {
					$return_args['msg'] = WPWHPRO()->helpers->translate( "Error while the comment.", 'action-' . $textdomain_context );
				}
			}

			if( ! empty( $do_action ) ){
				do_action( $do_action, $comment_id, $commentdata, $return_args );
			}

			return $return_args;
		}

		/**
		 * Update the post meta
		 *
		 * @param int $comment_id - the post id
		 * @return void
		 */
		public function create_update_comment_add_meta( $comment_id ){

			$response_body = WPWHPRO()->helpers->get_response_body();

			$meta_input = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'meta_input' );

			if( ! empty( $meta_input ) ){

				if( WPWHPRO()->helpers->is_json( $meta_input ) ){

					$post_meta_data = json_decode( $meta_input, true );
					foreach( $post_meta_data as $skey => $svalue ){

						if( ! empty( $skey ) ){
							if( $svalue == 'ironikus-delete' ){
								delete_comment_meta( $comment_id, $skey );
							} else {

								$ident = 'ironikus-serialize';
								if( is_string( $svalue ) && substr( $svalue , 0, strlen( $ident ) ) === $ident ){
									$serialized_value = trim( str_replace( $ident, '', $svalue ),' ' );

									if( WPWHPRO()->helpers->is_json( $serialized_value ) ){
										$serialized_value = json_decode( $svalue );
									}

									update_comment_meta( $comment_id, $skey, $serialized_value );

								} else {
									update_comment_meta( $comment_id, $skey, maybe_unserialize( $svalue ) );
								}

							}
						}
					}

				} else {

					$post_meta_data = explode( ';', trim( $meta_input, ';' ) );
					foreach( $post_meta_data as $single_meta ){
						$single_meta_data   = explode( ',', $single_meta );
						$meta_key           = sanitize_text_field( $single_meta_data[0] );
						$meta_value         = $single_meta_data[1];

						if( ! empty( $meta_key ) ){
							if( $meta_value == 'ironikus-delete' ){
								delete_comment_meta( $comment_id, $meta_key );
							} else {

								$ident = 'ironikus-serialize';
								if( substr( $meta_value , 0, strlen( $ident ) ) === $ident ){
									$serialized_value = trim( str_replace( $ident, '', $meta_value ),' ' );

									if( WPWHPRO()->helpers->is_json( $serialized_value ) ){
										$serialized_value = json_decode( $meta_value );
									}

									update_comment_meta( $comment_id, $meta_key, $serialized_value );

								} else {
									update_comment_meta( $comment_id, $meta_key, maybe_unserialize( $meta_value ) );
								}
							}
						}
					}

				}

			}

		}

		public function action_trash_comment() {

			$textdomain_context = 'trash_comment';

			$response_body = WPWHPRO()->helpers->get_response_body();
			$return_args = array(
				'success' => false,
                'msg' => '',
                'data' => array(
					'comment_id'   => 0,
				),
			);

			$comment_id = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_id' ));

			$do_action = sanitize_title( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'do_action' ) );


			if( empty( $comment_id ) ){
				$return_args['msg'] = WPWHPRO()->helpers->translate("A comment id is required to trash the comment.", 'action-' . $textdomain_context );

				return $return_args;
			}
 
			$return_args['data']['comment_id'] = $comment_id;
			
			$trashed = wp_trash_comment( $comment_id );

			if( $trashed ){
				$return_args['success'] = true;
				$return_args['msg'] = WPWHPRO()->helpers->translate("The comment was successfully trashed.", 'action-' . $textdomain_context );
			} else {
				$return_args['msg'] = WPWHPRO()->helpers->translate("Error while trashing the comment.", 'action-' . $textdomain_context );
			}

			if( ! empty( $do_action ) ){
				do_action( $do_action, $comment_id, $trashed, $return_args );
			}

			return $return_args;
		}

		public function action_delete_comment() {

			$textdomain_context = 'trash_comment';

			$response_body = WPWHPRO()->helpers->get_response_body();
			$return_args = array(
				'success' => false,
                'msg' => '',
                'data' => array(
					'comment_id'   => 0,
					'force_delete'   => 0,
				),
			);

			$comment_id = intval( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'comment_id' ));
			$force_delete = ( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'force_delete' ) == 'yes' ) ? true : false;

			$do_action = sanitize_title( WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'do_action' ) );


			if( empty( $comment_id ) ){
				$return_args['msg'] = WPWHPRO()->helpers->translate("A comment id is required to delete the comment.", 'action-' . $textdomain_context );

				return $return_args;
			}
 
			$return_args['data']['comment_id'] = $comment_id;
			$return_args['data']['force_delete'] = $force_delete;
			
			$deleted = wp_delete_comment( $comment_id, $force_delete );

			if( $deleted ){
				$return_args['success'] = true;

				if( $force_delete ){
					$return_args['msg'] = WPWHPRO()->helpers->translate("The comment was successfully deleted.", 'action-' . $textdomain_context );
				} else {
					$return_args['msg'] = WPWHPRO()->helpers->translate("The comment was successfully trashed.", 'action-' . $textdomain_context );
				}
				
			} else {
				$return_args['msg'] = WPWHPRO()->helpers->translate("Error while deleting the comment.", 'action-' . $textdomain_context );
			}

			if( ! empty( $do_action ) ){
				do_action( $do_action, $comment_id, $deleted, $return_args );
			}

			return $return_args;
		}

		/**
		 * ######################
		 * ###
		 * #### TRIGGERS
		 * ###
		 * ######################
		 */

		/**
		 * Regsiter all available webhook triggers
		 *
		 * @param $triggers - All registered triggers by the current plugin
		 *
		 * @return array - A array of all available triggers
		 */
		public function add_webhook_triggers_content( $triggers ){

			$triggers[] = $this->trigger_create_comment_content();
			$triggers[] = $this->trigger_update_comment_content();
			$triggers[] = $this->trigger_trash_comment_content();
			$triggers[] = $this->trigger_delete_comment_content();

			return $triggers;
		}

		/*
		* Add the specified webhook triggers logic.
		* We also add the demo functionality here
		*/
		public function add_webhook_triggers(){

			$active_webhooks = WPWHPRO()->settings->get_active_webhooks();
			$available_triggers = $active_webhooks['triggers'];

			if( isset( $available_triggers['create_comment'] ) ){
				add_action( 'wp_insert_comment', array( $this, 'ironikus_trigger_create_comment' ), 10, 2 );
				add_filter( 'ironikus_demo_test_create_comment', array( $this, 'ironikus_demo_test_create_comment' ), 10, 3 );
			}

			if( isset( $available_triggers['update_comment'] ) ){
				add_action( 'edit_comment', array( $this, 'ironikus_trigger_update_comment' ), 10, 2 );
				add_filter( 'ironikus_demo_test_update_comment', array( $this, 'ironikus_demo_test_create_comment' ), 10, 3 ); //Shares the same function than create_comment for now
			}

			if( isset( $available_triggers['trash_comment'] ) ){
				add_action( 'trashed_comment', array( $this, 'ironikus_trigger_trash_comment' ), 10, 2 );
				add_filter( 'ironikus_demo_test_trash_comment', array( $this, 'ironikus_demo_test_create_comment' ), 10, 3 ); //Shares the same function than create_comment for now
			}

			if( isset( $available_triggers['delete_comment'] ) ){
				add_action( 'deleted_comment', array( $this, 'ironikus_trigger_delete_comment' ), 10, 2 );
				add_filter( 'ironikus_demo_test_delete_comment', array( $this, 'ironikus_demo_test_create_comment' ), 10, 3 ); //Shares the same function than create_comment for now
			}

		}

		/*
		* Trigger webhook on creation of a comment
		*
		* @since 1.0.0
		*/
		public function trigger_create_comment_content(){

			$validated_post_types = array();
			foreach( get_post_types() as $name ){

				$singular_name = $name;
				$post_type_obj = get_post_type_object( $singular_name );
				if( ! empty( $post_type_obj->labels->singular_name ) ){
					$singular_name = $post_type_obj->labels->singular_name;
				} elseif( ! empty( $post_type_obj->labels->name ) ){
					$singular_name = $post_type_obj->labels->name;
				}

				$validated_post_types[ $name ] = $singular_name;
			}

			$parameter = array(
				'comment_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The comment id of the currently created comment.', 'trigger-create-comment' ) ),
				'comment_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data object of the comment.', 'trigger-create-comment' ) ),
				'current_post_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The post id of the post the comment was created on.', 'trigger-create-comment' ) ),
				'current_post_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the current post.', 'trigger-create-comment' ) ),
				'user_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The id of the user who posted the comment (In case it is given).', 'trigger-create-comment' ) ),
				'user_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the user of the comment (In case a user is given).', 'trigger-create-comment' ) ),
			);

			ob_start();
			?>
			<p><?php echo WPWHPRO()->helpers->translate( "Please copy your Webhooks Pro webhook URL into the provided input field. After that you can test your data via the Send demo button.", "trigger-create-comment" ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You will recieve a full response of the user post id, the full post object, as well as the post meta, so everything you need will be there.', 'trigger-create-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You can also filter the demo request by using a custom WordPress filter.', 'trigger-create-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'To check the Webhooks Pro response on a demo request, just open your browser console and you will see the object.', 'trigger-create-comment' ); ?></p>
			<p><?php echo sprintf( WPWHPRO()->helpers->translate( 'By default, we don\'t send the user password within the request. To active it, please use the following WordPress filter: wpwhpro/webhooks/trigger_create_comment_restrict_user_values (More details within our docs at <a title="Go to our plugin documentation" target="_blank" href="%s">ironikus.com/docs</a>', 'trigger-create-comment' ), 'https://ironikus.com/docs/?utm_source=wp-webhooks-comments&utm_medium=send-data-documentation&utm_campaign=WP%20Webhooks%20Pro'); ?></p>
			<?php
			$description = ob_get_clean();

			$settings = array(
				'load_default_settings' => true,
				'data' => array(
					'wpwhpro_create_comment_trigger_on_post_type' => array(
						'id'          => 'wpwhpro_create_comment_trigger_on_post_type',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_post_types,
						'label'       => WPWHPRO()->helpers->translate('Trigger on selected post types', 'wpwhpro-fields-trigger-on-post-type'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select only the post types you want to fire the trigger on. You can also choose multiple ones. If none is selected, all are triggered.', 'wpwhpro-fields-trigger-on-post-type-tip')
					),
				)
			);

			return array(
				'trigger'           => 'create_comment',
				'name'              => WPWHPRO()->helpers->translate( 'Send Data On New Comment', 'trigger-create-comment' ),
				'parameter'         => $parameter,
				'settings'          => $settings,
				'returns_code'      => WPWHPRO()->helpers->display_var( $this->ironikus_demo_test_create_comment( array(), '', '' ) ),
				'short_description' => WPWHPRO()->helpers->translate( 'This webhook fires after a new comment was created.', 'trigger-create-comment' ),
				'description'       => $description,
				'callback'          => 'test_create_comment'
			);

		}

		/*
		* Trigger webhook on updating of a comment
		*
		* @since 1.0.0
		*/
		public function trigger_update_comment_content(){

			$validated_post_types = array();
			foreach( get_post_types() as $name ){

				$singular_name = $name;
				$post_type_obj = get_post_type_object( $singular_name );
				if( ! empty( $post_type_obj->labels->singular_name ) ){
					$singular_name = $post_type_obj->labels->singular_name;
				} elseif( ! empty( $post_type_obj->labels->name ) ){
					$singular_name = $post_type_obj->labels->name;
				}

				$validated_post_types[ $name ] = $singular_name;
			}

			$parameter = array(
				'comment_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The comment id of the currently updated comment.', 'trigger-update-comment' ) ),
				'comment_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data object of the comment.', 'trigger-update-comment' ) ),
				'current_post_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The post id of the post the comment was updated on.', 'trigger-update-comment' ) ),
				'current_post_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the current post.', 'trigger-update-comment' ) ),
				'user_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The id of the user who posted the comment (In case it is given).', 'trigger-update-comment' ) ),
				'user_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the user of the comment (In case a user is given).', 'trigger-update-comment' ) ),
			);

			ob_start();
			?>
			<p><?php echo WPWHPRO()->helpers->translate( "Please copy your Webhooks Pro webhook URL into the provided input field. After that you can test your data via the Send demo button.", "trigger-update-comment" ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You will recieve a full response of the user post id, the full post object, as well as the post meta, so everything you need will be there.', 'trigger-update-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You can also filter the demo request by using a custom WordPress filter.', 'trigger-update-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'To check the Webhooks Pro response on a demo request, just open your browser console and you will see the object.', 'trigger-update-comment' ); ?></p>
			<p><?php echo sprintf( WPWHPRO()->helpers->translate( 'By default, we don\'t send the user password within the request. To active it, please use the following WordPress filter: wpwhpro/webhooks/trigger_update_comment_restrict_user_values (More details within our docs at <a title="Go to our plugin documentation" target="_blank" href="%s">ironikus.com/docs</a>', 'trigger-update-comment' ), 'https://ironikus.com/docs/?utm_source=wp-webhooks-comments&utm_medium=send-data-documentation&utm_campaign=WP%20Webhooks%20Pro'); ?></p>
			<?php
			$description = ob_get_clean();

			$settings = array(
				'load_default_settings' => true,
				'data' => array(
					'wpwhpro_update_comment_trigger_on_post_type' => array(
						'id'          => 'wpwhpro_update_comment_trigger_on_post_type',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_post_types,
						'label'       => WPWHPRO()->helpers->translate('Trigger on selected post types', 'wpwhpro-fields-trigger-on-post-type'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select only the post types you want to fire the trigger on. You can also choose multiple ones. If none is selected, all are triggered.', 'wpwhpro-fields-trigger-on-post-type-tip')
					),
				)
			);

			return array(
				'trigger'           => 'update_comment',
				'name'              => WPWHPRO()->helpers->translate( 'Send Data On Update of a Comment', 'trigger-update-comment' ),
				'parameter'         => $parameter,
				'settings'          => $settings,
				'returns_code'      => WPWHPRO()->helpers->display_var( $this->ironikus_demo_test_create_comment( array(), '', '' ) ), //Shares the same function than create_comment for now
				'short_description' => WPWHPRO()->helpers->translate( 'This webhook fires after a comment was updated.', 'trigger-update-comment' ),
				'description'       => $description,
				'callback'          => 'test_update_comment'
			);

		}

		/*
		* Trigger webhook on trashing of a comment
		*
		* @since 1.0.0
		*/
		public function trigger_trash_comment_content(){

			$validated_post_types = array();
			foreach( get_post_types() as $name ){

				$singular_name = $name;
				$post_type_obj = get_post_type_object( $singular_name );
				if( ! empty( $post_type_obj->labels->singular_name ) ){
					$singular_name = $post_type_obj->labels->singular_name;
				} elseif( ! empty( $post_type_obj->labels->name ) ){
					$singular_name = $post_type_obj->labels->name;
				}

				$validated_post_types[ $name ] = $singular_name;
			}

			$parameter = array(
				'comment_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The comment id of the currently trashed comment.', 'trigger-trash-comment' ) ),
				'comment_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data object of the comment.', 'trigger-trash-comment' ) ),
				'current_post_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The post id of the post the comment was trashd on.', 'trigger-trash-comment' ) ),
				'current_post_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the current post.', 'trigger-trash-comment' ) ),
				'user_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The id of the user who posted the comment (In case it is given).', 'trigger-trash-comment' ) ),
				'user_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the user of the comment (In case a user is given).', 'trigger-trash-comment' ) ),
			);

			ob_start();
			?>
			<p><?php echo WPWHPRO()->helpers->translate( "Please copy your Webhooks Pro webhook URL into the provided input field. After that you can test your data via the Send demo button.", "trigger-trash-comment" ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You will recieve a full response of the user post id, the full post object, as well as the post meta, so everything you need will be there.', 'trigger-trash-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You can also filter the demo request by using a custom WordPress filter.', 'trigger-trash-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'To check the Webhooks Pro response on a demo request, just open your browser console and you will see the object.', 'trigger-trash-comment' ); ?></p>
			<p><?php echo sprintf( WPWHPRO()->helpers->translate( 'By default, we don\'t send the user password within the request. To active it, please use the following WordPress filter: wpwhpro/webhooks/trigger_trash_comment_restrict_user_values (More details within our docs at <a title="Go to our plugin documentation" target="_blank" href="%s">ironikus.com/docs</a>', 'trigger-trash-comment' ), 'https://ironikus.com/docs/?utm_source=wp-webhooks-comments&utm_medium=send-data-documentation&utm_campaign=WP%20Webhooks%20Pro'); ?></p>
			<?php
			$description = ob_get_clean();

			$settings = array(
				'load_default_settings' => true,
				'data' => array(
					'wpwhpro_trash_comment_trigger_on_post_type' => array(
						'id'          => 'wpwhpro_trash_comment_trigger_on_post_type',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_post_types,
						'label'       => WPWHPRO()->helpers->translate('Trigger on selected post types', 'wpwhpro-fields-trigger-on-post-type'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select only the post types you want to fire the trigger on. You can also choose multiple ones. If none is selected, all are triggered.', 'wpwhpro-fields-trigger-on-post-type-tip')
					),
				)
			);

			return array(
				'trigger'           => 'trash_comment',
				'name'              => WPWHPRO()->helpers->translate( 'Send Data On Trashing a Comment', 'trigger-trash-comment' ),
				'parameter'         => $parameter,
				'settings'          => $settings,
				'returns_code'      => WPWHPRO()->helpers->display_var( $this->ironikus_demo_test_create_comment( array(), '', '' ) ), //Shares the same function than create_comment for now
				'short_description' => WPWHPRO()->helpers->translate( 'This webhook fires after a comment was trashed.', 'trigger-trash-comment' ),
				'description'       => $description,
				'callback'          => 'test_trash_comment'
			);

		}

		/*
		* Trigger webhook on deletion of a comment
		*
		* @since 1.0.0
		*/
		public function trigger_delete_comment_content(){

			$validated_post_types = array();
			foreach( get_post_types() as $name ){

				$singular_name = $name;
				$post_type_obj = get_post_type_object( $singular_name );
				if( ! empty( $post_type_obj->labels->singular_name ) ){
					$singular_name = $post_type_obj->labels->singular_name;
				} elseif( ! empty( $post_type_obj->labels->name ) ){
					$singular_name = $post_type_obj->labels->name;
				}

				$validated_post_types[ $name ] = $singular_name;
			}

			$parameter = array(
				'comment_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The comment id of the currently deleted comment.', 'trigger-delete-comment' ) ),
				'comment_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data object of the comment.', 'trigger-delete-comment' ) ),
				'current_post_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The post id of the post the comment was deleted on.', 'trigger-delete-comment' ) ),
				'current_post_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the current post.', 'trigger-delete-comment' ) ),
				'user_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The id of the user who posted the comment (In case it is given).', 'trigger-delete-comment' ) ),
				'user_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The full data of the user of the comment (In case a user is given).', 'trigger-delete-comment' ) ),
			);

			ob_start();
			?>
			<p><?php echo WPWHPRO()->helpers->translate( "Please copy your Webhooks Pro webhook URL into the provided input field. After that you can test your data via the Send demo button.", "trigger-delete-comment" ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You will recieve a full response of the user post id, the full post object, as well as the post meta, so everything you need will be there.', 'trigger-delete-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'You can also filter the demo request by using a custom WordPress filter.', 'trigger-delete-comment' ); ?></p>
			<p><?php echo WPWHPRO()->helpers->translate( 'To check the Webhooks Pro response on a demo request, just open your browser console and you will see the object.', 'trigger-delete-comment' ); ?></p>
			<p><?php echo sprintf( WPWHPRO()->helpers->translate( 'By default, we don\'t send the user password within the request. To active it, please use the following WordPress filter: wpwhpro/webhooks/trigger_delete_comment_restrict_user_values (More details within our docs at <a title="Go to our plugin documentation" target="_blank" href="%s">ironikus.com/docs</a>', 'trigger-delete-comment' ), 'https://ironikus.com/docs/?utm_source=wp-webhooks-comments&utm_medium=send-data-documentation&utm_campaign=WP%20Webhooks%20Pro'); ?></p>
			<?php
			$description = ob_get_clean();

			$settings = array(
				'load_default_settings' => true,
				'data' => array(
					'wpwhpro_delete_comment_trigger_on_post_type' => array(
						'id'          => 'wpwhpro_delete_comment_trigger_on_post_type',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_post_types,
						'label'       => WPWHPRO()->helpers->translate('Trigger on selected post types', 'wpwhpro-fields-trigger-on-post-type'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select only the post types you want to fire the trigger on. You can also choose multiple ones. If none is selected, all are triggered.', 'wpwhpro-fields-trigger-on-post-type-tip')
					),
				)
			);

			return array(
				'trigger'           => 'delete_comment',
				'name'              => WPWHPRO()->helpers->translate( 'Send Data On deletion of a Comment', 'trigger-delete-comment' ),
				'parameter'         => $parameter,
				'settings'          => $settings,
				'returns_code'      => WPWHPRO()->helpers->display_var( $this->ironikus_demo_test_create_comment( array(), '', '' ) ), //Shares the same function than create_comment for now
				'short_description' => WPWHPRO()->helpers->translate( 'This webhook fires after a comment was deleted.', 'trigger-delete-comment' ),
				'description'       => $description,
				'callback'          => 'test_delete_comment'
			);

		}

		//Create post test callback
		public function ironikus_demo_test_create_comment(){

			$data = array (
				'comment_id' => 9,
				'comment_data' => 
				array (
				  'comment_ID' => '9',
				  'comment_post_ID' => '375',
				  'comment_author' => 'admin',
				  'comment_author_email' => 'admin@xxx.dev',
				  'comment_author_url' => '',
				  'comment_author_IP' => '127.0.0.1',
				  'comment_date' => '2019-08-14 14:08:53',
				  'comment_date_gmt' => '2019-08-14 14:08:53',
				  'comment_content' => 'My test',
				  'comment_karma' => '0',
				  'comment_approved' => '1',
				  'comment_agent' => 'Mozilla/5.0 xxx',
				  'comment_type' => '',
				  'comment_parent' => '0',
				  'user_id' => '1',
				),
				'comment_meta' => 
				array (
				  'demo_key_1' => array( 375 ),
				  'demo_key_2' => array( 'test' ),
				),
				'current_post_id' => '375',
				'current_post_data' => 
				array (
				  'ID' => 375,
				  'post_author' => '1',
				  'post_date' => '2019-08-11 15:03:31',
				  'post_date_gmt' => '2019-08-11 15:03:31',
				  'post_content' => '',
				  'post_title' => 'Test Custom Comment 2',
				  'post_excerpt' => '',
				  'post_status' => 'publish',
				  'comment_status' => 'open',
				  'ping_status' => 'open',
				  'post_password' => '',
				  'post_name' => 'test-custom-comment-2',
				  'to_ping' => '',
				  'pinged' => '',
				  'post_modified' => '2019-08-14 11:53:24',
				  'post_modified_gmt' => '2019-08-14 11:53:24',
				  'post_content_filtered' => '',
				  'post_parent' => 0,
				  'guid' => 'https://xxx.dev/?p=375',
				  'menu_order' => 0,
				  'post_type' => 'post',
				  'post_mime_type' => '',
				  'comment_count' => '3',
				  'filter' => 'raw',
				),
				'current_post_data_meta' => 
				array (
				  'demo_key_1' => array( 375 ),
				  'demo_key_2' => array( 'test' ),
				),
				'user_id' => '1',
				'user_data' => 
				array (
				  'data' => 
				  array (
					'ID' => '1',
					'user_login' => 'admin',
					'user_nicename' => 'admin',
					'user_email' => 'admin@xxx.dev',
					'user_url' => '',
					'user_registered' => '2017-07-27 23:58:11',
					'user_activation_key' => '',
					'user_status' => '0',
					'display_name' => 'admin',
					'spam' => '0',
					'deleted' => '0',
				  ),
				  'ID' => 1,
				  'caps' => 
				  array (
					'administrator' => true,
				  ),
				  'cap_key' => 'XXX_capabilities',
				  'roles' => 
				  array (
					0 => 'administrator',
				  ),
				  'allcaps' => 
				  array (
					'switch_themes' => true,
					'edit_themes' => true,
					'activate_plugins' => true,
					'edit_plugins' => true,
					'edit_users' => true,
					'edit_files' => true,
					'manage_options' => true,
					'moderate_comments' => true,
					'manage_categories' => true,
					'manage_links' => true,
					'upload_files' => true,
					'import' => true,
					'unfiltered_html' => true,
					'edit_posts' => true,
					'edit_others_posts' => true,
					'edit_published_posts' => true,
					'publish_posts' => true,
					'edit_pages' => true,
					'read' => true,
					'level_10' => true,
					'level_9' => true,
					'level_8' => true,
					'level_7' => true,
					'level_6' => true,
					'level_5' => true,
					'level_4' => true,
					'level_3' => true,
					'level_2' => true,
					'level_1' => true,
					'level_0' => true,
					'edit_others_pages' => true,
					'edit_published_pages' => true,
					'publish_pages' => true,
					'delete_pages' => true,
					'delete_others_pages' => true,
					'delete_published_pages' => true,
					'delete_posts' => true,
					'delete_others_posts' => true,
					'delete_published_posts' => true,
					'delete_private_posts' => true,
					'edit_private_posts' => true,
					'read_private_posts' => true,
					'delete_private_pages' => true,
					'edit_private_pages' => true,
					'read_private_pages' => true,
					'delete_users' => true,
					'create_users' => true,
					'unfiltered_upload' => true,
					'edit_dashboard' => true,
					'update_plugins' => true,
					'delete_plugins' => true,
					'install_plugins' => true,
					'update_themes' => true,
					'install_themes' => true,
					'update_core' => true,
					'list_users' => true,
					'remove_users' => true,
					'promote_users' => true,
					'edit_theme_options' => true,
					'delete_themes' => true,
					'export' => true,
				  ),
				  'filter' => NULL,
				),
				'user_data_meta' => 
				array (
				  'demo_key_1' => array( 375 ),
				  'demo_key_2' => array( 'test' ),
				),
			  );

			return $data;

		}

		/*
		* Trigger on comment creation
		*
		* @since 1.0.0
		*/
		public function ironikus_trigger_create_comment( $comment_id, $comment ){

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'create_comment' );
			$data_array = array(
				'comment_id'   => $comment_id,
				'comment_data'  => $comment,
				'comment_meta'  => get_comment_meta( $comment_id ),
				'current_post_id' => 0,
				'current_post_data' => array(),
				'current_post_data_meta' => array(),
				'user_id' => 0,
				'user_data' => array(),
				'user_data_meta' => array()
			);
			$response_data = array();

			if( isset( $comment->comment_post_ID ) ){
				$post_id = $comment->comment_post_ID;
				if( ! empty( $post_id ) ){
					$data_array['current_post_id'] = $post_id;
					$data_array['current_post_data'] = get_post( $post_id );
					$data_array['current_post_data_meta'] = get_post_meta( $post_id );
				}
			}

			if( isset( $comment->comment_author_email ) && is_email( $comment->comment_author_email ) ){
				$user = get_user_by( 'email', sanitize_email( $comment->comment_author_email ) );
				if( ! empty( $user ) && ! is_wp_error( $user ) ){
					$data_array['user_id'] = $user->data->ID;
					$data_array['user_data'] = $user;
					$data_array['user_data_meta'] = get_user_meta( $user->data->ID );

					//Restrict password
					$restrict = apply_filters( 'wpwhpro/webhooks/trigger_create_comment_restrict_user_values', array( 'user_pass' ) );
					if( is_array( $restrict ) && ! empty( $restrict ) ){

						foreach( $restrict as $data_key ){
							if( ! empty( $data_array['user_data'] ) && isset( $data_array['user_data']->data ) && isset( $data_array['user_data']->data->{$data_key} )){
								unset( $data_array['user_data']->data->{$data_key} );
							}
						}
						
					}

				}
			}

			foreach( $webhooks as $webhook ){

				$is_valid = true;

				if( isset( $webhook['settings'] ) ){
					foreach( $webhook['settings'] as $settings_name => $settings_data ){

						if( $settings_name === 'wpwhpro_create_comment_trigger_on_post_type' && ! empty( $settings_data ) ){
							if( ! empty( $data_array['current_post_data'] ) ){
								if( ! in_array( $data_array['current_post_data']->post_type, $settings_data ) ){
									$is_valid = false;
								}
							}
						}
					}
				}

				if( $is_valid ){
					$webhook_url_name = ( is_array($webhook) && isset( $webhook['webhook_url_name'] ) ) ? $webhook['webhook_url_name'] : null;

					if( $webhook_url_name !== null ){
						$response_data[ $webhook_url_name ] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					} else {
						$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					}
				}
			}

			do_action( 'wpwhpro/webhooks/trigger_create_comment', $comment_id, $comment, $data_array, $response_data );

		}

		/*
		* Trigger on comment update
		*
		* @since 1.0.0
		*/
		public function ironikus_trigger_update_comment( $comment_id, $data ){

			$comment = get_comment( $comment_id );

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'update_comment' );
			$data_array = array(
				'comment_id'   => $comment_id,
				'comment_data'  => $comment,
				'comment_meta'  => get_comment_meta( $comment_id ),
				'current_post_id' => 0,
				'current_post_data' => array(),
				'current_post_data_meta' => array(),
				'user_id' => 0,
				'user_data' => array(),
				'user_data_meta' => array()
			);
			$response_data = array();

			if( isset( $comment->comment_post_ID ) ){
				$post_id = $comment->comment_post_ID;
				if( ! empty( $post_id ) ){
					$data_array['current_post_id'] = $post_id;
					$data_array['current_post_data'] = get_post( $post_id );
					$data_array['current_post_data_meta'] = get_post_meta( $post_id );
				}
			}

			if( isset( $comment->comment_author_email ) && is_email( $comment->comment_author_email ) ){
				$user = get_user_by( 'email', sanitize_email( $comment->comment_author_email ) );
				if( ! empty( $user ) && ! is_wp_error( $user ) ){
					$data_array['user_id'] = $user->data->ID;
					$data_array['user_data'] = $user;

					//Restrict password
					$restrict = apply_filters( 'wpwhpro/webhooks/trigger_update_comment_restrict_user_values', array( 'user_pass' ) );
					if( is_array( $restrict ) && ! empty( $restrict ) ){

						foreach( $restrict as $data_key ){
							if( ! empty( $data_array['user_data'] ) && isset( $data_array['user_data']->data ) && isset( $data_array['user_data']->data->{$data_key} )){
								unset( $data_array['user_data']->data->{$data_key} );
							}
						}
						
					}

				}
			}

			foreach( $webhooks as $webhook ){

				$is_valid = true;

				if( isset( $webhook['settings'] ) ){
					foreach( $webhook['settings'] as $settings_name => $settings_data ){

						if( $settings_name === 'wpwhpro_update_comment_trigger_on_post_type' && ! empty( $settings_data ) ){
							if( ! empty( $data_array['current_post_data'] ) ){
								if( ! in_array( $data_array['current_post_data']->post_type, $settings_data ) ){
									$is_valid = false;
								}
							}
						}
					}
				}

				if( $is_valid ){
					$webhook_url_name = ( is_array($webhook) && isset( $webhook['webhook_url_name'] ) ) ? $webhook['webhook_url_name'] : null;

					if( $webhook_url_name !== null ){
						$response_data[ $webhook_url_name ] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					} else {
						$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					}
				}
			}

			do_action( 'wpwhpro/webhooks/trigger_update_comment', $comment_id, $comment, $data_array, $response_data, $data );

		}

		/*
		* Trigger on comment deletion
		*
		* @since 1.0.0
		*/
		public function ironikus_trigger_trash_comment( $comment_id, $comment ){

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'trash_comment' );
			$data_array = array(
				'comment_id'   => $comment_id,
				'comment_data'  => $comment,
				'comment_meta'  => get_comment_meta( $comment_id ),
				'current_post_id' => 0,
				'current_post_data' => array(),
				'current_post_data_meta' => array(),
				'user_id' => 0,
				'user_data' => array(),
				'user_data_meta' => array()
			);
			$response_data = array();

			if( isset( $comment->comment_post_ID ) ){
				$post_id = $comment->comment_post_ID;
				if( ! empty( $post_id ) ){
					$data_array['current_post_id'] = $post_id;
					$data_array['current_post_data'] = get_post( $post_id );
					$data_array['current_post_data_meta'] = get_post_meta( $post_id );
				}
			}

			if( isset( $comment->comment_author_email ) && is_email( $comment->comment_author_email ) ){
				$user = get_user_by( 'email', sanitize_email( $comment->comment_author_email ) );
				if( ! empty( $user ) && ! is_wp_error( $user ) ){
					$data_array['user_id'] = $user->data->ID;
					$data_array['user_data'] = $user;

					//Restrict password
					$restrict = apply_filters( 'wpwhpro/webhooks/trigger_trash_comment_restrict_user_values', array( 'user_pass' ) );
					if( is_array( $restrict ) && ! empty( $restrict ) ){

						foreach( $restrict as $data_key ){
							if( ! empty( $data_array['user_data'] ) && isset( $data_array['user_data']->data ) && isset( $data_array['user_data']->data->{$data_key} )){
								unset( $data_array['user_data']->data->{$data_key} );
							}
						}
						
					}

				}
			}

			foreach( $webhooks as $webhook ){

				$is_valid = true;

				if( isset( $webhook['settings'] ) ){
					foreach( $webhook['settings'] as $settings_name => $settings_data ){

						if( $settings_name === 'wpwhpro_trash_comment_trigger_on_post_type' && ! empty( $settings_data ) ){
							if( ! empty( $data_array['current_post_data'] ) ){
								if( ! in_array( $data_array['current_post_data']->post_type, $settings_data ) ){
									$is_valid = false;
								}
							}
						}
					}
				}

				if( $is_valid ){
					$webhook_url_name = ( is_array($webhook) && isset( $webhook['webhook_url_name'] ) ) ? $webhook['webhook_url_name'] : null;

					if( $webhook_url_name !== null ){
						$response_data[ $webhook_url_name ] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					} else {
						$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					}
				}
			}

			do_action( 'wpwhpro/webhooks/trigger_update_comment', $comment_id, $comment, $data_array, $response_data );

		}

		/*
		* Trigger on comment deletion
		*
		* @since 1.0.0
		*/
		public function ironikus_trigger_delete_comment( $comment_id, $comment ){

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'delete_comment' );
			$data_array = array(
				'comment_id'   => $comment_id,
				'comment_data'  => $comment,
				'current_post_id' => 0,
				'current_post_data' => array(),
				'current_post_data_meta' => array(),
				'user_id' => 0,
				'user_data' => array(),
				'user_data_meta' => array()
			);
			$response_data = array();

			if( isset( $comment->comment_post_ID ) ){
				$post_id = $comment->comment_post_ID;
				if( ! empty( $post_id ) ){
					$data_array['current_post_id'] = $post_id;
					$data_array['current_post_data'] = get_post( $post_id );
					$data_array['current_post_data_meta'] = get_post_meta( $post_id );
				}
			}

			if( isset( $comment->comment_author_email ) && is_email( $comment->comment_author_email ) ){
				$user = get_user_by( 'email', sanitize_email( $comment->comment_author_email ) );
				if( ! empty( $user ) && ! is_wp_error( $user ) ){
					$data_array['user_id'] = $user->data->ID;
					$data_array['user_data'] = $user;

					//Restrict password
					$restrict = apply_filters( 'wpwhpro/webhooks/trigger_delete_comment_restrict_user_values', array( 'user_pass' ) );
					if( is_array( $restrict ) && ! empty( $restrict ) ){

						foreach( $restrict as $data_key ){
							if( ! empty( $data_array['user_data'] ) && isset( $data_array['user_data']->data ) && isset( $data_array['user_data']->data->{$data_key} )){
								unset( $data_array['user_data']->data->{$data_key} );
							}
						}
						
					}

				}
			}

			foreach( $webhooks as $webhook ){

				$is_valid = true;

				if( isset( $webhook['settings'] ) ){
					foreach( $webhook['settings'] as $settings_name => $settings_data ){

						if( $settings_name === 'wpwhpro_delete_comment_trigger_on_post_type' && ! empty( $settings_data ) ){
							if( ! empty( $data_array['current_post_data'] ) ){
								if( ! in_array( $data_array['current_post_data']->post_type, $settings_data ) ){
									$is_valid = false;
								}
							}
						}
					}
				}

				if( $is_valid ){
					$webhook_url_name = ( is_array($webhook) && isset( $webhook['webhook_url_name'] ) ) ? $webhook['webhook_url_name'] : null;

					if( $webhook_url_name !== null ){
						$response_data[ $webhook_url_name ] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					} else {
						$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
					}
				}
			}

			do_action( 'wpwhpro/webhooks/trigger_update_comment', $comment_id, $comment, $data_array, $response_data );

		}

	} // End class

	function wpwhpro_load_comments(){
		new WP_Webhooks_Comments();
	}

	// Make sure we load the extension after main plugin is loaded
	if( defined( 'WPWH_SETUP' ) || defined( 'WPWHPRO_SETUP' ) ){
		wpwhpro_load_comments();
    } else {
		add_action( 'wpwhpro_plugin_loaded', 'wpwhpro_load_comments' );
    }

	//Throw message in case WP Webhook is not active
	add_action( 'admin_notices', 'wpwh_comments_active', 100 );
    function wpwh_comments_active(){

        if( ! defined( 'WPWH_SETUP' ) && ! defined( 'WPWHPRO_SETUP' ) ){

                ob_start();
                ?>
                <div class="notice notice-warning">
                    <p><?php echo sprintf( '<strong>WP Webhooks - Comments</strong> is active, but <strong>WP Webhooks</strong> or <strong>WP Webhooks Pro</strong> isn\'t. Please activate it to use the functionality for <strong>comments</strong>. <a href="%s" target="_blank" rel="noopener">More Info</a>', 'https://de.wordpress.org/plugins/wp-webhooks/' ); ?></p>
                </div>
                <?php
                echo ob_get_clean();

        }

    }

}