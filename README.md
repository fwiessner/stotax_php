# stotax_php
PHP class for www.stotax-online.de to upload/download documents

This class gets JWT token via headless browser from www.stotax-online.de and then uploads or downloads files from the stotax-online.de Webinterface. chrome and chromedriver is required for this class to work properly. 

Examples:

```
<?php

require __DIR__ . '/vendor/autoload.php';
include ("stotax.class.php");

$s = new stotax("user@example.de","apassword");


//download files to folder (skipps already existing files)
$result = $s->stotax_download_files('/tmp/stotax') ; 
var_dump($result);



//upload file to stotax
$result = $s->stotax_upload_file("~/Downloads/test.pdf","Rewe-Buchungsbelege");
var_dump($result);
```


If you figure out a better way to get the JWT Token without a headless browser, let me know and make a commit/pull-request.
