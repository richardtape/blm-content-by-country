<?php
/*
Plugin Name: Content by Country
Plugin URI: #
Description: Provides an API to allow you to show content for users in a specific country.
Version: 1.0
Author: Richard Tape
Author URI: #
License: GPLv2
Text Domain: blm-content-by-country
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2018 Richard Tape.
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'BLMCBC__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

class BLM_Content_By_Country {

	/**
	 * Initialize ourselves.
	 *
	 * @return void
	 */
	public static function init() {

	}// end init()


	/**
	 * Wrapper method for most of the work here. Determine if the current visitor is from
	 * the passed country.
	 *
	 * @param string $country The country to check
	 * @return boolean True if user is in the passed country, false otherwise.
	 */
	public static function is_user_in( $country = 'CA' ) {

		$country_code_of_user = static::get_country_code_of_visitor( $country );

		if ( $country === $country_code_of_user ) {
			return true;
		}

		return false;

	}// end is_user_in()


	/**
	 * Get the country code for the current visitor.
	 *
	 * @return string The country code of the current visitor.
	 */
	public static function get_country_code_of_visitor( $default = 'CA' ) {

		// False by default because if we get a country code that isn't valid, we return false.
		$country = false;

		// If we are focrcing the country via a GET request, we override here. Still needs to be valid.
		if ( isset( $_GET['forcecountry'] ) ) { // WPCS: CSRF ok.

			$country = sanitize_text_field( wp_unslash( $_GET['forcecountry'] ) ); // WPCS: CSRF ok..

			// Validate.
			if ( false === static::validate_country_code( $country ) ) {
				return false;
			}

			// Bail early so we don't do unnecessary lookups for forced country checks.
			return apply_filters( 'blmcbc_get_country_of_visitor', $country, 'forced' );

		}

		$cookie_name = 'blm_country_code';

		// Check to see if we have a valid cookie, so we don't do lookups on every pageload.
		if ( isset( $_COOKIE[ $cookie_name ] ) && static::validate_country_code( $_COOKIE[ $cookie_name ] ) ) {
			return apply_filters( 'blmcbc_get_country_of_visitor', $_COOKIE[ $cookie_name ], 'cookie' );
		}

		// We are not forcing. So use visitor's IP Address to get their country.
		$country = static::get_country_of_user_from_ip();

		// If we don't have a country, revert to the default (or what is passed in as the override)
		if ( false === $country ) {
			$country = $default;
		}

		if ( false === static::validate_country_code( $country ) ) {
			return false;
		}

		// Set a cookie so we don't have to do this every pageload
		setcookie( $cookie_name, $country, 1 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

		return apply_filters( 'blmcbc_get_country_of_visitor', $country, 'calculated' );

	}// end get_country_code_of_visitor()


	/**
	 * Get the visitor's country from their IP Address.
	 *
	 * @return string|false The 2-letter country code for this visitor. False if it all goes wrong.
	 */
	public static function get_country_of_user_from_ip() {

		$users_ip = static::get_ip_address_for_user();

		$country = static::get_country_from_ip( $users_ip );

		return apply_filters( 'blmcbc_get_country_of_user_from_ip', $country, $users_ip );

	}// end get_country_of_user_from_IP()


	/**
	 * Get the visitor's IP Address.
	 *
	 * @return string The visitor's IP Address.
	 */
	public static function get_ip_address_for_user() {

		// Allow a local override
		if ( defined( 'BLM_CBC_IP_OVERRIDE' ) ) {
			$ip = constant( 'BLM_CBC_IP_OVERRIDE' );
			return apply_filters( 'blmcbc_get_ip_address_for_user', $ip );
		}

		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return apply_filters( 'blmcbc_get_ip_address_for_user', '0.0.0.0' );
		}

		$ip = $_SERVER['REMOTE_ADDR'];

		return apply_filters( 'blmcbc_get_ip_address_for_user', $ip );

	}// end get_ip_address_for_user()


	/**
	 * Get the country for the specified IP Address.
	 *
	 * @param string $ip The IP Address to look up.
	 * @return string The IP Address for the passed IP Address.
	 */
	public static function get_country_from_ip( $ip ) {

		$country = static::fetch_country_from_iplocate( $ip );

		return $country;

	}// end get_country_from_ip()


	/**
	 * Use https://www.iplocate.io/api/lookup/ to get the country data for the IP
	 *
	 * @param string $ip
	 * @return string The country code for the passed IP or false if one can not be detrmined.
	 */
	public static function fetch_country_from_iplocate( $ip ) {

		if ( ! $ip ) {
			return false;
		}

		// Validate IP Address
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		// We use the IPLocate service which is neat
		$base_url = 'https://www.iplocate.io/api/lookup/';

		$full_url = $base_url . $ip;

		$args = apply_filters( 'blmcbc_fetch_country_from_iplocate_get_request_args', array( 'timeout' => 3 ) );

		// Should return a JSON object as an array
		$response = wp_remote_get( $full_url, $args );

		if ( ! is_array( $response ) || is_wp_error( $response ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( $response ), true ), FILE_APPEND );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		// Parse JSON into a PHP Array
		$results = json_decode( $body, true );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return false;
		}

		if ( ! isset( $results['country_code'] ) ) {
			return false;
		}

		return apply_filters( 'blmcbc_fetch_country_from_iplocate', $results['country_code'], $ip );

	}// end fetch_country_from_iplocate()


	/**
	 * Validate the passed country code as one of the iso3166 country codes.
	 *
	 * @param string $country_code the 2-letter country code to validate
	 * @return string|false the 2-letter country code is valid, false otherwise
	 */
	public static function validate_country_code( $country_code ) {

		$country_code = strtoupper( $country_code );

		if ( ! in_array( $country_code, array_keys( static::get_code_to_country_list() ), true ) ) {
			return false;
		}

		return $country_code;

	}// end validate_country_code()


	/**
	 * Get the 2-letter country code to country name array.
	 *
	 * @return array The 2-letter country code => country name array.
	 */
	public static function get_code_to_country_list() {

		require_once BLMCBC__PLUGIN_DIR . 'blm-variables.php';

		global $code_to_country;
		return $code_to_country;

	}// end get_code_to_country_list()

	/**
	 * Get the country name to 2-letter country code array.
	 *
	 * @return array The country name => 2-letter country code array.
	 */
	public static function get_country_name_to_code_list() {

		require_once BLMCBC__PLUGIN_DIR . 'blm-variables.php';

		global $country_to_code;
		return $country_to_code;

	}// end get_country_name_to_code_list()

}// end class BLM_Content_By_Country

add_action( 'after_setup_theme', array( 'BLM_Content_By_Country', 'init' ) );
