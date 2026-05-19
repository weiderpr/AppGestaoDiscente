<?php
namespace App\Services;

use Exception;

class MailService {

    /**
     * Envia um e-mail utilizando SMTP via Sockets (se configurado) ou fallback para mail() do PHP
     *
     * @param string $to Destinatário
     * @param string $subject Assunto (já encodado em UTF-8 se necessário)
     * @param string $body Corpo em HTML
     * @param array $headers Cabeçalhos chave => valor
     * @return bool
     * @throws Exception
     */
    public static function send(string $to, string $subject, string $body, array $headers = []): bool {
        // Se as constantes do SMTP não estiverem definidas, faz fallback para mail() nativo do PHP
        if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
            $headersStr = "";
            foreach ($headers as $k => $v) {
                $headersStr .= "{$k}: {$v}\n";
            }
            $sent = @mail($to, $subject, $body, trim($headersStr));
            if (!$sent) {
                throw new Exception("O servidor local não possui um MTA (agente de e-mail local) configurado e não foram encontradas credenciais de SMTP em config.local.php.");
            }
            return true;
        }

        $host = SMTP_HOST;
        $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $user = SMTP_USER;
        $pass = SMTP_PASS;
        $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls'; // 'tls', 'ssl', ou ''

        // Normalização inteligente para Gmail: remove espaços da Senha de App de 16 caracteres
        if (str_contains(strtolower($host), 'gmail')) {
            $pass = str_replace(' ', '', $pass);
        }

        // Resolve o host do socket baseado na segurança SSL
        $socketHost = ($secure === 'ssl') ? "ssl://{$host}" : $host;
        
        // Abre conexão socket TCP
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Falha de conexão SMTP ao servidor {$socketHost}:{$port} ({$errno}): {$errstr}");
        }

        // Função interna para ler a resposta multilinhas do SMTP
        $getResponse = function($socket) {
            $response = "";
            while (($line = fgets($socket, 512)) !== false) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            return $response;
        };

        // Função interna para enviar comandos e verificar códigos esperados
        $sendCommand = function($socket, $command, $expectedResponse) use ($getResponse) {
            fputs($socket, $command . "\r\n");
            $response = $getResponse($socket);
            $code = (int)substr($response, 0, 3);
            if (!in_array($code, (array)$expectedResponse)) {
                throw new Exception("Falha no comando SMTP '{$command}': Resposta do Servidor: " . trim($response));
            }
            return $response;
        };

        try {
            // Lê a mensagem de saudação inicial (código esperado: 220)
            $response = $getResponse($socket);
            if ((int)substr($response, 0, 3) !== 220) {
                throw new Exception("Servidor SMTP inválido ou sem resposta: " . trim($response));
            }

            // Envia EHLO inicial
            $sendCommand($socket, "EHLO localhost", [250]);

            // Se for conexão TLS, inicia a negociação segura sobre a conexão aberta
            if ($secure === 'tls') {
                $sendCommand($socket, "STARTTLS", [220]);
                
                // Configura as opções do método de criptografia
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    throw new Exception("Falha na negociação de criptografia segura (TLS v1.2/v1.3).");
                }

                // Envia EHLO novamente sob canal seguro
                $sendCommand($socket, "EHLO localhost", [250]);
            }

            // Autenticação SMTP
            $sendCommand($socket, "AUTH LOGIN", [334]);
            $sendCommand($socket, base64_encode($user), [334]);
            $sendCommand($socket, base64_encode($pass), [235]);

            // Define o remetente e destinatário do envelope
            $sendCommand($socket, "MAIL FROM: <{$user}>", [250]);
            $sendCommand($socket, "RCPT TO: <{$to}>", [250, 251]);

            // Solicita início do envio de dados
            $sendCommand($socket, "DATA", [354]);

            // Constrói os cabeçalhos e corpo do e-mail
            $payload = "";
            foreach ($headers as $k => $v) {
                $payload .= "{$k}: {$v}\r\n";
            }
            // Garante que o assunto e o destinatário estejam presentes nos cabeçalhos visuais
            if (!isset($headers['Subject'])) {
                $payload .= "Subject: {$subject}\r\n";
            }
            if (!isset($headers['To'])) {
                $payload .= "To: {$to}\r\n";
            }
            
            // Corpo do e-mail
            $payload .= "\r\n" . $body . "\r\n.\r\n";

            // Envia o payload
            fputs($socket, $payload);
            $response = $getResponse($socket);
            if ((int)substr($response, 0, 3) !== 250) {
                throw new Exception("Falha na aceitação dos dados pelo servidor: " . trim($response));
            }

            // Finaliza a sessão SMTP de forma limpa
            $sendCommand($socket, "QUIT", [221]);
            fclose($socket);
            return true;

        } catch (Exception $e) {
            @fclose($socket);
            throw $e;
        }
    }
}
