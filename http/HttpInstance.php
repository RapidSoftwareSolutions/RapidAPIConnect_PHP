<?php

namespace RapidApi\http;

class HttpInstance
{
    /**
     * @var resource
     */
    private $curlInstance;
    /**
     * @var int
     */
    private $httpCode;
    /**
     * @var string
     */
    private $response;

    /**
     * httpInstance constructor.
     *
     * @param $url
     */
    public function __construct($url)
    {
        $this->curlInstance = curl_init($url);
    }

    public function setParameters($project, $key, $args)
    {
        curl_setopt($this->curlInstance, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization: Basic ' . base64_encode($project . ":" . $key)
            ]);

        curl_setopt($this->curlInstance, CURLOPT_POSTFIELDS, $args);

        curl_setopt($this->curlInstance, CURLOPT_RETURNTRANSFER, true);
    }

    public function getResponse()
    {
        $this->response = curl_exec($this->curlInstance);

        $this->httpCode = curl_getinfo($this->curlInstance, CURLINFO_HTTP_CODE);

        $error = curl_error($this->curlInstance);

        $errno = curl_errno($this->curlInstance);

        if (is_resource($this->curlInstance)) {

            curl_close($this->curlInstance);
        }

        if (0 !== $errno) {

            throw new \RuntimeException($error, $errno);
        }

        return $this->response;
    }

    public function getLastHttpCode()
    {
        return $this->httpCode;
    }

}