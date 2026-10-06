import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Input from '../components/Input';
import Button from '../components/Button';

/**
 * Household Settings page – implements read‑only view for members and edit
 * functionality for owners. Mirrors the pattern used in HouseholdProfile.jsx.
 */
export default function HouseholdSettings() {
  // ----- Context -----
  const { activeHouseholdId, activeHousehold, loading: contextLoading } = useHousehold();

  // ----- Local state -----
  const [settings, setSettings] = useState(null); // raw settings object from backend
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [isEditMode, setIsEditMode] = useState(false);
  const [editTimezone, setEditTimezone] = useState('');
  const [editCurrency, setEditCurrency] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);
  const [saveSuccess, setSaveSuccess] = useState(null);

  // ----- Request guards (stale‑response protection) -----
  const requestIdRef = useRef(0);
  const patchRequestIdRef = useRef(0);

  // ----- Fetch settings when household is ready -----
  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) {
      setSettings(null);
      setError(null);
      setLoading(false);
      return;
    }
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);
    api
      .get(`/households/${activeHouseholdId}/settings`)
      .then((res) => {
        if (requestIdRef.current !== currentRequestId) return;
        // API returns { settings: {...} } – may be null
        setSettings(res.data.settings ?? null);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          err.message ||
          'Gagal memuat settings household';
        setError(message);
        setSettings(null);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  }, [activeHouseholdId, contextLoading]);

  // ----- Reset edit mode / fields when household switches -----
  useEffect(() => {
    setIsEditMode(false);
    setSaveError(null);
    setSaveSuccess(null);
    setSaving(false);
    if (settings) {
      setEditTimezone(settings.timezone ?? '');
      setEditCurrency(settings.currency ?? '');
    } else {
      setEditTimezone('');
      setEditCurrency('');
    }
  }, [activeHouseholdId, settings]);

  // ----- Handlers -----
  const enterEditMode = () => {
    setIsEditMode(true);
    setSaveError(null);
    setSaveSuccess(null);
    setEditTimezone(settings?.timezone ?? '');
    setEditCurrency(settings?.currency ?? '');
  };

  const handleCancel = () => {
    setIsEditMode(false);
    setSaveError(null);
    setSaveSuccess(null);
    setEditTimezone(settings?.timezone ?? '');
    setEditCurrency(settings?.currency ?? '');
  };

  const handleSave = () => {
    // Minimal client validation
    if (!editTimezone?.trim()) {
      setSaveError('Timezone tidak boleh kosong');
      return;
    }
    if (!editCurrency?.trim()) {
      setSaveError('Currency tidak boleh kosong');
      return;
    }

    patchRequestIdRef.current += 1;
    const currentPatchId = patchRequestIdRef.current;
    const payload = {
      settings: {
        timezone: editTimezone.trim(),
        currency: editCurrency.trim(),
        // Preserve locale if it existed (read‑only for user)
        ...(settings && settings.locale !== undefined ? { locale: settings.locale } : {}),
      },
    };
    setSaving(true);
    setSaveError(null);
    setSaveSuccess(null);
    api
      .patch(`/households/${activeHouseholdId}/settings`, payload)
      .then((res) => {
        if (patchRequestIdRef.current !== currentPatchId) return;
        // Backend returns updated settings object
        setSettings(res.data.settings ?? null);
        setSaveSuccess('Pengaturan berhasil disimpan');
        setIsEditMode(false);
      })
      .catch((err) => {
        if (patchRequestIdRef.current !== currentPatchId) return;
        const message =
          (err.response && err.response.data && err.response.data.message) ||
          err.message ||
          'Gagal menyimpan settings household';
        setSaveError(message);
      })
      .finally(() => {
        if (patchRequestIdRef.current !== currentPatchId) return;
        setSaving(false);
      });
  };

  // ----- Rendering -----
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
        <p className="text-gray-600">Memuat pengaturan household…</p>
      </div>
    );
  }

  if (error) {
    return <Alert type="error">{error}</Alert>;
  }

  // At this point we have `settings` (may be null)
  const isOwner = activeHousehold && activeHousehold.role === 'household_owner';
  const displayTimezone = settings?.timezone ?? '';
  const displayCurrency = settings?.currency ?? '';
  const displayLocale = settings?.locale ?? '';

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
      <Card className="max-w-md w-full space-y-4 p-4">
        <h1 className="text-2xl font-bold text-indigo-600">Household Settings</h1>
        <p className="text-gray-600">Kelola pengaturan khusus untuk household yang aktif.</p>

        {/* Success / error alerts for save operation */}
        {saveSuccess && <Alert type="success">{saveSuccess}</Alert>}
        {saveError && <Alert type="error">{saveError}</Alert>}

        {isEditMode ? (
          // ----- Edit form -----
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
              <Input
                value={editTimezone}
                onChange={(e) => setEditTimezone(e.target.value)}
                placeholder="Timezone (e.g., UTC)"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Currency</label>
              <Input
                value={editCurrency}
                onChange={(e) => setEditCurrency(e.target.value)}
                placeholder="Currency (e.g., IDR)"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Locale (read‑only)</label>
              <Input value={displayLocale} readOnly placeholder="Locale" />
            </div>
            <div className="flex space-x-2 mt-4">
              <Button onClick={handleSave} disabled={saving} className="bg-indigo-600 hover:bg-indigo-700 text-white">
                {saving ? 'Menyimpan…' : 'Save'}
              </Button>
              <Button onClick={handleCancel} disabled={saving} className="bg-gray-300 hover:bg-gray-400 text-gray-800">
                Cancel
              </Button>
            </div>
          </>
        ) : (
          // ----- View mode -----
          <>
            <div className="mb-2">
              <strong>Timezone:</strong> {displayTimezone || <span className="text-gray-500">(tidak diset)</span>}
            </div>
            <div className="mb-2">
              <strong>Currency:</strong> {displayCurrency || <span className="text-gray-500">(tidak diset)</span>}
            </div>
            <div className="mb-2">
              <strong>Locale:</strong> {displayLocale || <span className="text-gray-500">(tidak diset)</span>}
            </div>
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
