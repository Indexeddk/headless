<?php
namespace Indexed\Headless;

class Request
{
    public const METHOD_POST = 'POST';
    public const METHOD_GET = 'GET';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';

    private $consumerKey;

    private $consumerSecret;

    private $publicToken;

    private $url = 'https://head01.webfamly.com/v1';

    private $useCache = false;

    private $cachePath = '';

    private $defaultCacheTime = 60;

    public function __construct($consumerKey, $consumerSecret, $publicToken = '')
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->publicToken = $publicToken;
    }

    public function setCachePath($path)
    {
        $this->cachePath = $path;
    }

    public function setCacheTime($cacheTime)
    {
        $this->defaultCacheTime = $cacheTime;
    }

    public function setUrl($url)
    {
        $url = trim($url, "/");
        $this->url = $url;

        return $this;
    }

    public function post($uri, $data)
    {
        $data = $this->request($uri, self::METHOD_POST, $data);

        return $data;
    }

    public function get($uri)
    {
        $data = $this->request($uri, self::METHOD_GET);

        return $data;
    }

    public function patch($uri, $data)
    {
        $data = $this->request($uri, 'PATCH', $data);

        return $data;
    }

    public function delete($uri)
    {
        $data = $this->request($uri, 'DELETE');

        return $data;
    }

    public function useCache($cache)
    {
        $this->useCache = $cache;
    }

    private function request($request, $method, $data = [])
    {
        $dataStr = json_encode($data);

        /*
         * @todo: don't cache sessions(cart)
         */
        if($this->useCache and $method == self::METHOD_GET) {

            $md5Key = md5($this->consumerKey.$request . serialize($data));

            if(empty($this->cachePath)) {
                $this->cachePath = $_SERVER['DOCUMENT_ROOT'] . '/cache';
            }

            if(!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0777);
            }

            $file = $this->cachePath . '/'.$md5Key;

            if (file_exists($file)) {

                $time = filectime($file);
                $diff = time() - $time;

                $cacheTime = $this->defaultCacheTime;

                if ($diff <= $cacheTime) {
                    $data = file_get_contents($file);
                    return json_decode($data);
                }
            }
        }

        try {
            $url = $this->url.$request;
            $method = trim(strtoupper($method));

            $request = strtolower($request);

            if(($request == '/products' or substr($request, 0, 10) == '/products?')  and $method == self::METHOD_GET) {
                $base = base64_encode("$this->publicToken:");
            } else {
                $base = base64_encode("$this->consumerKey:$this->consumerSecret");
            }

            $headers = array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $base,
            );

            $ch = curl_init($url);
            $method = trim(strtoupper($method));

            switch ($method) {
                default:
                case self::METHOD_GET:
                    break;
                case self::METHOD_DELETE:
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    break;
                CASE self::METHOD_POST:

                CASE self::METHOD_PUT:
                CASE self::METHOD_PATCH:

                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataStr);

                    $headers[] = 'Content-Length: ' . strlen($dataStr);
                    break;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if($httpcode == 401) {
                throw new \Exception('401 Not authourized');
            }

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            if($this->useCache and $method == self::METHOD_GET) {
                file_put_contents($file, $response);
            }

            $data = json_decode($response);

            if(!is_object($data)) {
                throw new \Exception($response);
            }

        }catch (\Exception $e) {
            die($e->getMessage());
        }

        return $data;
    }
}