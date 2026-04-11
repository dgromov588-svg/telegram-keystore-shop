<?php

declare(strict_types=1);

namespace App\Web;

use App\Config;
use App\Repository\JobRepository;
use App\Repository\OrderRepository;
use App\Support\Html;
use App\Support\HttpResponse;
use App\Support\SessionAuth;

final class AdminDashboardController
{
    public function __construct(
        private readonly SessionAuth $auth,
        private readonly OrderRepository $orders,
        private readonly JobRepository $jobs,
        private readonly Config $config,
    ) {
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($this->auth->attempt($username, $password)) {
                HttpResponse::redirect($this->url('admin-dashboard'));
            }

            HttpResponse::html($this->renderLogin('Неверный логин или пароль'), 401);
            return;
        }

        HttpResponse::html($this->renderLogin());
    }

    public function logout(): void
    {
        $this->auth->logout();
        HttpResponse::redirect($this->url('admin-login'));
    }

    public function dashboard(): void
    {
        $this->guard();

        $html = $this->layout(
            'Admin Dashboard',
            $this->renderDashboardBody()
        );

        HttpResponse::html($html);
    }

    public function retryOrder(): void
    {
        $this->guard();
        $this->ensurePost();
        if (!$this->auth->validateCsrf($_POST['_csrf'] ?? null)) {
            HttpResponse::html('Invalid CSRF token', 419);
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        if ($orderId > 0) {
            $this->jobs->retryFailedByOrderId('deliver_order', $orderId);
        }

        HttpResponse::redirect($this->url('admin-dashboard'));
    }

    public function retryJob(): void
    {
        $this->guard();
        $this->ensurePost();
        if (!$this->auth->validateCsrf($_POST['_csrf'] ?? null)) {
            HttpResponse::html('Invalid CSRF token', 419);
            return;
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $this->jobs->retryFailedByJobId($jobId);
        }

        HttpResponse::redirect($this->url('admin-dashboard'));
    }

    private function guard(): void
    {
        if (!$this->auth->check()) {
            HttpResponse::redirect($this->url('admin-login'));
        }
    }

    private function ensurePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            HttpResponse::html('Method Not Allowed', 405);
            exit;
        }
    }

    private function renderLogin(?string $error = null): string
    {
        $errorHtml = $error ? '<div class="alert">' . Html::e($error) . '</div>' : '';

        return '<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<style>
body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{width:360px;background:#111827;padding:24px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
input{width:100%;padding:12px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff;margin-bottom:12px;box-sizing:border-box}
button{width:100%;padding:12px;border:none;border-radius:10px;background:#2563eb;color:#fff;font-weight:bold;cursor:pointer}
.alert{background:#7f1d1d;padding:10px;border-radius:10px;margin-bottom:12px}
</style>
</head>
<body>
<div class="card">
<h2>Admin dashboard</h2>'
. $errorHtml .
'<form method="post" action="' . Html::e($this->url('admin-login')) . '">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>
</div>
</body>
</html>';
    }

    private function renderDashboardBody(): string
    {
        $csrf = $this->auth->csrfToken();
        $orderStats = $this->orders->countByStatus();
        $jobStats = $this->jobs->stats();
        $recentOrders = $this->orders->recent(50);
        $failedJobs = $this->jobs->failedJobs(20);
        $recentJobs = $this->jobs->recent(50);

        $statsCards = '';
        foreach (['awaiting_payment','paid','delivery_queued','delivering','delivered'] as $status) {
            $statsCards .= '<div class="stat"><div class="label">' . Html::e($status) . '</div><div class="value">' . (int) ($orderStats[$status] ?? 0) . '</div></div>';
        }
        foreach (['queued','retry','processing','done','failed'] as $status) {
            $statsCards .= '<div class="stat"><div class="label">job:' . Html::e($status) . '</div><div class="value">' . (int) ($jobStats[$status] ?? 0) . '</div></div>';
        }

        $ordersRows = '';
        foreach ($recentOrders as $order) {
            $ordersRows .= '<tr>'
                . '<td>#' . (int) $order['id'] . '</td>'
                . '<td>' . Html::e((string) $order['status']) . '</td>'
                . '<td>' . Html::e((string) ($order['payment_status'] ?? '')) . '</td>'
                . '<td>' . Html::e((string) ($order['provider_status'] ?? '')) . '</td>'
                . '<td>' . Html::e(number_format(((int) $order['total_cents']) / 100, 2, '.', ' ')) . '</td>'
                . '<td>' . Html::e((string) $order['created_at']) . '</td>'
                . '</tr>';
        }

        $failedRows = '';
        foreach ($failedJobs as $job) {
            $failedRows .= '<tr>'
                . '<td>#' . (int) $job['id'] . '</td>'
                . '<td>' . Html::e((string) $job['type']) . '</td>'
                . '<td>' . (int) $job['order_id'] . '</td>'
                . '<td>' . (int) $job['attempts'] . '/' . (int) $job['max_attempts'] . '</td>'
                . '<td>' . Html::e((string) ($job['last_error'] ?? '')) . '</td>'
                . '<td>'
                . '<form method="post" action="' . Html::e($this->url('admin-retry-job')) . '">'
                . '<input type="hidden" name="_csrf" value="' . Html::e($csrf) . '">'
                . '<input type="hidden" name="job_id" value="' . (int) $job['id'] . '">'
                . '<button class="btn small" type="submit">Retry job</button>'
                . '</form>'
                . '<form method="post" action="' . Html::e($this->url('admin-retry-order')) . '">'
                . '<input type="hidden" name="_csrf" value="' . Html::e($csrf) . '">'
                . '<input type="hidden" name="order_id" value="' . (int) $job['order_id'] . '">'
                . '<button class="btn ghost small" type="submit">Retry order</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $jobRows = '';
        foreach ($recentJobs as $job) {
            $jobRows .= '<tr>'
                . '<td>#' . (int) $job['id'] . '</td>'
                . '<td>' . Html::e((string) $job['type']) . '</td>'
                . '<td>' . (int) $job['order_id'] . '</td>'
                . '<td>' . Html::e((string) $job['status']) . '</td>'
                . '<td>' . (int) $job['attempts'] . '</td>'
                . '<td>' . Html::e((string) ($job['updated_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '
<div class="topbar">
  <div>
    <h1>Admin dashboard</h1>
    <p>MySQL · Telegram · Cryptomus · Queue worker</p>
  </div>
  <div class="topbar-actions">
    <a class="btn ghost" href="' . Html::e($this->url('health')) . '" target="_blank">health</a>
    <a class="btn ghost" href="' . Html::e($this->url('worker-status')) . '" target="_blank">worker-status</a>
    <a class="btn" href="' . Html::e($this->url('admin-logout')) . '">Logout</a>
  </div>
</div>

<div class="stats-grid">' . $statsCards . '</div>

<div class="grid-two">
  <section class="panel">
    <h2>Failed jobs</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Type</th><th>Order</th><th>Attempts</th><th>Error</th><th>Actions</th></tr></thead>
      <tbody>' . $failedRows . '</tbody>
    </table></div>
  </section>

  <section class="panel">
    <h2>Recent jobs</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Type</th><th>Order</th><th>Status</th><th>Attempts</th><th>Updated</th></tr></thead>
      <tbody>' . $jobRows . '</tbody>
    </table></div>
  </section>
</div>

<section class="panel">
  <h2>Recent orders</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Status</th><th>Payment</th><th>Provider</th><th>Total</th><th>Created</th></tr></thead>
    <tbody>' . $ordersRows . '</tbody>
  </table></div>
</section>';
    }

    private function layout(string $title, string $body): string
    {
        return '<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . Html::e($title) . '</title>
<style>
body{font-family:Inter,Arial,sans-serif;background:#0b1020;color:#e5e7eb;margin:0;padding:24px}
a{text-decoration:none}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.topbar h1{margin:0}.topbar p{margin:4px 0 0;color:#94a3b8}
.topbar-actions{display:flex;gap:10px;align-items:center}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.stat,.panel{background:#111827;border:1px solid #1f2937;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.2)}
.stat{padding:16px}.label{color:#94a3b8;font-size:14px}.value{font-size:28px;font-weight:700;margin-top:6px}
.panel{padding:18px;margin-bottom:24px}.panel h2{margin-top:0}
.grid-two{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;border-bottom:1px solid #1f2937;text-align:left;vertical-align:top}th{color:#94a3b8;font-weight:600}
.btn{display:inline-block;background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}.btn.ghost{background:#1f2937}.btn.small{padding:8px 10px;font-size:12px;margin-bottom:6px}
form{display:inline-block;margin-right:6px}
@media (max-width: 960px){.grid-two{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body>' . $body . '</body>
</html>';
    }

    private function url(string $action): string
    {
        return $this->config->appUrl . '?action=' . urlencode($action);
    }
}
