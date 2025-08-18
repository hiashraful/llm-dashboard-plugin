<?php

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Prompts_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_post_save_password', array($this, 'save_password'));
        add_action('admin_post_export_prompts', array($this, 'export_prompts'));
        add_action('admin_post_import_prompts', array($this, 'import_prompts'));
        add_action('init', array($this, 'add_elementor_support'));
    }

    public function add_elementor_support()
    {
        add_post_type_support('llm_prompt', 'elementor');
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'LLM Dashboard',
            'LLM Dashboard',
            'manage_options',
            'llm-dashboard-admin',
            array($this, 'dashboard_page'),
            'dashicons-format-chat',
            25
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'llm-dashboard-admin',
            array($this, 'dashboard_page')
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Prompts',
            'Prompts',
            'manage_options',
            'edit.php?post_type=llm_prompt'
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Add New',
            'Add New',
            'manage_options',
            'post-new.php?post_type=llm_prompt'
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Import/Export',
            'Import/Export',
            'manage_options',
            'llm-import-export',
            array($this, 'import_export_page')
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Password',
            'Password',
            'manage_options',
            'llm-password',
            array($this, 'password_page')
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Libraries',
            'Libraries',
            'manage_options',
            'edit-tags.php?taxonomy=llm_library&post_type=llm_prompt'
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Topics',
            'Topics',
            'manage_options',
            'edit-tags.php?taxonomy=llm_topic&post_type=llm_prompt'
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Tags',
            'Tags',
            'manage_options',
            'edit-tags.php?taxonomy=llm_tag&post_type=llm_prompt'
        );
    }

    public function dashboard_page()
    {
        $library_count = wp_count_terms('llm_library');
        $topic_count = wp_count_terms('llm_topic');
        $tag_count = wp_count_terms('llm_tag');
        $prompt_count = wp_count_posts('llm_prompt')->publish;

        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $topics = get_terms(array('taxonomy' => 'llm_topic', 'hide_empty' => false));
        $tags = get_terms(array('taxonomy' => 'llm_tag', 'hide_empty' => false));
        ?>
        <div class="llm-modern-dashboard">
            <div class="llm-header">
                <div class="llm-header-container">
                    <div class="llm-header-content">
                        <h1 class="llm-header-title">LLM Prompts Dashboard</h1>
                        <div class="llm-header-actions">
                            <a href="<?php echo admin_url('post-new.php?post_type=llm_prompt'); ?>" class="llm-add-button">
                                <svg class="llm-add-button-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Prompt
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="llm-main-container">
                <div class="llm-stats-grid">
                    <div class="llm-stat-card">
                        <div class="llm-stat-card-content">
                            <div class="llm-stat-card-info">
                                <h3>Total Prompts</h3>
                                <p><?php echo $prompt_count; ?></p>
                            </div>
                            <div class="llm-stat-card-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="llm-stat-card">
                        <div class="llm-stat-card-content">
                            <div class="llm-stat-card-info">
                                <h3>Libraries</h3>
                                <p><?php echo $library_count; ?></p>
                            </div>
                            <div class="llm-stat-card-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="llm-stat-card">
                        <div class="llm-stat-card-content">
                            <div class="llm-stat-card-info">
                                <h3>Topics</h3>
                                <p><?php echo $topic_count; ?></p>
                            </div>
                            <div class="llm-stat-card-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="llm-stat-card">
                        <div class="llm-stat-card-content">
                            <div class="llm-stat-card-info">
                                <h3>Tags</h3>
                                <p><?php echo $tag_count; ?></p>
                            </div>
                            <div class="llm-stat-card-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="llm-content-grid">
                    <div class="llm-section-card">
                        <div class="llm-section-header">
                            <h2 class="llm-section-title">Libraries</h2>
                            <a href="<?php echo admin_url('edit-tags.php?taxonomy=llm_library&post_type=llm_prompt'); ?>"
                                class="llm-section-link">View all</a>
                        </div>
                        <div class="llm-scrollable-container">
                            <div class="llm-item-list">
                                <?php if (!empty($libraries)): ?>
                                    <?php foreach ($libraries as $library): ?>
                                        <div class="llm-list-item">
                                            <div class="llm-list-item-content">
                                                <div class="llm-list-item-indicator"></div>
                                                <svg class="llm-list-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                                <span class="llm-list-item-name"><?php echo esc_html($library->name); ?></span>
                                            </div>
                                            <span class="llm-list-item-count"><?php echo $library->count; ?> prompts</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="llm-empty-state">
                                        <p>No libraries found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="llm-section-card">
                        <div class="llm-section-header">
                            <h2 class="llm-section-title">Topics</h2>
                            <a href="<?php echo admin_url('edit-tags.php?taxonomy=llm_topic&post_type=llm_prompt'); ?>"
                                class="llm-section-link">View all</a>
                        </div>
                        <div class="llm-scrollable-container">
                            <div class="llm-item-list">
                                <?php if (!empty($topics)): ?>
                                    <?php foreach ($topics as $topic): ?>
                                        <div class="llm-list-item">
                                            <div class="llm-list-item-content">
                                                <div class="llm-list-item-indicator"></div>
                                                <svg class="llm-list-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="llm-list-item-name"><?php echo esc_html($topic->name); ?></span>
                                            </div>
                                            <span class="llm-list-item-count"><?php echo $topic->count; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="llm-empty-state">
                                        <p>No topics found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="llm-section-card">
                        <div class="llm-section-header">
                            <h2 class="llm-section-title">Tags</h2>
                            <a href="<?php echo admin_url('edit-tags.php?taxonomy=llm_tag&post_type=llm_prompt'); ?>"
                                class="llm-section-link">View all</a>
                        </div>
                        <div class="llm-scrollable-container">
                            <div class="llm-item-list llm-tag-list">
                                <?php if (!empty($tags)): ?>
                                    <?php
                                    $tag_colors = array('chatgpt', 'facebook', 'instagram', 'multi-step', 'popular', 'recent');
                                    foreach ($tags as $index => $tag):
                                        $color_class = $tag_colors[$index % count($tag_colors)];
                                        ?>
                                        <div class="llm-tag-item">
                                            <span
                                                class="llm-tag-badge llm-tag-<?php echo $color_class; ?>"><?php echo esc_html($tag->name); ?></span>
                                            <span class="llm-tag-count"><?php echo $tag->count; ?> prompts</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="llm-empty-state">
                                        <p>No tags found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function import_export_page()
    {
        if (isset($_GET['exported']) && $_GET['exported'] == 'true') {
            echo '<div class="notice notice-success"><p>Prompts exported successfully!</p></div>';
        }

        if (isset($_GET['imported']) && $_GET['imported'] == 'true') {
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            echo '<div class="notice notice-success"><p>' . $count . ' prompts imported successfully!</p></div>';
        }

        if (isset($_GET['import_error'])) {
            $error = sanitize_text_field($_GET['import_error']);
            echo '<div class="notice notice-error"><p>Import Error: ' . $error . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Import/Export Prompts</h1>

            <div class="llm-import-export-grid">
                <div class="llm-export-section">
                    <h2>Export Prompts</h2>
                    <p>Export all prompts with their metadata and taxonomy terms to a JSON file.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="export_prompts">
                        <?php wp_nonce_field('llm_export_nonce'); ?>
                        <?php submit_button('Export All Prompts', 'primary', 'export_prompts'); ?>
                    </form>
                </div>

                <div class="llm-import-section">
                    <h2>Import Prompts</h2>
                    <p>Import prompts from a JSON file. This will create new prompts and taxonomy terms as needed.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_prompts">
                        <?php wp_nonce_field('llm_import_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">JSON File</th>
                                <td>
                                    <input type="file" name="import_file" accept=".json" required>
                                    <p class="description">Select a JSON file exported from this plugin.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Import Mode</th>
                                <td>
                                    <label>
                                        <input type="radio" name="import_mode" value="create" checked> Create new prompts only
                                    </label><br>
                                    <label>
                                        <input type="radio" name="import_mode" value="update"> Update existing prompts (match by
                                        title)
                                    </label><br>
                                    <label>
                                        <input type="radio" name="import_mode" value="replace"> Replace all existing prompts
                                    </label>
                                    <p class="description">Choose how to handle existing prompts during import.</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Import Prompts', 'primary', 'import_prompts'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function export_prompts()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_export_nonce')) {
            wp_die('Unauthorized');
        }

        $prompts = get_posts(array(
            'post_type' => 'llm_prompt',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        $export_data = array(
            'version' => '1.0',
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'prompts' => array()
        );

        foreach ($prompts as $prompt) {
            $libraries = wp_get_post_terms($prompt->ID, 'llm_library');
            $topics = wp_get_post_terms($prompt->ID, 'llm_topic');
            $tags = wp_get_post_terms($prompt->ID, 'llm_tag');

            $prompt_data = array(
                'title' => $prompt->post_title,
                'content' => $prompt->post_content,
                'status' => $prompt->post_status,
                'date' => $prompt->post_date,
                'meta' => array(
                    'short_description' => get_post_meta($prompt->ID, '_llm_short_description', true),
                    'tutorial_video' => get_post_meta($prompt->ID, '_llm_tutorial_video', true),
                    'multiple_prompts' => get_post_meta($prompt->ID, '_llm_multiple_prompts', true)
                ),
                'taxonomies' => array(
                    'libraries' => array_map(function ($term) {
                        return $term->name; }, $libraries),
                    'topics' => array_map(function ($term) {
                        return $term->name; }, $topics),
                    'tags' => array_map(function ($term) {
                        return $term->name; }, $tags)
                )
            );

            if (has_post_thumbnail($prompt->ID)) {
                $prompt_data['featured_image'] = get_the_post_thumbnail_url($prompt->ID, 'full');
            }

            $export_data['prompts'][] = $prompt_data;
        }

        $filename = 'llm-prompts-export-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));

        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    public function import_prompts()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_import_nonce')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=llm-import-export&import_error=file_upload_failed'));
            exit;
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(admin_url('admin.php?page=llm-import-export&import_error=invalid_json'));
            exit;
        }

        if (!isset($import_data['prompts']) || !is_array($import_data['prompts'])) {
            wp_redirect(admin_url('admin.php?page=llm-import-export&import_error=invalid_format'));
            exit;
        }

        $import_mode = sanitize_text_field($_POST['import_mode']);
        $imported_count = 0;

        if ($import_mode === 'replace') {
            $existing_prompts = get_posts(array(
                'post_type' => 'llm_prompt',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ));

            foreach ($existing_prompts as $existing_prompt) {
                wp_delete_post($existing_prompt->ID, true);
            }
        }

        foreach ($import_data['prompts'] as $prompt_data) {
            $existing_post = null;

            if ($import_mode === 'update') {
                $existing_post = get_page_by_title($prompt_data['title'], OBJECT, 'llm_prompt');
            }

            if ($existing_post && $import_mode === 'update') {
                $post_id = wp_update_post(array(
                    'ID' => $existing_post->ID,
                    'post_title' => sanitize_text_field($prompt_data['title']),
                    'post_content' => wp_kses_post($prompt_data['content']),
                    'post_status' => sanitize_text_field($prompt_data['status'] ?? 'publish'),
                    'post_type' => 'llm_prompt'
                ));
            } else {
                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($prompt_data['title']),
                    'post_content' => wp_kses_post($prompt_data['content']),
                    'post_status' => sanitize_text_field($prompt_data['status'] ?? 'publish'),
                    'post_type' => 'llm_prompt',
                    'post_date' => sanitize_text_field($prompt_data['date'] ?? current_time('mysql'))
                ));
            }

            if ($post_id && !is_wp_error($post_id)) {
                if (isset($prompt_data['meta'])) {
                    foreach ($prompt_data['meta'] as $meta_key => $meta_value) {
                        if (!empty($meta_value)) {
                            update_post_meta($post_id, '_llm_' . $meta_key, $meta_value);
                        }
                    }
                }

                if (isset($prompt_data['taxonomies'])) {
                    foreach ($prompt_data['taxonomies'] as $taxonomy => $terms) {
                        if (!empty($terms)) {
                            $taxonomy_name = '';
                            switch ($taxonomy) {
                                case 'libraries':
                                    $taxonomy_name = 'llm_library';
                                    break;
                                case 'topics':
                                    $taxonomy_name = 'llm_topic';
                                    break;
                                case 'tags':
                                    $taxonomy_name = 'llm_tag';
                                    break;
                            }

                            if ($taxonomy_name) {
                                $term_ids = array();
                                foreach ($terms as $term_name) {
                                    $term = get_term_by('name', $term_name, $taxonomy_name);
                                    if (!$term) {
                                        $term = wp_insert_term($term_name, $taxonomy_name);
                                        if (!is_wp_error($term)) {
                                            $term_ids[] = $term['term_id'];
                                        }
                                    } else {
                                        $term_ids[] = $term->term_id;
                                    }
                                }

                                if (!empty($term_ids)) {
                                    wp_set_post_terms($post_id, $term_ids, $taxonomy_name);
                                }
                            }
                        }
                    }
                }

                $imported_count++;
            }
        }

        wp_redirect(admin_url('admin.php?page=llm-import-export&imported=true&count=' . $imported_count));
        exit;
    }

    public function password_page()
    {
        $saved_password = get_option('llm_dashboard_password', '');

        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success"><p>Password updated successfully!</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Frontend Password Setup</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_password">
                <?php wp_nonce_field('llm_password_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Dashboard Password</th>
                        <td>
                            <div class="llm-password-field">
                                <input type="password" id="dashboard_password" name="dashboard_password"
                                    value="<?php echo esc_attr($saved_password); ?>" class="regular-text" />
                                <button type="button" class="llm-toggle-password" onclick="togglePassword()">
                                    <span class="dashicons dashicons-visibility" id="toggle-icon"></span>
                                </button>
                            </div>
                            <p class="description">This password will be required to access the frontend dashboard.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Password'); ?>
            </form>

            <script>
                function togglePassword() {
                    const passwordField = document.getElementById('dashboard_password');
                    const toggleIcon = document.getElementById('toggle-icon');

                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        toggleIcon.className = 'dashicons dashicons-hidden';
                    } else {
                        passwordField.type = 'password';
                        toggleIcon.className = 'dashicons dashicons-visibility';
                    }
                }
            </script>
        </div>
        <?php
    }

    public function save_password()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_password_nonce')) {
            wp_die('Unauthorized');
        }

        $password = sanitize_text_field($_POST['dashboard_password']);
        update_option('llm_dashboard_password', $password);

        wp_redirect(admin_url('admin.php?page=llm-password&updated=true'));
        exit;
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'llm_prompt_details',
            'Prompt Details',
            array($this, 'prompt_details_callback'),
            'llm_prompt',
            'normal',
            'default'
        );

        add_meta_box(
            'llm_multiple_prompts',
            'Multiple Prompts',
            array($this, 'multiple_prompts_callback'),
            'llm_prompt',
            'normal',
            'default'
        );
    }

    public function prompt_details_callback($post)
    {
        wp_nonce_field('llm_prompt_meta_nonce', 'llm_prompt_meta_nonce_field');

        $short_description = get_post_meta($post->ID, '_llm_short_description', true);
        $tutorial_video = get_post_meta($post->ID, '_llm_tutorial_video', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Short Description</th>
                <td>
                    <textarea name="llm_short_description" rows="3"
                        cols="50"><?php echo esc_textarea($short_description); ?></textarea>
                    <p class="description">Brief description shown in the prompt feed.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Tutorial Video URL</th>
                <td>
                    <input type="url" name="llm_tutorial_video" value="<?php echo esc_url($tutorial_video); ?>"
                        class="regular-text" />
                    <p class="description">Optional video tutorial URL.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function multiple_prompts_callback($post)
    {
        $prompts = get_post_meta($post->ID, '_llm_multiple_prompts', true);
        if (!$prompts)
            $prompts = array('');
        ?>
        <div id="llm-multiple-prompts">
            <?php foreach ($prompts as $index => $prompt): ?>
                <div class="llm-prompt-item"
                    style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <label>Prompt <?php echo $index + 1; ?>:</label>
                    <textarea name="llm_multiple_prompts[]" rows="4"
                        style="width: 100%; margin-top: 5px;"><?php echo esc_textarea($prompt); ?></textarea>
                    <?php if ($index > 0): ?>
                        <button type="button" onclick="removePrompt(this)"
                            style="margin-top: 5px; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Remove</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-prompt"
            style="background: #0073aa; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Add
            Another Prompt</button>

        <script>
            document.getElementById('add-prompt').addEventListener('click', function () {
                const container = document.getElementById('llm-multiple-prompts');
                const count = container.children.length;
                const div = document.createElement('div');
                div.className = 'llm-prompt-item';
                div.style.cssText = 'margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;';
                div.innerHTML = `
                <label>Prompt ${count + 1}:</label>
                <textarea name="llm_multiple_prompts[]" rows="4" style="width: 100%; margin-top: 5px;"></textarea>
                <button type="button" onclick="removePrompt(this)" style="margin-top: 5px; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Remove</button>
            `;
                container.appendChild(div);
            });

            function removePrompt(button) {
                button.parentElement.remove();
                const items = document.querySelectorAll('.llm-prompt-item label');
                items.forEach((label, index) => {
                    label.textContent = `Prompt ${index + 1}:`;
                });
            }
        </script>
        <?php
    }

    public function save_meta_boxes($post_id)
    {
        if (
            !isset($_POST['llm_prompt_meta_nonce_field']) ||
            !wp_verify_nonce($_POST['llm_prompt_meta_nonce_field'], 'llm_prompt_meta_nonce') ||
            defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
            !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        if (isset($_POST['llm_short_description'])) {
            update_post_meta($post_id, '_llm_short_description', sanitize_textarea_field($_POST['llm_short_description']));
        }

        if (isset($_POST['llm_tutorial_video'])) {
            update_post_meta($post_id, '_llm_tutorial_video', esc_url_raw($_POST['llm_tutorial_video']));
        }

        if (isset($_POST['llm_multiple_prompts'])) {
            $prompts = array_map('sanitize_textarea_field', $_POST['llm_multiple_prompts']);
            $prompts = array_filter($prompts);
            update_post_meta($post_id, '_llm_multiple_prompts', $prompts);
        }
    }
}