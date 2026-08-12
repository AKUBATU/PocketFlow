const API_BASE = window.POCKETFLOW_CONFIG?.API_BASE_URL || 'http://127.0.0.1:8000/api';
const tokenKey = 'pocketflow_token';

let state = {
  user: null,
  categories: [],
  transactions: [],
  dashboard: null,
};

const $ = (id) => document.getElementById(id);

function token() { return localStorage.getItem(tokenKey); }
function setToken(value) { localStorage.setItem(tokenKey, value); }
function clearToken() { localStorage.removeItem(tokenKey); }

function rupiah(value) {
  const number = Number(value || 0);
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number);
}

function todayDate() {
  return new Date().toISOString().slice(0, 10);
}

function showToast(message) {
  const toast = $('toast');
  toast.textContent = message;
  toast.classList.remove('hidden');
  setTimeout(() => toast.classList.add('hidden'), 2600);
}

async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  };

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (token()) headers.Authorization = `Bearer ${token()}`;

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    redirect: 'manual',
  });

  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const data = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    let message = 'Request gagal.';

    if (isJson) {
      const firstError = data?.errors ? Object.values(data.errors)?.[0]?.[0] : null;
      message = data?.message || firstError || message;
    } else if (typeof data === 'string' && data.trim()) {
      message = data.slice(0, 180);
    }

    throw new Error(message);
  }

  return data;
}

function showAuth() {
  $('auth-screen').classList.remove('hidden');
  $('app-screen').classList.add('hidden');
}

function showApp() {
  $('auth-screen').classList.add('hidden');
  $('app-screen').classList.remove('hidden');
}

async function boot() {
  $('transaction-date').value = todayDate();
  bindEvents();
  registerServiceWorker();

  if (!token()) {
    showAuth();
    return;
  }

  try {
    await loadInitialData();
    showApp();
  } catch (err) {
    clearToken();
    showAuth();
  }
}

function bindEvents() {
  $('login-tab').addEventListener('click', () => toggleAuthMode('login'));
  $('register-tab').addEventListener('click', () => toggleAuthMode('register'));
  $('login-form').addEventListener('submit', login);
  $('register-form').addEventListener('submit', register);
  $('logout-btn').addEventListener('click', logout);
  $('refresh-btn').addEventListener('click', refreshCurrent);
  $('transaction-form').addEventListener('submit', saveTransaction);
  $('category-form').addEventListener('submit', saveCategory);
  $('ocr-btn').addEventListener('click', runOcr);
  $('parse-text-btn').addEventListener('click', parseOcrText);
  $('reset-form-btn').addEventListener('click', resetTransactionForm);
  $('apply-filter-btn').addEventListener('click', loadTransactions);

  document.querySelectorAll('input[name="tx-type"]').forEach(input => {
    input.addEventListener('change', renderCategorySelect);
  });

  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => switchPage(btn.dataset.page));
  });
}

function toggleAuthMode(mode) {
  const login = mode === 'login';
  $('login-form').classList.toggle('hidden', !login);
  $('register-form').classList.toggle('hidden', login);
  $('login-tab').classList.toggle('active', login);
  $('register-tab').classList.toggle('active', !login);
}

async function login(event) {
  event.preventDefault();
  try {
    const data = await api('/login', {
      method: 'POST',
      body: JSON.stringify({
        email: $('login-email').value,
        password: $('login-password').value,
      })
    });
    setToken(data.token);
    await loadInitialData();
    showApp();
    showToast('Login berhasil.');
  } catch (err) {
    showToast(err.message);
  }
}

async function register(event) {
  event.preventDefault();
  try {
    const data = await api('/register', {
      method: 'POST',
      body: JSON.stringify({
        name: $('register-name').value,
        email: $('register-email').value,
        password: $('register-password').value,
      })
    });
    setToken(data.token);
    await loadInitialData();
    showApp();
    showToast('Akun berhasil dibuat.');
  } catch (err) {
    showToast(err.message);
  }
}

async function logout() {
  try { await api('/logout', { method: 'POST', body: JSON.stringify({}) }); } catch (_) {}
  clearToken();
  showAuth();
}

async function loadInitialData() {
  const [user, categories] = await Promise.all([
    api('/me'),
    api('/categories'),
  ]);
  state.user = user;
  state.categories = categories;
  renderProfile();
  renderCategorySelect();
  renderCategoryList();
  await Promise.all([loadDashboard(), loadTransactions()]);
}

async function refreshCurrent() {
  try {
    await loadInitialData();
    showToast('Data diperbarui.');
  } catch (err) {
    showToast(err.message);
  }
}

async function loadDashboard() {
  state.dashboard = await api('/dashboard');
  renderDashboard();
}

function renderDashboard() {
  const d = state.dashboard || {};
  $('balance-value').textContent = rupiah(d.balance);
  $('income-value').textContent = rupiah(d.monthly_income);
  $('expense-value').textContent = rupiah(d.monthly_expense);
  $('remaining-value').textContent = rupiah(d.monthly_remaining);
  $('condition-title').textContent = d.condition?.title || 'Ringkasan Keuangan';
  $('condition-message').textContent = d.condition?.message || 'Belum ada data.';
  $('condition-badge').textContent = d.condition?.status || 'neutral';

  if (d.top_expense_category) {
    $('top-category-text').textContent = `Kategori pengeluaran terbesar: ${d.top_expense_category.name} (${rupiah(d.top_expense_category.total)}).`;
  } else {
    $('top-category-text').textContent = '';
  }

  renderTransactionList($('latest-list'), d.latest_transactions || []);
}

async function loadTransactions() {
  const params = new URLSearchParams();
  if ($('filter-type').value) params.append('type', $('filter-type').value);
  if ($('filter-from').value) params.append('date_from', $('filter-from').value);
  if ($('filter-to').value) params.append('date_to', $('filter-to').value);
  if ($('filter-q').value) params.append('q', $('filter-q').value);
  params.append('per_page', '50');

  const data = await api(`/transactions?${params.toString()}`);
  state.transactions = data.data || [];
  renderTransactionList($('history-list'), state.transactions, true);
}

function renderTransactionList(container, items, withActions = false) {
  container.innerHTML = '';

  if (!items.length) {
    container.innerHTML = '<div class="info-card muted">Belum ada transaksi.</div>';
    return;
  }

  items.forEach(item => {
    const isIncome = item.type === 'income';
    const row = document.createElement('article');
    row.className = 'transaction-item';
    row.innerHTML = `
      <div>
        <div class="transaction-title">
          <span>${item.category?.icon || '🧾'}</span>
          <span>${escapeHtml(item.merchant || item.category?.name || 'Transaksi')}</span>
        </div>
        <div class="transaction-meta">
          ${item.transaction_date || ''} ${item.transaction_time ? item.transaction_time.slice(0,5) : ''} • ${escapeHtml(item.category?.name || 'Tanpa kategori')}
        </div>
        ${item.note ? `<div class="transaction-meta">${escapeHtml(item.note)}</div>` : ''}
        ${withActions ? `<div class="item-actions"><button data-edit="${item.id}">Edit</button><button class="delete" data-delete="${item.id}">Hapus</button></div>` : ''}
      </div>
      <div class="${isIncome ? 'amount-income' : 'amount-expense'}">${isIncome ? '+' : '-'} ${rupiah(item.amount)}</div>
    `;
    container.appendChild(row);
  });

  if (withActions) {
    container.querySelectorAll('[data-edit]').forEach(btn => btn.addEventListener('click', () => editTransaction(Number(btn.dataset.edit))));
    container.querySelectorAll('[data-delete]').forEach(btn => btn.addEventListener('click', () => deleteTransaction(Number(btn.dataset.delete))));
  }
}

function renderCategorySelect() {
  const type = document.querySelector('input[name="tx-type"]:checked')?.value || 'expense';
  const select = $('category-id');
  select.innerHTML = '<option value="">Tanpa Kategori</option>';
  state.categories.filter(c => c.type === type).forEach(c => {
    const option = document.createElement('option');
    option.value = c.id;
    option.textContent = `${c.icon || ''} ${c.name}`.trim();
    select.appendChild(option);
  });
}

function renderCategoryList() {
  const wrapper = $('category-list');
  wrapper.innerHTML = '';
  state.categories.forEach(c => {
    const div = document.createElement('div');
    div.className = 'category-pill';
    div.innerHTML = `<strong>${c.icon || ''} ${escapeHtml(c.name)}</strong><span>${c.type === 'income' ? 'Pemasukan' : 'Pengeluaran'}${c.is_default ? ' • bawaan' : ''}</span>`;
    wrapper.appendChild(div);
  });
}

function renderProfile() {
  $('profile-name').textContent = state.user?.name || 'User';
  $('profile-email').textContent = state.user?.email || '';
}

async function saveTransaction(event) {
  event.preventDefault();

  const id = $('transaction-id').value;
  const formData = new FormData();
  formData.append('type', document.querySelector('input[name="tx-type"]:checked').value);
  formData.append('amount', $('amount').value);
  formData.append('category_id', $('category-id').value);
  formData.append('merchant', $('merchant').value);
  formData.append('transaction_date', $('transaction-date').value);
  formData.append('transaction_time', $('transaction-time').value);
  formData.append('note', $('note').value);
  formData.append('ocr_text', $('ocr-text').value);
  formData.append('source', $('proof-image').files[0] ? 'photo' : 'manual');

  if ($('proof-image').files[0]) {
    formData.append('proof_image', $('proof-image').files[0]);
  }

  try {
    if (id) {
      await api(`/transactions/${id}`, { method: 'POST', body: formData, headers: {} });
      showToast('Transaksi diperbarui.');
    } else {
      await api('/transactions', { method: 'POST', body: formData, headers: {} });
      showToast('Transaksi disimpan.');
    }

    resetTransactionForm();
    await Promise.all([loadDashboard(), loadTransactions()]);
    switchPage('history');
  } catch (err) {
    showToast(err.message);
  }
}

function editTransaction(id) {
  const item = state.transactions.find(t => t.id === id);
  if (!item) return;

  $('transaction-id').value = item.id;
  document.querySelector(`input[name="tx-type"][value="${item.type}"]`).checked = true;
  renderCategorySelect();
  $('amount').value = Number(item.amount);
  $('category-id').value = item.category_id || '';
  $('merchant').value = item.merchant || '';
  $('transaction-date').value = item.transaction_date || todayDate();
  $('transaction-time').value = item.transaction_time ? item.transaction_time.slice(0, 5) : '';
  $('note').value = item.note || '';
  $('ocr-text').value = item.ocr_text || '';
  $('form-title').textContent = 'Edit Transaksi';
  switchPage('add');
}

async function deleteTransaction(id) {
  if (!confirm('Hapus transaksi ini?')) return;
  try {
    await api(`/transactions/${id}`, { method: 'DELETE' });
    await Promise.all([loadDashboard(), loadTransactions()]);
    showToast('Transaksi dihapus.');
  } catch (err) {
    showToast(err.message);
  }
}

function resetTransactionForm() {
  $('transaction-form').reset();
  $('transaction-id').value = '';
  $('ocr-text').value = '';
  $('proof-image').value = '';
  $('ocr-status').textContent = '';
  $('transaction-date').value = todayDate();
  $('form-title').textContent = 'Tambah Transaksi';
  renderCategorySelect();
}

async function runOcr() {
  const file = $('proof-image').files[0];
  if (!file) {
    showToast('Pilih foto dulu.');
    return;
  }

  if (!window.Tesseract) {
    showToast('Library OCR belum ter-load. Cek koneksi internet.');
    return;
  }

  $('ocr-status').textContent = 'Membaca foto... ini bisa beberapa detik.';

  try {
    const result = await Tesseract.recognize(file, 'eng+ind', {
      logger: m => {
        if (m.status) $('ocr-status').textContent = `${m.status} ${m.progress ? Math.round(m.progress * 100) + '%' : ''}`;
      }
    });

    $('ocr-text').value = result.data.text || '';
    $('ocr-status').textContent = 'OCR selesai. Mengekstrak nominal/tanggal...';
    await parseOcrText();
  } catch (err) {
    $('ocr-status').textContent = '';
    showToast(`OCR gagal: ${err.message}`);
  }
}

async function parseOcrText() {
  const text = $('ocr-text').value.trim();
  if (!text) {
    showToast('Teks OCR masih kosong.');
    return;
  }

  try {
    const parsed = await api('/ocr/parse', {
      method: 'POST',
      body: JSON.stringify({ text })
    });

    if (parsed.amount) $('amount').value = Math.round(parsed.amount);
    if (parsed.transaction_date) $('transaction-date').value = parsed.transaction_date;
    if (parsed.transaction_time) $('transaction-time').value = parsed.transaction_time.slice(0, 5);
    if (parsed.merchant) $('merchant').value = parsed.merchant;
    if (parsed.type_suggestion) {
      document.querySelector(`input[name="tx-type"][value="${parsed.type_suggestion}"]`).checked = true;
      renderCategorySelect();
    }

    $('ocr-status').textContent = 'Data berhasil diambil. Cek lagi sebelum simpan.';
  } catch (err) {
    showToast(err.message);
  }
}

async function saveCategory(event) {
  event.preventDefault();
  try {
    await api('/categories', {
      method: 'POST',
      body: JSON.stringify({
        name: $('new-category-name').value,
        type: $('new-category-type').value,
      })
    });
    $('category-form').reset();
    state.categories = await api('/categories');
    renderCategorySelect();
    renderCategoryList();
    showToast('Kategori ditambahkan.');
  } catch (err) {
    showToast(err.message);
  }
}

function switchPage(name) {
  const titles = { home: 'Dashboard', add: 'Tambah Transaksi', history: 'Riwayat', profile: 'Profil' };
  $('page-title').textContent = titles[name] || 'PocketFlow';

  document.querySelectorAll('.page').forEach(p => p.classList.remove('active-page'));
  $(`${name}-page`).classList.add('active-page');

  document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.page === name));

  if (name === 'history') loadTransactions().catch(err => showToast(err.message));
  if (name === 'home') loadDashboard().catch(err => showToast(err.message));
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>'"]/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  }[char]));
}

function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch(() => {});
  }
}

boot();