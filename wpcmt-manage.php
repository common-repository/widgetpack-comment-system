<?php
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field() {}
}

global $wpcmt_api;

require(ABSPATH . 'wp-includes/version.php');

if (!current_user_can('moderate_comments')) {
    die('The account you\'re logged in to doesn\'t have permission to access this page.');
}

function wpcmt_has_valid_nonce() {
    $nonce_actions = array('wpcmt_upgrade', 'wpcmt_reset', 'wpcmt_install', 'wpcmt_settings', 'wpcmt_active');
    $nonce_form_prefix = 'wpcmt-form_nonce_';
    $nonce_action_prefix = 'wpcmt-wpnonce_';
    foreach ($nonce_actions as $key => $value) {
        if (isset($_POST[$nonce_form_prefix.$value])) {
            check_admin_referer($nonce_action_prefix.$value, $nonce_form_prefix.$value);
            return true;
        }
    }
    return false;
}

if (!empty($_POST)) {
    $nonce_result_check = wpcmt_has_valid_nonce();
    if ($nonce_result_check === false) {
        die('Unable to save changes. Make sure you are accessing this page from the Wordpress dashboard.');
    }
}

// Reset
if (isset($_POST['reset'])) {
    foreach (wpcmt_options() as $opt) {
        delete_option($opt);
    }
    unset($_POST);
    wpcmt_reset_db();
?>
<div class="wrap">
    <h3><?php echo wpcmt_i('WidgetPack Reset'); ?></h3>
    <form method="POST" action="?page=wpcmt">
        <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_reset', 'wpcmt-form_nonce_wpcmt_reset'); ?>
        <p><?php echo wpcmt_i('WidgetPack has been reset successfully.') ?></p>
        <ul style="list-style: circle;padding-left:20px;">
            <li><?php echo wpcmt_i('Local settings for the plugin were removed.') ?></li>
            <li><?php echo wpcmt_i('Database changes by WidgetPack were reverted.') ?></li>
        </ul>
        <p>
            <?php echo wpcmt_i('If you wish to reinstall, you can do that now.') ?>
            <a href="?page=wpcmt">&nbsp;<?php echo wpcmt_i('Reinstall') ?></a>
        </p>
    </form>
</div>
<?php
die();
}

if (isset($_POST['wpcmt_site_id']) && isset($_POST['wpcmt_replace']) ) {
    update_option('wpcmt_replace', isset($_POST['wpcmt_replace']) ? esc_attr( $_POST['wpcmt_replace'] ) : 'all');
    update_option('wpcmt_sync_off', isset($_POST['wpcmt_sync_off']));
    update_option('wpcmt_disable_ssr', isset($_POST['wpcmt_disable_ssr']));
    update_option('wpcmt_debug', isset($_POST['wpcmt_debug']));
    update_option('wpcmt_sso_on', isset($_POST['wpcmt_sso_on']));
    if (version_compare($wp_version, '3.5', '>=')) {
        if ($_POST['wpcmt_sso_button']) {
            update_option('wpcmt_sso_button', isset($_POST['wpcmt_sso_button']) ? esc_url( $_POST['wpcmt_sso_button'] ) : '');
        }
    } else {
        if(isset($_FILES['wpcmt_sso_button'])) {
            wpcmt_img_upload('wpcmt_sso_button');
        }
    }
}

if (isset($_POST['wpcmt_active']) && isset($_GET['wpcmt_active'])) {
    update_option('wpcmt_active', ($_GET['wpcmt_active'] == '1' ? '1' : '0'));
}

if (isset($_POST['wpcmt_install']) && isset($_POST['wpac_site_data'])) {
    list($wpcmt_site_id, $wpcmt_api_key) = explode(':', $_POST['wpac_site_data']);
    update_option('wpcmt_site_id', $wpcmt_site_id);
    update_option('wpcmt_api_key', $wpcmt_api_key);
    update_option('wpcmt_replace', 'all');
    update_option('wpcmt_active', '1');
    update_option('wpcmt_ext_js', '0'); //TODO: 1
}

wp_enqueue_script('jquery');
wp_enqueue_script('jquery-ui-draggable');
wp_register_script('wpcmt_bootstrap_js', plugins_url('/static/js/bootstrap.min.js', __FILE__));
wp_enqueue_script('wpcmt_bootstrap_js', plugins_url('/static/js/bootstrap.min.js', __FILE__));

wp_register_style('wpcmt_bootstrap_css', plugins_url('/static/css/bootstrap.min.css', __FILE__));
wp_enqueue_style('wpcmt_bootstrap_css', plugins_url('/static/css/bootstrap.min.css', __FILE__));
wp_register_style('wpcmt_wpac_admin_css', plugins_url('/static/css/wpac-admin.css', __FILE__));
wp_enqueue_style('wpcmt_wpac_admin_css', plugins_url('/static/css/wpac-admin.css', __FILE__));
wp_register_style('wpcmt_admin_css', plugins_url('/static/css/admin.css', __FILE__));
wp_enqueue_style('wpcmt_admin_css', plugins_url('/static/css/admin.css', __FILE__));
?>

<?php if (!wpcmt_is_installed()) { ?>
<form method="POST" action="#wpcmt-plugin">
    <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_install', 'wpcmt-form_nonce_wpcmt_install'); ?>
    <input type="hidden" name="wpcmt_install"/>
    <div id="wpac-setup"></div>
</form>
<script type="text/javascript">
    wpac_init = window.wpac_init || [];
    wpac_init.push({widget: 'Setup'});
    (function() {
        var mc = document.createElement('script');
        mc.type = 'text/javascript';
        mc.async = true;
        mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
    })();
</script>
<?php
} else {
?>
<div class="wrap" id="wpcmt-wrap">
    <ul class="nav nav-tabs nav-justified">
        <li class="active">
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/comment/submenu/moderation" class="wpcmt-tab">
            <?php echo wpcmt_i('Moderate'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/comment/submenu/setting" class="wpcmt-tab">
            <?php echo wpcmt_i('Widget Settings'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/site/submenu/setting" class="wpcmt-tab">
            <?php echo wpcmt_i('Site Settings'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/site/submenu/admin" class="wpcmt-tab">
            <?php echo wpcmt_i('Moderators'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/site/submenu/stopword" class="wpcmt-tab">
            <?php echo wpcmt_i('Words Filter'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wpcmt_site_id'); ?>/menu/site/submenu/ban" class="wpcmt-tab">
            <?php echo wpcmt_i('Banned'); ?>
            </a>
        </li>
        <li>
            <a href="#wpcmt-plugin" class="wpcmt-tab">
            <?php echo wpcmt_i('Plugin Settings'); ?>
            </a>
        </li>
    </ul>
    <br>
    <div class="tab-content">
        <div role="tabpanel" class="tab-pane active" id="wpcmt-main">
            <div id="wpac-admin"></div>
            <script type="text/javascript">
                wpac_init = window.wpac_init || [];
                wpac_init.push({widget: 'Admin', popup: 'https://<?php echo WPAC_DOMAIN; ?>/login', id: <?php echo get_option('wpcmt_site_id'); ?>});
                (function() {
                    var mc = document.createElement('script');
                    mc.type = 'text/javascript';
                    mc.async = true;
                    mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
                    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
                })();
            </script>
        </div>
        <div role="tabpanel" class="tab-pane" id="wpcmt-plugin-pane">
            <?php
                $wpcmt_site_id = get_option('wpcmt_site_id');
                $wpcmt_replace = get_option('wpcmt_replace');
                $wpcmt_sso_on = get_option('wpcmt_sso_on');
                $wpcmt_sso_button = get_option('wpcmt_sso_button');
                $wpcmt_sync_off = get_option('wpcmt_sync_off');
                $wpcmt_disable_ssr = get_option('wpcmt_disable_ssr');
                $wpcmt_debug = get_option('wpcmt_debug');
                $wpcmt_enabled = get_option('wpcmt_active') == '1';
                $wpcmt_enabled_state = $wpcmt_enabled ? 'enabled' : 'disabled';
            ?>
            <!-- Settings -->
            <h3><?php echo wpcmt_i('Settings'); ?></h3>
            <p><?php echo wpcmt_i('Version: %s', esc_html(WPAC_VERSION)); ?></p>

            <!-- Enable/disable WidgetPack comment toggle -->
            <form method="POST" action="?page=wpcmt&amp;wpcmt_active=<?php echo (string)((int)($wpcmt_enabled != true)); ?>#wpcmt-plugin">
                <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_active', 'wpcmt-form_nonce_wpcmt_active'); ?>
                <p class="status">
                    <?php echo wpcmt_i('WidgetPack comments are currently '); ?>
                    <span class="wpcmt-<?php echo esc_attr($wpcmt_enabled_state); ?>-text"><b><?php echo $wpcmt_enabled_state; ?></b></span>
                </p>
                <input type="submit" name="wpcmt_active" class="button" value="<?php echo $wpcmt_enabled ? wpcmt_i('Disable') : wpcmt_i('Enable'); ?>" />
            </form>

            <!-- Configuration form -->
            <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_settings', 'wpcmt-form_nonce_wpcmt_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wpcmt_i('General') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Site ID'); ?></th>
                    <td>
                        <input type="hidden" name="wpcmt_site_id" value="<?php echo esc_attr($wpcmt_site_id); ?>"/>
                        <code><?php echo esc_attr($wpcmt_site_id); ?></code>
                        <br>
                        <?php echo wpcmt_i('This is the unique identifier for your website in WidgetPack, automatically set during installation.'); ?>
                        <br>
                        <?php echo wpcmt_i('Please include it to email when your request to support team at contact@widgetpack.com.'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wpcmt_i('Appearance') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Use WidgetPack Comments on'); ?></th>
                    <td>
                        <select name="wpcmt_replace" tabindex="1" class="wpcmt-replace">
                            <option value="all" <?php if($wpcmt_replace == 'all'){echo 'selected';}?>><?php echo wpcmt_i('All blog posts.'); ?></option>
                            <option value="closed" <?php if('closed'==$wpcmt_replace){echo 'selected';}?>><?php echo wpcmt_i('Blog posts with closed comments only.'); ?></option>
                        </select>
                        <br />
                        <?php
                            if ($wpcmt_replace == 'closed') echo '<p class="wpcmt-alert">'.wpcmt_i('You have selected to only enable WidgetPack on posts with closed comments. If you aren\'t seeing WidgetPack on new posts, change this option to "All blog posts".').'</p>';
                            else echo wpcmt_i('Shows comments on either all blog posts, or ones with closed comments. Select the "Blog posts with closed comments only" option if you plan on disabling WidgetPack, but want to keep it on posts which already have comments.');
                        ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top" colspan="2"><?php echo '<h3>' . wpcmt_i('Single Sign-On (SSO)') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('SSO Enabled'); ?></th>
                    <td>
                        <input type="checkbox" id="wpcmt_sso_on" name="wpcmt_sso_on" <?php if($wpcmt_sso_on) {echo 'checked="checked"';}?> >
                        <label for="wpcmt_sso_on"><?php echo wpcmt_i('Enabled Single Sign-On with your website'); ?></label>
                        <br><?php echo wpcmt_i('Allows logged on to your website users to be automatically logged in to WidgetPack and post comments with user avatar, name and a link to the user profile.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('SSO Log-in Button Image'); ?></th>
                    <td>
                        <?php if (!empty($wpcmt_sso_button)) { ?>
                        <img id="wpcmt_sso_img" src="<?php echo esc_attr($wpcmt_sso_button); ?>" alt="<?php echo esc_attr($wpcmt_sso_button); ?>" style="max-height:100px;"/><br>
                        <?php } ?>

                        <?php if (version_compare($wp_version, '3.5', '>=')) { wp_enqueue_media(); ?>
                        <input type="button" value="<?php echo ($wpcmt_sso_button ? wpcmt_i('Change') : wpcmt_i('Choose')).' '.wpcmt_i('button'); ?>" class="button upload_image_button" tabindex="2">
                        <input type="hidden" name="wpcmt_sso_button" id="wpcmt_sso_button" value=""/>
                        <?php } else { ?>
                        <input type="file" name="wpcmt_sso_button" value="<?php echo esc_attr($wpcmt_sso_button); ?>" tabindex="2">
                        <?php } ?>
                        <br>
                        <?php echo wpcmt_i('SSO button is small image (143x32) with logo and your website name.'); ?>
                        (<a href="https://media.cackle.me/7/97/6f7b66a71fe6afbdc1b0039622e8b977.png" target="_blank"><?php echo wpcmt_i('Example screenshot'); ?></a>)
                        <br />
                        <?php echo wpcmt_i('See our documentation for a template to create your own button.'); ?>&nbsp;
                        <a href="https://widgetpack.com/api/sso#sso-button" target="_blank"><?php echo wpcmt_i('Integrating SSO'); ?></a>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wpcmt_i('Synchronization') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Comment Importing'); ?></th>
                    <td>
                        <input type="checkbox" id="wpcmt_sync_off" name="wpcmt_sync_off" <?php if($wpcmt_sync_off) {echo 'checked="checked"';}?> >
                        <label for="wpcmt_sync_off"><?php echo wpcmt_i('Disable automated comment importing'); ?></label>
                        <br><?php echo wpcmt_i('If you have problems with WP-Cron taking too long, or have a large number of comments, you may wish to disable automated sync. Comments will only be imported to your local Wordpress database if you do so manually.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Server-Side Rendering'); ?></th>
                    <td>
                        <input type="checkbox" id="wpcmt_disable_ssr" name="wpcmt_disable_ssr" <?php if($wpcmt_disable_ssr){echo 'checked="checked"';}?> >
                        <label for="wpcmt_disable_ssr"><?php echo wpcmt_i('Disable server-side rendering of comments'); ?></label>
                        <br><?php echo wpcmt_i('Hides comments from nearly all search engines.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Debug Mode'); ?></th>
                    <td>
                        <input type="checkbox" id="wpcmt_debug" name="wpcmt_debug" <?php if($wpcmt_debug){echo 'checked="checked"';}?> >
                        <label for="wpcmt_debug"><?php echo wpcmt_i('Show debug information'); ?></label>
                        <br><?php echo wpcmt_i('Turn it on only if WidgetPack support team asked to do this.'); ?>
                    </td>
                </tr>
            </table>
            <p class="submit" style="text-align: left">
                <input type="hidden" name="wpcmt_site_id" value="<?php echo esc_attr($wpcmt_site_id); ?>"/>
                <input type="hidden" name="wpcmt_api_key" value="<?php echo esc_attr($wpcmt_api_key); ?>"/>
                <input name="submit" type="submit" value="Save" class="button-primary button" tabindex="4">
            </p>
            </form>

            <h3>Import and Export</h3>
            <table class="form-table">
                <tr id="export">
                    <th scope="row" valign="top"><?php echo wpcmt_i('Export comments to WidgetPack'); ?></th>
                    <td>
                        <div id="wpcmt_export">
                            <form method="POST" action="">
                                <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_export', 'wpcmt-form_nonce_wpcmt_export'); ?>
                                <p class="status">
                                    <a href="#" class="button"><?php echo wpcmt_i('Export Comments'); ?></a>
                                    <?php echo wpcmt_i('This will export your existing WordPress comments to WidgetPack'); ?>
                                </p>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Sync WidgetPack with WordPress'); ?></th>
                    <td>
                        <div id="wpcmt_import">
                            <form method="POST" action="">
                                <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_import', 'wpcmt-form_nonce_wpcmt_import'); ?>
                                <div class="status">
                                    <p>
                                        <a href="#" class="button"><?php echo wpcmt_i('Sync Comments'); ?></a>
                                        <?php echo wpcmt_i('This will download your WidgetPack comments and store them locally in WordPress'); ?>
                                    </p>
                                    <label>
                                        <input type="checkbox" id="wpcmt_import_wipe" name="wpcmt_import_wipe" value="1"/>
                                        <?php echo wpcmt_i('Remove all imported WidgetPack comments before syncing.'); ?>
                                    </label>
                                    <br/>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            </table>

            <h3>Reset</h3>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo wpcmt_i('Reset WidgetPack'); ?></th>
                    <td>
                        <form action="?page=wpcmt" method="POST">
                            <?php wp_nonce_field('wpcmt-wpnonce_wpcmt_reset', 'wpcmt-form_nonce_wpcmt_reset'); ?>
                            <p>
                                <input type="submit" value="Reset" name="reset" onclick="return confirm('<?php echo wpcmt_i('Are you sure you want to reset the WidgetPack plugin?'); ?>')" class="button" />
                                <?php echo wpcmt_i('This removes all WidgetPack-specific settings. Comments will remain unaffected.') ?>
                            </p>
                            <?php echo wpcmt_i('If you have problems with resetting taking too long you may wish to first manually drop the \'wpcmt_meta_idx\' index from your \'commentmeta\' table.') ?>
                        </form>
                    </td>
                </tr>
            </table>
            <br/>
        </div>
    </div>
</div>
<?php } ?>