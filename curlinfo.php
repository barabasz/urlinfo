#!/usr/bin/env php
<?php

define("DEFAULT_SCHEME", "https");
define("VERSION", "0.0.2");
define("TIMEOUT", 2000);
define("MIN_PHP_VER", 8.2);
define("USER_SYSTEM", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7");
define("USER_PLATFORM", "AppleWebKit/537.36 (KHTML, like Gecko)");
define("USER_BROWSER", "Chrome/115.0.0.0 Safari/537.36");
define("USER_AGENT", USER_SYSTEM . " " . USER_PLATFORM . " " . USER_BROWSER);
define("IPINFO_TOKEN", "");

readonly class Time
{
    public int $started;
    public int $finished;
    public int $elapsed;
    public string $elapsedFormatted;

    public function __construct()
    {
        $this->started = hrtime(true);
    }

    public function total() {
        $this->finished = hrtime(true);
        $this->elapsed = $this->finished - $this->started;
        if ($this->elapsed/1e+6 < 1000) {
            $this->elapsedFormatted = $this->elapsed/1e+6 . " ms";
        } else {
            $this->elapsedFormatted = $this->elapsed/1e+9 . " s";
        }
        echo "Total time:\t" . $this->elapsedFormatted . PHP_EOL;
    }
}

readonly class Errors
{
    private string $error_message;

    public function error($error_type)
    {
        match ($error_type) {
            'VERSION' => $this->error_message = "SiteSpeed version " . VERSION,
            'ONLY_ONE_ARGUMENT' => $this->error_message = "Too many arguments. Use -h for help.",
            'NO_ARGUMENT' => $this->error_message = "No URL specified. Use -h for help.",
            'INVALID_URL' => $this->error_message = "Incorrect URL.",
            'INVALID_SCHEME' => $this->error_message = "Only " . colorLog("http://", "i") . " and " . colorLog("https://", "i") . " schemes are allowed.",
            'CANNOT_PARSE_URL' => $this->error_message = "Cannot parse url.",
            'HELP' => $this->error_message = str_replace('            ', '', "
            Usage: urlinfo [options] URL
            
            Arguments:
                URL     url to request, could be with or without http(s):// prefix
            Options:
                -c or --curlinfo: print cURL info
                -h or --help:     print this info and exit
                -i of --info:     print response headers
                -v or --version:  print version and exit"),
            default => $this->error_message = $error_type,
        };
       throw new Exception($this->error_message);
    }
}

readonly class Params
{ 
    public string $url;
    public bool $info;
    public bool $curlinfo;

    public function __construct()
    {
        global $argv;
        global $error;
        $rest_index = null;
        $short_options = "c::h::i::v::";
        $long_options = ["curlinfo::", "help::", "info::", "version::"];
        $options = getopt($short_options, $long_options, $rest_index);
        $pos_args = array_slice($argv, $rest_index);

        if (count($options) > 0) {
            if(isset($options["v"]) || isset($options["version"])) $error->error('VERSION');
            if(isset($options["h"]) || isset($options["help"])) $error->error('HELP');
            $this->info = (isset($options["i"]) || isset($options["info"])) ? true : false;
            $this->curlinfo = (isset($options["c"]) || isset($options["curlinfo"])) ? true : false;
        } else {
            $this->info = false;
            $this->curlinfo = false;           
        }

        if (count($pos_args) > 1) {
            $error->error('ONLY_ONE_ARGUMENT');
        } elseif (count($pos_args) < 1) {
            $error->error('NO_ARGUMENT');
        } else {
            $this->url = $pos_args[0];
        }
    }
}

readonly class Url
{

    public string $final;

    public function __construct(string $url)
    {
        global $error;
        if (!parse_url($url, PHP_URL_SCHEME)) {
            $url = DEFAULT_SCHEME . '://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error->error('INVALID_URL');
        }
        $components = parse_url($url) ?: [];
        if (empty($components)) {
            $error->error('CANNOT_PARSE_URL');
        }
        $scheme = mb_strtolower($components['scheme']);
        if ($scheme != "http" && $scheme != "https") {
            $error->error('INVALID_SCHEME');
        }
        $this->final = 
            $scheme . "://" .
            ($components['host'] ?? '') .
            ($components['path'] ?? '') .
            (isset($components['query']) ? "?" . $components['query'] : "") .
            (isset($components['fragment']) ? "#" . $components['fragment'] : "");
    }

}

class CurlData
{
    public object $curlinfo;
    public object $headers;

    public function __construct(string $url)
    {
        global $error;
        $ch = curl_init();
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
            //"Accept-Encoding: gzip, deflate, br",
            //"Accept-Encoding: gzip",
            "Accept-Encoding: br;q=1.0, gzip;q=0.8, deflate;q=0.6, compress;q=0.4, *;q=0.1",
            "Accept-Language: en-GB,en-US;q=0.9,en;q=0.8,pl;q=0.7",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Referer: https://www.google.com/",
            "Sec-CH-UA-Platform: macOS",
            "Sec-Fetch-Dest: document",
            "Sec-Fetch-Mode: navigate",
            "Sec-Fetch-Site: cross-site",
            "Sec-Fetch-User: ?1"
        );
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => true,
            CURLOPT_CERTINFO => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => $headers
        );
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        if(!$response) $error->error(curl_error($ch));
        curl_close($ch);
        $this->curlinfo = (object)curl_getinfo($ch);

        if (empty($this->curlinfo->redirect_url)) {
            $this->get_headers_arr($response, $this->curlinfo->header_size);
            //$this->get_body($response, $this->curlinfo->header_size);
            $this->get_ipinfo($this->curlinfo->primary_ip);
        }
    }

    private function get_headers_arr(string $response, int $header_size) {
        $headers = substr($response, 0, $header_size);
        $headers_indexed_arr = array_filter(explode("\r\n", $headers));
        $headers_indexed_arr[0] = 'status: ' . $headers_indexed_arr[0];
        $this->curlinfo->headers = new stdClass();
        foreach ($headers_indexed_arr as $value) {
            if(false !== ($matches = explode(':', $value, 2))) {
              $this->curlinfo->headers->{strtolower(str_replace('-', '_', $matches[0]))} = trim($matches[1]);
            }
        }
    }

    private function get_body(string $response, int $header_size) {
        $body = substr($response, $header_size);
        $this->curlinfo->body = $body;
    }

    private function get_ipinfo(string $ip) {
        $ipinfo_url = "https://ipinfo.io/"  . $ip . "?token=" . IPINFO_TOKEN;
        $scc = stream_context_create(
            ['http'=>['timeout' => 1, 'ignore_errors' => true]]
        );
        $ipinfo_response = @file_get_contents($ipinfo_url, false, $scc);
        $ipinfo = json_decode($ipinfo_response);
        $this->curlinfo->ipinfo = $ipinfo;
    }

}

readonly class PrintData
{
    public bool $isRedirect;

    public function __construct(object $data)
    {
        echo $this->verboseInfo($data);
        echo $this->requestInfo($data);
        echo $this->connectionInfo($data);
        echo $this->responseInfo($data);
        if (isset($this->isRedirect)) {
            echo  PHP_EOL . colorLog("Redirecting...", "c") . PHP_EOL . PHP_EOL;
        } elseif ($data->http_code == 200) {
            echo $this->ipinfoInfo($data->ipinfo);
            if (strtolower($data->scheme) == "https") {
                echo $this->sslInfo($data);
            }
            echo $this->contentInfo($data);
            echo $this->speedInfo($data);
        }
    }

    private function verboseInfo($data): string {
        $data = clone $data;
        global $params;
        if ($params->curlinfo) {
            $info = "cURL info:\n\n";
            unset($data->certinfo, $data->headers, $data->ipinfo);
            $info .= parse_object($data) . PHP_EOL;
        }
        if ($params->info) {
            $info = "Response headers:\n\n";
            unset($data->headers->set_cookie, $data->headers->content_security_policy);
            $info .= parse_object($data->headers) . PHP_EOL;
        }
        return $info;
    }

    private function ipinfoInfo($data) {
        $info = "IP info:\t";
        if (isset($data->error)) {
            $info .= colorLog('API error ' . $data->status . ': ' . $data->error->title, 'e');
        } elseif ($data->bogon ?? false) {
            $info .= colorLog('private class bogon IP', 'w');
        } else {
            $info .= "company " . colorLog($data->org, 'w');
            $info .= " from " . colorLog($data->city, 'w');
            $info .= " (" . $data->region . ', ' . $data->country . ")";
        }

        if (isset($data->hostname)) {
            $info .= PHP_EOL . "IP hostname:\t" . colorLog($data->hostname, 'w');
        }

        return $info . PHP_EOL;
    }

    private function connectionInfo($data) {
        $info = "Connection:\t";
        $info .= colorLog($data->local_ip, "w") . ":" . colorLog($data->local_port, "w") . " → ";
        if ($data->scheme == "HTTP") {
            $info .= colorLog('HTTP', "be") . "/" . $data->http_version . " → ";
        } elseif ($data->scheme == "HTTPS") {
            $info .= colorLog('HTTPS', "bs") . "/" . $data->http_version . " → ";
        } else {
            $info .= " → ";  
        }
        $info .= colorLog($data->primary_ip, "w") . ":" . colorLog($data->primary_port, "w"). PHP_EOL;
        return $info;
    }    

    private function parseSslName(string $name, string $field) {
        $re = '/([^\s=]+)\s*=\s*([^,]*)/';                                                                      
        $str = $name;
        $arr = [];
        $str = preg_replace_callback ($re,   
                function ($matches) use (&$arr) {
                    $arr[$matches[1]] = $matches[2];
                    return null;      
                },                    
                $str
        );
        return $arr[$field];
    }
    
    private function sslInfo(object $data) {
        $subject = colorLog($this->parseSslName($data->certinfo[0]["Subject"], 'CN'), "w");
        $issuer = colorLog($this->parseSslName($data->certinfo[0]["Issuer"], 'CN'), "w");
        $until = strtotime($data->certinfo[0]["Expire date"]);
        $until = colorLog(date('Y-m-d H:i e', $until), 'w');
        $from = strtotime($data->certinfo[0]["Start date"]);
        $from = colorLog(date('Y-m-d H:i e', $from), 'w');
        $info = "SSL subject:\t" . "for " . $subject . " by " . $issuer . PHP_EOL;
        $info .= "SSL validity:\t" . "from " . $from . " until " . $until . PHP_EOL;
        return $info;
    }

    private function requestInfo($data) {
        $requestedUrl = (strlen($data->url) > 80) ? substr($data->url, 0, 80) . "[...]" : $data->url;
        return "Request:\t" . $data->effective_method . ": " . colorLog($requestedUrl, "l") . PHP_EOL;
    }

    private function responseInfo($data) {
        $response = "Response:\t";
        if (isset($data->redirect_url)) {
            $redirectUrl = (strlen($data->redirect_url) > 80) ? substr($data->redirect_url, 0, 85) . "[...]" : $data->redirect_url;
        }
        switch ($data->http_code) {
            case 200:
                $response .= colorLog($data->http_code, "bs") . " OK";
                break;
            case 301:
                $this->isRedirect = true;
                $response .= colorLog($data->http_code, "bw") . " Moved Permanently" . PHP_EOL;
                $response .= "Redirection:\t" . colorLog($redirectUrl, "l");
                break;
            case 302:
                $this->isRedirect = true;
                $response .= colorLog($data->http_code, "bw") . " Moved Temporarily" . PHP_EOL;
                $response .= "Redirection:\t" . colorLog($redirectUrl, "l");
                break;
            case 304:
                $response .= colorLog($data->http_code, "bs") . " Not Modified";
                break;
            case 307:
                $this->isRedirect = true;
                $response .= colorLog($data->http_code, "bw") . " Permanent Redirect" . PHP_EOL;
                $response .= "Redirection:\t" . colorLog($redirectUrl, "l");
                break;
            case 308:
                $this->isRedirect = true;
                $response .= colorLog($data->http_code, "bw") . " Moved Temporarily" . PHP_EOL;
                $response .= "Redirection:\t" . colorLog($redirectUrl, "l");
                break;
            case 400:
                $response .= colorLog($data->http_code, "be") . " Bad Request";
                break;
            case 401:
                $response .= colorLog($data->http_code, "be") . " Unauthorized";
                break;
            case 403:
                $response .= colorLog($data->http_code, "be") . " Forbidden";
                break;
            case 404:
                $response .= colorLog($data->http_code, "be") . " Not Found";
                break;
            case 500:
                $response .= colorLog($data->http_code, "be") . " Server Error";
                break;            
            case 502:
                $response .= colorLog($data->http_code, "be") . " Bad Gateway";
                break;            
            case 504:
                $response .= colorLog($data->http_code, "be") . " Gateway Timeout";
                break;            
            default:
                $response .= colorLog($data->http_code, "be");
                break;
        }
        $status = (isset($data->headers->status)) ? ' (' . $data->headers->status . ')' : '';
        return $response . $status . PHP_EOL;
    }

    private function human_readable_bytes(int $bytes, int $decimals = 2, $system = 'binary')
    {
        $mod = ($system === 'binary') ? 1024 : 1000;
        $units = array(
            'binary' => array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB','YiB',),
            'metric' => array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB',),
        );
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f %s", $bytes / pow($mod, $factor), $units[$system][$factor]);
    }

    private function contentInfo($data) {
        $type = colorLog($data->content_type, 'w');
        $size = colorLog($this->human_readable_bytes($data->size_download, 2), 'w');
        $speed = colorLog($this->human_readable_bytes($data->speed_download, 2) . '/s', 'w');


        $info = "Content type:\t" . $type;
        if (isset($data->content_encoding)) {

            switch ($data->content_encoding) {
                case 'gzip':
                    $encoding = 'gzip (LZ77 with CRC)';
                    break;
                case 'compress':
                    $encoding = 'compress (LZW)';
                    break;
                case 'deflate':
                    $encoding = 'deflate (zlib with deflate)';
                    break;
                case 'br':
                    $encoding = 'br (Brotli)';
                    break;
                default:
                    $encoding = $data->content_encoding;
                    break;
            }

            $info .= " encoded with " . colorLog($encoding, 'w');
        } else {
            $info .= " as " . colorLog('plain text', 'w');
        }
        $info .= PHP_EOL . "Content size:\t" . $size;
        $info .= " downloaded at " . $speed . PHP_EOL;
        return $info;
    }

    private function speedInfo($data) {
        return "Speed info..." . PHP_EOL;
    }

}

function parse_object(object $obj): string {
    $output = '';
    foreach ($obj as $key => $val) {
        $key = ucwords(str_replace('_', ' ', $key));
        $output .= $key . ": " . colorLog($val, 'w') . PHP_EOL;
    }
    return $output;
}

function print_object(object $obj): void {
    foreach ($obj as $key => $val) {
        $key = ucwords(str_replace('_', ' ', $key));
        echo $key . ": " . colorLog($val, 'w') . PHP_EOL;
    }
}

function colorLog($str, $type = 'i'){
    switch ($type) {
        case 'b': //bold
            return "\033[1m$str\033[0m";
        break;
        case 'e': //error
            return "\033[31m$str\033[0m";
        break;
        case 'be': //bold error
            return "\033[1m\033[31m$str\033[0m";
        break;
        case 's': //success
            return "\033[32m$str\033[0m";
        break;
        case 'bs': //bold success
            return "\033[1m\033[32m$str\033[0m";
        break;
        case 'w': //warning
            return "\033[33m$str\033[0m";
        break;
        case 'bw': //bold warning
            return "\033[1m\033[33m$str\033[0m";
        break;  
        case 'i': //info
            return "\033[36m$str\033[0m";
        break;
        case 'l': //link
            return "\033[4m\033[36m$str\033[0m";
        break;
        case 'c': //comment
            return "\033[38;5;238m$str\033[0m";
        break;   
        default:
            return $str;
        break;
    }
}

/*
MAIN PROGRAM
*/


try {
    $time = new Time();
    $error = new Errors();
    $params = new Params();
    do {
        $url = new Url($data->curlinfo->redirect_url ?? $params->url);
        $data = new CurlData($url->final);
        $print = new PrintData($data->curlinfo);
    } while (isset($print->isRedirect));
    $time->total();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
