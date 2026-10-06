import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

function Modal({ isOpen, title, onClose, children }) {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex justify-between items-center mb-4 pb-2 border-b">
          <h2 className="text-xl font-semibold text-gray-800">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">
            &times;
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

export default function RecurringTransactions() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  const [recurringList, setRecurringList] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);
  const [processingId, setProcessingId] = useState(null);

  const [accounts, setAccounts] = useState([]);
  const [categories, setCategories] = useState([]);

  // Create Modal
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [type, setType] = useState('expense');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [frequency, setFrequency] = useState('monthly');
  const [startDate, setStartDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [endDate, setEndDate] = useState('');
  const [accountId, setAccountId] = useState('');
  const [toAccountId, setToAccountId] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [createErrors, setCreateErrors] = useState({});
  const [createSubmitting, setCreateSubmitting] = useState(false);

  // Edit Modal
  const [editItem, setEditItem] = useState(null);
  const [editErrors, setEditErrors] = useState({});
  const [editSubmitting, setEditSubmitting] = useState(false);

  // Delete Modal
  const [deleteItem, setDeleteItem] = useState(null);
  const [deleteSubmitting, setDeleteSubmitting] = useState(false);

  const requestIdRef = useRef(0);

  const formatRupiah = (val) => {
    const num = Number(val);
    if (isNaN(num)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
  };

  const fetchOptions = async (householdId) => {
    try {
      const [accRes, catRes] = await Promise.all([
        api.get(`/households/${householdId}/accounts`),
        api.get(`/households/${householdId}/categories`)
      ]);
      setAccounts(accRes.data.data || accRes.data.accounts || accRes.data || []);
      setCategories(catRes.data.data || catRes.data.categories || catRes.data || []);
    } catch (err) {
      console.error('Failed to load accounts/categories', err);
    }
  };

  const fetchRecurring = async () => {
    if (!activeHouseholdId) return;
    const currentReqId = ++requestIdRef.current;
    setLoading(true);
    setError(null);

    try {
      const response = await api.get(`/households/${activeHouseholdId}/recurring-transactions`);
      if (currentReqId === requestIdRef.current) {
        setRecurringList(response.data.recurring_transactions || response.data.data || response.data || []);
        setLoading(false);
      }
    } catch (err) {
      if (currentReqId === requestIdRef.current) {
        setError(err.response?.data?.message || 'Gagal memuat transaksi rutin');
        setLoading(false);
      }
    }
  };

  useEffect(() => {
    if (activeHouseholdId) {
      fetchRecurring();
      fetchOptions(activeHouseholdId);
    }
  }, [activeHouseholdId]);

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    setCreateErrors({});
    setCreateSubmitting(true);

    try {
      const payload = {
        type,
        amount: parseFloat(amount),
        description: description || null,
        frequency,
        start_date: startDate,
        end_date: endDate || null,
        account_id: parseInt(accountId, 10),
        to_account_id: type === 'transfer' && toAccountId ? parseInt(toAccountId, 10) : null,
        category_id: type !== 'transfer' && categoryId ? parseInt(categoryId, 10) : null,
        is_active: isActive
      };

      await api.post(`/households/${activeHouseholdId}/recurring-transactions`, payload);
      setSuccessMessage('Transaksi rutin berhasil ditambahkan');
      setIsCreateOpen(false);
      resetCreateForm();
      fetchRecurring();
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setCreateErrors(err.response.data.errors);
      } else {
        setError(err.response?.data?.message || 'Gagal membuat transaksi rutin');
      }
    } finally {
      setCreateSubmitting(false);
    }
  };

  const resetCreateForm = () => {
    setType('expense');
    setAmount('');
    setDescription('');
    setFrequency('monthly');
    setStartDate(new Date().toISOString().slice(0, 10));
    setEndDate('');
    setAccountId('');
    setToAccountId('');
    setCategoryId('');
    setIsActive(true);
    setCreateErrors({});
  };

  const handleProcess = async (id) => {
    setProcessingId(id);
    setError(null);
    try {
      await api.post(`/households/${activeHouseholdId}/recurring-transactions/${id}/process`);
      setSuccessMessage('Transaksi berhasil diproses & dicatat!');
      fetchRecurring();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memproses transaksi rutin');
    } finally {
      setProcessingId(null);
    }
  };

  const handleDelete = async () => {
    if (!deleteItem) return;
    setDeleteSubmitting(true);
    try {
      await api.delete(`/households/${activeHouseholdId}/recurring-transactions/${deleteItem.id}`);
      setSuccessMessage('Transaksi rutin berhasil dihapus');
      setDeleteItem(null);
      fetchRecurring();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menghapus transaksi rutin');
    } finally {
      setDeleteSubmitting(false);
    }
  };

  if (contextLoading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/4"></div>
          <div className="h-48 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Transaksi Rutin</h1>
          <p className="text-gray-600 text-sm">Kelola jadwal transaksi otomatis bulanan, mingguan, atau harian</p>
        </div>
        <Button onClick={() => setIsCreateOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white">
          + Tambah Transaksi Rutin
        </Button>
      </div>

      {successMessage && (
        <Alert type="success" onClose={() => setSuccessMessage(null)}>
          {successMessage}
        </Alert>
      )}

      {error && (
        <Alert type="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      <Card>
        {loading ? (
          <div className="p-8 text-center text-gray-500">Memuat data transaksi rutin...</div>
        ) : recurringList.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            Belum ada transaksi rutin yang terdaftar. Klik "+ Tambah Transaksi Rutin" untuk membuat baru.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  <th className="py-3 px-4">Deskripsi</th>
                  <th className="py-3 px-4">Tipe</th>
                  <th className="py-3 px-4">Frekuensi</th>
                  <th className="py-3 px-4">Jumlah</th>
                  <th className="py-3 px-4">Akun</th>
                  <th className="py-3 px-4">Terakhir Diproses</th>
                  <th className="py-3 px-4 text-center">Status</th>
                  <th className="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y text-sm">
                {recurringList.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50">
                    <td className="py-3 px-4 font-medium text-gray-900">{item.description || '-'}</td>
                    <td className="py-3 px-4">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${
                        item.type === 'income' ? 'bg-green-100 text-green-800' :
                        item.type === 'expense' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'
                      }`}>
                        {item.type}
                      </span>
                    </td>
                    <td className="py-3 px-4 capitalize text-gray-700">{item.frequency}</td>
                    <td className="py-3 px-4 font-semibold text-gray-900">{formatRupiah(item.amount)}</td>
                    <td className="py-3 px-4 text-gray-600">{item.account?.name || '-'}</td>
                    <td className="py-3 px-4 text-gray-500 text-xs">{item.last_generated_at ? new Date(item.last_generated_at).toLocaleDateString('id-ID') : 'Belum pernah'}</td>
                    <td className="py-3 px-4 text-center">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>
                        {item.is_active ? 'Aktif' : 'Non-Aktif'}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right space-x-2">
                      <button
                        onClick={() => handleProcess(item.id)}
                        disabled={processingId === item.id}
                        className="px-3 py-1 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded text-xs font-semibold disabled:opacity-50"
                      >
                        {processingId === item.id ? 'Memproses...' : 'Proses Sekarang'}
                      </button>
                      <button
                        onClick={() => setDeleteItem(item)}
                        className="px-2 py-1 text-red-600 hover:text-red-800 text-xs font-semibold"
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
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateOpen} title="Tambah Transaksi Rutin" onClose={() => setIsCreateOpen(false)}>
        <form onSubmit={handleCreateSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tipe Transaksi</label>
            <select
              value={type}
              onChange={(e) => setType(e.target.value)}
              className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="expense">Pengeluaran (Expense)</option>
              <option value="income">Pemasukan (Income)</option>
              <option value="transfer">Transfer Akun</option>
            </select>
          </div>

          <Input
            label="Jumlah (Rp)"
            type="number"
            step="0.01"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            error={createErrors.amount?.[0]}
            placeholder="0"
            required
          />

          <Input
            label="Deskripsi / Catatan"
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            error={createErrors.description?.[0]}
            placeholder="Contoh: Tagihan Internet Indihome"
          />

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Frekuensi</label>
              <select
                value={frequency}
                onChange={(e) => setFrequency(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
              >
                <option value="daily">Harian</option>
                <option value="weekly">Mingguan</option>
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Akun Sumber</label>
              <select
                value={accountId}
                onChange={(e) => setAccountId(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                required
              >
                <option value="">-- Pilih Akun --</option>
                {accounts.map((acc) => (
                  <option key={acc.id} value={acc.id}>{acc.name}</option>
                ))}
              </select>
              {createErrors.account_id && <p className="text-red-500 text-xs mt-1">{createErrors.account_id[0]}</p>}
            </div>
          </div>

          {type === 'transfer' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Akun Tujuan</label>
              <select
                value={toAccountId}
                onChange={(e) => setToAccountId(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                required
              >
                <option value="">-- Pilih Akun Tujuan --</option>
                {accounts.map((acc) => (
                  <option key={acc.id} value={acc.id}>{acc.name}</option>
                ))}
              </select>
              {createErrors.to_account_id && <p className="text-red-500 text-xs mt-1">{createErrors.to_account_id[0]}</p>}
            </div>
          )}

          {type !== 'transfer' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Kategori (Opsional)</label>
              <select
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
              >
                <option value="">-- Tanpa Kategori --</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>{cat.name}</option>
                ))}
              </select>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Tanggal Mulai"
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              error={createErrors.start_date?.[0]}
              required
            />
            <Input
              label="Tanggal Selesai (Opsional)"
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              error={createErrors.end_date?.[0]}
            />
          </div>

          <div className="flex items-center space-x-2 pt-2">
            <input
              type="checkbox"
              id="isActiveCheck"
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
              className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <label htmlFor="isActiveCheck" className="text-sm font-medium text-gray-700">Status Aktif</label>
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <Button type="button" variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Batal
            </Button>
            <Button type="submit" disabled={createSubmitting} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              {createSubmitting ? 'Menyimpan...' : 'Simpan Transaksi Rutin'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Modal */}
      <Modal isOpen={!!deleteItem} title="Hapus Transaksi Rutin" onClose={() => setDeleteItem(null)}>
        <p className="text-gray-700 mb-4">
          Apakah Anda yakin ingin menghapus transaksi rutin <strong>{deleteItem?.description || 'tanpa nama'}</strong>?
        </p>
        <div className="flex justify-end space-x-3">
          <Button type="button" variant="secondary" onClick={() => setDeleteItem(null)}>
            Batal
          </Button>
          <Button type="button" onClick={handleDelete} disabled={deleteSubmitting} className="bg-red-600 hover:bg-red-700 text-white">
            {deleteSubmitting ? 'Menghapus...' : 'Hapus'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
