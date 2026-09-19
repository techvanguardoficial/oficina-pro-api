<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de Senha</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            padding: 40px 20px;
        }
        .wrapper {
            max-width: 560px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header img {
            height: 40px;
            max-width: 180px;
            object-fit: contain;
        }
        .header-fallback {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .body {
            padding: 40px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .text {
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
            margin-bottom: 16px;
        }
        .btn-wrap {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background: #1d4ed8;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 36px;
            border-radius: 8px;
            letter-spacing: 0.2px;
        }
        .expiry-notice {
            background: #f8fafc;
            border-left: 3px solid #94a3b8;
            padding: 14px 18px;
            border-radius: 0 6px 6px 0;
            margin: 24px 0;
        }
        .expiry-notice p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }
        .url-fallback {
            margin: 16px 0;
        }
        .url-fallback p {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .url-fallback a {
            font-size: 12px;
            color: #3b82f6;
            word-break: break-all;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 24px 0;
        }
        .footer {
            padding: 24px 40px;
            background: #f8fafc;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                @if(file_exists(public_path('logo.png')))
                    <img src="{{ asset('logo.png') }}" alt="OficinaPro">
                @else
                    <span class="header-fallback">OficinaPro</span>
                @endif
            </div>

            <div class="body">
                <p class="greeting">Olá!</p>

                <p class="text">
                    Recebemos uma solicitação para redefinir a senha da sua conta.
                    Clique no botão abaixo para criar uma nova senha:
                </p>

                <div class="btn-wrap">
                    <a href="{{ $url }}" class="btn">Redefinir minha senha</a>
                </div>

                <div class="expiry-notice">
                    <p>
                        <strong>Atenção:</strong> Este link é válido por
                        <strong>{{ $count }} {{ $unit }}</strong>.
                        Após esse prazo, você precisará solicitar um novo link.
                    </p>
                </div>

                <p class="text">
                    Caso você não tenha solicitado a redefinição de senha,
                    nenhuma ação é necessária — sua senha permanece a mesma.
                </p>

                <hr class="divider">

                <div class="url-fallback">
                    <p>Se o botão acima não funcionar, copie e cole o link abaixo no seu navegador:</p>
                    <a href="{{ $url }}">{{ $url }}</a>
                </div>
            </div>

            <div class="footer">
                <p>
                    Este e-mail foi enviado automaticamente pela plataforma OficinaPro.<br>
                    Por favor, não responda a este e-mail.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
