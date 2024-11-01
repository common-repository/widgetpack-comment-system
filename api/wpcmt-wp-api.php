<?php
require_once(ABSPATH.WPINC.'/http.php');
require_once(dirname(__FILE__) . '/wpcmt-api.php');

class WPacCommentWordPressAPI {
    var $site_id;
    var $site_api_key;

    function WPacCommentWordPressAPI($site_id=null, $site_api_key=null) {
        $this->site_id = $site_id;
        $this->site_api_key = $site_api_key;
        $this->api = new WPacCommentAPI($site_id, $site_api_key, WPAC_API_URL);
    }

    function get_last_error() {
        return $this->api->get_last_error();
    }

    function comment_list($offset_id=0) {
        $response = $this->api->comment_list(array(
            'status' => 1,
            'offset_id' => $offset_id,
            'size' => WPAC_API_LIST_SIZE
        ));
        return $response;
    }

    function comment_list_modif($modif=0, $offset_id=0) {
        $params = array(
            'modif' => $modif,
            'size' => WPAC_API_LIST_SIZE
        );
        if ($offset_id > 0) {
            $params['offset_id']  = $offset_id;
        }
        $response = $this->api->comment_list($params);
        return $response;
    }
}

?>
