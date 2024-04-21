<?php

class HR
{
    public $b;
    public $s;

    public function __construct($b, $s) { $this->b = $b; $this->s = $s; }
    public function getStatusCode() { return $this->s; }
    public function getBody() { return $this->b; }
}

class HttpClient
{
    private $dO = [ 'headers' => [], ];

    public function setDefaultOption($k, $v) { $this->dO[$k] = $v; }
    public function post($url, $body = null, $o = [])
    {
        $o = array_merge($this->dO, $o);
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $body);
        curl_setopt($c, CURLOPT_HTTPHEADER, $this->b($o['headers']));
        $r = curl_exec($c);
        $s = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);

        return new HR($r, $s);
    }

    private function b($h) { $hA = []; foreach ($h as $k => $v) { $hA[] = "$k: $v"; } return $hA; }
}

?>
