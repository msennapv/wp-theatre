<?php
/**
 * The Theater_Transient object.
 *
 * @since	0.15.24
 */
class Theater_Transient {

	/**
	 * The key used to store the transient.
	 *
	 * @since	0.15.24
	 *
	 * @var 	string
	 * @access 	private
	 */
	private $key;

	/**
	 * Constructor method for Theater_Transient objects.
	 *
	 * Set the $key property.
	 *
	 * @since	0.15.24
	 *
	 * @uses 	Theater_Transient::key to set the key based on $name and $args.
	 * @uses	Theater_Transient::calculate_key() to get the key based on $name and $args.
	 *
	 * @param 	string 	$name 	The name of the transient.
	 * @param 	array 	$args	The arguments of the transient.
	 * @return 	void
	 */
	function __construct( $name = '', $args = array() ) {
		$this->key = $this->calculate_key( $name, $args );
	}

	/**
	 * Calculates the key for this transient.
	 *
	 * @since	0.15.24
	 *
	 * @uses	Theater_Transient::get_prefix() to get the key prefix.
	 *
	 * @param 	string 	$name 	The name of the transient.
	 * @param 	array 	$args	The arguments of the transient.
	 * @return 	string			The key for this transient.
	 */
	function calculate_key( $name, $args ) {

		$prefix = $this->get_prefix();

		$key = $prefix . $name . md5( serialize( $args ) );

		/**
		 * Filter the key of this transient.
		 *
		 * @since	0.15.24
		 *
		 * @param	string				$key		The key of this transient.
		 * @param	Theater_Transient	$transient	The transient object.
		 */
		$key = apply_filters( 'theater/transient/key', $key, $this );

		return $key;

	}

	/**
	 * Gets the transient value.
	 *
	 * @since	0.15.24
	 * @since	0.19	Validates cached metadata and enforces runtime expiration checks.
	 *
	 * @uses	Theater_Transient::is_active() to check if the use of transients is active.
	 *
	 * @return 	mixed	The transient value.
	 *					If the transient does not exist, does not have a value, has expired, or if the use of transients in not active
	 *					then get_transient will return <false>.
	 */
	public function get() {

		if ( ! $this->is_active() ) {
			return false;
		}
		$value = get_transient( $this->key );

		// Cache was missing; clean up the book-keeping so we do not keep stale keys around.
		if ( false === $value ) {
			Theater_Transients::delete_transient_metadata( $this->key );
			$this->unregister();
			return false;
		}

		// Pull metadata that tracks when the transient was created and for how long it is valid.
		$metadata     = Theater_Transients::get_transient_metadata_for( $this->key );
		$generated_at = 0;
		$expiration   = $this->get_expiration();

		if ( is_array( $metadata ) ) {
			if ( isset( $metadata['generated_at'] ) ) {
				$generated_at = (int) $metadata['generated_at'];
			}

			if ( isset( $metadata['expiration'] ) ) {
				$expiration = (int) $metadata['expiration'];
			}
		}

		if ( $expiration > 0 ) {
			// No recorded generation moment: treat as corrupted and regenerate.
			if ( $generated_at <= 0 ) {
				$this->reset();
				return false;
			}

			// Transient has outlived its TTL: remove and force regeneration.
			if ( ( $generated_at + $expiration ) <= time() ) {
				$this->reset();
				return false;
			}
		}

		return $value;

	}

	/**
	 * Gets the expiration of this transient.
	 *
	 * @since	0.15.24
	 * @since	0.19	Normalises invalid filter results back to the default expiration.
	 * @return	int		The expiration of this transient.
	 */
	function get_expiration() {

		$default_expiration = 10 * MINUTE_IN_SECONDS;

		/**
		 * Filter the expiration of this transient.
		 *
		 * @since	0.15.24
		 *
		 * @param	int					$expiration	The expiration of this transient.
		 * @param	Theater_Transient	$transient	The transient object.
		 */
		$expiration = apply_filters( 'theater/transient/expiration', $default_expiration, $this );

		if ( ! is_numeric( $expiration ) ) {
			$expiration = $default_expiration;
		}

		return (int) $expiration;

	}

	/**
	 * Gets the prefix for this transient.
	 *
	 * @since	0.15.24
	 *
	 * @return 	string	The prefix for this transient.
	 */
	function get_prefix() {

		$prefix = 'wpt';

		/**
		 * Filter the prefix of this transient.
		 *
		 * @since	0.15.24
		 *
		 * @param	string				$prefix		The prefix of this transient.
		 * @param	Theater_Transient	$transient	The transient object.
		 */
		$prefix = apply_filters( 'theater/transient/prefix', $prefix, $this );

		return $prefix;

	}

	/**
	 * Checks if the use of transients is active.
	 *
	 * @since	0.15.24
	 *
	 * @return	bool
	 */
	function is_active() {

		$active = true;

		if ( is_user_logged_in() ) {
			$active = false;
		}

		/**
		 * Filter whether the use of transients if active.
		 *
		 * @since	0.15.24
		 *
		 * @param	bool				$active		Whether transients are currenlty active.
		 * @param	Theater_Transient	$transient	The transient object.
		 */
		$active = apply_filters( 'theater/transient/active', $active, $this );

		return $active;
	}

	/**
	 * Loads the transient by its key.
	 *
	 * @since	0.15.24
	 *
	 * @uses 	Theater_Transient::key to set the key property.
	 *
	 * @param 	string	$key	The transient key.
	 * @return 	void
	 */
	function load_by_key( $key ) {
		$this->key = $key;
	}

	/**
	 * Registers the transient in the list of Theater transients that are in use.
	 *
	 * @since	0.15.24
	 * @since	0.19	Ensures the registry option is saved with autoload disabled.
	 *
	 * @uses	Theater_Transients::get_transient_keys() to get all Theater transients that are in use.
	 * @uses	Theater_Transient::key to get the key of the current transient.
	 * @return 	void
	 */
	function register() {
		$transient_keys = Theater_Transients::get_transient_keys();
		$transient_keys[] = $this->key;
		$transient_keys = array_values( array_unique( $transient_keys ) );
		update_option( THEATER_TRANSIENTS_OPTION, $transient_keys, false );
	}

	/**
	 * Resets the transient.
	 *
	 * @since	0.15.24
	 * @since	0.19	Also clears the metadata store.
	 *
	 * @uses 	Theater_Transient::unregister() to remove the transient from the list of Theater transients that
	 *			are in use.
	 *
	 * @return	bool	<true> if successful, <false> otherwise.
	 */
	public function reset() {

		$result = delete_transient( $this->key );

		Theater_Transients::delete_transient_metadata( $this->key );
		$this->unregister();

		return $result;
	}

	/**
	 * Sets the transient value.
	 *
	 * @since	0.15.24
	 * @since	0.19	Adds runtime guard for zero expiration and stores metadata.
	 *
	 * @uses	Theater_Transient::is_active() to check if the use of transients is active.
	 *
	 * @param 	mixed 	$value	The transient value.
	 * @return 	bool			<false> if value was not set and <true> if value was set.
	 */
	public function set( $value ) {

		if ( ! $this->is_active() ) {
			return false;
		}

		$expiration = $this->get_expiration();

		// Guard against filtered values that effectively disable caching.
		if ( $expiration < 1 ) {
			$this->reset();
			return false;
		}

		$result = set_transient( $this->key, $value, $expiration );

		if ( $result ) {
			$this->register();
			// Persist metadata so reads can detect expiry without relying on the options table TTL.
			Theater_Transients::update_transient_metadata(
				$this->key,
				array(
					'generated_at' => time(),
					'expiration'   => $expiration,
				)
			);
		}

		return $result;
	}

	/**
	 * Unregisters the transient from the list of Theater transients that are in use.
	 *
	 * @since	0.15.24
	 * @since	0.19	Delegates storage updates to Theater_Transients::remove_transient_key().
	 *
	 * @uses	Theater_Transients::get_transient_keys() to get all Theater transients that are in use.
	 * @uses	Theater_Transient::key to get the key of the current transient.
	 * @return 	void
	 */
	private function unregister() {
		Theater_Transients::remove_transient_key( $this->key );
	}


}
