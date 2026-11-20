<?php
/**
 * Mapa único de permissões e hierarquia de cargos do painel administrativo.
 */

if (!function_exists('censurarEmail')) {
    function censurarEmail(string $email): string {
        $email = trim($email);
        if ($email === '') {
            return '';
        }
        $pos = strpos($email, '@');
        $local = $pos === false ? $email : substr($email, 0, $pos);
        $dominio = $pos === false ? '' : substr($email, $pos);
        $len = mb_strlen($local, 'UTF-8');
        if ($len <= 1) {
            return $local . '***' . $dominio;
        }
        $primeira = mb_substr($local, 0, 1, 'UTF-8');
        $ultima = mb_substr($local, -1, 1, 'UTF-8');
        return $primeira . '***' . $ultima . $dominio;
    }
}

if (!function_exists('normalizarCargoAdmin')) {
    function normalizarCargoAdmin(?string $cargo): ?string {
        $c = mb_strtolower(trim((string)$cargo), 'UTF-8');
        if ($c === '') {
            return null;
        }
        if (str_contains($c, 'fundad')) {
            return 'fundador';
        }
        if (str_contains($c, 'diretor') || $c === 'diretoria') {
            return 'diretor';
        }
        if (str_contains($c, 'coordena') || str_contains($c, 'design')) {
            return 'coordenador';
        }
        if (str_contains($c, 'desenvolvedor') || str_contains($c, 'developer') || $c === 'dev' || $c === 'devs') {
            return 'desenvolvedor';
        }
        return null;
    }
}

if (!function_exists('usuarioPodeAcessar')) {
    function usuarioPodeAcessar(string $modulo, ?string $cargoNivel): bool {
        if ($cargoNivel === null) {
            return false;
        }
        if ($modulo === 'conta') {
            return true;
        }
        static $mapa = [
            'fundador' => [
                'dashboard', 'email', 'logs', 'usuarios',
                'pedidos', 'vips', 'chaves', 'cupons',
                'servidores', 'equipe', 'equipe_manage', 'parceiros', 'wiki', 'wiki_categorias', 'noticias', 'webhooks',
            ],
            'diretor' => [
                'dashboard', 'email', 'logs', 'usuarios',
                'pedidos', 'vips', 'chaves', 'cupons',
                'servidores', 'equipe', 'equipe_manage', 'parceiros', 'wiki', 'wiki_categorias', 'noticias', 'webhooks',
            ],
            'coordenador' => [
                'email',
                'servidores', 'equipe', 'parceiros', 'wiki', 'wiki_categorias', 'noticias',
            ],
            'desenvolvedor' => [
                'email',
            ],
        ];
        return in_array($modulo, $mapa[$cargoNivel] ?? [], true);
    }
}

if (!function_exists('paginaInicialAdmin')) {
    function paginaInicialAdmin(?string $cargoNivel): string {
        return in_array($cargoNivel, ['fundador', 'diretor'], true)
            ? '/admin/dashboard.php'
            : '/admin/email/';
    }
}

if (!function_exists('podeCriarUsuarioParaCargo')) {
    function podeCriarUsuarioParaCargo(?string $atorNivel, ?string $alvoNivel): bool {
        if ($alvoNivel === null) {
            return false;
        }
        if ($atorNivel === 'fundador') {
            return true;
        }
        // Diretoria cria apenas cargos abaixo dela para evitar escalação de privilégio.
        if ($atorNivel === 'diretor') {
            return in_array($alvoNivel, ['coordenador', 'desenvolvedor'], true);
        }
        return false;
    }
}

if (!function_exists('podeDeletarUsuario')) {
    function podeDeletarUsuario(?string $atorNivel, int $atorId, ?string $alvoNivel, int $alvoId): bool {
        if ($atorId <= 0 || $alvoId <= 0 || $atorId === $alvoId) {
            return false;
        }
        if ($atorNivel === 'fundador') {
            return true;
        }
        if ($atorNivel === 'diretor') {
            return !in_array($alvoNivel, ['fundador', 'diretor'], true);
        }
        return false;
    }
}

if (!function_exists('podeEditarUsuario')) {
    function podeEditarUsuario(?string $atorNivel, int $atorId, ?string $alvoNivel, int $alvoId): bool {
        if ($atorId <= 0 || $alvoId <= 0) {
            return false;
        }
        // Fundador edita qualquer usuário (inclusive ele mesmo)
        if ($atorNivel === 'fundador') {
            return true;
        }
        if ($atorNivel === 'diretor') {
            return !in_array($alvoNivel, ['fundador', 'diretor'], true);
        }
        return false;
    }
}

if (!function_exists('enviarEmailAdmin')) {
    /**
     * Envia e-mail via SMTP (se SMTP_HOST estiver no .env) ou fallback para mail().
     */
    function enviarEmailAdmin(string $para, string $assunto, string $corpoHtml): bool {
        $host = trim((string)(getenv('SMTP_HOST') ?: ''));
        $port = (int)(getenv('SMTP_PORT') ?: 587);
        $user = trim((string)(getenv('SMTP_USER') ?: ''));
        $pass = (string)(getenv('SMTP_PASS') ?: '');
        $from = trim((string)(getenv('SMTP_FROM') ?: ($user !== '' ? $user : 'noreply@redenerds.com.br')));
        $fromName = 'Rede Nerd';

        $encodedSubj = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
        $headers = "From: {$fromName} <{$from}>\r\n"
            . "To: <{$para}>\r\n"
            . "Subject: {$encodedSubj}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";

        if ($host === '') {
            return @mail($para, $encodedSubj, $corpoHtml, "From: {$fromName} <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n");
        }

        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $sock = @stream_socket_client($remote, $errno, $errstr, 8);
        if (!$sock) {
            error_log("SMTP connect error ($host:$port): $errstr");
            return false;
        }
        stream_set_timeout($sock, 8);

        $ler = static function($s): string {
            $out = '';
            while (($line = fgets($s, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function($s, string $c, string $okPrefix) use ($ler): bool {
            if ($c !== '') {
                fwrite($s, $c . "\r\n");
            }
            $resp = $ler($s);
            return str_starts_with($resp, $okPrefix);
        };

        $ok = $cmd($sock, '', '220') && $cmd($sock, 'EHLO localhost', '250');
        if ($ok && $port !== 465) {
            if ($cmd($sock, 'STARTTLS', '220')) {
                $ok = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
                    && $cmd($sock, 'EHLO localhost', '250');
            }
        }
        if ($ok && $user !== '' && $pass !== '') {
            $ok = $cmd($sock, 'AUTH LOGIN', '334')
                && $cmd($sock, base64_encode($user), '334')
                && $cmd($sock, base64_encode($pass), '235');
        }
        if ($ok) {
            $body = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $corpoHtml);
            $ok = $cmd($sock, "MAIL FROM:<{$from}>", '250')
                && $cmd($sock, "RCPT TO:<{$para}>", '250')
                && $cmd($sock, 'DATA', '354')
                && $cmd($sock, $headers . "\r\n" . $body . "\r\n.", '250');
        }
        @fwrite($sock, "QUIT\r\n");
        @fclose($sock);
        return $ok;
    }
}

if (!function_exists('coordenadorPodeGerenciarCargo')) {
    function coordenadorPodeGerenciarCargo(?string $atorNivel, ?string $cargoAlvo): bool {
        if (in_array($atorNivel, ['fundador', 'diretor'], true)) {
            return true;
        }
        if ($atorNivel === 'coordenador') {
            return !in_array(normalizarCargoAdmin($cargoAlvo), ['fundador', 'diretor'], true);
        }
        return false;
    }
}

if (!function_exists('validarSenhaForte')) {
    function validarSenhaForte(string $senha): ?string {
        if (strlen($senha) < 8) {
            return 'A senha deve ter no mínimo 8 caracteres.';
        }
        if (!preg_match('/[0-9]/', $senha)) {
            return 'A senha deve conter pelo menos 1 número.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            return 'A senha deve conter pelo menos 1 caractere especial.';
        }
        return null;
    }
}

if (!function_exists('chamarDirectAdminEmailPop')) {
    function chamarDirectAdminEmailPop(string $action, string $email, string $senha, ?string &$erro = null): bool {
        $daUrl = rtrim(trim((string)(getenv('DA_URL') ?: '')), '/');
        $daUser = trim((string)(getenv('DA_USER') ?: ''));
        $daKey = (string)(getenv('DA_KEY') ?: '');
        if ($daUrl === '' || $daUser === '' || $daKey === '') {
            return true;
        }
        if (!function_exists('curl_init')) {
            $erro = 'Extensão cURL indisponível para conectar ao servidor de e-mail.';
            return false;
        }

        [$localPart, $domain] = array_pad(explode('@', mb_strtolower(trim($email), 'UTF-8'), 2), 2, '');
        $dominioEsperado = mb_strtolower(trim((string)(getenv('DA_DOMAIN') ?: 'redenerds.com.br')), 'UTF-8');
        if ($localPart === '' || $domain !== $dominioEsperado) {
            $erro = "O e-mail precisa pertencer ao domínio @{$dominioEsperado}.";
            return false;
        }

        $payload = [
            'action' => $action,
            'domain' => $domain,
            'user' => $localPart,
        ];
        if ($action === 'delete') {
            $payload['select0'] = $localPart;
        } else {
            $payload['passwd'] = $senha;
            $payload['passwd2'] = $senha;
        }
        if ($action === 'create') {
            $payload['quota'] = 0;
        }

        $ch = curl_init($daUrl . '/CMD_API_EMAIL_POP');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $daUser . ':' . $daKey,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($resp) || $resp === '') {
            $erro = 'Falha ao conectar no servidor de e-mail: ' . ($curlErr ?: "HTTP {$httpCode}");
            return false;
        }

        parse_str($resp, $parsed);
        if (($parsed['error'] ?? '1') !== '0') {
            $detalhe = trim(strip_tags((string)(($parsed['text'] ?? '') . ' ' . ($parsed['details'] ?? ''))));
            $erro = 'O servidor recusou a operação no e-mail: ' . ($detalhe !== '' ? $detalhe : "HTTP {$httpCode}");
            return false;
        }

        return true;
    }
}

if (!function_exists('criarEmailDirectAdmin')) {
    function criarEmailDirectAdmin(string $email, string $senha, ?string &$erro = null): bool {
        return chamarDirectAdminEmailPop('create', $email, $senha, $erro);
    }
}

if (!function_exists('atualizarSenhaEmailDirectAdmin')) {
    function atualizarSenhaEmailDirectAdmin(string $email, string $novaSenha, ?string &$erro = null): bool {
        return chamarDirectAdminEmailPop('modify', $email, $novaSenha, $erro);
    }
}

if (!function_exists('deletarEmailDirectAdmin')) {
    function deletarEmailDirectAdmin(string $email, ?string &$erro = null): bool {
        return chamarDirectAdminEmailPop('delete', $email, '', $erro);
    }
}

if (!function_exists('obterDestinoResetSenha')) {
    /**
     * Prefere email_recuperacao; se vazio, usa o email da empresa (nunca os dois).
     */
    function obterDestinoResetSenha(array $usuario): ?string {
        $rec = trim((string)($usuario['email_recuperacao'] ?? ''));
        if ($rec !== '' && filter_var($rec, FILTER_VALIDATE_EMAIL)) {
            return $rec;
        }
        $corp = trim((string)($usuario['email'] ?? ''));
        if ($corp !== '' && filter_var($corp, FILTER_VALIDATE_EMAIL)) {
            return $corp;
        }
        return null;
    }
}

if (!function_exists('enviarResetSenhaUsuario')) {
    /**
     * Gera token de redefinição (1h, uso único) e envia para o destino único escolhido.
     * Retorna ['ok' => bool, 'destino' => ?string, 'erro' => ?string].
     */
    function enviarResetSenhaUsuario(PDO $pdo, array $usuario): array {
        $destino = obterDestinoResetSenha($usuario);
        if ($destino === null) {
            return ['ok' => false, 'destino' => null, 'erro' => 'Este usuário não possui um e-mail válido cadastrado.'];
        }

        $token = bin2hex(random_bytes(32));
        $upd = $pdo->prepare("
            UPDATE usuarios
            SET reset_token_hash = :hash, reset_expira_em = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            WHERE id = :id
            LIMIT 1
        ");
        $upd->execute([
            ':hash' => hash('sha256', $token),
            ':id' => (int)$usuario['id'],
        ]);

        $baseUrl = (defined('SITE_URL') && !empty(SITE_URL)) ? rtrim((string)SITE_URL, '/') : 'https://redenerds.com.br';
        $link = $baseUrl . '/admin/redefinir_senha.php?token=' . urlencode($token);

        $nickSafe = htmlspecialchars((string)($usuario['nick'] ?? $usuario['usuario'] ?? ''), ENT_QUOTES, 'UTF-8');
        $linkSafe = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $corpo = "<p>Olá, <strong>{$nickSafe}</strong>.</p>"
            . "<p>Foi solicitada a redefinição da sua senha no Painel Administrativo da Rede Nerd.</p>"
            . "<p><a href=\"{$linkSafe}\" style=\"display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;\">Redefinir minha senha</a></p>"
            . "<p>Ou acesse: <br><code>{$linkSafe}</code></p>"
            . "<p>Este link expira em <strong>1 hora</strong> e pode ser usado apenas uma vez.</p>";

        if (!enviarEmailAdmin($destino, 'Redefinição de Senha - Painel Rede Nerd', $corpo)) {
            return ['ok' => false, 'destino' => $destino, 'erro' => 'Falha ao enviar o e-mail. Verifique as variáveis SMTP_HOST, SMTP_PORT, SMTP_USER e SMTP_PASS no .env.'];
        }

        return ['ok' => true, 'destino' => $destino, 'erro' => null];
    }
}

if (!function_exists('listarEmailsDirectAdmin')) {
    /**
     * Lista os e-mails existentes no domínio configurado no DirectAdmin (requer CMD_API_POP na Login Key).
     */
    function listarEmailsDirectAdmin(?string &$erro = null): array {
        $daUrl = rtrim(trim((string)(getenv('DA_URL') ?: '')), '/');
        $daUser = trim((string)(getenv('DA_USER') ?: ''));
        $daKey = (string)(getenv('DA_KEY') ?: '');
        $domain = mb_strtolower(trim((string)(getenv('DA_DOMAIN') ?: 'redenerds.com.br')), 'UTF-8');

        if ($daUrl === '' || $daUser === '' || $daKey === '' || !function_exists('curl_init')) {
            return [];
        }

        $ch = curl_init($daUrl . '/CMD_API_POP?action=list&domain=' . urlencode($domain));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $daUser . ':' . $daKey,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($resp) || $resp === '') {
            $erro = 'Falha ao consultar e-mails no servidor: ' . ($curlErr ?: "HTTP {$httpCode}");
            return [];
        }

        parse_str($resp, $parsed);
        if (isset($parsed['error']) && $parsed['error'] !== '0') {
            $detalhe = trim(strip_tags((string)(($parsed['text'] ?? '') . ' ' . ($parsed['details'] ?? ''))));
            $erro = $detalhe !== '' ? $detalhe : "HTTP {$httpCode}";
            return [];
        }

        $lista = $parsed['list'] ?? [];
        if (!is_array($lista)) {
            return [];
        }

        $emails = [];
        foreach ($lista as $localPart) {
            $local = trim((string)$localPart);
            if ($local !== '') {
                $emails[] = mb_strtolower($local . '@' . $domain, 'UTF-8');
            }
        }
        sort($emails);
        return $emails;
    }
}

if (!function_exists('autenticarSnappyMail')) {
    /**
     * Autentica no SnappyMail (RNMail) server-to-server e repassa os cookies de sessão ao navegador.
     * Retorna true se autenticou e enviou os cookies; false caso contrário.
     */
    function autenticarSnappyMail(string $email, string $senha): bool {
        if ($email === '' || $senha === '' || !function_exists('curl_init')) {
            return false;
        }

        $envUrl = trim((string)(getenv('SNAPPYMAIL_INTERNAL_URL') ?: ''));
        $ehLocal = str_starts_with((string)($_SERVER['HTTP_HOST'] ?? ''), 'localhost') || str_starts_with((string)($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1');
        $candidatos = $envUrl !== ''
            ? [rtrim($envUrl, '/')]
            : ($ehLocal ? ['http://127.0.0.1:8888', 'http://snappymail:8888'] : ['http://snappymail:8888', 'http://127.0.0.1:8888']);

        $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        // 1. Obtém cookie smtoken e CSRF System.token em /?/AppData/
        $baseUrl = '';
        $resp = '';
        $headerSize = 0;
        foreach ($candidatos as $cand) {
            $ch = curl_init($cand . '/?/AppData/');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
            ]);
            $r = curl_exec($ch);
            $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            if (is_string($r) && $r !== '') {
                $baseUrl = $cand;
                $resp = $r;
                $headerSize = $hs;
                break;
            }
        }

        if ($baseUrl === '' || $resp === '') {
            return false;
        }

        $rawHeaders = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        $data = json_decode($body, true);
        $xToken = is_array($data) ? ($data['System']['token'] ?? '') : '';

        $smtoken = '';
        if (preg_match('/^Set-Cookie:\s*smtoken=([^;\r\n]+)/mi', $rawHeaders, $m)) {
            $smtoken = $m[1];
        }
        if ($xToken === '' || $smtoken === '') {
            return false;
        }

        // 2. Executa Action=Login em /?/Json/
        $ch = curl_init($baseUrl . '/?/Json/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'Action' => 'Login',
                'Email' => $email,
                'Password' => $senha,
                'XToken' => $xToken,
            ]),
            CURLOPT_HTTPHEADER => [
                'X-SM-Token: ' . $xToken,
                'Cookie: smtoken=' . $smtoken,
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);
        $loginResp = curl_exec($ch);
        $loginHeaderSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if (!is_string($loginResp) || $loginResp === '') {
            return false;
        }

        $loginHeaders = substr($loginResp, 0, $loginHeaderSize);
        $loginBody = json_decode(substr($loginResp, $loginHeaderSize), true);
        if (!is_array($loginBody) || empty($loginBody['Result'])) {
            error_log('RNMail auto-login falhou para ' . $email . ': ' . ($loginBody['message'] ?? 'sem resposta'));
            return false;
        }

        // Repassa os cookies de sessão do SnappyMail para o navegador no escopo /webmail/
        // Usa setrawcookie() porque os valores vindos do Set-Cookie do SnappyMail já estão URL-encoded.
        $cookies = ['smtoken' => $smtoken];
        if (preg_match_all('/^Set-Cookie:\s*([^=\s;]+)=([^;\r\n]*)/mi', $loginHeaders, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $cookies[$m[1]] = $m[2];
            }
        }
        foreach ($cookies as $nome => $valor) {
            if ($valor === '') {
                continue;
            }
            setrawcookie($nome, $valor, [
                'expires' => 0,
                'path' => '/webmail/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return true;
    }
}

if (!function_exists('limparCookiesSnappyMail')) {
    function limparCookiesSnappyMail(): void {
        $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        foreach (['/', '/webmail/'] as $cookiePath) {
            foreach (['smtoken', 'smsession', 'smaccount', 'smadditional', 'smremember'] as $nome) {
                setcookie($nome, '', [
                    'expires' => time() - 42000,
                    'path' => $cookiePath,
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }
}

