<?php
/*
Plugin Name: Taxonomy Grid Block
Description: A Gutenberg block that displays a paginated, AJAX-loaded grid of taxonomy terms with thumbnails.
Version: 1.2
Author: neverything
*/

if (!defined('ABSPATH')) exit;

add_action('acf/init', function () {
    if (function_exists('acf_register_block_type')) {
        acf_register_block_type([
            'name'            => 'taxonomy-grid',
            'title'           => __('Taxonomy Grid'),
            'description'     => __('Displays a paginated grid of any taxonomy with thumbnails.'),
            'render_callback' => 'render_taxonomy_grid_block',
            'category'        => 'formatting',
            'icon'            => 'grid-view',
            'keywords'        => ['taxonomy', 'terms', 'grid'],
            'mode'            => 'preview',
            'supports'        => ['align' => ['wide', 'full'], 'anchor' => true],
        ]);
    }
});

add_filter('acf/load_field/name=taxonomy', function ($field) {
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    $choices = [];
    foreach ($taxonomies as $key => $tax) {
        $choices[$key] = $tax->labels->singular_name;
    }
    $field['choices'] = $choices;
    return $field;
});

function render_taxonomy_grid_block($block) {
    $taxonomy = get_field('taxonomy') ?: 'category';
    $columns = intval(get_field('grid_columns') ?: 3);

    if (!taxonomy_exists($taxonomy)) {
        echo '<p>Invalid taxonomy: ' . esc_html($taxonomy) . '</p>';
        return;
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        echo '<p>No terms found for taxonomy: ' . esc_html($taxonomy) . '</p>';
        return;
    }

    $date_field = $taxonomy . '_date';
    usort($terms, function($a, $b) use ($date_field) {
        return strtotime(get_field($date_field, $b)) - strtotime(get_field($date_field, $a));
    });

    $current_page = max(1, $_GET['page'] ?? 1);
    $per_page = get_field('items_per_page') ?: 6;
    $total_terms = count($terms);
    $total_pages = ceil($total_terms / $per_page);
    $offset = ($current_page - 1) * $per_page;
    $paged_terms = array_slice($terms, $offset, $per_page);

    echo '<div class="wp-block-taxonomy-grid" data-taxonomy="' . esc_attr($taxonomy) . '" data-current-page="' . esc_attr($current_page) . '" data-items-per-page="' . esc_attr($per_page) . '">';
    echo '<div class="taxonomy-grid-items" style="display: grid; grid-template-columns: repeat(' . $columns . ', 1fr); gap: 1.5rem;">';
    foreach ($paged_terms as $term) {
        $thumb_field = $taxonomy . '_thumbnail';
        $img_id = get_field($thumb_field, $term);
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : plugin_dir_url(__FILE__) . 'default-issue.jpg';
        $link = get_term_link($term);
        echo '<div class="taxonomy-item" style="text-align: center;">';
        echo '<a href="' . esc_url($link) . '"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($term->name) . '" style="width: 100%; height: auto; border-radius: 8px;"></a>';
        echo '<h3 style="margin-top: 0.5rem;"><a href="' . esc_url($link) . '" style="text-decoration: none; color: inherit;">' . esc_html($term->name) . '</a></h3>';
        echo '</div>';
    }
    echo '</div>';

    if ($total_pages > 1) {
        echo '<div class="taxonomy-pagination" style="margin-top: 2rem; text-align: center;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $link = add_query_arg('page', $i);
            $is_current = ($i == $current_page);
            echo '<a href="' . esc_url($link) . '" class="taxonomy-page-link' . ($is_current ? ' current' : '') . '" data-page="' . $i . '">' . $i . '</a> ';
        }
        echo '</div>';
    }

    echo '</div>';
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('taxonomy-grid-js', plugin_dir_url(__FILE__) . 'taxonomy-grid.js', ['jquery'], null, true);
    wp_localize_script('taxonomy-grid-js', 'TaxonomyGridAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});

add_action('wp_ajax_taxonomy_grid_ajax', 'taxonomy_grid_ajax_handler');
add_action('wp_ajax_nopriv_taxonomy_grid_ajax', 'taxonomy_grid_ajax_handler');

function taxonomy_grid_ajax_handler() {
    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = intval($_POST['per_page'] ?? 6);

    if (!taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => 'Invalid taxonomy'], 400);
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
    ]);

    $date_field = $taxonomy . '_date';
    usort($terms, function($a, $b) use ($date_field) {
        return strtotime(get_field($date_field, $b)) - strtotime(get_field($date_field, $a));
    });

    $offset = ($page - 1) * $per_page;
    $paged_terms = array_slice($terms, $offset, $per_page);

    ob_start();
    foreach ($paged_terms as $term) {
        $thumb_field = $taxonomy . '_thumbnail';
        $img_id = get_field($thumb_field, $term);
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : plugin_dir_url(__FILE__) . 'default-issue.jpg';
        $link = get_term_link($term);
        echo '<div class="taxonomy-item" style="text-align: center;">';
        echo '<a href="' . esc_url($link) . '"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($term->name) . '" style="width: 100%; height: auto; border-radius: 8px;"></a>';
        echo '<h3 style="margin-top: 0.5rem;"><a href="' . esc_url($link) . '" style="text-decoration: none; color: inherit;">' . esc_html($term->name) . '</a></h3>';
        echo '</div>';
    }
    echo ob_get_clean();
    wp_die();
}
