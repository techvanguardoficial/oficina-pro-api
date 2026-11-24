# Pagamentos de OS (abatimento) e OS com pagamento pendente

A OS pode ser paga em partes. Exemplo: OS de R$ 1000, o cliente paga R$ 500 hoje e combina o restante para o dia 30.

**Receita:** cada pagamento conta como receita **na data em que foi pago** (`paid_at`), não mais pelo total na conclusão.
As OS concluídas antes da mudança ganharam um pagamento integral na data de conclusão (nota "Pagamento integral (registro automático do histórico)"), então o histórico não mudou.

> Concluir uma OS **não** registra pagamento. OS concluída sem pagamento aparece em "Pendente pagamento" e só vira receita quando o pagamento for lançado. Sugestão para o front: ao concluir, oferecer "Quitar agora" (POST do restante).

Todas as rotas abaixo ficam em `/api/v1`, com `auth:sanctum` e assinatura ativa.

## Campos novos em toda OS

Vêm em `GET /order-services`, `/order-services/list-all`, `/order-services/{id}`, na criação e na edição:

| Campo | Tipo | Descrição |
|---|---|---|
| `total` | number | Peças + serviços |
| `paid_total` | number | Soma dos pagamentos |
| `remaining` | number | Quanto falta (nunca negativo) |
| `payment_status` | string | `pendente` (nada pago), `parcial`, `pago` ou `sem_valor` (OS sem itens) |
| `payment_due_date` | `Y-m-d` ou null | Data combinada para pagar o restante |
| `payments` | array | Pagamentos lançados (ver abaixo) |

`payment_due_date` também pode ser enviado em `POST /order-services` e `PUT /order-services/{id}` (`null` limpa).

Editar a OS deixando o total abaixo do valor já pago responde **422**: remova um pagamento antes.

## Lançar pagamento

`POST /order-services/{id}/payments`

```json
{
  "amount": 500,
  "paid_at": "2026-11-24",
  "method": "pix",
  "notes": "Entrada",
  "payment_due_date": "2026-11-30"
}
```

- `amount`: obrigatório, > 0 e no máximo o restante.
- `paid_at`: opcional (padrão hoje), não pode ser futuro.
- `method`: opcional: `dinheiro`, `pix`, `cartao_credito`, `cartao_debito`, `boleto`, `transferencia`, `outro`.
- `payment_due_date`: opcional, data combinada para o restante. Some sozinha quando a OS é quitada.
- OS cancelada responde 422.

Resposta `201`:

```json
{
  "message": "Pagamento registrado",
  "payment": { "id": 10, "amount": 500, "paid_at": "2026-11-24", "method": "pix", "notes": "Entrada", "user_id": 3 },
  "order_service_id": 42,
  "total": 1000,
  "paid_total": 500,
  "remaining": 500,
  "payment_status": "parcial",
  "payment_due_date": "2026-11-30",
  "payments": [ { "id": 10, "amount": 500, "paid_at": "2026-11-24", "method": "pix", "user": { "id": 3, "name": "Fulano" } } ]
}
```

## Ver pagamentos

`GET /order-services/{id}/payments`: mesmo formato acima, sem `message` e `payment`.

## Estornar pagamento

`DELETE /order-services/{id}/payments/{paymentId}`: remove o lançamento e devolve o resumo atualizado. O valor sai da receita.

## OS com pagamento pendente

`GET /order-services/pending-payment?overdue=1&page=1`

Entram as OS **não canceladas** que estão **concluídas** ou **já têm algum pagamento (sinal)** e ainda têm saldo.
Ordem: vencimento mais próximo primeiro, sem vencimento por último. `overdue=1` traz só as vencidas (`payment_due_date` anterior a hoje).

Resposta: paginação padrão do Laravel (`data`, `total`, `current_page`...) com as OS completas (veículo, cliente, status, pagamentos e os campos de saldo) e mais:

```json
"summary": { "orders": 3, "receivable": 2500, "overdueOrders": 1, "overdue": 600 }
```

`summary` é sempre de todas as pendentes, sem o filtro `overdue`.

## Dashboard

`GET /dashboard/stats` ganhou `pendingPayment` com o mesmo formato de `summary` (card "A receber").

## Financeiro

- `GET /transactions`, `/transactions/period`, `/transactions/list`: a receita de OS agora é a soma dos pagamentos pela data paga.
- `/transactions/list`: cada pagamento vira uma linha `source: "order"`, `id: "payment_{id}"`, com `order_id`.
- `/transactions/period`: `breakdown.incomes_from_order_payments` (o campo antigo `incomes_from_completed_orders` continua, com o mesmo valor).

## Portal do cliente

O histórico de OS do portal ganhou `paid`, `remaining` e `payment_status`.
