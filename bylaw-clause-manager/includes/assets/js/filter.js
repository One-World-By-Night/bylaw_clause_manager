// ── Dynamic Group Row Handling for Bylaw Group Settings Page ──
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('bcm-group-table');
  const addBtn = document.getElementById('bcm-add-group');

  if (addBtn && table) {
    addBtn.addEventListener('click', () => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><input type="text" name="bcm_groups_keys[]" value="" placeholder="e.g. character" required></td>
        <td><input type="text" name="bcm_groups_labels[]" value="" placeholder="e.g. Character" required></td>
        <td><button type="button" class="bcm-remove-group">Remove</button></td>
      `;
      table.querySelector('tbody').appendChild(row);
    });

    table.addEventListener('click', (e) => {
      if (e.target.classList.contains('bcm-remove-group')) {
        e.target.closest('tr').remove();
      }
    });
  }
});

// ── Quick Edit Handler for Bylaw Clause Custom Fields ──
function bcmPrefixMatcher(params, data) {
  if (!data || typeof data.text !== 'string') return null;
  if (!params || typeof params.term !== 'string') return data;

  const term = params.term.toLowerCase().trim();
  const text = data.text.toLowerCase().trim();

  // Only match the clause title before the first " – "
  const titlePart = text.split('–')[0].trim(); // e.g. "1_b_i"

  return titlePart.startsWith(term) ? data : null;
}

jQuery(function ($) {
  function populateQuickEditFields(postId) {
    const $dataDiv = $('.bcm-quickedit-data[data-id="' + postId + '"]');

    console.log('💡 Quick Edit Init');
    console.log('Post ID:', postId);
    console.log('Found data div?', $dataDiv.length);
    console.log('Data:', $dataDiv.data());

    const $editRow = $('#edit-' + postId);

    if (!$dataDiv.length || !$editRow.length) return;

    const group  = $dataDiv.data('bcm-group') || '';
    const tags   = $dataDiv.data('bcm-tags') || '';
    const parent = $dataDiv.data('bcm-parent') || '';

    console.log(`Quick Edit Values for post ${postId}:`, {
      group,
      tags,
      parent
    });

    // Set values
    $editRow.find('input[name="bcm_qe_tags"]').val(tags);
    
    // Set bylaw group FIRST
    const $groupSelect = $editRow.find('select[name="bcm_qe_bylaw_group"]');
    const $parentSelect = $editRow.find('select[name="bcm_qe_parent_clause"]');
    
    // Remove any existing change handlers to avoid duplicates
    $groupSelect.off('change.bcmfilter');
    
    // Add the change handler
    $groupSelect.on('change.bcmfilter', function() {
      const selectedGroup = $(this).val();
      
      console.log('Group changed to:', selectedGroup);
      
      // Hide all optgroups first
      $parentSelect.find('optgroup').hide();
      
      // Show only the matching group
      if (selectedGroup) {
        $parentSelect.find('optgroup[data-group="' + selectedGroup + '"]').show();
      }
      
      // Store current parent value
      const currentParent = $parentSelect.val();
      
      // Refresh Select2 if active
      if ($parentSelect.hasClass('select2-hidden-accessible')) {
        $parentSelect.select2('destroy');
      }
      
      // Re-init Select2 with filtered options
      $parentSelect.select2({ 
        width: '100%',
        placeholder: 'Select Parent Clause',
        allowClear: true
      });
      
      // Restore parent value if still valid
      if (currentParent && $parentSelect.find('option[value="' + currentParent + '"]:visible').length) {
        $parentSelect.val(currentParent).trigger('change');
      } else {
        $parentSelect.val('').trigger('change');
      }
    });

    // Set the group value and trigger change
    $groupSelect.val(group);
    $groupSelect.trigger('change.bcmfilter');
    
    // THEN set parent value after group filtering is done
    setTimeout(() => {
      if (parent && $parentSelect.find('option[value="' + parent + '"]').parent(':visible').length) {
        $parentSelect.val(parent).trigger('change');
      }
    }, 100);

    // Init Select2 on all selects
    $editRow.find('select').each(function () {
      if ($.fn.select2 && !$(this).hasClass('select2-hidden-accessible')) {
        $(this).select2({ width: '100%' });
      }
    });
  }

  $(document).on('click', 'button.editinline', function () {
    console.log('✅ editinline clicked');

    const $row = $(this).closest('tr');
    const postId = $row.attr('id')?.replace('post-', '');
    if (postId) {
      setTimeout(() => populateQuickEditFields(postId), 100);
    }
  });

  $(document).ajaxSuccess(function (e, xhr, settings) {
    if (settings.data && settings.data.includes('action=inline-save')) {
      setTimeout(() => {
        $('tr.inline-edit-row').each(function () {
          const postId = $(this).attr('id')?.replace('edit-', '');
          if (postId) populateQuickEditFields(postId);
        });
      }, 200);
    }
  });
  
  // ── Initialize Select2 on the metabox parent clause field ──
  setTimeout(() => {
    const $parent = $('#bcm_parent_clause');
    if ($parent.length && typeof $.fn.select2 === 'function') {
      $parent.select2({
        width: '100%',
        matcher: bcmPrefixMatcher,
        placeholder: 'Select Parent Clause',
        allowClear: true
      });
      console.log('✅ Select2 initialized on #bcm_parent_clause');
    }
  }, 100);
});

// ── Frontend Content Filtering for [render_bylaws] ──
document.addEventListener('DOMContentLoaded', () => {
  const filterInput = document.getElementById('bcm-content-filter');
  if (!filterInput) return;

  const clauses = document.querySelectorAll('.bylaw-clause');
  const clausesById = {};
  
  clauses.forEach(clause => {
    clausesById[clause.dataset.id] = clause;
  });

  function highlightText(element, searchTerm) {
    // Remove existing highlights
    element.querySelectorAll('.bcm-highlight').forEach(span => {
      const parent = span.parentNode;
      parent.replaceChild(document.createTextNode(span.textContent), span);
      parent.normalize();
    });

    if (!searchTerm) return;

    // Find all text nodes
    const walker = document.createTreeWalker(
      element,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: function(node) {
          // Skip empty nodes and nodes inside script/style
          if (!node.textContent.trim()) return NodeFilter.FILTER_REJECT;
          if (node.parentElement.tagName === 'SCRIPT' || 
              node.parentElement.tagName === 'STYLE') {
            return NodeFilter.FILTER_REJECT;
          }
          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    const textNodes = [];
    let node;
    while (node = walker.nextNode()) {
      textNodes.push(node);
    }

    // Highlight matches in text nodes
    textNodes.forEach(textNode => {
      const text = textNode.textContent;
      const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
      
      if (regex.test(text)) {
        const fragment = document.createDocumentFragment();
        let lastIndex = 0;
        let match;

        regex.lastIndex = 0;
        while ((match = regex.exec(text)) !== null) {
          // Add text before match
          if (match.index > lastIndex) {
            fragment.appendChild(
              document.createTextNode(text.slice(lastIndex, match.index))
            );
          }

          // Add highlighted match
          const span = document.createElement('span');
          span.className = 'bcm-highlight';
          span.textContent = match[1];
          fragment.appendChild(span);

          lastIndex = match.index + match[1].length;
        }

        // Add remaining text
        if (lastIndex < text.length) {
          fragment.appendChild(
            document.createTextNode(text.slice(lastIndex))
          );
        }

        textNode.parentNode.replaceChild(fragment, textNode);
      }
    });
  }

  function applyFilter() {
    const searchTerm = filterInput.value.toLowerCase().trim();
    const showIds = new Set();

    // First, clear all highlights if search is empty
    if (searchTerm === '') {
      clauses.forEach(clause => {
        const textElement = clause.querySelector('.bylaw-label-text');
        if (textElement) {
          highlightText(textElement, '');
        }
      });
    }

    clauses.forEach(clause => {
      const textElement = clause.querySelector('.bylaw-label-text');
      const content = (textElement ? textElement.textContent : clause.dataset.content || '').toLowerCase();

      if (searchTerm === '' || content.includes(searchTerm)) {
        showIds.add(clause.dataset.id);
        
        // Only highlight if there's a search term and it matches
        if (textElement && searchTerm && content.includes(searchTerm)) {
          highlightText(textElement, searchTerm);
        }
        
        // Show all ancestors
        let current = clause;
        while (current && current.dataset.parent && current.dataset.parent !== '0') {
          const parentId = current.dataset.parent;
          showIds.add(parentId);
          current = clausesById[parentId];
        }
      } else {
        // Remove highlights from non-matching elements
        if (textElement) {
          highlightText(textElement, '');
        }
      }
    });

    clauses.forEach(clause => {
      clause.style.display = showIds.has(clause.dataset.id) ? 'block' : 'none';
    });
  }

  // Trigger on Search button click
  const searchBtn = document.getElementById('bcm-content-search');
  if (searchBtn) {
    searchBtn.addEventListener('click', applyFilter);
  }

  // Trigger on Enter key press
  filterInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      applyFilter();
    }
  });
});

// ── Global Helper to Clear Filters ──
function bcmClearFilters() {
  const filterInput = document.getElementById('bcm-content-filter');
  if (filterInput) {
    filterInput.value = '';
  }
  // Trigger the search button click to apply empty filter
  const searchBtn = document.getElementById('bcm-content-search');
  if (searchBtn) {
    searchBtn.click();
  }
}

// --- Bylaw Group Settings (Add/Remove rows) ---
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('#bcm-group-table tbody');
    const addBtn = document.querySelector('#bcm-add-group');

    if (!table || !addBtn) return;

    addBtn.addEventListener('click', () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="bcm_groups_keys[]" value="" required></td>
            <td><input type="text" name="bcm_groups_labels[]" value="" required></td>
            <td><button type="button" class="bcm-remove-group">Remove</button></td>
        `;
        table.appendChild(row);
    });

    table.addEventListener('click', (e) => {
        if (e.target && e.target.matches('.bcm-remove-group')) {
            e.target.closest('tr').remove();
        }
    });
});