<?php
/**
 * Plugin Name: Rank Math API Manager
 * Plugin URI: https://devora.no/plugins/rankmath-api-manager
 * Description: A WordPress plugin that manages the update of Rank Math metadata (SEO Title, SEO Description, Canonical URL, Focus Keyword) via the REST API for WordPress posts and WooCommerce products.
 * Version: 1.0.6
 * Author: Devora AS
 * Author URI: https://devora.no
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: rank-math-api-manager
 * GitHub Plugin URI: https://github.com/devora-as/rank-math-api-manager
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rank_Math_API_Manager_Extended {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
	}

	public function register_meta_fields() {
		$meta_fields = [
			'rank_math_title'         => 'SEO Title',
			'rank_math_description'   => 'SEO Description',
			'rank_math_canonical_url' => 'Canonical URL',
			'rank_math_focus_keyword' => 'Focus Keyword',
		];

		$post_types = [ 'post' ];
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		foreach ( $post_types as $post_type ) {
			foreach ( $meta_fields as $meta_key => $description ) {
				$args = [
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'description'   => $description,
					'auth_callback' => [ $this, 'check_update_permission' ],
				];

				register_post_meta( $post_type, $meta_key, $args );
			}
		}
	}

	public function register_api_routes() {
		register_rest_route( 'rank-math-api/v1', '/update-meta', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_rank_math_meta' ],
			'permission_callback' => [ $this, 'check_update_permission' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && get_post( $param );
					}
				],
				'rank_math_title'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'rank_math_description'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'rank_math_canonical_url' => [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
				'rank_math_focus_keyword' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );
	}

	public function update_rank_math_meta( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );
		$fields  = [
			'rank_math_title',
			'rank_math_description',
			'rank_math_focus_keyword',
			'rank_math_canonical_url',
		];

		$result = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );

			if ( $value !== null ) {
				$update_result = update_post_meta( $post_id, $field, $value );
				$result[ $field ] = $update_result ? 'updated' : 'failed';
			}
		}

		if ( empty( $result ) ) {
			return new WP_Error( 'no_update', 'No metadata was updated', [ 'status' => 400 ] );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function check_update_permission() {
		return current_user_can( 'edit_posts' );
	}
}

new Rank_Math_API_Manager_Extended();
