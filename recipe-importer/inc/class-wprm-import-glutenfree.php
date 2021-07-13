<?php
/**
 * Gluten Free on a Shoestring importer.
 *
 * @since      1.0.0
 * @package    GlutenFreeRecipeImporter
 */

// Make sure the class name matches the file name.
class WPRM_Import_GlutenFree extends WPRM_Import {

	/**
	 * Get the UID of this import source.
	 *
	 * @since    1.20.0
	 */
	public function get_uid() {
		// This should return a uid (no spaces) representing the import source.
		// For example "wp-ultimate-recipe", "easyrecipe", ...

		return 'glutenfree';
	}

	/**
	 * Wether or not this importer requires a manual search for recipes.
	 *
	 * @since    1.20.0
	 */
	public function requires_search() {
		// Set to true when you need to search through the post content (or somewhere else) to actually find recipes.
		// When set to true the "search_recipes" function is required.
		// Usually false is fine as you can find recipes as a custom post type or in a custom table.

		return false;
	}

	/**
	 * Get the name of this import source.
	 *
	 * @since    1.20.0
	 */
	public function get_name() {
		// Display name for this importer.

		return 'Gluten Free on a Shoestring';
	}

	/**
	 * Get HTML for the import settings.
	 *
	 * @since    1.20.0
	 */
	public function get_settings_html() {
		// Any HTML can be added here if input is required for doing the import.
		// Take a look at the WP Ultimate Recipe importer for an example.
		// Most importers will just need ''.

		return '';
	}

	/**
	 * Get the total number of recipes to import.
	 *
	 * @since    1.20.0
	 */
	public function get_recipe_count() {
		// Return a count for the number of recipes left to import.
		// Don't include recipes that have already been imported.

		$loop = new WP_Query( array(
			'fields'         => 'ids',
			'post_type'      => 'post',
			'posts_per_page' => 999,
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

		return $loop->found_posts;
	}

	/**
	 * Search for recipes to import.
	 *
	 * @since    1.20.0
	 * @param	 int $page Page of recipes to import.
	 */
	public function search_recipes( $page = 0 ) {
		// Only needed if "search_required" returns true.
		// Function will be called with increased $page number until finished is set to true.
		// Will need a custom way of storing the recipes.
		// Take a look at the Easy Recipe importer for an example.

		return array(
			'finished' => true,
			'recipes'  => 0,
		);
	}

	/**
	 * Get a list of recipes that are available to import.
	 *
	 * @since    1.20.0
	 * @param	 int $page Page of recipes to get.
	 */
	public function get_recipes( $page = 0 ) {
		// Return an array of recipes to be imported with name and edit URL.
		// If not the same number of recipes as in "get_recipe_count" are returned pagination will be used.

		$loop = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 20,
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
			$recipes[ $post->ID ] = array(
				'name' => $post->post_title,
				'url'  => get_edit_post_link( $post->ID )
			);
		}
		return $recipes;

	}

	/**
	 * Get recipe with the specified ID in the import format.
	 *
	 * @since    1.20.0
	 * @param	 mixed $id ID of the recipe we want to import.
	 * @param	 array $post_data POST data passed along when submitting the form.
	 */
	public function get_recipe( $id, $post_data ) {
		// Get the recipe data in WPRM format for a specific ID, corresponding to the ID in the "get_recipes" array.
		// $post_data will contain any input fields set in the "get_settings_html" function.
		// Include any fields to backup in "import_backup".
		$recipe = array(
			'import_id'     => 0, // Important! If set to 0 will create the WPRM recipe as a new post. If set to an ID it will update to post with that ID to become a WPRM post type.
			'import_backup' => array(
				'example_recipe_id' => $id,
			),
		);

		$image_id = attachment_url_to_postid( get_field( 'image_upload', $id ) );

		// Image ID (if post meta is empty).
		if ( empty( $image_id ) ) {
			$image_id = get_post_thumbnail_id( $id );
		}

		// Get the yield.
		$yield = get_field( 'yield', $id );
		$yield = explode( ' ', $yield, 2 );

		// Get and set all the WPRM recipe fields.
		$recipe['name']          = get_the_title( $id );
		$recipe['summary']       = get_field( 'summary', $id );
		$recipe['author_name']   = get_the_author( $id );
		$recipe['notes']         = get_field( 'recipe_notes', $id );
		$recipe['image_id']      = $image_id;
		$recipe['servings']      = $yield[0];
		$recipe['servings_unit'] = $yield[1];
		$recipe['prep_time']     = preg_replace('/[^0-9]/', '', get_field( 'prep_time', $id ) );
		$recipe['cook_time']     = preg_replace('/[^0-9]/', '', get_field( 'cook_time', $id ) );
		$recipe['total_time']    = $recipe['prep_time'] + $recipe['cook_time'];

		// Set recipe options.
		$recipe['author_display']        = 'default'; // default, disabled, post_author, custom.
		$recipe['ingredient_links_type'] = 'global'; // global, custom.

		// Ingredients have to follow this array structure consisting of groups first.
		$recipe['ingredients'] = array();

		// ACF field - ingredient text.
		$ingredient_text = get_field( 'ingredient_text', $id );
		// Strip out paragraph tags.
		$ingredient_text = str_replace( '</p>', '', $ingredient_text );
		// Create array of values.
		$ingredients = explode( '<p>', $ingredient_text );

		$ingredient_sections['ingredients'] = $ingredients;

		// Ingredients have to follow this array structure consisting of groups first.
		foreach( $ingredient_sections as $ingredient_section ) {
			$name    = ! empty( $ingredient_section['title'] ) ? esc_html( $ingredient_section['title'] ) : '';
			$section = array(
				'name'        => $name,
				'ingredients' => array()
			);
			foreach( $ingredient_section['ingredients'] as $ingredient ) {
				$section['ingredients'][] = array( 'raw' => $ingredient['ingredient'] );
			}
			$recipe['ingredients'][] = $section;
		}

		// ACF field - steps (instructions).
		$steps = get_field( 'step', $id );

		// Create instructions array.
		$instructions = array();

		// Loop through instructions ACF field.
		foreach( $steps as $step ) {
			// Add step to instructions array.
			$instructions[] = array(
				'text' => $step['add_step'],
			);
		}

		// Instructions have to follow this array structure consisting of groups first.
		$recipe['instructions'] = array(
			array(
				'name'         => '', // Group names can be empty.
				'instructions' => $instructions
			),
		);

		return $recipe;
	}

	/**
	 * Replace the original recipe with the newly imported WPRM one.
	 *
	 * @since    1.20.0
	 * @param	 mixed $id ID of the recipe we want replace.
	 * @param	 mixed $wprm_id ID of the WPRM recipe to replace with.
	 * @param	 array $post_data POST data passed along when submitting the form.
	 */
	public function replace_recipe( $id, $wprm_id, $post_data ) {
		// The recipe with ID $id has been imported and we now have a WPRM recipe with ID $wprm_id (can be the same ID).
		// $post_data will contain any input fields set in the "get_settings_html" function.
		// Use this function to do anything after the import, like replacing shortcodes.

		// Mark as migrated so it isn't re-imported
		update_post_meta( $id, '_recipe_imported', 1 );

		// Set parent post that contains recipe
		update_post_meta( $wprm_id, 'wprm_parent_post_id', $id );

		// Add the WPRM shortcode.
		$post = get_post( $id );
		if ( false !== strpos( $post->post_content, '[insert-recipe-here]' ) ) {
			$content = str_replace( '[insert-recipe-here]', '[wprm-recipe id="' . $wprm_id . '"]', $post->post_content );
		} else {
			$content = $post->post_content .= ' [wprm-recipe id="' . $wprm_id . '"]';
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );

	}
}
