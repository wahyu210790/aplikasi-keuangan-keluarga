import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

/**
 * Responsive Modal component
 */
function Modal({ isOpen, title, onClose, children }) {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
      <div className="bg-white rounded-lg shadow-lg max-w-md w-full p-4 sm:p-6 my-8">
        <div className="flex justify-between items-center mb-4 border-b pb-2">
          <h2 className="text-lg sm:text-xl font-bold text-gray-800">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-2xl leading-none px-2 py-1">
            &times;
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

export default function Accounts() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();
  const { user: currentUser } = useAuth();

  const [accounts, setAccounts] = useState([]);
  const [members, setMembers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const requestIdRef = useRef(0);

  const [successMessage, setSuccessMessage] = useState(null);

  // ----- Create UI state -----
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createType, setCreateType] = useState('bank');
  const [createBalance, setCreateBalance] = useState('');
  const [createUserId, setCreateUserId] = useState('');
  const [createErrors, setCreateErrors] = useState({});
  const [creating, setCreating] = useState(false);

  // ----- Edit UI state -----
  const [editAccount, setEditAccount] = useState(null);
  const [editName, setEditName] = useState('');
  const [editType, setEditType] = useState('bank');
  const [editBalance, setEditBalance] = useState('');
  const [editUserId, setEditUserId] = useState('');
  const [editErrors, setEditErrors] = useState({});
  const [updating, setUpdating] = useState(false);

  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  const typeLabel = (type) => {
    switch (type) {
      case 'bank': return 'Bank';
      case 'cash': return 'Kas';
      case 'e_wallet': return 'E‑Wallet';
      default: return type;
    }
  };

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

    Promise.all([
      api.get(`/households/${householdId}/accounts`),
      api.get(`/households/${householdId}/members`),
    ])
      .then(([accRes, memRes]) => {
        if (requestIdRef.current !== currentRequestId) return;
        setAccounts(accRes.data.accounts ?? []);
        setMembers(memRes.data.members ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const message = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat data akun';
        setError(message);
        setAccounts([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

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

  useEffect(() => {
    setAccounts([]);
    setMembers([]);
    setError(null);
    setLoading(false);
    requestIdRef.current = 0;
    setSuccessMessage(null);
    setIsCreateOpen(false);
    setEditAccount(null);
  }, [activeHouseholdId]);

  // Compute active accounts quota per member
  const getActiveAccountCount = (userId) => {
    if (!userId) return 0;
    return accounts.filter((a) => (a.user_id === Number(userId) || (a.user_id === null && userId === currentUser?.id)) && a.is_active).length;
  };

  const openCreate = () => {
    setIsCreateOpen(true);
    setCreateName('');
    setCreateType('bank');
    setCreateBalance('');
    setCreateUserId(currentUser ? String(currentUser.id) : '');
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

    const bal = Number(createBalance);
    if (createBalance === '' || Number.isNaN(bal)) errors.initial_balance = 'Saldo awal wajib angka';
    else if (bal < 0) errors.initial_balance = 'Saldo tidak boleh negatif';

    const targetId = createUserId || currentUser?.id;
    if (targetId && getActiveAccountCount(Number(targetId)) >= 3) {
      errors.user_id = 'Pemilik ini sudah memiliki batas maksimum 3 akun aktif.';
    }

    setCreateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateSubmit = () => {
    if (!validateCreate()) return;
    const payload = {
      name: createName.trim(),
      type: createType,
      initial_balance: Number(createBalance),
      user_id: createUserId ? Number(createUserId) : currentUser?.id,
    };
    const currentHouseholdId = activeHouseholdId;
    setCreating(true);
    api
      .post(`/households/${currentHouseholdId}/accounts`, payload)
      .then(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        closeCreate();
        fetchAccounts(activeHouseholdId);
        setSuccessMessage('Akun keuangan berhasil dibuat');
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
          const apiErrors = {};
          for (const key in err.response.data.errors) {
            apiErrors[key] = err.response.data.errors[key].join(', ');
          }
          setCreateErrors(apiErrors);
        } else {
          setCreateErrors({ general: err.response?.data?.message || err.message || 'Terjadi kesalahan' });
        }
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setCreating(false);
      });
  };

  const openEdit = (account) => {
    setEditAccount(account);
    setEditName(account.name);
    setEditType(account.type);
    setEditBalance(account.initial_balance);
    setEditUserId(account.user_id ? String(account.user_id) : '');
    setEditErrors({});
  };

  const closeEdit = () => {
    setEditAccount(null);
    setEditErrors({});
    setUpdating(false);
  };

  const handleEditSubmit = () => {
    const payload = {};
    if (editName.trim() !== editAccount.name) payload.name = editName.trim();
    if (editType !== editAccount.type) payload.type = editType;
    if (Number(editBalance) !== Number(editAccount.initial_balance)) payload.initial_balance = Number(editBalance);
    if (editUserId && Number(editUserId) !== Number(editAccount.user_id)) payload.user_id = Number(editUserId);

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
        setSuccessMessage('Akun berhasil diperbarui');
      })
      .catch((err) => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setEditErrors({ general: err.response?.data?.message || err.message || 'Gagal mengedit akun' });
      })
      .finally(() => {
        if (activeHouseholdId !== currentHouseholdId) return;
        setUpdating(false);
      });
  };

  const handleArchive = (account) => {
    if (!window.confirm(`Arsipkan akun "${account.name}"?`)) return;
    api
      .patch(`/households/${activeHouseholdId}/accounts/${account.id}/archive`)
      .then(() => {
        fetchAccounts(activeHouseholdId);
        setSuccessMessage(`Akun "${account.name}" berhasil diarsipkan`);
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Gagal mengarsipkan akun');
      });
  };

  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600 font-medium">Memuat data household...</p>
      </div>
    );
  }

  if (!activeHouseholdId) {
    return (
      <div className="max-w-4xl mx-auto p-4">
        <Alert type="info">Belum ada household aktif. Pilih household pada selector di atas.</Alert>
      </div>
    );
  }

  return (
    <div className="flex justify-center bg-gray-100 p-2 sm:p-4">
      <Card className="max-w-5xl w-full space-y-6 p-4 sm:p-6">
        {/* Header & Quota summary */}
        <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b pb-4">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-indigo-600">Akun Keuangan Anggota</h1>
            <p className="text-xs sm:text-sm text-gray-500 mt-1">
              Setiap anggota keluarga dapat memiliki maks. 3 akun aktif.
            </p>
          </div>
          <Button onClick={openCreate} className="bg-indigo-600 hover:bg-indigo-700 text-white w-full sm:w-auto text-sm py-2 px-4">
            + Tambah Akun
          </Button>
        </div>

        {/* Active Member Quotas Badges */}
        {members.length > 0 && (
          <div className="bg-indigo-50/60 border border-indigo-100 rounded-lg p-3">
            <span className="text-xs font-bold text-indigo-800 uppercase tracking-wider block mb-2">Kuota Akun Aktif Anggota:</span>
            <div className="flex flex-wrap gap-2">
              {members.map((m) => {
                const count = getActiveAccountCount(m.user_id);
                const isFull = count >= 3;
                return (
                  <div
                    key={m.id}
                    className={`px-2.5 py-1 rounded-full text-xs font-semibold flex items-center space-x-1.5 border ${
                      isFull ? 'bg-red-50 text-red-700 border-red-200' : 'bg-white text-gray-700 border-gray-200'
                    }`}
                  >
                    <span>{m.user?.name || `Member #${m.user_id}`}</span>
                    <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${isFull ? 'bg-red-600 text-white' : 'bg-indigo-100 text-indigo-800'}`}>
                      {count}/3
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {successMessage && <Alert type="success">{successMessage}</Alert>}
        {error && <Alert type="error">{error}</Alert>}

        {loading ? (
          <div className="py-12 text-center text-gray-500 font-medium">Memuat daftar akun...</div>
        ) : accounts.length === 0 ? (
          <Alert type="info">Belum ada akun keuangan. Klik "Tambah Akun" untuk membuat baru.</Alert>
        ) : (
          <div>
            {/* Mobile View: Stacked Card Layout */}
            <div className="block md:hidden space-y-3">
              {accounts.map((account) => (
                <div key={account.id} className="bg-white border rounded-lg p-3.5 shadow-sm space-y-2">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="font-bold text-gray-900 text-sm">{account.name}</h3>
                      <div className="flex items-center space-x-2 mt-0.5">
                        <span className="text-[11px] bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-medium">{typeLabel(account.type)}</span>
                        <span className="text-[11px] text-indigo-700 font-semibold">👤 {account.user_name || 'Household'}</span>
                      </div>
                    </div>
                    {account.is_active ? (
                      <span className="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-0.5 rounded">Aktif</span>
                    ) : (
                      <span className="bg-gray-200 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded">Diarsipkan</span>
                    )}
                  </div>
                  <div className="border-t pt-2 flex justify-between items-center text-xs">
                    <span className="text-gray-500">Saldo Awal: <strong className="text-gray-800">{formatRupiah(account.initial_balance)}</strong></span>
                    <div className="flex space-x-2">
                      <button onClick={() => openEdit(account)} className="text-indigo-600 hover:text-indigo-900 font-bold">Edit</button>
                      {account.is_active && (
                        <button onClick={() => handleArchive(account)} className="text-red-600 hover:text-red-800 font-bold">Arsip</button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {/* Desktop View: Full Responsive Table */}
            <div className="hidden md:block overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left font-bold text-gray-700">Nama Akun</th>
                    <th className="px-4 py-3 text-left font-bold text-gray-700">Pemilik / Member</th>
                    <th className="px-4 py-3 text-left font-bold text-gray-700">Tipe</th>
                    <th className="px-4 py-3 text-left font-bold text-gray-700">Saldo Awal</th>
                    <th className="px-4 py-3 text-left font-bold text-gray-700">Status</th>
                    <th className="px-4 py-3 text-right font-bold text-gray-700">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                  {accounts.map((account) => (
                    <tr key={account.id} className="hover:bg-gray-50/50">
                      <td className="px-4 py-3 font-semibold text-gray-900">{account.name}</td>
                      <td className="px-4 py-3 text-indigo-700 font-medium">👤 {account.user_name || 'Household'}</td>
                      <td className="px-4 py-3 text-gray-600">{typeLabel(account.type)}</td>
                      <td className="px-4 py-3 font-medium text-gray-800">{formatRupiah(account.initial_balance)}</td>
                      <td className="px-4 py-3">
                        {account.is_active ? (
                          <span className="inline-block bg-green-100 text-green-800 text-xs px-2.5 py-0.5 rounded-full font-bold">Aktif</span>
                        ) : (
                          <span className="inline-block bg-gray-200 text-gray-700 text-xs px-2.5 py-0.5 rounded-full font-bold">Diarsipkan</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right space-x-2">
                        <button onClick={() => openEdit(account)} className="text-indigo-600 hover:text-indigo-900 font-bold">Edit</button>
                        {account.is_active && (
                          <button onClick={() => handleArchive(account)} className="text-red-600 hover:text-red-800 font-bold">Arsip</button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateOpen} title="Tambah Akun Keuangan" onClose={closeCreate}>
        {createErrors.general && <Alert type="error" className="mb-3">{createErrors.general}</Alert>}
        
        {members.length > 0 && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Pemilik Akun (Member)</label>
            <select
              value={createUserId}
              onChange={(e) => setCreateUserId(e.target.value)}
              className="w-full border rounded-lg p-2.5 text-sm bg-white"
            >
              {members.map((m) => {
                const count = getActiveAccountCount(m.user_id);
                return (
                  <option key={m.id} value={m.user_id} disabled={count >= 3}>
                    {m.user?.name || `Member #${m.user_id}`} ({count}/3 akun aktif) {count >= 3 ? '- Penuh' : ''}
                  </option>
                );
              })}
            </select>
            {createErrors.user_id && <p className="text-xs text-red-600 mt-1">{createErrors.user_id}</p>}
          </div>
        )}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Akun</label>
          <Input value={createName} onChange={(e) => setCreateName(e.target.value)} placeholder="Contoh: BCA Budi" />
          {createErrors.name && <p className="text-xs text-red-600 mt-1">{createErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jenis Akun</label>
          <select value={createType} onChange={(e) => setCreateType(e.target.value)} className="w-full border rounded-lg p-2.5 text-sm bg-white">
            <option value="bank">Bank</option>
            <option value="cash">Kas Tunai</option>
            <option value="e_wallet">E‑Wallet</option>
          </select>
          {createErrors.type && <p className="text-xs text-red-600 mt-1">{createErrors.type}</p>}
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Awal</label>
          <Input type="number" min="0" value={createBalance} onChange={(e) => setCreateBalance(e.target.value)} placeholder="0" />
          {createErrors.initial_balance && <p className="text-xs text-red-600 mt-1">{createErrors.initial_balance}</p>}
        </div>

        <div className="flex space-x-2 justify-end">
          <Button onClick={closeCreate} disabled={creating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">Batal</Button>
          <Button onClick={handleCreateSubmit} disabled={creating} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            {creating ? 'Menyimpan...' : 'Simpan Akun'}
          </Button>
        </div>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={!!editAccount} title="Edit Akun Keuangan" onClose={closeEdit}>
        {editErrors.general && <Alert type="error" className="mb-3">{editErrors.general}</Alert>}
        
        {members.length > 0 && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Pemilik Akun</label>
            <select value={editUserId} onChange={(e) => setEditUserId(e.target.value)} className="w-full border rounded-lg p-2.5 text-sm bg-white">
              {members.map((m) => (
                <option key={m.id} value={m.user_id}>
                  {m.user?.name || `Member #${m.user_id}`}
                </option>
              ))}
            </select>
          </div>
        )}

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Akun</label>
          <Input value={editName} onChange={(e) => setEditName(e.target.value)} />
          {editErrors.name && <p className="text-xs text-red-600 mt-1">{editErrors.name}</p>}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Jenis Akun</label>
          <select value={editType} onChange={(e) => setEditType(e.target.value)} className="w-full border rounded-lg p-2.5 text-sm bg-white">
            <option value="bank">Bank</option>
            <option value="cash">Kas Tunai</option>
            <option value="e_wallet">E‑Wallet</option>
          </select>
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-1">Saldo Awal</label>
          <Input type="number" min="0" value={editBalance} onChange={(e) => setEditBalance(e.target.value)} />
        </div>

        <div className="flex space-x-2 justify-end">
          <Button onClick={closeEdit} disabled={updating} className="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm">Batal</Button>
          <Button onClick={handleEditSubmit} disabled={updating} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            {updating ? 'Menyimpan...' : 'Simpan Perubahan'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
