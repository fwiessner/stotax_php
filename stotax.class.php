<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use GuzzleHttp\Client;

class stotax
{

    function __construct($username,$password, $debug = false)
    {
        $this->stotax_user = $username;
        $this->stotax_pass = $password;
        $this->stotax_url = "https://www.stotax-online.de";
        
        $this->cookie_file = "/tmp/cookiefile";

        $browserdriver_params = ['--headless','--no-sandbox','--disable-gpu'];
        if ($debug) {
            $browserdriver_params = ['--no-sandbox','--disable-gpu'];
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

    
    function stotax_upload_belege($files, $fachlichkeit = "Rewe-Buchungsbelege", $time = NULL) { 
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
        
        $user_info = (array) $this->user_info ;
        $user_identifier = $user_info["http://schemas.stotax.de/2012/02/identity/claims/useridentifier"];
        
        $post_url = "https://www.stotax-online.de/selectnetcore/p0/api/$user_identifier/auswertungen/createBeleg";
        
        
        if (isset($time)) {
            list($y,$m,$d) = preg_split("/-/",$time);
            $fachlichkeitsZeitraumVon = date("Y-m-d", mktime(0,0,0,$m,$d,$y)) . "T00:00:00";
        } else {
            $fachlichkeitsZeitraumVon = 'null';
        }
        
        
        $fileObjects = array();
        
        
        foreach ($files as $index => $file) {
            
            $file_base = basename($file);
            
            $fileObjects[$file_base] = ["id" => "00000000-0000-0000-0000-000000000000", "content" => base64_encode(file_get_contents($file)), "extension" => pathinfo($file, PATHINFO_EXTENSION) , "mimeType" => mime_content_type($file), "dokuments" => array(), "kundenpostfachDokuments" => array() ];
        }
        
        
         
        
        $data = ["text" => "autoupload", "nachricht" => "autoupload", "fileObjects" => $fileObjects , "useSubjectForEmail" => false, "private" => false, "synchronizationOptions" => 0 , "fachlichkeitsDatumVonL" => $fachlichkeitsZeitraumVon, "mandantId" => "00000000-0000-0000-0000-000000000000", "fachlichkeitId" => $fachlichkeit_id, "einstellVorgangTyp" => 1, "zeitraumTypen" => 8, "antwortId" => null, "stotaxId" => null, "kanzleiIdentifier" => null] ;
        
        
        $gclient = new Client();
        
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
        $header["content-type"] = "application/json; charset=utf-8";
        
       
        
        
        $response = $gclient->request('PUT',$post_url, [
            'headers' => $header,
            'body' => json_encode($data),
            
        ]);
        
        
        $data = $response->getBody()->getContents() ;
        
        
        return $data;
        
        
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

        $crawler = $this->client->refreshCrawler();
        
        $user_info = (array) $this->user_info ; 
        $user_identifier = $user_info["http://schemas.stotax.de/2012/02/identity/claims/useridentifier"];
        
        $vorgaenge_url = "https://www.stotax-online.de/selectnetcore/p0/api/$user_identifier/auswertungen/einstellVorgaenge";
        
       
        
        $data_raw = '{"baseData":{"pageSize":1000,"currentPage":1,"dateFrom":null,"dateTo":null,"searchText":null,"filterFachlichkeitId":"92689686-837e-e311-98be-00ff678cb26f","paginationEntityId":null,"paginationEntityType":0,"includeGestricheneBewegung":false,"vorgangsFilterTyp":0},"mitAntwortInfo":true}';
        $data_json = json_decode($data_raw);
        
       
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
        $header["content-type"] = "application/json; charset=utf-8";
        
     
        
        $gclient = new Client();
        
        $response = $gclient->request('POST',$vorgaenge_url, [
            'headers' => $header,
            'body' => json_encode($data_json), 
            
        ]);
        
        
        $data = $response->getBody()->getContents() ; 
        $data_json = json_decode($data);
        //var_dump($data_json);
        
        $einstellVorgaenge = $data_json->einstellVorgaenge ; 
        
        foreach ($einstellVorgaenge as $vorgang) { 
            foreach ($vorgang->dokuments->{'$values'} as $doc) { 
                
                $to_download[$doc->id] = $doc->name ; 
            }
        }
        
        
        
    
        
        
        // fuer jedes document getott machen um den onetimekey zu holen: 
        
        // https://www.stotax-online.de/selectnetcore/p0/api/$user_identifier/download/getott
        
        // payload: {"target":"https://www.stotax-online.de/selectnetcore/p0/api/f0484e49-58e4-429c-bb3e-cc0a37a4941e/download/dokumentauswertungen/8a6380df-e4a6-ed11-ad8d-0050568d5f34/972_10151_000_000000_202301_20230207_131546_LStA-Druck.PDF"}
        $downloaded = array();
        
        foreach ($to_download as $id => $name) { 
            
            $document_id = $id; 
            
            
            $filename = "item_". $document_id."_".$name;
            if (file_exists($save_dir."/".$filename)) {
                $downloaded[] = "skipped: ".$save_dir."/".$filename ;
                continue;
            }
            
            $ott = $this->getott($id,$name);
            $url_fname = str_replace(" ", "", $name);
            $url = "https://www.stotax-online.de/selectnetcore/p0/api/$user_identifier/download/dokumentauswertungen/$id/$url_fname?onetimetokenid=$ott";
            
            //echo $url ;
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
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
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
            
            unset($header);
            unset($curl_header);
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
    
    function getott($id,$name) {
        
        $name = str_replace(" ", "", $name);
        $user_info = (array) $this->user_info ;
        $user_identifier = $user_info["http://schemas.stotax.de/2012/02/identity/claims/useridentifier"];
        
        $url = "https://www.stotax-online.de/selectnetcore/p0/api/$user_identifier/download/getott";
        
        $gclient = new Client();
        
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
        $header["content-type"] = "application/json; charset=utf-8";
        
        $payload = '{"target":"https://www.stotax-online.de/selectnetcore/p0/api/'.$user_identifier.'/download/dokumentauswertungen/'.$id.'/'.$name.'"}';
       
        
        $response = $gclient->request('POST',$url, [
            'headers' => $header,
            'body' => ($payload),
            
        ]);
        
        
        $data = $response->getBody()->getContents() ;
        $data_json = json_decode($data);

        
        return $data_json->content;
    }
    
}
