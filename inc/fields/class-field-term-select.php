<?php

class Shortcode_UI_Field_Term_Select {

	private static $instance;

	// All registered post fields.
	private $post_fields  = array();

	// Field Settings.
	private $fields = array(
		'term_select' => array(
			'template' => 'shortcode-ui-field-term-select',
			'view'     => 'editAttributeFieldTermSelect',
		),
	);

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	private function setup_actions() {

		add_filter( 'shortcode_ui_fields',             array( $this, 'filter_shortcode_ui_fields' ) );
		add_action( 'enqueue_shortcode_ui',            array( $this, 'action_enqueue_shortcode_ui' ) );
		add_action( 'wp_ajax_shortcode_ui_term_field', array( $this, 'action_wp_ajax_shortcode_ui_term_field' ) );
		add_action( 'shortcode_ui_loaded_editor',      array( $this, 'action_shortcode_ui_loaded_editor' ) );

	}

	public function filter_shortcode_ui_fields( $fields ) {
		return array_merge( $fields, $this->fields );
	}

	public function action_enqueue_shortcode_ui() {

		$plugin_dir = dirname( dirname( __FILE__ ) );

		wp_enqueue_script( 'select2', plugins_url( 'lib/select2/select2.min.js', $plugin_dir ) , array( 'jquery', 'jquery-ui-sortable' ), '3.5.2' );
		wp_enqueue_style( 'select2', plugins_url( 'lib/select2/select2.css', $plugin_dir ), null, '3.5.2' );

		wp_localize_script( 'shortcode-ui', 'shortcodeUiTermFieldData', array(
			'nonce' => wp_create_nonce( 'shortcode_ui_field_term_select' ),
		) );

	}

	/**
	 * Output styles and templates used by post select field.
	 */
	public function action_shortcode_ui_loaded_editor() {

		?>

		<style>

			.edit-shortcode-form .select2-container {
				min-width: 300px;
			}

			.edit-shortcode-form .select2-container a {
				transition: none;
				-webkit-transition: none;
			}

			.wp-admin .select2-drop {
				z-index: 160001;
			}

		</style>

		<script type="text/html" id="tmpl-shortcode-ui-field-term-select">
			<div class="field-block shortcode-ui-field-term-select shortcode-ui-attribute-{{ data.attr }}">
				<label for="{{ data.id }}">{{{ data.label }}}</label>
				<input type="text" name="{{ data.attr }}" id="{{ data.id }}" value="{{ data.value }}" class="shortcode-ui-term-select" />
				<# if ( typeof data.description == 'string' ) { #>
					<p class="description">{{{ data.description }}}</p>
				<# } #>
			</div>
		</script>

		<?php
	}

	/**
	 * Ajax handler for select2 post field queries.
	 * Output JSON containing post data.
	 * Requires that shortcode, attr and nonce are passed.
	 * Requires that the field has been correctly registered and can be found in $this->post_fields
	 * Supports passing page number and search query string.
	 *
	 * @return null
	 */
	public function action_wp_ajax_shortcode_ui_term_field() {

		$nonce               = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : null;
		$requested_shortcode = isset( $_GET['shortcode'] ) ? sanitize_text_field( $_GET['shortcode'] ) : null;
		$requested_attr      = isset( $_GET['attr'] ) ? sanitize_text_field( $_GET['attr'] ) : null;
		$response            = array();

		$shortcodes = Shortcode_UI::get_instance()->get_shortcodes();

		if ( ! wp_verify_nonce( $nonce, 'shortcode_ui_field_term_select' ) ) {
			wp_send_json_error( $response );
		}

		// Shortcode not found.
		if ( ! isset( $shortcodes[ $requested_shortcode ] ) ) {
			wp_send_json_error( $response );
		}

		$shortcode = $shortcodes[ $requested_shortcode ];

		$taxonomy = sanitize_key( $_GET['tax'] );

		$tax = get_taxonomy( 'post_tag' );
		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			wp_die( -1 );
		}

		$s = wp_unslash( $_GET['s'] );

		$comma = _x( ',', 'tag delimiter' );
		if ( ',' !== $comma )
			$s = str_replace( $comma, ',', $s );
		if ( false !== strpos( $s, ',' ) ) {
			$s = explode( ',', $s );
			$s = $s[count( $s ) - 1];
		}
		$s = trim( $s );

		$term_search_min_chars = (int) apply_filters( 'term_search_min_chars', 2, $tax, $s );
		
		if ( ( $term_search_min_chars == 0 ) || ( strlen( $s ) < $term_search_min_chars ) ){
			wp_die();
		}

		$results = get_terms( $taxonomy, array( 'name__like' => $s, 'fields' => 'names', 'hide_empty' => false ) );

		wp_send_json_success( $results );

	}

}
