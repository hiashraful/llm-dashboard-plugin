<?php

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Prompts_Frontend
{
    private $pagination_data = array();

    public function __construct()
    {
        add_shortcode('llm_prompts_dashboard', array($this, 'dashboard_shortcode'));
        add_action('init', array($this, 'handle_access_code'));
    }

    public function handle_access_code()
    {
        // Only handle on dashboard page and when access code is submitted
        if (isset($_POST['llm_access_code']) && is_user_logged_in()) {
            $saved_code = get_option('llm_dashboard_password', '');
            $current_user_id = get_current_user_id();
            
            if ($_POST['llm_access_code'] === $saved_code) {
                $cookie_value = wp_hash($saved_code . $current_user_id);
                $cookie_set = setcookie('llm_code_verified', $cookie_value, time() + (24 * 60 * 60), '/', '', false, true);
                // Also set it immediately in $_COOKIE for current request
                $_COOKIE['llm_code_verified'] = $cookie_value;
            }
        }
    }

    public function dashboard_shortcode($atts)
    {
        
        wp_enqueue_style('llm-prompts-style', LLM_PROMPTS_URL . 'assets/style.css', array(), '1.0.19');
        wp_enqueue_script('llm-prompts-script', LLM_PROMPTS_URL . 'assets/script.js', array('jquery'), '1.0.19', true);
        
        // Get selected library from URL parameter or use default
        $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
        $selected_library_slug = isset($_GET['library']) ? sanitize_text_field($_GET['library']) : '';
        $selected_library = null;
        $default_library_id = get_option('llm_default_library', '');
        
        // Find selected library by slug
        if ($selected_library_slug) {
            foreach ($libraries as $library) {
                if ($library->slug === $selected_library_slug) {
                    $selected_library = $library;
                    break;
                }
            }
        } elseif ($default_library_id) {
            // Use default library if no URL parameter
            foreach ($libraries as $library) {
                if ($library->term_id == $default_library_id) {
                    $selected_library = $library;
                    break;
                }
            }
        }
        
        $premium_libraries = LLM_Prompts_Plugin::get_premium_libraries();
        $premium_library_ids = array_map(function ($lib) {
            return $lib->term_id;
        }, $premium_libraries);
        
        // Create library slug mapping for JavaScript
        $library_slugs = array();
        foreach ($libraries as $library) {
            $library_slugs[$library->term_id] = $library->slug;
        }

        wp_localize_script('llm-prompts-script', 'llm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llm_prompts_nonce'),
            'logout_url' => wp_logout_url(get_permalink()),
            'premium_libraries' => $premium_library_ids,
            'selected_library' => $selected_library ? $selected_library->term_id : '',
            'default_library' => $default_library_id,
            'library_slugs' => $library_slugs
        ));

        if (!is_user_logged_in()) {
            return $this->render_wordpress_login();
        }

        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Admin bypass - allow administrators full access
        if (user_can($user, 'manage_options')) {
            return $this->render_dashboard();
        }

        if (user_can($user, 'academy_student') && LLM_Prompts_Plugin::is_special_offer_student($current_user_id)) {
            return $this->render_dashboard();
        }

        $saved_code = get_option('llm_dashboard_password', '');
        
        // Check if user already has valid cookie (already entered code)
        $expected_cookie = wp_hash($saved_code . $current_user_id);
        $actual_cookie = isset($_COOKIE['llm_code_verified']) ? $_COOKIE['llm_code_verified'] : 'NOT SET';
        
        if (isset($_COOKIE['llm_code_verified']) && $_COOKIE['llm_code_verified'] === $expected_cookie) {
            return $this->render_dashboard();
        }

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
                <h2>ACLAS Knowledge Hub</h2>
                <p>Please log in to Academy to access the knowledge hub.</p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="llm-wp-login-btn">Log in to Academy</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_code_form()
    {
        $user = wp_get_current_user();
        $is_academy_student = user_can($user, 'academy_student');
        $is_special_student = $is_academy_student && LLM_Prompts_Plugin::is_special_offer_student(get_current_user_id());

        ob_start();
        ?>
        <div class="llm-login-container">
            <div class="llm-login-form">
                <h2>ACLAS Knowledge Hub</h2>
                <p>Welcome, <?php echo wp_get_current_user()->display_name; ?>!</p>

                <?php if ($is_academy_student && !$is_special_student): ?>
                    <div class="llm-info-box">
                        <h3>Academy Student</h3>
                        <p>You're an Academy student, but not eligible for the special offer. Please enter the access code to continue.</p>
                    </div>
                <?php endif; ?>

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

        // Get selected library from URL parameter or use default
        $selected_library_slug = isset($_GET['library']) ? sanitize_text_field($_GET['library']) : '';
        $selected_library = null;
        $default_library_id = get_option('llm_default_library', '');
        
        // Find selected library by slug
        if ($selected_library_slug) {
            foreach ($libraries as $library) {
                if ($library->slug === $selected_library_slug) {
                    $selected_library = $library;
                    break;
                }
            }
        } elseif ($default_library_id) {
            // Use default library if no URL parameter
            foreach ($libraries as $library) {
                if ($library->term_id == $default_library_id) {
                    $selected_library = $library;
                    break;
                }
            }
        }
        
        // Check if selected library is premium and show overlay if needed
        $show_premium_overlay = false;
        if ($selected_library && LLM_Prompts_Plugin::is_premium_library($selected_library->term_id)) {
            $show_premium_overlay = true;
        }
        
        // Determine header title
        $header_title = $selected_library ? $selected_library->name : 'ACLAS Knowledge Hub';

        ob_start();
        ?>
        <!-- Navigation Header -->
        <div class="llm-nav-header">
            <div class="llm-nav-container">
                <div class="llm-nav-item">
                    <?php
                    $custom_logo_url = get_option('llm_custom_logo_url', '');
                    if ($custom_logo_url): ?>
                        <img src="<?php echo esc_url($custom_logo_url); ?>" alt="Your Logo" class="llm-nav-logo">
                    <?php else:
                        $site_icon_url = get_site_icon_url();
                        if ($site_icon_url): ?>
                            <img src="<?php echo esc_url($site_icon_url); ?>" alt="Your Logo" class="llm-nav-logo">
                        <?php else: ?>
                            <span class="llm-nav-logo-text">Your Logo</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="llm-nav-item">
                    <?php
                    $nav_menu_id = get_option('llm_nav_menu', '');
                    if ($nav_menu_id && wp_get_nav_menu_object($nav_menu_id)): 
                        wp_nav_menu(array(
                            'menu' => $nav_menu_id,
                            'container' => false,
                            'menu_class' => 'llm-nav-menu',
                            'fallback_cb' => false,
                            'depth' => 1
                        ));
                    else: ?>
                        <span>Dashboard</span>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Hamburger Menu -->
                <div class="llm-hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div class="llm-mobile-menu">
            <div class="llm-mobile-menu-header">
                <div class="llm-nav-item">
                    <?php if ($custom_logo_url): ?>
                        <img src="<?php echo esc_url($custom_logo_url); ?>" alt="Your Logo" class="llm-nav-logo">
                    <?php else:
                        $site_icon_url = get_site_icon_url();
                        if ($site_icon_url): ?>
                            <img src="<?php echo esc_url($site_icon_url); ?>" alt="Your Logo" class="llm-nav-logo">
                        <?php else: ?>
                            <span class="llm-nav-logo-text">Your Logo</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="llm-mobile-close"></div>
            </div>
            <nav class="llm-mobile-menu-nav">
                <?php
                if ($nav_menu_id && wp_get_nav_menu_object($nav_menu_id)): 
                    wp_nav_menu(array(
                        'menu' => $nav_menu_id,
                        'container' => false,
                        'menu_class' => '',
                        'fallback_cb' => false,
                        'depth' => 1
                    ));
                else: ?>
                    <a href="#">Dashboard</a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="llm-dashboard">
            <div class="llm-sidebar">
                

                <!-- Sidebar header with logo and search -->
                <div class="llm-sidebar-header">
                    <div class="llm-logo">
                        <?php
                        $custom_logo_url = get_option('llm_custom_logo_url', '');
                        $logo_size = get_option('llm_logo_size', '32');
                        
                        if ($custom_logo_url): ?>
                            <img src="<?php echo esc_url($custom_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                                style="width: <?php echo $logo_size; ?>px; height: <?php echo $logo_size; ?>px; object-fit: contain;">
                        <?php else:
                            $site_icon_url = get_site_icon_url();
                            if ($site_icon_url): ?>
                                <img src="<?php echo esc_url($site_icon_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                                    style="width: <?php echo $logo_size; ?>px; height: <?php echo $logo_size; ?>px; object-fit: contain;">
                            <?php else: ?>
                                <span><?php echo esc_html(get_bloginfo('name')); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <svg class="llm-search-icon" id="llm-search-trigger" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </div>

                <!-- Search overlay -->
                <div class="llm-search-overlay" id="llm-search-overlay">
                    <div class="llm-search-container">
                        <input type="text" id="llm-search-input" placeholder="Search prompts, topics, tags...">
                    </div>
                </div>

                <!-- Filter sections -->
                <div class="llm-filter-sections">
                    <!-- Select Library -->
                    <div class="llm-filter-element" id="library-filter">
                        <div class="llm-filter-header">
                            <svg class="llm-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                            </svg>
                            <span class="llm-filter-title">SELEZIONA LIBRERIA</span>
                            <svg class="llm-filter-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        <div class="llm-filter-content">
                            <select id="llm-library-filter">
                                <?php foreach ($libraries as $library):
                                    $is_premium = LLM_Prompts_Plugin::is_premium_library($library->term_id);
                                    $is_selected = ($selected_library && $selected_library->term_id == $library->term_id);
                                    ?>
                                    <option value="<?php echo $library->term_id; ?>"
                                        data-premium="<?php echo $is_premium ? '1' : '0'; ?>"
                                        <?php selected($is_selected); ?>>
                                        <?php echo $library->name; ?>
                                        <?php if ($is_premium): ?> 🔒<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Video Tutorial -->
                    <div class="llm-filter-element" id="video-filter">
                        <div class="llm-filter-header">
                            <svg class="llm-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                            <span class="llm-filter-title">VIDEO TUTORIAL</span>
                            <label class="llm-checkbox-label" style="margin: 0; cursor: pointer;">
                                <input type="checkbox" id="llm-video-filter" style="margin: 0;">
                            </label>
                        </div>
                    </div>

                    <!-- Filter by Topic -->
                    <div class="llm-filter-element" id="topic-filter">
                        <div class="llm-filter-header">
                            <svg class="llm-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 2l3 3 3-3 3 3v12l-3-3-3 3-3-3-3 3V5z"></path>
                            </svg>
                            <span class="llm-filter-title">FILTRA PER ARGOMENTO</span>
                            <svg class="llm-filter-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        <div class="llm-filter-content">
                            <?php foreach ($topics as $topic): ?>
                                <label class="llm-checkbox-label">
                                    <input type="checkbox" class="llm-topic-filter" value="<?php echo $topic->term_id; ?>">
                                    <?php echo $topic->name; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Filter by Tag -->
                    <div class="llm-filter-element" id="tag-filter">
                        <div class="llm-filter-header">
                            <svg class="llm-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                            <span class="llm-filter-title">FILTRA PER TAG</span>
                            <svg class="llm-filter-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        <div class="llm-filter-content">
                            <?php foreach ($tags as $tag): ?>
                                <label class="llm-checkbox-label">
                                    <input type="checkbox" class="llm-tag-filter" value="<?php echo $tag->term_id; ?>">
                                    <?php echo $tag->name; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- User section at bottom -->
                <div class="llm-user-section">
                    <div class="llm-user-info">
                        <div class="llm-user-icon"><?php echo substr(wp_get_current_user()->display_name, 0, 1); ?></div>
                        <span><?php echo wp_get_current_user()->display_name; ?></span>
                    </div>
                    <a href="<?php echo wp_logout_url(home_url('/aclas-knowledge-hub/')); ?>">
                        <svg class="llm-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16,17 21,12 16,7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="llm-content">
                <div class="llm-header">
                    <h1><?php echo esc_html($header_title); ?></h1>
                    <?php
                    $current_user_id = get_current_user_id();
                    $user = wp_get_current_user();
                    $is_special_student = user_can($user, 'academy_student') && LLM_Prompts_Plugin::is_special_offer_student($current_user_id);
                    ?>
                </div>

                <div id="llm-prompts-feed" class="llm-prompts-feed">
                    <?php echo $this->get_initial_prompts(); ?>
                </div>

                <div id="llm-pagination" class="llm-pagination" <?php if ($this->pagination_data['found_posts'] == 0 || $this->pagination_data['max_num_pages'] <= 1) echo 'style="display:none;"'; ?>>
                    <a href="#" class="llm-pagination-arrow disabled" id="llm-prev-page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="15,18 9,12 15,6"></polyline>
                        </svg>
                    </a>
                    <div class="llm-pagination-numbers" id="llm-pagination-numbers">
                        <?php echo $this->generate_pagination_numbers(); ?>
                    </div>
                    <a href="#" class="llm-pagination-arrow<?php if ($this->pagination_data['current_page'] >= $this->pagination_data['max_num_pages']) echo ' disabled'; ?>" id="llm-next-page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="9,18 15,12 9,6"></polyline>
                        </svg>
                    </a>
                </div>

                <div id="llm-no-results" class="llm-no-results" <?php if ($this->pagination_data['found_posts'] > 0) echo 'style="display:none;"'; ?>>
                    No items found.
                </div>

                <div id="llm-premium-overlay" class="llm-premium-overlay" style="display:<?php echo $show_premium_overlay ? 'flex' : 'none'; ?>;">
                    <div class="llm-premium-content">
                        <div class="llm-premium-header">
                            <h2><?php echo esc_html(get_option('llm_premium_title', '🔒 Premium Library')); ?></h2>
                        </div>

                        <div class="llm-premium-message">
                            <?php
                            $premium_message = get_option('llm_premium_message', "⭐ Coming Soon!\n\nThis premium library is currently under development and will be available soon with exclusive high-quality prompts.");
                            echo nl2br(esc_html($premium_message));
                            ?>
                        </div>

                        <div class="llm-premium-actions">
                            <?php
                            $button_url = get_option('llm_premium_button_url', '#');
                            $button_text = get_option('llm_premium_button_text', '🚀 Get Premium Access');

                            if ($button_url && $button_url !== '#'): ?>
                                <a href="<?php echo esc_url($button_url); ?>" class="llm-premium-button" target="_blank">
                                    <?php echo esc_html($button_text); ?>
                                </a>
                            <?php endif; ?>

                            <button id="llm-back-to-dashboard" class="llm-back-button">
                                ← Back to Libraries
                            </button>
                        </div>
                    </div>
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

        // Get selected library from URL parameter or use default
        $selected_library_slug = isset($_GET['library']) ? sanitize_text_field($_GET['library']) : '';
        $selected_library = null;
        $default_library_id = get_option('llm_default_library', '');
        
        // Find selected library by slug
        if ($selected_library_slug) {
            $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
            foreach ($libraries as $library) {
                if ($library->slug === $selected_library_slug) {
                    $selected_library = $library;
                    break;
                }
            }
        } elseif ($default_library_id) {
            // Use default library if no URL parameter
            $libraries = get_terms(array('taxonomy' => 'llm_library', 'hide_empty' => false));
            foreach ($libraries as $library) {
                if ($library->term_id == $default_library_id) {
                    $selected_library = $library;
                    break;
                }
            }
        }

        $tax_queries = array();

        // Filter by selected library if one is chosen (but don't show prompts if it's premium)
        if ($selected_library && !LLM_Prompts_Plugin::is_premium_library($selected_library->term_id)) {
            $tax_queries[] = array(
                'taxonomy' => 'llm_library',
                'field' => 'term_id',
                'terms' => array($selected_library->term_id),
                'operator' => 'IN'
            );
        } elseif ($selected_library && LLM_Prompts_Plugin::is_premium_library($selected_library->term_id)) {
            // If premium library is selected, don't show any prompts (overlay will be shown instead)
            $tax_queries[] = array(
                'taxonomy' => 'llm_library',
                'field' => 'term_id',
                'terms' => array(-1), // Non-existent term ID to return no results
                'operator' => 'IN'
            );
        } else {
            // Exclude premium libraries when no specific library is selected
            $premium_libraries = LLM_Prompts_Plugin::get_premium_libraries();
            if (!empty($premium_libraries)) {
                $premium_library_ids = array_map(function ($lib) {
                    return $lib->term_id;
                }, $premium_libraries);
                $tax_queries[] = array(
                    'taxonomy' => 'llm_library',
                    'field' => 'term_id',
                    'terms' => $premium_library_ids,
                    'operator' => 'NOT IN'
                );
            }
        }

        if (!empty($tax_queries)) {
            $args['tax_query'] = $tax_queries;
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

        // Store pagination data for use in template
        $this->pagination_data = array(
            'found_posts' => $prompts->found_posts,
            'max_num_pages' => $prompts->max_num_pages,
            'current_page' => 1
        );

        return $html;
    }

    private function generate_pagination_numbers() 
    {
        $max_pages = $this->pagination_data['max_num_pages'];
        $current_page = $this->pagination_data['current_page'];
        
        if ($max_pages <= 1) {
            return '<a href="#" class="llm-pagination-number active" data-page="1">1</a>';
        }
        
        $html = '';
        
        // Always show first page
        $active = ($current_page == 1) ? ' active' : '';
        $html .= '<a href="#" class="llm-pagination-number' . $active . '" data-page="1">1</a>';
        
        if ($max_pages > 1) {
            // Show pages around current page
            $start_page = max(2, $current_page - 1);
            $end_page = min($max_pages - 1, $current_page + 1);
            
            // Add dots if needed
            if ($start_page > 2) {
                $html .= '<span class="llm-pagination-dots">...</span>';
            }
            
            // Add middle pages
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i !== 1 && $i !== $max_pages) {
                    $active = ($current_page == $i) ? ' active' : '';
                    $html .= '<a href="#" class="llm-pagination-number' . $active . '" data-page="' . $i . '">' . $i . '</a>';
                }
            }
            
            // Add dots if needed
            if ($end_page < $max_pages - 1) {
                $html .= '<span class="llm-pagination-dots">...</span>';
            }
            
            // Always show last page if different from first
            if ($max_pages > 1) {
                $active = ($current_page == $max_pages) ? ' active' : '';
                $html .= '<a href="#" class="llm-pagination-number' . $active . '" data-page="' . $max_pages . '">' . $max_pages . '</a>';
            }
        }
        
        return $html;
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
                        <path d="M7 17L17 7M9 7H17V15" stroke="white" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </div>
        <?php
    }
}