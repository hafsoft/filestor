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

// define constants
defined('BASE_DIR') || define('BASE_DIR', dirname(__FILE__));
defined('BASE_URL') || define(
'BASE_URL',
    'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])
);

/**
 * Filestor Server
 * This class is used for handling authentication and file upload requests from Filestor Client
 *
 * @package  Filestore
 * @version  0.9 beta
 * @author   Abi Hafshin <abi.hafshin@ui.ac.id>
 */
class FilestorServer
{
    private static $uploadErrors = array(
        0 => "There is no error, the file uploaded with success",
        1 => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
        2 => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
        3 => "The uploaded file was only partially uploaded",
        4 => "No file was uploaded",
        6 => "Missing a temporary folder"
    );

    private $appName;
    private $appDir;
    private $key;
    private $config = null;


    /**
     * Method for handling authentication and file upload requests from Filestor Client
     *
     * todo: block file extensions (.php .exe ...)
     *
     * @example
     *   <?php
     *   require_once "./filestor-server.php";
     *   FilestorServer::handleRequest($_GET, $_POST, $_FILES);
     *   return;
     *
     * @param string[] $get must $_GET
     * @param string[] $post must $_POST
     * @param string[] $file must $_FILES
     *
     * @return void
     */
    public static function handleRequest($get, $post, $file)
    {
        $status = 'OK';
        $data   = null;
        $error  = null;

        try {
            if (isset($get['a'], $post['app'], $post['keyId'])) {
                $action   = $get['a'];
                $appName  = $post['app'];
                $appKeyId = $post['keyId'];

                if ($action == 'token' && isset($post['salt'], $post['key'])) {
                    $app   = new FilestorServer($appName, $appKeyId);
                    $token = $app->createToken($post['salt'], $post['key']);
                    $data  = array('token' => $token);

                } elseif ($action == 'upload'
                    && isset($file['file'], $post['token'], $post['key'])
                ) {
                    $app = new FilestorServer($appName, $appKeyId);
                    $f   = $file['file'];
                    if ($f['error'] === UPLOAD_ERR_OK) {
                        $url  = $app->handleUpload(
                            $f['tmp_name'],
                            $f['name'],
                            $post['token'],
                            $post['key']
                        );
                        $data = array('url' => $url);

                    } else {
                        throw new Exception(
                            'Upload error: ' . self::$uploadErrors[$f['error']],
                            $f['error']
                        );
                    }

                } else {
                    throw new Exception('Invalid params');
                }

            } else {
                throw new Exception('Invalid arguments');
            }
        } catch (Exception $e) {
            $status = 'ERROR';
            $data   = null;
            $error  = array('msg' => $e->getMessage(), 'code' => $e->getCode());
        }

        $respond = array('status' => $status, 'data' => $data, 'error' => $error);
        echo json_encode($respond);
    }

    protected function __construct($app, $keyId)
    {
        $this->appName = $app;
        $this->appDir  = BASE_DIR . '/' . $app . '/';
        $confFile      = $this->appDir . 'config.php';
        if (file_exists($confFile)) {
            $this->config = $config = include $confFile;
            $keys         = $config['keys'];
            if (isset($keys[$keyId])) {
                $this->key = $keys[$keyId];
            } else {
                throw new Exception('Invalid Key ID');
            }

        } else {
            throw new Exception('Application not found');
        }
    }

    protected function createToken($salt, $key)
    {
        if (sha1("{$this->appName}:$salt:{$this->key}") == $key) {
            $token = time();
            do {
                $token     = md5($token . rand(0, 1000));
                $tokenFile = $this->getTokenFile($token);
            } while (file_exists($tokenFile));
            touch($tokenFile);
            return $token;
        }
        throw new Exception('Invalid key');
    }

    protected function handleUpload($tmpFile, $name, $token, $key)
    {
        $tokenFile = $this->getTokenFile($token);
        if (!file_exists($tokenFile)) {
            throw new Exception('Token not found');
        }

        $fileMd5 = md5_file($tmpFile);
        $oKey    = sha1("{$this->appName}:$fileMd5:$token:{$this->key}");
        if ($oKey == $key) {
            do {
                $fileDir = sprintf('/%s/%s%s/', $this->appName, date('YmdHi/s'), rand(1000, 9999));
            } while (file_exists(BASE_DIR . $fileDir));
            mkdir(BASE_DIR . $fileDir, 0777, true);

            $fileName = $fileDir . $name;
            if (move_uploaded_file($tmpFile, BASE_DIR . $fileName)) {
                return BASE_URL . $fileName;
            } else {
                throw new Exception('Error moving file');
            }
        }
        throw new Exception('Invalid key');
    }

    private function getTokenFile($token)
    {
        return BASE_DIR . '/sessions/' . $this->appName . '-' . sha1($token);
    }


}