# Bylaw Clause Manager

Manages and renders OWBN's organizational bylaws as structured, filterable, nested clauses.

Version: 3.1.0
Deployed to: council.owbn.net (network-activated)

## What It Does

OWBN's bylaws are broken into individual clauses stored as custom post types with parent-child nesting. The plugin renders them as a complete document with automatic section numbering, collapsible hierarchy, and tag-based filtering via Select2 dropdowns.

Key features:
- Bylaw Groups organize clauses into separate documents (e.g., Character Bylaws, Administrative Bylaws)
- Shortcode rendering -- [render_bylaws group="character"] outputs the full nested document
- Filtering -- readers filter by tag; clauses tagged "always" stay visible regardless
- Tooltips -- hover definitions for bylaw terms
- Vote metadata -- tracks which council vote adopted or amended each clause
- Elementor widget -- drop-in widget with advanced display controls
- TranslatePress compatible -- section numbers and content survive translation filters

## Requirements

- WordPress 5.0+, PHP 7.4+

## License

GPL-2.0-or-later
