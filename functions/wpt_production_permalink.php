<?php
/**
 * The production permalink.
 *
 * Manages the permalink structure for the productions.
 * The permalink structure can be set on the Wordpress permalink settings page.
 *
 * @since 0.12
 */
class WPT_Production_Permalink {
	
	public $options;

	function __construct() {
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );

		$this->options = get_option( 'wpt/production/permalink' );
	}

	/**
	 * Adds the production permalink section to the permalink settings page.
	 *
	 * @since	0.12
	 * @return	void
	 */
	public function add_settings() {
		add_settings_section(
			'wpt_production_permalink',
			__( 'Theater permalinks', 'theatre' ),
			array( $this, 'settings_section' ),
			'permalink'
		);
	}

	/**
	 * Gets the default permalink base.
	 *
	 * @since 	0.12
	 * @return 	string	The default permalink base.
	 */
	public function get_base_default() {
		$default = '/'._x( 'production', 'slug', 'theatre' );

		/**
		 * Filter the default production permalink base.
		 *
		 * @since 	0.12
		 * @param	string	$default	The default production permalink base.
		 */
		$default = apply_filters( 'wpt/production/permalink/base/default', $default );

		return $default;
	}

	/**
	 * Gets the production permalink base.
	 *
	 * @since	0.12
	 * @return 	string	The production permalink base.
	 */
	public function get_base() {
		$base = empty( $this->options['base'] ) ? $this->get_base_default() : $this->options['base'];

		/**
		 * Filter the production permalink base.
		 *
		 * @since	0.12
		 * @param	string	$base	The production permalink base.
		 */
		$base = apply_filters( 'wpt/production/permalink/base', $base );

		return $base;
	}

	/**
	 * Save the production permalink base.
	 *
	 * @since	0.12
	 * @param 	string	$base	The production permalink base.
	 * @return	void
	 */
	public function save_base($base = '') {
		if ( empty($base) ) {
			$base = $this->get_base_default();
		}

		$base = '/'.trim( $base, '/' );

		$this->options['base'] = $base;
		update_option( 'wpt/production/permalink', $this->options );
	}

	/**
	 * Save the production permalink settings.
	 *
	 * @since	0.12
	 * @since	0.19	Require manage_options capability and the permalink nonce before saving.
	 * @return 	void
	 */
	public function save_settings() {

		if ( ! isset( $_POST['wpt_production_permalink_base'] ) ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['_wpnonce'] ) ) {
			return;
		}

		// Match the core nonce used on the permalink settings screen.
		check_admin_referer( 'update-permalink' );

		$base = sanitize_text_field( $_POST['wpt_production_permalink_base'] );

		if ( 'custom' == $base ) {
			$custom_base = isset( $_POST['wpt_production_permalink_custom_base'] ) ? $_POST['wpt_production_permalink_custom_base'] : '';
			$base = sanitize_text_field( $custom_base );
		}

		$this->save_base( $base );
	}

	/**
	 * Outputs the production permalink settings section.
	 *
	 * @since 	0.12
	 * @return 	void
	 */
	public function settings_section() {
		global $wp_theatre;

		$html = '';

		$html .= wpautop( __( 'These settings control the permalinks used for Theater productions. These settings only apply when <strong>not using "default" permalinks above</strong>.', 'theatre' ) );

		$html .= '<table class="form-table">';
		$html .= '<tbody>';

		$permalink_options = array(
			'production' => array(
				'structure' => $this->get_base_default(),
				'title' => __( 'Production', 'theatre' ),
				'example' => home_url().trailingslashit( $this->get_base_default() ).__( 'sample-production','theatre' ),
			),
		);

		$option_checked = false;

		foreach ( $permalink_options as $name => $args ) {
			$html .= '<tr>';
			$html .= '<th>';
			$html .= '<label>';
			$html .= '<input name="wpt_production_permalink_base" type="radio" value="'.$args['structure'].'"';
			$html .= ' '.checked( $args['structure'], $this->get_base(), false ).' />';
			$html .= ' '.$args['title'];
			$html .= '</label>';
			$html .= '</th>';
			$html .= '<td><code>'.$args['example'].'</code></td>';
			$html .= '</tr>';

			if ( $args['structure'] == $this->get_base() ) {
				$option_checked = true;
			}
		}

		$html .= '<tr>';
		$html .= '<th>';
		$html .= '<label>';
		$html .= '<input name="wpt_production_permalink_base" type="radio" value="custom" '.checked( $option_checked, false, false ).' />';
		$html .= __( 'Custom Base', 'theatre' );
		$html .= '</label>';
		$html .= '</th>';
		$html .= '<td>';
		$html .= '<code>'.untrailingslashit( home_url() ).'</code>';
		$html .= '<input name="wpt_production_permalink_custom_base" type="text" value="'.esc_attr( $this->get_base() ).'" class="regular-text code">';
		$html .= '</td>';
		$html .= '</tr>';

		$html .= '</tbody>';
		$html .= '</table>';

		echo $html;
	}

}
