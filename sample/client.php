<?php

include './../lib/filestor-client.php';

$filestor = new filestore\Client(
    'http://localhost/filestor/sample/server/',
    'testapp',
    '1234567890'
);

echo $filestor->uploadFile('');