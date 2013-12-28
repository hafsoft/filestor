<?php
/**
 * filestor
 * copyright (c) 2013 abie
 * Php 5.0
 *
 * @date    12/27/13 3:04 PM
 * @version CVS: <a>
 */

namespace filestore;

defined('BASE_DIR') || define('BASE_DIR', dirname(__FILE__));
defined('BASE_URL') || define(
    'BASE_URL',
    'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])
);

/**
 * Class Server
 *
 * @category Lalala
 * @package  Filestore
 * @author   Abi Hafshin <abi.hafshin@ui.ac.id>
 */
class Server
{

    private $_appName;
    private $appDir;
    private $key;
    private $config = null;

    private static $uploadErrors = array(
        0=>"There is no error, the file uploaded with success",
        1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
        2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form" ,
        3=>"The uploaded file was only partially uploaded",
        4=>"No file was uploaded",
        6=>"Missing a temporary folder"
    );


    /**
     * tes
     *
     * @param string[] $get  $_GET
     * @param string[] $post $_POST
     * @param string[] $file $_FILES
     *
     * @return void
     */
    public static function main($get, $post, $file)
    {
        $status = 'OK';
        $data = null;
        $error = null; //'Invalid arguments';

        try {
            if (isset($get['a'], $post['app'], $post['keyId'])) {
                $action = $get['a'];
                $appName = $post['app'];
                $appKeyId = $post['keyId'];

                if ($action == 'token' && isset($post['salt'], $post['key'])) {
                    $app = new Server($appName, $appKeyId);
                    $token = $app->createToken($post['salt'], $post['key']);
                    $data = array('token' => $token);

                } elseif ($action == 'upload'
                    && isset($file['file'], $post['token'], $post['key'])
                ) {
                    $app = new Server($appName, $appKeyId);
                    $f = $file['file'];
                    if ($f['error'] === UPLOAD_ERR_OK) {
                        $url = $app->handleUpload(
                            $f['tmp_file'], $f['name'], $post['token'], $post['key']
                        );
                        $data = array('url' => $url);

                    } else {
                        throw new \Exception(
                            'Upload error: ' . self::$uploadErrors[$f['error']],
                            $f['error']
                        );
                    }

                } else {
                    throw new \Exception('Invalid params');
                }

            } else {
                throw new \Exception('Invalid arguments');
            }
        } catch (\Exception $e) {
            $status = 'ERROR';
            $data = null;
            $error = array('msg' => $e->getMessage(), 'code' => $e->getCode());
        }

        $ret = array('status' => $status, 'data' => $data, 'error' => $error);
        if (isset($get['shell'])) {
            /**
             * tes
             *
             * @param mixed  $a      yeyey
             * @param string $prefix lalala
             *
             * @return void
             */
            function encodeArray($a, $prefix = '')
            {
                foreach ($a as $k => $v) {
                    if (is_array($v)) {
                        encodeArray($v, $k . '_');
                    } else {
                        echo $prefix, $k, '="', addslashes($v), '"', PHP_EOL;
                    }
                }
            }

            encodeArray($ret);
        }
        else {
            echo json_encode($ret);
        }
    }

    public function __construct($app, $keyId) {
        $this->_appName = $app;
        $this->appDir = BASE_DIR . '/' . $app . '/';
        $conffile = $this->appDir . 'config.php';
        if (file_exists($conffile)) {
            $this->config = $config = include $conffile;
            $keys = $config['keys'];
            if (isset($keys[$keyId])) {
                $this->key = $keys[$keyId];
            }
            else {
                throw new \Exception('Invalid Key ID');
            }

        } else {
            throw new \Exception('Application not found');
        }
    }

    public function createToken($salt, $key) {
        if (sha1("{$this->_appName}:$salt:{$this->key}") == $key) {
            $token = time();
            do {
                $token = md5($token . rand(0, 1000));
                $tokenFile = $this->getTokenFile($token);
            } while (file_exists($tokenFile));
            touch($tokenFile);
            return $token;
        }
        throw new \Exception('Invalid key');
    }

    public function handleUpload($tmpFile, $name, $token, $key) {
        $tokenFile = $this->getTokenFile($token);
        if (!file_exists($tokenFile)) {
            throw new \Exception('Token not found');
        }

        $fileMd5 = md5_file($tmpFile);
        $oKey = sha1("{$this->_appName}:$fileMd5:$token:{$this->key}");
        if ($oKey == $key) {
            do {
                $fileDir = sprintf('/%s/%s%s/', $this->_appName, date('YmdHi/s'), rand(1000, 9999));
            } while (file_exists(BASE_DIR . $fileDir));

            $fileName = $fileDir . $name;
            if (move_uploaded_file($tmpFile, BASE_DIR . $fileName)) {
                return BASE_URL . $fileName;
            } else {
                throw new \Exception('Error moving file');
            }
        }
        throw new \Exception('Invalid key');
    }

    private function getTokenFile($token) {
        return BASE_DIR . '/sessions/' . $this->_appName . '-' . sha1($token);
    }

    /**
     * lala
     *
     * @return string
     */
    public function getAppName()
    {
        return $this->_appName;
    }


}