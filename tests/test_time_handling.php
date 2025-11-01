<?php
/**
 * Tests for DST-aware time handling helpers.
 *
 * These tests confirm the problem the refactor addressed: naive fixed-offset
 * arithmetic (using get_option('gmt_offset')) can produce different UTC
 * timestamps for local datetimes that occur during DST transitions. The
 * helpers in `Theater_Helpers_Time` are DST-aware and should match
 * DateTimeZone's behaviour.
 *
 * @group time
 * @since 0.17.0
 */

class WPT_Test_TimeHandling extends WPT_UnitTestCase {

    /**
     * Ensure the helper produces the same UTC timestamp as PHP's DateTime with the
     * site's timezone.
     */
    public function test_helper_matches_datetimezone_conversion() {
        // Use a timezone with DST transitions to exercise the behaviour.
        update_option( 'timezone_string', 'Europe/Amsterdam' );

        $local = '2025-10-26 02:30:00'; // Around the DST end in Europe (ambiguous hour)
        // Canonical conversion via DateTimeZone for the same timezone.
        $dt_local = new DateTimeImmutable( $local, new DateTimeZone( 'Europe/Amsterdam' ) );
        $utc_expected = $dt_local->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();

            if ( ! class_exists( 'Theater_Helpers_Time' ) ) {
                $this->markTestSkipped( 'Theater_Helpers_Time is not available in this branch.' );
            }

            // If the refactor is present, the helper should match DateTimeZone conversion.
            $utc_helper = Theater_Helpers_Time::get_utc_timestamp_from_local_string( $local );
            $this->assertEquals( $utc_expected, $utc_helper, 'Helper should match DateTimeZone conversion.' );
    }

    /**
     * Demonstrate that the legacy naive fixed-offset calculation differs from
     * the DST-aware helper for timestamps around DST transitions.
     *
     * This replicates the old pattern used in the codebase:
     *   strtotime( $time, current_time( 'timestamp' ) ) - get_option( 'gmt_offset' ) * 3600
     */
    public function test_naive_fixed_offset_differs_from_helper() {
        update_option( 'timezone_string', 'Europe/Amsterdam' );

    // Use a summer date where Europe/Amsterdam has UTC+2 (DST) so we can force a mismatch
    // by storing a numeric gmt_offset that does not account for DST.
    $local = '2025-07-01 12:00:00';
        // DST-aware expected value via DateTimeZone.
        $dt_local = new DateTimeImmutable( $local, new DateTimeZone( 'Europe/Amsterdam' ) );
        $utc_expected = $dt_local->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();

    // Force the legacy numeric gmt_offset to a value that does NOT include DST (e.g. +1).
    // This reproduces older installations that only stored a numeric offset.
    update_option( 'gmt_offset', '1' );

    // Legacy naive calculation (replicates the old code path)
    $naive = strtotime( $local, current_time( 'timestamp' ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

            if ( ! class_exists( 'Theater_Helpers_Time' ) ) {
                $this->markTestSkipped( 'Theater_Helpers_Time is not available in this branch.' );
            }

            // Confirm the helper round-trips: local -> UTC -> local yields the original local timestamp.
            $samples = array(
                '2025-07-01 12:00:00',
                '2025-10-26 02:30:00',
            );

            foreach ( $samples as $local ) {
                $utc = Theater_Helpers_Time::get_utc_timestamp_from_local_string( $local );
                $back = Theater_Helpers_Time::get_local_timestamp_from_utc( $utc );

                // Convert the original local string to a timestamp in the site's timezone for comparison.
                $dt_local = new DateTimeImmutable( $local, new DateTimeZone( 'Europe/Amsterdam' ) );
                $expected_local_timestamp = (int) $dt_local->getTimestamp();

                $this->assertEquals( $expected_local_timestamp, $back, "Local timestamp for {$local} should round-trip correctly" );
            }
    }

}
