<?php
/**
 * Plugin Name: LLM Prompts Dashboard
 * Plugin URI: https://www.devash.pro/
 * Description: A library of ready-to-use prompts for ChatGPT and other LLMs
 * Version: 1.0.29
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
            'show_in_menu' => false
        ));
    }

    public function register_taxonomies()
    {
        register_taxonomy('llm_library', 'llm_prompt', array(
            'labels' => array(
                'name' => 'Libraries',
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
        
        if (is_page('llm-dashboard') || is_singular('llm_prompt')) {
            $should_load = true;
        }
        
        if ($post && has_shortcode($post->post_content, 'llm_prompts_dashboard')) {
            $should_load = true;
        }
        
        if ($should_load) {
            wp_enqueue_style('llm-prompts-style', LLM_PROMPTS_URL . 'assets/style.css', array(), '1.0.18');
            wp_enqueue_script('llm-prompts-script', LLM_PROMPTS_URL . 'assets/script.js', array('jquery'), '1.0.18', true);
            wp_localize_script('llm-prompts-script', 'llm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('llm_prompts_nonce')
            ));
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
        flush_rewrite_rules();

        if (!get_page_by_path('llm-dashboard')) {
            wp_insert_post(array(
                'post_title' => 'LLM Dashboard',
                'post_name' => 'llm-dashboard',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[llm_prompts_dashboard]'
            ));
        }
    }

    public function load_single_template($template)
    {
        if (is_singular('llm_prompt')) {
            $plugin_template = LLM_PROMPTS_PATH . 'single-llm_prompt.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function override_elementor_template($template)
    {
        if (is_singular('llm_prompt')) {
            $plugin_template = LLM_PROMPTS_PATH . 'single-llm_prompt.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}

new LLM_Prompts_Plugin();