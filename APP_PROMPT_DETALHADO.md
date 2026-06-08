# Servcar Client App — Prompt Detalhado

## 📱 Estrutura de Navegação

```
App
├── Stack: Auth (público)
│   ├── Login Screen
│   ├── OTP Verification Screen
│   └── Loading Screen
│
└── Stack: Autenticado
    ├── Dashboard (Home)
    │   └── Tab: Veículos
    │   └── Tab: Perfil
    │
    ├── Vehicle Details
    │   ├── Info básica
    │   ├── Histórico de ordens
    │   └── Oficinas associadas
    │
    └── Profile
        ├── Dados do usuário
        ├── Oficinas vinculadas
        └── Logout
```

---

## 🎯 Tela 1: Login

### Objetivo
Capturar o telefone do usuário e solicitar um código OTP via WhatsApp.

### Layout
```
┌─────────────────────────────┐
│                             │
│     LOGO SERVCAR            │
│                             │
│  Insira seu telefone        │
│  ┌─────────────────────┐    │
│  │ (11) 9999-9999      │    │ ← Input formatado
│  └─────────────────────┘    │
│                             │
│  [ ENVIAR CÓDIGO ]          │ ← Desabilitado até válido
│                             │
│  Receba um código via       │
│  WhatsApp para fazer login  │
│                             │
└─────────────────────────────┘
```

### Funcionalidades
- **Input de Telefone:**
  - Aceita qualquer formato: `(11) 9999-9999`, `11999999999`, `+5511999999999`
  - Normaliza internamente para `55XXXXXXXXXX`
  - Máscara visual: `(XX) XXXXX-XXXX`
  - Validação: apenas números, mínimo 10 dígitos

- **Botão ENVIAR CÓDIGO:**
  - Desabilitado até telefone ser válido
  - Ao clicar: `POST /auth/request-otp { phone: "5511999999999" }`
  - Estados:
    - **Carregando:** Spinner, texto "Enviando..."
    - **Sucesso:** Toast "Código enviado para WhatsApp", navegue para OTP Verification
    - **Erro - Telefone não encontrado (404):** Alert "Esse número não está cadastrado em nenhuma oficina"
    - **Erro - Muitas tentativas (429):** Alert "Máximo de 3 tentativas em 15 minutos. Tente novamente em [X] minutos"
    - **Erro - Falha ao enviar (500):** Alert "Não conseguimos enviar o código. Tente novamente"

- **UX:**
  - Salvar último telefone usado (opcional, para conveniência)
  - Botão "Como funciona?" que explica o fluxo OTP

---

## 🎯 Tela 2: OTP Verification

### Objetivo
Validar o código OTP recebido via WhatsApp e obter o token de autenticação.

### Layout
```
┌─────────────────────────────┐
│                             │
│  ← (voltar)                 │
│                             │
│  Código de 6 dígitos        │
│  enviado para               │
│  (11) 99999-9999            │
│                             │
│  ┌─┬─┬─┬─┬─┬─┐  ← 6 inputs │
│  │_│_│_│_│_│_│             │
│  └─┴─┴─┴─┴─┴─┘             │
│                             │
│  Código expira em: 09:45    │ ← Contador regressivo
│                             │
│  [ VERIFICAR ]              │ ← Ativa quando 6 dígitos
│                             │
│  Não recebeu? [ REENVIAR ]  │
│                             │
└─────────────────────────────┘
```

### Funcionalidades
- **Input de Código:**
  - 6 campos numéricos separados
  - Auto-avanço: quando digita um dígito, move pro próximo campo
  - Auto-volta: Backspace volta pro campo anterior
  - Permite pasting: se colar "123456", preenche automaticamente
  - Validação: apenas números

- **Contador Regressivo:**
  - Começa em 10 minutos
  - Mostra tempo restante em formato MM:SS
  - Quando atinge 0:00, desabilita o botão VERIFICAR e mostra "Código expirado"

- **Botão VERIFICAR:**
  - Desabilitado até 6 dígitos serem preenchidos
  - Ao clicar: `POST /auth/verify-otp { phone: "5511999999999", code: "123456" }`
  - Estados:
    - **Carregando:** Spinner, desabilita campo
    - **Sucesso:** Salva token + workshops_count, navega para Dashboard
    - **Erro - Código inválido (422):** Alert "Código inválido ou expirado"

- **Botão REENVIAR:**
  - Disponível sempre (ou após X segundos da tentativa anterior)
  - Chama `/auth/request-otp` novamente
  - Reseta o contador para 10 minutos
  - Reseta os campos OTP

- **Botão VOLTAR:**
  - Volta para tela de Login
  - Limpa o estado do OTP

- **UX:**
  - Mostrar o telefone parcialmente: "(11) 9999-****"
  - Auto-submit quando 6 dígitos são preenchidos (opcional)
  - Teclado numérico em mobile

---

## 🎯 Tela 3: Dashboard / Home

### Objetivo
Listar todos os veículos do usuário consolidados de todas as oficinas.

### Layout
```
┌─────────────────────────────┐
│ 09:30        ◉  WIFI  🔋99% │
├─────────────────────────────┤
│ ← Dashboard              ⚙️  │
├─────────────────────────────┤
│                             │
│  Olá, João Silva! 👋        │ ← Saudação + nome
│  Você tem 2 veículos        │
│                             │
│  ┌─────────────────────┐    │
│  │ 🚗 ABC-1234         │    │ ← Card do veículo
│  │ Chevrolet Onix      │
│  │ 2019 • Preto        │
│  │                     │
│  │ ⚙️ 3 ordens | 2 oficial│ ← Conta ordens + oficinas
│  └─────────────────────┘    │
│                             │
│  ┌─────────────────────┐    │
│  │ 🚗 XYZ-5678         │    │
│  │ Toyota Corolla      │
│  │ 2020 • Prata        │
│  │                     │
│  │ ⚙️ 1 ordem | 1 oficial│
│  └─────────────────────┘    │
│                             │
├─────────────────────────────┤
│ [Veículos]  [Perfil]        │ ← Tabs
└─────────────────────────────┘
```

### Funcionalidades
- **Carregamento Inicial:**
  - Chama `GET /auth/me` para puxar dados do usuário
  - Chama `GET /vehicles` para listar veículos
  - Mostra skeleton/placeholder enquanto carrega

- **Saudação Personalizada:**
  - "Olá, [nome do cliente]! 👋"
  - Contador: "Você tem X veículos"

- **Cards de Veículos:**
  - Mostra: Placa formatada (ABC-1234), Marca + Modelo, Ano, Cor
  - Badge com ícone: "⚙️ 3 ordens | 2 oficinas"
  - Clicável → navega para Vehicle Details
  - Pull-to-refresh para recarregar lista

- **Estados:**
  - **Loading:** Skeleton cards
  - **Vazio:** "Nenhum veículo encontrado. Verifique com suas oficinas."
  - **Erro:** Botão de retry

- **Ícones/Visuais:**
  - Cada marca pode ter ícone específico (opcional)
  - Cores diferentes por tipo de veículo (sugestão)

---

## 🎯 Tela 4: Vehicle Details

### Objetivo
Mostrar informações detalhadas do veículo e histórico completo de ordens de serviço.

### Layout
```
┌─────────────────────────────┐
│ ← Vehicle Details       ⋯   │
├─────────────────────────────┤
│                             │
│  🚗 ABC-1234 • Preto        │ ← Header do veículo
│  Chevrolet Onix 2019        │
│                             │
│  ┌─────────────────────┐    │
│  │ km: 45.000          │    │ ← Atual
│  │ Info: --            │
│  └─────────────────────┘    │
│                             │
│  Oficinas (2):              │
│  ┌─────────────────────┐    │
│  │ • Oficina ABC       │    │ ← 3 ordens
│  │ • Auto Center XYZ   │    │ ← 1 ordem
│  └─────────────────────┘    │
│                             │
│  Histórico (4 ordens)       │
│  ┌─────────────────────┐    │
│  │ ✓ Troca de óleo     │    │ ← Status Concluído
│  │ 10/04/2026 10:00    │
│  │ Manutenção          │
│  └─────────────────────┘    │
│                             │
│  ┌─────────────────────┐    │
│  │ ⏳ Troca de freio    │    │ ← Status Em progresso
│  │ 05/05/2026 14:30    │
│  │ Manutenção          │
│  └─────────────────────┘    │
│                             │
│  ┌─────────────────────┐    │
│  │ ❌ Reparo motor     │    │ ← Status Cancelado
│  │ 02/05/2026 09:15    │
│  │ Reparo              │
│  └─────────────────────┘    │
│                             │
└─────────────────────────────┘
```

### Funcionalidades
- **Header do Veículo:**
  - Ícone do veículo, placa formatada (ABC-1234), cor
  - Marca + Modelo + Ano
  - Visual destacado (background com cor temática)

- **Seção de Info:**
  - KM atual: "45.000 km"
  - Info adicional: se vazio, mostrar "--"

- **Seção de Oficinas:**
  - Listar nome da oficina com contagem de ordens
  - Clicável → abre detalhes da oficina (opcional: telefone, endereço)

- **Histórico de Ordens:**
  - Ordenado por data DESC (mais recente primeiro)
  - Cada ordem mostra:
    - **Ícone de Status:**
      - ✓ = Concluído (verde)
      - ⏳ = Em progresso (amarelo)
      - ❌ = Cancelado (vermelho)
      - ⓘ = Aguardando (cinza)
    - **Descrição:** "Troca de óleo"
    - **Data/Hora:** "10/04/2026 10:00"
    - **Tipo:** "Manutenção", "Reparo", etc.
  - Clicável → navega para Order Details (opcional)

- **Pull-to-Refresh:**
  - Recarrega dados do veículo

- **Erros:**
  - Se veículo não encontrado (404): mostrar alert "Veículo não encontrado" e voltar

---

## 🎯 Tela 5: Profile

### Objetivo
Mostrar dados do usuário, oficinas vinculadas e permitir logout.

### Layout
```
┌─────────────────────────────┐
│ ← Dashboard              ⚙️  │
├─────────────────────────────┤
│ [Veículos]  [Perfil]        │ ← Tab ativo: Perfil
├─────────────────────────────┤
│                             │
│  👤 Seus Dados              │
│  ┌─────────────────────┐    │
│  │ Nome: João Silva    │    │
│  │ Telefone: (11) 9999-│
│  │           9999      │
│  │ Email: joao@email.. │
│  │ Último acesso:      │
│  │ 07/05/2026 20:00    │
│  └─────────────────────┘    │
│                             │
│  🏢 Suas Oficinas (2)       │
│  ┌─────────────────────┐    │
│  │ Oficina ABC Ltda    │    │
│  │ Fantasia: Oficina   │
│  │ ABC                 │
│  │ Tel: (11) 3333-1234 │
│  └─────────────────────┘    │
│                             │
│  ┌─────────────────────┐    │
│  │ Auto Center XYZ     │    │
│  │ Fantasia: Auto      │
│  │ Center XYZ          │
│  │ Tel: (11) 4444-5678 │
│  └─────────────────────┘    │
│                             │
│  [ SAIR DA CONTA ]          │
│  [ DELETAR CONTA ]          │
│                             │
└─────────────────────────────┘
```

### Funcionalidades
- **Seção: Seus Dados**
  - Nome completo
  - Telefone formatado
  - Email
  - Último acesso (data/hora formatado)
  - Não editável (dados vêm de `/auth/me`)

- **Seção: Suas Oficinas**
  - Lista todas as oficinas vinculadas
  - Cada card mostra:
    - Razão social
    - Nome fantasia
    - Telefone (clicável → abre dialer)
  - Clicável → abre detalhes da oficina (endereço, etc.)

- **Botão SAIR DA CONTA:**
  - Chama `POST /auth/logout`
  - Revoga token
  - Limpa armazenamento local
  - Navega de volta para Login
  - Confirmação: "Deseja realmente sair?"

- **Botão DELETAR CONTA:** (opcional)
  - Requer confirmação dupla
  - Chamada a endpoint DELETE (se existir)
  - Limpa tudo e volta para Login
  - Mostrar aviso: "Esta ação é permanente"

- **Carregamento:**
  - Dados carregados no Dashboard, apenas exibe aqui

---

## 🛠️ Estrutura de Dados & Estado

### Local Storage / Async Storage
```javascript
{
  token: "1|abc123...",           // Bearer Token
  phone: "5511999999999",         // Telefone normalizado
  workshops_count: 2,              // Número de oficinas
  user: {
    phone: "5511999999999",
    last_login_at: "2026-05-07...",
    vehicles_count: 2,
    workshops: [...]               // Array de oficinas
  },
  vehicles: [                       // Cache de veículos
    {
      placa: "abc1234",
      formatted_placa: "ABC-1234",
      color: "Preto",
      year: 2019,
      current_km: "45000",
      car_model: { id, name, car_maker: { id, name } },
      workshops_count: 2
    },
    ...
  ]
}
```

### Context/Redux State
```javascript
{
  auth: {
    token: null,
    phone: null,
    isLoading: false,
    isAuthenticated: false,
    error: null
  },
  user: {
    data: null,
    isLoading: false,
    error: null
  },
  vehicles: {
    list: [],
    current: null,  // Veículo atualmente visualizado
    isLoading: false,
    error: null
  },
  ui: {
    bottomTabIndex: 0,  // 0 = Veículos, 1 = Perfil
    isRefreshing: false
  }
}
```

---

## 📡 API Calls

### 1. Request OTP
```javascript
POST /auth/request-otp
{
  "phone": "5511999999999"
}

// Success (200)
{
  "message": "Código enviado via WhatsApp."
}

// Errors
404: { "code": "PHONE_NOT_FOUND", "message": "..." }
429: { "code": "TOO_MANY_ATTEMPTS", "message": "..." }
500: { "code": "SEND_FAILED", "message": "..." }
```

### 2. Verify OTP
```javascript
POST /auth/verify-otp
{
  "phone": "5511999999999",
  "code": "483921"
}

// Success (200)
{
  "token": "1|abc123...",
  "workshops_count": 2
}

// Error
422: { "code": "INVALID_OTP", "message": "..." }
```

### 3. Get User Data
```javascript
GET /auth/me
Header: Authorization: Bearer {token}

// Success (200)
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
    }
  ]
}
```

### 4. List Vehicles
```javascript
GET /vehicles
Header: Authorization: Bearer {token}

// Success (200)
[
  {
    "placa": "abc1234",
    "formatted_placa": "ABC-1234",
    "color": "Preto",
    "year": 2019,
    "current_km": "45000",
    "car_model": {
      "id": 12,
      "name": "Onix",
      "car_maker": { "id": 3, "name": "Chevrolet" }
    },
    "workshops_count": 2
  }
]
```

### 5. Get Vehicle Details
```javascript
GET /vehicles/{placa}
Header: Authorization: Bearer {token}

// Success (200)
{
  "placa": "abc1234",
  "formatted_placa": "ABC-1234",
  "color": "Preto",
  "year": 2019,
  "current_km": "45000",
  "car_model": { ... },
  "workshops": [
    {
      "company_id": 1,
      "company_name": "Oficina ABC",
      "orders_count": 3
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

// Error
404: { "code": "NOT_FOUND", "message": "..." }
```

### 6. Logout
```javascript
POST /auth/logout
Header: Authorization: Bearer {token}

// Success (200)
{
  "message": "Logout realizado com sucesso."
}
```

---

## ✅ Validações & Tratamentos de Erro

### Input de Telefone
```
✓ Apenas números
✓ Mínimo 10 dígitos (sem formatação)
✓ Máximo 15 dígitos
✓ Normalizar para 55XXXXXXXXXX
✓ Máscara visual: (XX) XXXXX-XXXX
```

### Input de OTP
```
✓ Apenas números
✓ Exatamente 6 dígitos
✓ Auto-advance entre campos
✓ Backspace volta pro campo anterior
✓ Permite pasting
```

### Tratamento de Erros API
```
401/403: Token expirado/inválido → logout forçado, volta para Login
404: Não encontrado → alert + voltar
422: Validação → mostrar mensagem específica
429: Rate limit → mostrar tempo de espera
500: Erro servidor → retry button
Network error: → offline mode / retry
```

---

## 🎨 Design System (Sugestões)

### Cores
```
Primary: #0047AB (Azul)
Success: #28A745 (Verde)
Warning: #FFC107 (Amarelo)
Danger: #DC3545 (Vermelho)
Neutral: #6C757D (Cinza)
Background: #F8F9FA
Card: #FFFFFF
Text: #212529
```

### Tipografia
```
Heading 1: 24px, Bold
Heading 2: 20px, SemiBold
Body: 16px, Regular
Caption: 12px, Regular
```

### Espaçamento
```
xs: 4px
sm: 8px
md: 16px
lg: 24px
xl: 32px
```

### Componentes Reutilizáveis
```
- Button (primary, secondary, danger)
- Card (veículo, oficina, ordem)
- Input (texto, telefone, OTP)
- Alert (sucesso, erro, aviso)
- Loading (spinner, skeleton)
- Badge (status, contador)
- Tab Navigation
- Bottom Navigation
```

---

## 📱 Requisitos Técnicos

### Plataformas
- iOS 13+
- Android 8+

### Dependências Sugeridas (React Native)
```json
{
  "@react-navigation/native": "^6.x",
  "@react-navigation/bottom-tabs": "^6.x",
  "@react-navigation/stack": "^6.x",
  "axios": "^1.x",
  "react-native-mask-input": "^1.x",
  "react-native-phone-number-input": "^2.x",
  "@react-native-async-storage/async-storage": "^1.x",
  "@react-native-community/netinfo": "^9.x"
}
```

### Permissões Necessárias
```
Android:
- INTERNET
- ACCESS_NETWORK_STATE

iOS:
- NSBonjourServices
- NSLocalNetworkUsageDescription
```

---

## 🔄 Fluxos Principais

### Fluxo: Primeiro Acesso
```
1. App abre → verifica token em storage
2. Sem token? → exibe tela Login
3. Usuário insere telefone
4. POST /auth/request-otp
5. Navega para OTP Verification
6. Usuário insere código
7. POST /auth/verify-otp
8. Salva token + workshops_count
9. GET /auth/me (puxar dados do usuário)
10. GET /vehicles (puxar lista de veículos)
11. Navega para Dashboard
```

### Fluxo: Token Expirado
```
1. API retorna 401/403
2. App limpa storage
3. Limpa state (auth, user, vehicles)
4. Navega para Login
5. Mostra toast: "Sessão expirada, faça login novamente"
```

### Fluxo: Offline
```
1. Network error ao chamar API
2. Mostrar alert: "Sem conexão. Verifique sua internet."
3. Botão retry disponível
4. (Opcional) Mostrar dados em cache enquanto offline
```

---

## 🚀 Fases de Desenvolvimento

### Fase 1: Auth (Sprint 1)
- [x] Tela Login
- [x] Tela OTP Verification
- [x] Chamadas API de autenticação
- [x] Armazenamento seguro de token

### Fase 2: Dashboard (Sprint 2)
- [x] Tela Dashboard com lista de veículos
- [x] Tela Profile
- [x] GET /auth/me
- [x] GET /vehicles
- [x] Logout

### Fase 3: Detalhes (Sprint 3)
- [x] Tela Vehicle Details
- [x] GET /vehicles/{placa}
- [x] Exibição de histórico de ordens
- [x] Oficinas associadas

### Fase 4: Polish (Sprint 4)
- [ ] Animations & Transitions
- [ ] Dark Mode (opcional)
- [ ] Notifications/Push (opcional)
- [ ] Tests & QA
- [ ] Deploy
