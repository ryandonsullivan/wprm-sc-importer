<?php
/**
 * Plugin Name: Recipe Importer
 * Description: Imports recipes to WP Recipe Maker for Gluten Free on a Shoestring
 * Version:     1.0.0
 * Author:      SiteCare
 * Author URI:  https://www.sitecare.com/
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2, as published by the
 * Free Software Foundation.  You may NOT assume that you can use any other
 * version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.
 *
 * @package    GlutenFreeRecipeImporter
 * @since      1.0.0
 * @copyright  Copyright (c) 2021, SiteCare.com
 * @license    GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin directory.
define( 'EA_DIR' , plugin_dir_path( __FILE__ ) );

// Include custom WPRM importer.
function be_custom_wprm_importer( $directories ) {
	$directories[] = EA_DIR . '/inc/';
	return $directories;
}
add_filter( 'wprm_importer_directories', 'be_custom_wprm_importer' );


// Add Shortcode
function acme_yield_updates() {

	
	$loop = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 9999,
		'paged'          => $page,
		'meta_query'     => array(
			array(
				'key' => 'cook_time',
			),
			array(
				'key'     => '_recipe_imported',
				'value'   => 1,
				'compare' => 'NOT EXISTS',
			)
		)
	) );

	$recipes = array();

	foreach( $loop->posts as $post ) {
		$string = get_field( 'yield', $post->ID );
		if ( ! is_numeric( $string[0] ) ) {
			$recipes[ $post->ID ] = array(
				'name' => $post->post_title,
				'url'  => get_edit_post_link( $post->ID ),
				'yield' => $string
			);
		}
	}

	echo '<ul>';
	foreach ( $recipes as $recipe ) {
		echo '<li><a href="' . $recipe['url'] . '">' . $recipe['name'] . '</a></li>';
	}
	echo '</ul>';

}
add_shortcode( 'yield_updates', 'acme_yield_updates' );