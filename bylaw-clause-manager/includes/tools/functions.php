<?php

/** File: includes/tools/functions.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.2
 * @author greghacke
 * Function: Tools functions for the Bylaw Clause Manager plugin
 */

defined( 'ABSPATH' ) || exit;

/** Normalizes a clause title by converting it to lowercase and replacing dots with underscores.
 * Used for consistent matching of clause hierarchy regardless of format.
 *
 * @param string $title
 * @return string
 */
function bcm_normalize_clause_title($title) {
    return strtolower(str_replace('.', '_', $title));
}

/** Fixes the parent_clause meta field based on post title hierarchy.
 * Supports both underscore (_) and dot (.) formats in post titles.
 */
function bcm_fix_clause_parents() {
    $posts = get_posts([
        'post_type'   => 'bylaw_clause',
        'numberposts' => -1,
        'post_status' => ['draft', 'publish', 'pending', 'future', 'private'],
    ]);

    // Normalize all post titles for quick lookup
    $title_map = [];
    foreach ($posts as $post) {
        $slug = bcm_normalize_clause_title($post->post_title);
        $title_map[$slug] = $post->ID;
    }

    $updated = 0;

    foreach ($posts as $post) {
        $original_title = $post->post_title;
        $parts = preg_split('/[_\.]/', $original_title); // supports both '_' and '.'

        if (count($parts) <= 1) continue;

        array_pop($parts); // Trim last part to find parent
        $parent_key = strtolower(implode('_', $parts)); // normalized to match map

        if (isset($title_map[$parent_key])) {
            $parent_id = $title_map[$parent_key];
            $current = get_post_meta($post->ID, 'parent_clause', true);

            if ((int) $current !== (int) $parent_id) {
                update_post_meta($post->ID, 'parent_clause', $parent_id);
                $updated++;
            }
        }
    }

    echo '<div class="notice notice-success"><p>' .
        esc_html("✅ $updated parent clauses updated based on post title hierarchy.") .
        '</p></div>';
}

/** Title sorting function for Bylaw Clauses.
 * This function generates a sort key array based on the hierarchical parts of the title.
 * It supports numeric, single-letter, and Roman numeral components.
 */
function bcm_title_sort_key($title) {
    if (!$title) return [999999];

    $parts = preg_split('/[\.\-_]/', strtoupper(trim($title)));
    $key = [];

    // Define repeating pattern
    $pattern = ['ordinal', 'alpha', 'roman'];

    foreach ($parts as $index => $part) {
        $type = $pattern[$index % count($pattern)];

        switch ($type) {
            case 'ordinal':
                $key[] = is_numeric($part) ? (int)$part : 900000 + crc32($part);
                break;

            case 'alpha':
                if (preg_match('/^[A-Z]$/', $part)) {
                    $key[] = ord($part) - ord('A'); // A = 0
                } else {
                    $key[] = 900000 + crc32($part);
                }
                break;

            case 'roman':
                $roman = bcm_roman_to_int($part);
                $key[] = $roman !== false ? $roman : (900000 + crc32($part));
                break;
        }
    }

    return $key;
}

/** Converts a Bylaw Clause sequence string to an integer for sorting.
 * Handles numeric sequences, single letters, and Roman numerals.
 * Returns a large integer for non-standard sequences to ensure they sort last.
 *
 * @param string $seq
 * @return int
 */
function bcm_sequence_to_int($seq) {
    if (!$seq) return 999999;

    $seq = trim($seq);
    $lower = strtolower($seq);

    // Numeric
    if (is_numeric($seq)) return (int)$seq;

    // Single letter
    if (preg_match('/^[a-zA-Z]$/', $seq)) {
        return ord($lower) - ord('a') + 1000;
    }

    // Roman numerals
    $roman_value = bcm_roman_to_int($seq);
    if ($roman_value !== false) {
        return 2000 + $roman_value;
    }

    // Fallback
    return 900000 + crc32($seq);
}

/** Converts a Roman numeral string to an integer.
 * This function supports standard Roman numeral notation and returns false for invalid input.
 * It handles both uppercase and lowercase letters, and trims whitespace.
 * It uses a mapping of Roman numeral symbols to their integer values.
 */
function bcm_roman_to_int($roman) {
    $roman = strtoupper(trim($roman));
    if (!$roman) return false;

    $map = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
        'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
        'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
    ];

    $i = 0; $value = 0;
    while ($i < strlen($roman)) {
        if ($i + 1 < strlen($roman) && isset($map[substr($roman, $i, 2)])) {
            $value += $map[substr($roman, $i, 2)];
            $i += 2;
        } elseif (isset($map[$roman[$i]])) {
            $value += $map[$roman[$i]];
            $i++;
        } else {
            return false; // invalid character
        }
    }

    return $value;
}

/** Converts an integer to a Roman numeral string. 
 * This function supports integers from 1 to 3999, as Roman numerals are not typically used for larger numbers.
 * It returns false for out-of-range values or non-integer inputs.
 */
function bcm_int_to_roman($number) {
    if (!is_int($number) || $number <= 0 || $number > 3999) {
        return false; // Out of standard Roman numeral range
    }

    $map = [
        'M'  => 1000,
        'CM' => 900,
        'D'  => 500,
        'CD' => 400,
        'C'  => 100,
        'XC' => 90,
        'L'  => 50,
        'XL' => 40,
        'X'  => 10,
        'IX' => 9,
        'V'  => 5,
        'IV' => 4,
        'I'  => 1,
    ];

    $result = '';

    foreach ($map as $roman => $value) {
        while ($number >= $value) {
            $result .= $roman;
            $number -= $value;
        }
    }

    return $result;
}

/** Inline Styles for Bylaw Clause Manager.
 * This function generates a string of CSS styles to be applied inline.
 * It styles the toolbar, buttons, labels, and Bylaw Clause elements.
 * It also includes styles for vote tooltips and their content.
 * The styles are designed to be minimal and functional, ensuring a clean and user-friendly interface.
 */
function bcm_get_inline_styles() {
    $css = '';

    $css .= "#bcm-toolbar{margin-bottom:1em;display:flex;align-items:center;gap:0.75em;flex-wrap:wrap;}";
    $css .= "#bcm-toolbar button{padding:4px 10px;font-size:0.9em;border-radius:4px;border:1px solid #ccc;background:#f5f5f5;cursor:pointer;transition:0.2s;line-height:1.2;}";
    $css .= "#bcm-toolbar button:hover{background:#e6e6e6;border-color:#999;}";
    $css .= "#bcm-toolbar label{margin-right:0.5em;font-size:0.95em;font-weight:500;}";

    $css .= ".bylaw-clause{display:block;margin-bottom:0em;}";
    $css .= ".bylaw-label-wrap{display:flex;align-items:flex-start;gap:1em;flex-wrap:wrap;margin-bottom:0.1em;line-height:1.1;}";
    $css .= ".bylaw-label-number{white-space:nowrap;font-size:1em;flex-shrink:0;}";
    $css .= ".bylaw-label-text{word-break:break-word;font-size:0.95em;flex:1;min-width:0;}";

    $css .= ".vote-tooltip{position:relative;display:inline-block;color:#555;cursor:help;font-size:0.85em;}";
    $css .= ".vote-tooltip .tooltip-content{display:none;position:absolute;top:100%;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:6px 8px;border-radius:4px;font-size:0.75em;z-index:9999;min-width:160px;max-width:280px;text-align:left;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:normal;}";
    $css .= ".vote-tooltip:hover .tooltip-content{display:block;}";
    $css .= ".tooltip-content a{color:#9cf;text-decoration:underline;}";
    $css .= ".tooltip-content a:hover{color:#cde;}";

    return $css;
}

