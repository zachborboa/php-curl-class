<?php

namespace Helper;

use Curl\Curl;

class Test
{
    const TEST_URL = 'http://127.0.0.1:8000/';
    const ERROR_URL = 'http://1.2.3.4/';

    private $testUrl;

    public function __construct($port = null)
    {
        $this->testUrl = $port === null ? self::TEST_URL : $this->getTestUrl($port);
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, false);
    }

    public function server($test, $request_method, $arg1 = null, $arg2 = null)
    {
        $this->curl->setHeader('X-DEBUG-TEST', $test);
        $request_method = strtolower($request_method);
        if ($arg1 !== null && $arg2 !== null) {
            $this->curl->$request_method($this->testUrl, $arg1, $arg2);
        } elseif ($arg1 !== null) {
            $this->curl->$request_method($this->testUrl, $arg1);
        } else {
            $this->curl->$request_method($this->testUrl);
        }
        return $this->curl->response;
    }

    /*
     * When chaining requests, the method must be forced, otherwise a
     * previously forced method might be inherited.
     * Especially, POSTs must be configured to not perform post-redirect-get.
     */
    private function chainedRequest($request_method, $data)
    {
        if ($request_method === 'POST') {
            $this->server('request_method', $request_method, $data, true);
        } else {
            $this->server('request_method', $request_method, $data);
        }
        \PHPUnit\Framework\Assert::assertEquals($request_method, $this->curl->responseHeaders['X-REQUEST-METHOD']);
    }

    public function chainRequests($first, $second, $data = array())
    {
        $this->chainedRequest($first, $data);
        $this->chainedRequest($second, $data);
    }

    public static function getTestUrl($port)
    {
        if (getenv('PHP_CURL_CLASS_LOCAL_TEST') === 'yes' ||
            in_array(getenv('TRAVIS_PHP_VERSION'), array('7.0', '7.1', '7.2', '7.3', '7.4', '8.0', 'nightly'))) {
            return 'http://127.0.0.1:' . $port . '/';
        } else {
            return self::TEST_URL;
        }
    }
}

function create_png()
{
    // PNG image data, 1 x 1, 1-bit colormap, non-interlaced
    ob_start();
    imagepng(imagecreatefromstring(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')));
    $raw_image = ob_get_contents();
    ob_end_clean();
    return $raw_image;
}

function create_tmp_file($data)
{
    $tmp_file = tmpfile();
    fwrite($tmp_file, $data);
    rewind($tmp_file);
    return $tmp_file;
}

function get_tmp_file_path()
{
    // Return temporary file path without creating file.
    $tmp_file_path =
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR . 'php-curl-class.' . uniqid(rand(), true);
    return $tmp_file_path;
}

function get_png()
{
    $tmp_filename = tempnam('/tmp', 'php-curl-class.');
    file_put_contents($tmp_filename, create_png());
    return $tmp_filename;
}

if (function_exists('finfo_open')) {
    function mime_type($file_path)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        return $mime_type;
    }
} else {
    function mime_type($file_path)
    {
        $mime_type = mime_content_type($file_path);
        return $mime_type;
    }
}

function upload_file_to_server($upload_file_path) {
    $upload_test = new Test();
    $upload_test->server('upload_response', 'POST', array(
        'image' => '@' . $upload_file_path,
    ));
    $uploaded_file_path = $upload_test->curl->response->file_path;

    // Ensure files are not the same path.
    assert($upload_file_path !== $uploaded_file_path);

    // Ensure file uploaded successfully.
    assert(md5_file($upload_file_path) === $upload_test->curl->responseHeaders['ETag']);

    return $uploaded_file_path;
}

function remove_file_from_server($uploaded_file_path) {
    $download_test = new Test();

    // Ensure file successfully removed.
    assert('true' === $download_test->server('upload_cleanup', 'POST', array(
        'file_path' => $uploaded_file_path,
    )));
    assert(file_exists($uploaded_file_path) === false);
}

function get_curl_property_value($instance, $property_name)
{
    $reflector = new \ReflectionClass('\Curl\Curl');
    $property = $reflector->getProperty($property_name);
    $property->setAccessible(true);
    return $property->getValue($instance);
}

function get_multi_curl_property_value($instance, $property_name)
{
    $reflector = new \ReflectionClass('\Curl\MultiCurl');
    $property = $reflector->getProperty($property_name);
    $property->setAccessible(true);
    return $property->getValue($instance);
}

function get_request_stats($start, $stop, $request_stats)
{
    foreach ($request_stats as $key => &$value) {
        $value['relative_start'] = sprintf('%.6f', round($value['start'] - $start, 6));
        $value['relative_stop'] = sprintf('%.6f', round($value['stop'] - $start, 6));
        $value['duration'] = (string)round($value['stop'] - $value['start'], 6);

        unset($value['start']);
        unset($value['stop']);
    }

    return $request_stats;
}
