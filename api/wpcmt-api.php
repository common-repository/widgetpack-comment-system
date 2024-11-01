<?php

require_once(dirname(__FILE__) . '/url.php');

if (!extension_loaded('json')) {
    require_once(dirname(__FILE__) . '/json.php');
    function wpcmt_json_decode($data) {
        $json = new JSON;
        return $json->unserialize($data);
    }
} else {
    function wpcmt_json_decode($data) {
        return json_decode($data);
    }
}

class WPacCommentAPI {
    var $site_id;
    var $site_api_key;
    var $api_url = WPAC_API_URL;

    function WPacCommentAPI($site_id, $site_api_key, $api_url=WPAC_API_URL) {
        $this->site_id = $site_id;
        $this->site_api_key = $site_api_key;
        $this->api_url = $api_url;
        $this->last_error = null;
    }

    function comment_list($params=array()) {
        return $this->call('list', $params);
    }

    function call($method, $args=array(), $post=false) {
        $url = $this->api_url . $method . '/';

        if (!isset($args['site_id'])) {
            $args['site_id'] = $this->site_id;
        }

        foreach ($args as $key=>$value) {
            if (empty($value)) unset($args[$key]);
        }

        if (!$post) {
            $url .= '?' . wpcmt_get_query_string($args, $this->site_api_key);
            $args = null;
        }

        if (!($response = wpcmt_urlopen($url, $args)) || !$response['code']) {
            $this->last_error = 'Unable to connect to the WidgetPack API servers';
            return false;
        }

        if ($response['code'] != 200) {
            if ($response['code'] == 500) {
                if (!empty($response['headers']['X-Error-ID'])) {
                    $this->last_error = 'WidgetPack returned a bad response (HTTP '.$response['code'].', ReferenceID: '.$response['headers']['X-Error-ID'].')';
                    return false;
                }
            } elseif ($response['code'] == 400) {
                $data = wpcmt_json_decode($response['data']);
                if ($data && $data->message) {
                    $this->last_error = $data->message;
                } else {
                    $this->last_error = "WidgetPack returned a bad response (HTTP ".$response['code'].")";
                }
                return false;
            }
            $this->last_error = "WidgetPack returned a bad response (HTTP ".$response['code'].")";
            return false;
        }

        $data = wpcmt_json_decode($response['data']);

        if (!$data) {
            $this->last_error = 'No valid JSON content returned from WidgetPack';
            return false;
        }
        $this->last_error = null;
        return $data->data;
    }
}

?>
