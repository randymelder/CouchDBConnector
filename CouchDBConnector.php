<?php  

/**
 *  Utilize CouchDB's RESTful interface from PHP.
 *
 * @author  randymelder@gmail.com
 * @since   2011
 * @version 1.0
 *
 */

class CouchDBConnector
{
    private $host;
    private $port; 
    private $user;
    private $pass;
    private $db;
    private $sock;
    private $view;
    
    const DEBUG_ON = 0;
    
	/**
	 * Implementation is easy. Here's an example
	 * $relax = new CouchDBConnector(array('host'=>'my.host.name','port'=>5984));
	 * 
	 */
    public function __construct ($params)
    {
        $this->host = $params['host'];
        $this->port = $params['port'];
    }
    
    /**
     * Takes you literally.
     */
    public function setDb($dbname = NULL)
    {
        $this->db = $dbname;
    }
    
    public function setHost($h = NULL)
    {
        $this->host = $h;
    }
    
    public function setPort($p = NULL)
    {
        $this->port = $p;
    }
    
    public function setUser($u = NULL)
    {
        $this->user = $u;
    }
    
    public function setPass($p = NULL)
    {
        $this->pass = $p;
    }
    
    public function setView($v = NULL)
    {
        $this->view = $v;
    }
    /**
     * getNamedSubView() is probably 99% of everything you'll ever need. 
     * Returns a JSON string.
     * @param String $db
     * @param String $view
     * @param String $namedsubview
     * @param Array $arrParams
     * @return String 
     */
    public function getNamedSubView($db, $view, $namedsubview, $arrParams)
    {
        $args = '';
        if (is_array($arrParams))
        {
            foreach ($arrParams AS $key=>$val)
            {
                $args .= $key.'='.$val.'&';
            }
        }
        $url = '/'.$db.'/_design/'.$view.'/_view/'.$namedsubview.'?'.$args;
        
        return $this->stripHeadersToJSON($this->get($url));
    }
    
    
    /**
     * Make a new database/bucket named $data
     * @param String $data
     * @return type 
     */
    public function put($data = NULL)
    {
        if (NULL == $data) return -1;
        return $this->execute('PUT', '/'.$data);
    }
    
    /**
     * $url is anything you want.
     * @param String $url
     * @return String 
     */
    public function get($url = NULL)
    {
        if (NULL == $url) return -1;
        return $this->execute('GET', $url);
    }
    
    public function getUUID()
    {
        return $this->execute('GET', '/_uuids');
    }
    
    /**
     * Added a document to the current $this->db. 
     * Parameter: $data needs to be formatted JSON.
     * @param String $data
     * @return mixed 
     */
    public function post($data = NULL)
    {
        if (NULL == $data) return -1;
        return $this->execute('POST', '/'.$this->db, $data);
    }
    
    /**
     * Just asking to be overloaded
     * @param String $data
     * @return mixed
     */
    public function delete($data = NULL)
    {
        if (NULL == $data) return -1;
    }
    
    /**
     * 
     * @param String $method
     * @param String $url
     * @param String $data
     * @return String 
     */
    private function execute($method = 'GET', $url = '/', $data = NULL)
    {
        if(!$this->host || !$this->port)
        {
            throw new Exception ( 'NULL host in: '.__CLASS__.'::'.__FUNCTION__.' for URL: '.$url );
            return NULL;
        }
        $req = "{$method} {$url} HTTP/1.0\r\nHost: {$this->host}\r\n";
        if (CouchDBConnector::DEBUG_ON) error_log(__FILE__.' line: ('.$req.')');
        if($this->user || $this->pass)
            $req    .= 'Authorization: Basic '
                    .base64_encode($this->username.':'.$this->password)."\r\n";

        if($data) {
            $req    .= 'Content-Length: '.strlen($data)."\r\n";
            $req    .= 'Content-Type: application/json'."\r\n\r\n";
            $req    .= $data."\r\n";
        } else {
            $req    .= "\r\n";
        }
        //open socket
        $err_num    = ''; $err_string = '';
        $this->sock = @fsockopen($this->host, $this->port, $err_num, $err_string);
        if(!$this->sock)            
            throw new Exception("fsockopen() failed."
                    .__CLASS__.__FUNCTION__.__LINE__
                    .' Error Num:'.$err_num.' Err String:'.$err_string);
        
        //write data
        fwrite($this->sock, $req);
        $response   = '';
        while(!feof($this->sock)) {
            $response .= fgets($this->sock);
        }
        
        //close socket
        fclose($this->sock);
        $this->sock = NULL;
        
        return $response;
    }
    
    /**
     * Takes a socket respons and strips down to JSON
     * @param String $data
     * @return String
     */
    public function stripHeadersToJSON($data)
    {
        $len    = strlen($data);
        $json   = '';
        for ($i = 0; $i < $len; ++$i)
        {
            if($data[$i] == '{')
            {
                $json = substr($data, $i);
                break;
            }
        }
        
        return strtr($json, "\r\n", "  ");
    }
    
    /**
     * Ask CouchDB for a uuid
     * @return String
     */
    public function getStringOfUUID()
    {
        $arr = json_decode($this->stripHeadersToJSON($this->getUUID()));
        
        return $arr->uuids[0];
    }
    
    public function getDocumentByID($id = NULL)
    {
        if (!is_string($id))
            throw new Exception("Invalid document id:".$id);
        
        return $this->get('/'.$this->db.'/'.$id);
    }
    
    public function jqplotNotationFromJSON($json)
    {
        $str = '';
        $arr = json_decode($json);
        //error_log(__CLASS__.'::'.__FUNCTION__.' line: '.__LINE__."\njson: ".$json."\n\n");
        if (trim($json) == '{"rows":[]}')
            return "[['NULL', 0]]";
        
        foreach($arr AS $top)
        {
            foreach($top AS $c)
            {
                $str .= "['".$c->key."',".$c->value."],";
            }
        }
        $str = substr($str, 0, strlen($str) - 1);
        return '[ '.$str.' ]';
    }
}


