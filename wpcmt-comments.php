<div id="wpac-comment">
    <?php if (!get_option('wpcmt_disable_ssr') && have_comments()): ?>
    <div id="wpcmt-content">

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
        <div class="navigation">
            <div class="nav-previous">
                <span class="meta-nav">&larr;</span>&nbsp;
                <?php previous_comments_link(wpcmt_i('Older Comments')); ?>
            </div>
            <div class="nav-next">
                <?php next_comments_link(wpcmt_i('Newer Comments')); ?>
                &nbsp;<span class="meta-nav">&rarr;</span>
            </div>
        </div>
        <?php endif; ?>

        <ul id="wpcmt-comments">
             <?php wp_list_comments(array('callback' => 'wpcmt_comment')); ?>
        </ul>

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
        <div class="navigation">
            <div class="nav-previous">
                <span class="meta-nav">&larr;</span>
                &nbsp;<?php previous_comments_link(wpcmt_i('Older Comments') ); ?>
            </div>
            <div class="nav-next">
                <?php next_comments_link(wpcmt_i('Newer Comments') ); ?>
                &nbsp;<span class="meta-nav">&rarr;</span>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>
</div>

<?php
if (get_option('wpcmt_ext_js') == '1') {
    $widget_vars = array(
        'options' => array(
            'sync_off' => get_option('wpcmt_sync_off'),
        ),
        'host' => WPAC_EMBED_DOMAIN,
        'id' => get_option('wpcmt_site_id'),
        'chan' => $post->ID,
    );
    wp_register_script('wpcmt_widget_js', plugins_url('/static/js/wpcmt.js', __FILE__));
    wp_localize_script('wpcmt_widget_js', 'widgetVars', $widget_vars);
    wp_enqueue_script('wpcmt_widget_js', plugins_url('/static/js/wpcmt.js', __FILE__));
} else {
?>
<script type="text/javascript">
<?php if (get_option('wpcmt_sync_off') != 1): ?>
setTimeout(function() {
    var script = document.createElement('script');
    script.async = true;
    script.src = '?cf_action=wpcmt_sync&post_id=<?php echo esc_attr($post->ID); ?>&ver=' + new Date().getTime();
    var firstScript = document.getElementsByTagName('script')[0];
    firstScript.parentNode.insertBefore(script, firstScript);
}, 2000);
<?php endif; ?>

wpac_init = window.wpac_init || [];
wpac_init.push({widget: 'Comment', id: <?php echo get_option('wpcmt_site_id'); ?>, chan: '<?php echo wpcmt_chan_id($post); ?>' <?php if (get_option('wpcmt_sso_on') == 1) { echo wpcmt_sso_button(); echo wpcmt_sso(); } ?>});
(function() {
    if ('WIDGETPACK_LOADED' in window) return;
    WIDGETPACK_LOADED = true;
    var mc = document.createElement('script');
    mc.type = 'text/javascript';
    mc.async = true;
    mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
})();
</script>
<?php
}
?>