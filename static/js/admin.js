jQuery(document).ready(function($) {
    if (window.location.hash) {
        $('.nav-tabs li.active').removeClass('active');
        $('a[href="' + window.location.hash + '"]').parent().addClass('active');
        if (window.location.hash == '#wpcmt-plugin') {
            $('#wpcmt-main').hide();
            $('#wpcmt-plugin-pane').show();
        } else {
            $('#wpcmt-main').show();
            $('#wpcmt-plugin-pane').hide();
        }
    } else {
        window.location.hash = '#/site/' + adminVars.siteId + '/menu/comment/submenu/moderation';
    }
    $('a.wpcmt-tab').unbind().click(function() {
        if ($(this).parent().hasClass('active')) {
            return false;
        }
        $('.nav-tabs li.active').removeClass('active');
        $(this).parent().addClass('active');
        $('.tab-content .tab-pane.active').removeClass('active');
        $('.tab-content #wpcmt-main').addClass('active');
        if ($(this).attr('href') == '#wpcmt-plugin') {
            $('#wpcmt-main').hide();
            $('#wpcmt-plugin-pane').show();
        } else {
            $('#wpcmt-main').show();
            $('#wpcmt-plugin-pane').hide();
        }
        return true;
    });
    wpcmt_fire_export();
    wpcmt_fire_import();
    wpcmt_img_upload($);
});

var wpcmt_fire_export = function() {
    jQuery(function($) {
        $('#wpcmt_export a.button').unbind().click(function() {
            $('#wpcmt_export .status').removeClass('wpcmt-export-fail').addClass('wpcmt-exporting').html('Processing...');
            wpcmt_export_comments();
            return false;
        });
    });
};

var wpcmt_export_comments = function() {
    jQuery(function($) {
        var status = $('#wpcmt_export .status');
        var nonce = $('#wpcmt-form_nonce_wpcmt_export').val();
        var export_info = (status.attr('rel') || '0|' + (new Date().getTime()/1000)).split('|');        
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'wpcmt_export',
                post_id: export_info[0],
                timestamp: export_info[1],
                _wpcmtexport_wpnonce: nonce
            },
            function(response) {
                var host = 'https://api.widgetpack.com',
                    url = host + '/1.0/comment/import?site_id=' + response.site_id + '&signature=' + response.signature;

                var req = new XMLHttpRequest();
                req.open('POST', url, true);
                req.setRequestHeader('Content-Type', 'application/json');
                req.onreadystatechange = function(res) {
                    if (req.readyState === 4) {
                        if (req.status === 200) {
                            var result = JSON.parse(req.responseText), msg;
                            if (result.error) {
                                msg = 'Failed to import comments to WidgetPack for post ID ' + response.post_id + ' please contact@widgetpack.com';
                            } else {
                                msg = 'Comments have been successful imported to WidgetPack for post ID' + response.post_id;
                            }
                            status.html(msg).attr('rel', response.post_id + '|' + response.timestamp);
                            switch (response.status) {
                                case 'partial':
                                    wpcmt_export_comments();
                                break;
                                case 'complete':
                                    status.html('All commets have been successfully imported').removeClass('wpcmt-exporting').addClass('wpcmt-exported');
                                break;
                            }
                        }
                    }
                };
                req.send(JSON.stringify(response.json));
                /*switch (response.result) {
                    case 'success':
                        status.html(response.msg).attr('rel', response.post_id + '|' + response.timestamp);
                        switch (response.status) {
                            case 'partial':
                                wpcmt_export_comments();
                            break;
                            case 'complete':
                                status.removeClass('wpcmt-exporting').addClass('wpcmt-exported');
                            break;
                        }
                    break;
                    case 'fail':
                        status.parent().html(response.msg);
                        wpcmt_fire_export();
                    break;
                }*/
            },
            'json'
        );
    });
};

var wpcmt_fire_import = function() {
    jQuery(function($) {
        $('#wpcmt_import a.button, #wpcmt_import_retry').unbind().click(function() {
            var wipe = $('#wpcmt_import_wipe').is(':checked');
            $('#wpcmt_import .status').removeClass('wpcmt-import-fail').addClass('wpcmt-importing').html('Processing...');
            wpcmt_import_comments(wipe);
            return false;
        });
    });
};

var wpcmt_import_comments = function(wipe) {
    jQuery(function($) {
        var status = $('#wpcmt_import .status');
        var nonce = $('#wpcmt-form_nonce_wpcmt_import').val();
        var last_id = status.attr('rel') || '0';
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'wpcmt_import',
                last_id: last_id,
                wipe: (wipe ? 1 : 0),
                _wpcmtimport_wpnonce: nonce
            },
            function(response) {
                switch (response.result) {
                    case 'success':
                        status.html(response.msg).attr('rel', response.last_id);
                        switch (response.status) {
                            case 'partial':
                                wpcmt_import_comments(false);
                                break;
                            case 'complete':
                                status.removeClass('wpcmt-importing').addClass('wpcmt-imported');
                                break;
                        }
                    break;
                    case 'fail':
                        status.parent().html(response.msg);
                        wpcmt_fire_import();
                    break;
                }
            },
            'json'
        );
    });
};

var wpcmt_img_upload = function($) {
    var file_frame;
    $('.upload_image_button').on('click', function(e) {
        e.preventDefault();
        if (file_frame) {
            file_frame.open();
            return;
        }

        file_frame = wp.media.frames.file_frame = wp.media({
            title: $(this).data('uploader_title'),
            button: {text: $(this).data('uploader_button_text')},
            multiple: false
        });

        file_frame.on('select', function() {
            var wpcmt_sso_button = $('#wpcmt_sso_button'),
                wpcmt_sso_img = $('#wpcmt_sso_img');
            attachment = file_frame.state().get('selection').first().toJSON();
            wpcmt_sso_button.val(attachment.url);
            if (wpcmt_sso_img.length == 0) {
                var parent = $(wpcmt_sso_button.parent());
                parent.prepend('<br>');
                parent.prepend($('<img />', {
                    id: 'wpcmt_sso_img',
                    src: attachment.url,
                    style: 'max-height:100px;'
                }));
            } else {
                wpcmt_sso_img.attr('src', attachment.url);
            }
        });
        file_frame.open();
    });
};