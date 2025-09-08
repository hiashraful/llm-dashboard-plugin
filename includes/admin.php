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
        add_action('admin_post_save_special_offer', array($this, 'save_special_offer'));
        add_action('admin_post_save_premium_libraries', array($this, 'save_premium_libraries'));
        add_action('admin_post_save_default_settings', array($this, 'save_default_settings'));
        add_action('init', array($this, 'add_elementor_support'));
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'show_user_columns'), 10, 3);
        add_action('restrict_manage_users', array($this, 'add_user_filters'));
        add_filter('pre_get_users', array($this, 'filter_users_by_special_offer'));
        add_action('admin_head', array($this, 'customize_editor_labels'));
        add_action('admin_init', array($this, 'remove_unnecessary_meta_boxes'));
        add_filter('screen_options_show_screen', array($this, 'remove_screen_options'), 10, 2);
    }

    public function add_elementor_support()
    {
        add_post_type_support('llm_prompt', 'elementor');
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Knowledge Hub',
            'Knowledge Hub',
            'manage_options',
            'llm-dashboard-admin',
            array($this, 'dashboard_page'),
            'dashicons-buddicons-replies',
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

        add_submenu_page(
            'llm-dashboard-admin',
            'Special Offer',
            'Special Offer',
            'manage_options',
            'llm-special-offer',
            array($this, 'special_offer_page')
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Premium Libraries',
            'Premium Libraries',
            'manage_options',
            'llm-premium-libraries',
            array($this, 'premium_libraries_page')
        );

        add_submenu_page(
            'llm-dashboard-admin',
            'Default Settings',
            'Default Settings',
            'manage_options',
            'llm-default-settings',
            array($this, 'default_settings_page')
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
                        <h1 class="llm-header-title">ACLAS Knowledge Hub</h1>
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
        $prompts_data = get_post_meta($post->ID, '_llm_multiple_prompts', true);
        
        // Handle both old array format and new string format
        if (is_string($prompts_data) && !empty($prompts_data)) {
            // New string format - convert back to array for editing
            $prompts = explode('---PROMPT SEPARATOR---', $prompts_data);
        } elseif (is_array($prompts_data) && !empty($prompts_data)) {
            // Old array format - use as is
            $prompts = $prompts_data;
        } else {
            // No data - start with empty prompt
            $prompts = array('');
        }
        ?>
        <div id="llm-multiple-prompts">
            <?php foreach ($prompts as $index => $prompt): ?>
                <div class="llm-prompt-item"
                    style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <label>Prompt <?php echo $index + 1; ?>:</label>
                    <textarea name="llm_multiple_prompts[]" rows="4"
                        style="width: 100%; margin-top: 5px; font-family: 'Inter', sans-serif;"><?php echo esc_textarea($prompt); ?></textarea>
                    <?php if ($index > 0): ?>
                        <button type="button" onclick="removePrompt(this)"
                            style="margin-top: 5px; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-family: 'Inter', sans-serif;">Remove</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-prompt"
            style="background: #0073aa; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-family: 'Inter', sans-serif;">Add
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
                <textarea name="llm_multiple_prompts[]" rows="4" style="width: 100%; margin-top: 5px; font-family: 'Inter', sans-serif;"></textarea>
                <button type="button" onclick="removePrompt(this)" style="margin-top: 5px; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-family: 'Inter', sans-serif;">Remove</button>
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
            $prompts = array_filter($prompts); // Remove empty entries
            
            // Convert array to string format expected by frontend
            if (!empty($prompts)) {
                $prompts_string = implode('---PROMPT SEPARATOR---', $prompts);
                update_post_meta($post_id, '_llm_multiple_prompts', $prompts_string);
            } else {
                delete_post_meta($post_id, '_llm_multiple_prompts');
            }
        }
    }

    public function special_offer_page()
    {
        $offer_date = get_option('llm_special_offer_date', '');
        $offer_limit = get_option('llm_special_offer_limit', 50);
        $offer_status = get_option('llm_special_offer_status', 'inactive');

        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success"><p>Special offer settings updated successfully!</p></div>';
        }

        $special_students = $this->get_special_offer_students();
        $special_count = count($special_students);

        ?>
        <div class="wrap">
            <h1>Special Offer Settings</h1>
            
            <div class="llm-special-offer-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div class="llm-offer-settings">
                    <h2>Offer Configuration</h2>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="save_special_offer">
                        <?php wp_nonce_field('llm_special_offer_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Offer Status</th>
                                <td>
                                    <select name="offer_status">
                                        <option value="inactive" <?php selected($offer_status, 'inactive'); ?>>Inactive</option>
                                        <option value="active" <?php selected($offer_status, 'active'); ?>>Active</option>
                                    </select>
                                    <p class="description">Enable or disable the special offer program.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Start Date</th>
                                <td>
                                    <input type="date" name="offer_date" value="<?php echo esc_attr($offer_date); ?>" class="regular-text" />
                                    <p class="description">Students registered after this date are eligible.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Number of Students</th>
                                <td>
                                    <input type="number" name="offer_limit" value="<?php echo esc_attr($offer_limit); ?>" min="1" max="1000" class="regular-text" />
                                    <p class="description">Maximum number of students who will receive the special offer.</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>

                <div class="llm-offer-status">
                    <h2>Offer Status</h2>
                    <div class="llm-status-cards" style="margin-bottom: 20px;">
                        <div class="llm-status-card" style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <h3 style="margin: 0 0 10px 0;">Current Status</h3>
                            <p style="margin: 0; font-size: 16px; color: <?php echo $offer_status === 'active' ? '#10B981' : '#EF4444'; ?>; font-family: 'Inter', sans-serif;">
                                <?php echo ucfirst($offer_status); ?>
                            </p>
                        </div>
                        <div class="llm-status-card" style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <h3 style="margin: 0 0 10px 0;">Special Students</h3>
                            <p style="margin: 0; font-size: 16px; font-family: 'Inter', sans-serif;">
                                <?php echo $special_count; ?> / <?php echo $offer_limit; ?> assigned
                            </p>
                            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; margin-top: 8px;">
                                <div style="background: #3B82F6; height: 100%; width: <?php echo min(100, ($special_count / max(1, $offer_limit)) * 100); ?>%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($special_students)): ?>
                        <h3>Special Offer Students</h3>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                            <table class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">ID</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($special_students as $student): ?>
                                        <tr>
                                            <td><?php echo $student->ID; ?></td>
                                            <td><?php echo esc_html($student->user_email); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($student->user_registered)); ?></td>
                                            <td><span style="background: #10B981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Special</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_special_offer()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_special_offer_nonce')) {
            wp_die('Unauthorized');
        }

        $offer_date = sanitize_text_field($_POST['offer_date']);
        $offer_limit = intval($_POST['offer_limit']);
        $offer_status = sanitize_text_field($_POST['offer_status']);

        update_option('llm_special_offer_date', $offer_date);
        update_option('llm_special_offer_limit', $offer_limit);
        update_option('llm_special_offer_status', $offer_status);

        wp_redirect(admin_url('admin.php?page=llm-special-offer&updated=true'));
        exit;
    }

    private function get_special_offer_students()
    {
        $args = array(
            'meta_query' => array(
                array(
                    'key'     => '_llm_special_offer_student',
                    'value'   => 'yes',
                    'compare' => '='
                )
            ),
            'fields' => 'all'
        );

        return get_users($args);
    }

    public function add_user_columns($columns)
    {
        $columns['special_offer'] = 'Special Offer';
        return $columns;
    }

    public function show_user_columns($value, $column_name, $user_id)
    {
        if ($column_name == 'special_offer') {
            $is_special = get_user_meta($user_id, '_llm_special_offer_student', true) === 'yes';
            $user = get_userdata($user_id);
            $is_academy_student = $user && user_can($user, 'academy_student');
            
            if ($is_special && $is_academy_student) {
                return '<span style="background: #10B981; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; font-family: \'Inter\', sans-serif;">SPECIAL</span>';
            } elseif ($is_academy_student) {
                return '<span style="background: #3B82F6; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; font-family: \'Inter\', sans-serif;">STUDENT</span>';
            }
            return '-';
        }
        return $value;
    }

    public function add_user_filters()
    {
        $filter_value = isset($_GET['special_offer_filter']) ? $_GET['special_offer_filter'] : '';
        ?>
        <select name="special_offer_filter">
            <option value="">All Users</option>
            <option value="special" <?php selected($filter_value, 'special'); ?>>Special Offer Students</option>
            <option value="academy" <?php selected($filter_value, 'academy'); ?>>Academy Students</option>
        </select>
        <?php
    }

    public function filter_users_by_special_offer($query)
    {
        global $pagenow;
        
        if (is_admin() && $pagenow == 'users.php' && isset($_GET['special_offer_filter']) && !empty($_GET['special_offer_filter'])) {
            $filter = $_GET['special_offer_filter'];
            
            if ($filter === 'special') {
                $query->set('meta_key', '_llm_special_offer_student');
                $query->set('meta_value', 'yes');
            } elseif ($filter === 'academy') {
                $query->set('meta_key', 'wp_capabilities');
                $query->set('meta_value', 'academy_student');
                $query->set('meta_compare', 'LIKE');
            }
        }
    }

    public function premium_libraries_page()
    {
        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));

        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success"><p>Premium library settings updated successfully!</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Premium Libraries Management</h1>
            <p>Configure which libraries require premium access. Premium libraries will show a "Coming Soon" message to non-premium users.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_premium_libraries">
                <?php wp_nonce_field('llm_premium_libraries_nonce'); ?>

                <div class="llm-premium-libraries-grid" style="display: grid; gap: 20px; margin-top: 20px;">
                    <?php if (!empty($libraries)): ?>
                        <div class="llm-libraries-section">
                            <h2>Library Settings</h2>
                            <table class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">Premium</th>
                                        <th>Library Name</th>
                                        <th style="width: 80px;">Prompts</th>
                                        <th style="width: 100px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($libraries as $library): 
                                        $is_premium = get_term_meta($library->term_id, '_llm_premium_library', true) === 'yes';
                                        ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <label>
                                                    <input type="checkbox" 
                                                           name="premium_libraries[]" 
                                                           value="<?php echo $library->term_id; ?>"
                                                           <?php checked($is_premium); ?>>
                                                </label>
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($library->name); ?></strong>
                                                <?php if ($library->description): ?>
                                                    <div style="color: #666; font-size: 12px; margin-top: 2px; font-family: 'Inter', sans-serif;">
                                                        <?php echo esc_html($library->description); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $library->count; ?> prompts</td>
                                            <td>
                                                <?php if ($is_premium): ?>
                                                    <span style="background: #F59E0B; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">PREMIUM</span>
                                                <?php else: ?>
                                                    <span style="background: #10B981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">FREE</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-info">
                            <p>No libraries found. Please create some libraries first.</p>
                        </div>
                    <?php endif; ?>

                    <div class="llm-premium-settings">
                        <h2>Premium Message Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Coming Soon Title</th>
                                <td>
                                    <input type="text" 
                                           name="premium_title" 
                                           value="<?php echo esc_attr(get_option('llm_premium_title', '🔒 Premium Library')); ?>" 
                                           class="regular-text" />
                                    <p class="description">Title shown on premium library pages.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Coming Soon Message</th>
                                <td>
                                    <textarea name="premium_message" 
                                              rows="4" 
                                              class="large-text"><?php echo esc_textarea(get_option('llm_premium_message', "⭐ Coming Soon!\n\nThis premium library is currently under development and will be available soon with exclusive high-quality prompts.")); ?></textarea>
                                    <p class="description">Message shown to users when they select a premium library.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Upgrade Button Text</th>
                                <td>
                                    <input type="text" 
                                           name="premium_button_text" 
                                           value="<?php echo esc_attr(get_option('llm_premium_button_text', '🚀 Get Premium Access')); ?>" 
                                           class="regular-text" />
                                    <p class="description">Text for the premium upgrade button.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Upgrade Button URL</th>
                                <td>
                                    <input type="url" 
                                           name="premium_button_url" 
                                           value="<?php echo esc_url(get_option('llm_premium_button_url', '#')); ?>" 
                                           class="regular-text" />
                                    <p class="description">URL where users go to upgrade to premium (leave # to hide button).</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('Save Premium Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function save_premium_libraries()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_premium_libraries_nonce')) {
            wp_die('Unauthorized');
        }

        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $premium_libraries = isset($_POST['premium_libraries']) ? $_POST['premium_libraries'] : array();

        foreach ($libraries as $library) {
            if (in_array($library->term_id, $premium_libraries)) {
                update_term_meta($library->term_id, '_llm_premium_library', 'yes');
            } else {
                delete_term_meta($library->term_id, '_llm_premium_library');
            }
        }

        update_option('llm_premium_title', sanitize_text_field($_POST['premium_title']));
        update_option('llm_premium_message', sanitize_textarea_field($_POST['premium_message']));
        update_option('llm_premium_button_text', sanitize_text_field($_POST['premium_button_text']));
        update_option('llm_premium_button_url', esc_url_raw($_POST['premium_button_url']));

        wp_redirect(admin_url('admin.php?page=llm-premium-libraries&updated=true'));
        exit;
    }

    public function customize_editor_labels()
    {
        global $post_type;
        
        if ($post_type === 'llm_prompt') {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Change the main editor label - bigger heading
                $('#postdivrich .wp-editor-tabs').before('<h2 style="margin: 15px 0 10px 0; font-size: 18px; font-weight: 600; color: #1d2327;">The Method</h2>');
                
                // Remove Add Media button
                $('#wp-content-media-buttons').hide();
                
                // Make editor smaller (simple height adjustment)
                $('#content').css('height', '200px');
                
                // Style the editor wrapper
                $('#postdivrich').css({
                    'border': '1px solid #ddd',
                    'border-radius': '4px',
                    'padding': '10px',
                    'margin-top': '10px'
                });
            });
            </script>
            <?php
        }
    }

    public function remove_unnecessary_meta_boxes()
    {
        // Remove for llm_prompt post type - try multiple hooks for better coverage
        add_action('admin_menu', function() {
            remove_meta_box('postcustom', 'llm_prompt', 'normal'); // Custom Fields
            remove_meta_box('commentstatusdiv', 'llm_prompt', 'normal'); // Comments
            remove_meta_box('trackbacksdiv', 'llm_prompt', 'normal'); // Trackbacks
            remove_meta_box('authordiv', 'llm_prompt', 'normal'); // Author
            remove_meta_box('slugdiv', 'llm_prompt', 'normal'); // Slug
            remove_meta_box('postexcerpt', 'llm_prompt', 'normal'); // Excerpt
        }, 99);

        // Also try removing after meta boxes are added
        add_action('add_meta_boxes', function() {
            remove_meta_box('postcustom', 'llm_prompt', 'normal'); // Custom Fields - try again here
            
            // Restrict This Content plugin
            remove_meta_box('rtc-meta-box', 'llm_prompt', 'side');
            remove_meta_box('restrict-content-pro', 'llm_prompt', 'side');
            
            // WPCode Page Scripts
            remove_meta_box('wpcode_page_scripts_metabox', 'llm_prompt', 'side');
            remove_meta_box('wpcode-metabox', 'llm_prompt', 'side');
            
            // MonsterInsights
            remove_meta_box('monsterinsights_posts_page_insights', 'llm_prompt', 'normal');
            remove_meta_box('monsterinsights-post-analytics', 'llm_prompt', 'normal');
            
            // Other common plugin meta boxes
            remove_meta_box('wpseo_meta', 'llm_prompt', 'normal'); // Yoast SEO
            remove_meta_box('rankmath_metabox', 'llm_prompt', 'normal'); // RankMath SEO
            remove_meta_box('aioseo-settings', 'llm_prompt', 'normal'); // All in One SEO
            remove_meta_box('elementor_custom_css', 'llm_prompt', 'side'); // Elementor Custom CSS
        }, 99);

        // Disable custom fields support entirely for this post type
        add_action('admin_head-post.php', function() {
            global $post_type;
            if ($post_type === 'llm_prompt') {
                ?>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Hide any remaining custom fields interface
                    $('#postcustom, .postbox[id*="custom"]').hide();
                    $('#screen-options-link-wrap').hide(); // Hide screen options entirely
                });
                </script>
                <?php
            }
        });

        add_action('admin_head-post-new.php', function() {
            global $post_type;
            if ($post_type === 'llm_prompt') {
                ?>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Hide any remaining custom fields interface
                    $('#postcustom, .postbox[id*="custom"]').hide();
                    $('#screen-options-link-wrap').hide(); // Hide screen options entirely
                });
                </script>
                <?php
            }
        });
    }

    public function remove_screen_options($show, $screen)
    {
        if (isset($screen->post_type) && $screen->post_type === 'llm_prompt') {
            return false; // Hide screen options for llm_prompt
        }
        return $show;
    }

    public function default_settings_page()
    {
        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $default_library_id = get_option('llm_default_library', '');
        $custom_logo_url = get_option('llm_custom_logo_url', '');
        $logo_size = get_option('llm_logo_size', '32');
        $nav_logo_height = get_option('llm_nav_logo_height', '32');
        $nav_logo_width = get_option('llm_nav_logo_width', '120');
        $selected_nav_menu = get_option('llm_nav_menu', '');
        $hide_admin_bar_for_admins = get_option('llm_hide_admin_bar_for_admins', 'yes');
        
        // Get all registered menus
        $nav_menus = wp_get_nav_menus();

        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success"><p>Default settings updated successfully!</p></div>';
        }
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();

        ?>
        <div class="wrap">
            <h1>Default Settings</h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_default_settings">
                <?php wp_nonce_field('llm_default_settings_nonce'); ?>

                <div class="llm-default-settings" style="margin-top: 20px;">
                    <div class="llm-settings-section">
                        <h2>Dashboard Logo</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Custom Logo</th>
                                <td>
                                    <div class="llm-logo-upload">
                                        <input type="hidden" name="custom_logo_url" id="custom_logo_url" value="<?php echo esc_attr($custom_logo_url); ?>">
                                        <div class="llm-logo-preview" style="margin-bottom: 10px;">
                                            <?php if ($custom_logo_url): ?>
                                                <img src="<?php echo esc_url($custom_logo_url); ?>" 
                                                     style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px;">
                                            <?php else: ?>
                                                <div style="width: 100px; height: 50px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #999;">
                                                    No Image
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button" id="upload_logo_button">
                                            <?php echo $custom_logo_url ? 'Change Logo' : 'Upload Logo'; ?>
                                        </button>
                                        <?php if ($custom_logo_url): ?>
                                            <button type="button" class="button" id="remove_logo_button" style="margin-left: 10px;">Remove Logo</button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">Upload a custom logo for the dashboard sidebar. If no logo is set, the site icon will be used.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Logo Size</th>
                                <td>
                                    <input type="number" name="logo_size" value="<?php echo esc_attr($logo_size); ?>" 
                                           min="16" max="100" step="1" class="small-text"> px
                                    <p class="description">Set the logo size in pixels (width and height will be the same).</p>
                                </td>
                            </tr>
                        </table>
                        
                        <h2>Navigation Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Navigation Logo Height</th>
                                <td>
                                    <input type="number" name="nav_logo_height" value="<?php echo esc_attr($nav_logo_height); ?>" 
                                           min="16" max="100" step="1" class="small-text"> px
                                    <p class="description">Set the navigation logo height in pixels.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Navigation Logo Width</th>
                                <td>
                                    <input type="number" name="nav_logo_width" value="<?php echo esc_attr($nav_logo_width); ?>" 
                                           min="50" max="300" step="1" class="small-text"> px
                                    <p class="description">Set the navigation logo width in pixels.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Navigation Menu</th>
                                <td>
                                    <select name="nav_menu" class="regular-text">
                                        <option value="">No Menu</option>
                                        <?php foreach ($nav_menus as $menu): ?>
                                            <option value="<?php echo $menu->term_id; ?>" 
                                                    <?php selected($selected_nav_menu, $menu->term_id); ?>>
                                                <?php echo esc_html($menu->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Select a WordPress menu to display in the navigation header. The menu will appear in the center of the navigation bar.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Hide Admin Bar for Administrators</th>
                                <td>
                                    <select name="hide_admin_bar_for_admins">
                                        <option value="yes" <?php selected($hide_admin_bar_for_admins, 'yes'); ?>>Yes</option>
                                        <option value="no" <?php selected($hide_admin_bar_for_admins, 'no'); ?>>No</option>
                                    </select>
                                    <p class="description">Choose whether to hide the WordPress admin bar for administrators on dashboard and prompt pages. Admin bar is always hidden for non-admin users.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <h2>Default Library</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Default Library</th>
                                <td>
                                    <select name="default_library" class="regular-text">
                                        <option value="">No Default (Show All Libraries)</option>
                                        <?php foreach ($libraries as $library): ?>
                                            <option value="<?php echo $library->term_id; ?>" 
                                                    <?php selected($default_library_id, $library->term_id); ?>>
                                                <?php echo esc_html($library->name); ?>
                                                (<?php echo $library->count; ?> prompts)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Select the default library that will be shown when users visit the dashboard without a specific library parameter.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('Save Default Settings'); ?>
            </form>

            <?php if (!empty($libraries)): ?>
                <div class="llm-url-examples" style="margin-top: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px;">
                    <h3>Library URLs</h3>
                    <ul style="font-family: monospace; background: white; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                        <?php foreach ($libraries as $library): ?>
                            <li style="margin-bottom: 8px;">
                                <strong><?php echo esc_html($library->name); ?>:</strong><br>
                                <code><?php echo home_url('/aclas-knowledge-hub/?library=' . $library->slug); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('#upload_logo_button').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Choose Logo',
                    button: {
                        text: 'Choose Logo'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#custom_logo_url').val(attachment.url);
                    $('.llm-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px;">');
                    $('#upload_logo_button').text('Change Logo');
                    
                    if ($('#remove_logo_button').length === 0) {
                        $('#upload_logo_button').after('<button type="button" class="button" id="remove_logo_button" style="margin-left: 10px;">Remove Logo</button>');
                    }
                });
                
                mediaUploader.open();
            });
            
            $(document).on('click', '#remove_logo_button', function(e) {
                e.preventDefault();
                $('#custom_logo_url').val('');
                $('.llm-logo-preview').html('<div style="width: 100px; height: 50px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>');
                $('#upload_logo_button').text('Upload Logo');
                $(this).remove();
            });
        });
        </script>
        <?php
    }

    public function save_default_settings()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'llm_default_settings_nonce')) {
            wp_die('Unauthorized');
        }

        $default_library = sanitize_text_field($_POST['default_library']);
        $custom_logo_url = esc_url_raw($_POST['custom_logo_url']);
        $logo_size = intval($_POST['logo_size']);
        $nav_logo_height = intval($_POST['nav_logo_height']);
        $nav_logo_width = intval($_POST['nav_logo_width']);
        $nav_menu = intval($_POST['nav_menu']);
        $hide_admin_bar_for_admins = sanitize_text_field($_POST['hide_admin_bar_for_admins']);
        
        // Validate logo size
        if ($logo_size < 16) $logo_size = 16;
        if ($logo_size > 100) $logo_size = 100;
        
        // Validate nav logo dimensions
        if ($nav_logo_height < 16) $nav_logo_height = 16;
        if ($nav_logo_height > 100) $nav_logo_height = 100;
        if ($nav_logo_width < 50) $nav_logo_width = 50;
        if ($nav_logo_width > 300) $nav_logo_width = 300;
        
        // Validate admin bar setting
        if (!in_array($hide_admin_bar_for_admins, ['yes', 'no'])) {
            $hide_admin_bar_for_admins = 'yes';
        }
        
        update_option('llm_default_library', $default_library);
        update_option('llm_custom_logo_url', $custom_logo_url);
        update_option('llm_logo_size', $logo_size);
        update_option('llm_nav_logo_height', $nav_logo_height);
        update_option('llm_nav_logo_width', $nav_logo_width);
        update_option('llm_nav_menu', $nav_menu);
        update_option('llm_hide_admin_bar_for_admins', $hide_admin_bar_for_admins);

        wp_redirect(admin_url('admin.php?page=llm-default-settings&updated=true'));
        exit;
    }

}