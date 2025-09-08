<?php

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Prompts_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_filter_prompts', array($this, 'filter_prompts'));
        add_action('wp_ajax_nopriv_filter_prompts', array($this, 'filter_prompts'));
        add_action('wp_ajax_load_more_prompts', array($this, 'load_more_prompts'));
        add_action('wp_ajax_nopriv_load_more_prompts', array($this, 'load_more_prompts'));
        add_action('wp_ajax_verify_access_code', array($this, 'verify_access_code'));
        add_action('wp_ajax_nopriv_verify_access_code', array($this, 'verify_access_code'));
        add_action('wp_ajax_llm_set_auth_cookie', array($this, 'set_auth_cookie'));
        add_action('wp_ajax_nopriv_llm_set_auth_cookie', array($this, 'set_auth_cookie'));
        add_action('wp_ajax_llm_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_nopriv_llm_logout', array($this, 'handle_logout'));
    }

    public function verify_access_code()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'llm_prompts_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to WordPress'));
            return;
        }

        $entered_code = sanitize_text_field($_POST['access_code']);
        $saved_code = get_option('llm_dashboard_password', '');

        if (empty($saved_code)) {
            wp_send_json_error(array('message' => 'No access code set in admin'));
            return;
        }

        if ($entered_code === $saved_code) {
            setcookie('llm_code_verified', wp_hash($saved_code . get_current_user_id()), time() + (24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN);
            wp_send_json_success(array('message' => 'Access code verified'));
        } else {
            wp_send_json_error(array('message' => 'Invalid access code'));
        }
    }

    public function set_auth_cookie()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'llm_set_auth_cookie')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        $user_id = intval($_POST['user_id']);
        
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(array('message' => 'Invalid user'));
            return;
        }

        // Set authentication cookies
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success(array('message' => 'Authentication set'));
    }

    public function handle_logout()
    {
        // Clear the access code cookie with proper domain settings
        setcookie('llm_code_verified', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false, true);
        
        // Also try clearing with root path and various domain combinations
        setcookie('llm_code_verified', '', time() - 3600, '/', '', false, true);
        setcookie('llm_code_verified', '', time() - 3600, '/', COOKIE_DOMAIN, false, true);
        
        // Clear WordPress auth cookies with different paths and domains
        wp_clear_auth_cookie();
        wp_set_current_user(0);
        
        // Also manually clear WordPress auth cookies
        setcookie(LOGGED_IN_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false, true);
        setcookie(LOGGED_IN_COOKIE, '', time() - 3600, '/', '', false, true);
        setcookie(AUTH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false, true);
        setcookie(AUTH_COOKIE, '', time() - 3600, '/', '', false, true);
        setcookie(SECURE_AUTH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false, true);
        setcookie(SECURE_AUTH_COOKIE, '', time() - 3600, '/', '', false, true);
        
        // Destroy session if exists
        if (session_id()) {
            session_destroy();
        }
        
        // Clear any PHP session data
        $_SESSION = array();
        
        wp_send_json_success(array('message' => 'Logged out successfully'));
    }

    public function filter_prompts()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'llm_prompts_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
            return;
        }

        $library = sanitize_text_field($_POST['library']);
        $search = sanitize_text_field($_POST['search']);
        $sort = sanitize_text_field($_POST['sort']);
        $video_only = $_POST['video_only'] === 'true';
        $topics = isset($_POST['topics']) ? array_map('intval', $_POST['topics']) : array();
        $tags = isset($_POST['tags']) ? array_map('intval', $_POST['tags']) : array();
        $page = intval($_POST['page']);
        $per_page = 10;

        $args = array(
            'post_type' => 'llm_prompt',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish'
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $tax_query = array();

        if (!empty($library)) {
            $tax_query[] = array(
                'taxonomy' => 'llm_library',
                'field' => 'term_id',
                'terms' => $library
            );
        } else {
            // Exclude premium libraries when no specific library is selected
            $premium_libraries = LLM_Prompts_Plugin::get_premium_libraries();
            if (!empty($premium_libraries)) {
                $premium_library_ids = array_map(function($lib) { return $lib->term_id; }, $premium_libraries);
                $tax_query[] = array(
                    'taxonomy' => 'llm_library',
                    'field' => 'term_id',
                    'terms' => $premium_library_ids,
                    'operator' => 'NOT IN'
                );
            }
        }

        if (!empty($topics)) {
            $tax_query[] = array(
                'taxonomy' => 'llm_topic',
                'field' => 'term_id',
                'terms' => $topics,
                'operator' => 'IN'
            );
        }

        if (!empty($tags)) {
            $tax_query[] = array(
                'taxonomy' => 'llm_tag',
                'field' => 'term_id',
                'terms' => $tags,
                'operator' => 'IN'
            );
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        if ($video_only) {
            $args['meta_query'] = array(
                array(
                    'key' => '_llm_tutorial_video',
                    'value' => '',
                    'compare' => '!='
                )
            );
        }

        switch ($sort) {
            case 'oldest':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'name_asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'name_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }

        $prompts = new WP_Query($args);

        ob_start();
        if ($prompts->have_posts()) {
            while ($prompts->have_posts()) {
                $prompts->the_post();
                $this->render_prompt_card(get_post());
            }
        }
        $html = ob_get_clean();

        wp_reset_postdata();

        wp_send_json_success(array(
            'html' => $html,
            'found_posts' => $prompts->found_posts,
            'max_num_pages' => $prompts->max_num_pages,
            'current_page' => $page
        ));
    }

    public function load_more_prompts()
    {
        $this->filter_prompts();
    }

    private function render_prompt_card($post)
    {
        $short_description = get_post_meta($post->ID, '_llm_short_description', true);
        $tutorial_video = get_post_meta($post->ID, '_llm_tutorial_video', true);
        $topics = get_the_terms($post->ID, 'llm_topic');
        $tags = get_the_terms($post->ID, 'llm_tag');
        $featured_image = get_the_post_thumbnail_url($post->ID, 'medium');

        $topic_colors = array('#8B5CF6', '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#EC4899');
        $tag_colors = array('#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F59E0B', '#10B981');
        ?>
        <div class="llm-prompt-card" data-post-id="<?php echo $post->ID; ?>"
            data-has-video="<?php echo !empty($tutorial_video) ? '1' : '0'; ?>">
            <div class="card-top">
                <?php if ($featured_image): ?>
                    <div class="llm-prompt-image">
                        <img src="<?php echo $featured_image; ?>" alt="<?php echo get_the_title($post); ?>">
                    </div>
                <?php endif; ?>
                <div class="card-heading">
                    <h3 class="llm-prompt-title"><?php echo get_the_title($post); ?></h3>
                    <?php if ($short_description): ?>
                        <p class="llm-prompt-description"><?php echo esc_html($short_description); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-detail">
                <div class="llm-prompt-tags">
                        <?php 
                        $tag_count = 0;
                        $max_tags = 10;
                        
                        if ($topics): ?>
                            <?php foreach ($topics as $index => $topic): 
                                if ($tag_count >= $max_tags) break;
                            ?>
                                <span class="llm-topic-tag" data-topic-id="<?php echo $topic->term_id; ?>"
                                    style="background-color: <?php echo $topic_colors[$index % count($topic_colors)]; ?>;"><?php echo $topic->name; ?></span>
                            <?php 
                                $tag_count++;
                            endforeach; ?>
                        <?php endif; ?>

                        <?php if ($tags && $tag_count < $max_tags): ?>
                            <?php foreach ($tags as $index => $tag): 
                                if ($tag_count >= $max_tags) break;
                            ?>
                                <span class="llm-tag-tag" data-tag-id="<?php echo $tag->term_id; ?>"
                                    style="background-color: <?php echo $tag_colors[$index % count($tag_colors)]; ?>;"><?php echo $tag->name; ?></span>
                            <?php 
                                $tag_count++;
                            endforeach; ?>
                        <?php endif; ?>
                    </div>
                <a href="<?php echo get_permalink($post); ?>" target="_blank" rel="noopener noreferrer"
                    class="llm-prompt-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 17L17 7M9 7H17V15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
            </div>
        </div>
        <?php
    }
}