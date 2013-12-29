<?php
/*
 * Filestor - PHP Based File Storage Service
 *
 * Copyright (c) 2013 Abi Hafshin <abi.hafshin@ui.ac.id>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */


/**
 * Filestor Client<br/>
 * This class is used for uploading one or more files to the Filestor Server
 *
 * @package  Filestore
 * @version  0.9 beta
 * @author   Abi Hafshin <abi.hafshin@ui.ac.id>
 */
class FilestorClient
{
    /** @var string Current application's name */
    private $appName;

    /** @var string application's key */
    private $appKey;

    /** @var int key id */
    private $appKeyId;

    /** @var string Server url */
    private $url;

    /**
     * Construct new Filestor client object
     *
     * @param string $url server's url
     * @param string $appName application's name
     * @param string $appKey application's key
     * @param int $appKeyId [optional] application's key id
     */
    public function __construct($url, $appName, $appKey, $appKeyId = 1)
    {
        $this->url      = $url;
        $this->appKey   = $appKey;
        $this->appKeyId = $appKeyId;
        $this->appName  = $appName;
    }

    /**
     * Upload a file to the server and return it's url
     *
     * @example
     *   $filestor = new FilestorClient($url, $appName, $appKey);
     *   try {
     *       $returnUrl = $filestor->uploadFile($filePath);
     *   } catch (Exception $e) {
     *       // handle error
     *   }
     *
     * @param string $fileName path name of the file which will be uploaded
     * @return string url that can be used to access the file which have been uploaded
     * @throws Exception
     */
    public function uploadFile($fileName)
    {
        $token   = $this->getToken();
        $fileMD5 = md5_file($fileName);
        $key     = sha1("{$this->appName}:$fileMD5:$token:{$this->appKey}");

        $req = $this->getHttpRequest('upload');
        $req->addPostField('token', $token);
        $req->addPostField('key', $key);
        $req->addPostField('file', '@' . $fileName);

        $responseText = $req->send();
        if ($responseText && ($response = json_decode($responseText))) {
            if ($response->status == 'OK' && $response->data) {
                return $response->data->url;
            }

            throw new Exception('Can not upload file: ' . $response->error->msg, $response->error->code);
        }
        throw new Exception('Can not upload file: Unknown error'); // ?
    }

    /**
     * method for retrieving access token for uploading file
     * this method is called internally. usually you don't need to call this directly
     *
     * @see uploadFile
     * @return string access token
     * @throws Exception
     */
    private function getToken()
    {
        $salt = md5(rand(0, 999999) . time());
        $key  = sha1("{$this->appName}:$salt:{$this->appKey}");

        $req = $this->getHttpRequest('token');
        $req->addPostField('salt', $salt);
        $req->addPostField('key', $key);
        $responseText = $req->send();
        if ($responseText && ($response = json_decode($responseText))) {
            if ($response->status == 'OK' && $response->data) {
                return $response->data->token;
            }

            throw new Exception('Can not get token: ' . $response->error->msg, $response->error->code);
        }
        throw new Exception('Can not get token: Unknown error'); // ?
    }

    /**
     * Method for creating http request object which will be used for sending request to the server. <br />
     * This method is called internally. Usually you don't need to call this directly.
     *
     * @see IFilestorHttpRequest
     * @param string $action action name
     * @return IFilestorHttpRequest
     */
    protected function getHttpRequest($action)
    {
        $req = new CurlHttpRequest();
        $req->open('POST', $this->url . '?a=' . $action);
        $req->addPostField('app', $this->appName);
        $req->addPostField('keyId', $this->appKeyId);
        return $req;
    }
}

/**
 * This interface is used FilestorClient for sending request to
 * and retrieving respond from server.
 *
 * This class is used internally by FilestorClient.
 * I think you will not interest to it. :)
 *
 */
interface IFilestorHttpRequest
{
    /**
     * Open a connection
     *
     * @param string $method Http request method; usually GET or POST
     * @param string $url remote url to be connected
     * @return bool return true on success
     */
    public function open($method, $url);

    /**
     * Set http header
     *
     * @example
     *   $request->setHeader('Content-type', 'application/json');
     *
     * @param string $name header name
     * @param string $value header value
     * @return bool return true on success
     */
    public function setHeader($name, $value);

    public function addPostField($name, $value);

    /**
     * Send data to the server and return the respond
     *
     * @param string|array $data [optional] data to be sent
     * @return string http respond body
     */
    public function send($data = null);
}

/**
 * This class is used internally by FilestorClient.
 * I think you will not interest to it. :)
 *
 * @see IFilestorHttpRequest
 */
class CurlHttpRequest implements IFilestorHttpRequest
{
    private $ch;
    private $header = array();
    private $postFields = array();


    public function __construct()
    {
        $this->ch = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function open($method, $url)
    {
        $opts = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
        );

        if (strtoupper($method) == 'POST') {
            $opts[CURLOPT_POST] = true;
        }

        return $this->setOpts($opts);
    }

    public function setHeader($name, $value)
    {
        $this->header[$name] = $value;
    }

    public function addPostField($name, $value)
    {
        $this->postFields[$name] = $value;
    }

    public function send($data = null)
    {
        if ($this->header) {
            $this->setOpt(CURLOPT_HTTPHEADER, $this->header);
        }

        if ($this->postFields) {
            $this->setOpt(CURLOPT_POSTFIELDS, $this->postFields);
        }

        if ($data) {
            $this->setOpt(CURLOPT_POSTFIELDS, $data);
        }

        return curl_exec($this->ch);
    }

    public function setOpt($name, $value = null)
    {
        return curl_setopt($this->ch, $name, $value);
    }

    public function setOpts($options)
    {
        return curl_setopt_array($this->ch, $options);
    }
}