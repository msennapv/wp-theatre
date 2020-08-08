<?php

/**
 * Test the listings page (and calendar).
 *
 * @group 	listing_page
 * @since	0.?
 */
class WPT_Test_Listing_Page extends WP_UnitTestCase {

	function setUp() {
		global $wp_rewrite; 
		global $wp_theatre;
		
		parent::setUp();
		
		$this->wp_theatre = $wp_theatre;
		
		// create a page for our listing
		$args = array(
			'post_type'=>'page'
		);
		
		$this->options = array(
			'listing_page_post_id' => $this->factory->post->create($args),
			'listing_page_position' => 'above'
		);	
		update_option('wpt_listing_page', $this->options);
		
		$season_args = array(
			'post_type'=>WPT_Season::post_type_name
		);
		
		$production_args = array(
			'post_type'=>WPT_Production::post_type_name
		);
		
		$event_args = array(
			'post_type'=>WPT_Event::post_type_name
		);
		
		/*
		 * Test content
		 */
		 
		// create seasons
		$this->season1 = $this->factory->post->create($season_args);
		$this->season2 = $this->factory->post->create($season_args);
		
		//create categories
		$this->category_muziek = wp_create_category('muziek');
		$this->category_film = wp_create_category('film');
		
		// create production with upcoming event
		$this->production_with_upcoming_event = $this->factory->post->create($production_args);
		add_post_meta($this->production_with_upcoming_event, WPT_Season::post_type_name, $this->season1);
		wp_set_post_categories($this->production_with_upcoming_event, array($this->category_muziek));
		wp_set_post_tags($this->production_with_upcoming_event,array('upcoming'));

		$this->upcoming_event_with_prices = $this->factory->post->create($event_args);
		add_post_meta($this->upcoming_event_with_prices, WPT_Production::post_type_name, $this->production_with_upcoming_event);
		add_post_meta($this->upcoming_event_with_prices, 'event_date', date('Y-m-d H:i:s', time() + (2 * DAY_IN_SECONDS)));
		add_post_meta($this->upcoming_event_with_prices, '_wpt_event_tickets_price', 12);
		add_post_meta($this->upcoming_event_with_prices, '_wpt_event_tickets_price', 8.5);
		
		// create production with 2 upcoming events
		$this->production_with_upcoming_events = $this->factory->post->create($production_args);
		add_post_meta($this->production_with_upcoming_events, WPT_Season::post_type_name, $this->season2);
		wp_set_post_categories($this->production_with_upcoming_events, array($this->category_muziek,$this->category_film));

		$upcoming_event = $this->factory->post->create($event_args);
		add_post_meta($upcoming_event, WPT_Production::post_type_name, $this->production_with_upcoming_events);
		add_post_meta($upcoming_event, 'event_date', date('Y-m-d H:i:s', time() + DAY_IN_SECONDS));
		add_post_meta($upcoming_event, 'tickets_status', 'other tickets status' );

		$upcoming_event = $this->factory->post->create($event_args);
		add_post_meta($upcoming_event, WPT_Production::post_type_name, $this->production_with_upcoming_events);
		add_post_meta($upcoming_event, 'event_date', date('Y-m-d H:i:s', time() + (3 * DAY_IN_SECONDS)));
		add_post_meta($upcoming_event, 'tickets_status', WPT_Event::tickets_status_cancelled );
		
		// create production with a historic event
		$this->production_with_historic_event = $this->factory->post->create($production_args);
		$event_id = $this->factory->post->create($event_args);
		add_post_meta($event_id, WPT_Production::post_type_name, $this->production_with_historic_event);
		add_post_meta($event_id, 'event_date', date('Y-m-d H:i:s', time() - DAY_IN_SECONDS));
		wp_set_post_tags($this->production_with_historic_event,array('historic'));

		// create sticky production with a historic event
		$this->production_with_historic_event_sticky = $this->factory->post->create($production_args);
		$event_id = $this->factory->post->create($event_args);
		add_post_meta($event_id, WPT_Production::post_type_name, $this->production_with_historic_event_sticky);
		add_post_meta($event_id, 'event_date', date('Y-m-d H:i:s', time() - YEAR_IN_SECONDS));
		stick_post($this->production_with_historic_event_sticky);
		wp_set_post_tags($this->production_with_historic_event_sticky,array('historic'));
		
		// create sticky production with an upcoming and a historic event
		$this->production_with_upcoming_and_historic_events = $this->factory->post->create($production_args);
		$event_id = $this->factory->post->create($event_args);
		add_post_meta($event_id, WPT_Production::post_type_name, $this->production_with_upcoming_and_historic_events);
		add_post_meta($event_id, 'event_date', date('Y-m-d H:i:s', time() - WEEK_IN_SECONDS));
		$event_id = $this->factory->post->create($event_args);
		add_post_meta($event_id, WPT_Production::post_type_name, $this->production_with_upcoming_and_historic_events);
		add_post_meta($event_id, 'event_date', date('Y-m-d H:i:s', time() + WEEK_IN_SECONDS));
		stick_post($this->production_with_upcoming_and_historic_events);
		add_post_meta($this->upcoming_event_with_prices, '_wpt_event_tickets_price', 12);
		add_post_meta($upcoming_event, 'tickets_status', WPT_Event::tickets_status_hidden );


		/* 
		 * Make sure permalink structure is consistent when running query tests.
		 * @see: https://core.trac.wordpress.org/ticket/27704#comment:7
		 * @see: https://core.trac.wordpress.org/changeset/28967
		 * @see: https://github.com/slimndap/wp-theatre/issues/48
		 */
		$wp_rewrite->init(); 
		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies(); 
		
		// Add theater rewrite rules.
		$wp_theatre->listing_page->init();
		$wp_rewrite->flush_rules();
	}
	
	function get_matching_rewrite_rule( $path ) {
		$rewrite_rules = get_option( 'rewrite_rules' );

		$match_path = untrailingslashit( parse_url( esc_url( $path ), PHP_URL_PATH ) );
		$wordpress_subdir_for_site = parse_url( home_url(), PHP_URL_PATH );
		if ( ! empty( $wordpress_subdir_for_site ) ) {
			$match_path = str_replace( $wordpress_subdir_for_site, '', $match_path );
		}
		$match_path = ltrim( $match_path, '/' );

		$target = false;
		// Loop through all the rewrite rules until we find a match
		foreach( $rewrite_rules as $rule => $maybe_target ) {
			if ( preg_match( "!^$rule!", $match_path, $matches ) ) {
				$target = $maybe_target;
				break;
			}
		}
		
		return $target;
	}


	/* Test the basics */

	function test_dedicated_listing_page_is_set() {
		$this->assertInstanceOf('WP_Post',$this->wp_theatre->listing_page->page());
	}
	
	function test_listing_appears_on_listing_page() {
		$this->go_to( get_permalink( $this->wp_theatre->listing_page->page() ) );

		$html = get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '<div class="wpt_listing'), $html);
	}
		
	/* 
	 * Test output 
	 * 
	 * type (productions, events)
	 * pagination (month, category)
	 * grouping (month)
	 * template 
	 */

	function test_productions_on_listing_page() {
		$this->go_to( get_permalink( $this->wp_theatre->listing_page->page() ) );

		$html = get_echo( 'the_content' );

		/*
		 * Unlike [wpt_productions], listing page only shows productions with upcoming events.
		 * Expected: 3 productions with upcoming events + 1 sticky production
		 */

		$this->assertEquals(4, substr_count($html, '"wp_theatre_prod"'), $html);
	}
	
	function test_events_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);
		
		$this->go_to( get_permalink( $this->wp_theatre->listing_page->page() ) );

		$html = get_echo( 'the_content' );
		$this->assertEquals(4, substr_count($html, '"wp_theatre_event"'), $html);
	}
	
	function test_events_on_listing_page_with_content_field() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_template'] = '{{title}}{{content}}{{tickets}}';
		$this->options['listing_page_position'] = 'above';

		update_option('wpt_listing_page', $this->options);

		$this->go_to( get_permalink( $this->wp_theatre->listing_page->page() ) );

		$html = get_echo( 'the_content' );
		
		$this->assertEquals(4, substr_count($html, '"wp_theatre_event"'), $html);
		$this->assertEquals(4, substr_count($html, '"wp_theatre_prod_content"'), $html);

	}
	
	function test_events_are_paginated_by_day_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'day';
		update_option('wpt_listing_page', $this->options);

		$days = $this->wp_theatre->events->get_days(array('upcoming' => true));
		$days = array_keys($days);
		
		$url = add_query_arg(
			'wpt_day',
			$days[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);
		
		$html = get_echo( 'the_content' );

		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination day"'), $html);
		
		// Test if pagination navigation is present.
		$this->assertEquals(4, substr_count($html, '<span class="wpt_listing_filter'), $html);
		
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active'), $html);
		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination day"'), $html);
		
	}
	
	function test_events_are_paginated_by_week_on_listing_page() {
		
	}
	
	function test_events_are_paginated_by_month_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'month';
		update_option('wpt_listing_page', $this->options);

		$months = $this->wp_theatre->events->get_months(array('upcoming' => true));
		$months = array_keys($months);
		
		$url = add_query_arg(
			'wpt_month',
			$months[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);
		
		$html = get_echo( 'the_content' );

		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination month"'), $html);
		$this->assertContains('"wp_theatre_event"', $html);
	}
	
	function test_events_are_paginated_by_year_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'year';
		update_option('wpt_listing_page', $this->options);

		$years = $this->wp_theatre->events->get_years(array('upcoming' => true));
		$years = array_keys($years);
		
		$url = add_query_arg(
			'wpt_year',
			$years[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);
		
		$html = get_echo( 'the_content' );

		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination year"'), $html);
		$this->assertContains('"wp_theatre_event"', $html);
		
	}
	
	function test_productions_are_paginated_by_category_on_listing_page() {
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'category';
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_category',
			'film',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination category"'), $html);
		$this->assertEquals(1, substr_count($html, '"wp_theatre_prod"'), $html);
		
	}

	function test_productions_are_paginated_by_tag_on_listing_page() {
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'tag';
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_tag',
			'upcoming',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination tag"'), $html);
		$this->assertEquals(1, substr_count($html, '"wp_theatre_prod"'), $html);
		
	}

	function test_events_are_paginated_by_category_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'category';
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_category',
			'film',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination category"'), $html);
		$this->assertEquals(2, substr_count($html, '"wp_theatre_event"'), $html);
		
	}

	function test_events_are_paginated_by_tag_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'tag';
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_tag',
			'upcoming',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_filter_pagination tag"'), $html);
		$this->assertEquals(1, substr_count($html, '"wp_theatre_event"'), $html);
		
	}

	function test_events_are_grouped_by_day_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'day';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(4, substr_count($html, '"wpt_listing_group day"'), $html);
		
	}
	
	function test_events_are_grouped_by_week_on_listing_page() {
		
	}
	
	function test_events_are_grouped_by_month_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'month';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);

		$html= get_echo( 'the_content' );
		$this->assertContains('"wpt_listing_group month"', $html);
	}
	
	function test_events_are_grouped_by_year_on_listing_page() {
		
	}
	
	function test_productions_are_grouped_by_season_on_listing_page() {
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'season';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(2, substr_count($html, '"wpt_listing_group season"'), $html);
	}
	
	function test_productions_are_grouped_by_category_on_listing_page() {
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'category';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);
		$html= get_echo( 'the_content' );
		$this->assertEquals(2, substr_count($html, '"wpt_listing_group category"'), $html);
	}
		
	function test_productions_are_grouped_by_tag_on_listing_page() {
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'tag';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);
		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_group tag"'), $html);
	}
	
	function test_events_are_grouped_by_category_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'category';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(2, substr_count($html, '"wpt_listing_group category"'), $html);

	}
	
	function test_events_are_grouped_by_tag_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'grouped';
		$this->options['listing_page_groupby'] = 'tag';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->wp_theatre->listing_page->page())
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '"wpt_listing_group tag"'), $html);

	}
	
	function test_events_are_filtered_by_day_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$days = $this->wp_theatre->events->get_days(array('upcoming' => true));
		$days = array_keys($days);
		
		$url = add_query_arg(
			'wpt_day',
			$days[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);

		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active"><a'), $html);

		// Are the filtered events shown?
		$this->assertEquals(1, substr_count($html, '"wp_theatre_event"'), $html);
		
	}
	
	function test_events_are_filtered_by_month_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$months = $this->wp_theatre->events->get_months(array('upcoming' => true));
		$months = array_keys($months);
		
		$url = add_query_arg(
			'wpt_month',
			$months[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);

		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active"><a'));

		// Are the filtered events shown?
		$this->assertContains('"wp_theatre_event"',$html);
	}
	
	function test_events_are_filtered_by_year_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$years = $this->wp_theatre->events->get_years(array('upcoming' => true));
		$years = array_keys($years);
		
		$url = add_query_arg(
			'wpt_year',
			$years[0],
			get_permalink( $this->wp_theatre->listing_page->page() )
		);
		
		$this->go_to($url);

		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active"><a'));

		// Are the filtered events shown?
		$this->assertContains('"wp_theatre_event"',$html);
		
	}
	
	function test_productions_are_filtered_by_category_on_listing_page() {
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_category',
			'film',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active"><a'), $html);

		// Are the filtered events shown?
		$this->assertEquals(1, substr_count($html, '"wp_theatre_prod"'), $html);
		
	}
	
	function test_productions_are_filtered_by_tag_on_listing_page() {
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_tag',
			'upcoming',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'tag-upcoming wpt_listing_filter_active"><a'), $html);

		// Are the filtered events shown?
		$this->assertEquals(1, substr_count($html, '"wp_theatre_prod"'), $html);
		
	}
	
	function test_events_are_filtered_by_category_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_category',
			'film',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'wpt_listing_filter_active"><a'), $html);

		// Are the filtered events shown?
		$this->assertEquals(2, substr_count($html, '"wp_theatre_event"'), $html);
		
	}
	
	function test_events_are_filtered_by_tag_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = add_query_arg(
			'wpt_tag',
			'upcoming',
			get_permalink( $this->wp_theatre->listing_page->page() )
		);

		$this->go_to($url);
		
		$html= get_echo( 'the_content' );

		// Is the active filter shown?
		$this->assertEquals(1, substr_count($html, 'tag-upcoming wpt_listing_filter_active"><a'), $html);

		// Are the filtered events shown?
		$this->assertEquals(1, substr_count($html, '"wp_theatre_event"'), $html);
		
	}

	
	function test_events_are_filtered_by_category_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url(array('wpt_category'=>'film'));
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_category=$matches[1]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_events_are_filtered_by_tag_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url(array('wpt_tag'=>'upcoming'));
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_tag=$matches[1]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_events_are_filtered_by_month_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url(array('wpt_month'=>'2018-12'));
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_month=$matches[1]-$matches[2]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_events_are_filtered_by_day_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url(array('wpt_day'=>'2018-12-02'));
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_day=$matches[1]-$matches[2]-$matches[3]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_events_are_filtered_by_day_and_category_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url( array( 
			'wpt_day'=>'2018-12-02',
			'wpt_category' => 'film',
		) );
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_category=$matches[1]&wpt_day=$matches[2]-$matches[3]-$matches[4]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_events_are_filtered_by_month_and_category_on_listing_page_with_pretty_permalinks() {
		
		global $wp_theatre;

		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url( array( 
			'wpt_month'=>'2018-12',
			'wpt_category' => 'film',
		) );
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename='.$listing_page->post_name.'&wpt_category=$matches[1]&wpt_month=$matches[2]-$matches[3]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );
		
	}

	function test_productions_are_filtered_by_category_on_listing_page_with_parent_page() {
		
		global $wp_theatre;
		global $wp_rewrite;
		
		$args = array(
			'post_type' => 'page',
			'post_title' => 'Parent page',
			'post_status' => 'published',	
		);
		$parent_id = $this->factory->post->create( $args );
		
		$listing_page_id = $this->options[ 'listing_page_post_id' ];
		
		$args = array(
			'ID' => $this->options[ 'listing_page_post_id' ],
			'post_parent' => $parent_id,
		);
		wp_update_post( $args );
		
		$wp_theatre->listing_page->init();
		$wp_rewrite->flush_rules();
		
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		update_option('wpt_listing_page', $this->options);

		$url = $wp_theatre->listing_page->url(array('wpt_category'=>'film'));
		$listing_page = get_post( $this->options['listing_page_post_id'] );
		$expected = 'index.php?pagename=parent-page/'.$listing_page->post_name.'&wpt_category=$matches[1]';
		$actual = $this->get_matching_rewrite_rule( $url );
		$this->assertEquals( $expected, $actual );

	}
	

	function test_events_on_production_page() {
		$this->options['listing_page_position_on_production_page'] = 'below';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->production_with_upcoming_events)
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(2, substr_count($html, '"wp_theatre_event"'), $html);
	}
	
	function test_events_with_template_on_production_page() {
		$this->options['listing_page_position_on_production_page'] = 'above';
		$this->options['listing_page_template_on_production_page'] = '{{title}}template!';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($this->production_with_upcoming_events)
		);

		$html = get_echo( 'the_content' );

        $this->assertContains('template!', $html, $html);			
	}
	
	function test_events_on_production_page_with_custom_heading() {
		
		function custom_heading() {
			return '<h3>custom heading</h3>';
		}
		
		$this->options['listing_page_position_on_production_page'] = 'below';
		update_option('wpt_listing_page', $this->options);

		add_filter('wpt_production_page_events_header', 'custom_heading');

		$this->go_to(
			get_permalink($this->production_with_upcoming_events)
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '<h3>custom heading</h3>'), $html);
	}
	
	function test_events_on_production_page_with_custom_content() {
		
		function custom_content() {
			return '<p>custom content</p>';
		}
		
		$this->options['listing_page_position_on_production_page'] = 'before';
		update_option('wpt_listing_page', $this->options);

		add_filter('wpt_production_page_content', 'custom_content');

		$this->go_to(
			get_permalink($this->production_with_upcoming_events)
		);

		$html= get_echo( 'the_content' );
		$this->assertEquals(1, substr_count($html, '<p>custom content</p>'), $html);
	}
	
	
	/**
	 * Tests if the 'events' header is not showing on a the page
	 * of a production without events.
	 */
	function test_events_on_production_page_for_production_without_events() {
		
		$production_args = array(
			'post_type'=>WPT_Production::post_type_name,
			'post_content' => 'hallo',
		);
		
		// create production with upcoming event
		$production_without_events = $this->factory->post->create($production_args);

		$this->options['listing_page_position_on_production_page'] = 'below';
		update_option('wpt_listing_page', $this->options);

		$this->go_to(
			get_permalink($production_without_events)
		);

		$html= get_echo( 'the_content' );

		$this->assertEquals(0, substr_count($html, '<h3>Events</h3>'), $html);
		
	}
	
	function test_events_on_production_page_with_translated_header() {
		global $wp_theatre;
		update_option('wpt_listing_page', array('listing_page_position_on_production_page' => 'below'));
		
		update_option('wpt_language', array('language_events' => 'Shows'));
		$wp_theatre->wpt_language_options = get_option('wpt_language');
		

		$this->go_to(
			get_permalink($this->production_with_upcoming_event)
		);
		
		$html = get_echo( 'the_content' );
		
		$this->assertEquals(1, substr_count($html, '<h3>Shows</h3>'), $html);
		
		
	}
	
	function test_filter_pagination_urls_on_listing_page() {
		$this->options['listing_page_type'] = WPT_Event::post_type_name;
		$this->options['listing_page_nav'] = 'paginated';
		$this->options['listing_page_groupby'] = 'month';
		update_option('wpt_listing_page', $this->options);

		$months = $this->wp_theatre->events->get_months(array('upcoming' => true));
		$months = array_keys($months);
		
		$listing_page_url = get_permalink( $this->wp_theatre->listing_page->page() );
		$month_url = $listing_page_url.str_replace('-','/',$months[0]);
				
		$this->go_to($listing_page_url);
		
		$html = get_echo( 'the_content' );

		$this->assertContains($month_url, $html);
		
	}
	
	/**
	 * Tests the Theater Categories widget output.
	 * 
	 * @since	0.15.28
	 */
	function test_widget_categories() {
		$widget = new WPT_Categories_Widget();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section>',
			'after_widget'  => "</section>\n",			
		);
		$instance = array(
			'title' => 'Theater Categories',	
		);

		ob_start();
		$widget->widget( $args, $instance );

		$actual = ob_get_clean();
		$expected = '<ul class="wpt_categories"><li class="film">';
		
		$this->assertContains( $expected, $actual );
		
	}
	
	/* 
	 * Test backwards compatibility
	 */
}
