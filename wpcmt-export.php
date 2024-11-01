<?php
@set_time_limit(0);
@ini_set('memory_limit', '256M');

function wpcmt_export_json($post, $comments=null) {
    global $wpdb;

    if (!$comments) {
        $comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_agent NOT LIKE 'Wpcmt/%%'", $post->ID) );
    }

    $comments_array = array();
    if ($comments) {
        foreach ($comments as $key => $c) {
            if ($c->comment_approved == 1) {
                $status = 1;
            } elseif ($c->comment_approved == 'spam') {
                $status = 3;
            } elseif ($c->comment_approved == 'trash') {
                $status = 2;
            } else {
                $status = 0;
            }
            $comment = array(
                'id' => $c->comment_ID,
                'parent_id' => $c->comment_parent,
                'ip' => $c->comment_author_IP,
                'msg' => $c->comment_content,
                'status' => $status,
                'created' => strtotime($c->comment_date) * 1000,
                'meta' => "wp",
                'status' => $status,
            );
            if ($c->user_id == 0) {
                $comment["name"] = $c->comment_author;
                $comment["email"] = $c->comment_author_email;
            } else {
                $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE id = %d", $c->user_id));
                $avatar_tag = get_avatar($user->ID);
                $avatar_data = array();
                preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
                $avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
                $user_data = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => $avatar,
                    'www' => $user->user_url,
                );
                $comment["user"] = $user_data;
            }
            array_push($comments_array, $comment);
        }
    }
    return array(
        'chan' => wpcmt_chan_id($post),
        'url' => wpcmt_chan_url($post),
        'title' => wpcmt_chan_title($post),
        'comments' => $comments_array,
    );
}

?>
