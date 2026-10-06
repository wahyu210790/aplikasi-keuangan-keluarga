import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Input from '../components/Input';
import Button from '../components/Button';

export default function HouseholdProfile() {
  // Context values
  const { activeHouseholdId, activeHousehold, loading: contextLoading } = useHousehold();

  // Local state for profile data (GET)
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Edit mode state (allowed for owner only)
  const [isEditMode, setIsEditMode] = useState(false);
  const [editName, setEditName] = useState('');
  const [editDescription, setEditDescription] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);
  const [saveSuccess, setSaveSuccess] = useState(null);

  // Ref to track the most recent GET request (stale‑response guard)
  const requestIdRef = useRef(0);
  // Ref to track the most recent PATCH request (stale‑response guard)
  const patchRequestIdRef = useRef(0);

  // Effect: fetch profile when context is ready and id is present
  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setProfile(null);
      setError(null);
      setLoading(false);
      return;
    }

    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);
    api
      .get(`/households/${activeHouseholdId}`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        // API returns the household object directly (see GET implementation)
        setProfile(res.data);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          err.message ||
          'Gagal memuat profil household';
        setError(message);
        setProfile(null);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  }, [activeHouseholdId, contextLoading]);

  // Reset edit mode / fields when active household changes (including during an edit)
  useEffect(() => {
    // If household switches, exit edit mode and clear any pending PATCH guard
    setIsEditMode(false);
    setSaveError(null);
    setSaveSuccess(null);
    setSaving(false);
    // Reset edit fields to the latest profile (if available)
    if (profile) {
      setEditName(profile.name || '');
      setEditDescription(profile.description || '');
    }
  }, [activeHouseholdId, profile]);

  // ---------- UI rendering ----------

  // 1. Context still loading (we don't yet know if a household exists)
  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat data household...</p>
      </div>
    );
  }

  // 2. No active household selected
  if (!activeHouseholdId) {
    return <Alert type="info">Belum ada household aktif. Pilih household pada selector.</Alert>;
  }

  // 3. Profile fetch in progress
  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat profil household...</p>
      </div>
    );
  }

  // 4. Error state from GET
  if (error) {
    return <Alert type="error">{error}</Alert>;
  }

  // 5. Success – display profile data (view or edit mode)
  if (profile) {
    const isOwner = activeHousehold && activeHousehold.role === 'household_owner';
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <Card className="max-w-md w-full space-y-4 p-4">
          <h1 className="text-2xl font-bold text-indigo-600">Household Profile</h1>
          <p className="text-gray-600">Berikut ini menampilkan informasi household yang sedang aktif.</p>

          {/* Success / error alerts for save operation */}
          {saveSuccess && <Alert type="success">{saveSuccess}</Alert>}
          {saveError && <Alert type="error">{saveError}</Alert>}

          {isEditMode ? (
            // ---------- Edit Form ----------
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                <Input
                  value={editName}
                  onChange={(e) => setEditName(e.target.value)}
                  placeholder="Nama household"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  rows={3}
                  value={editDescription}
                  onChange={(e) => setEditDescription(e.target.value)}
                  placeholder="Deskripsi (optional)"
                />
              </div>
              <div className="flex space-x-2 mt-4">
                <Button
                  onClick={handleSave}
                  disabled={saving}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                  {saving ? 'Menyimpan…' : 'Save'}
                </Button>
                <Button onClick={handleCancel} disabled={saving} className="bg-gray-300 hover:bg-gray-400 text-gray-800">
                  Cancel
                </Button>
              </div>
            </>
          ) : (
            // ---------- View Mode ----------
            <>
              <h2 className="text-xl font-semibold text-indigo-600">{profile.name}</h2>
              <p className="mb-1"><strong>ID:</strong> {profile.id}</p>
              {profile.description && (
                <p className="mb-1"><strong>Deskripsi:</strong> {profile.description}</p>
              )}
              {activeHousehold && (
                <p className="mb-1"><strong>Peran Anda:</strong> {activeHousehold.role === 'household_owner' ? 'Owner' : activeHousehold.role === 'household_member' ? 'Member' : activeHousehold.role}</p>
              )}
              {/* Edit button visible only to owners */}
              {isOwner && (
                <Button onClick={enterEditMode} className="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white">
                  Edit
                </Button>
              )}
            </>
          )}
        </Card>
      </div>
    );
  }

  // Fallback – should not happen, but keep UI safe
  return null;

  // ---------- Handlers ----------
  function enterEditMode() {
    setIsEditMode(true);
    setSaveError(null);
    setSaveSuccess(null);
    setEditName(profile?.name || '');
    setEditDescription(profile?.description || '');
  }

  function handleCancel() {
    // Exit edit mode and reset fields to current profile
    setIsEditMode(false);
    setSaveError(null);
    setSaveSuccess(null);
    setEditName(profile?.name || '');
    setEditDescription(profile?.description || '');
  }

  async function handleSave() {
    // Basic client‑side validation
    const trimmedName = editName.trim();
    if (!trimmedName) {
      setSaveError('Nama tidak boleh kosong.');
      return;
    }

    if (!activeHouseholdId) {
      setSaveError('Tidak ada household yang dipilih.');
      return;
    }

    setSaving(true);
    setSaveError(null);
    setSaveSuccess(null);

    // Capture the household id and create a unique request identifier
    const currentHouseholdId = activeHouseholdId;
    patchRequestIdRef.current += 1;
    const currentPatchId = patchRequestIdRef.current;

    const payload = {
      name: trimmedName,
    };
    // Include description only if it is not undefined (allow null)
    if (editDescription !== undefined) {
      payload.description = editDescription;
    }

    try {
      const res = await api.patch(`/households/${currentHouseholdId}`, payload);
      // Stale‑response guard: ensure this is the latest PATCH and household hasn't changed
      if (patchRequestIdRef.current !== currentPatchId) return;
      if (activeHouseholdId !== currentHouseholdId) return;

      // Backend returns the updated household inside "household" key; fallback to raw data
      const updated = res.data && res.data.household ? res.data.household : res.data;
      setProfile(updated);
      setSaveSuccess('Profil household berhasil diperbarui.');
      setIsEditMode(false);
    } catch (err) {
      if (patchRequestIdRef.current !== currentPatchId) return;
      if (activeHouseholdId !== currentHouseholdId) return;
      const message =
        (err.response && err.response.data && err.response.data.message) ||
        (err.response && err.response.data && err.response.data.errors && Object.values(err.response.data.errors).flat().join(', ')) ||
        err.message ||
        'Gagal menyimpan profil household';
      setSaveError(message);
    } finally {
      // Only clear saving flag if this is still the latest PATCH request
      if (patchRequestIdRef.current === currentPatchId) {
        setSaving(false);
      }
    }
  }
}
