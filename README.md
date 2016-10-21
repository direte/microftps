# MicroFtps

Micro library to deal with FTPS connections over cUrl


## Why?

* Old 'non-upgradable' customer server
* Common `data_accept: SSL/TLS handshake failed` error with the default explicit `ftp_ssl_connect`
* Required a quick solution


## Solution

Raw FTPS (not SFTP) connection over cUrl


## Usage

```php
$someOpts = array(
  'passive' => true,
  'port' => 990,
  'timeout' => 10,
  'curlOptions' => array(
    'CURLOPT_SSL_VERIFYPEER' => true
  )
);

$mf = new \DiRete\MicroFtps();
$mf->connect('ftps.server.com', 'username', 'pass', $someOpts);
// Or $mf = new \DiRete\MicroFtps('ftps.server.com', 'username', 'pass', $someOpts);
$fileContent = $mf->read('/path/to/my/file.txt');
```


## API (quick overview)

* connect($server, $username, $password, $options)

* read($filepath)

* listDir($filepath)

* write($remoteFilename, $localFilename)

* delete($filepath)


## Contribution

PRs are welcome


## License

MIT
