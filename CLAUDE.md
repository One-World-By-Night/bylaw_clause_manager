# CLAUDE.md - Bylaw Clause Manager

## Project Overview

WordPress plugin for managing hierarchical bylaws/legal clauses. Built for One World By Light (OWbN) organization.

- **Type**: WordPress Custom Post Type Plugin
- **Language**: PHP 7.4+ (backend) + JavaScript (frontend)
- **WordPress**: 6.0+
- **Version**: 2.2.4
- **Text Domain**: `bylaw-clause-manager`

## Quick Reference

### Directory Structure

```
bylaw-clause-manager/
├── bylaw-clause-manager.php          # Main plugin entry point
├── readme.txt                         # WordPress.org metadata
└── includes/
    ├── init.php                       # Bootstrap loader
    ├── core/                          # Constants, authorization
    ├── admin/                         # CPT, metabox, settings, save handlers
    ├── hooks/                         # Filters, REST API, webhooks
    ├── render/                        # Tree rendering, admin columns
    ├── shortcodes/                    # [render_bylaws] shortcode
    ├── assets/css/, assets/js/        # Frontend assets
    ├── helper/                        # Utility functions
    ├── tools/                         # Sorting, parent fixing utilities
    └── templates/, languages/, tests/ # Supporting modules
```

### Key Files

| Purpose | File |
|---------|------|
| Plugin entry | `bylaw-clause-manager.php` |
| CPT registration | `includes/admin/cpt.php` |
| Metabox UI | `includes/admin/metabox.php` |
| Save handlers | `includes/admin/save.php` |
| Tree rendering | `includes/render/listing.php` |
| Shortcode | `includes/shortcodes/listing.php` |
| Frontend JS | `includes/assets/js/filter.js` |
| Frontend CSS | `includes/assets/css/style.css` |
| Admin settings | `includes/admin/settings.php` |

## Development Commands

No build system - plugin is ready to use directly. Manual version bumps required in:
- `bylaw-clause-manager.php` (header)
- `includes/core/bootstrap.php` (constant)
- `readme.txt` (metadata)

## Custom Post Type: `bylaw_clause`

### Meta Fields
- `bylaw_group` - Category (character, council, coordinator, etc.)
- `parent_clause` - Post ID of parent for hierarchy
- `section_id` - Display ID (e.g., "2.g.i.3")
- `tags` - Comma-separated filter tags
- `vote_date`, `vote_reference`, `vote_url` - Vote metadata

### Shortcode Usage
```
[render_bylaws group="character"]
```

## Code Conventions

### Security (always follow)
- Nonce verification: `wp_verify_nonce()`
- Capability checks: `current_user_can('edit_post', $post_id)`
- Sanitize input: `sanitize_text_field()`, `sanitize_key()`, `esc_url_raw()`
- Escape output: `esc_html()`, `esc_attr()`, `wp_kses_post()`
- AJAX validation: `check_ajax_referer()`

### Naming
- Functions prefixed with `bcm_`
- Meta keys prefixed with `bylaw_` or descriptive names
- Nonce actions: `bcm_clause_meta_save`, `bcm_qe_save`

### i18n
Use translation functions for user-facing strings:
```php
__('Text', 'bylaw-clause-manager')
esc_html__('Text', 'bylaw-clause-manager')
```

## Key Functions

```php
bcm_render_bylaw_tree($parent_id, $depth, $group)  // Recursive tree renderer
bcm_get_bylaw_groups()                              // Get configured groups
bcm_title_sort_key($title)                          // Generate sort key
bcm_fix_clause_parents()                            // Auto-fix parent relationships
bcm_generate_vote_tooltip($clause_id)               // Vote metadata tooltip
```

## Dependencies

- **Select2 4.1.0** - Enhanced dropdowns (included in `/assets/`)
- **jQuery** - WordPress native
- No Composer/npm dependencies

## Testing

Minimal test structure in `includes/tests/`. No PHPUnit configured.

## Common Tasks

### Adding features
1. Identify relevant module in `/includes/`
2. Follow existing patterns for hooks/filters
3. Use proper security practices
4. Update version numbers if releasing

### Modifying admin UI
- Metabox: `includes/admin/metabox.php`
- Columns: `includes/render/admin.php`
- Settings: `includes/admin/settings.php`

### Modifying frontend
- Rendering: `includes/render/listing.php`
- Shortcode: `includes/shortcodes/listing.php`
- JS filtering: `includes/assets/js/filter.js`
- Styles: `includes/assets/css/style.css`
