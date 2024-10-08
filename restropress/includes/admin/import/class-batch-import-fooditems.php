<?php
/**
 * RestroPress import class
 *
 * This class handles importing fooditems with the batch processing API
 *
 * @package     RPRESS
 * @subpackage  Admin/Import
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * RPRESS_Batch_FoodItems_Import Class
 *
 * @since 1.0.0
 */
class RPRESS_Batch_FoodItems_Import extends RPRESS_Batch_Import {
	/**
	 * Set up our import config.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Set up default field map values
		$this->field_mapping = array(
			'post_title'     => '',
			'post_name'      => '',
			'post_status'    => 'draft',
			'post_author'    => '',
			'post_date'      => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'price'          => '',
			'categories'     => '',
			'addons'     	 => '',
			'tags'           => '',
			'tag_mark'		 =>	'',
			'addon_prices'	 =>	'',
			'addon_max'		 =>	'',
			'addon_is_required'		 =>	'',
			'sku'            => '',
			'earnings'       => '',
			'sales'          => '',
			'featured_image' => '',
			'notes'          => '',
			'variable_price_label'          => '',
			'default_addons' => ''
		);
	}
	/**
	 * Process a step
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function process_step() {
		$more = false;
		if ( ! $this->can_import() ) {
			wp_die( esc_html__( 'You do not have permission to import data.', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
		}
		$i      = 1;
		$offset = $this->step > 1 ? ( $this->per_step * ( $this->step - 1 ) ) : 0;
		if( $offset > $this->total ) {
			$this->done = true;
		}
		if( ! $this->done && $this->csv->data ) {
			$more = true;
			foreach( $this->csv->data as $key => $row ) {
				// Skip all rows until we pass our offset
				if( $key + 1 <= $offset ) {
					continue;
				}
				// Done with this batch
				if( $i > $this->per_step ) {
					break;
				}
				// Import RestroPress
				$args = array(
					'post_type'    => 'fooditem',
					'post_title'   => '',
					'post_name'    => '',
					'post_status'  => '',
					'post_author'  => '',
					'post_date'    => '',
					'post_content' => '',
					'post_excerpt' => ''
				);
				foreach ( $args as $key => $field ) {
					if ( ! empty( $this->field_mapping[ $key ] ) && ! empty( $row[ $this->field_mapping[ $key ] ] ) ) {
						$args[ $key ] = $row[ $this->field_mapping[ $key ] ];
					}
				}
				if ( empty( $args['post_author'] ) ) {
	 				$user = wp_get_current_user();
	 				$args['post_author'] = $user->ID;
	 			} else {
	 				// Check all forms of possible user inputs, email, ID, login.
	 				if ( is_email( $args['post_author'] ) ) {
	 					$user = get_user_by( 'email', $args['post_author'] );
	 				} elseif ( is_numeric( $args['post_author'] ) ) {
	 					$user = get_user_by( 'ID', $args['post_author'] );
	 				} else {
	 					$user = get_user_by( 'login', $args['post_author'] );
	 				}
	 				// If we don't find one, resort to the logged in user.
	 				if ( false === $user ) {
	 					$user = wp_get_current_user();
	 				}
	 				$args['post_author'] = $user->ID;
	 			}
				// Format the date properly
				if ( ! empty( $args['post_date'] ) ) {
					$timestamp = strtotime( $args['post_date'], current_time( 'timestamp' ) );
					if( $timestamp == false ) {
						$date = date_i18n( 'Y-m-d H:i:s' );
					} else {
						$date = gmdate( 'Y-m-d H:i:s', $timestamp );
					}
					// If the date provided results in a date string, use it, or just default to today so it imports
					if ( ! empty( $date ) ) {
						$args['post_date'] = $date;
					} else {
						$date = '';
					}
				}
				// Detect any status that could map to `publish`
				if ( ! empty( $args['post_status'] ) ) {
					$published_statuses = array(
						'live',
						'published',
					);
					$current_status = strtolower( $args['post_status'] );
					if ( in_array( $current_status, $published_statuses ) ) {
						$args['post_status'] = 'publish';
					}
				}
				$fooditem_id = wp_insert_post( $args );
				// setup categories
				if( ! empty( $this->field_mapping['categories'] ) && ! empty( $row[ $this->field_mapping['categories'] ] ) ) {
                    $strCategories = $row[ $this->field_mapping['categories'] ] ;
					$categories = $this->str_to_array($strCategories);
                    if (substr($strCategories, 0, 3) === " | ") {
                        $this->set_taxonomy_terms_cp( $fooditem_id, $categories, 'food-category' );
					
                    }else{
                    
                        $this->set_taxonomy_terms( $fooditem_id, $categories, 'food-category', false );
                    }
                    
				}
				// setup addons
				if( ! empty( $this->field_mapping['addons'] ) && ! empty( $row[ $this->field_mapping['addons'] ] ) ) {
                    $strAddons =  $row[ $this->field_mapping['addons'] ] ;
					$addons = $this->str_to_array( $strAddons );
                    if (substr($strAddons, 0, 3) === " | ") {
                    
                    $this->set_taxonomy_terms_cp( $fooditem_id, $addons, 'addon_category' );
                    }else{
                        $this->set_taxonomy_terms( $fooditem_id, $addons, 'addon_category', $addons[0] );
                    }
					
				}
				// setup tags
				if( ! empty( $this->field_mapping['tags'] ) && ! empty( $row[ $this->field_mapping['tags'] ] ) ) {
					$tags = $this->str_to_array( $row[ $this->field_mapping['tags'] ] );
					$this->set_taxonomy_terms( $fooditem_id, $tags, 'fooditem_tag', false );
				}
				// setup tag mark
				if( ! empty( $this->field_mapping['tag_mark'] ) && ! empty( $row[ $this->field_mapping['tag_mark'] ] ) ) {
					update_post_meta( $fooditem_id, 'rpress_food_type', sanitize_text_field( $row[ $this->field_mapping['tag_mark'] ] ) );
				}
				// setup price(s)
				if( ! empty( $this->field_mapping['price'] ) && ! empty( $row[ $this->field_mapping['price'] ] ) ) {
					$price = $row[ $this->field_mapping['price'] ];
					$this->set_price( $fooditem_id, $price );
				}
				// Product Image
				if( ! empty( $this->field_mapping['featured_image'] ) && ! empty( $row[ $this->field_mapping['featured_image'] ] ) ) {
					$image = sanitize_text_field( $row[ $this->field_mapping['featured_image'] ] );
					$this->set_image( $fooditem_id, $image, $args['post_author'] );
				}
				// Sale count
				if( ! empty( $this->field_mapping['sales'] ) && ! empty( $row[ $this->field_mapping['sales'] ] ) ) {
					update_post_meta( $fooditem_id, '_rpress_fooditem_sales', absint( $row[ $this->field_mapping['sales'] ] ) );
				}
				// Earnings
				if( ! empty( $this->field_mapping['earnings'] ) && ! empty( $row[ $this->field_mapping['earnings'] ] ) ) {
					update_post_meta( $fooditem_id, '_rpress_fooditem_earnings', rpress_sanitize_amount( $row[ $this->field_mapping['earnings'] ] ) );
				}
				// Notes
				if( ! empty( $this->field_mapping['notes'] ) && ! empty( $row[ $this->field_mapping['notes'] ] ) ) {
					update_post_meta( $fooditem_id, 'rpress_product_notes', sanitize_text_field( $row[ $this->field_mapping['notes'] ] ) );
				}
				// Variation Price Label
				if( ! empty( $this->field_mapping['variable_price_label'] ) && ! empty( $row[ $this->field_mapping['variable_price_label'] ] ) ) {
					update_post_meta( $fooditem_id, 'rpress_variable_price_label', sanitize_text_field( $row[ $this->field_mapping['variable_price_label'] ] ) );
				}
				// SKU
				if( ! empty( $this->field_mapping[ 'sku' ] ) && ! empty( $row[ $this->field_mapping[ 'sku' ] ] ) ) {
					update_post_meta( $fooditem_id, 'rpress_sku', sanitize_text_field( $row[ $this->field_mapping['sku'] ] ) );
				}
                if( ! empty( $this->field_mapping['addon_prices'] ) && ! empty( $row[ $this->field_mapping['addon_prices'] ] ) ) {
                    $price = $row[ $this->field_mapping['addon_prices'] ];
                    $this->set_addon_prices( $fooditem_id, $price );
				}
                if( ! empty( $this->field_mapping['addon_max'] ) && ! empty( $row[ $this->field_mapping['addon_max'] ] ) ) {
                    $price = $row[ $this->field_mapping['addon_max'] ];
                    $this->set_addon_max( $fooditem_id, $price );
				}
                if( ! empty( $this->field_mapping['addon_is_required'] ) && ! empty( $row[ $this->field_mapping['addon_is_required'] ] ) ) {
                    $price = $row[ $this->field_mapping['addon_is_required'] ];
                    $this->set_addon_required( $fooditem_id, $price );
				}
                if( ! empty( $this->field_mapping['default_addons'] ) && ! empty( $row[ $this->field_mapping['default_addons'] ] ) ) {
                    $price = $row[ $this->field_mapping['default_addons'] ];
                    $this->set_addon_default( $fooditem_id, $price );
				}
				// Custom fields
				// Code goes here
				$i++;
			}
		}
		return $more;
	}
	/**
	 * Return the calculated completion percentage
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_percentage_complete() {
		if( $this->total > 0 ) {
			$percentage = ( $this->step * $this->per_step / $this->total ) * 100;
		}
		if( $percentage > 100 ) {
			$percentage = 100;
		}
		return $percentage;
	}
	/**
	 * Set up and store the addon max option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_max( $fooditem_id = 0, $price = '' ) {
		$prices = (array) explode( ' | ', $price );
		$food_addons     = get_post_meta( $fooditem_id, '_addon_items', true );
      
		$data_addons = array();
         $i = 0;
		foreach ( $food_addons as $addon_id => $food_addon ) {
            // Check if the element at index $i exists and is not equal to 'Not Define'
if (isset($prices[$i]) && $prices[$i] !== 'Not Define') {
    $food_addon['max_addons'] = $prices[$i];
}
			$food_addons[ $addon_id ] = $food_addon;
            $i++;
		}
			
        update_post_meta( $fooditem_id, '_addon_items',  $food_addons);
		
	}
	/**
	 * Set up and store the addon required option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_required( $fooditem_id = 0, $price = '' ) {
		$prices = (array) explode( ' | ', $price );
		$food_addons     = get_post_meta( $fooditem_id, '_addon_items', true );
      
		$data_addons = array();
         $i = 0;
		foreach ( $food_addons as $addon_id => $food_addon ) {
            if(isset($prices[$i]) && $prices[$i] == 'yes'){
            $food_addon ['is_required']    = $prices[$i];
            }
			$food_addons[ $addon_id ] = $food_addon;
            $i++;
		}
			
        update_post_meta( $fooditem_id, '_addon_items',  $food_addons);
		
	}
 /**
	 * Set up and store the addon default option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_default( $fooditem_id = 0, $price = '' ) {
		$default_per_category = (array) explode( ' | ', trim($price) );
		$food_addons     = get_post_meta( $fooditem_id, '_addon_items', true );
      
         $i = 0;
		foreach ( $food_addons as $addon_id => $food_addon ) {
            if(!isset($food_addon ['default'])){
                $food_addon ['default'] = array();
            }
            if(isset($default_per_category[$i])){
                $dafault_per_addon = (array) explode( ' : ', $default_per_category[$i]);
            
                if(!empty($dafault_per_addon)){
            $food_addon ['default']  =    array_merge($food_addon ['default'], $dafault_per_addon);
                }
            }
			$food_addons[ $addon_id ] = $food_addon;
            $i++;
		}
        
			
        update_post_meta( $fooditem_id, '_addon_items',  $food_addons);
		
	}
	/**
	 * Set up and store the price for the fooditem
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_prices( $fooditem_id = 0, $price = '' ) {
		$prices = (array) explode( ' | ', $price );
		$variable_prices = get_post_meta( $fooditem_id, 'rpress_variable_prices' );
        if( is_array($variable_prices) && count($variable_prices) ==1){
        $variable_prices=$variable_prices[0];
        }
		$food_addons     = get_post_meta( $fooditem_id, '_addon_items', true );
  
		$data_addons = array();
		
		foreach ( $food_addons as $addon_id => $food_addon ) {
            $price = array();
			if ( isset( $food_addon['items'] ) && is_array( $food_addon['items'] ) ) {
				foreach ( $food_addon['items'] as  $key => $addonId ) {
					if ( ! empty( $variable_prices ) ) {
						$var_prices   = (array) explode( ' : ', $prices[ $key ] );
						$priceVarData = array();
                        $i = 0;
						foreach ( $variable_prices as $foodVarKey => $food_variable ) {
						
                            $priceVarData[  str_replace(' ', '', $food_variable['name'])] = '' . $var_prices[ $i];
						$i++;
                        }
						$price[ $addonId ] = $priceVarData;
					} else {
						$price[ $addonId ] = $prices[ $key ];
					}
				}
			}
            $food_addon ['prices']    = $price;
			$food_addons[ $addon_id ] = $food_addon;
		}
			
        update_post_meta( $fooditem_id, '_addon_items',  $food_addons);
		
	}
	/**
	 * Set up and store the price for the fooditem
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_price( $fooditem_id = 0, $price = '' ) {
		if( is_numeric( $price ) ) {
			update_post_meta( $fooditem_id, 'rpress_price', rpress_sanitize_amount( $price ) );
		} else {
			$prices = $this->str_to_array( $price );
			if( ! empty( $prices ) ) {
				$variable_prices = array();
				$price_id        = 1;
				foreach( $prices as $price ) {
					// See if this matches the RPRESS RestroPress export for variable prices
					if( false !== strpos( $price, ':' ) ) {
						$price = array_map( 'trim', explode( ':', $price ) );
						$variable_prices[ $price_id ] = array( 'name' => $price[ 0 ], 'amount' => $price[ 1 ] );
						$price_id++;
					}
				}
				update_post_meta( $fooditem_id, '_variable_pricing', 1 );
				update_post_meta( $fooditem_id, 'rpress_variable_prices', $variable_prices );
			}
		}
	}
	/**
	 * Set up and store the file fooditems
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_files( $fooditem_id = 0, $files = array() ) {
		if( ! empty( $files ) ) {
			$fooditem_files = array();
			$file_id        = 1;
			foreach( $files as $file ) {
				$condition = '';
				if ( false !== strpos( $file, ';' ) ) {
					$split_on  = strpos( $file, ';' );
					$file_url  = substr( $file, 0, $split_on );
					$condition = substr( $file, $split_on + 1 );
				} else {
					$file_url = $file;
				}
				$fooditem_file_args = array(
					'file' => $file_url,
					'name' => basename( $file_url ),
				);
				if ( ! empty( $condition ) ) {
					$fooditem_file_args['condition'] = $condition;
				}
				$fooditem_files[ $file_id ] = $fooditem_file_args;
				$file_id++;
			}
			update_post_meta( $fooditem_id, 'rpress_fooditem_files', $fooditem_files );
		}
	}
	/**
	 * Set up and store the Featured Image
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_image( $fooditem_id = 0, $image = '', $post_author = 0 ) {
		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( site_url(), $image );
		$ext      = rpress_get_file_extension( $image );
		if( $is_url && $is_local ) {
			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $image );
		} elseif( $is_url ) {
			if( ! function_exists( 'media_sideload_image' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}
			// Image given by external URL
			$url = media_sideload_image( $image, $fooditem_id, '', 'src' );
			if( ! is_wp_error( $url ) ) {
				$attachment_id = attachment_url_to_postid( $url );
			}
		} elseif( false === strpos( $image, '/' ) && rpress_get_file_extension( $image ) ) {
			// Image given by name only
			$upload_dir = wp_upload_dir();
			if( file_exists( trailingslashit( $upload_dir['path'] ) . $image ) ) {
				// Look in current upload directory first
				$file = trailingslashit( $upload_dir['path'] ) . $image;
			} else {
				// Now look through year/month sub folders of upload directory for files with our image's same extension
				$files = glob( $upload_dir['basedir'] . '/*/*/*{' . $ext . '}', GLOB_BRACE );
				foreach( $files as $file ) {
					if( basename( $file ) == $image ) {
						// Found our file
						break;
					}
					// Make sure $file is unset so our empty check below does not return a false positive
					unset( $file );
				}
			}
			if( ! empty( $file ) ) {
				// We found the file, let's see if it already exists in the media library
				$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
				$attachment_id = attachment_url_to_postid( $guid );
				if( empty( $attachment_id ) ) {
					// Doesn't exist in the media library, let's add it
					$filetype = wp_check_filetype( basename( $file ), null );
					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid'           => $guid,
						'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $image ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'post_author'    => $post_author
					);
					// Insert the attachment.
					$attachment_id = wp_insert_attachment( $attachment, $file, $fooditem_id );
					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
					wp_update_attachment_metadata( $attachment_id, $attach_data );
				}
			}
		}
		if( ! empty( $attachment_id ) ) {
			return set_post_thumbnail( $fooditem_id, $attachment_id );
		}
		return false;
	}
	/**
	 * Set up and taxonomy terms with child
	 *
	 * @since 2.9.9
	 * @return void
	 */
	private function set_taxonomy_terms_cp( $fooditem_id = 0, $terms = array(), $taxonomy = 'addon_category' ) {
		if ( ! empty( $terms ) ) {
			if ( 'addon_category' == $taxonomy ) {
				$addons = get_post_meta( $fooditem_id, '_addon_items' );
			} else {
				$addons = array();
			}
			if ( ! is_array( $addons ) || empty( $addons ) ) {
				$addons = array();
			}
			foreach ( $terms as $input ) {
				if ( empty( $input ) ) {
					continue;
				}
				$terms_array = explode( ' , ', $input );
				// Trim whitespaces from each term
				$terms_array = array_map( 'trim', $terms_array );
				if ( empty( $terms_array ) || ! is_array( $terms_array ) ) {
					// Insufficient terms to create
					continue; // Skip
				}
				// First term is the parent
				$parent_term_name = rtrim( reset( $terms_array ), ' ,' );
				$parent_term_slug = sanitize_title( $parent_term_name );
				if ( empty( $parent_term_slug ) ) {
					// Insufficient terms to create
					continue; // Skip
				}
				$terms_ids = wp_set_object_terms( $fooditem_id, $parent_term_name, $taxonomy );
				if ( empty( $terms_ids ) ) {
					continue;
				}
				$parent_term_id = $terms_ids[0];
				if ( isset( $parent_term_id ) && 'addon_category' == $taxonomy ) {
					if ( ! isset( $addons[ '' . $parent_term_id ] ) ) {
						$addons[ '' . $parent_term_id ] = array(
							'category' => '' . $parent_term_id,
						);
					}
				}
				// Create child terms and associate them with the parent
				array_splice( $terms_array, 0, 1 );
				foreach ( $terms_array as $child_term_name ) {
					$child_term_slug = sanitize_title( rtrim( $child_term_name, ' ,' ) );
					if ( empty( $child_term_slug ) ) {
						// Insufficient terms to create
						continue; // Skip
					}
					// Check if the child term already exists
					$child_term_exists = term_exists( $child_term_slug, $taxonomy );
					if ( $child_term_exists ) {
						$child_term_id = $child_term_exists['term_id'];
					} else {
						// Create the child term
						$child_term_args = array(
							'parent'      => $parent_term_id,
							'taxonomy'    => $taxonomy,
							'description' => '', // You can set a description if needed
							'slug'        => $child_term_slug,
						);
						$child_term = wp_insert_term( $child_term_name, $taxonomy, $child_term_args );
						if ( is_wp_error( $child_term ) ) {
							// Handle the error if any
							error_log( 'Error creating child term: ' . $child_term->get_error_message() );
							continue; // Skip to the next iteration
						}
						$child_term_id = $child_term['term_id'];
					}
					// Set the terms for the specified object
					
					if ( 'addon_category' == $taxonomy ) {
						if ( ! isset( $addons[ '' . $parent_term_id ]['items'] ) ) {
							$addons[ '' . $parent_term_id ]['items'] = array();
						}
						$addons[ '' . $parent_term_id ]['items'][] = '' . $child_term_id;
					}
				}
				if ( ! empty( $addons ) ) {
					unset( $addons['0'] );
					update_post_meta( $fooditem_id, '_addon_items', $addons );
				}
                if(!empty($terms_array)){
                array_unshift($terms_array,$parent_term_slug);
                wp_set_object_terms( $fooditem_id, $terms_array, $taxonomy );
                }
			}
			return $addons;
		}
	}
	/**
	 * Set up and taxonomy terms
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_taxonomy_terms( $fooditem_id = 0, $terms = array(), $taxonomy = 'food-category', $parent_addon = false ) {
		$terms = $this->maybe_create_terms( $terms, $taxonomy, $parent_addon );
		if( ! empty( $terms ) ) {
			wp_set_object_terms( $fooditem_id, $terms, $taxonomy );
		}
		if( ! empty( $terms ) && 'addon_category' == $taxonomy ) {
			$all_addons = get_terms( array(
				'taxonomy' 	=> 'addon_category',
				'include'	=> $terms
			) );
			$addon_items = array();
			if( ! is_wp_error( $all_addons ) ) {
				foreach ( $all_addons as $addon ) {
					if( $addon->parent != 0 )
						continue;
					$addon_items[$addon->term_id] = array(
						'category' => $addon->term_id,
						'items' => []
					);
				}
				foreach ( $all_addons as $addon ) {
					if( $addon->parent == 0 )
						continue;
					$addon_items[$addon->parent]['items'][] = $addon->term_id;
				}
			}
			if( ! empty( $addon_items ) ) {
				update_post_meta( $fooditem_id, '_addon_items', $addon_items );
			}
		}
	}
	/**
	 * Locate term IDs or create terms if none are found
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function maybe_create_terms( $terms = array(), $taxonomy = 'food-category', $parent_addon = false ) {
		// Return of term IDs
		$term_ids = array();
		foreach( $terms as $term ) {
			$parent_id = 0;
			// Get parent Addon ID
			$parent = get_term_by( 'slug', $parent_addon ,$taxonomy );
			if ( !empty( $parent ) ) {
				$parent_id = $parent->term_id;
			}
			if( is_numeric( $term ) && 0 === (int) $term ) {
				$t = get_term( $term, $taxonomy );
			} else {
				$t = get_term_by( 'name', $term, $taxonomy );
				if( ! $t ) {
					$t = get_term_by( 'slug', $term, $taxonomy );
				}
			}
			if( ! empty( $t ) ) {
				$term_ids[] = $t->term_id;
			} else {
				$term_data = wp_insert_term( $term, $taxonomy, array( 'slug' => sanitize_title( $term ), 'parent' => $parent_id ) );
				if( ! is_wp_error( $term_data ) ) {
					$term_ids[] = $term_data['term_id'];
				}
			}
		}
		return array_map( 'absint', $term_ids );
	}
	/**
	 * Retrieve URL to RestroPress list table
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_list_table_url() {
		return admin_url( 'edit.php?post_type=fooditem' );
	}
	/**
	 * Retrieve RestroPress label
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_import_type_label() {
		return rpress_get_label_plural( true );
	}
}