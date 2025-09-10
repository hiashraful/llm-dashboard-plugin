<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/libreria-digitale/'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Handle password update
$password_error = '';
$password_success = false;
if (isset($_POST['update_password']) && isset($_POST['llm_account_nonce'])) {
    if (wp_verify_nonce($_POST['llm_account_nonce'], 'llm_account_update')) {
        $current_password = sanitize_text_field($_POST['current_password']);
        $new_password = sanitize_text_field($_POST['new_password']);
        $confirm_password = sanitize_text_field($_POST['confirm_password']);

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $password_error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $password_error = 'New password must be at least 8 characters long.';
        } elseif (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
            $password_error = 'Current password is incorrect.';
        } else {
            wp_set_password($new_password, $user_id);
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            $password_success = true;
        }
    }
}

get_header();

// Enqueue styles
wp_enqueue_style('llm-prompts-style', LLM_PROMPTS_URL . 'assets/style.css', array(), '1.0.19');

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

<!-- Account Management -->
<div class="llm-account-container">
    <div class="llm-account-form">
        <h2>Libreria Digitale Account</h2>
        <p>Gestisci le informazioni e le impostazioni del tuo account.</p>

        <!-- User Information Section -->
        <div class="llm-account-section">
            <h3>Informazioni Account</h3>
            <div class="llm-account-info">
                <div class="llm-info-row">
                    <label>Nome:</label>
                    <span><?php echo esc_html($current_user->display_name); ?></span>
                </div>
                <div class="llm-info-row">
                    <label>Email:</label>
                    <span><?php echo esc_html($current_user->user_email); ?></span>
                </div>
            </div>
        </div>

        <!-- Password Update Section -->
        <div class="llm-account-section">
            <h3>Cambia Password</h3>
            
            <?php if (!empty($password_error)): ?>
                <div class="llm-error">
                    <?php echo esc_html($password_error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($password_success): ?>
                <div class="llm-success">
                    Password aggiornata con successo!
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('llm_account_update', 'llm_account_nonce'); ?>
                
                <div class="llm-password-wrapper">
                    <input type="password" 
                           name="current_password" 
                           placeholder="Password Attuale" 
                           required>
                </div>
                
                <div class="llm-password-wrapper">
                    <input type="password" 
                           name="new_password" 
                           placeholder="Nuova Password" 
                           required>
                </div>
                
                <div class="llm-password-wrapper">
                    <input type="password" 
                           name="confirm_password" 
                           placeholder="Conferma Nuova Password" 
                           required>
                </div>
                
                <button type="submit" name="update_password" class="llm-update-btn">
                    Aggiorna Password
                </button>
            </form>
        </div>

        <!-- Logout Section -->
        <div class="llm-account-section">
            <a href="<?php echo wp_logout_url('https://cuadroacademy.com/libreria-digitale-dashboard/'); ?>" class="llm-logout-btn">
                Logout
            </a>
        </div>
    </div>
</div>

<style>
.llm-account-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 8vh;
    padding: 40px 20px;
    background-color: #f0f4ff;
    padding-top: 190px;
}

.llm-account-form {
    border: 1px solid #e0e0e0;
    background-color: #fff;
    border-radius: 20px;
    padding: 40px;
    max-width: 500px;
    width: 100%;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.llm-account-form h2 {
    margin-bottom: 20px;
    color: #02062B;
    font-size: 28px;
    font-weight: 700;
    font-family: 'Rubik', sans-serif;
}

.llm-account-form p {
    margin-bottom: 30px;
    color: #666;
    font-family: 'Rubik', sans-serif;
    line-height: 1.6;
}

.llm-account-section {
    margin-bottom: 10px;
    text-align: left;
}

.llm-account-section h3 {
    color: #02062B;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    font-family: 'Rubik', sans-serif;
}

.llm-account-info {
    background: #f8f9ff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 10px;
}

.llm-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.llm-info-row:last-child {
    margin-bottom: 0;
}

.llm-info-row label {
    font-weight: 600;
    color: #02062B;
    font-family: 'Rubik', sans-serif;
}

.llm-info-row span {
    color: #666;
    font-family: 'Rubik', sans-serif;
}

.llm-password-wrapper {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
}

.llm-password-wrapper input {
    width: 100% !important;
    padding: 15px 20px !important;
    border: 1px solid #e0e0e0 !important;
    border-radius: 12px !important;
    font-size: 16px !important;
    font-family: 'Rubik', sans-serif !important;
    box-sizing: border-box !important;
    transition: all 0.3s ease !important;
    margin-bottom: 0 !important;
}

.llm-password-wrapper input:focus {
    outline: none !important;
    border-color: #2D2CFF !important;
}


.llm-update-btn {
    width: 100% !important;
    padding: 15px !important;
    background: #2D2CFF !important;
    color: white !important;
    border: none !important;
    border-radius: 12px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    font-family: 'Rubik', sans-serif !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    margin-bottom: 10px;
}

.llm-update-btn:hover {
    background: #1E1BFF !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 8px 20px rgba(45, 44, 255, 0.3) !important;
}

.llm-logout-btn {
    display: inline-block;
    width: 100%;
    padding: 15px;
    background: #dc3545;
    color: white !important;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    font-family: 'Rubik', sans-serif;
    text-decoration: none !important;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.llm-logout-btn:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
    color: white !important;
    text-decoration: none;
}

.llm-error {
    color: #dc3545;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 12px;
    font-family: 'Rubik', sans-serif;
}

.llm-success {
    color: #155724;
    margin-bottom: 20px;
    padding: 15px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 12px;
    font-family: 'Rubik', sans-serif;
}

@media (max-width: 768px) {
    .llm-account-container {
        padding-top: 120px;
    }
    
    .llm-account-form {
        padding: 30px 20px;
    }
    
    .llm-info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<script>

// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.llm-hamburger');
    const mobileMenu = document.querySelector('.llm-mobile-menu');
    const closeBtn = document.querySelector('.llm-mobile-close');

    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function() {
            mobileMenu.classList.add('active');
        });
    }

    if (closeBtn && mobileMenu) {
        closeBtn.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
        });
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (mobileMenu && mobileMenu.classList.contains('active') && 
            !mobileMenu.contains(e.target) && !hamburger.contains(e.target)) {
            mobileMenu.classList.remove('active');
        }
    });
});
</script>

<?php get_footer(); ?>