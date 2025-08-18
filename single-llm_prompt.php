<?php
get_header();

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

        <div class="llm-single-prompt">
            <div class="llm-single-container">
                <div class="llm-single-header">
                    <?php if ($libraries && !is_wp_error($libraries)): ?>
                        <div class="llm-library-name"><?php echo esc_html($libraries[0]->name); ?></div>
                    <?php endif; ?>

                    <h1 class="llm-single-title"><?php the_title(); ?></h1>

                    <?php if ($short_description): ?>
                        <p class="llm-single-description"><?php echo esc_html($short_description); ?></p>
                    <?php endif; ?>

                    <div class="llm-single-tags">
                        <?php if ($topics && !is_wp_error($topics)): ?>
                            <?php foreach ($topics as $index => $topic): ?>
                                <span class="llm-single-topic"
                                    style="background-color: <?php echo $topic_colors[$index % count($topic_colors)]; ?>;">
                                    <?php echo esc_html($topic->name); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($tags && !is_wp_error($tags)): ?>
                            <?php foreach ($tags as $index => $tag): ?>
                                <span class="llm-single-tag"
                                    style="background-color: <?php echo $tag_colors[$index % count($tag_colors)]; ?>;">
                                    <?php echo esc_html($tag->name); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($tutorial_video): ?>
                    <div class="llm-single-video">
                        <?php
                        $video_id = '';
                        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $tutorial_video, $matches)) {
                            $video_id = $matches[1];
                        }

                        if ($video_id): ?>
                            <iframe width="100%" height="400" src="https://www.youtube.com/embed/<?php echo $video_id; ?>"
                                frameborder="0" allowfullscreen></iframe>
                        <?php else: ?>
                            <video width="100%" height="400" controls>
                                <source src="<?php echo esc_url($tutorial_video); ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="llm-single-content">
                    <h2>The method</h2>
                    <div class="llm-method-content">
                        <?php the_content(); ?>
                    </div>

                    <h2>The prompts</h2>
                    <div class="llm-prompts-section">
                        <?php if ($multiple_prompts && is_array($multiple_prompts) && count($multiple_prompts) > 0): ?>
                            <?php foreach ($multiple_prompts as $index => $prompt): ?>
                                <?php if (!empty(trim($prompt))): ?>
                                    <div class="llm-prompt-box">
                                        <div class="llm-prompt-header">
                                            <span class="llm-prompt-number">Prompt <?php echo $index + 1; ?></span>
                                            <button class="llm-copy-btn" data-prompt="<?php echo esc_attr($prompt); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                                Copy
                                            </button>
                                        </div>
                                        <div class="llm-prompt-text"><?php echo nl2br(esc_html($prompt)); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="llm-prompt-box">
                                <div class="llm-prompt-header">
                                    <span class="llm-prompt-number">Prompt</span>
                                    <button class="llm-copy-btn" data-prompt="<?php echo esc_attr(get_the_content()); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="llm-prompt-text"><?php the_content(); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="llm-back-button">
                    <a href="<?php echo home_url('/llm-dashboard/'); ?>" class="llm-back-link">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m12 19-7-7 7-7"></path>
                            <path d="M19 12H5"></path>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php
    }
}

wp_enqueue_style('llm-prompts-style', plugin_dir_url(__FILE__) . '../assets/style.css', array(), '1.0.0');
wp_enqueue_script('llm-prompts-script', plugin_dir_url(__FILE__) . '../assets/script.js', array('jquery'), '1.0.0', true);
wp_localize_script('llm-prompts-script', 'llm_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('llm_prompts_nonce')
));

get_footer();
?>