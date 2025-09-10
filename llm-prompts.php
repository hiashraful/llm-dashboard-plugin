<?php
/**
 * Plugin Name: LIBRERIA DIGITALE
 * Plugin URI: https://www.devash.pro/
 * Description: A library of ready-to-use prompts for ChatGPT and other LLMs
 * Version: 2.0.7
 * Author: Dev Ash
 * Author URI: https://www.devash.pro/
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LLM_PROMPTS_URL', plugin_dir_url(__FILE__));
define('LLM_PROMPTS_PATH', plugin_dir_path(__FILE__));

class LLM_Prompts_Plugin
{

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('single_template', array($this, 'load_single_template'));
        add_filter('template_include', array($this, 'override_elementor_template'), 99);
        add_filter('template_include', array($this, 'load_account_template'));
        add_action('user_register', array($this, 'handle_new_user_registration'));
        add_action('set_user_role', array($this, 'handle_user_role_change'), 10, 3);
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        add_action('wp', array($this, 'hide_admin_bar_on_dashboard_pages'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('parse_request', array($this, 'parse_custom_urls'));
        add_filter('post_type_link', array($this, 'custom_prompt_permalink'), 10, 2);
        add_action('template_redirect', array($this, 'redirect_old_urls'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init()
    {
        $this->register_post_types();
        $this->register_taxonomies();
        $this->include_files();
    }

    public function register_post_types()
    {
        register_post_type('llm_prompt', array(
            'labels' => array(
                'name' => 'Prompts',
                'singular_name' => 'Prompt',
                'add_new' => 'Add New Prompt',
                'add_new_item' => 'Add New Prompt',
                'edit_item' => 'Edit Prompt',
                'new_item' => 'New Prompt',
                'view_item' => 'View Prompt',
                'search_items' => 'Search Prompts',
                'not_found' => 'No prompts found',
                'not_found_in_trash' => 'No prompts found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-format-chat',
            'show_in_menu' => false,
            'rewrite' => false
        ));
    }

    public function register_taxonomies()
    {
        register_taxonomy('llm_library', 'llm_prompt', array(
            'labels' => array(
                'name' => 'Librerie',
                'singular_name' => 'Library'
            ),
            'hierarchical' => false,
            'show_admin_column' => true
        ));

        register_taxonomy('llm_topic', 'llm_prompt', array(
            'labels' => array(
                'name' => 'Topics',
                'singular_name' => 'Topic'
            ),
            'hierarchical' => false,
            'show_admin_column' => true
        ));

        register_taxonomy('llm_tag', 'llm_prompt', array(
            'labels' => array(
                'name' => 'Tags',
                'singular_name' => 'Tag'
            ),
            'hierarchical' => false,
            'show_admin_column' => true
        ));
    }

    public function include_files()
    {
        require_once LLM_PROMPTS_PATH . 'includes/admin.php';
        require_once LLM_PROMPTS_PATH . 'includes/frontend.php';
        require_once LLM_PROMPTS_PATH . 'includes/ajax.php';

        new LLM_Prompts_Admin();
        new LLM_Prompts_Frontend();
        new LLM_Prompts_Ajax();
    }

    public function enqueue_frontend_scripts()
    {
        global $post;
        
        $should_load = false;
        
        if (is_page('libreria-digitale') || is_page('libreria-digitale-account') || is_singular('llm_prompt')) {
            $should_load = true;
        }
        
        if ($post && (has_shortcode($post->post_content, 'llm_prompts_dashboard') || has_shortcode($post->post_content, 'aclas_recent_prompts'))) {
            $should_load = true;
        }
        
        if ($should_load) {
            wp_enqueue_style('llm-prompts-style', LLM_PROMPTS_URL . 'assets/style.css', array(), '1.0.18');
            wp_enqueue_script('llm-prompts-script', LLM_PROMPTS_URL . 'assets/script.js', array('jquery'), '1.0.18', true);
            wp_localize_script('llm-prompts-script', 'llm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('llm_prompts_nonce')
            ));

            // Add inline CSS for nav logo dimensions
            $nav_logo_height = get_option('llm_nav_logo_height', '32');
            $nav_logo_width = get_option('llm_nav_logo_width', '120');
            $custom_css = "
                :root {
                    --llm-nav-logo-height: {$nav_logo_height}px;
                    --llm-nav-logo-width: {$nav_logo_width}px;
                }
            ";
            wp_add_inline_style('llm-prompts-style', $custom_css);
        }
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_style('llm-admin-style', LLM_PROMPTS_URL . 'assets/admin-style.css', array(), '1.0.18');
    }

    public function activate()
    {
        $this->register_post_types();
        $this->register_taxonomies();
        $this->add_rewrite_rules();
        flush_rewrite_rules();

        if (!get_page_by_path('libreria-digitale')) {
            wp_insert_post(array(
                'post_title' => 'LIBRERIA DIGITALE',
                'post_name' => 'libreria-digitale',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[llm_prompts_dashboard]'
            ));
        }
        
        if (!get_page_by_path('libreria-digitale-account')) {
            wp_insert_post(array(
                'post_title' => 'Libreria Digitale Account',
                'post_name' => 'libreria-digitale-account',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => ''
            ));
        }
    }

    public function load_single_template($template)
    {
        if (is_singular('llm_prompt')) {
            $plugin_template = LLM_PROMPTS_PATH . 'templates/single-llm_prompt.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function override_elementor_template($template)
    {
        if (is_singular('llm_prompt')) {
            $plugin_template = LLM_PROMPTS_PATH . 'templates/single-llm_prompt.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function load_account_template($template)
    {
        if (is_page('libreria-digitale-account')) {
            $plugin_template = LLM_PROMPTS_PATH . 'templates/libreria-digitale-account.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function handle_new_user_registration($user_id)
    {
        $user = get_userdata($user_id);
        
        if ($user && user_can($user, 'academy_student')) {
            $this->check_special_offer_eligibility($user_id);
        }
    }

    public function handle_user_role_change($user_id, $new_role, $old_roles)
    {
        if ($new_role === 'academy_student') {
            $this->check_special_offer_eligibility($user_id);
        }
    }

    private function check_special_offer_eligibility($user_id)
    {
        $offer_status = get_option('llm_special_offer_status', 'inactive');
        $offer_date = get_option('llm_special_offer_date', '');
        $offer_limit = get_option('llm_special_offer_limit', 50);

        if ($offer_status !== 'active' || empty($offer_date)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        // Check if user is from GHL - only GHL users can be special students
        $is_ghl_user = get_user_meta($user_id, 'registration_source', true) === 'ghl';
        if (!$is_ghl_user) {
            return;
        }

        $registration_date = strtotime($user->user_registered);
        $cutoff_date = strtotime($offer_date);

        if ($registration_date <= $cutoff_date) {
            return;
        }

        $current_special_students = get_users(array(
            'meta_query' => array(
                array(
                    'key'     => '_llm_special_offer_student',
                    'value'   => 'yes',
                    'compare' => '='
                )
            ),
            'fields' => 'ID',
            'count_total' => true
        ));

        if (count($current_special_students) >= $offer_limit) {
            return;
        }

        update_user_meta($user_id, '_llm_special_offer_student', 'yes');
        update_user_meta($user_id, '_llm_special_offer_date', current_time('mysql'));

        do_action('llm_special_offer_assigned', $user_id);
    }

    public static function is_special_offer_student($user_id)
    {
        // Check if explicitly marked as special offer student
        if (get_user_meta($user_id, '_llm_special_offer_student', true) === 'yes') {
            return true;
        }
        
        // Check if user is from GHL - GHL users are considered special offer students
        $registration_source = get_user_meta($user_id, 'registration_source', true);
        return $registration_source === 'ghl';
    }

    public static function is_premium_library($library_id)
    {
        return get_term_meta($library_id, '_llm_premium_library', true) === 'yes';
    }

    public static function get_premium_libraries()
    {
        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $premium_libraries = array();
        
        foreach ($libraries as $library) {
            if (self::is_premium_library($library->term_id)) {
                $premium_libraries[] = $library;
            }
        }
        
        return $premium_libraries;
    }

    public function register_api_endpoints()
    {
        register_rest_route('llm/v1', '/seats-remaining', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_seats_remaining'),
            'permission_callback' => '__return_true'
        ));
        
        // Add CORS preflight support
        register_rest_route('llm/v1', '/seats-remaining', array(
            'methods' => 'OPTIONS',
            'callback' => array($this, 'handle_preflight'),
            'permission_callback' => '__return_true'
        ));
        
        // Add action to handle CORS for all REST requests
        add_action('rest_api_init', function() {
            add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 10, 4);
        });
    }

    public function get_seats_remaining($request)
    {
        $override_limit = $request->get_param('limit');
        $offer_limit = $override_limit ? intval($override_limit) : get_option('llm_special_offer_limit', 50);
        
        $special_students = get_users(array(
            'meta_query' => array(
                array(
                    'key'     => '_llm_special_offer_student',
                    'value'   => 'yes',
                    'compare' => '='
                )
            ),
            'fields' => 'ID',
            'count_total' => true
        ));
        
        $current_count = count($special_students);
        $remaining = max(0, $offer_limit - $current_count);
        $percentage_filled = $offer_limit > 0 ? round(($current_count / $offer_limit) * 100, 2) : 0;
        
        $status = 'available';
        if ($remaining <= 10) {
            $status = 'urgent';
        } elseif ($remaining <= 50) {
            $status = 'low';
        }
        
        $response = array(
            'total_limit' => $offer_limit,
            'current_count' => $current_count,
            'remaining' => $remaining,
            'percentage_filled' => $percentage_filled,
            'status' => $status,
            'last_updated' => current_time('c')
        );
        
        $response = rest_ensure_response($response);
        return $response;
    }

    public function handle_preflight()
    {
        return new WP_REST_Response(null, 200);
    }

    public function add_cors_headers($served, $result, $request, $server)
    {
        // Only add CORS headers for our API endpoints
        if (strpos($request->get_route(), '/llm/v1/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
            header('Access-Control-Allow-Credentials: false');
            header('Access-Control-Max-Age: 86400');
        }
        return $served;
    }

    public function hide_admin_bar_on_dashboard_pages()
    {
        // Get the admin bar setting for administrators
        $hide_admin_bar_for_admins = get_option('llm_hide_admin_bar_for_admins', 'yes');
        
        // If user is admin and setting is set to 'no', don't hide admin bar for admins
        if (current_user_can('manage_options') && $hide_admin_bar_for_admins === 'no') {
            return;
        }
        
        // Check if we're on the dashboard page (contains llm_prompts_dashboard shortcode)
        if (is_page('libreria-digitale')) {
            show_admin_bar(false);
            return;
        }
        
        // Check if we're on the account page
        if (is_page('libreria-digitale-account')) {
            show_admin_bar(false);
            return;
        }
        
        // Check if we're on a single prompt page
        if (is_singular('llm_prompt')) {
            show_admin_bar(false);
            return;
        }
        
        // Check if current page has the dashboard shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, 'llm_prompts_dashboard')) {
            show_admin_bar(false);
            return;
        }
    }

    public function add_rewrite_rules()
    {
        add_rewrite_tag('%library_slug%', '([^&]+)');
        add_rewrite_tag('%prompt_slug%', '([^&]+)');
        add_rewrite_rule('^([^/]+)/([^/]+)/?$', 'index.php?library_slug=$matches[1]&prompt_slug=$matches[2]', 'top');
    }

    public function parse_custom_urls($wp)
    {
        if (isset($wp->query_vars['library_slug']) && isset($wp->query_vars['prompt_slug'])) {
            $library_slug = sanitize_text_field($wp->query_vars['library_slug']);
            $prompt_slug = sanitize_text_field($wp->query_vars['prompt_slug']);

            // Check if library exists
            $library = get_term_by('slug', $library_slug, 'llm_library');
            if (!$library) {
                return;
            }

            // Find prompt by slug that belongs to this library
            $args = array(
                'name' => $prompt_slug,
                'post_type' => 'llm_prompt',
                'post_status' => 'publish',
                'numberposts' => 1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'llm_library',
                        'field' => 'term_id',
                        'terms' => $library->term_id,
                    ),
                ),
            );

            $posts = get_posts($args);
            if ($posts) {
                $wp->query_vars['post_type'] = 'llm_prompt';
                $wp->query_vars['name'] = $prompt_slug;
                unset($wp->query_vars['library_slug']);
                unset($wp->query_vars['prompt_slug']);
            }
        }
    }

    public function custom_prompt_permalink($permalink, $post)
    {
        if ($post->post_type !== 'llm_prompt') {
            return $permalink;
        }

        $libraries = get_the_terms($post->ID, 'llm_library');
        if ($libraries && !is_wp_error($libraries)) {
            $library = reset($libraries);
            return home_url('/' . $library->slug . '/' . $post->post_name . '/');
        }

        return home_url('/llm_prompt/' . $post->post_name . '/');
    }

    public function redirect_old_urls()
    {
        if (is_singular('llm_prompt')) {
            global $post;
            $current_url = $_SERVER['REQUEST_URI'];
            
            if (strpos($current_url, '/llm_prompt/') !== false) {
                $libraries = get_the_terms($post->ID, 'llm_library');
                if ($libraries && !is_wp_error($libraries)) {
                    $library = reset($libraries);
                    $new_url = home_url('/' . $library->slug . '/' . $post->post_name . '/');
                    wp_redirect($new_url, 301);
                    exit;
                }
            }
        }
    }
}

new LLM_Prompts_Plugin();