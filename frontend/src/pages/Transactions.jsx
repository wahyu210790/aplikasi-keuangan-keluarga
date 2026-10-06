import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

/**
 * Reusable Modal component.
 */
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

export default function Transactions() {
  // Context
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  // Transaction list state & filter
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [filterType, setFilterType] = useState('all'); // 'all', 'income', 'expense', 'transfer'
  const requestIdRef = useRef(0);

  // Pagination state (Task 7.25)
  const [currentPage, setCurrentPage] = useState(1);
  const perPage = 10;

  // Account list state (shared for dropdowns & display names)
  const [accounts, setAccounts] = useState([]);
  const [accountsLoading, setAccountsLoading] = useState(false);
  const accountsRequestIdRef = useRef(0);

  // Success Feedback Toast/Alert
  const [successMessage, setSuccessMessage] = useState(null);

  // ----- Create UI state -----
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createType, setCreateType] = useState('income');
  const [createAmount, setCreateAmount] = useState('');
  const [createDescription, setCreateDescription] = useState('');
  const [createDate, setCreateDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [createAccountId, setCreateAccountId] = useState('');
  const [createToAccountId, setCreateToAccountId] = useState('');
  const [createErrors, setCreateErrors] = useState({});
  const [creating, setCreating] = useState(false);

  // ----- Detail UI state -----
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [detailTx, setDetailTx] = useState(null);

  // ----- Edit UI state -----
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editTransactionId, setEditTransactionId] = useState(null);
  const [editType, setEditType] = useState('income');
  const [editAmount, setEditAmount] = useState('');
  const [editDescription, setEditDescription] = useState('');
  const [editDate, setEditDate] = useState('');
  const [editAccountId, setEditAccountId] = useState('');
  const [editToAccountId, setEditToAccountId] = useState('');
  const [editErrors, setEditErrors] = useState({});
  const [updating, setUpdating] = useState(false);

  // ----- Delete UI state -----
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteTransactionId, setDeleteTransactionId] = useState(null);
  const [deleteTx, setDeleteTx] = useState(null);
  const [deleteErrors, setDeleteErrors] = useState({});
  const [deleting, setDeleting] = useState(false);

  // Helpers
  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  const typeLabel = (type) => {
    switch (type) {
      case 'income':
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Pemasukan</span>;
      case 'expense':
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pengeluaran</span>;
      case 'transfer':
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Transfer</span>;
      default:
        return type;
    }
  };

  const getAccountName = (accId) => {
    if (!accId) return '—';
    const acc = accounts.find((a) => String(a.id) === String(accId));
    return acc ? acc.name : `Akun #${accId}`;
  };

  // Fetch transactions with filter support (Task 7.24)
  const fetchTransactions = (householdId, typeFilter = filterType) => {
    if (!householdId) {
      setTransactions([]);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);

    let url = `/households/${householdId}/transactions`;
    if (typeFilter && typeFilter !== 'all') {
      url += `?type=${typeFilter}`;
    }

    api
      .get(url)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        setTransactions(res.data.transactions ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat data transaksi';
        setError(msg);
        setTransactions([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

  // Fetch accounts for household
  const fetchAccounts = (householdId) => {
    if (!householdId) {
      setAccounts([]);
      setAccountsLoading(false);
      return;
    }
    accountsRequestIdRef.current += 1;
    const currentRequestId = accountsRequestIdRef.current;
    setAccountsLoading(true);
    api
      .get(`/households/${householdId}/accounts`)
      .then((res) => {
        if (accountsRequestIdRef.current !== currentRequestId) return;
        const active = (res.data.accounts ?? []).filter((a) => a.is_active);
        setAccounts(active);
      })
      .catch(() => {
        if (accountsRequestIdRef.current !== currentRequestId) return;
        setAccounts([]);
      })
      .finally(() => {
        if (accountsRequestIdRef.current !== currentRequestId) return;
        setAccountsLoading(false);
      });
  };

  // Load when household or filter changes
  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setTransactions([]);
      setError(null);
      setLoading(false);
      setAccounts([]);
      return;
    }
    fetchTransactions(activeHouseholdId, filterType);
    fetchAccounts(activeHouseholdId);
  }, [activeHouseholdId, contextLoading, filterType]);

  // Reset page when filter or household changes
  useEffect(() => {
    setCurrentPage(1);
  }, [filterType, activeHouseholdId]);

  // Reset UI on household switch
  useEffect(() => {
    setTransactions([]);
    setError(null);
    setSuccessMessage(null);
    setIsCreateOpen(false);
    setIsDetailOpen(false);
    setIsEditOpen(false);
    setIsDeleteOpen(false);
  }, [activeHouseholdId]);

  // Handle Filter Click
  const handleFilterChange = (newType) => {
    setFilterType(newType);
  };

  // Pagination calculation (Task 7.25)
  const totalItems = transactions.length;
  const totalPages = Math.ceil(totalItems / perPage) || 1;
  const startIndex = (currentPage - 1) * perPage;
  const currentTransactions = transactions.slice(startIndex, startIndex + perPage);

  // -------------------- Create Validation & Submit --------------------
  const validateCreate = () => {
    const errors = {};
    if (!['income', 'expense', 'transfer'].includes(createType)) errors.type = 'Tipe tidak valid';
    const amt = Number(createAmount);
    if (createAmount === '' || Number.isNaN(amt)) errors.amount = 'Jumlah wajib berupa angka';
    else if (amt <= 0) errors.amount = 'Jumlah harus lebih besar dari 0';
    if (!createDate) errors.date = 'Tanggal wajib diisi';
    if (!createAccountId) errors.account_id = 'Akun wajib dipilih';
    if (createType === 'transfer') {
      if (!createToAccountId) errors.to_account_id = 'Akun tujuan wajib dipilih';
      else if (String(createToAccountId) === String(createAccountId)) errors.to_account_id = 'Akun sumber dan akun tujuan tidak boleh sama.';
    }
    if (createDescription && createDescription.length > 255) errors.description = 'Maksimum 255 karakter';
    setCreateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateSubmit = () => {
    if (!validateCreate()) return;
    const payload = {
      type: createType,
      amount: Number(createAmount),
      description: createDescription.trim() || undefined,
      transaction_date: createDate,
      account_id: Number(createAccountId),
    };
    if (createType === 'transfer') payload.to_account_id = Number(createToAccountId);

    const currentHouseholdId = activeHouseholdId;
    setCreating(true);
    api
      .post(`/households/${currentHouseholdId}/transactions`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsCreateOpen(false);
        setCreateAmount('');
        setCreateDescription('');
        setCreateDate(new Date().toISOString().slice(0, 10));
        setCreateAccountId('');
        setCreateToAccountId('');
        setCreateErrors({});
        setSuccessMessage('Transaksi berhasil ditambahkan!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchTransactions(activeHouseholdId, filterType);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setCreateErrors(apiErrors);
        } else if (err.response && err.response.status === 403) {
          setCreateErrors({ general: 'Akses ditolak (403)' });
        } else if (err.response && err.response.status === 401) {
          setCreateErrors({ general: 'Harus login terlebih dahulu (401)' });
        } else {
          setCreateErrors({ general: 'Gagal menyimpan transaksi. Silakan coba lagi.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setCreating(false);
      });
  };

  // -------------------- Detail Helper --------------------
  const openDetail = (tx) => {
    setDetailTx(tx);
    setIsDetailOpen(true);
  };

  // -------------------- Edit Helpers --------------------
  const openEdit = (tx) => {
    setEditTransactionId(tx.id);
    setEditType(tx.type);
    setEditAmount(String(tx.amount));
    setEditDescription(tx.description ?? '');
    setEditDate(tx.transaction_date);
    setEditAccountId(String(tx.account_id ?? ''));
    setEditToAccountId(String(tx.to_account_id ?? ''));
    setEditErrors({});
    setIsEditOpen(true);
  };

  const closeEdit = () => {
    setIsEditOpen(false);
    setEditTransactionId(null);
    setEditErrors({});
    setUpdating(false);
  };

  const validateEdit = () => {
    const errors = {};
    if (!['income', 'expense', 'transfer'].includes(editType)) errors.type = 'Tipe tidak valid';
    const amt = Number(editAmount);
    if (editAmount === '' || Number.isNaN(amt)) errors.amount = 'Jumlah wajib berupa angka';
    else if (amt <= 0) errors.amount = 'Jumlah harus lebih besar dari 0';
    if (!editDate) errors.date = 'Tanggal wajib diisi';
    if (!editAccountId) errors.account_id = 'Akun wajib dipilih';
    if (editType === 'transfer') {
      if (!editToAccountId) errors.to_account_id = 'Akun tujuan wajib dipilih';
      else if (String(editToAccountId) === String(editAccountId)) errors.to_account_id = 'Akun sumber dan akun tujuan tidak boleh sama.';
    }
    if (editDescription && editDescription.length > 255) errors.description = 'Maksimum 255 karakter';
    setEditErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleEditSubmit = () => {
    if (!validateEdit()) return;
    const payload = {
      type: editType,
      amount: Number(editAmount),
      description: editDescription.trim() || undefined,
      transaction_date: editDate,
      account_id: Number(editAccountId),
    };
    if (editType === 'transfer') payload.to_account_id = Number(editToAccountId);

    const currentHouseholdId = activeHouseholdId;
    const txId = editTransactionId;
    setUpdating(true);
    api
      .patch(`/households/${currentHouseholdId}/transactions/${txId}`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        closeEdit();
        setSuccessMessage('Transaksi berhasil diperbarui!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchTransactions(activeHouseholdId, filterType);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setEditErrors(apiErrors);
        } else if (err.response && err.response.status === 404) {
          setEditErrors({ general: 'Transaksi tidak ditemukan (404)' });
        } else if (err.response && err.response.status === 403) {
          setEditErrors({ general: 'Akses ditolak (403)' });
        } else {
          setEditErrors({ general: 'Gagal memperbarui transaksi.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setUpdating(false);
      });
  };

  // -------------------- Delete Helpers --------------------
  const openDelete = (tx) => {
    setDeleteTransactionId(tx.id);
    setDeleteTx(tx);
    setDeleteErrors({});
    setIsDeleteOpen(true);
  };

  const closeDelete = () => {
    setIsDeleteOpen(false);
    setDeleteTransactionId(null);
    setDeleteTx(null);
    setDeleteErrors({});
    setDeleting(false);
  };

  const handleDeleteConfirm = () => {
    const currentHouseholdId = activeHouseholdId;
    const txId = deleteTransactionId;
    setDeleting(true);
    api
      .delete(`/households/${currentHouseholdId}/transactions/${txId}`)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        closeDelete();
        setSuccessMessage('Transaksi berhasil dihapus!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchTransactions(activeHouseholdId, filterType);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 404) {
          setDeleteErrors({ general: 'Transaksi tidak ditemukan (404)' });
        } else if (err.response && err.response.status === 403) {
          setDeleteErrors({ general: 'Akses ditolak (403)' });
        } else {
          setDeleteErrors({ general: 'Gagal menghapus transaksi.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setDeleting(false);
      });
  };

  // Layout Renders
  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat data household…</p>
      </div>
    );
  }

  if (!activeHouseholdId) {
    return <Alert type="info">Belum ada household aktif. Silakan pilih household pada selector di atas.</Alert>;
  }

  return (
    <div className="flex min-h-screen justify-center bg-gray-100 p-4">
      <Card className="max-w-6xl w-full space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row justify-between md:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-indigo-600">Transaksi Keuangan</h1>
            <p className="text-gray-600 text-sm">Kelola pemasukan, pengeluaran, dan transfer antar akun</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
            + Tambah Transaksi
          </Button>
        </div>

        {/* Success Alert */}
        {successMessage && <Alert type="success">{successMessage}</Alert>}

        {/* Error Alert */}
        {error && (
          <div className="space-y-2">
            <Alert type="error">{error}</Alert>
            <Button onClick={() => fetchTransactions(activeHouseholdId, filterType)} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs">
              Coba Lagi
            </Button>
          </div>
        )}

        {/* Filter Controls (Task 7.24) */}
        <div className="flex flex-wrap items-center justify-between border-b pb-4 gap-2">
          <div className="flex space-x-1 bg-gray-200 p-1 rounded-lg text-sm font-medium">
            <button
              onClick={() => handleFilterChange('all')}
              className={`px-4 py-1.5 rounded-md transition ${filterType === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Semua
            </button>
            <button
              onClick={() => handleFilterChange('income')}
              className={`px-4 py-1.5 rounded-md transition ${filterType === 'income' ? 'bg-white text-green-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Pemasukan
            </button>
            <button
              onClick={() => handleFilterChange('expense')}
              className={`px-4 py-1.5 rounded-md transition ${filterType === 'expense' ? 'bg-white text-red-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Pengeluaran
            </button>
            <button
              onClick={() => handleFilterChange('transfer')}
              className={`px-4 py-1.5 rounded-md transition ${filterType === 'transfer' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Transfer
            </button>
          </div>
          <span className="text-xs text-gray-500 font-medium">
            Total: {totalItems} Transaksi
          </span>
        </div>

        {/* Loading Indicator */}
        {loading ? (
          <div className="py-12 text-center text-gray-500 font-medium">
            Memuat daftar transaksi…
          </div>
        ) : transactions.length === 0 ? (
          <Alert type="info">Belum ada data transaksi yang sesuai filter.</Alert>
        ) : (
          <>
            {/* Transactions Table */}
            <div className="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
              <table className="min-w-full divide-y divide-gray-200 bg-white text-sm">
                <thead className="bg-gray-50 text-gray-700 font-semibold">
                  <tr>
                    <th className="px-4 py-3 text-left">Tanggal</th>
                    <th className="px-4 py-3 text-left">Tipe</th>
                    <th className="px-4 py-3 text-left">Deskripsi</th>
                    <th className="px-4 py-3 text-left">Jumlah</th>
                    <th className="px-4 py-3 text-left">Akun / Akun Asal</th>
                    <th className="px-4 py-3 text-left">Akun Tujuan</th>
                    <th className="px-4 py-3 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 text-gray-600">
                  {currentTransactions.map((t) => (
                    <tr key={t.id} className="hover:bg-gray-50 transition">
                      <td className="px-4 py-3 whitespace-nowrap font-medium text-gray-900">{t.transaction_date}</td>
                      <td className="px-4 py-3 whitespace-nowrap">{typeLabel(t.type)}</td>
                      <td className="px-4 py-3">{t.description || <span className="text-gray-400 italic">Tanpa deskripsi</span>}</td>
                      <td className="px-4 py-3 whitespace-nowrap font-semibold text-gray-900">{formatRupiah(t.amount)}</td>
                      <td className="px-4 py-3 whitespace-nowrap">{getAccountName(t.account_id)}</td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        {t.type === 'transfer' ? getAccountName(t.to_account_id) : <span className="text-gray-300">—</span>}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap text-center space-x-1">
                        <Button onClick={() => openDetail(t)} className="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2.5 py-1">
                          Detail
                        </Button>
                        <Button onClick={() => openEdit(t)} className="bg-amber-600 hover:bg-amber-700 text-white text-xs px-2.5 py-1">
                          Edit
                        </Button>
                        <Button onClick={() => openDelete(t)} className="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-1">
                          Hapus
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination Controls (Task 7.25) */}
            <div className="flex flex-col sm:flex-row justify-between items-center gap-4 pt-4 border-t text-sm">
              <div className="text-gray-600">
                Menampilkan <span className="font-semibold text-gray-900">{startIndex + 1}</span> hingga{' '}
                <span className="font-semibold text-gray-900">{Math.min(startIndex + perPage, totalItems)}</span> dari{' '}
                <span className="font-semibold text-gray-900">{totalItems}</span> transaksi
              </div>
              <div className="flex items-center space-x-2">
                <Button
                  onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                  disabled={currentPage === 1}
                  className="bg-white border text-gray-700 hover:bg-gray-50 text-xs px-3 py-1.5 disabled:opacity-40"
                >
                  &laquo; Sebelumnya
                </Button>
                <span className="px-3 py-1 text-gray-700 font-medium">
                  {currentPage} / {totalPages}
                </span>
                <Button
                  onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
                  disabled={currentPage >= totalPages}
                  className="bg-white border text-gray-700 hover:bg-gray-50 text-xs px-3 py-1.5 disabled:opacity-40"
                >
                  Selanjutnya &raquo;
                </Button>
              </div>
            </div>
          </>
        )}
      </Card>

      {/* ----- Create Modal ----- */}
      <Modal isOpen={isCreateOpen} title="Tambah Transaksi" onClose={() => setIsCreateOpen(false)}>
        {createErrors.general && <Alert type="error" className="mb-3">{createErrors.general}</Alert>}
        
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tipe Transaksi</label>
          <select value={createType} onChange={(e) => setCreateType(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="income">Pemasukan (Income)</option>
            <option value="expense">Pengeluaran (Expense)</option>
            <option value="transfer">Transfer Internal</option>
          </select>
          {createErrors.type && <p className="text-xs text-red-600 mt-1">{createErrors.type}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp)</label>
          <Input type="number" value={createAmount} onChange={(e) => setCreateAmount(e.target.value)} placeholder="0" />
          {createErrors.amount && <p className="text-xs text-red-600 mt-1">{createErrors.amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Deskripsi (Opsional)</label>
          <Input value={createDescription} onChange={(e) => setCreateDescription(e.target.value)} placeholder="Contoh: Gaji Bulanan / Belanja Sembako" />
          {createErrors.description && <p className="text-xs text-red-600 mt-1">{createErrors.description}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Transaksi</label>
          <Input type="date" value={createDate} onChange={(e) => setCreateDate(e.target.value)} />
          {createErrors.date && <p className="text-xs text-red-600 mt-1">{createErrors.date}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {createType === 'transfer' ? 'Akun Sumber' : 'Akun'}
          </label>
          {accountsLoading ? (
            <p className="text-xs text-gray-500">Memuat data akun…</p>
          ) : (
            <select value={createAccountId} onChange={(e) => setCreateAccountId(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm">
              <option value="">-- Pilih Akun --</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>{a.name}</option>
              ))}
            </select>
          )}
          {createErrors.account_id && <p className="text-xs text-red-600 mt-1">{createErrors.account_id}</p>}
        </div>

        {createType === 'transfer' && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Akun Tujuan</label>
            {accountsLoading ? (
              <p className="text-xs text-gray-500">Memuat data akun…</p>
            ) : (
              <select value={createToAccountId} onChange={(e) => setCreateToAccountId(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm">
                <option value="">-- Pilih Akun Tujuan --</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
            )}
            {createErrors.to_account_id && <p className="text-xs text-red-600 mt-1">{createErrors.to_account_id}</p>}
          </div>
        )}

        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsCreateOpen(false)} disabled={creating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleCreateSubmit} disabled={creating} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            {creating ? 'Menyimpan…' : 'Simpan Transaksi'}
          </Button>
        </div>
      </Modal>

      {/* ----- Detail Modal ----- */}
      <Modal isOpen={isDetailOpen} title="Detail Transaksi" onClose={() => setIsDetailOpen(false)}>
        {detailTx && (
          <div className="space-y-3 text-sm text-gray-700">
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">ID Transaksi:</span>
              <span className="font-mono">{detailTx.id}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Tipe:</span>
              <span>{typeLabel(detailTx.type)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Jumlah:</span>
              <span className="font-bold text-gray-900">{formatRupiah(detailTx.amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Tanggal:</span>
              <span>{detailTx.transaction_date}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">{detailTx.type === 'transfer' ? 'Akun Sumber:' : 'Akun:'}</span>
              <span>{getAccountName(detailTx.account_id)}</span>
            </div>
            {detailTx.type === 'transfer' && (
              <div className="flex justify-between py-1 border-b">
                <span className="font-semibold text-gray-500">Akun Tujuan:</span>
                <span>{getAccountName(detailTx.to_account_id)}</span>
              </div>
            )}
            <div className="py-1">
              <span className="font-semibold text-gray-500 block mb-1">Deskripsi:</span>
              <p className="bg-gray-50 p-2.5 rounded text-gray-800">{detailTx.description || 'Tidak ada deskripsi.'}</p>
            </div>
          </div>
        )}
        <div className="flex justify-end mt-6 pt-4 border-t">
          <Button onClick={() => setIsDetailOpen(false)} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Tutup
          </Button>
        </div>
      </Modal>

      {/* ----- Edit Modal ----- */}
      <Modal isOpen={isEditOpen} title="Edit Transaksi" onClose={closeEdit}>
        {editErrors.general && <Alert type="error" className="mb-3">{editErrors.general}</Alert>}
        
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tipe Transaksi</label>
          <select value={editType} onChange={(e) => setEditType(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm">
            <option value="income">Pemasukan (Income)</option>
            <option value="expense">Pengeluaran (Expense)</option>
            <option value="transfer">Transfer Internal</option>
          </select>
          {editErrors.type && <p className="text-xs text-red-600 mt-1">{editErrors.type}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp)</label>
          <Input type="number" value={editAmount} onChange={(e) => setEditAmount(e.target.value)} placeholder="0" />
          {editErrors.amount && <p className="text-xs text-red-600 mt-1">{editErrors.amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Deskripsi (Opsional)</label>
          <Input value={editDescription} onChange={(e) => setEditDescription(e.target.value)} placeholder="Deskripsi transaksi" />
          {editErrors.description && <p className="text-xs text-red-600 mt-1">{editErrors.description}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Transaksi</label>
          <Input type="date" value={editDate} onChange={(e) => setEditDate(e.target.value)} />
          {editErrors.date && <p className="text-xs text-red-600 mt-1">{editErrors.date}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {editType === 'transfer' ? 'Akun Sumber' : 'Akun'}
          </label>
          {accountsLoading ? (
            <p className="text-xs text-gray-500">Memuat data akun…</p>
          ) : (
            <select value={editAccountId} onChange={(e) => setEditAccountId(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm">
              <option value="">-- Pilih Akun --</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>{a.name}</option>
              ))}
            </select>
          )}
          {editErrors.account_id && <p className="text-xs text-red-600 mt-1">{editErrors.account_id}</p>}
        </div>

        {editType === 'transfer' && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Akun Tujuan</label>
            {accountsLoading ? (
              <p className="text-xs text-gray-500">Memuat data akun…</p>
            ) : (
              <select value={editToAccountId} onChange={(e) => setEditToAccountId(e.target.value)} className="w-full border border-gray-300 rounded-md p-2 text-sm">
                <option value="">-- Pilih Akun Tujuan --</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
            )}
            {editErrors.to_account_id && <p className="text-xs text-red-600 mt-1">{editErrors.to_account_id}</p>}
          </div>
        )}

        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={closeEdit} disabled={updating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleEditSubmit} disabled={updating} className="bg-amber-600 hover:bg-amber-700 text-white text-sm">
            {updating ? 'Menyimpan…' : 'Simpan Perubahan'}
          </Button>
        </div>
      </Modal>

      {/* ----- Delete Confirmation Modal ----- */}
      <Modal isOpen={isDeleteOpen} title="Konfirmasi Hapus Transaksi" onClose={closeDelete}>
        {deleteErrors.general && <Alert type="error" className="mb-3">{deleteErrors.general}</Alert>}
        {deleteTx && (
          <div className="mb-4 text-sm text-gray-700 space-y-2">
            <p>Apakah Anda yakin ingin menghapus transaksi ini?</p>
            <div className="bg-red-50 border border-red-200 p-3 rounded-md space-y-1">
              <div><strong>Tipe:</strong> {typeLabel(deleteTx.type)}</div>
              <div><strong>Jumlah:</strong> {formatRupiah(deleteTx.amount)}</div>
              <div><strong>Tanggal:</strong> {deleteTx.transaction_date}</div>
              {deleteTx.description && <div><strong>Deskripsi:</strong> {deleteTx.description}</div>}
            </div>
          </div>
        )}
        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={closeDelete} disabled={deleting} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleDeleteConfirm} disabled={deleting} className="bg-red-600 hover:bg-red-700 text-white text-sm">
            {deleting ? 'Menghapus…' : 'Hapus Transaksi'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
