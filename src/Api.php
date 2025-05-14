<?php
/**
 * Cliente para comunicação com a API Evolution
 */
class Api {
    private $baseUrl;
    private $apiKey;
    private $logger;
    
    /**
     * Construtor da classe API
     *
     * @param string $baseUrl URL base da API
     * @param string $apiKey Chave da API
     * @param Logger $logger Instância do logger
     */
    public function __construct($baseUrl = null, $apiKey = null, $logger = null) {
        $this->baseUrl = $baseUrl ?? getEnv('BASE_URL');
        $this->apiKey = $apiKey ?? getEnv('API_KEY');
        $this->logger = $logger ?? new Logger('api.log');
        
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            $this->logger->error("Configuração de API incompleta: baseUrl ou apiKey ausentes");
            throw new Exception("Configuração de API incompleta");
        }
    }
    
    /**
     * Retorna a URL base configurada
     * 
     * @return string URL base
     */
    public function getBaseUrl() {
        return $this->baseUrl;
    }
    
    /**
     * Retorna a chave da API configurada
     * 
     * @return string Chave API
     */
    public function getApiKey() {
        return $this->apiKey;
    }
    
    /**
     * Realiza uma requisição GET à API
     *
     * @param string $endpoint Endpoint da API
     * @param array $params Parâmetros da requisição
     * @param int $timeout Tempo limite em segundos
     * @return array Resposta da API
     */
    public function get($endpoint, $params = [], $timeout = 30) {
        $url = $this->buildUrl($endpoint, $params);
        return $this->request('GET', $url, null, $timeout);
    }
    
    /**
     * Realiza uma requisição POST à API
     *
     * @param string $endpoint Endpoint da API
     * @param array $data Dados a serem enviados
     * @param int $timeout Tempo limite em segundos
     * @return array Resposta da API
     */
    public function post($endpoint, $data = [], $timeout = 30) {
        $url = $this->buildUrl($endpoint);
        return $this->request('POST', $url, $data, $timeout);
    }
    
    /**
     * Realiza uma requisição PUT à API
     *
     * @param string $endpoint Endpoint da API
     * @param array $data Dados a serem enviados
     * @param int $timeout Tempo limite em segundos
     * @return array Resposta da API
     */
    public function put($endpoint, $data = [], $timeout = 30) {
        $url = $this->buildUrl($endpoint);
        return $this->request('PUT', $url, $data, $timeout);
    }
    
    /**
     * Realiza uma requisição DELETE à API
     *
     * @param string $endpoint Endpoint da API
     * @param array $params Parâmetros da requisição
     * @param int $timeout Tempo limite em segundos
     * @return array Resposta da API
     */
    public function delete($endpoint, $params = [], $timeout = 30) {
        $url = $this->buildUrl($endpoint, $params);
        return $this->request('DELETE', $url, null, $timeout);
    }
    
    /**
     * Constrói a URL completa para a requisição
     *
     * @param string $endpoint Endpoint da API
     * @param array $params Parâmetros da URL
     * @return string URL completa
     */
    private function buildUrl($endpoint, $params = []) {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Realiza uma requisição à API
     *
     * @param string $method Método HTTP
     * @param string $url URL da requisição
     * @param array $data Dados a serem enviados
     * @param int $timeout Tempo limite em segundos
     * @return array Resposta da API
     * @throws Exception Em caso de erro na requisição
     */
    private function request($method, $url, $data = null, $timeout = 30) {
        $startTime = microtime(true);
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $this->apiKey,
            ],
        ];
        
        if ($data !== null) {
            $jsonData = json_encode($data);
            $options[CURLOPT_POSTFIELDS] = $jsonData;
            
            // Para depuração
            $this->logger->info("Dados enviados para {$url}: {$jsonData}");
        }
        
        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $duration = round(microtime(true) - $startTime, 3);
        
        curl_close($curl);
        
        // Log da requisição
        $this->logger->info("API {$method} {$url} - Status: {$statusCode} - Tempo: {$duration}s");
        
        if ($err) {
            $this->logger->error("Erro na API: {$err}");
            throw new Exception("Erro na comunicação com a API: " . $err);
        }
        
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Erro ao decodificar resposta JSON: " . json_last_error_msg());
            $this->logger->error("Resposta: " . substr($response, 0, 1000));
            throw new Exception("Erro ao processar resposta da API");
        }
        
        if ($statusCode >= 400) {
            $errorMessage = $responseData['message'] ?? 'Erro desconhecido';
            $this->logger->error("Erro na API (HTTP {$statusCode}): {$errorMessage}");
            throw new Exception("Erro na API: " . $errorMessage);
        }
        
        return $responseData;
    }
    
    /**
     * Cria uma nova instância WhatsApp
     *
     * @param string $instanceName Nome da instância
     * @param bool $qrcode Gerar QR code imediatamente
     * @param string $integration Tipo de integração
     * @param array $additionalConfig Configurações adicionais (rejectCall, msgCall, etc)
     * @return array Resposta da API
     */
    public function createInstance($instanceName, $qrcode = true, $integration = 'WHATSAPP-BAILEYS', $additionalConfig = []) {
        $data = [
            'instanceName' => $instanceName,
            'qrcode' => $qrcode,
            'integration' => $integration,
            'code' => true // Adiciona o campo code para gerar o QR Code no novo formato
        ];
        
        // Adiciona as configurações adicionais
        if (!empty($additionalConfig)) {
            $data = array_merge($data, $additionalConfig);
        }
        
        return $this->post('instance/create', $data);
    }
    
    /**
     * Conecta a uma instância existente
     *
     * @param string $instanceName Nome da instância
     * @return array Resposta da API
     */
    public function connectInstance($instanceName) {
        return $this->get("instance/connect/{$instanceName}");
    }
    
    /**
     * Obtém o estado de conexão de uma instância
     *
     * @param string $instanceName Nome da instância
     * @return array Resposta da API
     */
    public function getConnectionState($instanceName) {
        return $this->get("instance/connectionState/{$instanceName}");
    }
    
    /**
     * Desconecta (logout) de uma instância
     *
     * @param string $instanceName Nome da instância
     * @return array Resposta da API
     */
    public function logoutInstance($instanceName) {
        return $this->delete("instance/logout/{$instanceName}");
    }
    
    /**
     * Remove uma instância
     *
     * @param string $instanceName Nome da instância
     * @return array Resposta da API
     */
    public function deleteInstance($instanceName) {
        return $this->delete("instance/delete/{$instanceName}");
    }
    
    /**
     * Envia uma mensagem de texto
     *
     * @param string $instanceName Nome da instância
     * @param string $number Número de destino
     * @param string $text Texto da mensagem
     * @return array Resposta da API
     */
    public function sendText($instanceName, $number, $text) {
        $data = [
            'number' => $number,
            'text' => $text
        ];
        
        return $this->post("message/sendText/{$instanceName}", $data);
    }
    
    /**
     * Define as configurações da instância
     *
     * @param string $instanceName Nome da instância
     * @param array $settings Array com as configurações (rejectCall, msgCall, groupsIgnore, etc)
     * @return array Resposta da API
     */
    public function setSettings($instanceName, $settings) {
        // Certifique-se de que os valores booleanos estão corretos
        if (isset($settings['rejectCall'])) $settings['rejectCall'] = (bool)$settings['rejectCall'];
        if (isset($settings['groupsIgnore'])) $settings['groupsIgnore'] = (bool)$settings['groupsIgnore'];
        if (isset($settings['alwaysOnline'])) $settings['alwaysOnline'] = (bool)$settings['alwaysOnline'];
        if (isset($settings['readMessages'])) $settings['readMessages'] = (bool)$settings['readMessages'];
        if (isset($settings['readStatus'])) $settings['readStatus'] = (bool)$settings['readStatus'];
        if (isset($settings['syncFullHistory'])) $settings['syncFullHistory'] = (bool)$settings['syncFullHistory'];
        
        return $this->post("settings/set/{$instanceName}", $settings);
    }
    
    /**
     * Obtém as configurações da instância
     *
     * @param string $instanceName Nome da instância
     * @return array Resposta da API
     */
    public function getSettings($instanceName) {
        return $this->get("settings/find/{$instanceName}");
    }
}