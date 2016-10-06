<?php

namespace RapidApi;

use RapidApi\Utils\HttpInstance;

class RapidApi
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

            if ($httpInstance->getLastHttpCode() != 200 || !isset($response['outcome'])) {

                $callback['error'] = $response;

                return $callback;
            } else {

                $callback[$response['outcome']] = $response['payload'];

                return $callback;
            }

        } catch (\RuntimeException $ex) {

            $callback['error'] = sprintf('Http error %s with code %d', $ex->getMessage(), $ex->getCode());

            return $callback;
        }
    }

}