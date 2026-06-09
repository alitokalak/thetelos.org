<?php
/**
 * The Telos — Book Rating System
 *
 * Stores star ratings per post. Anonymous users tracked by hashed IP.
 * Meta keys:
 *   _thetelos_rating_scores — serialised array [ hash => 1-5 ]
 *   _thetelos_rating_avg    — float, cached average
 *   _thetelos_rating_count  — int, cached count
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ──────────────────────────────────────────────────────────
// AJAX endpoint (logged-in and guests)
// ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_thetelos_rate_book',        'thetelos_handle_rate_book' );
add_action( 'wp_ajax_nopriv_thetelos_rate_book', 'thetelos_handle_rate_book' );

function thetelos_handle_rate_book() {
    // Verify nonce
    if ( ! check_ajax_referer( 'thetelos_rating', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $rating  = isset( $_POST['rating'] )  ? (int) $_POST['rating']      : 0;

    if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
        wp_send_json_error( 'Invalid post', 400 );
    }

    // Rating must be 0 (remove) or 1-5
    if ( $rating < 0 || $rating > 5 ) {
        wp_send_json_error( 'Invalid rating', 400 );
    }

    // Build voter identifier: hash of IP (privacy-friendly)
    $ip   = thetelos_get_client_ip();
    $hash = hash( 'sha256', $ip . NONCE_KEY );

    // If logged in, use user id as identifier instead
    if ( is_user_logged_in() ) {
        $hash = 'u_' . get_current_user_id();
    }

    // Load existing scores
    $scores = get_post_meta( $post_id, '_thetelos_rating_scores', true );
    if ( ! is_array( $scores ) ) $scores = [];

    if ( $rating === 0 ) {
        // Remove vote
        unset( $scores[ $hash ] );
    } else {
        $scores[ $hash ] = $rating;
    }

    // Recalculate
    $count = count( $scores );
    $avg   = $count > 0 ? array_sum( $scores ) / $count : 0;

    // Persist
    update_post_meta( $post_id, '_thetelos_rating_scores', $scores );
    update_post_meta( $post_id, '_thetelos_rating_avg',    round( $avg, 2 ) );
    update_post_meta( $post_id, '_thetelos_rating_count',  $count );

    wp_send_json_success( [
        'avg'   => round( $avg, 1 ),
        'count' => $count,
    ] );
}

// ──────────────────────────────────────────────────────────
// Helper: get current rating for a post
// ──────────────────────────────────────────────────────────
function thetelos_get_rating( $post_id ) {
    return [
        'avg'   => (float) get_post_meta( $post_id, '_thetelos_rating_avg',   true ),
        'count' => (int)   get_post_meta( $post_id, '_thetelos_rating_count', true ),
    ];
}

// ──────────────────────────────────────────────────────────
// Helper: safe client IP
// ──────────────────────────────────────────────────────────
function thetelos_get_client_ip() {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ( $keys as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip = trim( explode( ',', sanitize_text_field( $_SERVER[ $key ] ) )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
        }
    }
    return '0.0.0.0';
}
