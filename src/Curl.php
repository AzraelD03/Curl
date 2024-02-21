<?php

namespace Curl;

use Campo\UserAgent;

class Curl
{
    private $ch;

    private function init(
        $url,
        $header,
        $server,
        $cookie,
        $setOpt
    ) {
        $this->ch = curl_init($url);

        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => UserAgent::random(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYSTATUS => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        if (!empty($header)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($server)) {
            if (empty($server['method'])) {
                return (object) [
                    'success' => false,
                    'error'   => '$server["method"] does not exist.'
                ];
            }

            switch ($server['method']) {
                case 'ip':
                    if (empty($server['server'])) {
                        return (object) [
                            'success' => false,
                            'error'   => '$server["server"] does not exist.'
                        ];
                    }

                    curl_setopt_array($this->ch, [
                        CURLOPT_HTTPPROXYTUNNEL => true,
                        CURLOPT_PROXY => $server['server']
                    ]);

                    break;

                case 'auth':
                    if (empty($server['server']) || empty($server['userPass'])) {
                        $missingField = empty($server['server'])
                            ? '$server["server"]' : '$server["userPass"]';

                        return (object) [
                            'success' => false,
                            'error'   => $missingField . ' does not exist.'
                        ];
                    }

                    curl_setopt_array($this->ch, [
                        CURLOPT_PROXY => $server['server'],
                        CURLOPT_PROXYUSERPWD => $server['userPass']
                    ]);

                    break;

                default:
                    return (object) [
                        'success' => false,
                        'error' => 'Invalid method.'
                    ];
            }
        }

        if (!empty($cookie)) {
            if (!is_dir('cookies/')) {
                mkdir('cookies');
            }

            $cookie_f = 'cookies/ck_' . $cookie . '.txt';

            curl_setopt_array($this->ch, [
                CURLOPT_COOKIEJAR => $cookie_f,
                CURLOPT_COOKIEFILE => $cookie_f
            ]);
        }

        if (!empty($setOpt)) {
            curl_setopt_array($this->ch, $setOpt);
        }

        return (object)[
            'success' => true
        ];
    }

    private function parseHttpHeaders($header)
    {
        preg_match_all('#^(.*?):\s*(.*?)$#m', $header, $matches);

        $index = array_map(fn ($v) => strtolower(trim($v)), $matches[1]);
        $values = array_map('trim', $matches[2]);

        return array_combine($index, $values);
    }

    private function exec()
    {
        $res = curl_exec($this->ch);

        $resInfo = curl_getinfo($this->ch);

        $errorCode = curl_errno($this->ch);
        $errorMsg  = curl_error($this->ch);

        curl_close($this->ch);

        if ($res !== false) {
            $code = $resInfo['http_code'];
            $time = $resInfo['total_time'];

            $header_res = $this->parseHttpHeaders(substr($res, 0, $resInfo['header_size']));
            $header_req = $this->parseHttpHeaders($resInfo['request_header']);

            $body = substr($res, $resInfo['header_size']);

            return (object)[
                'success' => true,
                'code' => $code,
                'time' => $time,
                'headers' => (object)[
                    'req' => $header_req,
                    'res' => $header_res
                ],
                'body' => $body
            ];
        }

        return (object)[
            'success' => false,
            'error' => (object)[
                'code' => $errorCode,
                'msg' => $errorMsg
            ]
        ];
    }

    public function Get(
        string $url,
        array $header = null,
        array $server = null,
        string $cookie = null,
        array $setOpt = null
    ) {
        $init = $this->init($url, $header, $server, $cookie, $setOpt);

        if ($init->success) {
            return $this->exec();
        }

        return $init;
    }

    public function Post(
        string $url,
        array $header = null,
        array | string $data = null,
        array $server = null,
        string $cookie = null,
        array $setOpt = null
    ) {
        $init = $this->init($url, $header, $server, $cookie, $setOpt);

        if ($init->success) {
            if (!empty($data)) {
                $data = is_array($data)
                    ? http_build_query($data) : $data;

                curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
            }

            return $this->exec();
        }

        return $init;
    }
}
