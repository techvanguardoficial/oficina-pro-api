# Payloads da API de Subscriptions

## 🔓 GET /api/v1/plans

**Descrição:** Retorna lista de todos os planos ativos com suas features

**Response (200 OK):**

```json
[
  {
    "id": 1,
    "name": "Básico",
    "slug": "basic",
    "description": "Perfeito para oficinas pequenas que estão começando",
    "price": 9900,
    "interval": "month",
    "stripe_product_id": "prod_basic_servcar",
    "stripe_price_id": "price_basic_servcar",
    "trial_days": 7,
    "max_users": 2,
    "max_vehicles": 10,
    "max_orders": 50,
    "max_clients": 100,
    "sort_order": 1,
    "is_active": true,
    "created_at": "2026-04-05T10:30:00.000000Z",
    "updated_at": "2026-04-05T10:30:00.000000Z",
    "deleted_at": null,
    "features": [
      {
        "id": 1,
        "name": "Relatórios Básicos",
        "slug": "basic_reports",
        "description": "Relatórios simples de receita e despesa",
        "category": "reporting",
        "sort_order": 1,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 1,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 5,
        "name": "Gerenciamento de Clientes",
        "slug": "client_management",
        "description": "CRUD completo de clientes",
        "category": "general",
        "sort_order": 5,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 5,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 6,
        "name": "Gerenciamento de Veículos",
        "slug": "vehicle_management",
        "description": "Cadastro e histórico de veículos",
        "category": "general",
        "sort_order": 6,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 6,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 7,
        "name": "Gerenciamento de Ordens",
        "slug": "order_management",
        "description": "Criar e rastrear ordens de serviço",
        "category": "general",
        "sort_order": 7,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 7,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 8,
        "name": "Gerenciamento de Usuários",
        "slug": "user_management",
        "description": "Adicionar e gerenciar usuários da empresa",
        "category": "general",
        "sort_order": 8,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 8,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 13,
        "name": "Suporte por Email",
        "slug": "email_support",
        "description": "Suporte técnico por email (24h)",
        "category": "support",
        "sort_order": 13,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 1,
          "feature_id": 13,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      }
    ]
  },
  {
    "id": 2,
    "name": "Profissional",
    "slug": "professional",
    "description": "Ideal para oficinas em crescimento com múltiplas operações",
    "price": 29900,
    "interval": "month",
    "stripe_product_id": "prod_professional_servcar",
    "stripe_price_id": "price_professional_servcar",
    "trial_days": 14,
    "max_users": 10,
    "max_vehicles": 100,
    "max_orders": 500,
    "max_clients": 1000,
    "sort_order": 2,
    "is_active": true,
    "created_at": "2026-04-05T10:30:00.000000Z",
    "updated_at": "2026-04-05T10:30:00.000000Z",
    "deleted_at": null,
    "features": [
      {
        "id": 1,
        "name": "Relatórios Básicos",
        "slug": "basic_reports",
        "description": "Relatórios simples de receita e despesa",
        "category": "reporting",
        "sort_order": 1,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 2,
          "feature_id": 1,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 2,
        "name": "Relatórios Avançados",
        "slug": "advanced_reports",
        "description": "Relatórios detalhados com gráficos e análises",
        "category": "reporting",
        "sort_order": 2,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 2,
          "feature_id": 2,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 3,
        "name": "Dashboard Customizado",
        "slug": "custom_dashboard",
        "description": "Dashboard personalizável com widgets",
        "category": "reporting",
        "sort_order": 3,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 2,
          "feature_id": 3,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      },
      {
        "id": 4,
        "name": "Exportar Dados",
        "slug": "export_data",
        "description": "Exportar relatórios em PDF, Excel e CSV",
        "category": "reporting",
        "sort_order": 4,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 2,
          "feature_id": 4,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      }
    ]
  },
  {
    "id": 3,
    "name": "Enterprise",
    "slug": "enterprise",
    "description": "Solução completa para grandes redes e grupos de oficinas",
    "price": 99900,
    "interval": "month",
    "stripe_product_id": "prod_enterprise_servcar",
    "stripe_price_id": "price_enterprise_servcar",
    "trial_days": 30,
    "max_users": -1,
    "max_vehicles": -1,
    "max_orders": -1,
    "max_clients": -1,
    "sort_order": 3,
    "is_active": true,
    "created_at": "2026-04-05T10:30:00.000000Z",
    "updated_at": "2026-04-05T10:30:00.000000Z",
    "deleted_at": null,
    "features": [
      {
        "id": 1,
        "name": "Relatórios Básicos",
        "slug": "basic_reports",
        "description": "Relatórios simples de receita e despesa",
        "category": "reporting",
        "sort_order": 1,
        "created_at": "2026-04-05T10:30:00.000000Z",
        "updated_at": "2026-04-05T10:30:00.000000Z",
        "deleted_at": null,
        "pivot": {
          "plan_id": 3,
          "feature_id": 1,
          "is_included": true,
          "created_at": "2026-04-05T10:30:00.000000Z",
          "updated_at": "2026-04-05T10:30:00.000000Z"
        }
      }
    ]
  }
]
```

---

## 📋 Campos Explicados

### Plan
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID único do plano |
| `name` | string | Nome do plano (ex: "Básico") |
| `slug` | string | Identificador único em URL (ex: "basic") |
| `description` | string | Descrição do plano |
| `price` | integer | Preço em centavos (ex: 9900 = R$ 99,00) |
| `interval` | string | Período de cobrança ("month" ou "year") |
| `stripe_product_id` | string | ID do produto no Stripe |
| `stripe_price_id` | string | ID do preço no Stripe |
| `trial_days` | integer | Dias de trial gratuito |
| `max_users` | integer | Limite de usuários (-1 = ilimitado) |
| `max_vehicles` | integer | Limite de veículos (-1 = ilimitado) |
| `max_orders` | integer | Limite de pedidos (-1 = ilimitado) |
| `max_clients` | integer | Limite de clientes (-1 = ilimitado) |
| `sort_order` | integer | Ordem de exibição (1, 2, 3...) |
| `is_active` | boolean | Plano está ativo? |
| `created_at` | timestamp | Data de criação |
| `updated_at` | timestamp | Data de atualização |
| `deleted_at` | timestamp | Data de exclusão (soft delete) |

### Feature (dentro de features array)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID da feature |
| `name` | string | Nome legível |
| `slug` | string | Identificador único |
| `description` | string | O que a feature faz |
| `category` | string | Categoria (reporting, general, integrations, support, advanced) |
| `sort_order` | integer | Ordem de exibição |
| `created_at` | timestamp | Data de criação |
| `updated_at` | timestamp | Data de atualização |
| `deleted_at` | timestamp | Data de exclusão |

### Pivot (relacionamento plan_features)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `plan_id` | integer | ID do plano |
| `feature_id` | integer | ID da feature |
| `is_included` | boolean | Feature está incluída neste plano? |
| `created_at` | timestamp | Data do relacionamento |
| `updated_at` | timestamp | Atualização do relacionamento |

---

## 💡 Exemplos de Uso

### JavaScript/Frontend
```javascript
// Fetch plans
const response = await fetch('http://localhost:8000/api/v1/plans');
const plans = await response.json();

// Acessar dados
plans.forEach(plan => {
  console.log(`${plan.name} - R$ ${(plan.price / 100).toFixed(2)}/mês`);
  
  // Features incluídas
  const includedFeatures = plan.features.filter(f => f.pivot.is_included);
  console.log(`Features: ${includedFeatures.map(f => f.name).join(', ')}`);
});
```

### PHP/Laravel
```php
$plans = Plan::where('is_active', true)
    ->with('features')
    ->get();

foreach ($plans as $plan) {
    echo $plan->name . ' - R$ ' . ($plan->price / 100) . '/mês';
    
    // Features incluídas
    $included = $plan->features()
        ->where('is_included', true)
        ->get();
}
```

### cURL
```bash
curl -X GET http://localhost:8000/api/v1/plans \
  -H "Accept: application/json" | jq '.'
```

---

## 🎨 Renderizar em Tabela (HTML)

```html
<table>
  <thead>
    <tr>
      <th>Plano</th>
      <th>Preço</th>
      <th>Trial</th>
      <th>Usuários</th>
      <th>Features</th>
      <th>Ação</th>
    </tr>
  </thead>
  <tbody id="plans-table">
    <!-- Será preenchido por JavaScript -->
  </tbody>
</table>

<script>
fetch('/api/v1/plans')
  .then(r => r.json())
  .then(plans => {
    const tbody = document.getElementById('plans-table');
    plans.forEach(plan => {
      const featureCount = plan.features.filter(f => f.pivot.is_included).length;
      const row = `
        <tr>
          <td>${plan.name}</td>
          <td>R$ ${(plan.price / 100).toFixed(2)}</td>
          <td>${plan.trial_days} dias</td>
          <td>${plan.max_users === -1 ? '∞' : plan.max_users}</td>
          <td>${featureCount} features</td>
          <td><button onclick="selectPlan(${plan.id})">Escolher</button></td>
        </tr>
      `;
      tbody.innerHTML += row;
    });
  });
</script>
```

---

## 🔍 Filtragem por Categoria (Frontend)

```javascript
const response = await fetch('/api/v1/plans');
const plans = await response.json();

// Agrupar features por categoria
function groupByCategory(plan) {
  return plan.features.reduce((acc, feature) => {
    if (!acc[feature.category]) {
      acc[feature.category] = [];
    }
    acc[feature.category].push(feature);
    return acc;
  }, {});
}

plans.forEach(plan => {
  console.log(`\n${plan.name}`);
  const grouped = groupByCategory(plan);
  
  Object.entries(grouped).forEach(([category, features]) => {
    console.log(`  ${category}:`);
    features
      .filter(f => f.pivot.is_included)
      .forEach(f => console.log(`    ✓ ${f.name}`));
  });
});
```

**Output:**
```
Básico
  reporting:
    ✓ Relatórios Básicos
  general:
    ✓ Gerenciamento de Clientes
    ✓ Gerenciamento de Veículos
    ✓ Gerenciamento de Ordens
  support:
    ✓ Suporte por Email

Profissional
  reporting:
    ✓ Relatórios Básicos
    ✓ Relatórios Avançados
    ✓ Dashboard Customizado
  ...
```

---

## 📊 Estatísticas de Preço

```javascript
const response = await fetch('/api/v1/plans');
const plans = await response.json();

plans.forEach(plan => {
  const monthlyPrice = plan.price / 100;
  const annualPrice = (monthlyPrice * 12) / 100;
  const discountPercentage = ((monthlyPrice * 12 - annualPrice) / (monthlyPrice * 12) * 100).toFixed(2);
  
  console.log(`
    ${plan.name}
    Mensal: R$ ${monthlyPrice.toFixed(2)}
    Anual:  R$ ${(monthlyPrice * 12).toFixed(2)} (R$ ${annualPrice.toFixed(2)} com desconto)
    Desconto: ${discountPercentage}%
  `);
});
```

**Output:**
```
Básico
Mensal: R$ 99.00
Anual:  R$ 1,188.00 (R$ 1,188.00 com desconto)
Desconto: 0.00%

Profissional
Mensal: R$ 299.00
Anual:  R$ 3,588.00 (R$ 3,588.00 com desconto)
Desconto: 0.00%
```
