<?php
get_header();

function check_prompt_access()
{
    if (!is_user_logged_in()) {
        return 'not_logged_in';
    }

    $user = wp_get_current_user();

    // Admin bypass
    if (user_can($user, 'manage_options')) {
        return 'access_granted';
    }

    // Check if user has academy_student role
    if (!in_array('academy_student', $user->roles)) {
        return 'invalid_role';
    }

    // Check the verification cookie
    $expected_cookie_value = wp_hash(get_option('llm_dashboard_password', '') . $user->ID);
    $actual_cookie_value = isset($_COOKIE['llm_code_verified']) ? $_COOKIE['llm_code_verified'] : '';


    if ($expected_cookie_value === $actual_cookie_value) {
        return 'access_granted';
    } else {
        return 'code_required';
    }
}

$access_status = check_prompt_access();

if ($access_status !== 'access_granted') {
    // Redirect to dashboard for login/access code entry
    $dashboard_url = home_url('/aclas-knowledge-hub/');
    wp_redirect($dashboard_url);
    exit;
}

if (have_posts()) {
    while (have_posts()) {
        the_post();

        $short_description = get_post_meta(get_the_ID(), '_llm_short_description', true);
        $tutorial_video = get_post_meta(get_the_ID(), '_llm_tutorial_video', true);
        $multiple_prompts = get_post_meta(get_the_ID(), '_llm_multiple_prompts', true);

        $libraries = get_the_terms(get_the_ID(), 'llm_library');
        $topics = get_the_terms(get_the_ID(), 'llm_topic');
        $tags = get_the_terms(get_the_ID(), 'llm_tag');

        $topic_colors = array('#8B5CF6', '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#EC4899');
        $tag_colors = array('#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F59E0B', '#10B981');
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

        <div class="llm-single-prompt">
            <div class="llm-single-container">


                <div class="llm-single-header">
                    <h1 class="llm-single-title"><?php the_title(); ?></h1>

                    <?php if ($short_description): ?>
                        <p class="llm-single-description"><?php echo esc_html($short_description); ?></p>
                    <?php endif; ?>

                    <div class="llm-single-meta">
                        <?php if ($libraries): ?>
                            <div class="llm-single-libraries">
                                <?php foreach ($libraries as $library): ?>
                                    <span class="llm-library-badge"><?php echo "Library: " . $library->name; ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="llm-single-terms">
                            <?php if ($topics): ?>
                                <?php foreach ($topics as $index => $topic): ?>
                                    <span class="llm-topic-tag"
                                        style="background-color: <?php echo $topic_colors[$index % count($topic_colors)]; ?>;">
                                        <?php echo $topic->name; ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($tags): ?>
                                <?php foreach ($tags as $index => $tag): ?>
                                    <span class="llm-tag-tag"
                                        style="background-color: <?php echo $tag_colors[$index % count($tag_colors)]; ?>;">
                                        <?php echo $tag->name; ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($tutorial_video): ?>
                    <div class="llm-single-video">
                        <?php if (strpos($tutorial_video, 'youtube.com') !== false || strpos($tutorial_video, 'youtu.be') !== false): ?>
                            <?php
                            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $tutorial_video, $matches);
                            $video_id = $matches[1] ?? '';
                            ?>
                            <iframe width="100%" height="400" src="https://www.youtube.com/embed/<?php echo $video_id; ?>"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                        <?php elseif (strpos($tutorial_video, 'vimeo.com') !== false): ?>
                            <?php
                            preg_match('/vimeo\.com\/(\d+)/', $tutorial_video, $matches);
                            $video_id = $matches[1] ?? '';
                            ?>
                            <iframe src="https://player.vimeo.com/video/<?php echo $video_id; ?>" width="100%" height="400"
                                frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                        <?php else: ?>
                            <video width="100%" height="400" controls>
                                <source src="<?php echo esc_url($tutorial_video); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="llm-single-content">
                    <!-- The Method Section -->
                    <?php $method_content = get_the_content(); ?>
                    <?php if (!empty($method_content)): ?>
                        <div class="llm-method-section">
                            <div class="llm-method-header">
                                <h3>The Method</h3>
                            </div>
                            <div class="llm-method-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Prompts Section -->
                    <?php if (!empty($multiple_prompts) && is_string($multiple_prompts) && trim($multiple_prompts) !== ''): ?>
                        <div class="llm-multiple-prompts">
                            <?php
                            $prompt_sections = explode('---PROMPT SEPARATOR---', $multiple_prompts);
                            foreach ($prompt_sections as $index => $prompt):
                                $prompt = trim($prompt);
                                if (empty($prompt))
                                    continue;
                                ?>
                                <div class="llm-prompt-section">
                                    <div class="llm-prompt-header">
                                        <h3>Prompt <?php echo $index + 1; ?></h3>
                                        <button class="llm-copy-btn" data-prompt="<?php echo esc_attr($prompt); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Copy
                                        </button>
                                    </div>
                                    <div class="llm-single-prompt-text">
                                        <?php echo nl2br(esc_html($prompt)); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="<?php echo home_url('/aclas-knowledge-hub/'); ?>" class="llm-back-button">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <?php
    }
}

get_footer();
?>