=== TranslatePress Import Sync ===
Contributors: olivierbigras
Tags: translatepress, wp all import, translation, multilingual, woocommerce
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sync translations from WP All Import to TranslatePress using the official Custom API.

== Description ==

**TranslatePress Import Sync** enables you to import multilingual content directly into TranslatePress when using WP All Import. Perfect for bulk importing products, posts, or any content with translations.

= Features =

* **Title, Content & Excerpt** - Import translated titles, descriptions, and excerpts
* **WooCommerce Categories** - Import translated category names and slugs
* **WooCommerce Attributes** - Import translated attribute values (colors, sizes, etc.)
* **Variation Descriptions** - Import translated variation descriptions
* **Variable Products** - Full support for WooCommerce variable products and variations
* **Multiple Languages** - Import all your languages simultaneously
* **Human Reviewed Status** - Translations are marked as "Human Reviewed" (status 2) so auto-translation won't overwrite them
* **Automatic Paragraph Stripping** - All paragraph breaks are stripped into a single text block so TranslatePress always matches one string per description

= Requirements =

* WP All Import Pro
* TranslatePress Multilingual
* TranslatePress Custom API plugin (provided by TranslatePress support)
* TranslatePress SEO Pack (optional, for slug translations)

= How It Works =

1. Map your default language fields normally in WP All Import
2. Add special custom fields for translated content (e.g., `_trp_title_fr_CA`)
3. Run the import - translations are automatically synced to TranslatePress
4. Temporary custom fields are cleaned up after import

== Installation ==

1. Upload the `wpai-translatepress-sync` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure TranslatePress and TranslatePress Custom API are also active
4. Configure your import in WP All Import with the translation custom fields

== Frequently Asked Questions ==

= What custom fields should I use? =

**For posts/products:**
* `_trp_title_[lang]` - Translated title
* `_trp_content_[lang]` - Translated content/description
* `_trp_excerpt_[lang]` - Translated excerpt (optional)

**For WooCommerce categories:**
* `_trp_cat_[lang]` - Translated category names (pipe-separated)
* `_trp_cat_slug_[lang]` - Translated category slugs (requires SEO Pack)

**For WooCommerce variation descriptions:**
* `_variation_description` - Default language variation description (standard WooCommerce field)
* `_trp_variation_desc_[lang]` - Translated variation description

**For WooCommerce attributes:**
* `_trp_attr_[attribute-slug]_[lang]` - Translated attribute values (pipe-separated)

Replace `[lang]` with your language code (e.g., `fr_CA`, `es_ES`, `de_DE`).

= What happens to existing translations? =

They are updated/overwritten with the new translations from your import.

= Will auto-translation overwrite my imported translations? =

No! Imported translations are saved with status 2 (Human Reviewed), which protects them from being overwritten by automatic translation.

= How do I import multiple categories or attribute values? =

Use pipe-separated values. Example: `_trp_cat_fr_CA` = "Chaises|Tables|Bureaux"

= Do I need the SEO Pack for category slug translations? =

Yes, TranslatePress SEO Pack addon is required for slug translations. Category name translations work without it.

== Screenshots ==

1. Admin notice showing available custom fields
2. WP All Import custom field mapping
3. Translations synced in TranslatePress

== Changelog ==

= 3.13.0 =
* FIX: Strip ALL paragraph separators into a single text block (spaces only, no <br>)
* wpautop() wraps in ONE <p> = TranslatePress detects ONE string = dictionary always matches
* Works regardless of paragraph count differences between languages
* Re-enabled content flattening hook and safety-net DB update

= 3.12.0 =
* Per-paragraph translation matching (superseded by 3.13.0) — inserts one TranslatePress dictionary entry per paragraph
* Removed content flattening (was causing mismatch between DB content and what TranslatePress detects)
* Added split_into_paragraphs helper that handles <p> tags, <br><br>, and double newlines
* Variation descriptions also use per-paragraph matching
* Fixes French translations not appearing for multi-paragraph product descriptions

= 3.11.0 =
* NEW: Full plugin dashboard under Settings → TP Import Sync
* Tabbed interface: Dashboard (system status), Field Reference (auto-detected fields with copy buttons), Logs
* Auto-detects TranslatePress languages, WooCommerce attributes, and all available translation fields
* Copy-to-clipboard button on every field name for easy WP All Import mapping
* Scrollable attributes table with sticky header
* WP All Import admin notice now slim link to dashboard instead of huge inline HTML

= 3.10.0 =
* Admin settings page with log viewer and logging toggle

= 3.9.0 =
* FIX: Content normalization now persists in database (uses direct DB update instead of wp_update_post)
* WooCommerce hooks were reverting normalized content back to original with paragraph breaks
* Added safety-net normalization on `pmxi_after_post_import` hook (runs after all WooCommerce processing)
* Added detailed logging to track normalization flow
* Fixes mixed English/French display caused by TranslatePress detecting individual paragraphs

= 3.8.1 =
* FIX: Variation description translations now always normalized (removed old `_trp_convert_linebreaks` check)
* FIX: Original variation description is also normalized before dictionary insertion
* FIX: post_excerpt normalization now also updates the DB (not just post_content)
* Updated header comment version

= 3.8.0 =
* Bulletproof content flattening: handles ALL content formats, never skips
* Strips `<p>`, `</p>`, `<div>`, `</div>` tags and replaces with `<br><br>`
* Strips Gutenberg block comments (`<!-- wp:paragraph -->`)
* Normalizes all `<br>` variants (`<br>`, `<br/>`, `<br />`)
* Collapses excessive `<br>` tags and `&nbsp;` spacers
* Handles mixed content: `<p>` tags + newlines + `<br>` + `<div>` in any combination
* Cleans up leading/trailing `<br>` tags

= 3.7.0 =
* Auto-normalize paragraph breaks: converts \n\n to <br><br> in both original and translated content
* Prevents wpautop() from creating separate <p> tags, so TranslatePress always detects one string per description
* Updates post_content in DB automatically when paragraph breaks are found
* `_trp_convert_linebreaks` custom field is no longer required (normalization always happens)
* Removed paragraph-splitting approach in favor of simpler single-block normalization

= 3.6.0 =
* FIX: Multi-paragraph descriptions now translated correctly
* TranslatePress detects each paragraph as a separate string; plugin now splits and inserts each paragraph pair individually
* Logs a warning when paragraph counts don't match between original and translated text
* Requires matching paragraph breaks (double newlines) in translated spreadsheet text

= 3.5.0 =
* FIX: Handle WordPress wptexturize() smart quotes in translation matching
* TranslatePress detects rendered text with smart quotes (&#8221;) but translations were stored with straight quotes (")
* Now updates existing auto-detected dictionary entries and inserts texturized variants
* Also handles HTML-encoded texturized versions for full coverage

= 3.4.0 =
* Keep variation description translation meta for direct theme lookup
* Prevents cleanup of `_trp_variation_desc_[lang]` meta so themes can access it directly

= 3.3.0 =
* FIX: Attribute translation for variations now correctly matches HTML entities
* Improved variation-to-parent product attribute resolution

= 3.1.0 =
* NEW: Variation description translation support (`_trp_variation_desc_[lang]`)
* NEW: Documentation for configuring variation descriptions in WP All Import
* Updated admin notice with variation description fields

= 3.0.0 =
* NEW: Full support for WooCommerce variable products and variations
* NEW: Automatic parent product detection for variations
* NEW: Collapsible attribute field list in admin notice
* NEW: Support for `pa_` prefix in attribute meta keys
* FIX: Attribute translations now work correctly with variable products
* FIX: Terms are now retrieved from parent product for variations
* Force update mode: existing translations are always overwritten
* Removed debug logging for production use

= 2.3.2 =
* Added upgrade notice section
* Minor readme improvements

= 2.3.1 =
* Renamed plugin to "TranslatePress Import Sync" (WordPress.org compliance)
* Fixed text domain to match plugin slug
* Updated tested up to WordPress 6.9

= 2.3.0 =
* NEW: WooCommerce category name translations (`_trp_cat_[lang]`)
* NEW: WooCommerce category slug translations (`_trp_cat_slug_[lang]`) - requires SEO Pack
* NEW: WooCommerce attribute translations (`_trp_attr_[slug]_[lang]`)
* Improved admin notice with all available fields
* Support for both global (taxonomy) and local (custom) product attributes

= 2.2.0 =
* Line break conversion is now optional (disabled by default)
* Enable with custom field `_trp_convert_linebreaks` = `1` (deprecated in 3.7.0 - now automatic)
* Added instructions for "Keep line breaks from file" WP All Import setting

= 2.1.0 =
* Added automatic line break conversion
* Prevents TranslatePress from segmenting long descriptions into multiple strings

= 2.0.0 =
* Rewrote to use official TranslatePress Custom API
* Added support for multiple languages simultaneously
* Dynamic language detection from TranslatePress settings
* Improved admin notices with language-specific examples

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.13.0 =
Strip all paragraph breaks into one text block. Translation always matches regardless of paragraph count.

= 3.12.0 =
Per-paragraph matching (superseded by 3.13.0). Fixes French translations not appearing for multi-paragraph descriptions.

= 3.11.0 =
Full plugin dashboard with tabbed UI.

= 3.10.0 =
Admin log viewer under Tools.

= 3.9.0 =
Critical fix: content normalization now persists. Bypasses WooCommerce hooks that reverted changes. Adds safety-net normalization.

= 3.8.1 =
Fixes remaining old references. Variation descriptions and excerpts now fully normalized.

= 3.8.0 =
Bulletproof content flattening. Handles <p> tags, <div> wrappers, Gutenberg blocks, and all mixed formats.

= 3.7.0 =
Automatic paragraph normalization. `_trp_convert_linebreaks` field is no longer needed - remove it from your import.

= 3.5.0 =
Fixes smart quote mismatch between imported translations and TranslatePress frontend detection.

= 3.4.0 =
Keeps variation description translation meta for direct theme lookup.

= 3.3.0 =
Fixes attribute translation matching for variations with HTML entities.

= 3.1.0 =
Adds variation description translation support for WooCommerce product variations.

= 3.0.0 =
Major update with full WooCommerce variable products support. Attribute translations now work correctly with variations.

= 2.3.2 =
Minor readme improvements.

= 2.3.1 =
Plugin renamed for WordPress.org compliance. No breaking changes.

= 2.3.0 =
Adds WooCommerce category and attribute translation support. No breaking changes.
