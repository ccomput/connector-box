<?php

/** Created by Outobx360 ...*/

namespace App\Services;

use DateInterval;
use Exception;
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
        $dataHoraAtual = date('Y-m-d\TH:i');
        $dataHoraIni = date('Y-m-d\TH:i', strtotime('-5 minutes', strtotime(date('Y-m-d H:i:s'))));
        $where = 'createdIn between ' . $dataHoraIni . ' AND ' . $dataHoraAtual;
        // TODO $where = 'createdIn=2022-02-21T17:56';
        // TODO $where = 'createdIn between 2022-02-16T14:00 AND 2022-02-16T14:05';
        // TODO $where = 'fabiomariuzzo@icloud.com';
        $fields = '_all';
        $segmentId = 36;
        $segmentAbandonedCartId = 14;

        // get reponse for all master data clients (CL) vtex
        $responseVtexMasterData = $this->getResponseVtexMasterData($dataEntitie, $where, $fields);

        if (count(get_object_vars($responseVtexMasterData)) > 0) {
            // get list skus cart
            $skus = substr($responseVtexMasterData->{'0'}->rclastcart, 4);
            $skusForMautic = $this->getSkusName($skus);

            if ($responseVtexMasterData->{'0'}->checkouttag->DisplayValue == 'Finalizado') {
                // get shipping estimated date
                $shippingEstimatedDate = $this->getShippingEstimatedDateVtex($responseVtexMasterData->{'0'}->email);

                // data for api mautic
                $data = array(
                'firstname' => $responseVtexMasterData->{'0'}->firstName,
                'lastname'  => $responseVtexMasterData->{'0'}->lastName,
                'email'     => $responseVtexMasterData->{'0'}->email,
                'shippingestimateddate' => $shippingEstimatedDate,
                'skuslastorder' => $skusForMautic,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'overwriteWithBlank' => true,
                );
            } else {
                // data for api mautic abandoned cart
                $data = array(
                    'firstname' => $responseVtexMasterData->{'0'}->firstName,
                    'lastname'  => $responseVtexMasterData->{'0'}->lastName,
                    'email'     => $responseVtexMasterData->{'0'}->email,
                    'skuabandonedcart' => substr($responseVtexMasterData->{'0'}->rclastcart, 4),
                    'skunameabandonedcart' => $skusForMautic,
                    'lastdatecart' => $responseVtexMasterData->{'0'}->rclastsessiondate,
                    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'overwriteWithBlank' => true,
                );
            }

            // mautic token
            $auth = $this->getTokenMautic();

            // insert mautic contact
            $contactId = $this->createContactMautic($auth, $data);

            // check abandoned cart
            if ($responseVtexMasterData->{'0'}->checkouttag->DisplayValue == 'Finalizado') {
                // add contact to a segment clients in mautic
                $integrationSegment = $this->addContactToASegmentMautic($segmentId, $contactId, $auth);
            } else {
                // add contact to a segment abandoned cart in mautic
                $integrationSegment = $this->addContactToASegmentMautic($segmentAbandonedCartId, $contactId, $auth);
            }

            // log
            if ($integrationSegment) {
                Log::info('Integração realizado com sucesso na data: ' . date('m-d-Y h:i:s a', time()) . ' para o ID: '
                . $responseVtexMasterData->{'0'}->userId);
            } else {
                Log::error('Falha de integração na data: ' . date('m-d-Y h:i:s a', time()) . ' para o ID: '
                . $responseVtexMasterData->{'0'}->userId);
            }
        } else {
            //Log::info('Não há dados no master data vetex ' . date('m-d-Y h:i:s a', time()));
            return null;
        }
    }

    private function getResponseVtex(string $apiType, string $uri = null)
    {
        $full_path = $this->url . $apiType;
        $full_path .= $uri;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 240,
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
        //test TODO
        //$full_path = $this->url .  'dataentities/' . $dataEntite . '/search?email=' . $where . '&_fields=' . $fields;
        $full_path = $this->url .  'dataentities/' . $dataEntite . '/search?_where=' . $where . '&_fields=' . $fields;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 300,
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

    private function getShippingEstimatedDateVtex(string $email)
    {
        $full_path = $this->url . 'oms/pvt/orders?q=' . $email;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 300,
            'connect_timeout' => true,
            'http_errors'     => true,
        ]);
        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;
        if ($response && $status === 200 && $response !== 'null') {
            $order = (object) json_decode($response);
            /*$shippingEstimatedDate = substr($order->list['0']->ShippingEstimatedDateMax, 0, 10);
            $shippingEstimatedDateConverted = implode("/", array_reverse(explode("-", $shippingEstimatedDate)));*/

            return $order->list['0']->ShippingEstimatedDateMax;
        }

        return null;
    }

    private function getSkusName(string $skus)
    {
        $skusExplode = explode("&", $skus);
                $skusSepareted = "";
                $skusForMautic = "";

        for ($i = 0; $i < count($skusExplode) - 1; $i++) {
            if ($this->like('sku%', $skusExplode[$i])) {
                $skusSepareted .= $skusExplode[$i];
            }
        }

                $skusApi = explode("sku=", $skusSepareted);

        if ($skusApi) {
            for ($p = 1; $p < count($skusApi); $p++) {
                $product = $this->getProductBySku($skusApi[$p]);
                print $product->{'0'}->productName;
                if ($p == 1) {
                    $skusForMautic = $product->{'0'}->productName;
                } elseif ($p > 1) {
                    $skusForMautic .= ' e ' . $product->{'0'}->productName;
                }
            }
        }

        return $skusForMautic;
    }

    private function getProductBySku(string $sku)
    {
        $full_path = $this->url . 'catalog_system/pub/products/search?fq=skuId:' . $sku;
        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 300,
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
        $settings = [
            'userName'   => 'r3d',
            'password'   => '@r3d2021',
        ];

        // TODO settings teste
        /*$settings = [
            'userName'   => 'ricardo',
            'password'   => 'b2-4acbox#',
        ];*/

        $initAuth = new ApiAuth();
        $auth     = $initAuth->newAuth($settings, 'BasicAuth');

        //work only https
        /*$settings = array(
            'baseUrl'        => 'https://shure.lumixpro.com.br',
            'version'        => 'OAuth2',
            'clientKey'      => '1_30ng5yji050kcg4o84c0g4gwk08k8wgsg80sgwggwk0ckkoo0k',
            'clientSecret'   => '5tg49wsn7nokogssc4kk8sg4gw4o0kgscg8o0swg4goc404o4k',
            'callback'       => ''
        );

        try {
            // Initiate the auth object
            $initAuth = new ApiAuth();
            $auth     = $initAuth->newAuth($settings);
        } catch (Exception $e) {
            Log::error('Falha ao realizar autenticação: ' . date('m-d-Y h:i:s a', time()) . ' erro: ' . $e->getMessage());
        }

        try {
            if ($auth->validateAccessToken()) {
                if ($auth->accessTokenUpdated()) {
                    $accessTokenData = $auth->getAccessTokenData();
                    //store access token data however you want
                }
            }
        } catch (Exception $e) {
            Log::error('Falha ao solicitar o token mautic: ' . date('m-d-Y h:i:s a', time()) . ' erro: ' . $e->getMessage());
        }*/

        return $auth;
    }

    private function createContactMautic($auth = null, array $data = [])
    {
        try {
            // TODO url teste
            //$apiUrl     = "http://mautic.outbox360.com.br";
            $apiUrl     = "https://shure.lumixpro.com.br";
            $api        = new MauticApi();
            $contactApi = $api->newApi("contacts", $auth, $apiUrl);
            $contact = $contactApi->create($data);
        } catch (Exception $e) {
            Log::error('Falha ao adicionar o contato: ' . $e->getMessage());
        }

        if ($contact) {
            return $contact['contact']['id'];
        } else {
            return 0;
        }
    }

    private function addContactToASegmentMautic($segmentId, $contactId, $auth = null)
    {
        // TODO url teste
        //$apiUrl     = "http://mautic.outbox360.com.br";
        $apiUrl     = "https://shure.lumixpro.com.br";
        $api        = new MauticApi();
        $segmentApi = $api->newApi("segments", $auth, $apiUrl);
        $response   = $segmentApi->addContact($segmentId, $contactId);

        if (!isset($response['success'])) {
            Log::error('Falha ao adicionar o contato: ' . $contactId . ' ao segmento: ' . $segmentId);
            return false;
        } else {
            Log::info('Contato: ' . $contactId . ' adicionado ao segmento: ' . $segmentId . ' com sucesso!');
            return true;
        }
    }

    private function like($needle, $haystack)
    {
        $regex = '/' . str_replace('%', '.*?', $needle) . '/';

        return preg_match($regex, $haystack) > 0;
    }
}
