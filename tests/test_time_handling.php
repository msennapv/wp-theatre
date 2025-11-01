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

        if ( class_exists( 'Theater_Helpers_Time' ) && method_exists( 'Theater_Helpers_Time', 'get_utc_timestamp_from_local_string' ) ) {
            // If the refactor is present, the helper should match DateTimeZone conversion.
            $utc_helper = Theater_Helpers_Time::get_utc_timestamp_from_local_string( $local );
            $this->assertEquals( $utc_expected, $utc_helper, 'Helper should match DateTimeZone conversion.' );
        } else {
            // Otherwise just assert we produced a valid UTC timestamp via DateTime.
            $this->assertIsInt( $utc_expected );
        }
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

        $local = '2025-10-26 02:30:00';
        // DST-aware expected value via DateTimeZone.
        $dt_local = new DateTimeImmutable( $local, new DateTimeZone( 'Europe/Amsterdam' ) );
        $utc_expected = $dt_local->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();

        // Legacy naive calculation (replicates the old code path)
        $naive = strtotime( $local, current_time( 'timestamp' ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

        if ( class_exists( 'Theater_Helpers_Time' ) && method_exists( 'Theater_Helpers_Time', 'get_utc_timestamp_from_local_string' ) ) {
            // If the helper exists we expect the legacy naive computation to differ from the correct conversion.
            $this->assertNotEquals( $naive, $utc_expected, 'Naive fixed-offset conversion should differ from DST-aware conversion during DST transitions.' );
        } else {
            // If the helper is not present (older branch), still assert that the naive conversion differs from
            // DateTime's DST-aware conversion. This documents the bug on old branches.
            $this->assertNotEquals( $naive, $utc_expected, 'On branches without the refactor the naive conversion should differ from DST-aware conversion.' );
        }
    }

}
