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

export default function Savings() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  // State
  const [savings, setSavings] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [filterStatus, setFilterStatus] = useState('all'); // 'all', 'active', 'completed'
  const requestIdRef = useRef(0);

  // Accounts list for dropdown
  const [accounts, setAccounts] = useState([]);
  const [accountsLoading, setAccountsLoading] = useState(false);

  // Success Toast
  const [successMessage, setSuccessMessage] = useState(null);

  // ----- Create UI State -----
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createTargetAmount, setCreateTargetAmount] = useState('');
  const [createCurrentAmount, setCreateCurrentAmount] = useState('0');
  const [createTargetDate, setCreateTargetDate] = useState('');
  const [createAccountId, setCreateAccountId] = useState('');
  const [createErrors, setCreateErrors] = useState({});
  const [creating, setCreating] = useState(false);

  // ----- Detail UI State -----
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [detailSaving, setDetailSaving] = useState(null);

  // ----- Edit UI State -----
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editSavingId, setEditSavingId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editTargetAmount, setEditTargetAmount] = useState('');
  const [editCurrentAmount, setEditCurrentAmount] = useState('');
  const [editTargetDate, setEditTargetDate] = useState('');
  const [editAccountId, setEditAccountId] = useState('');
  const [editIsCompleted, setEditIsCompleted] = useState(false);
  const [editErrors, setEditErrors] = useState({});
  const [updating, setUpdating] = useState(false);

  // ----- Delete UI State -----
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteSavingId, setDeleteSavingId] = useState(null);
  const [deleteSaving, setDeleteSaving] = useState(null);
  const [deleteErrors, setDeleteErrors] = useState({});
  const [deleting, setDeleting] = useState(false);

  // Format IDR Currency
  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  // Fetch Savings Goal
  const fetchSavings = (householdId) => {
    if (!householdId) {
      setSavings([]);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);

    api
      .get(`/households/${householdId}/savings`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        setSavings(res.data.savings ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat data target tabungan';
        setError(msg);
        setSavings([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

  // Fetch Accounts
  const fetchAccounts = (householdId) => {
    if (!householdId) {
      setAccounts([]);
      return;
    }
    setAccountsLoading(true);
    api
      .get(`/households/${householdId}/accounts`)
      .then((res) => {
        const activeAcc = (res.data.accounts ?? []).filter((a) => a.is_active);
        setAccounts(activeAcc);
      })
      .catch(() => setAccounts([]))
      .finally(() => setAccountsLoading(false));
  };

  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setSavings([]);
      setAccounts([]);
      return;
    }
    fetchSavings(activeHouseholdId);
    fetchAccounts(activeHouseholdId);
  }, [activeHouseholdId, contextLoading]);

  // Create Validation & Submit
  const validateCreate = () => {
    const errors = {};
    if (!createName.trim()) errors.name = 'Nama target tabungan wajib diisi';
    const target = Number(createTargetAmount);
    if (!createTargetAmount || Number.isNaN(target) || target <= 0) errors.target_amount = 'Target dana harus > 0';
    const current = Number(createCurrentAmount);
    if (createCurrentAmount && (Number.isNaN(current) || current < 0)) errors.current_amount = 'Saldo terkumpul tidak boleh negatif';
    setCreateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateSubmit = () => {
    if (!validateCreate()) return;

    const payload = {
      name: createName.trim(),
      target_amount: Number(createTargetAmount),
      current_amount: createCurrentAmount ? Number(createCurrentAmount) : 0,
      target_date: createTargetDate || null,
      account_id: createAccountId ? Number(createAccountId) : null,
    };

    const currentHouseholdId = activeHouseholdId;
    setCreating(true);
    api
      .post(`/households/${currentHouseholdId}/savings`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsCreateOpen(false);
        setCreateName('');
        setCreateTargetAmount('');
        setCreateCurrentAmount('0');
        setCreateTargetDate('');
        setCreateAccountId('');
        setCreateErrors({});
        setSuccessMessage('Target tabungan baru berhasil ditambahkan!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchSavings(activeHouseholdId);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setCreateErrors(apiErrors);
        } else {
          setCreateErrors({ general: 'Gagal membuat target tabungan. Silakan coba lagi.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setCreating(false);
      });
  };

  // Edit Helpers
  const openEdit = (s) => {
    setEditSavingId(s.id);
    setEditName(s.name);
    setEditTargetAmount(String(s.target_amount));
    setEditCurrentAmount(String(s.current_amount));
    setEditTargetDate(s.target_date || '');
    setEditAccountId(s.account_id ? String(s.account_id) : '');
    setEditIsCompleted(s.is_completed ?? false);
    setEditErrors({});
    setIsEditOpen(true);
  };

  const validateEdit = () => {
    const errors = {};
    if (!editName.trim()) errors.name = 'Nama target tabungan wajib diisi';
    const target = Number(editTargetAmount);
    if (!editTargetAmount || Number.isNaN(target) || target <= 0) errors.target_amount = 'Target dana harus > 0';
    const current = Number(editCurrentAmount);
    if (editCurrentAmount && (Number.isNaN(current) || current < 0)) errors.current_amount = 'Saldo terkumpul tidak boleh negatif';
    setEditErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleEditSubmit = () => {
    if (!validateEdit()) return;

    const payload = {
      name: editName.trim(),
      target_amount: Number(editTargetAmount),
      current_amount: Number(editCurrentAmount),
      target_date: editTargetDate || null,
      account_id: editAccountId ? Number(editAccountId) : null,
      is_completed: editIsCompleted,
    };

    const currentHouseholdId = activeHouseholdId;
    const sId = editSavingId;
    setUpdating(true);
    api
      .patch(`/households/${currentHouseholdId}/savings/${sId}`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsEditOpen(false);
        setSuccessMessage('Target tabungan berhasil diperbarui!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchSavings(activeHouseholdId);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setEditErrors(apiErrors);
        } else {
          setEditErrors({ general: 'Gagal memperbarui target tabungan.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setUpdating(false);
      });
  };

  // Delete Helpers
  const openDelete = (s) => {
    setDeleteSavingId(s.id);
    setDeleteSaving(s);
    setDeleteErrors({});
    setIsDeleteOpen(true);
  };

  const handleDeleteConfirm = () => {
    const currentHouseholdId = activeHouseholdId;
    const sId = deleteSavingId;
    setDeleting(true);
    api
      .delete(`/households/${currentHouseholdId}/savings/${sId}`)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsDeleteOpen(false);
        setSuccessMessage('Target tabungan berhasil dihapus!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchSavings(activeHouseholdId);
      })
      .catch(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setDeleteErrors({ general: 'Gagal menghapus target tabungan.' });
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setDeleting(false);
      });
  };

  // Filter Savings
  const filteredSavings = savings.filter((s) => {
    if (filterStatus === 'active') return s.status === 'active';
    if (filterStatus === 'completed') return s.status === 'completed';
    return true;
  });

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
            <h1 className="text-2xl font-bold text-indigo-600">Target Tabungan (Savings)</h1>
            <p className="text-gray-600 text-sm">Rencanakan dan wujudkan impian finansial keluarga Anda</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
            + Tambah Target Tabungan
          </Button>
        </div>

        {/* Success Alert */}
        {successMessage && <Alert type="success">{successMessage}</Alert>}

        {/* Error Alert */}
        {error && (
          <div className="space-y-2">
            <Alert type="error">{error}</Alert>
            <Button onClick={() => fetchSavings(activeHouseholdId)} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs">
              Coba Lagi
            </Button>
          </div>
        )}

        {/* Filter Tabs */}
        <div className="flex flex-wrap items-center justify-between border-b pb-4 gap-2">
          <div className="flex space-x-1 bg-gray-200 p-1 rounded-lg text-sm font-medium">
            <button
              onClick={() => setFilterStatus('all')}
              className={`px-4 py-1.5 rounded-md transition ${filterStatus === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Semua ({savings.length})
            </button>
            <button
              onClick={() => setFilterStatus('active')}
              className={`px-4 py-1.5 rounded-md transition ${filterStatus === 'active' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Berjalan ({savings.filter((s) => s.status === 'active').length})
            </button>
            <button
              onClick={() => setFilterStatus('completed')}
              className={`px-4 py-1.5 rounded-md transition ${filterStatus === 'completed' ? 'bg-white text-green-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Tercapai 🎉 ({savings.filter((s) => s.status === 'completed').length})
            </button>
          </div>
        </div>

        {/* Content Grid */}
        {loading ? (
          <div className="py-12 text-center text-gray-500 font-medium">Memuat data target tabungan…</div>
        ) : filteredSavings.length === 0 ? (
          <Alert type="info">Belum ada target tabungan yang terdaftar.</Alert>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredSavings.map((s) => {
              const pct = Number(s.progress_percentage) || 0;
              const isDone = s.status === 'completed';
              const isExpired = s.status === 'expired';

              let barColor = 'bg-blue-500';
              let badgeColor = 'bg-blue-100 text-blue-800';
              let statusText = 'Berjalan';

              if (isDone) {
                barColor = 'bg-green-500';
                badgeColor = 'bg-green-100 text-green-800';
                statusText = 'Tercapai! 🎉';
              } else if (isExpired) {
                barColor = 'bg-amber-500';
                badgeColor = 'bg-amber-100 text-amber-800';
                statusText = 'Melewati Target Date';
              }

              return (
                <div key={s.id} className="bg-white border rounded-lg p-5 shadow-sm space-y-4 hover:shadow-md transition">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="font-bold text-gray-900 text-base">{s.name}</h3>
                      <p className="text-xs text-gray-500">
                        {s.account ? `Akun: ${s.account.name}` : 'Semua Akun Tabungan'}
                      </p>
                    </div>
                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeColor}`}>
                      {statusText}
                    </span>
                  </div>

                  {/* Progress Bar */}
                  <div>
                    <div className="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                      <span>Terkumpul: {formatRupiah(s.current_amount)}</span>
                      <span>{pct}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                      <div
                        className={`h-2.5 rounded-full transition-all duration-300 ${barColor}`}
                        style={{ width: `${Math.min(pct, 100)}%` }}
                      ></div>
                    </div>
                  </div>

                  {/* Detail stats */}
                  <div className="grid grid-cols-2 gap-2 text-xs bg-gray-50 p-2.5 rounded border">
                    <div>
                      <span className="text-gray-500 block">Target Dana:</span>
                      <span className="font-bold text-gray-900">{formatRupiah(s.target_amount)}</span>
                    </div>
                    <div>
                      <span className="text-gray-500 block">Sisa Kekurangan:</span>
                      <span className={`font-bold ${isDone ? 'text-green-700' : 'text-indigo-600'}`}>
                        {formatRupiah(s.remaining_amount)}
                      </span>
                    </div>
                  </div>

                  <div className="text-xs text-gray-500 flex justify-between">
                    <span>Target Selesai: {s.target_date || 'Tanpa Batas Waktu'}</span>
                  </div>

                  {/* Actions */}
                  <div className="flex justify-end space-x-2 pt-2 border-t">
                    <Button onClick={() => { setDetailSaving(s); setIsDetailOpen(true); }} className="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2.5 py-1">
                      Detail
                    </Button>
                    <Button onClick={() => openEdit(s)} className="bg-amber-600 hover:bg-amber-700 text-white text-xs px-2.5 py-1">
                      Edit
                    </Button>
                    <Button onClick={() => openDelete(s)} className="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-1">
                      Hapus
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </Card>

      {/* ----- Create Modal ----- */}
      <Modal isOpen={isCreateOpen} title="Tambah Target Tabungan" onClose={() => setIsCreateOpen(false)}>
        {createErrors.general && <Alert type="error" className="mb-3">{createErrors.general}</Alert>}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Target Tabungan</label>
          <Input value={createName} onChange={(e) => setCreateName(e.target.value)} placeholder="Contoh: Tabungan Umroh / DP Rumah" />
          {createErrors.name && <p className="text-xs text-red-600 mt-1">{createErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Target Dana yang Ingin Dicapai (Rp)</label>
          <Input type="number" value={createTargetAmount} onChange={(e) => setCreateTargetAmount(e.target.value)} placeholder="30000000" />
          {createErrors.target_amount && <p className="text-xs text-red-600 mt-1">{createErrors.target_amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Awal Terkumpul Saat Ini (Rp)</label>
          <Input type="number" value={createCurrentAmount} onChange={(e) => setCreateCurrentAmount(e.target.value)} placeholder="0" />
          {createErrors.current_amount && <p className="text-xs text-red-600 mt-1">{createErrors.current_amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Target Tanggal Selesai (Opsional)</label>
          <Input type="date" value={createTargetDate} onChange={(e) => setCreateTargetDate(e.target.value)} />
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tautkan ke Akun Bank/Dompet (Opsional)</label>
          {accountsLoading ? (
            <p className="text-xs text-gray-500">Memuat data akun…</p>
          ) : (
            <select
              value={createAccountId}
              onChange={(e) => setCreateAccountId(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2 text-sm"
            >
              <option value="">-- Tanpa Tautan Akun --</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>{a.name}</option>
              ))}
            </select>
          )}
          {createErrors.account_id && <p className="text-xs text-red-600 mt-1">{createErrors.account_id}</p>}
        </div>

        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsCreateOpen(false)} disabled={creating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleCreateSubmit} disabled={creating} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            {creating ? 'Menyimpan…' : 'Simpan Target Tabungan'}
          </Button>
        </div>
      </Modal>

      {/* ----- Detail Modal ----- */}
      <Modal isOpen={isDetailOpen} title="Detail Target Tabungan" onClose={() => setIsDetailOpen(false)}>
        {detailSaving && (
          <div className="space-y-3 text-sm text-gray-700">
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Nama Target:</span>
              <span className="font-bold text-gray-900">{detailSaving.name}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Akun Terkait:</span>
              <span>{detailSaving.account ? detailSaving.account.name : '—'}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Target Dana:</span>
              <span className="font-bold text-gray-900">{formatRupiah(detailSaving.target_amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Total Terkumpul:</span>
              <span className="font-bold text-blue-600">{formatRupiah(detailSaving.current_amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Sisa Kekurangan:</span>
              <span className="font-bold text-indigo-600">{formatRupiah(detailSaving.remaining_amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Persentase Capaian:</span>
              <span className="font-bold">{detailSaving.progress_percentage}%</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Target Tanggal:</span>
              <span>{detailSaving.target_date || 'Tanpa Batas Waktu'}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Status Capaian:</span>
              <span className="font-bold uppercase text-xs">{detailSaving.status}</span>
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
      <Modal isOpen={isEditOpen} title="Edit Target Tabungan" onClose={() => setIsEditOpen(false)}>
        {editErrors.general && <Alert type="error" className="mb-3">{editErrors.general}</Alert>}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Target Tabungan</label>
          <Input value={editName} onChange={(e) => setEditName(e.target.value)} placeholder="Nama Target" />
          {editErrors.name && <p className="text-xs text-red-600 mt-1">{editErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Target Dana (Rp)</label>
          <Input type="number" value={editTargetAmount} onChange={(e) => setEditTargetAmount(e.target.value)} placeholder="Target Dana" />
          {editErrors.target_amount && <p className="text-xs text-red-600 mt-1">{editErrors.target_amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Terkumpul (Rp)</label>
          <Input type="number" value={editCurrentAmount} onChange={(e) => setEditCurrentAmount(e.target.value)} placeholder="Saldo Terkumpul" />
          {editErrors.current_amount && <p className="text-xs text-red-600 mt-1">{editErrors.current_amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Target Tanggal Selesai (Opsional)</label>
          <Input type="date" value={editTargetDate} onChange={(e) => setEditTargetDate(e.target.value)} />
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tautkan ke Akun Bank/Dompet (Opsional)</label>
          {accountsLoading ? (
            <p className="text-xs text-gray-500">Memuat data akun…</p>
          ) : (
            <select
              value={editAccountId}
              onChange={(e) => setEditAccountId(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2 text-sm"
            >
              <option value="">-- Tanpa Tautan Akun --</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>{a.name}</option>
              ))}
            </select>
          )}
          {editErrors.account_id && <p className="text-xs text-red-600 mt-1">{editErrors.account_id}</p>}
        </div>

        <div className="flex items-center mb-4">
          <input
            type="checkbox"
            id="editIsCompleted"
            checked={editIsCompleted}
            onChange={(e) => setEditIsCompleted(e.target.checked)}
            className="rounded text-indigo-600 border-gray-300 focus:ring-indigo-500"
          />
          <label htmlFor="editIsCompleted" className="ml-2 text-sm font-medium text-gray-700">
            Tandai Target Sudah Tercapai 🎉
          </label>
        </div>

        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsEditOpen(false)} disabled={updating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleEditSubmit} disabled={updating} className="bg-amber-600 hover:bg-amber-700 text-white text-sm">
            {updating ? 'Menyimpan…' : 'Simpan Perubahan'}
          </Button>
        </div>
      </Modal>

      {/* ----- Delete Modal ----- */}
      <Modal isOpen={isDeleteOpen} title="Konfirmasi Hapus Target Tabungan" onClose={() => setIsDeleteOpen(false)}>
        {deleteErrors.general && <Alert type="error" className="mb-3">{deleteErrors.general}</Alert>}
        {deleteSaving && (
          <div className="mb-4 text-sm text-gray-700 space-y-2">
            <p>Apakah Anda yakin ingin menghapus target tabungan ini?</p>
            <div className="bg-red-50 border border-red-200 p-3 rounded-md space-y-1">
              <div><strong>Nama:</strong> {deleteSaving.name}</div>
              <div><strong>Target:</strong> {formatRupiah(deleteSaving.target_amount)}</div>
              <div><strong>Terkumpul:</strong> {formatRupiah(deleteSaving.current_amount)}</div>
            </div>
          </div>
        )}
        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsDeleteOpen(false)} disabled={deleting} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleDeleteConfirm} disabled={deleting} className="bg-red-600 hover:bg-red-700 text-white text-sm">
            {deleting ? 'Menghapus…' : 'Hapus Target'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
