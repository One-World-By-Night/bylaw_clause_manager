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

// QUICK EDIT FIELD POPULATION
document.addEventListener('DOMContentLoaded', () => {
  function initSelect2QuickEdit() {
    jQuery('.bcm-select2').each(function () {
      if (jQuery.fn.select2) {
        if (jQuery(this).hasClass('select2-hidden-accessible')) {
          jQuery(this).select2('destroy');
        }
        jQuery(this).select2({ width: '100%' });
      }
    });
  }

  function populateQuickEditFields(postId) {
    const row = document.getElementById(`post-${postId}`);
    const dataDiv = row?.querySelector('.bcm-quickedit-data');

    if (!dataDiv) return;

    const group = dataDiv.dataset.bcmGroup || '';
    const parent = dataDiv.dataset.bcmParent || '';
    const tags = dataDiv.dataset.bcmTags || '';

    const $ = jQuery;
    $('select[name="bcm_qe_bylaw_group"]').val(group);
    $('select[name="bcm_qe_parent_clause"]').val(parent).trigger('change');
    $('input[name="bcm_qe_tags"]').val(tags);
  }

  // On Quick Edit click
  jQuery(document).on('click', '.editinline', function () {
    const postId = jQuery(this).closest('tr').attr('id').replace('post-', '');
    setTimeout(() => {
      initSelect2QuickEdit();
      populateQuickEditFields(postId);
    }, 100);
  });

  // On AJAX save (optional re-init)
  jQuery(document).ajaxSuccess((e, xhr, settings) => {
    if (settings.data && settings.data.includes('action=inline-save')) {
      setTimeout(initSelect2QuickEdit, 200);
    }
  });
});