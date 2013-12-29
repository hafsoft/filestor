#!/usr/bin/env php
<?php

// TODO: handle exception


$opts = getopt('ha:k:i:u:', array('help', 'app:', 'key:', 'keyid:', 'url:'));

$url = '';
$app = '';
$key = '';
$keyId = 1;

$showHelp = false;
$files = array();
for ($i = 1; $i<$argc; $i++) {
    switch ($argv[$i]) {
        case '-h':
        case '-?':
        case '--help':
            $showHelp = true;
            break;

        case '-u':
        case '--url':
            $url = $argv[++$i];
            break;

        case '-a':
        case '--app':
            $app = $argv[++$i];
            break;

        case '-k':
        case '--key':
            $key = $argv[++$i];
            break;

        case '-i':
        case '--keyid':
            $keyId = $argv[++$i];
            break;

        default:
            $files[] = $argv[$i];
    }
}

if (!$showHelp && $url != '' && $app != '' && $key != '' && $keyId != '' && count($files) > 0) {
    require_once './../../lib/filestor-client.php';
    $client = new FilestorClient($url, $app, $key, $keyId);

    foreach ($files as $f) {
        if (file_exists($f)) {
            $url = $client->uploadFile($f);
            echo "$f => $url\n";
        }
        else {
            echo "phpstor: File $f not exist!\n";
        }
    }
    return;
}

echo 'Filestor Client CLI
Usage:
    $ php cli.php -u|--url <url> -a|--app <app-name> \
      -k|--key <app-key> [-i|--keyid <app-key-id>] \
      <filename1> <filename2> ...

Options:
    -h  --help   show this help
    -u  --url    set server url
    -a  --app    set application name
    -k  --key    set application key
    -i  --keyid  set application key id

Example
    php cli.php -u http://localhost/upload/ -a testapp -k 1234567890 my-file.txt my-pic.jpg other-files.zip
';


