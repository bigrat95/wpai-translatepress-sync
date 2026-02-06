<?php
/**
 * Plugin Name: WP All Import - TranslatePress Sync
 * Plugin URI: https://github.com/bigrat95/wpai-translatepress-sync
 * Description: Automatically sync translations from WP All Import to TranslatePress using the official Custom API. Map _trp_title_[lang] and _trp_content_[lang] custom fields in your import.
 * Version: 2.3.0
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPL v2 or later
 * Text Domain: wpai-trp-sync
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =============================================================================
 * WP ALL IMPORT + TRANSLATEPRESS SYNC PLUGIN (v2 - Using Official API)
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
 * FOR WOOCOMMERCE PRODUCTS (Categories & Attributes):
 *    - _trp_cat_[lang]           → Translated category names (pipe-separated for multiple)
 *    - _trp_cat_slug_[lang]      → Translated category slugs (requires SEO Pack)
 *    - _trp_attr_[slug]_[lang]   → Translated attribute values (e.g., _trp_attr_color_fr_CA)
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
     * Check if line break conversion is enabled for this import
     * Looks for _trp_convert_linebreaks custom field in the import data
     * 
     * @param array $article_data Post data being imported
     * @return bool
     */
    private function is_linebreak_conversion_enabled( $article_data ) {
        try {
            if ( ! is_array( $article_data ) ) {
                return false;
            }
            
            // Check in custom fields being imported
            if ( ! isset( $article_data['post_meta'] ) || ! is_array( $article_data['post_meta'] ) ) {
                return false;
            }
            
            foreach ( $article_data['post_meta'] as $meta ) {
                if ( ! is_array( $meta ) ) {
                    continue;
                }
                if ( isset( $meta['key'] ) && $meta['key'] === '_trp_convert_linebreaks' ) {
                    return ! empty( $meta['value'] ) && $meta['value'] === '1';
                }
            }
        } catch ( \Throwable $e ) {
            $this->log( 'Error checking linebreak conversion: ' . $e->getMessage() );
        }
        return false;
    }

    /**
     * Convert line breaks to <br> tags before WordPress saves the post
     * This prevents wpautop() from creating multiple <p> tags
     * ONLY activated when _trp_convert_linebreaks custom field is set to "1"
     * 
     * @param array $article_data Post data being imported
     * @param object $import Import object
     * @param int $post_id Post ID (0 for new posts)
     * @param int $current_position Current import position
     * @param array $xml_node Current record data
     * @return array Modified article data
     */
    public function convert_linebreaks_before_save( $article_data, $import, $post_id, $current_position ) {
        try {
            // Safety check
            if ( ! is_array( $article_data ) ) {
                return $article_data;
            }
            
            // Only convert if _trp_convert_linebreaks is set to "1"
            if ( ! $this->is_linebreak_conversion_enabled( $article_data ) ) {
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
     * Convert double line breaks to <br><br> and single to <br>
     * Preserves visual paragraph spacing without creating multiple <p> tags
     * 
     * @param string $content Content with line breaks
     * @return string Content with <br> tags
     */
    private function convert_linebreaks( $content ) {
        // Skip if already contains <p> tags (already formatted HTML)
        if ( preg_match( '/<p[^>]*>/i', $content ) ) {
            return $content;
        }
        
        // Convert double line breaks to <br><br> (paragraph breaks)
        $content = preg_replace( '/\r\n\r\n|\n\n|\r\r/', '<br><br>', $content );
        
        // Convert remaining single line breaks to <br>
        $content = preg_replace( '/\r\n|\n|\r/', '<br>', $content );
        
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
        try {
            // Check if TranslatePress Custom API is available
            if ( ! function_exists( 'trpc_insert_translation' ) ) {
                $this->log( 'TranslatePress Custom API not available, skipping post #' . $post_id );
                return;
            }

            $post = get_post( $post_id );
            if ( ! $post ) {
                return;
            }

            $translation_languages = $this->get_translation_languages();
            if ( empty( $translation_languages ) ) {
                $this->log( 'No translation languages configured' );
                return;
            }

            $translations_added = 0;

            // Get all post meta to find our translation fields
            $all_meta = get_post_meta( $post_id );

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

                    // Convert line breaks in translation to match the original format
                    // Only if _trp_convert_linebreaks is enabled
                    $convert_enabled = isset( $all_meta['_trp_convert_linebreaks'] ) && $all_meta['_trp_convert_linebreaks'][0] === '1';
                    if ( $convert_enabled ) {
                        $translated_value = $this->convert_linebreaks( $translated_value );
                    }

                    // Get original value from post
                    $original_value = isset( $post->$post_field ) ? $post->$post_field : '';
                    
                    if ( empty( $original_value ) ) {
                        continue;
                    }

                    // Use official TranslatePress Custom API
                    $result = trpc_insert_translation( 
                        $original_value, 
                        $translated_value, 
                        $lang_code,
                        array( 'status' => 2 ) // HUMAN_REVIEWED
                    );

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

            // Clean up the convert flag
            delete_post_meta( $post_id, '_trp_convert_linebreaks' );

            // Sync category translations (WooCommerce products)
            $this->sync_category_translations( $post_id, $all_meta, $translation_languages );

            // Sync attribute translations (WooCommerce products)
            $this->sync_attribute_translations( $post_id, $all_meta, $translation_languages );

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
                        $result = trpc_insert_translation(
                            $category->name,
                            $translated_cats[ $index ],
                            $lang_code,
                            array( 'status' => 2 )
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
        // Check if this is a WooCommerce product
        if ( get_post_type( $post_id ) !== 'product' || ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        $attributes = $product->get_attributes();
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

            // Get original attribute values
            $taxonomy = 'pa_' . $attr_slug;
            
            if ( isset( $attributes[ $taxonomy ] ) && $attributes[ $taxonomy ]->is_taxonomy() ) {
                // Global attribute (taxonomy-based)
                $terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'all' ) );
                
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    $translated_values = array_map( 'trim', explode( '|', $meta_values[0] ) );
                    
                    foreach ( $terms as $index => $term ) {
                        if ( isset( $translated_values[ $index ] ) && ! empty( $translated_values[ $index ] ) ) {
                            $result = trpc_insert_translation(
                                $term->name,
                                $translated_values[ $index ],
                                $lang_code,
                                array( 'status' => 2 )
                            );
                            
                            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                                $translations_added++;
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
                        $result = trpc_insert_translation(
                            $original_value,
                            $translated_values[ $index ],
                            $lang_code,
                            array( 'status' => 2 )
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
            <p><strong>📝 TranslatePress Sync Active v2.3</strong> (using Official API)</p>
            
            <p><strong>📄 Post/Product Fields:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_trp_title_<?php echo esc_html( $first_lang ); ?></code> → Translated title</li>
                <li><code>_trp_content_<?php echo esc_html( $first_lang ); ?></code> → Translated content</li>
                <li><code>_trp_excerpt_<?php echo esc_html( $first_lang ); ?></code> → Translated excerpt (optional)</li>
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
            
            <p style="margin-top: 10px;"><strong>⚡ Line Break Conversion (optional):</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>_trp_convert_linebreaks</code> → Set to <code>1</code> to convert \n to &lt;br&gt;</li>
            </ul>
            
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
