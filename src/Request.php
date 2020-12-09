<?php
namespace Indexed\Headless;

class Request
{
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';

    private $consumerKey;

    private $consumerSecret;

    private $publicToken;

    private $url = 'https://api.indexedshop.com/v1';

    private $useCache = false;

    private $cachePath = '';

    private $defaultCacheTime = 60;

    private $exceptionOnError = false;

    public function __construct($consumerKey, $consumerSecret, $publicToken = '')
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->publicToken = $publicToken;
    }

    public function exceptionOnError($exception = false)
    {
        $this->exceptionOnError = $exception;
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

    public function put($uri, $data)
    {
        $data = $this->request($uri, 'PUT', $data);

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

    /**
     * Get the base root request
     *
     * @param $root
     * @return mixed|string
     */
    private function getRoot($root)
    {
        if(strstr($root, '?') !== false) {
            $root = substr($root, 0, strpos($root, '?'));
        }

        $root = trim($root, '/');
        $roots = explode('/', $root);
        $root = $roots[0];

        return $root;
    }

    /**
     * Cleanup cache
     */
    private function cleanupCache()
    {
        if($this->useCache and !empty($this->cachePath) and is_dir($this->cachePath) and is_writeable($this->cachePath)) {
            $files = scandir($this->cachePath);

            foreach($files as $file) {

                if(in_array($file, ['.', '..', '.gitkeep', '.gitignore', '.htaccess'])) continue;

                $filePath = $this->cachePath . '/'.$file;

                if(filectime($filePath) < strtotime('-24 hours')) {
                    unlink($filePath);
                }
            }
        }
    }

    private function request($request, $method, $data = [])
    {
        $dataStr = json_encode($data);

        $root = $this->getRoot($request);

        if($this->useCache and $method == self::METHOD_GET and !in_array($root, ['sessions'])) {

            $md5Key = md5($this->consumerKey.$request . serialize($data));

            if(empty($this->cachePath)) {
                $this->cachePath = $_SERVER['DOCUMENT_ROOT'] . '/cache';
            }

            if(!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0777);

                if(!file_exists($this->cachePath.'/.htaccess')) {
                    file_put_contents($this->cachePath.'/.htaccess', 'deny from all');
                }
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

            if($this->useCache and $method == self::METHOD_GET and !in_array($root, ['sessions']) and isset($file)) {
                file_put_contents($file, $response);
            }

            $data = json_decode($response);

            if(!is_object($data)) {
                throw new \Exception($response);
            }

            /*
             * Periodic cleanup of cache files
             */
            if($this->useCache) {
                if(rand(1, 1000) == 1) {
                    $this->cleanupCache();
                }
            }

        }catch (\Exception $e) {
            die('Unhandled headless error: '.$e->getMessage());
        }

        /*
         * Throw an exception on error from headless
         */
        if($this->exceptionOnError and !empty($response->error)) {
            throw new \Exception($response->error);
        }

        return $data;
    }
}