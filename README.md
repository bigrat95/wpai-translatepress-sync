# TranslatePress Import Sync

Automatically sync translations from WP All Import to TranslatePress using the official TranslatePress Custom API.

**Author:** Olivier Bigras  
**Website:** [olivierbigras.com](https://olivierbigras.com)  
**Email:** oli@olivierbigras.com  
**Version:** 3.8.1  
**License:** GPL v2 or later

---

## Requirements

- WordPress 5.0+
- [WP All Import Pro](https://www.wpallimport.com/)
- [TranslatePress Multilingual](https://translatepress.com/)
- [TranslatePress Custom API](https://translatepress.com/) (provided by TranslatePress support)

---

## Installation

1. Download or copy the `wpai-translatepress-sync` folder
2. Upload to `wp-content/plugins/` 
3. Activate the plugin in WordPress → Plugins
4. Ensure TranslatePress and TranslatePress Custom API are also active

---

## How It Works

When you run a WP All Import, the plugin:

1. **Flattens content** into a single block (strips `<p>`, `<div>` tags, converts newlines to `<br>` tags)
2. Captures translated content from special custom fields you map
3. Handles smart quote mismatches (WordPress `wptexturize()` conversions)
4. Uses the official TranslatePress Custom API to insert translations
5. Marks translations as "Human Reviewed" (status 2)
6. Updates the post content in the database to ensure TranslatePress detects one string per description
7. Cleans up the temporary custom fields after import

---

## WP All Import Configuration

### Step 1: Map Your Default Language Fields

Map your CSV/XML fields to the standard WordPress fields:

| WordPress Field | Your CSV Column |
|-----------------|-----------------|
| Title | English title |
| Content | English description |
| Excerpt | English short description |

### Step 2: Add Translation Custom Fields

In the **Custom Fields** section of your import, add fields using this naming pattern:

```
_trp_title_[LANGUAGE_CODE]
_trp_content_[LANGUAGE_CODE]
_trp_excerpt_[LANGUAGE_CODE]
```

Where `[LANGUAGE_CODE]` matches your TranslatePress configuration (e.g., `fr_CA`, `fr_FR`, `es_ES`, `de_DE`).

### Examples

For French Canadian (`fr_CA`):

| Custom Field Name | CSV Column |
|-------------------|------------|
| `_trp_title_fr_CA` | French title column |
| `_trp_content_fr_CA` | French description column |
| `_trp_excerpt_fr_CA` | French short description (optional) |

For Spanish (`es_ES`):

| Custom Field Name | CSV Column |
|-------------------|------------|
| `_trp_title_es_ES` | Spanish title column |
| `_trp_content_es_ES` | Spanish description column |

---

## Supported Fields

### Post/Product Fields

| Custom Field Prefix | Maps To |
|---------------------|---------|
| `_trp_title_` | Post title |
| `_trp_content_` | Post content (description) |
| `_trp_excerpt_` | Post excerpt (short description) |

### WooCommerce Category Fields

| Custom Field | Description |
|--------------|-------------|
| `_trp_cat_[lang]` | Translated category names (pipe-separated for multiple) |
| `_trp_cat_slug_[lang]` | Translated category slugs (requires SEO Pack) |

**Example:** `_trp_cat_fr_CA` = "Chaises\|Tables\|Bureaux"

### WooCommerce Variation Description Fields

| Custom Field | Description |
|--------------|-------------|
| `_variation_description` | Default language variation description (standard WooCommerce field) |
| `_trp_variation_desc_[lang]` | Translated variation description |

**Example:** `_trp_variation_desc_fr_CA` = "Description de la variation en français"

**Note:** This field is for product variations only (not the main product). Map `_variation_description` for the default language in WP All Import's Variations tab or Custom Fields section.

### WooCommerce Attribute Fields

| Custom Field | Description |
|--------------|-------------|
| `_trp_attr_[slug]_[lang]` | Translated attribute values (pipe-separated) |

**Examples:**
- `_trp_attr_color_fr_CA` = "Rouge\|Bleu\|Vert"
- `_trp_attr_size_fr_CA` = "Petit\|Moyen\|Grand"

**Note:** Works with both global attributes (taxonomy-based like `pa_color`) and local/custom product attributes.

---

## WooCommerce Variable Products

The plugin fully supports WooCommerce variable products imported with WP All Import.

### Supported Import Structures

**Option 1: Variations grouped by unique value** (Recommended)
- Use "All products with variations are grouped with a unique value that is the same for each variation and unique for each product"
- Each CSV row represents one variation
- Set `Unique Value` and `Parent SKU` to your parent SKU column
- Map individual variation SKUs in Inventory → SKU

**Option 2: Variations linked to parent SKU**
- Use "All my variable products have SKUs or some other unique identifier"
- Each variation is linked to its parent

### How It Works

1. When importing variations, the plugin automatically detects the parent product
2. Attribute terms are retrieved from the parent product
3. Translations are applied to the attribute terms in TranslatePress
4. Both simple products and variable products are supported

### Example CSV Structure

| SKU WEB | SKU WEB - PARENT | MAGNETIC - EN | MAGNETIC - FR |
|---------|------------------|---------------|---------------|
| BDC3232WT | BDC-R, BDC-L | Magnetic Drain Cover | Cache-drain magnétique |
| BDC3636WT | BDC-R, BDC-L | Magnetic Drain Cover | Cache-drain magnétique |

### Custom Fields Mapping

```
_trp_attr_magnetic_fr_CA = {magneticfr[1]}
```

The plugin will:
1. Detect this is a product variation
2. Get the parent product
3. Find the `pa_magnetic` attribute on the parent
4. Translate "Magnetic Drain Cover" → "Cache-drain magnétique"

---

## Multiple Languages

You can import multiple languages simultaneously! Just add custom fields for each language:

```
_trp_title_fr_CA    → French Canadian title
_trp_title_es_ES    → Spanish title
_trp_content_fr_CA  → French Canadian content
_trp_content_es_ES  → Spanish content
```

The plugin automatically detects all configured languages from TranslatePress.

---

## Automatic Content Normalization

TranslatePress detects each `<p>` block as a separate translatable string. Without normalization, a 3-paragraph description would require 3 separate dictionary entries — making translation matching unreliable.

The plugin **automatically flattens all content** into a single block. No extra configuration needed.

### What It Handles

| Content Format | Action |
|---|---|
| `\n\n` (double newlines from CSV) | Converted to `<br><br>` |
| `\n` (single newlines) | Converted to `<br>` |
| `<p>...</p>` tags (from editors) | Stripped, replaced with `<br><br>` |
| `<div>...</div>` wrappers | Stripped, replaced with `<br><br>` |
| `<!-- wp:paragraph -->` (Gutenberg) | Stripped |
| `<br/>`, `<br />` variants | Normalized to `<br>` |
| Multiple `&nbsp;` spacers | Collapsed to `<br><br>` |
| Mixed combinations | All handled |

### What It Preserves

- `<strong>`, `<b>` — bold text
- `<em>`, `<i>` — italic text
- `<a href="...">` — links
- `<span>` — inline styling
- `<ul>`, `<ol>`, `<li>` — lists
- All other inline HTML tags

### Result

- Your entire description is always ONE string in TranslatePress
- Visual paragraph spacing is preserved with `<br>` tags
- English and French translations match 1:1
- Works with content, excerpts, and variation descriptions

### Example

CSV input (or content with `<p>` tags, `<div>` wrappers, etc.):
```
Paragraph 1...

Paragraph 2...

Paragraph 3...
```

Saved in WordPress as:
```html
Paragraph 1...<br><br>Paragraph 2...<br><br>Paragraph 3...
```

> **Note:** The `_trp_convert_linebreaks` custom field from v2.x is no longer needed. Normalization always happens automatically.

---

## Admin Notice

When you're on a WP All Import page, you'll see a helpful notice showing:
- The custom field names to use
- Which languages are configured in TranslatePress

---

## Helper Functions

Use these functions in your theme or other plugins:

### Add a single translation

```php
wpai_trp_add_translation( 'Hello World', 'Bonjour le monde', 'fr_CA' );
```

### Bulk add translations

```php
$translations = array(
    'Hello' => 'Bonjour',
    'Goodbye' => 'Au revoir',
    'Welcome' => 'Bienvenue',
);

wpai_trp_bulk_add_translations( $translations, 'fr_CA' );
```

---

## TranslatePress Custom API Functions

This plugin uses the official TranslatePress Custom API:

```php
// Insert single translation
trpc_insert_translation( $original, $translated, $language, $args );

// Bulk insert
trpc_insert_translations_bulk( $translations, $language, $args );

// Get configured languages
trpc_get_languages();
// Returns: ['default' => 'en_CA', 'translations' => ['fr_CA'], 'all' => ['en_CA', 'fr_CA']]
```

---

## Debugging

Enable WordPress debug mode to see import logs:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check `wp-content/debug.log` for entries like:
```
[WPAI-TRP-Sync] Added 2 translations for post #123
```

---

## FAQ

### Q: What happens to existing translations?

**A:** They are updated/overwritten with the new translations from your import.

### Q: What status do imported translations get?

**A:** Status 2 (Human Reviewed), so they will **never** be overwritten by automatic translation. You can safely keep auto-translation enabled for other strings.

### Q: Can I keep automatic translation enabled?

**A:** Yes! Your imported translations are protected with status 2 (Human Reviewed). Automatic translation will only translate strings that don't have a human-reviewed translation.

### Q: Can I use this with WooCommerce products?

**A:** Yes! Full WooCommerce support including product categories and attributes.

### Q: How do I translate category names?

**A:** Add `_trp_cat_fr_CA` custom field with pipe-separated translated names matching the order of your original categories.

### Q: How do I translate product attributes?

**A:** Use `_trp_attr_[attribute-slug]_[lang]` format. For a "color" attribute: `_trp_attr_color_fr_CA` = "Rouge|Bleu"

### Q: Do I need to keep the temporary custom fields?

**A:** No, they are automatically deleted after the translation is synced.

---

## Changelog

### 3.8.1
- **FIX:** Variation description translations now always normalized
- **FIX:** Original variation description also normalized before dictionary insertion
- **FIX:** post_excerpt normalization now also updates the DB
- Removed last remaining `_trp_convert_linebreaks` reference

### 3.8.0
- Bulletproof content flattening: handles ALL content formats, never skips
- Strips `<p>`, `</p>`, `<div>`, `</div>` tags and replaces with `<br><br>`
- Strips Gutenberg block comments (`<!-- wp:paragraph -->`)
- Normalizes all `<br>` variants (`<br>`, `<br/>`, `<br />`)
- Collapses excessive `<br>` tags and `&nbsp;` spacers
- Handles mixed content: `<p>` tags + newlines + `<br>` + `<div>` in any combination
- Cleans up leading/trailing `<br>` tags

### 3.7.0
- Auto-normalize paragraph breaks in both original and translated content
- Updates post_content in DB automatically when paragraph breaks are found
- `_trp_convert_linebreaks` custom field is no longer required

### 3.5.0
- **FIX:** Handle WordPress `wptexturize()` smart quotes in translation matching
- Inserts translations for raw, HTML-encoded, and smart-quoted string variants
- Updates existing auto-detected dictionary entries

### 3.4.0
- Keep variation description translation meta for direct theme lookup

### 3.3.0
- **FIX:** Attribute translation for variations now correctly matches HTML entities

### 3.1.0
- **NEW:** Variation description translation support (`_trp_variation_desc_[lang]`)

### 3.0.0
- **NEW:** Full support for WooCommerce variable products and variations
- **NEW:** Automatic parent product detection for variations
- Force update mode: existing translations are always overwritten

### 2.3.0
- **NEW:** WooCommerce category and attribute translation support

### 2.0.0
- Rewrote to use official TranslatePress Custom API

### 1.0.0
- Initial release

---

## Support

For issues or questions:
- **Email:** oli@olivierbigras.com
- **Website:** [olivierbigras.com](https://olivierbigras.com)
