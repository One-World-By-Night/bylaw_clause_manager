document.addEventListener('DOMContentLoaded', () => {
  const clauses = document.querySelectorAll('.bylaw-clause');
  const tagSet = new Set();

  // Extract all tags
  clauses.forEach(clause => {
    clause.classList.forEach(cls => {
      if (cls !== 'bylaw-clause') tagSet.add(cls);
    });
  });

  // Build select
  const select = document.createElement('select');
  select.multiple = true;
  select.id = 'bcm-tag-filter';
  select.style.margin = '1em 0';
  select.style.display = 'block';

  tagSet.forEach(tag => {
    const option = document.createElement('option');
    option.value = tag;
    option.textContent = tag.charAt(0).toUpperCase() + tag.slice(1);
    select.appendChild(option);
  });

  const container = document.querySelector('.bylaw-clause')?.parentElement;
  if (container) container.prepend(select);

  // Main filtering logic
  select.addEventListener('change', () => {
    const selectedTags = Array.from(select.selectedOptions).map(opt => opt.value);
    const clausesById = {};
    const showIds = new Set();

    // Index all clauses by ID
    clauses.forEach(clause => {
      const id = clause.dataset.id;
      clausesById[id] = clause;

      const classes = Array.from(clause.classList);
      const tags = classes.filter(cls => cls !== 'bylaw-clause');

      const hasAlways = tags.includes('always');
      const matchesFilter = selectedTags.some(tag => tags.includes(tag));

      if (hasAlways || matchesFilter) {
        showIds.add(id);

        // Walk up parents and include them
        let current = clause;
        while (current && current.dataset.parent && current.dataset.parent !== '0') {
          const parentId = current.dataset.parent;
          showIds.add(parentId);
          current = clausesById[parentId];
        }
      }
    });

    // Apply visibility
    clauses.forEach(clause => {
      clause.style.display = showIds.has(clause.dataset.id) ? 'block' : 'none';
    });
  });
});