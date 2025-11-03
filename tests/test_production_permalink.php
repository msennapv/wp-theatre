<?php
/**
 * Verifies the production permalink base behaves correctly and the admin save
 * handler enforces capability and nonce protections introduced for security.
 */
class WPT_Test_Production_Permalink extends WP_UnitTestCase {

	protected function setUp(): void {
		global $wp_theatre;

		unset($wp_theatre->production_permalink->options);
		
		parent::setUp();

	}

	function activate_pretty_permalinks() {
		/* 
		 * Make sure permalink structure is consistent when running query tests.
		 * @see: https://core.trac.wordpress.org/ticket/27704#comment:7
		 * @see: https://core.trac.wordpress.org/changeset/28967
		 * @see: https://github.com/slimndap/wp-theatre/issues/48
		 */
		global $wp_rewrite;
		$wp_rewrite->init(); 
		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies(); 
		$wp_rewrite->flush_rules();		

		global $wp_theatre;
		$wp_theatre->setup->register_post_types();
	}
	
	function create_production() {
		$production_args = array(
			'post_type' => WPT_Production::post_type_name,
		);
		return $this->factory->post->create( $production_args );
	}

	/**
	 * Ensures classic query-style permalinks are used when structure is disabled.
	 */
	function test_production_permalink_is_off() {
		global $wp_rewrite;
		$wp_rewrite->init(); 
		$wp_rewrite->set_permalink_structure( '' );
		create_initial_taxonomies(); 
		$wp_rewrite->flush_rules();		

		$production_id = $this->create_production();
		$production = get_post($production_id);

		$expected = trailingslashit(home_url().'/?'.WPT_Production::post_type_name.'='.$production->post_name);
		$returned = trailingslashit(get_permalink($production_id));

		$this->assertEquals($expected, $returned);
	}
	
	/**
	 * Confirms the default pretty permalink base renders correctly.
	 */
	function test_production_permalink_default() {
		$this->activate_pretty_permalinks();
		
		$production_id = $this->create_production();
		$production = get_post($production_id);

		$expected = trailingslashit(home_url().'/production/'.$production->post_name);
		$returned = trailingslashit(get_permalink($production_id));

		$this->assertEquals($expected, $returned);
	}
	
	/**
	 * Verifies saving a custom base stores a sanitized slug option.
	 */
	function test_production_permalink_is_set() {
		global $wp_theatre;
		
		$base = 'hallo123';
		$wp_theatre->production_permalink->save_base($base);

		$options = get_option( 'wpt/production/permalink' );

		$expected = $options['base'];
		$returned = '/'.$base;

		$this->assertEquals($expected, $returned);
	}
	
	/**
	 * Checks the default base is returned when no custom option exists.
	 */
	function test_production_permalink_is_retrieved() {
		global $wp_theatre;

		$base = $wp_theatre->production_permalink->get_base();

		$expected = '/production';
		$returned = $base;

		$this->assertEquals($expected, $returned);
	}
	
	/**
	 * Confirms production permalinks use the saved custom base in URLs.
	 */
	function test_production_permalink_custom() {
		global $wp_theatre;
		
		$base = 'hallo456';
		$wp_theatre->production_permalink->save_base($base);

		$this->activate_pretty_permalinks();
		
		$production_id = $this->create_production();
		$production = get_post($production_id);

		$expected = trailingslashit(home_url().'/'.$base.'/'.$production->post_name);
		$returned = trailingslashit(get_permalink($production_id));

		$this->assertEquals($expected, $returned);		
	}

	/**
	 * Blocks unauthenticated updates, preventing capability bypass in admin_init.
	 */
	function test_save_settings_requires_capability() {
		global $wp_theatre;

		$original_base = $wp_theatre->production_permalink->get_base();

		// Mimic being on the permalink settings screen so is_admin() passes.
		set_current_screen( 'options-permalink' );

		$_POST['wpt_production_permalink_base'] = 'custom';
		$_POST['wpt_production_permalink_custom_base'] = 'pwned';

		wp_set_current_user( 0 );

		$wp_theatre->production_permalink->save_settings();

		$this->assertEquals( $original_base, $wp_theatre->production_permalink->get_base() );

		unset( $_POST['wpt_production_permalink_base'] );
		unset( $_POST['wpt_production_permalink_custom_base'] );

		// Switch back to the public-facing screen to avoid polluting other tests.
		set_current_screen( 'front' );
	}

	/**
	 * Requires the core permalink nonce to thwart CSRF attempts.
	 */
	function test_save_settings_requires_nonce() {
		global $wp_theatre;

		$original_base = $wp_theatre->production_permalink->get_base();

		set_current_screen( 'options-permalink' );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['wpt_production_permalink_base'] = 'custom';
		$_POST['wpt_production_permalink_custom_base'] = 'pwned';

		$wp_theatre->production_permalink->save_settings();

		$this->assertEquals( $original_base, $wp_theatre->production_permalink->get_base() );

		unset( $_POST['wpt_production_permalink_base'] );
		unset( $_POST['wpt_production_permalink_custom_base'] );

		// Reset the screen after the assertion for isolation.
		set_current_screen( 'front' );
	}

	/**
	 * Allows authorized admins with a valid nonce to change the base slug.
	 */
	function test_save_settings_updates_when_authorized() {
		global $wp_theatre;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'options-permalink' );

		$_POST['wpt_production_permalink_base'] = 'custom';
		$_POST['wpt_production_permalink_custom_base'] = 'pwned';
		$_POST['_wpnonce'] = wp_create_nonce( 'update-permalink' );
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

		$wp_theatre->production_permalink->save_settings();

		$this->assertEquals( '/pwned', $wp_theatre->production_permalink->get_base() );

		$wp_theatre->production_permalink->save_base( 'production' );

		// Reset the screen back to the default admin state for subsequent tests.
		set_current_screen( 'front' );

		unset( $_POST['wpt_production_permalink_base'] );
		unset( $_POST['wpt_production_permalink_custom_base'] );
		unset( $_POST['_wpnonce'] );
		unset( $_REQUEST['_wpnonce'] );
	}

	
}
