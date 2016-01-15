<?php

include_once TRANSIFEX_LIVE_INTEGRATION_DIRECTORY_BASE . '/includes/admin/transifex-live-integration-settings-util.php';

class Transifex_Live_Integration_Settings_Page {

	/**
	 * Function that handles loading setting data and displaying the admin page.
	 */
	static function options_page() {
		Plugin_Debug::logTrace();
		$db_settings = get_option( 'transifex_live_settings', array() );
		if ( !$db_settings ) {
			$db_settings = Transifex_Live_Integration_Defaults::settings();
		}

		$db_colors = array_map( 'esc_attr', (array) get_option( 'transifex_live_colors', array() ) );
		if ( empty( $db_colors ) ) {
			$db_colors = Transifex_Live_Integration_Defaults::settings()['colors'];
		}
		$colors_colors = [ 'colors' => $db_colors ];
		$raw_settings = array_merge( $db_settings, $colors_colors );
		$settings = array_merge( Transifex_Live_Integration_Defaults::settings(), $raw_settings );

		$is_update_transifex_languages = false;
		if ( isset( $settings['api_key'] ) && // Initialize Live languages after API key is setup.
				( '' === $settings['raw_transifex_languages'] ) ) { // TODO: This seems brittle add more safety.
			Plugin_Debug::logTrace("initial api_key set...updating transifex languages");
			$is_update_transifex_languages = true;
				}
		
		if (isset($settings['sync'])) {
			Plugin_Debug::logTrace("sync button...updating transifex languages");
			$is_update_transifex_languages = true;
		}
		
		if (strcmp($settings['api_key'],$settings['previous_api_key'] )!==0){
			Plugin_Debug::logTrace("api_key updated...updating transifex languages");
			$is_update_transifex_languages = true;
		}
				
		if ($is_update_transifex_languages) {	 
			$raw_api_response_check = Transifex_Live_Integration_Settings_Util::get_raw_transifex_languages( $settings['api_key'] );
			$raw_api_response = $raw_api_response_check ? $raw_api_response_check : null;
			if ( isset( $raw_api_response ) ) {
				// TODO Thesee are used in the template below should be refactored.
				$raw_transifex_languages = $raw_api_response;
				$settings['source_language'] = Transifex_Live_Integration_Settings_Util::get_source( $raw_api_response );
				$languages = Transifex_Live_Integration_Settings_Util::get_default_languages( $raw_api_response );
				$language_lookup = Transifex_Live_Integration_Settings_Util::get_language_lookup( $raw_api_response );
			}
		}
		// TODO Thesee are used in the template below should be refactored.
		if ( !isset( $raw_transifex_languages ) ) {
			$raw_transifex_languages = stripslashes( $settings['raw_transifex_languages'] );
		}
		if ( !isset( $languages ) ) {
			$languages = explode( ",", $settings['transifex_languages'] );
		}

		if ( !isset( $language_lookup ) ) {
			$language_lookup = json_decode( stripslashes( $settings['language_lookup'] ), true );
		}

		ob_start();
		include_once TRANSIFEX_LIVE_INTEGRATION_DIRECTORY_BASE . '/includes/admin/transifex-live-integration-settings-template.php';
		$content = ob_get_clean();
		echo $content;
	}

	/**
	 * Function that handles saving the setting data and sanitization.
	 */
	public function update_settings() {

		Plugin_Debug::logTrace();
		// TODO: Revisit use of Global POST object...maybe there is a WP API that can be used?
		if ( isset( $_POST['transifex_live_nonce'] ) && wp_verify_nonce( $_POST['transifex_live_nonce'], 'transifex_live_settings' ) ) {
			$settings = Transifex_Live_Integration_Settings_Page::sanitize_settings( $_POST );
			$transifex_languages = explode( ',', $settings['transifex_live_settings']['transifex_languages'] );
			$languages_regex = '';
			$languages_map = [ ];
			$languages = '';
			$trim = false;
			foreach ($transifex_languages as $lang) {
				$trim = true;
				$k = 'wp_language_' . $lang;
				$languages .= $settings['transifex_live_settings'][$k];
				$languages .= ',';
				$languages_regex .= $settings['transifex_live_settings'][$k];
				$languages_regex .= '|';
				$languages_map [$lang] = $settings['transifex_live_settings'][$k];
			}
			$languages = ($trim) ? rtrim( $languages, ',' ) : '';
			$languages_regex = ($trim) ? rtrim( $languages_regex, '|' ) : '';
			$languages_regex = '(' . $languages_regex . ')';
			$languages_map_string = htmlentities( json_encode( $languages_map ) ); // TODO: Switch to wp_json_encode.

			$settings['transifex_live_settings']['languages_map'] = $languages_map_string;
			$settings['transifex_live_settings']['languages_regex'] = $languages_regex;
			$settings['transifex_live_settings']['languages'] = $languages;
			if ( isset( $settings['transifex_live_settings'] ) ) {
				update_option( 'transifex_live_settings', $settings['transifex_live_settings'] );
			}

			if ( isset( $settings['transifex_live_colors'] ) ) {
				update_option( 'transifex_live_colors', $settings['transifex_live_colors'] );
			}
		}
	}

	/**
	 * Callback function that sets notifications in WP admin pages
	 */
	public function admin_notices_hook() {
		$is_admin_page_notice = false;
		
		$is_admin_dashboard_notice = false;
		
		// TODO: refactor this DB call to a better place.
		$settings = get_option( 'transifex_live_settings', array() );
		// TODO: might need to trap the state here when indices api_key or raw_transifex_languages are missing.
		
		$is_api_key_set_notice = (!isset($settings['api_key']))?true:false;
		
		$is_transifex_languages_set_notice = false;
		$is_transifex_languages_match = false;
		if ( ! $is_api_key_set_notice ) {
			$is_transifex_languages_set_notice = (!isset($settings['raw_transifex_languages']))?true:false;
			if (isset($settings['raw_transifex_languages'])) {
			$is_transifex_languages_match = Transifex_Live_Integration_Settings_Util::check_raw_transifex_languages( $settings['api_key'], $settings['raw_transifex_languages'] );
			}
			
			}
		
		$notice = '';
		if ( isset( $_POST['transifex_live_settings'] ) ) {
			$is_admin_page_notice = true;
			$notice = '<p>' . __( 'Your changes to the settings have been saved!', TRANSIFEX_LIVE_INTEGRATION_TEXT_DOMAIN ) . '</p>';
		}

		if ( isset( $_POST['transifex_live_colors'] ) ) {
			$is_admin_page_notice = true;
			$notice .= '<p>' . __( 'Your changes to the colors have been saved!', TRANSIFEX_LIVE_INTEGRATION_TEXT_DOMAIN ) . '</p>';
		}

		if ( $is_transifex_languages_set_notice ) {
			$is_admin_dashboard_notice = true;
			$notice .= '<p>*Yoda voice* A problem with the Transifex Live plugin, there was. A transmission [to us send], and happy to assist you we would be. https://www.transifex.com/contact/</p>';
		}
		
		if ( $is_api_key_set_notice ){
			$is_admin_dashboard_notice = true;
			$notice .= "<p><strong>Thanks for installing the Transifex Live WordPress plugin!</strong> [Add your API key] to make translations live for your site.</p>";
		}
		
		if ( $is_transifex_languages_match ) {
			$is_admin_dashboard_notice = true;
			$notice .= "<p>Looks like there were some changes to your published languages. Please reinstall the Transifex Live plugin.</p>";
		}
		
		if ( $is_admin_page_notice ) {
			echo '<div class="notice is-dismissable">' . $notice . '</div>';
		}
		if ( $is_admin_dashboard_notice ) {
			echo '<div class="update-nag is-dismissable">' . $notice . '</div>';
		}
		
	}

	/**
	 * Function called to ensure settings input is sane before saving to DB
	 * @param array $settings list of all settings saved in the WP DB.
	 */
	static public function sanitize_settings( $settings ) {
		Plugin_Debug::logTrace();
		$settings['transifex_live_settings']['api_key'] = ( isset( $settings['transifex_live_settings']['api_key'] )) ? sanitize_text_field( $settings['transifex_live_settings']['api_key'] ) : '';
		$settings['transifex_live_settings']['enable_frontend_css'] = ( $settings['transifex_live_settings']['enable_frontend_css'] ) ? 1 : 0;
		$settings['transifex_live_settings']['custom_picker_id'] = ( isset( $settings['transifex_live_settings']['custom_picker_id'] )) ? sanitize_text_field( $settings['transifex_live_settings']['custom_picker_id'] ) : '';
		$settings['transifex_live_settings']['raw_transifex_languages'] = ( isset( $settings['transifex_live_settings']['raw_transifex_languages'] )) ? sanitize_text_field( $settings['transifex_live_settings']['raw_transifex_languages'] ) : '';
		$settings['transifex_live_settings']['languages'] = ( isset( $settings['transifex_live_settings']['languages'] )) ? sanitize_text_field( $settings['transifex_live_settings']['languages'] ) : '';
		$settings['transifex_live_settings']['language_lookup'] = ( isset( $settings['transifex_live_settings']['language_lookup'] )) ? sanitize_text_field( $settings['transifex_live_settings']['language_lookup'] ) : '';

		$settings['transifex_live_colors']['accent'] = Transifex_Live_Integration_Settings_Util::sanitize_hex_color( $settings['transifex_live_colors']['accent'] );
		$settings['transifex_live_colors']['text'] = Transifex_Live_Integration_Settings_Util::sanitize_hex_color( $settings['transifex_live_colors']['text'] );
		$settings['transifex_live_colors']['background'] = Transifex_Live_Integration_Settings_Util::sanitize_hex_color( $settings['transifex_live_colors']['background'] );
		$settings['transifex_live_colors']['menu'] = Transifex_Live_Integration_Settings_Util::sanitize_hex_color( $settings['transifex_live_colors']['menu'] );
		$settings['transifex_live_colors']['languages'] = Transifex_Live_Integration_Settings_Util::sanitize_hex_color( $settings['transifex_live_colors']['languages'] );

		return $settings;
	}

}
