<?php
/**
 * Plugin Name: Flexmail for WooCommerce
 * Description: Allow customers to have their email added to Flexmail.
 * Author: Jan Boddez
 * Author URI: https://janboddez.tech/
 * Text Domain: jb-wc-flexmail
 * Version: 0.1.0
 *
 * @author Jan Boddez [jan@janboddez.be]
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0
 * @package JB_Flexmail_WooCommerce
 */

if ( ! class_exists( 'JB_Flexmail_WooCommerce' ) ) :
/**
 * Main plugin class and settings.
 */
class JB_Flexmail_WooCommerce {
	/**
	 * Registers actions.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'display_checkbox' ), 9 );
		add_action( 'woocommerce_checkout_process', array( $this, 'add_to_flexmail' ) );
	}

	/**
	 * Enables localization.
	 *
	 * @since 0.1.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'jb-wc-flexmail', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Registers the plugin settings page.
	 *
	 * @since 0.1.0
	 */
	public function create_menu() {
		add_options_page(
			__( 'Flexmail for WooCommerce', 'jb-wc-flexmail' ),
			__( 'Flexmail for WooCommerce', 'jb-wc-flexmail' ),
			'manage_options',
			'jb-wc-flexmail-settings-page',
			array( $this, 'settings_page' )
		);

		add_action( 'admin_init', array( $this, 'add_settings' ) );
	}

	/**
	 * Registers the actual options.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {
		register_setting(
			'jb-wc-flexmail-settings-group',
			'jb_wc_flexmail_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Sanitizes submitted options.
	 *
	 * @param array $settings Settings as submitted by the user.
	 *
	 * @return array Settings to save to the database.
	 *
	 * @since 0.1.0
	 */
	public function sanitize_settings( $settings ) {
		// Retrieve existing values.
		$options = get_option( 'jb_wc_flexmail_settings', array() );

		if ( isset( $settings['user_id'] ) ) {
			// Must be an integer.
			$options['user_id'] = ( is_numeric( $settings['user_id'] ) ? intval( $settings['user_id'] ) : '' );
		}

		if ( isset( $settings['user_token'] ) ) {
			$options['user_token'] = sanitize_text_field( $settings['user_token'] );
		}

		if ( isset( $settings['list_id'] ) ) {
			// Must be an integer.
			$options['list_id'] = ( is_numeric( $settings['list_id'] ) ? intval( $settings['list_id'] ) : '' );
		}

		if ( isset( $settings['checkbox_label'] ) ) {
			$options['checkbox_label'] = sanitize_text_field( $settings['checkbox_label'] );

			// We're using two forms and thus can't simply check for
			// `export_address` only; it'll always be missing if the 'other'
			// form is submitted.
			if ( isset( $settings['export_address'] ) ) {
				$options['export_address'] = 1;
			} else {
				$options['export_address'] = 0;
			}
		}

		if ( isset( $settings['source_name'] ) ) {
			$options['source_name'] = sanitize_text_field( $settings['source_name'] );
		}

		return $options;
	}

	/**
	 * Echoes the plugin options form.
	 *
	 * @since 0.1.0
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Flexmail for WooCommerce', 'jb-wc-flexmail' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				// Retrieve existing values.
				$options = get_option( 'jb_wc_flexmail_settings', array() );

				// Set some defaults.
				foreach ( array( 'user_id', 'user_token', 'list_id' ) as $option_name ) {
					if ( ! isset( $options[ $option_name ] ) ) {
						$options[ $option_name ] = '';
					}
				}

				if ( ! isset( $options['checkbox_label'] ) ) {
					$options['checkbox_label'] = sprintf( __( 'Yes, I&rsquo;d like to sign up for the %s newsletter.', 'jb-wc-flexmail' ), get_bloginfo( 'name' ) );
				}

				if ( ! isset( $options['export_address'] ) ) {
					$options['export_address'] = 0;
				}

				if ( ! isset( $options['source_name'] ) ) {
					$options['source_name'] = get_bloginfo( 'name' );
				}

				// Try and fetch the different Flexmail mailing lists.
				$mailing_lists = $this->_get_mailing_lists( $options['user_id'], $options['user_token'] );

				if ( empty( $mailing_lists ) || ! is_array( $mailing_lists ) ) {
					// Unless something really went wrong, the API settings are missing or incorrect.
					?>
					<div class="notice notice-warning">
						 <p><?php _e( 'Fill out and <strong>save</strong> the <strong>Flexmail API Settings</strong> first! Only then will you be able to select your Flexmail Contacts List below.', 'jb-wc-flexmail' ); ?></p>
					</div>
					<?php
				}

				// Print nonces and such.
				settings_fields( 'jb-wc-flexmail-settings-group' );
				?>

				<h2><?php _e( 'Flexmail API Settings', 'jb-wc-flexmail' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="jb_wc_flexmail_settings[user_id]"><?php _e( 'API User ID', 'jb-wc-flexmail' ); ?></label></th>
						<td>
							<input class="widefat" type="text" id="jb_wc_flexmail_settings[user_id]" name="jb_wc_flexmail_settings[user_id]" value="<?php echo esc_attr( $options['user_id'] ); ?>" />
							<p class="description"><?php _e( 'Your Flexmail API user ID.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jb_wc_flexmail_settings[user_token]"><?php _e( 'API User Token', 'jb-wc-flexmail' ); ?></label></th>
						<td>
							<input class="widefat" type="text" id="jb_wc_flexmail_settings[user_token]" name="jb_wc_flexmail_settings[user_token]" value="<?php echo esc_attr( $options['user_token'] ); ?>" />
							<p class="description"><?php _e( 'Your Flexmail API user token.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="options.php">
				<?php settings_fields( 'jb-wc-flexmail-settings-group' ); ?>

				<h2><?php _e( 'Miscellaneous Settings', 'jb-wc-flexmail' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="jb_wc_flexmail_settings[list_id]"><?php _e( 'Flexmail Contacts List', 'jb-wc-flexmail' ); ?></label></th>
						<td>
							<select class="widefat" id="jb_wc_flexmail_settings[list_id]" name="jb_wc_flexmail_settings[list_id]">
								<option value=""><?php echo esc_html__( 'Select list', 'jb-wc-flexmail' ); ?></option>
								<?php foreach ( $mailing_lists as $list_id => $list_name ) : ?>
								<option value="<?php echo esc_attr( $list_id ); ?>" <?php selected( $list_id, $options['list_id'] ); ?>><?php echo esc_html( $list_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'The list that corresponds with your <strong>general contacts database</strong>, i.e., the topmost list in Flexmail.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jb_wc_flexmail_settings[checkbox_label]"><?php _e( 'Checkbox Label Text', 'jb-wc-flexmail' ); ?></label></th>
						<td>
							<textarea class="widefat" id="jb_wc_flexmail_settings[checkbox_label]" name="jb_wc_flexmail_settings[checkbox_label]"><?php echo esc_html( $options['checkbox_label'] ); ?></textarea>
							<p class="description"><?php _e( 'The message customers see next to the opt-in checkbox.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Include Billing Address', 'jb-wc-flexmail' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="jb_wc_flexmail_settings[export_address]" name="jb_wc_flexmail_settings[export_address]" value="1" <?php checked( 1, $options['export_address'] ); ?> />
								<?php _e( 'Export physical address data', 'jb-wc-flexmail' ); ?>
							</label>
							<p class="description"><?php _e( 'If left unchecked, only name, email address and language&mdash;or, rather, the active site locale&mdash;wil be exported.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jb_wc_flexmail_settings[source_name]"><?php _e( 'Source Name', 'jb-wc-flexmail' ); ?></label></th>
						<td>
							<input class="widefat" type="text" id="jb_wc_flexmail_settings[source_name]" name="jb_wc_flexmail_settings[source_name]" value="<?php echo esc_attr( $options['source_name'] ); ?>" />
							<p class="description"><?php _e( 'In order to help with meaningful list segmentation, a source will be attached to each new contact. Default value: site name.', 'jb-wc-flexmail' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds the optional signup checkbox to the checkout form.
	 *
	 * @since 0.1.0
	 */
	public function display_checkbox() {
		$options = get_option( 'jb_wc_flexmail_settings', array() );

		if ( ! empty( $options ) ) {
			woocommerce_form_field( 'flexmail', array(
				'type' => 'checkbox',
				'required' => false,
				'label' => ( ! empty( $options['checkbox_label'] ) ? esc_html( $options['checkbox_label'] ) : '' ),
			) );
		}
	}

	/**
	 * If applicable, adds the customer to the Flexmail list of contacts.
	 *
	 * @since 0.1.0
	 */
	public function add_to_flexmail() {
		if ( ! isset( $_POST['flexmail'] ) ) {
			// The checkbox was left unchecked. Nothing to do here.
			return;
		}

		if ( empty( $_POST['billing_email'] ) || empty( $_POST['billing_first_name'] ) || empty( $_POST['billing_last_name'] ) ) {
			// At least one of the required customer details is missing. Bail.
			return;
		}

		$options = get_option( 'jb_wc_flexmail_settings', false );

		if ( empty( $options['user_id'] ) || empty( $options['user_token'] ) || empty( $options['list_id'] ) ) {
			// At least one of the required config settings is missing. Bail.
			return;
		}

		// Try and submit the customer's contact details to Flexmail's API.
		try {
			$SoapClient = new SoapClient(
				'https://soap.flexmail.eu/3.0.0/flexmail.wsdl',
				array(
					'location' => 'https://soap.flexmail.eu/3.0.0/flexmail.php',
					'uri' => 'https://soap.flexmail.eu/3.0.0/flexmail.php',
					'trace' => 1,
				)
			);

			$header = new stdClass();
			$header->userId = (int) $options['user_id'] ;
			$header->userToken = $options['user_token'];

			// Assemble the API request.
			$createEmailAddressReq = new stdClass();
			$createEmailAddressReq->header = $header;
			$createEmailAddressReq->mailingListId = $options['list_id'];

			$createEmailAddressReq->emailAddressType = new stdClass();
			$createEmailAddressReq->emailAddressType->emailAddress = sanitize_email( $_POST['billing_email'] );
			$createEmailAddressReq->emailAddressType->name = sanitize_text_field( $_POST['billing_first_name'] );
			$createEmailAddressReq->emailAddressType->surname = sanitize_text_field( $_POST['billing_last_name'] );
			$createEmailAddressReq->emailAddressType->language = substr( get_locale(), 0, 2 );

			// If the customer's address should be added, too.
			if ( ! empty( $options['export_address'] ) ) {
				$address = '';

				if ( ! empty( $_POST['billing_address_1'] ) ) {
					$address = sanitize_text_field( $_POST['billing_address_1'] );
				}

				if ( ! empty( $_POST['billing_address_2'] ) ) {
					$address .= ' ' . sanitize_text_field( $_POST['billing_address_2'] );
				}

				$address = trim( $address );

				if ( ! empty( $address ) ) {
					$createEmailAddressReq->emailAddressType->address = $address;
				}

				if ( ! empty( $_POST['billing_postcode'] ) ) {
					$createEmailAddressReq->emailAddressType->zipcode = sanitize_text_field( $_POST['billing_postcode'] );
				}

				if ( ! empty( $_POST['billing_city'] ) ) {
					$createEmailAddressReq->emailAddressType->city = sanitize_text_field( $_POST['billing_city'] );
				}

				if ( ! empty( $_POST['billing_city'] ) ) {
					$createEmailAddressReq->emailAddressType->phone = sanitize_text_field( $_POST['billing_phone'] );
				}

				if ( ! empty( $_POST['billing_company'] ) ) {
					$createEmailAddressReq->emailAddressType->company = sanitize_text_field( $_POST['billing_company'] );
				}
			}

			// If a source should be added. (Default value: site name.)
			if ( ! empty( $options['source_name'] ) ) {
				$source = new stdClass();
				$source->name = $options['source_name'];

				$createEmailAddressReq->sources = array( $source );
			}

			// Call the API.
			$createEmailAddressResp = $SoapClient->__soapCall( 'CreateEmailAddress', array( $createEmailAddressReq ) );

			if ( 0 === $createEmailAddressResp->errorCode ) {
				error_log( 'Email address created with ID: ' . $createEmailAddressResp->emailAddressId );
			} else {
				error_log( 'Email address creation failed: ' . $createEmailAddressResp->errorMessage );
			}
		} catch( Exception $ex ) {
			error_log( 'SOAP Exception: ' . $ex->getMessage() );
		}
	}

	/**
	 * Requests all of a Flexmail account's mailing lists.
	 * 
	 * @param int $user_id Flexmail API user ID.
	 * @param string $user_token Flexmail API user token.
	 * 
	 * @return array|void Array of mailing lists or nothing on failure.
	 *
	 * @since 0.1.0
	 */
	private function _get_mailing_lists( $user_id, $user_token ) {
		if ( empty( $user_id ) || empty( $user_token ) ) {
			return;
		}

		$mailing_lists = array();

		try {
			$SoapClient = new SoapClient(
				'https://soap.flexmail.eu/3.0.0/flexmail.wsdl',
				array(
					'location' => 'https://soap.flexmail.eu/3.0.0/flexmail.php',
					'uri' => 'https://soap.flexmail.eu/3.0.0/flexmail.php',
					'trace' => 1,
				)
			);

			$header = new stdClass();
			$header->userId = (int) $user_id ;
			$header->userToken = $user_token;

			$getMailingListsReq = new stdClass();
			$getMailingListsReq->header = $header;
			$getMailingListsReq->categoryId = 0; // 'GetMailingLists' requires a category ID; 0 is okay.

			$getMailingListsResp = $SoapClient->__soapCall( 'GetMailingLists', array( $getMailingListsReq ) );

			if ( 0 === $getMailingListsResp->errorCode ) {
				foreach ( $getMailingListsResp->mailingListTypeItems as $mailingListType ) {
					$mailing_lists[ $mailingListType->mailingListId ] = $mailingListType->mailingListName;
				}

				// Sorting by key, i.e., list ID. This should (?) put the
				// oldest list, and probably the one we're looking for, first.
				ksort( $mailing_lists );

				return $mailing_lists;
			} else {
				error_log( 'Fetching mailing lists failed: ' . $getMailingListsResp->errorMessage );
			}
		} catch( Exception $ex ) {
			error_log( 'SOAP Exception: ' . $ex->getMessage() );
		}
	}
}
endif;

new JB_Flexmail_WooCommerce();
