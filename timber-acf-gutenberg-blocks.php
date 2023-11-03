<?php

/*
Plugin Name: Timber ACF Gutenberg Blocks
Description: Customize WordPress & Timber with powerful, professional and intuitive fields.
Version: 1.0
Author: Sander Janssen
Author URI: https://sanderjanssen.nl
Text Domain: timber-acf
*/

namespace App;

use Timber\Timber;

require_once get_template_directory() . '/vendor/autoload.php';

// Check if ACF and Timber are loaded
if (!function_exists('acf_register_block_type') || !class_exists('\Timber\Timber')) {
    return;
}

// Add a filter for the blocks directory
add_filter('timber-acf-gutenberg-blocks-templates', function () {
    return ['/views/blocks'];
});

add_action('acf/init', function () {
    $directories = apply_filters('timber-acf-gutenberg-blocks-templates', []);

    foreach ($directories as $directory) {
        $dir = locate_template($directory);

        if (!file_exists($dir)) {
            return;
        }

        $template_directory = new \DirectoryIterator($dir);

        foreach ($template_directory as $template) {
            if (!$template->isDot() && !$template->isDir()) {
                $slug = removeTwigExtension($template->getFilename());
                if (!$slug) {
                    continue;
                }

                $file = "$dir/$slug.twig";
                if (!file_exists($file)) {
                    continue;
                }

                $file_headers = get_twig_file_data($file, [
                    'title' => 'Title',
                    'description' => 'Description',
                    'category' => 'Category',
                    'icon' => 'Icon',
                    'keywords' => 'Keywords',
                    'mode' => 'Mode',
                    'align' => 'Align',
                    'post_types' => 'PostTypes',
                    'supports_align' => 'SupportsAlign',
                    'supports_anchor' => 'SupportsAnchor',
                    'supports_mode' => 'SupportsMode',
                    'supports_jsx' => 'SupportsInnerBlocks',
                    'supports_align_text' => 'SupportsAlignText',
                    'supports_align_content' => 'SupportsAlignContent',
                    'supports_multiple' => 'SupportsMultiple',
                    'enqueue_style'     => 'EnqueueStyle',
                    'enqueue_script'    => 'EnqueueScript',
                    'enqueue_assets'    => 'EnqueueAssets',
                ]);

                if (empty($file_headers['title'])) {
                    handle_error('This block needs a title: ' . $dir . '/' . $template->getFilename(), E_USER_WARNING);
                }

                if (empty($file_headers['category'])) {
                    handle_error('This block needs a category: ' . $dir . '/' . $template->getFilename(), E_USER_WARNING);
                }

                // Checks if dist contains this asset, then enqueues the dist version.
                if (!empty($file_headers['enqueue_style'])) {
                    checkAssetPath($file_headers['enqueue_style']);
                }

                if (!empty($file_headers['enqueue_script'])) {
                    checkAssetPath($file_headers['enqueue_script']);
                }

                // Set up block data for registration
                $data = [
                    'name' => $slug,
                    'title' => $file_headers['title'],
                    'description' => $file_headers['description'],
                    'category' => $file_headers['category'],
                    'icon' => $file_headers['icon'],
                    'keywords' => explode(' ', $file_headers['keywords']),
                    'mode' => $file_headers['mode'],
                    'align' => $file_headers['align'],
                    'render_callback'  => __NAMESPACE__.'\\sage_blocks_callback',
                    'enqueue_style'   => $file_headers['enqueue_style'],
                    'enqueue_script'  => $file_headers['enqueue_script'],
                    'enqueue_assets'  => $file_headers['enqueue_assets'],
                    'example'  => array(
                        'attributes' => array(
                            'mode' => 'preview',
                        )
                    )
                ];

                // If the PostTypes header is set in the template, restrict this block to those types
                if (!empty($file_headers['post_types'])) {
                    $data['post_types'] = explode(' ', $file_headers['post_types']);
                }

                // If the SupportsAlign header is set in the template, restrict this block to those aligns
                if (!empty($file_headers['supports_align'])) {
                    $data['supports']['align'] = in_array($file_headers['supports_align'], array('true', 'false'), true) ? filter_var($file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN) : explode(' ', $file_headers['supports_align']);
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_anchor'])) {
                    $data['supports']['anchor'] = $file_headers['supports_anchor'] === 'true' ? true : false;
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_mode'])) {
                    $data['supports']['mode'] = $file_headers['supports_mode'] === 'true' ? true : false;
                }

                // If the SupportsInnerBlocks header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_jsx'])) {
                    $data['supports']['jsx'] = $file_headers['supports_jsx'] === 'true' ? true : false;
                }

                // If the SupportsAlignText header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_align_text'])) {
                    $data['supports']['align_text'] = $file_headers['supports_align_text'] === 'true' ? true : false;
                }

                // If the SupportsAlignContent header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_align_text'])) {
                    $data['supports']['align_content'] = $file_headers['supports_align_content'] === 'true' ? true : false;
                }

                // If the SupportsMultiple header is set in the template, restrict this block multiple feature
                if (!empty($file_headers['supports_multiple'])) {
                    $data['supports']['multiple'] = $file_headers['supports_multiple'] === 'true' ? true : false;
                }

                $data['render_callback'] = __NAMESPACE__ . '\\timber_blocks_callback';

                // Register the block with ACF
                acf_register_block_type($data);
            }
        }
    }
});

function timber_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0) {
    $slug = str_replace('acf/', '', $block['name']);
    $block['slug'] = $slug;

    // Set up the block data
    $block['post_id'] = $post_id;
    $block['is_preview'] = $is_preview;
    $block['content'] = $content;
    $block['slug'] = $slug;
    $block['anchor'] = isset($block['anchor']) ? $block['anchor'] : '';
    // Send classes as array to filter for easy manipulation.
    $block['classes'] = [
        $slug,
        isset($block['className']) ? $block['className'] : '',
        $block['is_preview'] ? 'is-preview' : null,
        'align'.$block['align']
    ];

    // Filter the block data.
    $block = apply_filters("timber/blocks/$slug/data", $block);

    // Join up the classes.
    $block['classes'] = implode(' ', array_filter($block['classes']));

    $directories = apply_filters('timber-acf-gutenberg-blocks-templates', []);

    foreach ($directories as $directory) {
        $view = $directory . '/' . $slug . '.twig';


        if (file_exists(locate_template($view))) {
            console_log($block);
            Timber::render($view, ['block' => $block]);
        }
    }
}

function removeTwigExtension($filename) {
    $twig_pattern = '/(.*)\.twig$/';
    $matches = [];
    if (preg_match($twig_pattern, $filename, $matches)) {
        return $matches[1];
    }
    return false;
}

function get_twig_file_data($file, $default_headers) {
    // Read the entire file into a string
    $file_data = file_get_contents($file);

    // This will match the multiline comment block and capture the contents
    $pattern = "/\{#\s*(.*?)\s*#\}/s";
    preg_match($pattern, $file_data, $matches);
    $comment_block = $matches[1] ?? '';

    // Split the comment block into lines and parse each line for headers
    $headers = [];
    $lines = explode("\n", $comment_block);
    foreach ($lines as $line) {
        foreach ($default_headers as $field => $regex) {
            if (strpos($line, $regex . ':') !== false) {
                // Extract the content after the header name
                list(, $value) = explode($regex . ':', $line, 2);
                $headers[$field] = trim($value);
            }
        }
    }

    // Fill in the missing headers with empty strings
    foreach ($default_headers as $field => $regex) {
        if (!isset($headers[$field])) {
            $headers[$field] = '';
        }
    }

    return $headers;
}

function handle_error($message, $error_type = E_USER_WARNING) {
    if (WP_DEBUG === true) {
        trigger_error($message, $error_type);
    }
}

function console_log($data) {
    echo '<script>';
    echo 'console.log('. json_encode( $data ) .')';
    echo '</script>';
}
