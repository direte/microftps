<?php
namespace DiRete;

class MicroFtps
{
    const ERR_CONNECT = 'Connect to the server before executing commands';

    const ERR_URL_EMPTY = 'FTPS server url is empty';

    const ERR_USER_EMPTY = 'FTPS username is empty';

    const ERR_CURL_INIT = 'Could not initialize cURL';

    const ERR_OPEN_STREAM = 'Failed to open php://temp for writing';

	/**
	 * CURL resource handler
	 * @var resource
	 */
	private $curl;

    /**
	 * Server url
	 * @var string
	 */
	private $server;

    /**
	 * Username
	 * @var string
	 */
	private $username;

    /**
	 * Password
	 * @var string
	 */
	private $password;

    /**
	 * Options
	 * @var string
	 */
	private $opts;

	/**
	 * Server path
	 * @var string
	 */
	private $url;

    /**
	 * Indicates if the connection is in passive
	 * @var string
	 */
    private $passive;

    /**
	 * The port
	 * @var integer
	 */
    private $port;

    /**
	 * CURL timeout in seconds
	 * @var integer
	 */
    private $timeout;

    /**
	 * CURL response info
	 * @var array
	 */
    public $responseInfo = array();

    /**
	 * Constructor
	 *
	 * @param string $server Server url
	 * @param string $username Username
	 * @param string $password Password
	 * @param array $opts Options
	 */
	public function __construct($server, $username, $password = '', $opts = array())
    {
        // Connect if possible
        if($server && $username){
            $this->connect($server, $username, $password, $opts);
        }
    }

	/**
	 * Connect to FTP server over SSL/TLS
	 *
	 * @param string $server Server url
	 * @param string $username Username
	 * @param string $password Password
	 * @param array $opts Options
	 */
	public function connect($server, $username, $password, $opts = array())
    {
        if (!$server) {
			throw new \Exception(self::ERR_URL_EMPTY);
        }

		if (!$username) {
			throw new \Exception(self::ERR_USER_EMPTY);
        }

        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->opts = $opts;

        $defaultOpts = array(
            'passive' => true,
            'port' => 990,
            'timeout' => 10,
            'curlOptions' => array()
        );

        // Check for defaults
        foreach ($defaultOpts as $key => $value) {
            if(!isset($opts[$key])){
                $opts[$key] = $value;
            }
        }

        $this->passive = $opts['passive'];
        $this->port = $opts['port'];
        $this->timeout = $opts['timeout'];
		$this->url = "ftps://$server";
		$this->curl = curl_init();

		if (!$this->curl) {
            throw new \Exception(self::ERR_CURL_INIT);
        }

		// CURL options
		$curlOpts = array(
            CURLOPT_USERPWD => $username.':'.$password,
            CURLOPT_PORT => $this->port,
            CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
            CURLINFO_HEADER_OUT => true,
		);

		// CURL FTP enables passive mode by default
		if (!$this->passive) {
			$curlOpts[CURLOPT_FTPPORT] = '-';
        }

        foreach ($opts['curlOptions'] as $key => $value) {
            $curlOpts[$key] = $value;
        }

		foreach ($curlOpts as $key => $value) {
			curl_setopt($this->curl, $key, $value);
		}
	}

    /**
    * Read a file
    * @param string $filepath
    * @return string
    */
    public function read($filepath)
    {
        $this->init();
        $path = $this->url.$filepath;
        curl_setopt($this->curl, CURLOPT_URL, $path);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);

        return $this->exec($this->curl);
    }

    /**
    * List directory
    * @param string $dir Directory to list
    * @return array
    */
    public function listDir($dir)
    {
        $this->init();
        $path = $this->url.$dir;
        curl_setopt( $this->curl, CURLOPT_URL, $path);
        curl_setopt( $this->curl,CURLOPT_FTPLISTONLY, 1);
        curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1);
        $list = $this->exec($this->curl);

        return explode("\n", trim($list));
    }

	/**
	 * Write a file
	 * @param string $rFilename Remote filename, eg. '/somewhere/file.txt'
	 * @param string $lFilename Local filename to upload
	 * @return mixed
	 */
	public function write($rFilename, $lFilename)
    {
        $this->init();
        $path = $this->url.$rFilename;
		// Manage stream
		$stream = fopen('php://temp', 'w+');

		if (!$stream) {
            throw new Exception(self::ERR_OPEN_STREAM);
        }

		fwrite($stream, $lFilename);
		rewind($stream);
        curl_setopt( $this->curl, CURLOPT_UPLOAD, 1);
        curl_setopt( $this->curl, CURLOPT_URL, $path);
        curl_setopt( $this->curl, CURLOPT_INFILE, $stream);
        $result = $this->exec($this->curl);
		fclose( $stream );

        return $result;
	}

    /**
    * Delete specified file
    * @param string $rFilename Remote filename, eg. '/somewhere/file.txt'
    * @return mixed
    */
    public function delete($rFilename)
    {
        $this->init();
        $path = $this->url.$rFilename;
        curl_setopt( $this->curl, CURLOPT_URL, $this->url);
        curl_setopt( $this->curl, CURLOPT_QUOTE, array('DELE '.$path));
        curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1);
        return $this->exec($this->curl);
    }

    /**
     * Init connection
     */
    private function init()
    {
        if(!$this->server && !$this->username){
            throw new \Exception(self::ERR_CONNECT);
        }
        $this->connect($this->server, $this->username, $this->password, $this->opts);
    }

    /**
     * Execute curl resource
     * @param resource $curl
     * @return mixed
     */
    private function exec($curl)
    {
        $result = curl_exec($curl);
        $this->responseInfo = curl_getinfo($curl);

        if (!$result) {
            throw new \Exception(curl_error($curl));
        }
        curl_close($curl);
        return $result;
    }

    /**
	 * Close CURL connection
	 */
	public function __destruct()
    {
		@curl_close($this->curl);
	}

}
