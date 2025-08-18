<?php

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Prompts_Frontend
{

    public function __construct()
    {
        add_shortcode('llm_prompts_dashboard', array($this, 'dashboard_shortcode'));
    }

    public function dashboard_shortcode($atts)
    {
        wp_enqueue_style('llm-prompts-style', LLM_PROMPTS_URL . 'assets/style.css', array(), '1.0.18');
        wp_enqueue_script('llm-prompts-script', LLM_PROMPTS_URL . 'assets/script.js', array('jquery'), '1.0.18', true);
        wp_localize_script('llm-prompts-script', 'llm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llm_prompts_nonce'),
            'logout_url' => wp_logout_url(get_permalink())
        ));

        if (!is_user_logged_in()) {
            return $this->render_wordpress_login();
        }

        $saved_code = get_option('llm_dashboard_password', '');

        if (isset($_POST['llm_access_code']) && $_POST['llm_access_code'] === $saved_code) {
            return $this->render_dashboard();
        }

        return $this->render_code_form();
    }

    private function render_wordpress_login()
    {
        ob_start();
        ?>
        <div class="llm-login-container">
            <div class="llm-login-form">
                <h2>LLM Prompts Dashboard</h2>
                <p>Please log in to WordPress to access the dashboard.</p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="llm-wp-login-btn">Log in to WordPress</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_code_form()
    {
        ob_start();
        ?>
        <div class="llm-login-container">
            <div class="llm-login-form">
                <h2>LLM Prompts Dashboard</h2>
                <p>Welcome, <?php echo wp_get_current_user()->display_name; ?>! Please enter the access code.</p>
                <form method="post">
                    <input type="text" name="llm_access_code" placeholder="Access Code" required>
                    <button type="submit">Access Dashboard</button>
                </form>
                <?php if (isset($_POST['llm_access_code'])): ?>
                    <div class="llm-error">Incorrect access code. Please try again.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_dashboard()
    {
        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $topics = get_terms(array('taxonomy' => 'llm_topic', 'hide_empty' => false));
        $tags = get_terms(array('taxonomy' => 'llm_tag', 'hide_empty' => false));

        ob_start();
        ?>
        <div class="llm-dashboard">
            <div class="llm-sidebar">
                <div class="llm-filter-section">
                    <label>Select library</label>
                    <select id="llm-library-filter">
                        <option value="">All Libraries</option>
                        <?php foreach ($libraries as $library): ?>
                            <option value="<?php echo $library->term_id; ?>"><?php echo $library->name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="llm-filter-section">
                    <label>Search any keyword, topic or tag</label>
                    <input type="text" id="llm-search-input" placeholder="Search">
                </div>

                <div class="llm-filter-section">
                    <label>Sort by</label>
                    <select id="llm-sort-filter">
                        <option value="newest">Newest to oldest</option>
                        <option value="oldest">Oldest to newest</option>
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                    </select>
                </div>

                <div class="llm-filter-section">
                    <label>
                        <input type="checkbox" id="llm-video-filter"> Video Tutorial
                    </label>
                    <p class="llm-filter-description">When checked, only shows prompts with video explained.</p>
                </div>

                <div class="llm-filter-section">
                    <label>Filter by topic</label>
                    <div class="llm-checkbox-container" id="llm-topics-container">
                        <?php
                        $visible_topics = array_slice($topics, 0, 5);
                        $hidden_topics = array_slice($topics, 5);

                        foreach ($visible_topics as $topic): ?>
                            <label class="llm-checkbox-label">
                                <input type="checkbox" class="llm-topic-filter" value="<?php echo $topic->term_id; ?>">
                                <?php echo $topic->name; ?>
                            </label>
                        <?php endforeach; ?>

                        <?php if (count($hidden_topics) > 0): ?>
                            <div class="llm-hidden-checkboxes" id="llm-hidden-topics">
                                <?php foreach ($hidden_topics as $topic): ?>
                                    <label class="llm-checkbox-label">
                                        <input type="checkbox" class="llm-topic-filter" value="<?php echo $topic->term_id; ?>">
                                        <?php echo $topic->name; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="llm-filter-section">
                    <label>Filter by tag</label>
                    <div class="llm-checkbox-container" id="llm-tags-container">
                        <?php
                        $visible_tags = array_slice($tags, 0, 5);
                        $hidden_tags = array_slice($tags, 5);

                        foreach ($visible_tags as $tag): ?>
                            <label class="llm-checkbox-label">
                                <input type="checkbox" class="llm-tag-filter" value="<?php echo $tag->term_id; ?>">
                                <?php echo $tag->name; ?>
                            </label>
                        <?php endforeach; ?>

                        <?php if (count($hidden_tags) > 0): ?>
                            <div class="llm-hidden-checkboxes" id="llm-hidden-tags">
                                <?php foreach ($hidden_tags as $tag): ?>
                                    <label class="llm-checkbox-label">
                                        <input type="checkbox" class="llm-tag-filter" value="<?php echo $tag->term_id; ?>">
                                        <?php echo $tag->name; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="llm-sidebar-logout-btn-container">
                    <a href="<?php echo wp_logout_url(home_url('/dashboard/')); ?>" class="llm-logout-btn">Logout</a>
                </div>
            </div>

            <div class="llm-content">
                <div class="llm-header">
                    <h1>LLM Prompts</h1>
                    <p>A library of ready-to-use prompts for ChatGPT and other language models. They'll help you write better,
                        solve problems faster, and get more done.</p>
                    <p><strong>Not sure where to start? Try filtering by tag for 'Staff Pick' or 'Popular' to find our
                            favorites.</strong></p>
                </div>

                <div id="llm-prompts-feed" class="llm-prompts-feed">
                    <?php echo $this->get_initial_prompts(); ?>
                </div>

                <div id="llm-load-more-container" class="llm-load-more-container">
                    <button id="llm-load-more" class="llm-load-more">LOAD MORE</button>
                </div>

                <div id="llm-no-results" class="llm-no-results" style="display:none;">
                    No items found.
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_initial_prompts()
    {
        $args = array(
            'post_type' => 'llm_prompt',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $prompts = new WP_Query($args);

        ob_start();
        if ($prompts->have_posts()) {
            while ($prompts->have_posts()) {
                $prompts->the_post();
                $this->render_prompt_card(get_post());
            }
        }
        wp_reset_postdata();

        return ob_get_clean();
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
                    <div class="llm-prompt-tags">
                        <?php if ($topics): ?>
                            <?php foreach ($topics as $index => $topic): ?>
                                <span class="llm-topic-tag" data-topic-id="<?php echo $topic->term_id; ?>"
                                    style="background-color: <?php echo $topic_colors[$index % count($topic_colors)]; ?>;"><?php echo $topic->name; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($tags): ?>
                            <?php foreach ($tags as $index => $tag): ?>
                                <span class="llm-tag-tag" data-tag-id="<?php echo $tag->term_id; ?>"
                                    style="background-color: <?php echo $tag_colors[$index % count($tag_colors)]; ?>;"><?php echo $tag->name; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <h3 class="llm-prompt-title"><?php echo get_the_title($post); ?></h3>
                </div>
            </div>
            <div class="card-detail">
                <?php if ($short_description): ?>
                    <p class="llm-prompt-description"><?php echo esc_html($short_description); ?></p>
                <?php endif; ?>
                <a href="<?php echo get_permalink($post); ?>" target="_blank" rel="noopener noreferrer"
                    class="llm-prompt-arrow">
                    <svg width="31" height="31" viewBox="0 0 31 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.599609" y="0.100098" width="30.4" height="30.4" rx="5.13" fill="black"></rect>
                        <path d="M8.95898 15.3002H22.639M22.639 15.3002L17.509 10.1702M22.639 15.3002L17.509 20.4302"
                            stroke="white" stroke-width="1.2825" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
            </div>
        </div>
        <?php
    }
}