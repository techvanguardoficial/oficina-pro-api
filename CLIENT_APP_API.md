# Servcar — Client App API

API exclusiva para o aplicativo mobile dos proprietários de veículos.

---

## Visão Geral

- **Base URL:** `{{base_url}}/v1/client-app`
- **Autenticação:** Bearer Token (Sanctum) — validade de 30 dias
- **Formato:** JSON

### Como funciona o login

O cliente não usa senha. A autenticação é feita via OTP enviado por WhatsApp:

```
1. POST /auth/request-otp  → envia código de 6 dígitos via WhatsApp
2. POST /auth/verify-otp   → valida o código e retorna o Bearer Token
3. Requisições seguintes   → usa o token no header Authorization
```

O token dura 30 dias. Após esse prazo, o cliente passa pelo fluxo de OTP novamente.

### Multi-oficina

O mesmo cliente pode estar cadastrado em mais de uma oficina. O sistema vincula automaticamente todos os registros pelo número de telefone. O histórico de veículos e ordens de serviço é consolidado de todas as oficinas.

---

## Rotas Públicas

### POST `/auth/request-otp`

Envia um código OTP via WhatsApp para o número informado.

O número deve estar cadastrado em pelo menos uma oficina. Limite de 3 tentativas por 15 minutos.

**Request**
```json
{
  "phone": "11999999999"
}
```

**Response 200**
```json
{
  "message": "Código enviado via WhatsApp."
}
```

**Errors**

| Status | Code | Descrição |
|--------|------|-----------|
| 404 | `PHONE_NOT_FOUND` | Telefone não encontrado em nenhuma oficina |
| 429 | `TOO_MANY_ATTEMPTS` | Mais de 3 tentativas em 15 minutos |
| 500 | `SEND_FAILED` | Falha ao enviar a mensagem WhatsApp |

---

### POST `/auth/verify-otp`

Valida o código OTP e retorna o Bearer Token. O código expira em 10 minutos.

**Request**
```json
{
  "phone": "11999999999",
  "code": "483921"
}
```

**Response 200**
```json
{
  "token": "1|abc123...",
  "workshops_count": 2
}
```

> `workshops_count` indica em quantas oficinas o cliente está cadastrado.

**Errors**

| Status | Code | Descrição |
|--------|------|-----------|
| 422 | `INVALID_OTP` | Código inválido ou expirado |

---

## Rotas Autenticadas

Todas as rotas abaixo exigem o header:

```
Authorization: Bearer {token}
```

---

### GET `/auth/me`

Retorna os dados do cliente autenticado e todas as oficinas vinculadas.

**Response 200**
```json
{
  "phone": "5511999999999",
  "last_login_at": "2026-05-07T20:00:00.000000Z",
  "vehicles_count": 2,
  "workshops": [
    {
      "client_id": 1,
      "name": "João Silva",
      "email": "joao@email.com",
      "company": {
        "id": 1,
        "name": "Oficina ABC Ltda",
        "fantasy_name": "Oficina ABC",
        "phone": "1133331234"
      }
    },
    {
      "client_id": 7,
      "name": "João Silva",
      "email": "joao@email.com",
      "company": {
        "id": 3,
        "name": "Auto Center XYZ",
        "fantasy_name": "Auto Center XYZ",
        "phone": "1144445678"
      }
    }
  ]
}
```

---

### POST `/auth/logout`

Revoga o token atual.

**Response 200**
```json
{
  "message": "Logout realizado com sucesso."
}
```

---

### GET `/vehicles`

Lista todos os veículos do cliente em todas as oficinas, agrupados por placa.

**Response 200**
```json
[
  {
    "placa": "abc1234",
    "formatted_placa": "ABC-1234",
    "color": "Preto",
    "year": 2019,
    "info": null,
    "current_km": "45000",
    "car_model": {
      "id": 12,
      "name": "Onix",
      "car_maker": {
        "id": 3,
        "name": "Chevrolet"
      }
    },
    "workshops_count": 2
  }
]
```

> `workshops_count` indica em quantas oficinas o veículo está registrado.

---

### GET `/vehicles/{placa}`

Retorna o detalhe de um veículo com o histórico completo de ordens de serviço de todas as oficinas.

**Parâmetros**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `placa` | string | Placa do veículo (ex: `ABC1234`) |

**Response 200**
```json
{
  "placa": "abc1234",
  "formatted_placa": "ABC-1234",
  "color": "Preto",
  "year": 2019,
  "info": null,
  "current_km": "45000",
  "car_model": {
    "id": 12,
    "name": "Onix",
    "car_maker": {
      "id": 3,
      "name": "Chevrolet"
    }
  },
  "workshops": [
    {
      "company_id": 1,
      "company_name": "Oficina ABC",
      "orders_count": 3
    },
    {
      "company_id": 3,
      "company_name": "Auto Center XYZ",
      "orders_count": 1
    }
  ],
  "order_services": [
    {
      "id": 15,
      "description": "Troca de óleo",
      "created_at": "2026-04-10T10:00:00.000000Z",
      "order_status": { "id": 3, "name": "Concluído" },
      "order_type": { "id": 1, "name": "Manutenção" }
    }
  ]
}
```

**Errors**

| Status | Code | Descrição |
|--------|------|-----------|
| 404 | `NOT_FOUND` | Veículo não encontrado ou não pertence ao cliente |

---

## Variáveis de Ambiente

```env
EVOLUTION_API_URL=https://integrations-evolution-api.ghqegs.easypanel.host
EVOLUTION_API_KEY=sua_chave_aqui
EVOLUTION_INSTANCE=oficina-pro-envio
```

---

## Banco de Dados

### Tabelas criadas

**`otp_codes`**

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `phone` | string | Telefone normalizado (ex: `5511999999999`) |
| `code` | string(6) | Código OTP |
| `expires_at` | timestamp | Expiração (10 minutos) |
| `used_at` | timestamp\|null | Preenchido quando utilizado |

**`client_app_users`**

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `phone` | string | Identificador único do usuário no app |
| `device_token` | string\|null | Token para push notifications |
| `last_login_at` | timestamp\|null | Último login |

**`client_app_user_clients`** *(pivot)*

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `client_app_user_id` | FK | Usuário do app |
| `client_id` | FK | Registro do cliente na oficina |

---

## Normalização de Telefone

O sistema aceita qualquer formato de entrada e normaliza para o padrão Evolution API:

| Entrada | Normalizado |
|---------|-------------|
| `(11) 99999-9999` | `5511999999999` |
| `11999999999` | `5511999999999` |
| `+5511999999999` | `5511999999999` |
| `5511999999999` | `5511999999999` |

A busca no banco compara os últimos 11 dígitos para tolerar variações de formato nas colunas `phone_one`, `phone_two` e `phone_three`.
