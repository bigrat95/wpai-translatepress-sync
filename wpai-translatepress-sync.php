<?php
/**
 * Plugin Name: TranslatePress Import Sync
 * Plugin URI: https://github.com/bigrat95/wpai-translatepress-sync
 * Description: Automatically sync translations from WP All Import to TranslatePress using the official Custom API. Map _trp_title_[lang] and _trp_content_[lang] custom fields in your import.
 * Version: 3.8.1
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPL v2 or later
 * Text Domain: wpai-translatepress-sync
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =============================================================================
 * WP ALL IMPORT + TRANSLATEPRESS SYNC PLUGIN (v3 - Using Official API)
 * =============================================================================
 * 
 * REQUIRES: TranslatePress Custom API plugin
 * 
 * HOW TO USE IN WP ALL IMPORT:
 * 
 * 1. In your import template, map these custom fields:
 *    - _trp_title_[lang]    → Your translated title field from CSV/XML
 *    - _trp_content_[lang]  → Your translated content/description field
 *    - _trp_excerpt_[lang]  → Your translated excerpt/short description (optional)
 * 
 *    Where [lang] is the language code (e.g., fr_CA, es_ES, de_DE)
 *    Examples: _trp_title_fr_CA, _trp_content_fr_CA
 * 
 * FOR WOOCOMMERCE PRODUCTS (Categories, Attributes & Variations):
 *    - _trp_cat_[lang]              → Translated category names (pipe-separated for multiple)
 *    - _trp_cat_slug_[lang]         → Translated category slugs (requires SEO Pack)
 *    - _trp_attr_[slug]_[lang]      → Translated attribute values (e.g., _trp_attr_color_fr_CA)
 *    - _trp_variation_desc_[lang]   → Translated variation description
 *    
 *    Examples: _trp_cat_fr_CA = "Chaises|Tables", _trp_attr_color_fr_CA = "Rouge|Bleu"
 * 
 * 2. Map your default language fields normally:
 *    - Title   → Default language title
 *    - Content → Default language description
 *    - Excerpt → Default language short description
 * 
 * 3. Run the import - translations are automatically synced to TranslatePress
 */

class WPAI_TranslatePress_Sync {

    /**
     * Custom field prefixes for post fields
     */
    private $field_prefixes = array(
        '_trp_title_'   => 'post_title',
        '_trp_content_' => 'post_content',
        '_trp_excerpt_' => 'post_excerpt',
    );

    /**
     * Prefixes for WooCommerce category translations
     */
    private $cat_prefix = '_trp_cat_';
    private $cat_slug_prefix = '_trp_cat_slug_';

    /**
     * Prefix for attribute translations
     */
    private $attr_prefix = '_trp_attr_';

    /**
     * Prefix for variation description translations
     */
    private $variation_desc_prefix = '_trp_variation_desc_';

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WP All Import BEFORE post is saved to convert line breaks
        add_filter( 'pmxi_article_data', array( $this, 'convert_linebreaks_before_save' ), 10, 4 );
        
        // Hook into WP All Import after post is saved
        add_action( 'pmxi_saved_post', array( $this, 'sync_translations' ), 10, 3 );
        
        // Admin notice for configuration help
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );
    }

    /**
     * Convert line breaks to <br> tags before WordPress saves the post
     * This prevents wpautop() from creating multiple <p> tags
     * Always runs during import to normalize paragraph breaks
     * 
     * @param array $article_data Post data being imported
     * @param object $import Import object
     * @param int $post_id Post ID (0 for new posts)
     * @param int $current_position Current import position
     * @return array Modified article data
     */
    public function convert_linebreaks_before_save( $article_data, $import, $post_id, $current_position ) {
        try {
            if ( ! is_array( $article_data ) ) {
                return $article_data;
            }
            
            if ( ! empty( $article_data['post_content'] ) && is_string( $article_data['post_content'] ) ) {
                $article_data['post_content'] = $this->convert_linebreaks( $article_data['post_content'] );
            }
            
            if ( ! empty( $article_data['post_excerpt'] ) && is_string( $article_data['post_excerpt'] ) ) {
                $article_data['post_excerpt'] = $this->convert_linebreaks( $article_data['post_excerpt'] );
            }
        } catch ( \Throwable $e ) {
            $this->log( 'Error in convert_linebreaks_before_save: ' . $e->getMessage() );
        }
        
        return $article_data;
    }

    /**
     * Log messages for debugging
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WPAI-TRP-Sync] ' . $message );
        }
    }

    /**
     * Force insert/update a translation by deleting existing one first
     * This ensures translations are ALWAYS updated, not skipped if they exist
     * 
     * Also inserts HTML-encoded version if the string contains special characters,
     * because TranslatePress may detect the string with HTML entities on the frontend.
     * 
     * @param string $original    Original string
     * @param string $translated  Translated string
     * @param string $lang_code   Language code (e.g., 'fr_CA')
     * @return array|WP_Error Result from trpc_insert_translation
     */
    private function force_insert_translation( $original, $translated, $lang_code ) {
        global $wpdb;
        
        // First, delete any existing translation for this original string
        $trp = TRP_Translate_Press::get_trp_instance();
        $trp_settings_obj = $trp->get_component( 'settings' );
        $trp_settings = $trp_settings_obj->get_settings();
        
        // Get the table name for this language (format: trp_dictionary_[default]_[translation])
        $default_lang = strtolower( $trp_settings['default-language'] );
        $table_name = $wpdb->prefix . 'trp_dictionary_' . $default_lang . '_' . strtolower( $lang_code );
        
        // Delete existing translation for this original string
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `$table_name` WHERE original = %s",
                $original
            )
        );
        
        // Also check for HTML-encoded version and delete it too
        $original_encoded = htmlspecialchars( $original, ENT_QUOTES, 'UTF-8' );
        if ( $original_encoded !== $original ) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `$table_name` WHERE original = %s",
                    $original_encoded
                )
            );
        }
        
        // Now insert the new translation using the API
        if ( function_exists( 'trpc_insert_translation' ) ) {
            // Insert the raw version
            $result = trpc_insert_translation( $original, $translated, $lang_code, array( 'status' => 2 ) );
            
            // Also insert HTML-encoded version if different (for frontend matching)
            // TranslatePress detects strings after esc_html() is applied, so quotes become &quot;
            if ( $original_encoded !== $original ) {
                $translated_encoded = htmlspecialchars( $translated, ENT_QUOTES, 'UTF-8' );
                trpc_insert_translation( $original_encoded, $translated_encoded, $lang_code, array( 'status' => 2 ) );
            }
            
            // Handle wptexturize'd version (WordPress converts straight quotes to smart quotes on render)
            // TranslatePress detects the rendered page where " becomes &#8221; / &#8220; etc.
            $original_texturized = wptexturize( $original );
            if ( $original_texturized !== $original ) {
                $translated_texturized = wptexturize( $translated );
                
                // Update any existing auto-detected entries that match the texturized version
                $wpdb->update(
                    $table_name,
                    array(
                        'translated' => $translated_texturized,
                        'status'     => 2,
                    ),
                    array( 'original' => $original_texturized ),
                    array( '%s', '%d' ),
                    array( '%s' )
                );
                
                // If no rows were updated, insert a new entry
                if ( $wpdb->rows_affected === 0 ) {
                    trpc_insert_translation( $original_texturized, $translated_texturized, $lang_code, array( 'status' => 2 ) );
                }
                
                // Also handle the HTML-encoded texturized version
                $original_tex_encoded = htmlspecialchars( $original_texturized, ENT_QUOTES, 'UTF-8' );
                if ( $original_tex_encoded !== $original_texturized ) {
                    $translated_tex_encoded = htmlspecialchars( $translated_texturized, ENT_QUOTES, 'UTF-8' );
                    
                    $wpdb->update(
                        $table_name,
                        array(
                            'translated' => $translated_tex_encoded,
                            'status'     => 2,
                        ),
                        array( 'original' => $original_tex_encoded ),
                        array( '%s', '%d' ),
                        array( '%s' )
                    );
                    
                    if ( $wpdb->rows_affected === 0 ) {
                        trpc_insert_translation( $original_tex_encoded, $translated_tex_encoded, $lang_code, array( 'status' => 2 ) );
                    }
                }
            }
            
            return $result;
        }
        
        return new WP_Error( 'api_not_available', 'TranslatePress Custom API not available' );
    }

    /**
     * Flatten content into a single block for TranslatePress compatibility
     * 
     * TranslatePress detects each block-level HTML element (<p>, <div>) as a
     * separate translatable string. This function removes all block-level
     * structure and replaces it with <br> tags, so the entire description is
     * always detected as ONE string = ONE dictionary entry.
     * 
     * Handles all known content formats:
     * - Raw newlines from CSV/spreadsheet (\n, \r\n, \r)
     * - <p> tags from WordPress editor or wpautop()
     * - <div> wrappers from page builders or rich editors
     * - Gutenberg block comments (<!-- wp:paragraph -->)
     * - <br>, <br/>, <br /> variants
     * - &nbsp; used as paragraph spacers
     * - Mixed combinations of all the above
     * 
     * @param string $content Content in any format
     * @return string Flattened content with <br> as line separators
     */
    private function convert_linebreaks( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        // 1. Strip Gutenberg block comments: <!-- wp:paragraph --> etc.
        $content = preg_replace( '/<!--\s*\/?\s*wp:\w+[^>]*?-->/s', '', $content );

        // 2. Normalize all <br> variants (<br>, <br/>, <br />) to standard <br>
        $content = preg_replace( '/<br\s*\/?>/i', '<br>', $content );

        // 3. Replace closing+opening block tag pairs with <br><br>
        //    Handles: </p><p>, </p>\n\n<p>, </div><div class="x">, etc.
        $content = preg_replace(
            '/<\/(p|div)>\s*<(p|div)[^>]*>/i',
            '<br><br>',
            $content
        );

        // 4. Strip any remaining <p>, </p>, <div>, </div> tags
        $content = preg_replace( '/<\/?(p|div)[^>]*>/i', '', $content );

        // 5. Convert double line breaks to <br><br> (paragraph breaks)
        //    Handles \r\n\r\n, \n\n, \r\r, and variants with whitespace between
        $content = preg_replace( '/(\r\n\s*){2,}/', '<br><br>', $content );
        $content = preg_replace( '/(\n\s*){2,}/', '<br><br>', $content );
        $content = preg_replace( '/(\r\s*){2,}/', '<br><br>', $content );

        // 6. Convert remaining single line breaks to <br>
        $content = preg_replace( '/\r\n|\n|\r/', '<br>', $content );

        // 7. Collapse 3+ consecutive <br> (with optional whitespace) to <br><br>
        $content = preg_replace( '/(<br>\s*){3,}/', '<br><br>', $content );

        // 8. Collapse multiple &nbsp; used as paragraph spacers into <br><br>
        $content = preg_replace( '/(&nbsp;\s*){3,}/', '<br><br>', $content );

        // 9. Remove leading/trailing <br> tags and whitespace
        $content = preg_replace( '/^(\s*<br>\s*)+/', '', $content );
        $content = preg_replace( '/(\s*<br>\s*)+$/', '', $content );

        // 10. Final trim
        $content = trim( $content );

        return $content;
    }

    /**
     * Get configured translation languages from TranslatePress
     */
    private function get_translation_languages() {
        if ( function_exists( 'trpc_get_languages' ) ) {
            $langs = trpc_get_languages();
            return isset( $langs['translations'] ) ? $langs['translations'] : array();
        }
        
        // Fallback to settings
        $trp_settings = get_option( 'trp_settings', array() );
        $all_langs = isset( $trp_settings['translation-languages'] ) ? $trp_settings['translation-languages'] : array();
        $default = isset( $trp_settings['default-language'] ) ? $trp_settings['default-language'] : 'en_US';
        
        return array_diff( $all_langs, array( $default ) );
    }

    /**
     * Sync translations after WP All Import saves a post
     */
    public function sync_translations( $post_id, $xml_node, $is_update ) {
        $this->log( '=== SYNC START for post #' . $post_id . ' ===' );
        
        try {
            // Check if TranslatePress Custom API is available
            if ( ! function_exists( 'trpc_insert_translation' ) ) {
                $this->log( 'ERROR: trpc_insert_translation function not available!' );
                return;
            }
            $this->log( 'trpc_insert_translation function is available' );

            $post = get_post( $post_id );
            if ( ! $post ) {
                $this->log( 'ERROR: Could not get post #' . $post_id );
                return;
            }
            $this->log( 'Post type: ' . $post->post_type );

            $translation_languages = $this->get_translation_languages();
            if ( empty( $translation_languages ) ) {
                $this->log( 'No translation languages configured' );
                return;
            }
            $this->log( 'Translation languages: ' . implode( ', ', $translation_languages ) );

            $translations_added = 0;

            // Get all post meta to find our translation fields
            $all_meta = get_post_meta( $post_id );
            $this->log( 'Found ' . count( $all_meta ) . ' meta fields' );
            
            // Log which _trp_ fields exist
            $trp_fields = array_filter( array_keys( $all_meta ), function( $key ) {
                return strpos( $key, '_trp_' ) === 0;
            });
            $this->log( 'TRP fields found: ' . ( empty( $trp_fields ) ? 'NONE' : implode( ', ', $trp_fields ) ) );

            foreach ( $this->field_prefixes as $prefix => $post_field ) {
                foreach ( $translation_languages as $lang_code ) {
                    $meta_key = $prefix . $lang_code;
                    
                    // Check if this meta exists
                    if ( ! isset( $all_meta[ $meta_key ] ) ) {
                        continue;
                    }
                    
                    $translated_value = $all_meta[ $meta_key ][0];
                    
                    if ( empty( $translated_value ) ) {
                        continue;
                    }

                    // Get original value from post
                    $original_value = isset( $post->$post_field ) ? $post->$post_field : '';
                    
                    if ( empty( $original_value ) ) {
                        continue;
                    }

                    // Normalize paragraph breaks: convert \n\n to <br><br> in both original and translation
                    // This prevents wpautop() from creating separate <p> tags, so TranslatePress
                    // always detects the description as ONE string = ONE dictionary entry
                    $original_normalized = $this->convert_linebreaks( $original_value );
                    $translated_value = $this->convert_linebreaks( $translated_value );

                    // If the original had paragraph breaks, update the post field in DB
                    if ( $original_normalized !== $original_value && in_array( $post_field, array( 'post_content', 'post_excerpt' ), true ) ) {
                        wp_update_post( array(
                            'ID'        => $post_id,
                            $post_field => $original_normalized,
                        ) );
                        $this->log( sprintf( 'Normalized paragraph breaks in %s for post #%d', $post_field, $post_id ) );
                    }

                    // Force update translation (delete existing first)
                    $result = $this->force_insert_translation( $original_normalized, $translated_value, $lang_code );

                    if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                        $translations_added++;
                    } elseif ( is_wp_error( $result ) ) {
                        $this->log( sprintf( 'API Error for post #%d, field %s: %s', $post_id, $meta_key, $result->get_error_message() ) );
                    }

                    // Clean up temporary meta field
                    delete_post_meta( $post_id, $meta_key );
                }
            }

            // Log for debugging
            if ( $translations_added > 0 ) {
                $this->log( sprintf( 'Added %d translations for post #%d', $translations_added, $post_id ) );
            }

            // Clean up legacy convert flag if present (no longer required)
            delete_post_meta( $post_id, '_trp_convert_linebreaks' );

            // Sync category translations (WooCommerce products)
            $this->sync_category_translations( $post_id, $all_meta, $translation_languages );

            // Sync attribute translations (WooCommerce products)
            $this->sync_attribute_translations( $post_id, $all_meta, $translation_languages );

            // Sync variation description translations (WooCommerce variations)
            $this->sync_variation_description_translations( $post_id, $all_meta, $translation_languages );

        } catch ( Exception $e ) {
            $this->log( 'Error in sync_translations for post #' . $post_id . ': ' . $e->getMessage() );
        }
    }

    /**
     * Sync category translations for WooCommerce products
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_category_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product
        if ( get_post_type( $post_id ) !== 'product' ) {
            return;
        }

        // Get assigned product categories
        $categories = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'all' ) );
        if ( is_wp_error( $categories ) || empty( $categories ) ) {
            return;
        }

        $translations_added = 0;

        foreach ( $translation_languages as $lang_code ) {
            // Category name translations
            $cat_meta_key = $this->cat_prefix . $lang_code;
            if ( isset( $all_meta[ $cat_meta_key ] ) && ! empty( $all_meta[ $cat_meta_key ][0] ) ) {
                $translated_cats = array_map( 'trim', explode( '|', $all_meta[ $cat_meta_key ][0] ) );
                
                // Match translated categories with original categories by position
                foreach ( $categories as $index => $category ) {
                    if ( isset( $translated_cats[ $index ] ) && ! empty( $translated_cats[ $index ] ) ) {
                        $result = $this->force_insert_translation(
                            $category->name,
                            $translated_cats[ $index ],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                }
                
                delete_post_meta( $post_id, $cat_meta_key );
            }

            // Category slug translations (requires SEO Pack)
            $cat_slug_meta_key = $this->cat_slug_prefix . $lang_code;
            if ( isset( $all_meta[ $cat_slug_meta_key ] ) && ! empty( $all_meta[ $cat_slug_meta_key ][0] ) ) {
                if ( function_exists( 'trpc_insert_slug_translation' ) ) {
                    $translated_slugs = array_map( 'trim', explode( '|', $all_meta[ $cat_slug_meta_key ][0] ) );
                    
                    foreach ( $categories as $index => $category ) {
                        if ( isset( $translated_slugs[ $index ] ) && ! empty( $translated_slugs[ $index ] ) ) {
                            $result = trpc_insert_slug_translation(
                                $category->slug,
                                $translated_slugs[ $index ],
                                $lang_code,
                                array( 'type' => 'term', 'status' => 2 )
                            );
                            
                            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                                $translations_added++;
                            }
                        }
                    }
                } else {
                    $this->log( 'SEO Pack required for slug translations, skipping category slugs' );
                }
                
                delete_post_meta( $post_id, $cat_slug_meta_key );
            }
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d category translations for post #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Sync attribute translations for WooCommerce products
     * 
     * Custom field format: _trp_attr_[attribute_slug]_[lang_code]
     * Example: _trp_attr_color_fr_CA = "Rouge|Bleu|Vert"
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_attribute_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product or variation
        $post_type = get_post_type( $post_id );
        
        if ( ! in_array( $post_type, array( 'product', 'product_variation' ), true ) || ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        // For variations, get parent product to access attributes
        $parent_product = $product;
        $terms_post_id = $post_id; // Post ID to get terms from
        if ( $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();
            $parent_product = wc_get_product( $parent_id );
            if ( ! $parent_product ) {
                return;
            }
            $terms_post_id = $parent_id; // Get terms from parent for variations
        }

        $attributes = $parent_product->get_attributes();
        if ( empty( $attributes ) ) {
            return;
        }

        $translations_added = 0;

        // Find all attribute translation meta keys
        foreach ( $all_meta as $meta_key => $meta_values ) {
            if ( strpos( $meta_key, $this->attr_prefix ) !== 0 ) {
                continue;
            }

            // Parse the meta key: _trp_attr_[attribute_slug]_[lang_code]
            $suffix = substr( $meta_key, strlen( $this->attr_prefix ) );
            
            // Find which language this is for
            $lang_code = null;
            $attr_slug = null;
            
            foreach ( $translation_languages as $lang ) {
                if ( substr( $suffix, -strlen( $lang ) - 1 ) === '_' . $lang ) {
                    $lang_code = $lang;
                    $attr_slug = substr( $suffix, 0, -strlen( $lang ) - 1 );
                    break;
                }
            }

            if ( ! $lang_code || ! $attr_slug || empty( $meta_values[0] ) ) {
                continue;
            }

            // Handle case where user already included 'pa_' prefix in meta key
            // e.g., _trp_attr_pa_magnetic_fr_CA -> attr_slug = 'pa_magnetic'
            if ( strpos( $attr_slug, 'pa_' ) === 0 ) {
                $attr_slug = substr( $attr_slug, 3 ); // Remove 'pa_' prefix
            }

            // Get original attribute values
            $taxonomy = 'pa_' . $attr_slug;
            
            if ( isset( $attributes[ $taxonomy ] ) && $attributes[ $taxonomy ]->is_taxonomy() ) {
                // Global attribute (taxonomy-based)
                $translated_values = array_map( 'trim', explode( '|', $meta_values[0] ) );
                
                // For variations, get the specific attribute value this variation uses
                if ( $product->is_type( 'variation' ) ) {
                    $variation_attr_value = $product->get_attribute( $taxonomy );
                    
                    if ( ! empty( $variation_attr_value ) && ! empty( $translated_values[0] ) ) {
                        $result = $this->force_insert_translation(
                            $variation_attr_value,
                            $translated_values[0],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                } else {
                    // For parent products, match all terms by index (pipe-separated values)
                    $terms = wp_get_post_terms( $terms_post_id, $taxonomy, array( 'fields' => 'all' ) );
                    
                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                        foreach ( $terms as $index => $term ) {
                            if ( isset( $translated_values[ $index ] ) && ! empty( $translated_values[ $index ] ) ) {
                                $result = $this->force_insert_translation(
                                    $term->name,
                                    $translated_values[ $index ],
                                    $lang_code
                                );
                                
                                if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                                    $translations_added++;
                                }
                            }
                        }
                    }
                }
            } elseif ( isset( $attributes[ $attr_slug ] ) && ! $attributes[ $attr_slug ]->is_taxonomy() ) {
                // Local/custom attribute (stored in product data)
                $original_values = $attributes[ $attr_slug ]->get_options();
                $translated_values = array_map( 'trim', explode( '|', $meta_values[0] ) );
                
                foreach ( $original_values as $index => $original_value ) {
                    if ( isset( $translated_values[ $index ] ) && ! empty( $translated_values[ $index ] ) ) {
                        $result = $this->force_insert_translation(
                            $original_value,
                            $translated_values[ $index ],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                }
            }

            delete_post_meta( $post_id, $meta_key );
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d attribute translations for post #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Sync variation description translations for WooCommerce product variations
     * 
     * Custom field format: _trp_variation_desc_[lang_code]
     * Example: _trp_variation_desc_fr_CA = "Description traduite de la variation"
     * 
     * In WP All Import, map:
     * - _variation_description → Your default language variation description
     * - _trp_variation_desc_fr_CA → Your translated variation description
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_variation_description_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product variation
        if ( get_post_type( $post_id ) !== 'product_variation' ) {
            return;
        }

        // Get the original variation description
        $original_desc = isset( $all_meta['_variation_description'] ) ? $all_meta['_variation_description'][0] : '';
        
        if ( empty( $original_desc ) ) {
            return;
        }

        $translations_added = 0;

        foreach ( $translation_languages as $lang_code ) {
            $meta_key = $this->variation_desc_prefix . $lang_code;
            
            if ( ! isset( $all_meta[ $meta_key ] ) || empty( $all_meta[ $meta_key ][0] ) ) {
                continue;
            }

            $translated_desc = $all_meta[ $meta_key ][0];

            // Normalize both original and translated to single block
            $original_desc_normalized = $this->convert_linebreaks( $original_desc );
            $translated_desc = $this->convert_linebreaks( $translated_desc );

            // Force update translation
            $result = $this->force_insert_translation(
                $original_desc_normalized,
                $translated_desc,
                $lang_code
            );

            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                $translations_added++;
            } elseif ( is_wp_error( $result ) ) {
                $this->log( sprintf( 'API Error for variation #%d description: %s', $post_id, $result->get_error_message() ) );
            }

            // Keep the meta field for direct lookup by theme (don't delete)
            // This allows themes to read translation directly without dictionary lookup
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d variation description translations for variation #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Show admin notice with usage instructions
     */
    public function admin_notice() {
        $screen = get_current_screen();
        
        // Only show on WP All Import pages
        if ( ! $screen || strpos( $screen->id, 'pmxi' ) === false ) {
            return;
        }

        // Check if TranslatePress Custom API is available
        if ( ! function_exists( 'trpc_insert_translation' ) ) {
            ?>
            <div class="notice notice-error">
                <p><strong>WP All Import - TranslatePress Sync:</strong> TranslatePress Custom API plugin is required. Please install and activate it.</p>
            </div>
            <?php
            return;
        }

        $languages = $this->get_translation_languages();
        $lang_examples = ! empty( $languages ) ? implode( ', ', array_slice( $languages, 0, 2 ) ) : 'fr_CA';

        $first_lang = ! empty( $languages ) ? $languages[0] : 'fr_CA';
        ?>
        <div class="notice notice-info is-dismissible" id="wpai-trp-notice">
            <p><strong>📝 TranslatePress Sync Active v3.8.1</strong> (Force Update Mode)</p>
            
            <p><strong>📄 Post/Product Fields:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_trp_title_<?php echo esc_html( $first_lang ); ?></code> → Translated title</li>
                <li><code>_trp_content_<?php echo esc_html( $first_lang ); ?></code> → Translated content</li>
                <li><code>_trp_excerpt_<?php echo esc_html( $first_lang ); ?></code> → Translated excerpt (optional)</li>
            </ul>
            
            <p style="margin-top: 10px;"><strong>🔄 WooCommerce Variations:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_variation_description</code> → Default language variation description (standard WooCommerce field)</li>
                <li><code>_trp_variation_desc_<?php echo esc_html( $first_lang ); ?></code> → Translated variation description</li>
            </ul>
            
            <p style="margin-top: 10px;"><strong>🏷️ WooCommerce Categories:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_trp_cat_<?php echo esc_html( $first_lang ); ?></code> → Translated category names (pipe-separated: "Chaises|Tables")</li>
                <li><code>_trp_cat_slug_<?php echo esc_html( $first_lang ); ?></code> → Translated category slugs (requires SEO Pack)</li>
            </ul>
            
            <p style="margin-top: 10px;"><strong>🎨 WooCommerce Attributes:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_trp_attr_[slug]_<?php echo esc_html( $first_lang ); ?></code> → Translated attribute values (pipe-separated)</li>
                <li>Example: <code>_trp_attr_color_<?php echo esc_html( $first_lang ); ?></code> = "Rouge|Bleu|Vert"</li>
            </ul>
            
            <?php
            // Get all WooCommerce product attributes
            if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                if ( ! empty( $attribute_taxonomies ) ) {
                    ?>
                    <details style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <summary style="cursor: pointer; font-weight: bold; color: #0073aa;">
                            📋 Available Attribute Fields (<?php echo count( $attribute_taxonomies ); ?> attributes × <?php echo count( $languages ); ?> languages = <?php echo count( $attribute_taxonomies ) * count( $languages ); ?> fields)
                        </summary>
                        <div style="margin-top: 10px; max-height: 300px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr style="background: #e0e0e0;">
                                        <th style="padding: 5px; text-align: left; border: 1px solid #ccc;">Attribute</th>
                                        <?php foreach ( $languages as $lang ) : ?>
                                            <th style="padding: 5px; text-align: left; border: 1px solid #ccc;"><?php echo esc_html( $lang ); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $attribute_taxonomies as $attribute ) : ?>
                                        <tr>
                                            <td style="padding: 5px; border: 1px solid #ccc; font-weight: bold;">
                                                <?php echo esc_html( $attribute->attribute_label ); ?>
                                                <br><small style="color: #666;">pa_<?php echo esc_html( $attribute->attribute_name ); ?></small>
                                            </td>
                                            <?php foreach ( $languages as $lang ) : ?>
                                                <td style="padding: 5px; border: 1px solid #ccc;">
                                                    <code style="font-size: 11px; background: #fff; padding: 2px 4px;">_trp_attr_<?php echo esc_html( $attribute->attribute_name ); ?>_<?php echo esc_html( $lang ); ?></code>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="margin-top: 10px; font-size: 11px; color: #666;">
                                <strong>💡 Tip:</strong> Copy the field name and paste it in WP All Import custom fields. Values should be pipe-separated (e.g., "Rouge|Bleu|Vert").
                            </p>
                        </div>
                    </details>
                    <?php
                }
            }
            ?>
            
            <p style="margin-top: 10px;"><strong>⚡ Paragraph Normalization:</strong> Line breaks are automatically converted to <code>&lt;br&gt;</code> tags so TranslatePress always detects one string per description. No extra fields needed.</p>
            
            <p style="margin-top: 10px;"><strong>🔒 Auto-Translation Protection:</strong> All imported translations are saved with <strong>status 2 (Human Reviewed)</strong>.</p>
            <p><small>Configured languages: <?php echo esc_html( implode( ', ', $languages ) ); ?></small></p>
        </div>
        <?php
    }
}

// Initialize the plugin
new WPAI_TranslatePress_Sync();

/**
 * =============================================================================
 * HELPER FUNCTIONS (using official TranslatePress Custom API)
 * =============================================================================
 */

/**
 * Manually add a translation (wrapper for official API)
 * 
 * @param string $original   The original string
 * @param string $translated The translated string
 * @param string $lang       Target language code (e.g., fr_CA, es_ES)
 * @return array|WP_Error
 * 
 * Usage: wpai_trp_add_translation( 'Hello', 'Bonjour', 'fr_CA' );
 */
function wpai_trp_add_translation( $original, $translated, $lang = 'fr_CA' ) {
    if ( ! function_exists( 'trpc_insert_translation' ) ) {
        return new WP_Error( 'missing_api', 'TranslatePress Custom API plugin is required.' );
    }
    
    return trpc_insert_translation( $original, $translated, $lang, array( 'status' => 2 ) );
}

/**
 * Bulk add translations (wrapper for official API)
 * 
 * @param array  $translations Array of 'original' => 'translated' pairs
 * @param string $lang         Target language code
 * @return array Results
 * 
 * Usage: wpai_trp_bulk_add_translations( array( 'Hello' => 'Bonjour' ), 'fr_CA' );
 */
function wpai_trp_bulk_add_translations( $translations, $lang = 'fr_CA' ) {
    if ( ! function_exists( 'trpc_insert_translations_bulk' ) ) {
        return new WP_Error( 'missing_api', 'TranslatePress Custom API plugin is required.' );
    }
    
    $formatted = array();
    foreach ( $translations as $original => $translated ) {
        $formatted[] = array(
            'original'   => $original,
            'translated' => $translated,
        );
    }
    
    return trpc_insert_translations_bulk( $formatted, $lang, array( 'status' => 2 ) );
}
