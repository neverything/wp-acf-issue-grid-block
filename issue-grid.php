<?php
/*
Plugin Name: ACF Issue Grid Block
Description: A ACF block that displays a paginated, AJAX-loaded grid of custom taxonomy terms with thumbnails.
Version: 1.0
Author: neverything
*/

if (!defined('ABSPATH')) exit;

add_action('acf/init', function () {
    if (function_exists('acf_register_block_type')) {
        acf_register_block_type([
            'name'            => 'issue-grid',
            'title'           => __('Issue Grid'),
            'description'     => __('Displays a paginated grid of issues with thumbnails.'),
            'render_callback' => 'render_issue_grid_block',
            'category'        => 'formatting',
            'icon'            => 'grid-view',
            'keywords'        => ['issues', 'taxonomy', 'grid'],
            'mode'            => 'preview',
            'supports'        => ['align' => ['wide', 'full'], 'anchor' => true],
        ]);
    }
});

function render_issue_grid_block($block) {
    $terms = get_terms([
        'taxonomy'   => 'issues',
        'hide_empty' => false,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        echo '<p>No issues found.</p>';
        return;
    }

    usort($terms, function($a, $b) {
        return strtotime(get_field('issue_date', $b)) - strtotime(get_field('issue_date', $a));
    });

    $current_page = max(1, $_GET['page'] ?? 1);
    $per_page = get_field('items_per_page') ?: 6;
    $total_terms = count($terms);
    $total_pages = ceil($total_terms / $per_page);
    $offset = ($current_page - 1) * $per_page;
    $paged_terms = array_slice($terms, $offset, $per_page);

    echo '<div class="wp-block-issue-grid" data-current-page="' . esc_attr($current_page) . '" data-items-per-page="' . esc_attr($per_page) . '">';
    echo '<div class="issue-grid-items" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">';
    foreach ($paged_terms as $term) {
        $img_id = get_field('issue_thumbnail', $term);
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : plugin_dir_url(__FILE__) . 'default-issue.jpg';
        $link = get_term_link($term);
        echo '<div class="issue-item" style="text-align: center;">';
        echo '<a href="' . esc_url($link) . '"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($term->name) . '" style="width: 100%; height: auto; border-radius: 8px;"></a>';
        echo '<h3 style="margin-top: 0.5rem;"><a href="' . esc_url($link) . '" style="text-decoration: none; color: inherit;">' . esc_html($term->name) . '</a></h3>';
        echo '</div>';
    }
    echo '</div>';

    if ($total_pages > 1) {
        echo '<div class="issue-pagination" style="margin-top: 2rem; text-align: center;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $link = add_query_arg('page', $i);
            $is_current = ($i == $current_page);
            echo '<a href="' . esc_url($link) . '" class="issue-page-link' . ($is_current ? ' current' : '') . '" data-page="' . $i . '">' . $i . '</a> ';
        }
        echo '</div>';
    }

    echo '</div>';
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('issue-grid-js', plugin_dir_url(__FILE__) . 'issue-grid.js', ['jquery'], null, true);
    wp_localize_script('issue-grid-js', 'IssueGridAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});

add_action('wp_ajax_issue_grid_ajax', 'issue_grid_ajax_handler');
add_action('wp_ajax_nopriv_issue_grid_ajax', 'issue_grid_ajax_handler');

function issue_grid_ajax_handler() {
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = intval($_POST['per_page'] ?? 6);

    $terms = get_terms([
        'taxonomy'   => 'issues',
        'hide_empty' => false,
    ]);

    usort($terms, function($a, $b) {
        return strtotime(get_field('issue_date', $b)) - strtotime(get_field('issue_date', $a));
    });

    $offset = ($page - 1) * $per_page;
    $paged_terms = array_slice($terms, $offset, $per_page);

    ob_start();
    foreach ($paged_terms as $term) {
        $img_id = get_field('issue_thumbnail', $term);
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : plugin_dir_url(__FILE__) . 'default-issue.jpg';
        $link = get_term_link($term);
        echo '<div class="issue-item" style="text-align: center;">';
        echo '<a href="' . esc_url($link) . '"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($term->name) . '" style="width: 100%; height: auto; border-radius: 8px;"></a>';
        echo '<h3 style="margin-top: 0.5rem;"><a href="' . esc_url($link) . '" style="text-decoration: none; color: inherit;">' . esc_html($term->name) . '</a></h3>';
        echo '</div>';
    }
    echo ob_get_clean();
    wp_die();
}
