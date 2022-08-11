<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class stotax
{

    function __construct($username,$password, $debug = false)
    {
        $this->stotax_user = $username;
        $this->stotax_pass = $password;
        $this->stotax_url = "https://www.stotax-online.de";
        
        $this->cookie_file = "/tmp/cookiefile";

        $browserdriver_params = ['--headless','--no-sandbox'];
        if ($debug) {
            $browserdriver_params = ['--no-sandbox'];
        }
        
        
        $this->client = \Symfony\Component\Panther\Client::createChromeClient('/usr/bin/chromedriver', 
            $browserdriver_params, 
            [
            'capabilities' => [
                'goog:loggingPrefs' => [
                    'browser' => 'ALL', // calls to console.* methods
                    'performance' => 'ALL' // performance data
                ]
            ]
        ]);

        $this->fachlichkeiten = array(
            "Rewe-Bankbelege" => "62417408-8a0b-432f-a317-9df6da785f22",
            "Rewe-Buchungsbelege" => "a9689686-837e-e311-98be-00ff678cb26f"
        );

        $this->header = $this->get_stotax_jwt();
        $this->mandant = $this->get_mandant();
        $this->user_info = $this->get_user_info_from_jwt($this->header["Authorization"]);
    }

    function stotax_upload_file($file, $fachlichkeit = "Rewe-Buchungsbelege", $time = NULL)
    {
        $header = $this->header;

        $header["Sec-Fetch-Dest"] = "empty";
        $header["Sec-Fetch-Mode"] = "cors";
        $header["Sec-Fetch-Site"] = "same-origin";
        $header["TE"] = "trailers";
        $header["Referer"] = "https://www.stotax-online.de/auswertungen/";
        $header["Host"] = "www.stotax-online.de";
        $header["Origin"] = "https://www.stotax-online.de";
        $header["Connection"] = "keep-alive";
        $header["Accept-Encoding"] = "gzip, deflate, br";

        $fachlichkeit_id = $this->fachlichkeiten[$fachlichkeit];
        $mandant = $this->mandant;
        $post_url = "https://www.stotax-online.de/auswertungen/api/v1/mandant/$mandant/beleg?fachlichkeitId=$fachlichkeit_id&customSubject=&privateData=false";

        $split = preg_split("/\//", $file);
        $upload_filename = array_pop($split);
        $cf = new CURLFile($file, mime_content_type($file), $upload_filename);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        if (isset($time)) {
            list($y,$m,$d) = preg_split("/-/",$time);
            $fachlichkeitsZeitraumVon = date("D M d Y", mktime(0,0,0,$m,$d,$y));
        } else {
            $fachlichkeitsZeitraumVon = 'null';
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            // ATTACH FILE UPLOAD
            "file" => $cf,
            "fachlichkeitsZeitraumVon" => $fachlichkeitsZeitraumVon,
            "text" => 'Autouploaded',
            "zeitraumTyp" => 8
        ]);

        foreach ($header as $k => $v) {
            $curl_header[] = "$k: $v";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return "CURL ERROR - " . curl_error($ch);
        } 
        else {
            return $result;
        }
    }

    function stotax_upload_files($files, $fachlichkeit = "Rewe-Buchungsbelege", $time = NULL)
    {
        $header = $this->header;
        
        $header["Sec-Fetch-Dest"] = "empty";
        $header["Sec-Fetch-Mode"] = "cors";
        $header["Sec-Fetch-Site"] = "same-origin";
        $header["TE"] = "trailers";
        $header["Referer"] = "https://www.stotax-online.de/auswertungen/";
        $header["Host"] = "www.stotax-online.de";
        $header["Origin"] = "https://www.stotax-online.de";
        $header["Connection"] = "keep-alive";
        $header["Accept-Encoding"] = "gzip, deflate, br";
        
        $fachlichkeit_id = $this->fachlichkeiten[$fachlichkeit];
        $mandant = $this->mandant;
        $post_url = "https://www.stotax-online.de/auswertungen/api/v1/mandant/$mandant/beleg?fachlichkeitId=$fachlichkeit_id&customSubject=&privateData=false";
        
        foreach ($files as $index => $file) { 
            $postData['file[' . $index . ']'] = curl_file_create(
                realpath($file),
                mime_content_type($file),
                basename($file)
                );
            
        }

        if (isset($time)) {
            list($y,$m,$d) = preg_split("/-/",$time);
            $fachlichkeitsZeitraumVon = date("D M d Y", mktime(0,0,0,$m,$d,$y));
        } else {
            $fachlichkeitsZeitraumVon = 'null';
        }
        
        $postData["fachlichkeitsZeitraumVon"] = $fachlichkeitsZeitraumVon; 
        $postData["text"] = "Autouploaded";
        $postData["zeitraumTyp"] = 8 ; 
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        
        foreach ($header as $k => $v) {
            $curl_header[] = "$k: $v";
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return "CURL ERROR - " . curl_error($ch);
        }
        else {

            return $result;
        }
    }
    
    
    
    function stotax_download_files($save_dir = null)
    {
        
        if (isset($save_dir)) {
            if (!file_exists($save_dir)) {
                die("Save Directory does not exist!");
            }
        } else {
            $save_dir = "/tmp/"; 
        }
 
        $this->client->waitForVisibility('.eingaenge');
        $this->client->executeScript("document.querySelector('li[title=\'Anwendungsauswahl\'] span[class=\'ng-binding\']').click();");
        $this->client->clicklink('Eingänge (Auswertungen)');
        $this->client->waitForVisibility('.downloadLink');
        $crawler = $this->client->refreshCrawler();
 
        $list = $crawler->filterXPath('//table//tr[starts-with(@id, "item_")]');
       
        foreach ($list as $trnode) { 
            $item_id = $trnode->getAttribute('id');
            $item_ids[] = $item_id; 
            $filenames[$item_id] = $crawler->filterXPath("//tr[@id='$item_id']//div[@class='downloadLink ng-binding']")->text();
        }
        array_unique($item_ids);
        array_unique($filenames);
        $downloaded = array();

        foreach ($item_ids as $document_id) { 
            
            
            $header = $this->header;
            
            $header["Sec-Fetch-Dest"] = "empty";
            $header["Sec-Fetch-Mode"] = "cors";
            $header["Sec-Fetch-Site"] = "same-origin";
            $header["TE"] = "trailers";
            $header["Referer"] = "https://www.stotax-online.de/auswertungen/";
            $header["Host"] = "www.stotax-online.de";
            $header["Origin"] = "https://www.stotax-online.de";
            $header["Connection"] = "keep-alive";
            $header["Accept-Encoding"] = "gzip, deflate, br";
            $header["Accept"] = "application/json, text/plain, */*";
            
            $filename = $document_id."_".$filenames[$document_id];
            if (file_exists($save_dir."/".$filename)) { 
                $downloaded[] = "skipped: ".$save_dir."/".$filename ; 
                continue; 
            }
            $document_id = str_replace("item_","",$document_id);
            $get_url = "https://www.stotax-online.de/auswertungen/api/v1/accesstoken?baseString=api%2Fv1%2Fdokument%2F$document_id%257CDokument%257C$document_id";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $get_url);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
             
            foreach ($header as $k => $v) {
                $curl_header[] = "$k: $v";
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                return "CURL ERROR - " . curl_error($ch);
            }
            else {
                $token_result = json_decode($result);
            }
            $accessToken = $token_result->accessToken; 
            
            unset($curl_header);
            unset($header["Authorization"]);
            
            //get the file and save it
            $get_url = "https://www.stotax-online.de/auswertungen/api/v1/dokument/$document_id?accessKey=$accessToken";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $get_url);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
            
            foreach ($header as $k => $v) {
                $curl_header[] = "$k: $v";
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                return "CURL ERROR - " . curl_error($ch);
            }

            $fh = fopen($save_dir."/".$filename,"a");
            fputs($fh,$result,strlen($result));
            fclose($fh);
            $downloaded[] = "downloaded: ". $save_dir."/".$filename ; 
        }
        return $downloaded; 
        
    }

    function get_stotax_jwt()
    {
        $this->client->followRedirects(true);
        $size = new WebDriverDimension(1600, 1500);
        $this->client->manage()
            ->window()
            ->setSize($size);

        $crawler = $this->client->request('GET', 'https://www.stotax-online.de/');

        $this->client->clicklink('Anmelden');

        $this->client->waitForVisibility('#authorizationFrame');
        $authorizationFrame = $this->client->findElement(WebDriverBy::cssSelector('#authorizationFrame'));

        $this->client->switchTo()->frame($authorizationFrame);

        $this->client->findElement(WebDriverBy::cssSelector('input#username'))->sendKeys($this->stotax_user);
        $this->client->findElement(WebDriverBy::cssSelector('input#password'))->sendKeys($this->stotax_pass);

        $this->client->executeScript("document.querySelector('#loginForm button').click()");

        $driver = $this->client->getWebDriver();
        $log = $driver->manage()->getLog("performance");

        
        foreach ($log as $entry) { 
            $log_entry = json_decode($entry["message"]);
            
            if (isset($log_entry->message->params->request->headers)) {
                $header = $log_entry->message->params->request->headers;
                $header = (array) $header;

                if (isset($header["Authorization"])) { 
                    break;
                }
            }
        }
        $this->client->switchTo()->defaultContent();
        return $header;
        
    }
    
    function get_user_info_from_jwt($token) { 
        $jwt = substr($token,7);
        list($jwt,$data,$sign) = preg_split("/\./",$jwt); 
        $jsdata = json_decode($this->base64url_decode($data)); 
        return $jsdata;
    }
    
    function get_mandant() {
        
        $get_url = "https://www.stotax-online.de//auswertungen/api/v1//application/rechte";
        
        $header = $this->header;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $get_url);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        
        foreach ($header as $k => $v) {
            $curl_header[] = "$k: $v";
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return "CURL ERROR - " . curl_error($ch);
        }
        else {
            $token_result = json_decode($result);
        }
        
        return $token_result[0]->entityId;
        
    }
    
    function base64url_decode($data) { 
        return base64_decode( strtr( $data, '-_', '+/') . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 ));
    }
    
}
