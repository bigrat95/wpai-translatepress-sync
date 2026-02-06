=== TranslatePress Import Sync ===
Contributors: olivierbigras
Tags: translatepress, wp all import, translation, multilingual, woocommerce
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sync translations from WP All Import to TranslatePress using the official Custom API.

== Description ==

**TranslatePress Import Sync** enables you to import multilingual content directly into TranslatePress when using WP All Import. Perfect for bulk importing products, posts, or any content with translations.

= Features =

* **Title, Content & Excerpt** - Import translated titles, descriptions, and excerpts
* **WooCommerce Categories** - Import translated category names and slugs
* **WooCommerce Attributes** - Import translated attribute values (colors, sizes, etc.)
* **Multiple Languages** - Import all your languages simultaneously
* **Human Reviewed Status** - Translations are marked as "Human Reviewed" (status 2) so auto-translation won't overwrite them
* **Line Break Conversion** - Optional conversion of line breaks to `<br>` tags

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

= 2.3.0 =
* NEW: WooCommerce category name translations (`_trp_cat_[lang]`)
* NEW: WooCommerce category slug translations (`_trp_cat_slug_[lang]`) - requires SEO Pack
* NEW: WooCommerce attribute translations (`_trp_attr_[slug]_[lang]`)
* Improved admin notice with all available fields
* Support for both global (taxonomy) and local (custom) product attributes

= 2.2.0 =
* Line break conversion is now optional (disabled by default)
* Enable with custom field `_trp_convert_linebreaks` = `1`
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

= 2.3.0 =
Adds WooCommerce category and attribute translation support. No breaking changes.
