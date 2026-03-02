# Bylaw Clause Manager

WordPress plugin for managing hierarchical bylaws as individual clauses with filtering, tooltips, vote metadata, and recursive rendering.

**Version**: 3.1.0
**Requires PHP**: 7.4
**License**: GPL-2.0-or-later

## Installation

1. Copy `bylaw-clause-manager/` into `/wp-content/plugins/`
2. Activate in WordPress admin
3. Configure Bylaw Groups under **Bylaw Clauses > Bylaw Groups**

## Usage

Render bylaws with the shortcode:

```
[render_bylaws group="character"]
```

Clauses nest automatically based on parent-child relationships. Filtering uses Select2 dropdowns built from clause tags. Clauses tagged `always` remain visible regardless of filters.

## Changelog

### 3.1.0

- Renamed WPPLUGINNAME_* constants to BCM_*
- Stripped comment bloat and redundant PHPDoc

### 3.0.0

- Elementor widget with advanced controls

### 2.3.9

- Fixed TranslatePress compatibility with section numbers and content filters

### 2.3.6

- Fixed save latency

### 2.3.0

- Transition to content filters

## Contributing

[github.com/One-World-By-Night/bylaw-clause-manager](https://github.com/One-World-By-Night/bylaw-clause-manager)
