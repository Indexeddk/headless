<?php
namespace Indexed\Headless;

class Request
{
    public const METHOD_POST = 'POST';
    public const METHOD_GET = 'GET';

    private $consumerKey;

    private $consumerSecret;

    private $publicToken;

    private $url = 'https://head01.webfamly.com/v1';

    public function __construct($consumerKey, $consumerSecret, $publicToken = '')
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->publicToken = $publicToken;
    }

    public function setUrl($url)
    {
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

    private function request($request, $method, $data = [])
    {
        $dataStr = json_encode($data);

        $useCache = false;

        if($useCache and $method == self::METHOD_GET) {

            $md5Key = md5($request . serialize($data));
            $file = $_SERVER['DOCUMENT_ROOT'] . '/shop/cache/' . $md5Key;

            if (file_exists($file)) {

                $time = filectime($file);
                $diff = time() - $time;

                $cacheTime = 60;

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

            if(($request == '/products' or substr($request, 0, 10) == '/products?')  and $method == 'GET') {
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
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    break;
                CASE self::METHOD_POST:

                CASE 'PUT':
                CASE 'PATCH':

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

            if($useCache and $method == self::METHOD_GET) {
                file_put_contents($file, $response);
            }

            $data = json_decode($response);

            if(!is_object($data)) {
                throw new \Exception($response);
            }

            if(!empty($data->error)) {
                throw new \Exception($data->error);
            }

        }catch (\Exception $e) {
            die($e->getMessage());
        }

        return $data;
    }
}