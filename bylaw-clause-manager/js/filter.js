document.addEventListener('DOMContentLoaded', () => {
  const clauses = document.querySelectorAll('.bylaw-clause');
  const tagSet = new Set();

  // Extract tags from class names
  clauses.forEach(clause => {
    clause.classList.forEach(cls => {
      if (cls !== 'bylaw-clause') tagSet.add(cls);
    });
  });

  // Populate Select2 dropdown
  const select = document.getElementById('bcm-tag-select');
  tagSet.forEach(tag => {
    const option = document.createElement('option');
    option.value = tag;
    option.textContent = tag.charAt(0).toUpperCase() + tag.slice(1);
    select.appendChild(option);
  });

  // Initialize Select2
  jQuery(select).select2({
    placeholder: 'Filter by tag',
    width: 'resolve'
  });

  // Core filter logic
  function applyFilter(selected) {
    const showIds = new Set();
    const clausesById = {};

    clauses.forEach(clause => {
      const id = clause.dataset.id;
      clausesById[id] = clause;

      const classes = Array.from(clause.classList);
      const tags = classes.filter(cls => cls !== 'bylaw-clause');

      const hasAlways = tags.includes('always');
      const matchesFilter = selected.some(tag => tags.includes(tag));

      if (hasAlways || matchesFilter || selected.length === 0) {
        showIds.add(id);
        let current = clause;
        while (current && current.dataset.parent && current.dataset.parent !== '0') {
          const parentId = current.dataset.parent;
          showIds.add(parentId);
          current = clausesById[parentId];
        }
      }
    });

    clauses.forEach(clause => {
      clause.style.display = showIds.has(clause.dataset.id) ? 'block' : 'none';
    });
  }

  // Change handler for tag selection
  jQuery(select).on('change', () => {
    const selected = jQuery(select).val() || [];
    applyFilter(selected);
  });
});

// Reusable Clear Filters function (linked to the button via onclick)
function bcmClearFilters() {
  const select = document.getElementById('bcm-tag-select');
  if (select && jQuery(select).select2) {
    jQuery(select).val(null).trigger('change');
  }
}