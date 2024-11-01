<?php
/*
Plugin Name: WidgetPack Comment System
Plugin URI: https://widgetpack.com/comment-system
Description: The WidgetPack Comment System replaces default WordPress comments with Real-time and social comments service to drive more traffic for your website.
Author: WidgetPack <contact@widgetpack.com>
Version: 1.6.1
Author URI: https://widgetpack.com
*/

require_once(dirname(__FILE__) . '/api/wpcmt-wp-api.php');

define('WPAC_VERSION',        '1.6.1');
define('WPAC_DOMAIN',         'widgetpack.com');
define('WPAC_EMBED_DOMAIN',   'embed.widgetpack.com');
define('WPAC_API_URL',        'http://api.widgetpack.com/1.0/comment/');
define('WPAC_API_LIST_SIZE',  100);
define('WPAC_SYNC_TIMEOUT',   30);
define('WPAC_DEBUG',          get_option('wpcmt_debug'));

function wpcmt_options() {
    return array(
        '_wpcmt_sync_lock',
        '_wpcmt_sync_modif',
        'wpcmt_site_id',
        'wpcmt_api_key',
        'wpcmt_replace',
        'wpcmt_active',
        'wpcmt_ext_js',
        'wpcmt_sso_on',
        'wpcmt_sync_off',
        'wpcmt_disable_ssr',
        'wpcmt_version',
        'wpcmt_last_id',
        'wpcmt_last_modif',
        'wpcmt_last_modif_offset_id',
        'wpcmt_last_modif_2',
        'wpcmt_debug',
    );
}

$wpcmt_api = new WPacCommentWordPressAPI(get_option('wpcmt_site_id'), get_option('wpcmt_api_key'));

/*-------------------------------- Admin --------------------------------*/
function wpcmt_load_admin_js($hook) {
    if ('comments_page_wpcmt' != $hook) {
        return;
    }
    $admin_vars = array(
        'indexUrl' => admin_url('index.php'),
        'siteId' => get_option('wpcmt_site_id'),
    );
    wp_register_script('wpcmt_admin_script', plugins_url('/static/js/admin.js', __FILE__));
    wp_localize_script('wpcmt_admin_script', 'adminVars', $admin_vars );
    wp_enqueue_script('wpcmt_admin_script', plugins_url('/static/js/admin.js', __FILE__), array('jQuery'));
}
add_action('admin_enqueue_scripts', 'wpcmt_load_admin_js');

/*-------------------------------- Menu --------------------------------*/
function wpcmt_admin_menu() {
     add_submenu_page(
         'edit-comments.php',
         'WidgetPack Comment System',
         'WidgetPack',
         'moderate_comments',
         'wpcmt',
         'wpcmt_manage'
     );
}
add_action('admin_menu', 'wpcmt_admin_menu', 10);

function wpcmt_manage() {
    if (wpcmt_does_need_update()) {
        wpcmt_install();
    }
    include_once(dirname(__FILE__) . '/wpcmt-manage.php');
}

function wpcmt_plugin_action_links($links, $file) {
    $plugin_file = basename(__FILE__);
    if (basename($file) == $plugin_file) {
        if (!wpcmt_is_installed()) {
            $settings_link = '<a href="edit-comments.php?page=wpcmt">'.wpcmt_i('Configure').'</a>';
        } else {
            $settings_link = '<a href="edit-comments.php?page=wpcmt#wpcmt-plugin">'.wpcmt_i('Settings').'</a>';
        }
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'wpcmt_plugin_action_links', 10, 2);

/*-------------------------------- Database --------------------------------*/
function wpcmt_install($allow_db_install=true) {
    global $wpdb, $userdata;

    $version = (string)get_option('wpcmt_version');
    if (!$version) {
        $version = '0';
    }

    if ($allow_db_install) {
        wpcmt_install_db($version);
    }

    if (version_compare($version, WPAC_VERSION, '=')) {
        return;
    }

    if ($version == '0') {
        add_option('wpcmt_active', '0');
    } else {
        add_option('wpcmt_active', '1');
    }
    update_option('wpcmt_version', WPAC_VERSION);
}

function wpcmt_install_db($version=0) {
    global $wpdb;
    if (!wpcmt_is_wpvip()) {
        $wpdb->query("CREATE INDEX wpcmt_meta_idx ON `".$wpdb->prefix."commentmeta` (meta_key, meta_value(11));");
    }
}
function wpcmt_reset_db($version=0) {
    global $wpdb;
    if (!wpcmt_is_wpvip()) {
        $wpdb->query("DROP INDEX wpcmt_meta_idx ON `".$wpdb->prefix."commentmeta`;");
    }
}
function wpcmt_is_wpvip() {
    return defined('WPCOM_IS_VIP_ENV') && WPCOM_IS_VIP_ENV;
}

/*-------------------------------- Default --------------------------------*/
function wpcmt_pre_comment_on_post($comment_post_ID) {
    if (wpcmt_can_replace()) {
        wp_die(wpcmt_i('Sorry, the built-in commenting system is disabled because WidgetPack Comments is active.') );
    }
    return $comment_post_ID;
}
add_action('pre_comment_on_post', 'wpcmt_pre_comment_on_post');

/*-------------------------------- WidgetPack --------------------------------*/
function wpcmt_output_count_js() {
    if (get_option('wpcmt_ext_js') == '1') {
        $widget_vars = array(
            'host' => WPAC_EMBED_DOMAIN,
            'id' => get_option('wpcmt_site_id'),
            'chan' => $post->ID,
        );
        wp_register_script('wpcmt_count_script', plugins_url('/static/js/count.js', __FILE__));
        wp_localize_script('wpcmt_count_script', 'countVars', $count_vars);
        wp_enqueue_script('wpcmt_count_script', plugins_url('/static/js/count.js', __FILE__));
    } else {
        ?>
        <script type="text/javascript">
        // <![CDATA[
        (function () {
            var nodes = document.getElementsByTagName('span');
            for (var i = 0, url; i < nodes.length; i++) {
                if (nodes[i].className.indexOf('wpcmt-postid') != -1) {
                    nodes[i].parentNode.setAttribute('data-wpac-chan', nodes[i].getAttribute('data-wpac-chan'));
                    url = nodes[i].parentNode.href.split('#', 1);
                    if (url.length == 1) { url = url[0]; }
                    else { url = url[1]; }
                    nodes[i].parentNode.href = url + '#wpac-comment';
                }
            }
            wpac_init = window.wpac_init || [];
            wpac_init.push({widget: 'CommentCount', id: <?php echo get_option('wpcmt_site_id'); ?>});
            (function() {
                if ('WIDGETPACK_LOADED' in window) return;
                WIDGETPACK_LOADED = true;
                var mc = document.createElement('script');
                mc.type = 'text/javascript';
                mc.async = true;
                mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
            })();
        }());
        // ]]>
        </script>
        <?php
    }
}

function wpcmt_output_footer_comment_js() {
    if (!wpcmt_can_replace()) {
        return;
    }
    wpcmt_output_count_js();
}
add_action('wp_footer', 'wpcmt_output_footer_comment_js');

$EMBED = false;
function wpcmt_comments_template($value) {
    global $EMBED;
    global $post;
    global $comments;

    if (!(is_singular() && (have_comments() || 'open' == $post->comment_status))) {
        return;
    }

    if (!wpcmt_is_installed() || !wpcmt_can_replace() ) {
        return $value;
    }

    $EMBED = true;
    return dirname(__FILE__) . '/wpcmt-comments.php';
}

function wpcmt_comments_text($comment_text) {
    global $post;

    if (wpcmt_can_replace()) {
        return '<span class="wpcmt-postid" data-wpac-chan="'.esc_attr(wpcmt_chan_id($post)).'">'.$comment_text.'</span>';
    } else {
        return $comment_text;
    }
}

function wpcmt_comments_number($count) {
    global $post;
    return $count;
}

add_filter('comments_template', 'wpcmt_comments_template');
add_filter('comments_number', 'wpcmt_comments_text');
add_filter('get_comments_number', 'wpcmt_comments_number');

function wpcmt_comment($comment, $args, $depth) {
    $GLOBALS['comment'] = $comment;
    switch ($comment->comment_type):
        case '' :
    ?>
    <li <?php comment_class(); ?> id="wpcmt-comment-<?php echo comment_ID(); ?>" itemtype="http://schema.org/Comment" itemscope="itemscope">
        <div id="wpcmt-comment-header-<?php echo comment_ID(); ?>" class="wpcmt-comment-header">
            <cite id="wpcmt-cite-<?php echo comment_ID(); ?>">
                <?php if(comment_author_url()) : ?>
                <a id="wpcmt-author-user-<?php echo comment_ID(); ?>" href="<?php echo comment_author_url(); ?>" target="_blank" rel="nofollow" itemprop="author"><?php echo comment_author(); ?></a>
                <?php else : ?>
                <span id="wpcmt-author-user-<?php echo comment_ID(); ?>" itemprop="author"><?php echo comment_author(); ?></span>
                <?php endif; ?>
            </cite>
        </div>
        <div id="wpcmt-comment-body-<?php echo comment_ID(); ?>" class="wpcmt-comment-body">
            <div id="wpcmt-comment-message-<?php echo comment_ID(); ?>" class="wpcmt-comment-message" itemprop="text"><?php echo wp_filter_kses(comment_text()); ?></div>
        </div>
        <meta itemprop="dateCreated" content="<?php echo comment_time('Y-m-d\TH:i:s'); ?>">
    </li>
    <?php
        break;
        case 'pingback'  :
        case 'trackback' :
    ?>
    <li class="post pingback">
        <p><?php echo wpcmt_i('Pingback:'); ?> <?php comment_author_link(); ?>(<?php edit_comment_link(wpcmt_i('Edit'), ' '); ?>)</p>
    </li>
    <?php
        break;
    endswitch;
}

function wpcmt_comments_open($open, $post_id=null) {
    global $EMBED;
    if ($EMBED) return false;
    return $open;
}
add_filter('comments_open', 'wpcmt_comments_open');

/*-------------------------------- Channel --------------------------------*/
function wpcmt_chan_id($post) {
    return $post->ID;
}

function wpcmt_chan_url($post) {
    return get_permalink($post);
}

function wpcmt_chan_title($post) {
    $title = get_the_title($post);
    $title = strip_tags($title);
    return $title;
}

/*-------------------------------- Request --------------------------------*/
function wpcmt_request_handler() {
    global $post;
    global $wpdb;
    global $wpcmt_api;

    if (!empty($_GET['cf_action'])) {
        switch ($_GET['cf_action']) {
            case 'wpcmt_sync':
                if(!($post_id = $_GET['post_id'])) {
                    header("HTTP/1.0 400 Bad Request");
                    die();
                }
                // sync schedule after 5 minutes
                $ts = time() + 300;
                $sync_modif = get_option('_wpcmt_sync_modif');
                if ($sync_modif == '1') {
                    wp_schedule_single_event($ts, 'wpcmt_sync_modif');
                    die('// wpcmt_sync_modif scheduled');
                } else {
                    wp_schedule_single_event($ts, 'wpcmt_sync');
                    die('// wpcmt_sync scheduled');
                }
            break;
            case 'wpcmt_export':
                if (current_user_can('manage_options')) {
                    $msg = '';
                    $result = '';
                    $response = null;

                    $timestamp = intval($_GET['timestamp']);
                    $post_id = intval($_GET['post_id']);
                    if ( isset($_GET['_wpcmtexport_wpnonce']) === false ) {
                        $msg = wpcmt_i('Unable to export comments. Make sure you are accessing this page from the Wordpress dashboard.');
                        $result = 'fail';
                    } else {
                        check_admin_referer('wpcmt-wpnonce_wpcmt_export', '_wpcmtexport_wpnonce');

                        $post = $wpdb->get_results($wpdb->prepare("
                            SELECT *
                            FROM $wpdb->posts
                            WHERE post_type != 'revision'
                            AND post_status = 'publish'
                            AND comment_count > 0
                            AND ID > %d
                            ORDER BY ID ASC
                            LIMIT 1
                        ", $post_id));
                        $post = $post[0];
                        $post_id = $post->ID;
                        $max_post_id = $wpdb->get_var("
                            SELECT MAX(Id)
                            FROM $wpdb->posts
                            WHERE post_type != 'revision'
                            AND post_status = 'publish'
                            AND comment_count > 0
                        ");
                        $eof = (int)($post_id == $max_post_id);
                        if ($eof) {
                            $status = 'complete';
                            $msg = wpcmt_i('Your comments have been sent to WidgetPack and queued for import!');
                        }
                        else {
                            $status = 'partial';
                            $msg = wpcmt_i('Processed comments on post') . ' #'. $post_id . '&hellip;';
                        }
                        $result = 'fail';
                        if ($post) {
                            require_once(dirname(__FILE__) . '/wpcmt-export.php');
                            $json = wpcmt_export_json($post);
                        }
                    }
                    $site_id = get_option('wpcmt_site_id');
                    $encoded_json = cf_json_encode($json);
                    $wpcmt_api_key = get_option('wpcmt_api_key');
                    $signature = md5('site_id='.$site_id.$encoded_json.$wpcmt_api_key);
                    $response = compact('timestamp', 'status', 'post_id', 'site_id', 'json', 'signature');
                    header('Content-type: text/javascript');
                    echo cf_json_encode($response);
                    die();
                }
            break;
            case 'wpcmt_import':
                if (current_user_can('manage_options')) {
                    $msg = '';
                    $result = '';
                    $response = null;

                    if (isset($_GET['_wpcmtimport_wpnonce']) === false) {
                        $msg = wpcmt_i('Unable to import comments. Make sure you are accessing this page from the Wordpress dashboard.');
                        $result = 'fail';
                    } else {
                        check_admin_referer('wpcmt-wpnonce_wpcmt_import', '_wpcmtimport_wpnonce');

                        if (!isset($_GET['last_id'])) $last_id = false;
                        else $last_id = $_GET['last_id'];

                        if ($_GET['wipe'] == '1') {
                            $wpdb->query("DELETE FROM `".$wpdb->prefix."commentmeta` WHERE meta_key IN ('wpcmt_id', 'wpcmt_parent_id')");
                            $wpdb->query("DELETE FROM `".$wpdb->prefix."comments` WHERE comment_agent LIKE 'Wpcmt/%%'");
                        }

                        ob_start();
                        $response = wpcmt_sync($last_id, true);
                        $debug = ob_get_clean();
                        if (!$response) {
                            $status = 'error';
                            $result = 'fail';
                            $error = $wpcmt_api->get_last_error();
                            $msg = '<p class="status wpcmt-export-fail">'.wpcmt_i('There was an error downloading your comments from WidgetPack.').'<br/>'.esc_attr($error).'</p>';
                        } else {
                            list($comments, $last_id) = $response;
                            if (!$comments) {
                                $status = 'complete';
                                $msg = wpcmt_i('Your comments have been downloaded from WidgetPack and saved in your local database.');
                            } else {
                                $status = 'partial';
                                $msg = wpcmt_i('Import in progress (last comment id: %s)', $last_id) . ' &hellip;';
                            }
                            $result = 'success';
                        }
                        $debug = explode("\n", $debug);
                        $response = compact('result', 'status', 'comments', 'msg', 'last_id', 'debug');
                        header('Content-type: text/javascript');
                        echo cf_json_encode($response);
                        die();
                    }
                }
            break;
        }
    }
}
add_action('init', 'wpcmt_request_handler');

/*-------------------------------- Sync --------------------------------*/
function wpcmt_sync($last_id=false, $force=false) {
    global $wpdb;
    global $wpcmt_api;

    set_time_limit(WPAC_SYNC_TIMEOUT);

    if ($force) {
        $sync_time = null;
    } else {
        $sync_time = (int)get_option('_wpcmt_sync_lock');
    }

    // lock sync for 1 hour if previous sync isn't done
    if ($sync_time && $sync_time > time() - 60*60) {
        return false;
    } else {
        update_option('_wpcmt_sync_lock', time());
    }

    // init last_id as offset id cursor
    if ($last_id === false) {
        $last_id = get_option('wpcmt_last_id');
        if (!$last_id) {
            $last_id = 0;
        }
    }

    // init last_modif as offset modified cursor - do it here that's don't lose edited comments for sync period
    $last_modif = get_option('wpcmt_last_modif');
    if (!$last_modif) {
        update_option('wpcmt_last_modif', round(microtime(true) * 1000));
    }

    // Get comments from API
    $wpcmt_res = $wpcmt_api->comment_list($last_id);
    if($wpcmt_res < 0 || $wpcmt_res === false) {
        update_option('_wpcmt_sync_modif', '1');
        return false;
    }
    // Sync comments with database.
    wpcmt_sync_comments($wpcmt_res);

    $total = 0;
    if ($wpcmt_res) {
        foreach ($wpcmt_res as $comment) {
            $total += 1;
            if ($comment->id > $last_id) $last_id = $comment->id;
        }
        if ($last_id > get_option('wpcmt_last_id')) {
            update_option('wpcmt_last_id', $last_id);
        }
    }
    unset($comment);

    if ($total < WPAC_API_LIST_SIZE) {
        // If get few comments to switch sync modif (edited)
        update_option('_wpcmt_sync_modif', '1');
    } else {
        // If get a lot of comments continue to sync (new)
        delete_option('_wpcmt_sync_modif');
    }

    delete_option('_wpcmt_sync_lock');
    return array($total, $last_id);
}
add_action('wpcmt_sync', 'wpcmt_sync');

function wpcmt_sync_comments($comments) {
    if (count($comments) < 1) {
        return;
    }

    global $wpdb;

    // user MUST be logged out during this process
    wp_set_current_user(0);

    foreach ($comments as $comment) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->id));
        if (count($results)) {
            if (count($results) > 1) {
                $results = array_slice($results, 1);
                foreach ($results as $result) {
                    $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE comment_id = %s LIMIT 1", $result);
                }
            }
            continue;
        }

        $commentdata = false;

        if (isset($comment->meta)) {
            $comment_meta = is_array($comment->meta) ? $comment->meta : array($comment->meta);
            foreach ($comment_meta as $meta) {
                if ($meta->meta_key == 'wp-id') {
                    $commentdata = $wpdb->get_row($wpdb->prepare("SELECT comment_ID, comment_parent FROM $wpdb->comments WHERE comment_ID = %s LIMIT 1", $meta->meta_value), ARRAY_A);
                }
            }
        }

        if (!$commentdata) {
            if ($comment->status == 1) {
                $status = 1;
            } elseif ($comment->status == 3) {
                $status = 'spam';
            } else {
                $status = 0;
            }
            $unix_time = intval($comment->created) / 1000;
            $commentdata = array(
                'comment_post_ID' => $comment->site_chan->chan,
                'comment_date' => date('Y-m-d\TH:i:s', $unix_time + (get_option('gmt_offset') * 3600)),
                'comment_date_gmt' => date('Y-m-d\TH:i:s', $unix_time),
                'comment_content' => apply_filters('pre_comment_content', $comment->msg),
                'comment_approved' => $status,
                'comment_agent' => 'Wpcmt/1.0('.WPAC_VERSION.'):'.intval($comment->id),
                'comment_type' => '',
                'comment_author_IP' => $comment->ip
            );
            if ($comment->user) {
                $commentdata['comment_author'] = $comment->user->name;
                $commentdata['comment_author_email'] = $comment->user->email;
                $commentdata['comment_author_url'] = $comment->user->www;
            } else {
                $commentdata['comment_author'] = $comment->name;
                $commentdata['comment_author_email'] = $comment->email;
            }
            $commentdata = wp_filter_comment($commentdata);
            if ($comment->parent_id) {
                $parent_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->parent_id));
                if ($parent_id) {
                    $commentdata['comment_parent'] = $parent_id;
                }
            }

            // test again for comment exist
            if ($wpdb->get_row($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->id))) {
                continue;
            }

            $commentdata['comment_ID'] = wp_insert_comment($commentdata);
        }
        if ((isset($commentdata['comment_parent']) && !$commentdata['comment_parent']) && $comment->parent_id) {
            $parent_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->parent_id));
            if ($parent_id) {
                $wpdb->query($wpdb->prepare("UPDATE $wpdb->comments SET comment_parent = %s WHERE comment_id = %s", $parent_id, $commentdata['comment_ID']));
            }
        }
        $comment_id = $commentdata['comment_ID'];
        update_comment_meta($comment_id, 'wpcmt_parent_id', $comment->parent_id);
        update_comment_meta($comment_id, 'wpcmt_id', $comment->id);
    }
    unset($comment);
}

/*-------------------------------- Sync modif --------------------------------*/
function wpcmt_sync_modif() {
    global $wpdb;
    global $wpcmt_api;

    set_time_limit(WPAC_SYNC_TIMEOUT);

    $sync_time = (int)get_option('_wpcmt_sync_lock');

    // lock sync for 1 hour if previous sync isn't done
    if ($sync_time && $sync_time > time() - 60*60) {
        return false;
    } else {
        update_option('_wpcmt_sync_lock', time());
    }

    $last_modif = get_option('wpcmt_last_modif');
    if (!$last_modif) {
        $last_modif = 0;
    }

    $last_modif_offset_id = get_option('wpcmt_last_modif_offset_id');
    if ($last_modif_offset_id) {
        $wpcmt_res = $wpcmt_api->comment_list_modif($last_modif, $last_modif_offset_id);
    } else {
        $wpcmt_res = $wpcmt_api->comment_list_modif($last_modif);
        $last_modif_offset_id = 0;
    }

    if($wpcmt_res < 0 || $wpcmt_res === false) {
        return false;
    }
    // Sync comments with database.
    wpcmt_sync_comments_modif($wpcmt_res);

    $total = 0;
    if ($wpcmt_res) {
        foreach ($wpcmt_res as $comment) {
            $total += 1;
            if ($comment->modif > $last_modif) $last_modif = $comment->modif;
            if ($comment->id > $last_modif_offset_id) $last_modif_offset_id = $comment->id;
        }
        unset($comment);
        if ($total < WPAC_API_LIST_SIZE) {
            if ($last_modif > get_option('wpcmt_last_modif')) {
                update_option('wpcmt_last_modif', $last_modif);
            }
        } else {
            update_option('wpcmt_last_modif_2', $last_modif);
            update_option('wpcmt_last_modif_offset_id', $last_modif_offset_id);
        }
    }

    if ($total == 0) {
        $last_modif_2 = get_option('wpcmt_last_modif_2');
        if ($last_modif_2 > get_option('wpcmt_last_modif')) {
            update_option('wpcmt_last_modif', $last_modif_2);
        }
        delete_option('wpcmt_last_modif_offset_id');
    }

    delete_option('_wpcmt_sync_modif');
    delete_option('_wpcmt_sync_lock');
    return true;
}
add_action('wpcmt_sync_modif', 'wpcmt_sync_modif');

function wpcmt_sync_comments_modif($comments) {
    if (count($comments) < 1) {
        return;
    }

    global $wpdb;

    // user MUST be logged out during this process
    wp_set_current_user(0);

    foreach ($comments as $comment) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->id));
        if (count($results)) {
            if (count($results) > 1) {
                $results = array_slice($results, 1);
                foreach ($results as $result) {
                    $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE comment_id = %s LIMIT 1", $result);
                }
            }
        }

        $wp_comment_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->id));
        if ($wp_comment_id) {
            if ($comment->status == 1) {
                $status = 1;
            } elseif ($comment->status == 3) {
                $status = 'spam';
            } else {
                $status = 0;
            }
            $unix_time = intval($comment->created) / 1000;
            $commentdata = array(
                'comment_ID' => $wp_comment_id,
                'comment_post_ID' => $comment->site_chan->chan,
                'comment_content' => apply_filters('pre_comment_content', $comment->msg),
                'comment_approved' => $status,
                'comment_author_IP' => $comment->ip
            );
            if ($comment->user) {
                $commentdata['comment_author'] = $comment->user->name;
                $commentdata['comment_author_email'] = $comment->user->email;
                $commentdata['comment_author_url'] = $comment->user->www;
            } else {
                $commentdata['comment_author'] = $comment->name;
                $commentdata['comment_author_email'] = $comment->email;
            }
            $commentdata = wp_filter_comment($commentdata);
            if ($comment->parent_id) {
                $parent_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->parent_id));
                if ($parent_id) {
                    $commentdata['comment_parent'] = $parent_id;
                }
            }
            wp_update_comment($commentdata);

            if ((isset($commentdata['comment_parent']) && !$commentdata['comment_parent']) && $comment->parent_id) {
                $parent_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wpcmt_id' AND meta_value = %s LIMIT 1", $comment->parent_id));
                if ($parent_id) {
                    $wpdb->query($wpdb->prepare("UPDATE $wpdb->comments SET comment_parent = %s WHERE comment_id = %s", $parent_id, $commentdata['comment_ID']));
                }
            }
            update_comment_meta($wp_comment_id, 'wpcmt_parent_id', $comment->parent_id);
            update_comment_meta($wp_comment_id, 'wpcmt_id', $comment->id);
        } else {
            wpcmt_sync_comments(array($comment));
        }
    }
    unset($comment);
}

/*-------------------------------- SSO --------------------------------*/
function wpcmt_sso_button() {
    $button = get_option('wpcmt_sso_button');
    if ($button) {
        $sitename = get_bloginfo('name');
        $siteurl = site_url();
        $sso_button= ", sso: {
              name: '" . esc_js( $sitename ) . "',
              button: '" . $button . "',
              url: '" . $siteurl . "/wp-login.php',
              logout: '" . $siteurl . "/wp-login.php?action=logout',
              width: '670',
              height: '520'
        }";
        return $sso_button;
    } else {
        return '';
    }
}

function wpcmt_sso() {
    global $current_user;
    get_currentuserinfo();
    if ($current_user->ID) {
        $avatar_tag = get_avatar($current_user->ID);
        $avatar_data = array();
        preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
        $avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
        $user_data = array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'avatar' => $avatar,
            'www' => $current_user->user_url,
        );
        $api_key = get_option('wpcmt_api_key');
        $user_data = base64_encode(cf_json_encode($user_data));
        $time = round(microtime(true) * 1000);
        $sign = md5($user_data.$api_key.$time);
        return ", sso_auth: '".$user_data." ".$sign." ".$time."'";
    } else {
        return ", sso_auth: ''";
    }
}

/*-------------------------------- Helpers --------------------------------*/
function wpcmt_is_installed() {
    $wpcmt_site_id = get_option('wpcmt_site_id');
    $wpcmt_api_key = get_option('wpcmt_api_key');
    if (is_numeric($wpcmt_site_id) > 0 && strlen($wpcmt_api_key) > 0) {
        return true;
    } else {
        return false;
    }
}

function wpcmt_does_need_update() {
    $version = (string)get_option('wpcmt_version');
    if (empty($version)) {
        $version = '0';
    }
    if (version_compare($version, '1.0', '<')) {
        return true;
    }
    return false;
}

function wpcmt_can_replace() {
    global $id, $post;

    if (get_option('wpcmt_active') === '0'){ return false; }

    $replace = get_option('wpcmt_replace');

    if (is_feed())                         { return false; }
    if (!isset($post))                     { return false; }
    if ('draft' == $post->post_status)     { return false; }
    if (!get_option('wpcmt_site_id'))      { return false; }
    else if ('all' == $replace)            { return true; }

    if (!isset($post->comment_count)) {
        $num_comments = 0;
    } else {
        if ('empty' == $replace) {
            if ( $post->comment_count > 0 ) {
                $comments = get_approved_comments($post->ID);
                foreach ($comments as $comment) {
                    if ($comment->comment_type != 'trackback' && $comment->comment_type != 'pingback') {
                        $num_comments++;
                    }
                }
            } else {
                $num_comments = 0;
            }
        } else {
            $num_comments = $post->comment_count;
        }
    }
    return (('empty' == $replace && 0 == $num_comments) || ('closed' == $replace && 'closed' == $post->comment_status));
}

function wpcmt_i($text, $params=null) {
    if (!is_array($params)) {
        $params = func_get_args();
        $params = array_slice($params, 1);
    }
    return vsprintf(__($text, 'wpcmt'), $params);
}

function wpcmt_img_upload($option_name) {
    if(isset($_FILES[$option_name]) && ($_FILES[$option_name]['size'] > 0)) {
        $arr_file_type = wp_check_filetype(basename($_FILES[$option_name]['name']));
        $uploaded_file_type = $arr_file_type['type'];
        $allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png','image/x-icon','image/svg');
        if(in_array($uploaded_file_type, $allowed_file_types)) {
            $upload_overrides = array('test_form' => false);
            $uploaded_file = wp_handle_upload($_FILES[$option_name], $upload_overrides);
            if(isset($uploaded_file['url'])) {
                update_option($option_name, $uploaded_file['url']);
            }
        }
    }
}

if (!function_exists('esc_html')) {
function esc_html( $text ) {
    $safe_text = wp_check_invalid_utf8( $text );
    $safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
    return apply_filters( 'esc_html', $safe_text, $text );
}
}

if (!function_exists('esc_attr')) {
function esc_attr( $text ) {
    $safe_text = wp_check_invalid_utf8( $text );
    $safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
    return apply_filters( 'attribute_escape', $safe_text, $text );
}
}

/**
 * JSON ENCODE for PHP < 5.2.0
 * Checks if json_encode is not available and defines json_encode
 * to use php_json_encode in its stead
 * Works on iteratable objects as well - stdClass is iteratable, so all WP objects are gonna be iteratable
 */
if(!function_exists('cf_json_encode')) {
    function cf_json_encode($data) {

        // json_encode is sending an application/x-javascript header on Joyent servers
        // for some unknown reason.
        return cfjson_encode($data);
    }

    function cfjson_encode_string($str) {
        if(is_bool($str)) {
            return $str ? 'true' : 'false';
        }

        return str_replace(
            array(
                '\\'
                , '"'
                //, '/'
                , "\n"
                , "\r"
            )
            , array(
                '\\\\'
                , '\"'
                //, '\/'
                , '\n'
                , '\r'
            )
            , $str
        );
    }

    function cfjson_encode($arr) {
        $json_str = '';
        if (is_array($arr)) {
            $pure_array = true;
            $array_length = count($arr);
            for ( $i = 0; $i < $array_length ; $i++) {
                if (!isset($arr[$i])) {
                    $pure_array = false;
                    break;
                }
            }
            if ($pure_array) {
                $json_str = '[';
                $temp = array();
                for ($i=0; $i < $array_length; $i++) {
                    $temp[] = sprintf("%s", cfjson_encode($arr[$i]));
                }
                $json_str .= implode(',', $temp);
                $json_str .="]";
            }
            else {
                $json_str = '{';
                $temp = array();
                foreach ($arr as $key => $value) {
                    $temp[] = sprintf("\"%s\":%s", $key, cfjson_encode($value));
                }
                $json_str .= implode(',', $temp);
                $json_str .= '}';
            }
        }
        else if (is_object($arr)) {
            $json_str = '{';
            $temp = array();
            foreach ($arr as $k => $v) {
                $temp[] = '"'.$k.'":'.cfjson_encode($v);
            }
            $json_str .= implode(',', $temp);
            $json_str .= '}';
        }
        else if (is_string($arr)) {
            $json_str = '"'. cfjson_encode_string($arr) . '"';
        }
        else if (is_numeric($arr)) {
            $json_str = $arr;
        }
        else if (is_bool($arr)) {
            $json_str = $arr ? 'true' : 'false';
        }
        else {
            $json_str = '"'. cfjson_encode_string($arr) . '"';
        }
        return $json_str;
    }
}
?>