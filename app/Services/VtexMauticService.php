<?php

/** Created by Outobx360 ...*/

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;

class VtexMauticService
{
    protected $url;
    protected $http;
    protected $headers;
    public function __construct()
    {
        $this->url = 'https://lojashure.myvtex.com/api/';
        $this->http = new Client();
        $this->headers = [
            'Accept' => 'application/vnd.vtex.ds.v10+json',
            'Content-type' => 'application/json',
            'REST-Range' => 'resources=0-10',
            'X-VTEX-API-AppKey' => 'vtexappkey-lojashure-OHFXFM',
            'X-VTEX-API-AppToken' => 'CUAQGQTITSOOZEPKQXTNOQURJBJVFHSZJGRSLEQRGWUEUDOLUUXGTVJMAJHEHXCMZOFTNXYMGBGSVPWFNXALJMFOWFSCXMKITIEGYVYXWLZSORQKMBRJXRCNIQWHFVOU',
        ];
    }

    public function integration()
    {
        $uri = null;
        $apiType = 'rns/pub/subscriptions/id';
        //$reponseVtex = $this->getResponseVtex($apiType, $uri);
        $dataEntitie = 'CL';
        //$dataAtual = date('Y-m-d');
        //$horaAtual = date('H:i');
        $dataHoraAtual = date('Y-m-d\TH:i');
        $where = 'createdIn=' . $dataHoraAtual;
        $fields = '_all';

        $responseVtexMasterData = $this->getResponseVtexMasterData($dataEntitie, $where, $fields);

        // data for api mautic
        $data = array(
            'firstname' => $responseVtexMasterData->{'firstName'},
            'lastname'  => $responseVtexMasterData->{'lastName'},
            'email'     => $responseVtexMasterData->{'email'},
            'ipAddress' => $_SERVER['REMOTE_ADDR'],
            'overwriteWithBlank' => true,
        );

        // mautic token
        $auth = $this->getTokenMautic();

        $dateIntegration = $this->postResponseMautic($auth, $data);

        // log
        Log::channel('vtexmautic')->info('Integração realizado com sucesso na data: ' . $dateIntegration . 'para o ID: '
        . $responseVtexMasterData->{'userId'});
    }

    private function getResponseVtex(string $apiType, string $uri = null)
    {
        $full_path = $this->url . $apiType;
        $full_path .= $uri;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_errors'     => true,
        ]);
        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;
        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }

    private function getResponseVtexMasterData(string $dataEntite, string $where, string $fields)
    {
        $full_path = $this->url .  'dataentities/' . $dataEntite . '/search?_where=' . $where . '&_fields=' . $fields;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_errors'     => true,
        ]);
        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;
        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }

    private function postResponseVtex(
        string $url = null,
        string $headers = null,
        string $uri = null,
        array $post_params = []
    ) {
        $full_path = $url;
        $full_path .= $uri;
        $request = $this->http->post($full_path, [
            'headers'         => $headers,
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_erros'      => true,
            'form_params'     => $post_params,
        ]);
        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;
        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }

    private function getTokenMautic()
    {
        $settings = array(
            'baseUrl'        => 'http://mautic.outbox360.com.br/oauth/v2/authorize',
            'version'        => 'OAuth2',
            'clientKey'      => '1_3n46yeh81uyokcw4w8cggc4sg08o0w8kk00k0sgc88w0kgs8g8',
            'clientSecret'   => '4anvh3wqyhyccw4wg8sswgcswcwwgcwkwwkwcow0coo08s04o0',
            'callback'       => 'http://mautic.outbox360.com.br/'
        );

        // Initiate the auth object
        $initAuth = new ApiAuth();
        $auth     = $initAuth->newAuth($settings);

        if ($auth->validateAccessToken()) {
            if ($auth->accessTokenUpdated()) {
                $accessTokenData = $auth->getAccessTokenData();
                //store access token data however you want
            }
        }

        return $auth;
    }

    private function postResponseMautic($auth = null, array $data = [])
    {
        $apiUrl     = "http://mautic.outbox360.com.br";
        $api        = new MauticApi();
        $contactApi = $api->newApi("contacts", $auth, $apiUrl);
        $contact = $contactApi->create($data);

        return date('m-d-Y h:i:s a', time());
    }
}
