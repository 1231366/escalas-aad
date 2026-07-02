# ADR-0001 — Stack: monólito Laravel + microserviço solver Python

**Estado:** aceite · **Data:** 2026-07-02

## Contexto

O projeto precisa de: auth com papéis, convites por link, CRUD de perfis/regras, geração de escalas (problema NP-hard), trocas validadas, notificações email+in-app, export Excel, feeds iCal — e o utilizador quer **deploy num único sítio**, custo baixo, e transição futura para app mobile. Foi considerada uma alternativa TypeScript ponta-a-ponta (Next.js + NestJS + monorepo Turborepo).

## Decisão

- **Monólito Laravel 11 (PHP 8.3)** — MVC clássico: Controllers, Form Requests, Policies, Eloquent Models, Jobs, Notifications.
- **Frontend Inertia.js + React 18 + TypeScript + Tailwind + shadcn/ui + Framer Motion** — SPA moderna dentro do mesmo repo/deploy, sem API intermédia para a web.
- **Auth Laravel Sanctum** — sessão para a web Inertia; tokens em `routes/api.php` para a futura app mobile (Expo/React Native) consumir a mesma lógica.
- **Solver: Python 3.12 + FastAPI + Google OR-Tools CP-SAT**, serviço separado (ver ADR-0002).
- **Filas/Scheduler:** Laravel Queues (driver Redis) + Scheduler. **Realtime:** Laravel Reverb. **Excel:** maatwebsite/excel. **Email:** Resend (ou Brevo) via Laravel Notifications.
- **BD:** PostgreSQL no Supabase (+ Storage para ficheiros exportados). **Deploy:** Railway — serviços `app` (Laravel), `solver` (Python), `redis`; domínio próprio via DNS.

## Consequências

- 2 serviços em vez de 4 → alinha com "tudo no mesmo sítio" e reduz custo/ops.
- MVC explícito e reconhecível → bom artefacto de portfólio.
- A app mobile futura é só mais um cliente da API Sanctum; nenhuma lógica é reescrita.
- Trade-off aceite: perde-se a partilha de tipos TS entre backend e frontend do monorepo TS; mitigado por DTOs/Resources tipados e geração de tipos (ex.: `laravel-typescript-transformer`) se se justificar.
