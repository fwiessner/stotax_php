<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class stotax
{

    function __construct($username,$password)
    {
        $this->stotax_user = $username;
        $this->stotax_pass = $password;
        $this->stotax_url = "https://www.stotax-online.de";
        
        $this->cookie_file = "/tmp/cookiefile";

        $this->client = \Symfony\Component\Panther\Client::createChromeClient('/usr/bin/chromedriver', 
            ['--headless'], 
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

        // $fp = fopen(dirname(__FILE__).'/errorlog.txt', 'w');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        // curl_setopt($ch, CURLOPT_STDERR, $fp);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            // ATTACH FILE UPLOAD
            "file" => $cf,
            "fachlichkeitsZeitraumVon" => 'null',
            "text" => 'Autouploaded',
            "zeitraumTyp" => 1
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
            // $info = curl_getinfo($ch);
            // print_r($info);
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
        //var_dump($filenames);

        //var_dump($item_ids);
        
        // URL api/v1/dokument/80b71411-ce0c-ed11-ad81-0050568d5f34%7CDokument%7C80b71411-ce0c-ed11-ad81-0050568d5f34
        // https://www.stotax-online.de/auswertungen/api/v1/accesstoken?baseString=api%2Fv1%2Fdokument%2F80b71411-ce0c-ed11-ad81-0050568d5f34%257CDokument%257C80b71411-ce0c-ed11-ad81-0050568d5f34
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
            
            $filename = $filenames[$document_id];
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
                // $info = curl_getinfo($ch);
                // print_r($info);
                $token_result = json_decode($result);
            }
            $accessToken = $token_result->accessToken; 
            //print $accessToken.PHP_EOL ;
            
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

        $last_log_entry = array_pop($log);

        $log_entry = json_decode($last_log_entry["message"]);

        $header = $log_entry->message->params->request->headers;
        $header = (array) $header;
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