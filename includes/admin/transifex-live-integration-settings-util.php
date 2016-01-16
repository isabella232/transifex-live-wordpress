<?php

class Transifex_Live_Integration_Settings_Util {

	const EMPTY_TRANSIFEX_LANGUAGES_PATTERN = '/^transifex_languages\(\{\"timestamp\":\".*\"\}\);/';

	/**
	 * Function to retrieve transifex_languages javascript
	 * @param string $api_key API key entered by user.
	 */
	static function get_raw_transifex_languages( $api_key ) {
		Plugin_Debug::logTrace();

		// TODO: move this url to the plugin constants.
		$languages_json_format = "https://cdn.transifex.com/%s/latest/languages.jsonp";
		$request_url = sprintf( $languages_json_format, $api_key );
		$response = wp_remote_get( $request_url ); // TODO: switch to vip_safe_wp_remote_get.
		$response_body = null;
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $response_code ) {
			$response_body = wp_remote_retrieve_body( $response );
			if ( preg_match( self::EMPTY_TRANSIFEX_LANGUAGES_PATTERN, $response_body ) ) {
				Plugin_Debug::logTrace("empty transifex languages file...skipping");
				return false;
			}
			return $response_body;
		}
		Plugin_Debug::logTrace("did not get a 200 getting transifex languages");
		return false;
	}

	/**
	 * Function to validate transifex_languages javascript
	 * @param string $api_key API key entered by user.
	 * @param string $raw_transifex_languages string to compare.
	 */
	static function check_raw_transifex_languages( $api_key,
			$raw_transifex_languages ) {
		Plugin_Debug::logTrace();
		$s = self::get_raw_transifex_languages( $api_key );
		return strcmp( $s, $raw_transifex_languages ) === 0 ? true : false;
	}

	/**
	 * Function to parse out languages array
	 * @param string $raw_transifex_languages string to parse.
	 */
	static function get_default_languages( $raw_transifex_languages ) {
		Plugin_Debug::logTrace();
		$reg = "/\s*transifex_languages\(\s*(.+?)\s*\);/";
		preg_match( $reg, $raw_transifex_languages, $m );
		$tl_array = json_decode( $m[1], true );
		$tl_t_array = $tl_array['translation'];
		$language_array = array_column( $tl_t_array, 'code' );
		if ( isset( $language_array ) ) {
			return $language_array;
		} else {
			return null;
		}
	}

	/**
	 * Function to parse out an assoc array of language mapping
	 * @param string $raw_transifex_languages string to parse.
	 */
	static function get_language_lookup( $raw_transifex_languages ) {
		Plugin_Debug::logTrace();
		$reg = "/\s*transifex_languages\(\s*(.+?)\s*\);/";
		preg_match( $reg, $raw_transifex_languages, $m );
		$tl_array = json_decode( $m[1], true );
		$tl_t_array = $tl_array['translation'];
		$f = function( $x ) { 
			return ['code' => $x['code'], 'name' => $x['tx_name'] ];
		};
		$language_array = array_map( $f, $tl_t_array );
		if ( isset( $language_array ) ) {
			return $language_array;
		} else {
			return null;
		}
	}
	
	/**
	 * Function to parse out source language
	 * @param string $raw_transifex_languages string to parse.
	 */
	static function get_source( $raw_transifex_languages ) {
		Plugin_Debug::logTrace();
		$reg = "/\s*transifex_languages\(\s*(.+?)\s*\);/";
		preg_match( $reg, $raw_transifex_languages, $m );
		$tl_array = json_decode( $m[1], true );
		$tl_s_array = $tl_array['source'];
		$source_string = $tl_s_array['code'];
		if ( isset( $source_string ) ) {
			return $source_string;
		} else {
			return null;
		}
	}

	/**
	 * Type checking function for lists
	 * @param array $list array to sanitize.
	 * 
	 * TODO This feels like wheel re-invention...look for a library
	 */
	static function sanitize_list( $list ) {
		Plugin_Debug::logTrace();
		$list_arr = explode( ',', $list );

		if ( empty( $list_arr ) ) {
			'';
		}

		$count = count( $list_arr );
		for ( $i = 0; $i < $count; $i++ ) {
			$list_arr[ $i ] = sanitize_html_class( $list_arr[$i] );
		}

		$list_arr = array_filter( $list_arr );
		return implode( ',', $list_arr );
	}

	/**
	 * Type checking function for colors
	 * @param string $color value to check.
	 * 
	 * TODO This feels like wheel re-invention...look for a library
	 */
	static function sanitize_hex_color( $color ) {
		Plugin_Debug::logTrace();

		if ( '' === $color ) {
			return '';
		}

		// 3 or 6 hex digits, or the empty string.
		if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
			return $color;
		}

		return null;
	}

	/**
	 * Admin template helper function for rendering Language UI
	 * @param array $language_array list of languages.
	 * @param array $settings all plugin settings.
	 * 
	 * TODO This needs to be moved to a template file.
	 */
	static function render_language_mapper( $language_array, $settings ) {
		Plugin_Debug::logTrace();

		if ( ! isset( $language_array ) || ! count( $language_array ) > 0 ) {
			Plugin_Debug::logTrace( "$language_array not valid" );
			return false;
		}
		$header_label = __( 'Language URLs', TRANSIFEX_LIVE_INTEGRATION_TEXT_DOMAIN );
		$source_language = $settings['source_language'];
		$source_label = __( 'Source Language', TRANSIFEX_LIVE_INTEGRATION_TEXT_DOMAIN );
		ob_start();
		checked( $settings['add_language_rewrites'], 'none' );
		$selected_none = ob_get_clean();

		ob_start();
		checked( $settings['add_language_rewrites'], 1 );
		$selected_opt1 = ob_get_clean();

		ob_start();
		checked( $settings['add_language_rewrites'], 2 );
		$selected_opt2 = ob_get_clean();

		ob_start();
		checked( $settings['add_language_rewrites'], 3 );
		$selected_opt3 = ob_get_clean();

		$enable_hreflang_label = __( 'Enable HREFLANG?', TRANSIFEX_LIVE_INTEGRATION_TEXT_DOMAIN );
		ob_start();
		checked( $settings['hreflang'] );
		$checked_hreflang = ob_get_clean();

		$mapper = <<<SOURCE
		<th scope="row" class="titledesc">$header_label</th>
        <td class="forminp">
		<input type="radio" id="transifex_live_settings_add_language_rewrites" name="transifex_live_settings[add_language_rewrites]" value="none" $selected_none >None Selected
		<br/>
        <input type="radio" id="transifex_live_settings_add_language_rewrites" name="transifex_live_settings[add_language_rewrites]" value="1" $selected_opt1>to all URLs
		<br/>
		<input type="radio" id="transifex_live_settings_add_language_rewrites" name="transifex_live_settings[add_language_rewrites]" value="2" $selected_opt2>to pages only
		<br/>
        <input type="radio" id="transifex_live_settings_add_language_rewrites" name="transifex_live_settings[add_language_rewrites]" value="3" $selected_opt3>Custom (Only adds %lang% url pattern)
		<br/><br/>
		<fieldset>
        <legend class="screen-reader-text"><span>$enable_hreflang_label</span></legend>
        <label for="transifex_live_settings_hreflang">
        <input name="transifex_live_settings[hreflang]" type="hidden" value="0">
        <input name="transifex_live_settings[hreflang]" type="checkbox" id="transifex_live_settings_hreflang" value="1" $checked_hreflang>
		$enable_hreflang_label
        </label>
        </fieldset>
		<br/><br/>
		<label class="enable_checkbox" for="transifex_live_settings_source_language">
		$source_label
		<input disabled name="transifex_live_settings[source_language]" type="text" id="transifex_live_settings_source_language" value="$source_language" class="regular-text">
		<input type="hidden" value="$source_language" name="transifex_live_settings[source_language]" id="transifex_live_settings_source_language" />
		</label>
	    <br/><br/>
SOURCE;

		foreach ( $language_array as $item ) {
			$name = $item['name'];
			$code = $item['code'];
			$value = (isset( $settings['wp_language_' . $item['code']] )) ? $settings['wp_language_' . $item['code']] : $item['code'];
			$mapper .= <<<MAPPER
			<input disabled="true" type="text" class="regular-text" style="width:200px" name="transifex_live_settings[tx_language_$code]" value="$name" />
            <input type="text" name="transifex_live_settings[wp_language_$code]" id="transifex_live_settings_wp_language_$code" value="$value" class="regular-text">
            <br/>
MAPPER;
		}
		$mapper .= <<<FOOTER
				<p class="submit"><input type="submit" name="sync" id="sync" class="button button-primary" value="Sync"></p>
				</td>
FOOTER;
		echo $mapper;
		return true;
	}

	/**
	 * Admin template helper function for color picker
	 * @param string $name HTML 'name'.
	 * @param string $id HTML 'id'.
	 * @param string $value HTML current value.
	 * 
	 * TODO This needs to be moved to a template file.
	 */
	static function color_picker( $name, $id, $value ) {
		Plugin_Debug::logTrace();
		$header_name = esc_html( $name );
		$input_name = esc_attr( $id );
		$input_id = esc_attr( str_replace( array( '[', ']' ), array( '_', '' ), $id ) );
		$input_value = esc_attr( $value );
		$div_id = 'colorPickerDiv_' . esc_attr( $id );
		$picker = <<<PICKER
            <div class="color-box"><strong>$header_name</strong>
				<input name="$input_name" id="$input_id" type="text" value="$input_value" class="colorpick" />
				<div id="$div_id" class="colorpickdiv"></div>
			</div>
PICKER;
		echo $picker;
	}

}