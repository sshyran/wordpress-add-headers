<?php
/*
Plugin Name: Add Headers
Plugin URI: https://bitbucket.org/gnotaras/wordpress-add-headers
Description: Adds the ETag, Last-Modified, Expires and Cache-Control headers to HTTP responses generated by WordPress to facilitate caching.
Version: 1.0.1
Author: George Notaras
Author URI: http://www.g-loaded.eu/
License: GPLv3
*/

/**
 *  This file is part of the Add-Headers distribution package.
 *
 *  Add-Headers is an extension for the WordPress publishing platform.
 *
 *  Homepage:
 *  - http://wordpress.org/plugins/add-headers/
 *  Documentation:
 *  - http://www.codetrax.org/projects/wp-add-headers/wiki
 *  Development Web Site and Bug Tracker:
 *  - http://www.codetrax.org/projects/wp-add-headers
 *  Main Source Code Repository (Mercurial):
 *  - https://bitbucket.org/gnotaras/wordpress-add-headers
 *  Mirror repository (Git):
 *  - https://github.com/gnotaras/wordpress-add-headers
 *
 *  Licensing Information
 *
 *  Copyright 2013 George Notaras <gnot@g-loaded.eu>, CodeTRAX.org
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


// Store plugin directory
define('ADDH_DIR', dirname(__FILE__));

// Import modules
// require_once( join( DIRECTORY_SEPARATOR, array( ADDH_DIR, 'addh-settings.php' ) ) );


/**
 * Translation Domain
 *
 * Translation files are searched in: wp-content/plugins
 */
//load_plugin_textdomain('add-headers', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');




/**
 * Generates headers
 */
function addh_generate_headers( $post, $mtime, $options ) {
    $headers_arr = array();

    // ETag
    if ( $options['add_etag_header'] === true ) {
        $header_etag_value = md5( $mtime . $post->post_date_gmt ) . '.' . md5( $post->guid . $post->post_name . $post->ID );
        $headers_arr[] = sprintf( 'ETag: "%s"', $header_etag_value );
    }
    
    // Last-Modified
    if ( $options['add_last_modified_header'] === true ) {
        $header_last_modified_value = str_replace( '+0000', 'GMT', gmdate('r', $mtime) );
        $headers_arr[] = 'Last-Modified: ' . $header_last_modified_value;
    }

    // Expires (Calculated from client access time, aka current time)
    if ( $options['add_expires_header'] === true ) {
        $header_expires_value = str_replace( '+0000', 'GMT', gmdate('r', time() + $options['cache_max_age_seconds'] ) );
        $headers_arr[] = 'Expires: ' . $header_expires_value;
    }

    // Cache-Control
    if ( $options['add_cache_control_header'] === true ) {
        $default_cache_control_template = 'public, max-age=%s';
        $cache_control_template = apply_filters( 'addh_cache_control_header_format', $default_cache_control_template );
        $header_cache_control_value = sprintf( $cache_control_template, $options['cache_max_age_seconds'] );
        $headers_arr[] = 'Cache-Control: ' . $header_cache_control_value;
    }


    // Allow filtering of the generated headers
    $headers_arr = apply_filters( 'addh_headers', $headers_arr );

    // Sent headers
    foreach ( $headers_arr as $header_data ) {
        header( $header_data );
    }
}



/**
 * Returns the modified time for post objects (posts, pages, attachments custom
 * post types). Two time sources are used:
 * 1) the post object's modified time.
 * 2) the modified time of the most recent comment that is attached to the post object.
 * The most "recent" timestamp is returned.
 */
function addh_set_headers_for_object( $options ) {

    // Get current queried object.
    $post = get_queried_object();
    // Valid post types: post, page, attachment
    if ( ! is_object($post) || ! isset($post->post_type) || ! in_array( get_post_type($post), array('post', 'page', 'attachment') ) ) {
        return;
    }

    // Retrieve stored time of post object
    $post_mtime = $post->post_modified_gmt;
    $post_mtime_unix = strtotime( $post_mtime );

    // Initially set the $mtime to the post mtime timestamp
    $mtime = $post_mtime_unix;

    // If there are comments attached to this post object, find the mtime of
    // the most recent comment.
    if ( intval($post->comment_count) > 0 ) {

        // Retrieve the mtime of the most recent comment
        $comments = get_comments( array(
            'status' => 'approve',
            'orderby' => 'comment_date_gmt',
            'number' => '1',
            'post_id' => $post->ID
        ) );
        if ( ! empty($comments) ) {
            $comment = $comments[0];
            $comment_mtime = $comment->comment_date_gmt;
            $comment_mtime_unix = strtotime( $comment_mtime );
            // Compare the two mtimes and keep the most recent (higher) one.
            if ( $comment_mtime_unix > $post_mtime_unix ) {
                $mtime = $comment_mtime_unix;
            }
        }
    }

    addh_generate_headers( $post, $mtime, $options );
}


/**
 * Sets headers on archives
 */
function addh_set_headers_for_archive( $options ) {

    // On archives, the global post object is the first post of the list.
    // So, we use this to set the headers for the archive.
    // There is no need to check for pagination, since every page of the archive
    // has different posts.
    global $post;
    // Valid post types: post
    if ( ! is_object($post) || ! isset($post->post_type) || ! in_array( get_post_type($post), array('post') ) ) {
        return;
    }

    // Retrieve stored time of post object
    $post_mtime = $post->post_modified_gmt;
    $mtime = strtotime( $post_mtime );

    addh_generate_headers( $post, $mtime, $options );
}


function addh_headers( $buffer ){
    
    // Options
    $default_options = array(
        'add_etag_header' => true,
        'add_last_modified_header' => true,
        'add_expires_header' => true,
        'add_cache_control_header' => true,
        'cache_max_age_seconds' => 86400,
    );
    $options = apply_filters( 'addh_options', $default_options );

    // Post objects and Static front page
    if ( is_singular() ) {
        addh_set_headers_for_object( $options );
    }
    
    // Archives, Default latest posts front page, Static posts page
    elseif ( is_archive() || is_home() ) {
        addh_set_headers_for_archive( $options );
    }

    return $buffer;
}


// See this page for what this workaround is about:
// http://stackoverflow.com/questions/12608881/wordpress-redirect-issue-headers-already-sent
// Possibly related:
// http://wordpress.stackexchange.com/questions/16547/wordpress-plugin-development-headers-already-sent-message
// http://stackoverflow.com/questions/8677901/cannot-modify-header-information-with-mail-and-header-php-with-ob-start
// How WP boots: http://theme.fm/2011/10/wordpress-internals-how-wordpress-boots-up-part-3-2673/
function addh_add_ob_start(){
    ob_start('addh_headers');
}
function addh_flush_ob_end(){
    ob_end_flush();
}
add_action('init', 'addh_add_ob_start');
add_action('wp', 'addh_flush_ob_end');

?>