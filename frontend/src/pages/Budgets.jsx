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

export default function Budgets() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  // State
  const [budgets, setBudgets] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [filterStatus, setFilterStatus] = useState('all'); // 'all', 'active', 'exceeded'
  const [alertSummary, setAlertSummary] = useState(null);
  const requestIdRef = useRef(0);

  // Categories list for dropdown
  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);

  // Success Toast
  const [successMessage, setSuccessMessage] = useState(null);

  // ----- Create UI State -----
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createCategoryId, setCreateCategoryId] = useState('');
  const [createAmount, setCreateAmount] = useState('');
  const [createPeriodType, setCreatePeriodType] = useState('monthly');
  const [createStartDate, setCreateStartDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
  });
  const [createEndDate, setCreateEndDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
  });
  const [createErrors, setCreateErrors] = useState({});
  const [creating, setCreating] = useState(false);

  // ----- Detail UI State -----
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [detailBudget, setDetailBudget] = useState(null);

  // ----- Edit UI State -----
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editBudgetId, setEditBudgetId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editCategoryId, setEditCategoryId] = useState('');
  const [editAmount, setEditAmount] = useState('');
  const [editPeriodType, setEditPeriodType] = useState('monthly');
  const [editStartDate, setEditStartDate] = useState('');
  const [editEndDate, setEditEndDate] = useState('');
  const [editIsActive, setEditIsActive] = useState(true);
  const [editErrors, setEditErrors] = useState({});
  const [updating, setUpdating] = useState(false);

  // ----- Delete UI State -----
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteBudgetId, setDeleteBudgetId] = useState(null);
  const [deleteBudget, setDeleteBudget] = useState(null);
  const [deleteErrors, setDeleteErrors] = useState({});
  const [deleting, setDeleting] = useState(false);

  // Helpers
  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  // Fetch budgets
  const fetchBudgets = (householdId) => {
    if (!householdId) {
      setBudgets([]);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);

    api
      .get(`/households/${householdId}/budgets`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        setBudgets(res.data.budgets ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat data anggaran';
        setError(msg);
        setBudgets([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });

    api.get(`/households/${householdId}/budgets/alerts`).then((res) => {
      if (res.data?.summary) {
        setAlertSummary(res.data.summary);
      }
    }).catch(() => {});
  };

  // Fetch categories
  const fetchCategories = (householdId) => {
    if (!householdId) {
      setCategories([]);
      return;
    }
    setCategoriesLoading(true);
    api
      .get(`/households/${householdId}/categories`)
      .then((res) => {
        const activeExp = (res.data.categories ?? []).filter((c) => c.is_active && c.type === 'expense');
        setCategories(activeExp);
      })
      .catch(() => setCategories([]))
      .finally(() => setCategoriesLoading(false));
  };

  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setBudgets([]);
      setCategories([]);
      return;
    }
    fetchBudgets(activeHouseholdId);
    fetchCategories(activeHouseholdId);
  }, [activeHouseholdId, contextLoading]);

  // Handle monthly date autofill
  const handlePeriodTypeChange = (type, setType, setStart, setEnd) => {
    setType(type);
    if (type === 'monthly') {
      const d = new Date();
      setStart(new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10));
      setEnd(new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10));
    }
  };

  // Create Validation & Submit
  const validateCreate = () => {
    const errors = {};
    if (!createName.trim()) errors.name = 'Nama anggaran wajib diisi';
    const amt = Number(createAmount);
    if (!createAmount || Number.isNaN(amt) || amt <= 0) errors.amount = 'Jumlah anggaran harus > 0';
    if (!createStartDate) errors.start_date = 'Tanggal mulai wajib diisi';
    if (!createEndDate) errors.end_date = 'Tanggal selesai wajib diisi';
    if (createStartDate && createEndDate && new Date(createEndDate) < new Date(createStartDate)) {
      errors.end_date = 'Tanggal selesai harus setelah/sama dengan tanggal mulai';
    }
    setCreateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateSubmit = () => {
    if (!validateCreate()) return;

    const payload = {
      name: createName.trim(),
      category_id: createCategoryId ? Number(createCategoryId) : null,
      amount: Number(createAmount),
      period_type: createPeriodType,
      start_date: createStartDate,
      end_date: createEndDate,
    };

    const currentHouseholdId = activeHouseholdId;
    setCreating(true);
    api
      .post(`/households/${currentHouseholdId}/budgets`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsCreateOpen(false);
        setCreateName('');
        setCreateCategoryId('');
        setCreateAmount('');
        setCreateErrors({});
        setSuccessMessage('Anggaran baru berhasil dibuat!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchBudgets(activeHouseholdId);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setCreateErrors(apiErrors);
        } else {
          setCreateErrors({ general: 'Gagal membuat anggaran. Silakan coba lagi.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setCreating(false);
      });
  };

  // Edit Helpers
  const openEdit = (b) => {
    setEditBudgetId(b.id);
    setEditName(b.name);
    setEditCategoryId(b.category_id ? String(b.category_id) : '');
    setEditAmount(String(b.amount));
    setEditPeriodType(b.period_type || 'monthly');
    setEditStartDate(b.start_date);
    setEditEndDate(b.end_date);
    setEditIsActive(b.is_active ?? true);
    setEditErrors({});
    setIsEditOpen(true);
  };

  const validateEdit = () => {
    const errors = {};
    if (!editName.trim()) errors.name = 'Nama anggaran wajib diisi';
    const amt = Number(editAmount);
    if (!editAmount || Number.isNaN(amt) || amt <= 0) errors.amount = 'Jumlah anggaran harus > 0';
    if (!editStartDate) errors.start_date = 'Tanggal mulai wajib diisi';
    if (!editEndDate) errors.end_date = 'Tanggal selesai wajib diisi';
    if (editStartDate && editEndDate && new Date(editEndDate) < new Date(editStartDate)) {
      errors.end_date = 'Tanggal selesai harus setelah/sama dengan tanggal mulai';
    }
    setEditErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleEditSubmit = () => {
    if (!validateEdit()) return;

    const payload = {
      name: editName.trim(),
      category_id: editCategoryId ? Number(editCategoryId) : null,
      amount: Number(editAmount),
      period_type: editPeriodType,
      start_date: editStartDate,
      end_date: editEndDate,
      is_active: editIsActive,
    };

    const currentHouseholdId = activeHouseholdId;
    const bId = editBudgetId;
    setUpdating(true);
    api
      .patch(`/households/${currentHouseholdId}/budgets/${bId}`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsEditOpen(false);
        setSuccessMessage('Anggaran berhasil diperbarui!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchBudgets(activeHouseholdId);
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const k in err.response.data.errors) apiErrors[k] = err.response.data.errors[k].join(', ');
          setEditErrors(apiErrors);
        } else {
          setEditErrors({ general: 'Gagal memperbarui anggaran.' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setUpdating(false);
      });
  };

  // Delete Helpers
  const openDelete = (b) => {
    setDeleteBudgetId(b.id);
    setDeleteBudget(b);
    setDeleteErrors({});
    setIsDeleteOpen(true);
  };

  const handleDeleteConfirm = () => {
    const currentHouseholdId = activeHouseholdId;
    const bId = deleteBudgetId;
    setDeleting(true);
    api
      .delete(`/households/${currentHouseholdId}/budgets/${bId}`)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setIsDeleteOpen(false);
        setSuccessMessage('Anggaran berhasil dihapus!');
        setTimeout(() => setSuccessMessage(null), 3000);
        fetchBudgets(activeHouseholdId);
      })
      .catch(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setDeleteErrors({ general: 'Gagal menghapus anggaran.' });
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setDeleting(false);
      });
  };

  // Filtered Budgets
  const filteredBudgets = budgets.filter((b) => {
    if (filterStatus === 'active') return b.is_active;
    if (filterStatus === 'exceeded') return Number(b.spent_percentage) >= 100;
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
    return <Alert type="info">Belum ada household aktif. Pilih household pada selector di atas.</Alert>;
  }

  return (
    <div className="flex min-h-screen justify-center bg-gray-100 p-4">
      <Card className="max-w-6xl w-full space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row justify-between md:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-indigo-600">Perencanaan Anggaran (Budget)</h1>
            <p className="text-gray-600 text-sm">Kelola dan pantau batas pengeluaran rumah tangga Anda</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
            + Tambah Anggaran
          </Button>
        </div>

        {/* Success Alert */}
        {successMessage && <Alert type="success">{successMessage}</Alert>}

        {/* Error Alert */}
        {error && (
          <div className="space-y-2">
            <Alert type="error">{error}</Alert>
            <Button onClick={() => fetchBudgets(activeHouseholdId)} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs">
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
              Semua ({budgets.length})
            </button>
            <button
              onClick={() => setFilterStatus('active')}
              className={`px-4 py-1.5 rounded-md transition ${filterStatus === 'active' ? 'bg-white text-green-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Aktif ({budgets.filter((b) => b.is_active).length})
            </button>
            <button
              onClick={() => setFilterStatus('exceeded')}
              className={`px-4 py-1.5 rounded-md transition ${filterStatus === 'exceeded' ? 'bg-white text-red-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
            >
              Melebihi Limit ({budgets.filter((b) => Number(b.spent_percentage) >= 100).length})
            </button>
          </div>
        </div>

        {/* Content */}
        {loading ? (
          <div className="py-12 text-center text-gray-500 font-medium">Memuat data anggaran…</div>
        ) : filteredBudgets.length === 0 ? (
          <Alert type="info">Belum ada anggaran yang terdaftar pada kategori ini.</Alert>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredBudgets.map((b) => {
              const pct = Number(b.spent_percentage) || 0;
              const isOver = pct >= 100;
              const isWarning = pct >= 80 && pct < 100;

              let barColor = 'bg-green-500';
              let badgeColor = 'bg-green-100 text-green-800';
              let statusText = 'Aman';

              if (isOver) {
                barColor = 'bg-red-500';
                badgeColor = 'bg-red-100 text-red-800';
                statusText = 'Melebihi Limit!';
              } else if (isWarning) {
                barColor = 'bg-amber-500';
                badgeColor = 'bg-amber-100 text-amber-800';
                statusText = 'Hampir Limit (>=80%)';
              }

              return (
                <div key={b.id} className="bg-white border rounded-lg p-5 shadow-sm space-y-4 hover:shadow-md transition">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="font-bold text-gray-900 text-base">{b.name}</h3>
                      <p className="text-xs text-gray-500">
                        {b.category ? b.category.name : 'Semua Kategori Expense'}
                      </p>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${badgeColor}`}>
                      {statusText}
                    </span>
                  </div>

                  {/* Progress Bar */}
                  <div>
                    <div className="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                      <span>Terpakai: {formatRupiah(b.spent_amount)}</span>
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
                      <span className="text-gray-500 block">Total Anggaran:</span>
                      <span className="font-bold text-gray-900">{formatRupiah(b.amount)}</span>
                    </div>
                    <div>
                      <span className="text-gray-500 block">Sisa Anggaran:</span>
                      <span className={`font-bold ${isOver ? 'text-red-600' : 'text-green-700'}`}>
                        {formatRupiah(b.remaining_amount)}
                      </span>
                    </div>
                  </div>

                  <div className="text-xs text-gray-500 flex justify-between">
                    <span>Periode: {b.start_date} s/d {b.end_date}</span>
                  </div>

                  {/* Actions */}
                  <div className="flex justify-end space-x-2 pt-2 border-t">
                    <Button onClick={() => { setDetailBudget(b); setIsDetailOpen(true); }} className="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2.5 py-1">
                      Detail
                    </Button>
                    <Button onClick={() => openEdit(b)} className="bg-amber-600 hover:bg-amber-700 text-white text-xs px-2.5 py-1">
                      Edit
                    </Button>
                    <Button onClick={() => openDelete(b)} className="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-1">
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
      <Modal isOpen={isCreateOpen} title="Tambah Anggaran Baru" onClose={() => setIsCreateOpen(false)}>
        {createErrors.general && <Alert type="error" className="mb-3">{createErrors.general}</Alert>}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Anggaran</label>
          <Input value={createName} onChange={(e) => setCreateName(e.target.value)} placeholder="Contoh: Anggaran Belanja Bulanan" />
          {createErrors.name && <p className="text-xs text-red-600 mt-1">{createErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Kategori Pengeluaran (Opsional)</label>
          {categoriesLoading ? (
            <p className="text-xs text-gray-500">Memuat kategori…</p>
          ) : (
            <select
              value={createCategoryId}
              onChange={(e) => setCreateCategoryId(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2 text-sm"
            >
              <option value="">-- Semua Kategori Pengeluaran --</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
          {createErrors.category_id && <p className="text-xs text-red-600 mt-1">{createErrors.category_id}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah Batas Anggaran (Rp)</label>
          <Input type="number" value={createAmount} onChange={(e) => setCreateAmount(e.target.value)} placeholder="5000000" />
          {createErrors.amount && <p className="text-xs text-red-600 mt-1">{createErrors.amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tipe Periode</label>
          <select
            value={createPeriodType}
            onChange={(e) => handlePeriodTypeChange(e.target.value, setCreatePeriodType, setCreateStartDate, setCreateEndDate)}
            className="w-full border border-gray-300 rounded-md p-2 text-sm"
          >
            <option value="monthly">Bulanan (Otomatis awal s/d akhir bulan)</option>
            <option value="custom">Kustom (Tanggal bebas)</option>
          </select>
        </div>

        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <Input type="date" value={createStartDate} onChange={(e) => setCreateStartDate(e.target.value)} />
            {createErrors.start_date && <p className="text-xs text-red-600 mt-1">{createErrors.start_date}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
            <Input type="date" value={createEndDate} onChange={(e) => setCreateEndDate(e.target.value)} />
            {createErrors.end_date && <p className="text-xs text-red-600 mt-1">{createErrors.end_date}</p>}
          </div>
        </div>

        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsCreateOpen(false)} disabled={creating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleCreateSubmit} disabled={creating} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            {creating ? 'Menyimpan…' : 'Simpan Anggaran'}
          </Button>
        </div>
      </Modal>

      {/* ----- Detail Modal ----- */}
      <Modal isOpen={isDetailOpen} title="Detail Anggaran" onClose={() => setIsDetailOpen(false)}>
        {detailBudget && (
          <div className="space-y-3 text-sm text-gray-700">
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Nama Anggaran:</span>
              <span className="font-bold text-gray-900">{detailBudget.name}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Kategori:</span>
              <span>{detailBudget.category ? detailBudget.category.name : 'Semua Kategori Expense'}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Jumlah Anggaran:</span>
              <span className="font-bold text-gray-900">{formatRupiah(detailBudget.amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Total Terpakai:</span>
              <span className="font-bold text-red-600">{formatRupiah(detailBudget.spent_amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Sisa Anggaran:</span>
              <span className="font-bold text-green-700">{formatRupiah(detailBudget.remaining_amount)}</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Persentase Penggunaan:</span>
              <span className="font-bold">{detailBudget.spent_percentage}%</span>
            </div>
            <div className="flex justify-between py-1 border-b">
              <span className="font-semibold text-gray-500">Periode:</span>
              <span>{detailBudget.start_date} s/d {detailBudget.end_date} ({detailBudget.period_type})</span>
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
      <Modal isOpen={isEditOpen} title="Edit Anggaran" onClose={() => setIsEditOpen(false)}>
        {editErrors.general && <Alert type="error" className="mb-3">{editErrors.general}</Alert>}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Anggaran</label>
          <Input value={editName} onChange={(e) => setEditName(e.target.value)} placeholder="Nama Anggaran" />
          {editErrors.name && <p className="text-xs text-red-600 mt-1">{editErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Kategori Pengeluaran (Opsional)</label>
          {categoriesLoading ? (
            <p className="text-xs text-gray-500">Memuat kategori…</p>
          ) : (
            <select
              value={editCategoryId}
              onChange={(e) => setEditCategoryId(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2 text-sm"
            >
              <option value="">-- Semua Kategori Pengeluaran --</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
          {editErrors.category_id && <p className="text-xs text-red-600 mt-1">{editErrors.category_id}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah Batas Anggaran (Rp)</label>
          <Input type="number" value={editAmount} onChange={(e) => setEditAmount(e.target.value)} placeholder="Jumlah" />
          {editErrors.amount && <p className="text-xs text-red-600 mt-1">{editErrors.amount}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tipe Periode</label>
          <select
            value={editPeriodType}
            onChange={(e) => handlePeriodTypeChange(e.target.value, setEditPeriodType, setEditStartDate, setEditEndDate)}
            className="w-full border border-gray-300 rounded-md p-2 text-sm"
          >
            <option value="monthly">Bulanan</option>
            <option value="custom">Kustom</option>
          </select>
        </div>

        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <Input type="date" value={editStartDate} onChange={(e) => setEditStartDate(e.target.value)} />
            {editErrors.start_date && <p className="text-xs text-red-600 mt-1">{editErrors.start_date}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
            <Input type="date" value={editEndDate} onChange={(e) => setEditEndDate(e.target.value)} />
            {editErrors.end_date && <p className="text-xs text-red-600 mt-1">{editErrors.end_date}</p>}
          </div>
        </div>

        <div className="flex items-center mb-4">
          <input
            type="checkbox"
            id="editIsActive"
            checked={editIsActive}
            onChange={(e) => setEditIsActive(e.target.checked)}
            className="rounded text-indigo-600 border-gray-300 focus:ring-indigo-500"
          />
          <label htmlFor="editIsActive" className="ml-2 text-sm font-medium text-gray-700">
            Anggaran Aktif
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
      <Modal isOpen={isDeleteOpen} title="Konfirmasi Hapus Anggaran" onClose={() => setIsDeleteOpen(false)}>
        {deleteErrors.general && <Alert type="error" className="mb-3">{deleteErrors.general}</Alert>}
        {deleteBudget && (
          <div className="mb-4 text-sm text-gray-700 space-y-2">
            <p>Apakah Anda yakin ingin menghapus anggaran ini?</p>
            <div className="bg-red-50 border border-red-200 p-3 rounded-md space-y-1">
              <div><strong>Nama:</strong> {deleteBudget.name}</div>
              <div><strong>Jumlah:</strong> {formatRupiah(deleteBudget.amount)}</div>
              <div><strong>Periode:</strong> {deleteBudget.start_date} s/d {deleteBudget.end_date}</div>
            </div>
          </div>
        )}
        <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
          <Button onClick={() => setIsDeleteOpen(false)} disabled={deleting} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">
            Batal
          </Button>
          <Button onClick={handleDeleteConfirm} disabled={deleting} className="bg-red-600 hover:bg-red-700 text-white text-sm">
            {deleting ? 'Menghapus…' : 'Hapus Anggaran'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
