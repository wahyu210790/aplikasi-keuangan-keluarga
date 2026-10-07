import React, { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';

export default function AdminDashboard() {
  const { user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  // Tab state synced with location path or default 'overview'
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    if (location.pathname.includes('/admin/users')) setActiveTab('users');
    else if (location.pathname.includes('/admin/plans')) setActiveTab('plans');
    else if (location.pathname.includes('/admin/subscriptions')) setActiveTab('subscriptions');
    else if (location.pathname.includes('/admin/activity-logs')) setActiveTab('logs');
    else if (location.pathname.includes('/admin/settings')) setActiveTab('settings');
    else setActiveTab('overview');
  }, [location.pathname]);

  const changeTab = (tab, path) => {
    setActiveTab(tab);
    navigate(path);
  };

  // State Data
  const [stats, setStats] = useState(null);
  const [households, setHouseholds] = useState([]);
  const [users, setUsers] = useState([]);
  const [plans, setPlans] = useState([]);
  const [subscriptions, setSubscriptions] = useState([]);
  const [activityLogs, setActivityLogs] = useState([]);
  const [settings, setSettings] = useState({});

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);

  // Modals & Form States
  const [resetUser, setResetUser] = useState(null);
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const [planModal, setPlanModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState(null);
  const [planForm, setPlanForm] = useState({
    name: '',
    slug: '',
    description: '',
    price: 0,
    duration_days: 30,
    max_members: '',
    max_accounts: '',
    max_transactions_per_month: '',
    is_active: true,
  });

  // Customer Modal & Form States
  const [customerModal, setCustomerModal] = useState(false);
  const [customerForm, setCustomerForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    household_name: '',
    plan_id: '',
  });
  const [customerLoading, setCustomerLoading] = useState(false);
  const [customerError, setCustomerError] = useState(null);
  const [customerValidationErrors, setCustomerValidationErrors] = useState({});

  const handleCreateCustomer = async (e) => {
    e.preventDefault();
    setCustomerLoading(true);
    setCustomerError(null);
    setCustomerValidationErrors({});
    try {
      await api.post('/admin/customers', customerForm);
      setSuccessMessage(`Customer "${customerForm.name}" dan Household "${customerForm.household_name}" berhasil dibuat.`);
      setCustomerModal(false);
      setCustomerForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        household_name: '',
        plan_id: '',
      });
      fetchData();
    } catch (err) {
      if (err.response && err.response.status === 422) {
        setCustomerValidationErrors(err.response.data?.errors || {});
        setCustomerError(err.response.data?.message || 'Validasi gagal. Periksa kembali form anda.');
      } else {
        setCustomerError(err.response?.data?.message || 'Gagal membuat customer.');
      }
    } finally {
      setCustomerLoading(false);
    }
  };

  const isSuperAdmin = user?.global_role === 'super_admin';

  // Fetch Data according to activeTab
  const fetchData = async () => {
    if (!isSuperAdmin) return;
    setLoading(true);
    setError(null);
    try {
      if (activeTab === 'overview') {
        const [statsRes, householdsRes] = await Promise.all([
          api.get('/admin/stats'),
          api.get('/admin/households'),
        ]);
        setStats(statsRes.data.data);
        setHouseholds(householdsRes.data.data || []);
      } else if (activeTab === 'users') {
        const res = await api.get('/admin/users');
        setUsers(res.data.data || []);
      } else if (activeTab === 'plans') {
        const res = await api.get('/admin/plans');
        setPlans(res.data.plans || []);
      } else if (activeTab === 'subscriptions') {
        const res = await api.get('/admin/subscriptions');
        setSubscriptions(res.data.data || []);
      } else if (activeTab === 'logs') {
        const res = await api.get('/admin/activity-logs');
        setActivityLogs(res.data.data || []);
      } else if (activeTab === 'settings') {
        const res = await api.get('/admin/settings');
        setSettings(res.data.settings || {});
      }
    } catch (err) {
      console.error('Failed to fetch admin data:', err);
      setError('Gagal memuat data admin.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [activeTab, isSuperAdmin]);

  // Handlers for Task 16.6 (Reset Password)
  const handleResetPassword = async (e) => {
    e.preventDefault();
    if (newPassword !== confirmPassword) {
      alert('Konfirmasi password tidak cocok.');
      return;
    }
    try {
      await api.post(`/admin/users/${resetUser.id}/reset-password`, {
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setSuccessMessage(`Password user ${resetUser.email} berhasil di-reset.`);
      setResetUser(null);
      setNewPassword('');
      setConfirmPassword('');
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal reset password user.');
    }
  };

  // Handlers for Task 16.7 (Plan Management)
  const handleSavePlan = async (e) => {
    e.preventDefault();
    try {
      if (editingPlan) {
        await api.patch(`/admin/plans/${editingPlan.id}`, planForm);
        setSuccessMessage('Paket langganan berhasil diperbarui.');
      } else {
        await api.post('/admin/plans', planForm);
        setSuccessMessage('Paket langganan berhasil dibuat.');
      }
      setPlanModal(false);
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menyimpan paket langganan.');
    }
  };

  const handleDeletePlan = async (planId) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus paket ini?')) return;
    try {
      await api.delete(`/admin/plans/${planId}`);
      setSuccessMessage('Paket langganan berhasil dihapus.');
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menghapus paket langganan.');
    }
  };

  // Handlers for Task 16.10 (Admin Settings)
  const handleSaveSettings = async (e) => {
    e.preventDefault();
    try {
      await api.patch('/admin/settings', { settings });
      setSuccessMessage('Pengaturan platform berhasil disimpan.');
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menyimpan pengaturan.');
    }
  };

  if (!isSuperAdmin) {
    return (
      <div className="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-lg shadow-md border-l-4 border-red-500">
        <h2 className="text-xl font-bold text-red-600 mb-2">403 - Akses Ditolak</h2>
        <p className="text-gray-700">Anda tidak memiliki izin Super Admin untuk mengakses halaman ini.</p>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      {/* Header & Tabs */}
      <div className="border-b pb-4 space-y-4">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Super Admin Panel</h1>
            <p className="text-sm text-gray-500">Pusat kontrol operasional SaaS, manajemen user, paket, & audit log</p>
          </div>
          <button
            onClick={fetchData}
            className="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700 transition"
          >
            Refresh Data
          </button>
        </div>

        {/* Navigation Tabs */}
        <div className="flex space-x-2 border-b overflow-x-auto text-sm font-medium">
          <button
            onClick={() => changeTab('overview', '/admin')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'overview' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Ringkasan &amp; Tenant
          </button>
          <button
            onClick={() => changeTab('users', '/admin/users')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'users' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Manajemen User (16.5)
          </button>
          <button
            onClick={() => changeTab('plans', '/admin/plans')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'plans' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Manajemen Paket (16.7)
          </button>
          <button
            onClick={() => changeTab('subscriptions', '/admin/subscriptions')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'subscriptions' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Subscription Tenant (16.8)
          </button>
          <button
            onClick={() => changeTab('logs', '/admin/activity-logs')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'logs' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Global Audit Log (16.9)
          </button>
          <button
            onClick={() => changeTab('settings', '/admin/settings')}
            className={`px-4 py-2 border-b-2 transition ${activeTab === 'settings' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            Pengaturan Platform (16.10)
          </button>
        </div>
      </div>

      {successMessage && (
        <div className="p-3 bg-emerald-50 text-emerald-800 rounded-md text-sm border border-emerald-200 flex justify-between">
          <span>{successMessage}</span>
          <button onClick={() => setSuccessMessage(null)} className="font-bold">&times;</button>
        </div>
      )}

      {error && (
        <div className="p-3 bg-red-50 text-red-800 rounded-md text-sm border border-red-200">
          {error}
        </div>
      )}

      {/* TAB 1: OVERVIEW & TENANTS */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white p-4 rounded-lg border shadow-sm">
              <span className="text-xs text-gray-400 font-semibold uppercase">Total Users</span>
              <p className="text-2xl font-extrabold text-gray-900 mt-1">{stats?.total_users ?? 0}</p>
            </div>
            <div className="bg-white p-4 rounded-lg border shadow-sm">
              <span className="text-xs text-gray-400 font-semibold uppercase">Total Households</span>
              <p className="text-2xl font-extrabold text-indigo-600 mt-1">{stats?.total_households ?? 0}</p>
            </div>
            <div className="bg-white p-4 rounded-lg border shadow-sm">
              <span className="text-xs text-gray-400 font-semibold uppercase">Total Transaksi</span>
              <p className="text-2xl font-extrabold text-gray-900 mt-1">{stats?.total_transactions ?? 0}</p>
            </div>
            <div className="bg-white p-4 rounded-lg border shadow-sm">
              <span className="text-xs text-gray-400 font-semibold uppercase">Volume Transaksi</span>
              <p className="text-2xl font-extrabold text-emerald-600 mt-1">Rp {(stats?.total_volume ?? 0).toLocaleString('id-ID')}</p>
            </div>
          </div>

          <div className="bg-white border rounded-lg p-4 space-y-3">
            <h3 className="font-bold text-gray-800">Daftar Tenant (Household)</h3>
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 border-b">
                <tr>
                  <th className="p-2">ID</th>
                  <th className="p-2">Nama</th>
                  <th className="p-2">Status</th>
                  <th className="p-2">Pemilik</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {households.map((h) => (
                  <tr key={h.id}>
                    <td className="p-2 font-mono">#{h.id}</td>
                    <td className="p-2 font-medium">{h.name}</td>
                    <td className="p-2">
                      <span className={`px-2 py-0.5 text-xs font-bold rounded-full ${h.status === 'suspended' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'}`}>
                        {h.status || 'active'}
                      </span>
                    </td>
                    <td className="p-2 text-xs text-gray-600">{h.owner?.email || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 2: USER MANAGEMENT (16.5 & 16.6) */}
      {activeTab === 'users' && (
        <div className="bg-white border rounded-lg p-4 space-y-4">
          <div className="flex justify-between items-center flex-wrap gap-2">
            <div>
              <h3 className="font-bold text-gray-800">Manajemen Customer &amp; User</h3>
              <p className="text-xs text-gray-500">Kelola akun pengguna, registrasi customer baru, dan reset password</p>
            </div>
            <button
              onClick={() => {
                setCustomerError(null);
                setCustomerValidationErrors({});
                setCustomerForm({ name: '', email: '', password: '', password_confirmation: '', household_name: '', plan_id: '' });
                setCustomerModal(true);
              }}
              className="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700 transition"
            >
              + Tambah Customer
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 border-b text-xs uppercase text-gray-500">
                <tr>
                  <th className="p-2">ID</th>
                  <th className="p-2">Nama</th>
                  <th className="p-2">Email</th>
                  <th className="p-2">Role</th>
                  <th className="p-2">Household</th>
                  <th className="p-2 text-center">Status</th>
                  <th className="p-2 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y text-xs">
                {users.map((u) => (
                  <tr key={u.id} className="hover:bg-gray-50">
                    <td className="p-2 font-mono">#{u.id}</td>
                    <td className="p-2 font-semibold text-gray-900">{u.name}</td>
                    <td className="p-2 text-gray-600">{u.email}</td>
                    <td className="p-2">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${u.global_role === 'super_admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}`}>
                        {u.role_label || u.global_role || 'Customer'}
                      </span>
                    </td>
                    <td className="p-2 font-medium text-gray-800">{u.household_name || '-'}</td>
                    <td className="p-2 text-center">
                      <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full ${u.status === 'suspended' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'}`}>
                        {u.status || 'active'}
                      </span>
                    </td>
                    <td className="p-2 text-center">
                      <button
                        onClick={() => { setResetUser(u); setNewPassword(''); setConfirmPassword(''); }}
                        className="px-2.5 py-1 bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 rounded text-xs font-semibold"
                      >
                        Reset Password
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: PLAN MANAGEMENT (16.7) */}
      {activeTab === 'plans' && (
        <div className="bg-white border rounded-lg p-4 space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="font-bold text-gray-800">Manajemen Paket Langganan (SaaS Plans)</h3>
            <button
              onClick={() => {
                setEditingPlan(null);
                setPlanForm({ name: '', slug: '', description: '', price: 0, duration_days: 30, max_members: '', max_accounts: '', max_transactions_per_month: '', is_active: true });
                setPlanModal(true);
              }}
              className="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700"
            >
              + Tambah Paket Baru
            </button>
          </div>

          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 border-b text-xs text-gray-500">
              <tr>
                <th className="p-2">Nama</th>
                <th className="p-2">Slug</th>
                <th className="p-2">Harga</th>
                <th className="p-2">Durasi</th>
                <th className="p-2 text-center">Active Subs</th>
                <th className="p-2 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y text-xs">
              {plans.map((p) => (
                <tr key={p.id}>
                  <td className="p-2 font-bold text-gray-900">{p.name}</td>
                  <td className="p-2 font-mono text-gray-500">{p.slug}</td>
                  <td className="p-2 font-semibold text-emerald-700">Rp {p.price.toLocaleString('id-ID')}</td>
                  <td className="p-2">{p.duration_days} hari</td>
                  <td className="p-2 text-center font-bold">{p.subscriptions_count ?? 0}</td>
                  <td className="p-2 text-center space-x-2">
                    <button
                      onClick={() => {
                        setEditingPlan(p);
                        setPlanForm({
                          name: p.name,
                          slug: p.slug,
                          description: p.description || '',
                          price: p.price,
                          duration_days: p.duration_days,
                          max_members: p.max_members || '',
                          max_accounts: p.max_accounts || '',
                          max_transactions_per_month: p.max_transactions_per_month || '',
                          is_active: p.is_active,
                        });
                        setPlanModal(true);
                      }}
                      className="px-2 py-1 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleDeletePlan(p.id)}
                      className="px-2 py-1 bg-red-50 text-red-600 hover:bg-red-100 rounded"
                    >
                      Hapus
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 4: SUBSCRIPTION MANAGEMENT (16.8) */}
      {activeTab === 'subscriptions' && (
        <div className="bg-white border rounded-lg p-4 space-y-4">
          <h3 className="font-bold text-gray-800">Manajemen Subscription Tenant</h3>
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 border-b text-xs text-gray-500">
              <tr>
                <th className="p-2">ID</th>
                <th className="p-2">Household</th>
                <th className="p-2">Paket</th>
                <th className="p-2">Status</th>
                <th className="p-2">Berakhir Pada</th>
              </tr>
            </thead>
            <tbody className="divide-y text-xs">
              {subscriptions.map((s) => (
                <tr key={s.id}>
                  <td className="p-2 font-mono">#{s.id}</td>
                  <td className="p-2 font-semibold text-gray-900">{s.household?.name ?? '-'}</td>
                  <td className="p-2 text-indigo-700 font-bold">{s.plan?.name ?? '-'}</td>
                  <td className="p-2">
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${s.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>
                      {s.status}
                    </span>
                  </td>
                  <td className="p-2 text-gray-500">{s.expires_at ? new Date(s.expires_at).toLocaleDateString('id-ID') : 'Selamanya'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 5: GLOBAL ACTIVITY LOG (16.9) */}
      {activeTab === 'logs' && (
        <div className="bg-white border rounded-lg p-4 space-y-4">
          <h3 className="font-bold text-gray-800">System-wide Global Audit Log</h3>
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 border-b text-xs text-gray-500">
              <tr>
                <th className="p-2">ID</th>
                <th className="p-2">Aksi (Action)</th>
                <th className="p-2">User</th>
                <th className="p-2">Household</th>
                <th className="p-2">Deskripsi</th>
                <th className="p-2">Waktu</th>
              </tr>
            </thead>
            <tbody className="divide-y text-xs">
              {activityLogs.map((l) => (
                <tr key={l.id}>
                  <td className="p-2 font-mono">#{l.id}</td>
                  <td className="p-2 font-bold text-indigo-700">{l.action}</td>
                  <td className="p-2">{l.user?.name || '-'}</td>
                  <td className="p-2">{l.household?.name || '-'}</td>
                  <td className="p-2 text-gray-600 truncate max-w-xs">{l.description || '-'}</td>
                  <td className="p-2 text-gray-400">{l.created_at ? new Date(l.created_at).toLocaleString('id-ID') : '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 6: ADMIN SETTINGS (16.10) */}
      {activeTab === 'settings' && (
        <div className="bg-white border rounded-lg p-6 max-w-2xl space-y-4">
          <h3 className="font-bold text-gray-800 border-b pb-2">Global Platform Settings</h3>
          <form onSubmit={handleSaveSettings} className="space-y-4 text-sm">
            <div>
              <label className="block text-xs font-semibold text-gray-600 mb-1">Nama Aplikasi / SaaS</label>
              <input
                type="text"
                value={settings.app_name || ''}
                onChange={(e) => setSettings({ ...settings, app_name: e.target.value })}
                className="w-full border rounded px-3 py-1.5"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-600 mb-1">Email Dukungan (Support Email)</label>
              <input
                type="email"
                value={settings.support_email || ''}
                onChange={(e) => setSettings({ ...settings, support_email: e.target.value })}
                className="w-full border rounded px-3 py-1.5"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-600 mb-1">Mode Pemeliharaan (Maintenance Mode)</label>
              <select
                value={settings.maintenance_mode || 'false'}
                onChange={(e) => setSettings({ ...settings, maintenance_mode: e.target.value })}
                className="w-full border rounded px-3 py-1.5"
              >
                <option value="false">Matikan (SaaS Normal Aktif)</option>
                <option value="true">Aktifkan (Mode Maintenance Platform)</option>
              </select>
            </div>
            <button
              type="submit"
              className="px-4 py-2 bg-indigo-600 text-white rounded text-xs font-bold hover:bg-indigo-700"
            >
              Simpan Pengaturan
            </button>
          </form>
        </div>
      )}

      {/* Reset Password Modal */}
      {resetUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6 space-y-4">
            <h3 className="font-bold text-lg text-gray-900 border-b pb-2">Reset Password User: {resetUser.email}</h3>
            <form onSubmit={handleResetPassword} className="space-y-3 text-sm">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Password Baru</label>
                <input
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  required
                  minLength={8}
                  className="w-full border rounded px-3 py-1.5"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Konfirmasi Password Baru</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  minLength={8}
                  className="w-full border rounded px-3 py-1.5"
                />
              </div>
              <div className="flex justify-end space-x-2 border-t pt-3">
                <button type="button" onClick={() => setResetUser(null)} className="px-3 py-1.5 bg-gray-200 rounded text-xs">Batal</button>
                <button type="submit" className="px-3 py-1.5 bg-red-600 text-white rounded text-xs font-bold hover:bg-red-700">Force Reset</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Plan Form Modal */}
      {planModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-lg w-full p-6 space-y-4">
            <h3 className="font-bold text-lg text-gray-900 border-b pb-2">{editingPlan ? 'Edit Paket Langganan' : 'Tambah Paket Langganan Baru'}</h3>
            <form onSubmit={handleSavePlan} className="space-y-3 text-sm">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Nama Paket</label>
                  <input
                    type="text"
                    value={planForm.name}
                    onChange={(e) => setPlanForm({ ...planForm, name: e.target.value })}
                    required
                    className="w-full border rounded px-3 py-1.5"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Slug (Unik)</label>
                  <input
                    type="text"
                    value={planForm.slug}
                    onChange={(e) => setPlanForm({ ...planForm, slug: e.target.value })}
                    required
                    className="w-full border rounded px-3 py-1.5 font-mono"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Harga (Rp)</label>
                  <input
                    type="number"
                    value={planForm.price}
                    onChange={(e) => setPlanForm({ ...planForm, price: e.target.value })}
                    required
                    className="w-full border rounded px-3 py-1.5"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Durasi (Hari)</label>
                  <input
                    type="number"
                    value={planForm.duration_days}
                    onChange={(e) => setPlanForm({ ...planForm, duration_days: e.target.value })}
                    required
                    className="w-full border rounded px-3 py-1.5"
                  />
                </div>
              </div>
              <div className="flex justify-end space-x-2 border-t pt-3">
                <button type="button" onClick={() => setPlanModal(false)} className="px-3 py-1.5 bg-gray-200 rounded text-xs">Batal</button>
                <button type="submit" className="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-bold hover:bg-indigo-700">Simpan Paket</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Customer Form Modal */}
      {customerModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6 space-y-4 shadow-xl">
            <div className="flex justify-between items-center border-b pb-2">
              <h3 className="font-bold text-lg text-gray-900">+ Tambah Customer &amp; Household Baru</h3>
              <button
                type="button"
                onClick={() => setCustomerModal(false)}
                className="text-gray-400 hover:text-gray-600 text-xl font-bold"
              >
                &times;
              </button>
            </div>

            {customerError && (
              <div className="bg-red-100 border border-red-300 text-red-700 text-xs p-3 rounded">
                {customerError}
              </div>
            )}

            <form onSubmit={handleCreateCustomer} className="space-y-3 text-sm">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Nama Customer *</label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Wahyu Santoso"
                  className="w-full border rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  value={customerForm.name}
                  onChange={(e) => setCustomerForm({ ...customerForm, name: e.target.value })}
                  disabled={customerLoading}
                />
                {customerValidationErrors.name && (
                  <p className="text-red-500 text-[11px] mt-0.5">{customerValidationErrors.name[0]}</p>
                )}
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Email Customer *</label>
                <input
                  type="email"
                  required
                  placeholder="Contoh: wahyu@example.com"
                  className="w-full border rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  value={customerForm.email}
                  onChange={(e) => setCustomerForm({ ...customerForm, email: e.target.value })}
                  disabled={customerLoading}
                />
                {customerValidationErrors.email && (
                  <p className="text-red-500 text-[11px] mt-0.5">{customerValidationErrors.email[0]}</p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Password Sementara *</label>
                  <input
                    type="password"
                    required
                    placeholder="••••••••"
                    className="w-full border rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={customerForm.password}
                    onChange={(e) => setCustomerForm({ ...customerForm, password: e.target.value })}
                    disabled={customerLoading}
                  />
                  {customerValidationErrors.password && (
                    <p className="text-red-500 text-[11px] mt-0.5">{customerValidationErrors.password[0]}</p>
                  )}
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Konfirmasi Password *</label>
                  <input
                    type="password"
                    required
                    placeholder="••••••••"
                    className="w-full border rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={customerForm.password_confirmation}
                    onChange={(e) => setCustomerForm({ ...customerForm, password_confirmation: e.target.value })}
                    disabled={customerLoading}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Nama Household *</label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Keluarga Wahyu"
                  className="w-full border rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  value={customerForm.household_name}
                  onChange={(e) => setCustomerForm({ ...customerForm, household_name: e.target.value })}
                  disabled={customerLoading}
                />
                {customerValidationErrors.household_name && (
                  <p className="text-red-500 text-[11px] mt-0.5">{customerValidationErrors.household_name[0]}</p>
                )}
              </div>

              <div className="flex justify-end space-x-2 border-t pt-3">
                <button
                  type="button"
                  onClick={() => setCustomerModal(false)}
                  className="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300"
                  disabled={customerLoading}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={customerLoading}
                  className="px-4 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 disabled:opacity-50"
                >
                  {customerLoading ? 'Memproses...' : 'Buat Customer & Household'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
