<?php

namespace RapidApi;

use RapidApi\Utils\HttpInstance;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

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
     * Returns the base URL to obtain token for webhook calls
     *
     * @return string
     */
    public static function callbackBaseURL()
    {
        return "https://webhooks.rapidapi.com/api/get_token?user_id=";
    }

    /**
     * Returns the base URL for websocket calls
     *
     * @return string
     */
    public static function websocketBaseURL()
    {
        return "wss://webhooks.rapidapi.com/socket/websocket?token=";
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

    /**
     * @param LoopInterface $loop
     * @return mixed
     */
    public function connectionFactory(LoopInterface $loop)
    {
        $connFactory = function () use ($loop) {
            $connector = new Connector($loop);

            return function ($token) use ($connector) {

                return $connector(static::websocketBaseURL() . $token);
            };
        };

        return $connFactory();
    }

    /**
     * @param WebSocket $websocket
     * @param LoopInterface $loop
     * @param $args
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    public function createListener(WebSocket $websocket, LoopInterface $loop, $args)
    {
        $token = substr($websocket->request->getUri()->getQuery(), 6);

        $connect = [
            "topic" => "users_socket:$token",
            "event" => "phx_join",
            "ref" => "1",
            "payload" => $args
        ];

        $heartbeat = [
            "topic" => "phoenix",
            "event" => "heartbeat",
            "ref" => "1",
            "payload" => []
        ];

        $deferred = new Deferred;

        $websocket->on('message', function (MessageInterface $msg) use ($deferred, $websocket, $token) {

            $message = json_decode($msg, true);

            $deferred->notify($this->getNotify($message, $token));
        });

        $websocket->send(json_encode($connect, JSON_FORCE_OBJECT));

        $loop->addPeriodicTimer(30, function () use ($websocket, $heartbeat) {

            $websocket->send(json_encode($heartbeat, JSON_FORCE_OBJECT));
        });

        return $deferred->promise();
    }

    /**
     * @param $pack
     * @param $event
     * @return string
     */
    public function getWebHookToken($pack, $event)
    {
        $httpInstance = new HttpInstance(static::callbackBaseURL() . $pack . $event . "_" . $this->project . ":" . $this->key);

        $httpInstance->setGetParameters($this->project, $this->key);

        try {
            $response = json_decode($httpInstance->getResponse(), true);

            return $response['token'];
        } catch (\RuntimeException $ex) {

            return $this->createCallback("error", $ex);
        }
    }

    public function getNotify($message, $token)
    {
        if ($message["event"] == "joined") {

            return $this->createCallback("join", $message);
        } elseif ($message["event"] == "new_msg") {
            if (!isset($message["payload"]["token"])) {

                return $this->createCallback("error", $message["payload"]["body"]);
            } else {

                return $this->createCallback("message", $message["payload"]["body"]);
            }
        }

        return null;
    }

    /**
     * @param $state
     * @param $message
     * @return string
     */
    public function createCallback($state, $message)
    {
        $callback[$state] = $message;

        return json_encode($callback, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

}
