=== Bylaw Clause Manager ===
Contributors: owbnwebcoord, greghacke
Tags: bylaws, nested content, legal clauses, acf, custom post types
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.25
Requires PHP: 7.4
 License: GPL-2.0-or-later
 License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage and display nested bylaws or hierarchical clauses with ACF support, filtering, tooltips, and Select2-enhanced editing.

== Description ==
**Bylaw Clause Manager** is a custom WordPress plugin that allows organizations like OWbN to manage a complex, hierarchical set of bylaws in a structured, modular, and trackable way. Each clause is independently editable, tagged for filtering, version-controlled, and rendered in a readable nested format — just like a legal document, but with modern digital accessibility.

This plugin is purpose-built for domains where clauses need to be tracked individually, filtered by thematic relevance (e.g., "Anarch", "Caitiff"), and presented with full contextual hierarchy for readability and transparency.

Note, this plugin relies on the free Advanced Custom Fields plugin, available through the WordPress Plugins directory or directly at www.advancedcustomfields.com

Licensed under GNU/GPL v2.0
---

# How It Works

### 1. Custom Post Type: `bylaw_clause`
Each bylaw clause is a WordPress post with structured ACF fields:
- **Section ID** (`3`)
- **Post Title** (recommended: use a machine-readable version of the Section ID, e.g., `2_g_i_3`)
- **Content** (rich text body of the clause)
- **Parent Clause** (for nesting and hierarchy) (2.g.i)
- **Sort Order** (inferred from Section ID, used for display)
- **Tags** (e.g., `anarch`, `caitiff`, `always`)
- **Vote Date** (e.g., *March 10, 2024*)
- **Vote Reference** (e.g., *1000001*)
- **Vote URL** (optional link to vote record or minutes)
- **Bylaw Group** (e.g., `character`, `coordinator`, `council`) to segment clauses by context

---

### 2. Recursive Rendering
A shortcode `[render_bylaws group="character"]` renders clauses by group:
- Automatically nests based on parent-child relationships.
- Indents for visual clarity.
- Outputs metadata (`data-id`, `data-parent`) for client-side filtering.
- Adds a tooltip if vote metadata is present.
- Anchors each clause using its Section ID (`#clause-2-g-i-3`).

---

### 3. Vote Metadata Display (Hover Tooltip)
- A tooltip appears next to a clause if vote information is set.
- Tooltip can include:
  - **Vote Date**
  - **Reference Number**
  - A **“View Details”** link if a Vote URL is specified
- Example tooltip:
  `Vote Date: March 10, 2024 | Reference: 1000001 | View Details`

---

### 4. Tag-Based Filtering with Select2
Above the clause tree is a dynamic multi-select tag filter:
- Uses Select2 for a searchable dropdown of all tags in use
- Enables filtering by terms like `anarch`, `caitiff`, etc.
- Includes a **“Clear Filters”** button
- Ensures:
  - Matched clauses are shown
  - All ancestor clauses are shown to preserve context
  - Clauses tagged `always` are always visible

---

### 5. Print / Export Support
- A **“Print / Export PDF”** button is available above the clause display
- Only visible clauses are printed/exported, matching any filters
- Great for printing thematic handbooks or filtered legal summaries

---

### 6. Enhanced Admin Experience
The admin dashboard for `Bylaw Clauses` includes:
- **Custom Columns**: Bylaw Group, Parent Clause
- **Sorting**: Bylaw Group and Parent Clause columns are sortable
- **Admin Filters**: Filter by Bylaw Group directly in the list view
- **Parent Clause Selection**:
  - Uses Select2-enhanced dropdowns
  - Displays both title and a content preview
  - Filtered by group to avoid cross-context nesting
- **Quick Edit**:
  - Inline editing of Bylaw Group and Parent Clause
  - Parent dropdown shows section title and short content preview
  - Respects dynamic field options from ACF

---

# Real Example: OWbN Character Bylaws

From https://www.owbn.net/bylaws/character, we can model this clause structure:
2.	Character Creation
  g. Vampire Characters must have a clearly defined Sect…
    i. Anarch (Anarch Coordinator Controlled)
      3. Caitiff

### In the Plugin:

| Section ID | Post Title | Content Preview                          | Tags             | Parent    |
|------------|------------|------------------------------------------|------------------|-----------|
| `2`        | `2`        | Character Creation                       | always           | *none*    |
| `g`        | `2_g`      | Vampire Characters must have...          |                  | `2`       |
| `i`        | `2_g_i`    | Anarch (Anarch Coordinator Controlled)   | anarch           | `2.g`     |
| `3`        | `2_g_i_3`  | Caitiff                                  | anarch,caitiff   | `2.g.i`   |

**Recommended**: Set the WordPress post title to match the machine-readable version of the Section ID, e.g., `2_g_i_3`, for internal consistency and debugging ease.

### Filtering Behavior:
- Selecting the **Caitiff** tag reveals:
  - `2` → `2.g` → `2.g.i` → `3`
- All ancestor clauses are shown, even if they aren’t tagged
- Any clause tagged `always` is visible regardless of filters
- “Clear Filters” returns the full hierarchy to view