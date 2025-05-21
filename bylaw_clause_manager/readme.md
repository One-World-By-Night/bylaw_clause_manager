# Summary

**Bylaw Clause Manager** is a custom WordPress plugin that allows organizations like OWbN to manage a complex, hierarchical set of bylaws in a structured, modular, and trackable way. Each clause is independently editable, tagged for filtering, version-controlled, and rendered in a readable nested format — just like a legal document, but with modern digital accessibility.

This plugin is purpose-built for domains where clauses need to be tracked individually, filtered by thematic relevance (e.g., "Anarch", "Caitiff"), and presented with full contextual hierarchy for readability and transparency.

Note, this plugin relies on the free Advanced Custom Fields plugin, available through the Wordpress Plugins or directly at [www.advancedcustomfields.com](https://www.advancedcustomfields.com/)

---

# How It Works

### 1. **Custom Post Type: `bylaw_clause`**
Each bylaw clause is a WordPress post with fields:
- **Section ID** (`2.g.i.3`)
- **Label** (e.g., *Caitiff*)
- **Content** (rich text)
- **Parent Clause** (to establish hierarchy)
- **Sort Order**
- **Tags** (e.g., `anarch`, `caitiff`, `always`)
- **Vote Date** (e.g., *March 10, 2024*)
- **Vote Reference** (e.g., *1000001*)
- **Bylaw Group** (e.g., `character`, `coordinator`, `council`) for filtering different sources

---

### 2. **Recursive Rendering**
A shortcode `[render_bylaws group="character"]` renders clauses by group:
- Automatically nests based on parent-child relationships.
- Indents for visual structure.
- Outputs metadata (`data-id`, `data-parent`) for JavaScript filtering.
- Adds a tooltip (`title` attribute) if vote metadata is present.
- Generates anchored links using section IDs (e.g., `#clause-2-g-i-3`).

---

### 3. **Vote Metadata Display (Hover Tooltip)**
- If either **vote date** or **vote reference** exists for a clause, the plugin adds a tooltip to the clause block.
- Tooltip text includes:
  - `Vote Date: <date>` (if set)
  - `Reference: <reference>` (if set)
- If **both fields are empty**, no tooltip is shown — avoiding unnecessary clutter or blank hovers.
- Example tooltip:  
  _Vote Date: March 10, 2024 | Reference: 1000001_

---

### 4. **Tag-Based Filtering with Select2**
A Select2-powered multi-select dropdown dynamically loads all tags used across clauses:
- Allows users to filter by terms like `anarch`, `caitiff`, etc.
- Includes a **“Clear Filters” button** to reset the dropdown and reveal all clauses.
- Ensures that:
  - **Matched clauses** are shown.
  - **All ancestor clauses** are also shown for readability.
  - **Clauses tagged `always`** are **always visible**, even when filters are active.

---

### 5. **Print / Export Support**
- A **“Print / Export PDF” button** is included above the clause tree.
- When clicked, it prints only the currently visible clauses (honoring active filters).
- Useful for exporting filtered views of specific sections like “Caitiff-only” rules or a Coordinator-specific handbook.

---

### 6. **Enhanced Admin Experience**
The WordPress admin interface for `Bylaw Clauses` includes:
- **Custom columns**: `Bylaw Group`, `Parent Clause`, and sortable `Date`
- **Sortable columns**: Bylaw Group and Parent Clause
- **Admin filtering dropdown**: Filter clauses by `Bylaw Group` in the dashboard
- **Improved display**: Parent dropdowns show both the title and section ID

---

# Real Example: OWbN Character Bylaws

From [https://www.owbn.net/bylaws/character](https://www.owbn.net/bylaws/character), we can model this clause structure:

```
2. Character Creation  
  g. Vampire Characters must have a clearly defined Sect...  
    i. Anarch (Anarch Coordinator Controlled)  
      3. Caitiff  
```

### In the Plugin:

| Section ID | Label                                         | Tags               | Parent      |
|------------|-----------------------------------------------|--------------------|-------------|
| `2`        | Character Creation                            | `always`           | *none*      |
| `2.g`      | Vampire Characters must have a clearly...     |                    | `2`         |
| `2.g.i`    | Anarch (Anarch Coordinator Controlled)        | `anarch`           | `2.g`       |
| `2.g.i.3`  | Caitiff                                       | `anarch,caitiff`   | `2.g.i`     |

### Filtering Behavior:
- If the user selects **“Caitiff”**, the output will show:
  - `2` → `2.g` → `2.g.i` → `2.g.i.3`
- Even though only the final item is tagged `caitiff`, its ancestors are shown.
- Any item tagged `always` (like `2`) is shown regardless of filter.
- Clicking “Clear Filters” returns all clauses to view.