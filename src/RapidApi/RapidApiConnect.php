<?php

namespace RapidApi;

use RapidApi\Utils\HttpInstance;
use WebSocket\Client;
use WebSocket\ConnectionException;

class RapidApiConnect
{
    /**
     * Creates a new RapidAPI Connect instance
     *
     * @param $project {Name of the project you are working with}
     * @param $key {API key for the project}
     */
    public function __construct($project, $key)
    {
        $this->project = $project;
        $this->key = $key;
    }

    /**
     * Returns the base URL for block calls
     *
     * @returns string Base URL for block calls
     */
    public static function getBaseUrl()
    {
        return "https://rapidapi.io/connect";
    }

    /**
     * Build a URL for a block call
     *
     * @param $pack {Package where the block is}
     * @param $block {Block to be called}
     * @returns string Generated URL
     */
    public static function blockUrlBuild($pack, $block)
    {
        return static::getBaseUrl() . '/' . $pack . '/' . $block;
    }
    
    public static function callbackBaseURL()
    {
        //return "https://webhooks.imrapid.io";
        return "https://webhooks.rapidapi.xyz";
    }

    public static function websocketBaseURL()
    {
        //return "wss://webhooks.imrapid.io";
        return "wss://webhooks.rapidapi.xyz";
    }
  
    /**
     * Call a block
     *
     * @param $pack {Package of the block}
     * @param $block {Name of the block}
     * @param $args {Arguments to send to the block (JSON)}
     * @return mixed|string
     */
    public function call($pack, $block, $args)
    {
        $callback = [];

        $httpInstance = new HttpInstance(static::blockUrlBuild($pack, $block));

        $httpInstance->setParameters($this->project, $this->key, $args);

        try {

            $response = json_decode($httpInstance->getResponse(), true);

            if ($httpInstance->getLastHttpCode() != 200 || !isset($response['outcome']) || $response['outcome'] == "error") {

                $callback['error'] = $response;

                return $callback;
            } else {

                $callback['success'] = $response['payload'];

                return $callback;
            }

        } catch (\RuntimeException $ex) {

            $callback['error'] = sprintf('Http error %s with code %d', $ex->getMessage(), $ex->getCode());

            return $callback;
        }
    }

    public function listen($pack, $event, $args, $callbacks)
    {
        $user_id = "$pack.$event" . "_$this->project:$this->key";
        $get_token_url = static::callbackBaseURL() . "/api/get_token?user_id=$user_id";
        $httpInstance = new HttpInstance($get_token_url);
        $httpInstance->setGetParameters($this->project, $this->key);
        try {
            $response = json_decode($httpInstance->getResponse(), true);
            $token = $response['token'];
            $socket_url = static::websocketBaseURL() . "/socket/websocket?token=$token";
            $client = new Client($socket_url);
            $connect = array(
                "topic" => "users_socket:$user_id",
                "event" => "phx_join",
                "ref" => "1"
            );
            $connect["payload"] = $args;
            $heartbeat = array(
                "topic" => "phoenix",
                "event" => "heartbeat",
                "ref" => "1"
            );
            $heartbeat["payload"] = array();
            $client->send(json_encode($connect));
            $echo_time = time();
            $interval = 30;
            try {
                while (1)
                {
                    $message = json_decode($client->receive(), true);
                    if ($message["event"] == "joined" && is_callable($callbacks['onJoin'])) {
                        call_user_func($callbacks['onJoin']);
                    }                    
                    if (substr($message["event"], 0, 4) != "phx_" && $message["payload"]["token"] == $token)
                    {
                        if (is_callable($callbacks['onMessage'])) {
                            call_user_func($callbacks['onMessage'], $message["payload"]["body"]);
                        }
                    }

                    if ($echo_time + $interval >= time())
                    {
                        $client->send(json_encode($heartbeat, JSON_FORCE_OBJECT));
                        $echo_time = time();
                    }
                }
            } catch (ConnectionException $e) {
                if (is_callable($callbacks['onClose'])) {
                    call_user_func($callbacks['onClose'], $e);
                }
                exit;
            }
        } catch (\RuntimeException $ex) {
            return $ex;
        }
    }

}
