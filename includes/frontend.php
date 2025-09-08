<?php

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Prompts_Frontend
{
    private $pagination_data = array();
    private $login_error = '';
    private $login_success = false;
    private $login_user_id = 0;

    public function __construct()
    {
        add_shortcode('llm_prompts_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('aclas_recent_prompts', array($this, 'recent_prompts_shortcode'));
        add_action('init', array($this, 'handle_access_code'));
        add_action('init', array($this, 'handle_custom_logout'));
    }

    public function process_login_form()
    {
        // Handle custom login form submission
        if (isset($_POST['llm_login_submit']) && isset($_POST['llm_login_nonce'])) {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['llm_login_nonce'], 'llm_custom_login')) {
                $this->login_error = 'Security check failed. Please try again.';
                return;
            }

            // Rate limiting check
            $ip = $_SERVER['REMOTE_ADDR'];
            $transient_key = 'llm_login_attempts_' . md5($ip);
            $attempts = get_transient($transient_key);
            
            if ($attempts && $attempts >= 5) {
                $this->login_error = 'Too many login attempts. Please wait 15 minutes before trying again.';
                return;
            }

            $email = sanitize_email($_POST['user_email']);
            $password = sanitize_text_field($_POST['user_password']);

            if (empty($email) || empty($password)) {
                $this->login_error = 'Please fill in both email and password.';
                $this->increment_login_attempts($transient_key);
                return;
            }

            if (!is_email($email)) {
                $this->login_error = 'Please enter a valid email address.';
                $this->increment_login_attempts($transient_key);
                return;
            }

            // Authenticate user
            $user = wp_authenticate($email, $password);

            if (is_wp_error($user)) {
                $this->login_error = 'Invalid email or password.';
                $this->increment_login_attempts($transient_key);
                return;
            }

            // Check if user has required role
            if (!user_can($user, 'academy_student') && !user_can($user, 'manage_options')) {
                $this->login_error = 'You don\'t have permission to access the Knowledge Hub.';
                $this->increment_login_attempts($transient_key);
                return;
            }

            // Clear failed attempts on successful login
            delete_transient($transient_key);

            // Store user ID for cookie setting via JavaScript
            $this->login_user_id = $user->ID;
            
            // Mark successful login for redirect handling
            $this->login_success = true;
        }
    }

    private function increment_login_attempts($transient_key)
    {
        $attempts = get_transient($transient_key) ?: 0;
        $attempts++;
        set_transient($transient_key, $attempts, 15 * MINUTE_IN_SECONDS);
    }

    public function handle_custom_logout()
    {
        // Handle logout from URL parameter (fallback method)
        if (isset($_GET['llm_logout']) && $_GET['llm_logout'] === '1') {
            // Use JavaScript redirect to avoid header issues
            echo '<script>
                jQuery.post("' . admin_url('admin-ajax.php') . '", {
                    action: "llm_logout"
                }).always(function() {
                    window.location.href = "' . remove_query_arg('llm_logout', get_permalink()) . '";
                });
            </script>';
            exit;
        }
        
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
        // Process login form if submitted (do this first)
        $this->process_login_form();

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
            'logout_url' => add_query_arg('llm_logout', '1', get_permalink()),
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

        // Special students get direct access (no code required)
        if (user_can($user, 'academy_student') && LLM_Prompts_Plugin::is_special_offer_student($current_user_id)) {
            return $this->render_dashboard();
        }

        // Check if user has academy_student role - only students can access
        if (!user_can($user, 'academy_student')) {
            return $this->render_access_denied();
        }

        // Regular academy students need code verification
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

    private function render_access_denied()
    {
        ob_start();
        ?>
        <div class="llm-login-container">
            <div class="llm-login-form">
                <h2>Access Denied</h2>
                <p>You don't have permission to access the ACLAS Knowledge Hub. This area is restricted to Academy students
                    only.</p>
                <p>If you believe this is an error, please contact support.</p>
                <a href="<?php echo home_url(); ?>" class="llm-wp-login-btn">Go to Homepage</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_wordpress_login()
    {
        ob_start();
        ?>
        <div class="llm-login-container">
            <div class="llm-login-form">
                <h2>ACLAS Knowledge Hub</h2>
                <p>Please log in to access the knowledge hub.</p>
                
                <?php if (!empty($this->login_error)): ?>
                    <div class="llm-error" style="margin-bottom: 20px; padding: 12px; background: #fee; border: 1px solid #fcc; border-radius: 8px; color: #c33;">
                        <?php echo esc_html($this->login_error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <?php wp_nonce_field('llm_custom_login', 'llm_login_nonce'); ?>
                    
                    <input type="email" 
                           name="user_email" 
                           placeholder="Email Address" 
                           required
                           value="<?php echo isset($_POST['user_email']) ? esc_attr($_POST['user_email']) : ''; ?>">
                    
                    <div class="llm-password-wrapper">
                        <input type="password" 
                               name="user_password" 
                               id="user_password"
                               placeholder="Password" 
                               required>
                        <span class="llm-password-toggle" onclick="togglePassword()">
                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                                <circle cx="12" cy="12" r="2.5"/>
                            </svg>
                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="display: none;">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                                <circle cx="12" cy="12" r="2.5"/>
                                <line x1="4" y1="4" x2="20" y2="20"/>
                            </svg>
                        </span>
                    </div>
                    
                    <button type="submit" name="llm_login_submit">Log In</button>
                </form>
                
                <div style="margin-top: 20px; font-size: 14px; color: #666;">
                    <a href="<?php echo wp_lostpassword_url(get_permalink()); ?>" style="color: #2D2CFF; text-decoration: none;">
                        Forgot your password?
                    </a>
                </div>
            </div>
        </div>
        
        <?php if ($this->login_success): ?>
            <script>
                // Set authentication cookies via AJAX and reload
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'llm_set_auth_cookie',
                    user_id: <?php echo $this->login_user_id; ?>,
                    nonce: '<?php echo wp_create_nonce('llm_set_auth_cookie'); ?>'
                }).always(function() {
                    window.location.reload();
                });
            </script>
        <?php endif; ?>
        
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
                        <p>You're an Academy student, but not eligible for the special offer. Please enter the access code to
                            continue.</p>
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
                                        data-premium="<?php echo $is_premium ? '1' : '0'; ?>" <?php selected($is_selected); ?>>
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
                    <a href="#" onclick="llmLogout(); return false;">
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

                <div id="llm-pagination" class="llm-pagination" <?php if ($this->pagination_data['found_posts'] == 0 || $this->pagination_data['max_num_pages'] <= 1)
                    echo 'style="display:none;"'; ?>>
                    <a href="#" class="llm-pagination-arrow disabled" id="llm-prev-page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="15,18 9,12 15,6"></polyline>
                        </svg>
                    </a>
                    <div class="llm-pagination-numbers" id="llm-pagination-numbers">
                        <?php echo $this->generate_pagination_numbers(); ?>
                    </div>
                    <a href="#" class="llm-pagination-arrow<?php if ($this->pagination_data['current_page'] >= $this->pagination_data['max_num_pages'])
                        echo ' disabled'; ?>" id="llm-next-page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="9,18 15,12 9,6"></polyline>
                        </svg>
                    </a>
                </div>

                <div id="llm-no-results" class="llm-no-results" <?php if ($this->pagination_data['found_posts'] > 0)
                    echo 'style="display:none;"'; ?>>
                    No items found.
                </div>

                <div id="llm-premium-overlay" class="llm-premium-overlay"
                    style="display:<?php echo $show_premium_overlay ? 'flex' : 'none'; ?>;">
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
                            if ($tag_count >= $max_tags)
                                break;
                            ?>
                            <span class="llm-topic-tag" data-topic-id="<?php echo $topic->term_id; ?>"
                                style="background-color: <?php echo $topic_colors[$index % count($topic_colors)]; ?>;"><?php echo $topic->name; ?></span>
                            <?php
                            $tag_count++;
                        endforeach; ?>
                    <?php endif; ?>

                    <?php if ($tags && $tag_count < $max_tags): ?>
                        <?php foreach ($tags as $index => $tag):
                            if ($tag_count >= $max_tags)
                                break;
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
                    <img src="https://cuadroacademy.com/wp-content/uploads/2025/08/black-arrow.svg" alt="">
                </a>
            </div>
        </div>
        <?php
    }

    public function recent_prompts_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'count' => 3,
        ), $atts, 'aclas_recent_prompts');

        ob_start();

        // Query the most recent prompts - always get exactly 3
        $args = array(
            'post_type' => 'llm_prompt',
            'posts_per_page' => 3,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $prompts = new WP_Query($args);

        if (!$prompts->have_posts()) {
            return '<p>No recent prompts found.</p>';
        }

        ?>
        <style>
            .aclas-recent-prompts * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            .aclas-recent-prompts {
                max-width: 900px;
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
                height: 600px;
                font-family: 'Lato', sans-serif;
            }

            .aclas-recent-prompts .card {
                position: relative;
                border-radius: 1.5rem;
                border-bottom-right-radius: 0;
                overflow: hidden;
                background: #fff;
                transition: transform 0.3s ease;
            }

            .aclas-recent-prompts .card-1 {
                grid-row: span 2;
            }

            .aclas-recent-prompts .card-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .aclas-recent-prompts .card-content {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 1));
                color: white;
                padding: 40px 100px 20px 40px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transform: translateY(0);
                transition: transform 0.3s ease;
            }

            .aclas-recent-prompts .card-title {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .aclas-recent-prompts .card-description {
                font-size: 0.9rem;
                opacity: 0.9;
            }

            .aclas-recent-prompts .arrow-button {
                position: absolute;
                bottom: -0.375rem;
                right: -0.375rem;
                width: 6rem;
                height: 6rem;
                background: #F0F4FF;
                border-top-left-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
                z-index: 10;
            }

            .aclas-recent-prompts .arrow-button::before {
                position: absolute;
                content: "";
                bottom: 0.375rem;
                left: -1.25rem;
                background: transparent;
                width: 1.25rem;
                height: 1.25rem;
                border-bottom-right-radius: 1.25rem;
                box-shadow: 0.313rem 0.313rem 0 0.313rem #F0F4FF;
            }

            .aclas-recent-prompts .arrow-button::after {
                position: absolute;
                content: "";
                top: -1.25rem;
                right: 0.375rem;
                background: transparent;
                width: 1.25rem;
                height: 1.25rem;
                border-bottom-right-radius: 1.25rem;
                box-shadow: 0.313rem 0.313rem 0 0.313rem #F0F4FF;
            }

            .aclas-recent-prompts .arrow-icon {
                position: absolute;
                inset: 0.625rem;
                background: #fff;
                border-radius: 50%;
                display: flex;
                justify-content: center;
                align-items: center;
                transition: transform 0.3s ease;
            }

            .aclas-recent-prompts .arrow-icon img {
                width: 76px;
                height: 76px;
            }

            .aclas-recent-prompts .card-2,
            .aclas-recent-prompts .card-3 {
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .aclas-recent-prompts .card-3 {
                background-color: #6A4DFB;
            }

            .aclas-recent-prompts .card-4 {
                background-color: #13132B;
            }

            .aclas-recent-prompts .card-3 .card-content {
                background: none !important;
            }

            .aclas-recent-prompts .card-4 .card-content {
                background: none !important;
            }

            .card-1 .card-content {
                padding: 40px 100px 100px 40px;
            }

            @media (max-width: 768px) {
                .aclas-recent-prompts {
                    grid-template-columns: 1fr;
                    height: auto;
                }

                .aclas-recent-prompts .card-title {
                    font-size: 18px;
                    font-weight: 500;
                    margin-bottom: 0.5rem;
                }

                .card-1 .card-content {
                    padding: 40px 100px 40px 40px;
                }

                .aclas-recent-prompts .card-1 {
                    grid-row: span 1;
                    height: 300px;
                }

                .aclas-recent-prompts .card {
                    height: 250px;
                }
            }
        </style>

        <div class="aclas-recent-prompts">
            <?php
            $count = 0;
            while ($prompts->have_posts()):
                $prompts->the_post();
                $count++;
                $card_class = ($count === 1) ? 'card card-1' : 'card card-' . ($count + 1);
                $short_description = get_post_meta(get_the_ID(), '_llm_short_description', true);

                // Set specific images and arrow icons for each card
                if ($count === 1) {
                    // Card 1: Image + black arrow
                    $card_image = 'https://cuadroacademy.com/wp-content/uploads/2025/08/1.png';
                    $arrow_icon = 'https://cuadroacademy.com/wp-content/uploads/2025/08/black-arrow.svg';
                } elseif ($count === 2) {
                    // Card 2: Purple background + black arrow
                    $card_image = null;
                    $arrow_icon = 'https://cuadroacademy.com/wp-content/uploads/2025/08/purple-arrow.svg';
                } else {
                    // Card 3: Dark navy background + purple arrow
                    $card_image = null;
                    $arrow_icon = 'https://cuadroacademy.com/wp-content/uploads/2025/08/black-arrow.svg';
                }
                ?>
                <div class="<?php echo esc_attr($card_class); ?>">
                    <?php if ($card_image): ?>
                        <img src="<?php echo esc_url($card_image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>"
                            class="card-image">
                    <?php endif; ?>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo esc_html(get_the_title()); ?></h3>
                        <p class="card-description">
                            <?php echo esc_html($short_description ?: wp_trim_words(get_the_content(), 20)); ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="arrow-button">
                        <div class="arrow-icon">
                            <img src="<?php echo esc_url($arrow_icon); ?>" alt="">
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>

        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}