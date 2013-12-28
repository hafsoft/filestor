<?php
/**
 * filestor
 * copyright (c) 2013 abie
 *
 * @author abie
 * @date 12/27/13 4:19 PM
 */

namespace filestore;

class Client {
    private $appName;
    private $appKey;
    private $appKeyId;
    private $url;

    public function __construct($url, $appName, $appKey, $appKeyId = 1)
    {
        $this->url = $url;
        $this->appKey   = $appKey;
        $this->appKeyId = $appKeyId;
        $this->appName  = $appName;
    }

    public function uploadFile($fileName) {
        $token = $this->getToken();
        $fileMD5 = md5_file($fileName);
        $key = sha1("{$this->appName}:$fileMD5:$token:{$this->appKey}");

        $req = $this->getHttpRequest('upload');
        $req->addPostField('token', $token);
        $req->addPostField('key', $key);
        $req->addPostField('file', '@' . $fileName);

        $responseText = $req->send();
        if ($responseText && ($response = json_decode($responseText))) {
            if ($response->status == 'OK' && $response->data) {
                return $response->data->url;
            }

            throw new \Exception('Can not upload file: ' . $response->error->msg, $response->error->code);
        }
        throw new \Exception('Can not upload file: Unknown error'); // ?
    }

    private function getToken() {
        $salt = md5(rand(0, 999999) . time());
        $key = sha1("{$this->appName}:$salt:{$this->appKey}");

        $req = $this->getHttpRequest('token');
        $req->addPostField('salt', $salt);
        $req->addPostField('key', $key);
        $responseText = $req->send();
        if ($responseText && ($response = json_decode($responseText))) {
            if ($response->status == 'OK' && $response->data) {
                return $response->data->token;
            }

            throw new \Exception('Can not get token: ' . $response->error->msg, $response->error->code);
        }
        throw new \Exception('Can not get token: Unknown error'); // ?
    }

    protected function getHttpRequest($action) {
        $req = new CurlHttpRequest();
        $req->open('POST', $this->url . '?a=' . $action);
        $req->addPostField('app', $this->appName);
        $req->addPostField('keyId', $this->appKeyId);
        return $req;
    }

}

interface IHttpRequest {
    public function open($method, $url);
    public function setHeader($name, $value);
    public function addPostField($name, $value);
    public function send($data = null);
}
class CurlHttpRequest implements IHttpRequest {
    private $ch;
    private $header = array();
    private $postFields = array();

    public function __construct() {
        $this->ch = curl_init();
    }

    public function __destruct() {
        curl_close($this->ch);
    }

    public function open($method, $url)
    {
        $opts = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HEADER => FALSE,
        );

        if (strtoupper($method) == 'POST') {
            $opts[CURLOPT_POST] = TRUE;
        }

        $this->setOpts($opts);
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

    public function setOpt($name, $value = null) {
        return curl_setopt($this->ch, $name, $value);
    }

    public function setOpts($options) {
        return curl_setopt_array($this->ch, $options);
    }
}