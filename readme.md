# Executive Summary

**Bylaw Clause Manager** is a custom WordPress plugin that allows organizations like OWbN to manage a complex, hierarchical set of bylaws in a structured, modular, and trackable way. Each clause is independently editable, tagged for filtering, version-controlled, and rendered in a readable nested format — just like a legal document, but with modern digital accessibility.

This plugin is purpose-built for domains where clauses need to be tracked individually, filtered by thematic relevance (e.g., "Anarch", "Caitiff"), and presented with full contextual hierarchy for readability and transparency.

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

### 2. **Recursive Rendering**
A shortcode `[render_bylaws]` renders all clauses recursively:
- Automatically nests based on parent-child relationships.
- Indents for visual structure.
- Outputs metadata (`data-id`, `data-parent`) for JS filtering.

### 3. **Tag-Based Filtering**
A JavaScript-enhanced multi-select dropdown:
- Auto-generates from tags used on the page.
- Allows users to filter by tags like `anarch`, `caitiff`.
- Ensures that:
  - **Matched clauses** are shown.
  - **All ancestor clauses** are also shown for context.
  - **Clauses tagged `always`** are **always visible**.

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