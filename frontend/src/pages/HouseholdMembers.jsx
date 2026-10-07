import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Input from '../components/Input';
import Button from '../components/Button';

/**
 * Household Members page – displays list of members and role management for owners.
 */
export default function HouseholdMembers() {
  const { activeHouseholdId, activeHousehold, loading: contextLoading } = useHousehold();

  // State for GET members
  const [members, setMembers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const requestIdRef = useRef(0);

  // State for Add Member (POST)
  const [isAddMode, setIsAddMode] = useState(false);
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('adult_member');
  const [addError, setAddError] = useState(null);
  const [addSuccess, setAddSuccess] = useState(null);
  const [saving, setSaving] = useState(false);
  const postRequestIdRef = useRef(0);

  // State for Delete Member (DELETE)
  const [deleteError, setDeleteError] = useState(null);
  const [deleteSuccess, setDeleteSuccess] = useState(null);
  const [deletingId, setDeletingId] = useState(null);
  const deleteRequestIdRef = useRef(0);

  // State for Update Role
  const [updatingId, setUpdatingId] = useState(null);

  const fetchMembers = (householdId) => {
    if (!householdId) {
      setMembers([]);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);
    api
      .get(`/households/${householdId}/members`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        setMembers(res.data.members ?? []);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          err.message ||
          'Gagal memuat anggota household';
        setError(message);
        setMembers([]);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setMembers([]);
      setError(null);
      setLoading(false);
      return;
    }
    fetchMembers(activeHouseholdId);
  }, [activeHouseholdId, contextLoading]);

  // Reset UI when household changes
  useEffect(() => {
    setIsAddMode(false);
    setEmail('');
    setRole('adult_member');
    setAddError(null);
    setAddSuccess(null);
    setSaving(false);
    setDeleteError(null);
    setDeleteSuccess(null);
    setDeletingId(null);
    deleteRequestIdRef.current = 0;
  }, [activeHouseholdId]);

  const enterAddMode = () => {
    setIsAddMode(true);
    setAddError(null);
    setAddSuccess(null);
    setEmail('');
    setRole('adult_member');
  };

  const handleCancel = () => {
    setIsAddMode(false);
    setEmail('');
    setRole('adult_member');
    setAddError(null);
    setAddSuccess(null);
  };

  const handleSubmit = () => {
    const trimmed = email.trim();
    if (!trimmed) {
      setAddError('Email tidak boleh kosong');
      return;
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(trimmed)) {
      setAddError('Format email tidak valid');
      return;
    }
    const currentHouseholdId = activeHouseholdId;
    postRequestIdRef.current += 1;
    const currentPostId = postRequestIdRef.current;
    setSaving(true);
    setAddError(null);
    setAddSuccess(null);
    api
      .post(`/households/${currentHouseholdId}/members`, { email: trimmed, role })
      .then(() => {
        if (postRequestIdRef.current !== currentPostId) return;
        if (activeHouseholdId !== currentHouseholdId) return;
        fetchMembers(currentHouseholdId);
        setAddSuccess('Anggota berhasil ditambahkan');
        setIsAddMode(false);
        setEmail('');
      })
      .catch((err) => {
        if (postRequestIdRef.current !== currentPostId) return;
        if (activeHouseholdId !== currentHouseholdId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          (err.response && err.response.data && err.response.data.errors && Object.values(err.response.data.errors).flat().join(', ')) ||
          err.message ||
          'Gagal menambahkan anggota';
        setAddError(message);
      })
      .finally(() => {
        if (postRequestIdRef.current !== currentPostId) return;
        setSaving(false);
      });
  };

  const handleRoleChange = (member, newRole) => {
    setUpdatingId(member.id);
    api
      .patch(`/households/${activeHouseholdId}/members/${member.id}`, { role: newRole })
      .then(() => {
        fetchMembers(activeHouseholdId);
      })
      .catch((err) => {
        alert('Gagal mengubah role anggota');
      })
      .finally(() => {
        setUpdatingId(null);
      });
  };

  const handleDelete = (member) => {
    const confirmDelete = window.confirm(`Apakah Anda yakin ingin menghapus anggota "${member.name ?? member.email}" dari household?`);
    if (!confirmDelete) return;
    const currentHouseholdId = activeHouseholdId;
    const memberId = member.id;
    deleteRequestIdRef.current += 1;
    const currentDeleteId = deleteRequestIdRef.current;
    setDeletingId(memberId);
    setDeleteError(null);
    setDeleteSuccess(null);
    api
      .delete(`/households/${currentHouseholdId}/members/${memberId}`)
      .then(() => {
        if (deleteRequestIdRef.current !== currentDeleteId) return;
        if (activeHouseholdId !== currentHouseholdId) return;
        fetchMembers(currentHouseholdId);
        setDeleteSuccess('Anggota berhasil dihapus');
      })
      .catch((err) => {
        if (deleteRequestIdRef.current !== currentDeleteId) return;
        if (activeHouseholdId !== currentHouseholdId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          (err.response && err.response.data && err.response.data.errors && Object.values(err.response.data.errors).flat().join(', ')) ||
          err.message ||
          'Gagal menghapus anggota';
        setDeleteError(message);
      })
      .finally(() => {
        if (deleteRequestIdRef.current !== currentDeleteId) return;
        setDeletingId(null);
      });
  };

  const formatRoleLabel = (r) => {
    if (r === 'household_owner') return 'Owner';
    if (r === 'child_member') return 'Child Member';
    return 'Adult Member';
  };

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
        <p className="text-gray-600">Memuat daftar anggota household…</p>
      </div>
    );
  }

  if (error) {
    return <Alert type="error">{error}</Alert>;
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
      <Card className="max-w-3xl w-full space-y-4 p-4">
        <h1 className="text-2xl font-bold text-indigo-600">Household Members</h1>
        <p className="text-gray-600">Daftar anggota yang terdaftar pada household yang aktif.</p>

        {activeHousehold && activeHousehold.role === 'household_owner' && !isAddMode && (
          <Button onClick={enterAddMode} className="bg-indigo-600 hover:bg-indigo-700 text-white">Tambah Anggota</Button>
        )}

        {isAddMode && (
          <div className="mt-4 space-y-3 bg-gray-50 p-4 rounded-lg border border-gray-200">
            {addError && <Alert type="error">{addError}</Alert>}
            {addSuccess && <Alert type="success">{addSuccess}</Alert>}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email Anggota</label>
              <Input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="user@example.com" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Role Anggota</label>
              <select
                value={role}
                onChange={(e) => setRole(e.target.value)}
                className="w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="adult_member">Adult Member (Anggota Dewasa)</option>
                <option value="child_member">Child Member (Anak / Restricted)</option>
              </select>
            </div>
            <div className="flex space-x-2 pt-2">
              <Button onClick={handleSubmit} disabled={saving} className="bg-indigo-600 hover:bg-indigo-700 text-white">
                {saving ? 'Menambahkan…' : 'Tambah'}
              </Button>
              <Button onClick={handleCancel} disabled={saving} className="bg-gray-300 hover:bg-gray-400 text-gray-800">Batal</Button>
            </div>
          </div>
        )}

        {members.length === 0 ? (
          <Alert type="info">Tidak ada anggota pada household ini.</Alert>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full table-auto mt-4">
              <thead className="bg-gray-100">
                <tr>
                  <th className="px-4 py-2 text-left">Nama</th>
                  <th className="px-4 py-2 text-left">Email</th>
                  <th className="px-4 py-2 text-left">Peran</th>
                  {activeHousehold && activeHousehold.role === 'household_owner' && (
                    <th className="px-4 py-2 text-left">Aksi</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {members.map((m) => (
                  <tr key={m.id} className="border-b">
                    <td className="px-4 py-2 font-medium">{m.name ?? ''}</td>
                    <td className="px-4 py-2 text-sm text-gray-600">{m.email ?? ''}</td>
                    <td className="px-4 py-2">
                      {activeHousehold && activeHousehold.role === 'household_owner' && m.role !== 'household_owner' ? (
                        <select
                          value={m.role === 'household_member' ? 'adult_member' : m.role}
                          disabled={updatingId === m.id}
                          onChange={(e) => handleRoleChange(m, e.target.value)}
                          className="px-2 py-1 border rounded text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        >
                          <option value="adult_member">Adult Member</option>
                          <option value="child_member">Child Member</option>
                        </select>
                      ) : (
                        <span className={`inline-flex px-2 py-1 text-xs rounded font-medium ${
                          m.role === 'household_owner' ? 'bg-indigo-100 text-indigo-800' :
                          m.role === 'child_member' ? 'bg-amber-100 text-amber-800' :
                          'bg-emerald-100 text-emerald-800'
                        }`}>
                          {formatRoleLabel(m.role)}
                        </span>
                      )}
                    </td>
                    {activeHousehold && activeHousehold.role === 'household_owner' && (
                      <td className="px-4 py-2">
                        {m.role !== 'household_owner' && (
                          <Button onClick={() => handleDelete(m)} disabled={deletingId === m.id} className="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1">
                            {deletingId === m.id ? 'Menghapus…' : 'Hapus'}
                          </Button>
                        )}
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
