import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

/**
 * Simple Modal component (no portal).
 */
function Modal({ isOpen, title, onClose, children }) {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-lg max-w-md w-full p-4">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold">{title}</h2>
          <button onClick={onClose} className="text-gray-500 hover:text-gray-700 text-2xl leading-none">
            &times;
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

/**
 * Accounts page – displays the list of financial accounts belonging to the active household.
 * Includes create and edit modals.
 */
export default function Accounts() {
  // Context
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  // Account list state
  const [accounts, setAccounts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const requestIdRef = useRef(0); // race‑protection identifier

  // Success message after create/edit
  const [successMessage, setSuccessMessage] = useState(null);

  // ----- Create UI state -----
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createType, setCreateType] = useState('bank');
  const [createBalance, setCreateBalance] = useState('');
  const [createErrors, setCreateErrors] = useState({});
  const [creating, setCreating] = useState(false);

  // ----- Edit UI state -----
  const [editAccount, setEditAccount] = useState(null); // account object being edited
  const [editName, setEditName] = useState('');
  const [editType, setEditType] = useState('bank');
  const [editBalance, setEditBalance] = useState('');
  const [editErrors, setEditErrors] = useState({});
  const [updating, setUpdating] = useState(false);

  // Helpers
  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(number);
  };

  const typeLabel = (type) => {
    switch (type) {
      case 'bank':
        return 'Bank';
      case 'cash':
        return 'Cash';
      case 'e_wallet':
        return 'E‑Wallet';
      default:
        return type;
    }
  };

  // Fetch accounts
  const fetchAccounts = (householdId) => {
    if (!householdId) {
      setAccounts([]);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);
    api
      .get(`/households/${householdId}/accounts`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return; // stale response
        setAccounts(res.data.accounts ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          err.message ||
          'Gagal memuat data akun';
        setError(message);
        setAccounts([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

  // Load when household changes
  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setAccounts([]);
      setError(null);
      setLoading(false);
      return;
    }
    fetchAccounts(activeHouseholdId);
  }, [activeHouseholdId, contextLoading]);

  // Reset everything when household switches (including modals)
  useEffect(() => {
    setAccounts([]);
    setError(null);
    setLoading(false);
    requestIdRef.current = 0;
    setSuccessMessage(null);
    // reset create UI
    setIsCreateOpen(false);
    setCreateName('');
    setCreateType('bank');
    setCreateBalance('');
    setCreateErrors({});
    setCreating(false);
    // reset edit UI
    setEditAccount(null);
    setEditName('');
    setEditType('bank');
    setEditBalance('');
    setEditErrors({});
    setUpdating(false);
  }, [activeHouseholdId]);

  // ----- Create handlers -----
  const openCreate = () => {
    setIsCreateOpen(true);
    setCreateName('');
    setCreateType('bank');
    setCreateBalance('');
    setCreateErrors({});
  };

  const closeCreate = () => {
    setIsCreateOpen(false);
    setCreateErrors({});
    setCreating(false);
  };

  const validateCreate = () => {
    const errors = {};
    const trimmedName = createName.trim();
    if (!trimmedName) errors.name = 'Nama akun wajib diisi';
    else if (trimmedName.length > 255) errors.name = 'Maksimum 255 karakter';

    if (!createType) errors.type = 'Jenis akun wajib dipilih';
    else if (!['bank', 'cash', 'e_wallet'].includes(createType)) errors.type = 'Jenis akun tidak valid';

    const bal = Number(createBalance);
    if (createBalance === '' || Number.isNaN(bal)) errors.initial_balance = 'Saldo awal wajib angka';
    else if (bal < 0) errors.initial_balance = 'Saldo tidak boleh negatif';

    setCreateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateSubmit = () => {
    if (!validateCreate()) return;
    const payload = {
      name: createName.trim(),
      type: createType,
      initial_balance: Number(createBalance),
    };
    const currentHouseholdId = activeHouseholdId; // capture current id
    setCreating(true);
    api
      .post(`/households/${currentHouseholdId}/accounts`, payload)
      .then(() => {
        // ensure still same household
        if (activeHouseholdId !== currentHouseholdId) return;
        closeCreate();
        fetchAccounts(activeHouseholdId);
        setSuccessMessage('Akun berhasil dibuat');
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          // map backend field errors to our UI
          const apiErrors = {};
          for (const key in err.response.data.errors) {
            apiErrors[key] = err.response.data.errors[key].join(', ');
          }
          setCreateErrors(apiErrors);
        } else if (err.response && err.response.status === 403) {
          setCreateErrors({ general: 'Akses ditolak (403)' });
        } else if (err.response && err.response.status === 401) {
          // let auth flow handle – we just show generic message
          setCreateErrors({ general: 'Harus masuk (401)' });
        } else {
          setCreateErrors({ general: err.message || 'Terjadi kesalahan' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setCreating(false);
      });
  };

  // ----- Edit handlers -----
  const openEdit = (account) => {
    setEditAccount(account);
    setEditName(account.name);
    setEditType(account.type);
    setEditBalance(account.initial_balance);
    setEditErrors({});
  };

  const closeEdit = () => {
    setEditAccount(null);
    setEditErrors({});
    setUpdating(false);
  };

  const validateEdit = () => {
    const errors = {};
    const trimmedName = editName.trim();
    if (!trimmedName) errors.name = 'Nama akun wajib diisi';
    else if (trimmedName.length > 255) errors.name = 'Maksimum 255 karakter';

    if (!editType) errors.type = 'Jenis akun wajib dipilih';
    else if (!['bank', 'cash', 'e_wallet'].includes(editType)) errors.type = 'Jenis akun tidak valid';

    const bal = Number(editBalance);
    if (editBalance === '' || Number.isNaN(bal)) errors.initial_balance = 'Saldo awal wajib angka';
    else if (bal < 0) errors.initial_balance = 'Saldo tidak boleh negatif';

    setEditErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleEditSubmit = () => {
    if (!validateEdit()) return;
    // Determine changed fields
    const payload = {};
    if (editName.trim() !== editAccount.name) payload.name = editName.trim();
    if (editType !== editAccount.type) payload.type = editType;
    if (Number(editBalance) !== Number(editAccount.initial_balance)) payload.initial_balance = Number(editBalance);

    if (Object.keys(payload).length === 0) {
      setEditErrors({ general: 'Tidak ada perubahan' });
      return;
    }

    const currentHouseholdId = activeHouseholdId;
    const accountId = editAccount.id;
    setUpdating(true);
    api
      .patch(`/households/${currentHouseholdId}/accounts/${accountId}`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        closeEdit();
        fetchAccounts(activeHouseholdId);
        setSuccessMessage('Akun berhasil diupdate');
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const key in err.response.data.errors) {
            apiErrors[key] = err.response.data.errors[key].join(', ');
          }
          setEditErrors(apiErrors);
        } else if (err.response && err.response.status === 403) {
          setEditErrors({ general: 'Akses ditolak (403)' });
        } else if (err.response && err.response.status === 401) {
          setEditErrors({ general: 'Harus masuk (401)' });
        } else {
          setEditErrors({ general: err.message || 'Terjadi kesalahan' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setUpdating(false);
      });
  };

  // UI rendering
  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat data household…</p>
      </div>
    );
  }

  if (!activeHouseholdId) {
    return <Alert type="info">Belum ada household aktif. Pilih household pada selector.</Alert>;
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat daftar akun…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-gray-100 p-4">
        <Alert type="error">{error}</Alert>
        <Button onClick={() => fetchAccounts(activeHouseholdId)} className="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white">
          Coba Lagi
        </Button>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
      <Card className="max-w-4xl w-full space-y-4 p-6">
        <h1 className="text-2xl font-bold text-indigo-600">Akun Keuangan</h1>
        <p className="text-gray-600">
          Daftar akun keuangan yang dimiliki oleh household aktif. Saldo saat ini belum tersedia.
        </p>
        {successMessage && <Alert type="success" className="mb-4">{successMessage}</Alert>}
        <Button onClick={openCreate} className="bg-indigo-600 hover:bg-indigo-700 text-white mb-4">
          Tambah Akun
        </Button>
        {accounts.length === 0 ? (
          <Alert type="info">Belum ada akun keuangan.</Alert>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full table-auto">
              <thead className="bg-gray-100">
<tr>
                  <th className="px-4 py-2 text-left">Nama</th>
                  <th className="px-4 py-2 text-left">Tipe</th>
                  <th className="px-4 py-2 text-left">Saldo Awal</th>
                  <th className="px-4 py-2 text-left">Saldo Saat Ini</th>
                  <th className="px-4 py-2 text-left">Status</th>
                  <th className="px-4 py-2 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {accounts.map((account) => (
                  <tr key={account.id} className="border-b">
                    <td className="px-4 py-2">{account.name}</td>
                    <td className="px-4 py-2">{typeLabel(account.type)}</td>
                    <td className="px-4 py-2">{formatRupiah(account.initial_balance)}</td>
                    <td className="px-4 py-2">Belum tersedia</td>
                    <td className="px-4 py-2">
                      {account.is_active ? (
                        <span className="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Aktif</span>
                      ) : (
                        <span className="inline-block bg-gray-200 text-gray-800 text-xs px-2 py-1 rounded">Diarsipkan</span>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      <Button onClick={() => openEdit(account)} className="bg-yellow-600 hover:bg-yellow-700 text-white">
                        Edit
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateOpen} title="Tambah Akun" onClose={closeCreate}>
        {createErrors.general && <Alert type="error" className="mb-2">{createErrors.general}</Alert>}
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Akun</label>
          <Input value={createName} onChange={(e) => setCreateName(e.target.value)} placeholder="Contoh: BCA" />
          {createErrors.name && <p className="text-sm text-red-600">{createErrors.name}</p>}
        </div>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jenis Akun</label>
          <select
            value={createType}
            onChange={(e) => setCreateType(e.target.value)}
            className="w-full border rounded p-2"
          >
            <option value="bank">Bank</option>
            <option value="cash">Cash</option>
            <option value="e_wallet">E‑Wallet</option>
          </select>
          {createErrors.type && <p className="text-sm text-red-600">{createErrors.type}</p>}
        </div>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Awal</label>
          <Input
            type="number"
            min="0"
            value={createBalance}
            onChange={(e) => setCreateBalance(e.target.value)}
            placeholder="0"
          />
          {createErrors.initial_balance && <p className="text-sm text-red-600">{createErrors.initial_balance}</p>}
        </div>
        <div className="flex space-x-2 justify-end">
          <Button onClick={handleCreateSubmit} disabled={creating} className="bg-indigo-600 hover:bg-indigo-700 text-white">
            {creating ? 'Menyimpan…' : 'Simpan'}
          </Button>
          <Button onClick={closeCreate} disabled={creating} className="bg-gray-300 hover:bg-gray-400 text-gray-800">
            Batal
          </Button>
        </div>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={!!editAccount} title="Edit Akun" onClose={closeEdit}>
        {editErrors.general && <Alert type="error" className="mb-2">{editErrors.general}</Alert>}
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Akun</label>
          <Input value={editName} onChange={(e) => setEditName(e.target.value)} />
          {editErrors.name && <p className="text-sm text-red-600">{editErrors.name}</p>}
        </div>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jenis Akun</label>
          <select
            value={editType}
            onChange={(e) => setEditType(e.target.value)}
            className="w-full border rounded p-2"
          >
            <option value="bank">Bank</option>
            <option value="cash">Cash</option>
            <option value="e_wallet">E‑Wallet</option>
          </select>
          {editErrors.type && <p className="text-sm text-red-600">{editErrors.type}</p>}
        </div>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Awal</label>
          <Input
            type="number"
            min="0"
            value={editBalance}
            onChange={(e) => setEditBalance(e.target.value)}
          />
          {editErrors.initial_balance && <p className="text-sm text-red-600">{editErrors.initial_balance}</p>}
        </div>
        <div className="flex space-x-2 justify-end">
          <Button onClick={handleEditSubmit} disabled={updating} className="bg-indigo-600 hover:bg-indigo-700 text-white">
            {updating ? 'Menyimpan…' : 'Simpan'}
          </Button>
          <Button onClick={closeEdit} disabled={updating} className="bg-gray-300 hover:bg-gray-400 text-gray-800">
            Batal
          </Button>
        </div>
      </Modal>
    </div>
  );
}
