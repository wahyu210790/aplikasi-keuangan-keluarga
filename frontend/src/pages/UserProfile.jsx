import React, { useEffect, useState } from 'react';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

export default function UserProfile() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);

  // Profile Edit Form State
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [profileSubmitting, setProfileSubmitting] = useState(false);

  // Password Change Form State
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
  const [passwordSubmitting, setPasswordSubmitting] = useState(false);
  const [passwordErrors, setPasswordErrors] = useState({});

  const fetchProfile = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get('/profile');
      const u = res.data.user;
      setUser(u);
      setName(u.name || '');
      setEmail(u.email || '');
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat profil pengguna');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProfile();
  }, []);

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setProfileSubmitting(true);
    setSuccessMessage(null);
    setError(null);

    try {
      const res = await api.patch('/profile', { name, email });
      setSuccessMessage('Profil pengguna berhasil diperbarui.');
      setUser(res.data.user);
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memperbarui profil');
    } finally {
      setProfileSubmitting(false);
    }
  };

  const handleChangePassword = async (e) => {
    e.preventDefault();
    setPasswordSubmitting(true);
    setPasswordErrors({});
    setSuccessMessage(null);
    setError(null);

    try {
      await api.post('/profile/change-password', {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation
      });
      setSuccessMessage('Password berhasil diperbarui.');
      setCurrentPassword('');
      setNewPassword('');
      setNewPasswordConfirmation('');
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setPasswordErrors(err.response.data.errors);
      } else {
        setError(err.response?.data?.message || 'Gagal mengubah password');
      }
    } finally {
      setPasswordSubmitting(false);
    }
  };

  const handleRevokeTokens = async () => {
    if (!window.confirm('Apakah Anda yakin ingin mencabut seluruh sesi login di perangkat lain?')) return;
    setError(null);
    try {
      await api.post('/profile/tokens/revoke');
      setSuccessMessage('Seluruh sesi login perangkat telah dicabut.');
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal mencabut sesi token');
    }
  };

  if (loading) {
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
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Profil & Keamanan Akun</h1>
        <p className="text-gray-600 text-sm">Kelola informasi diri, kata sandi, dan keamanan sesi perangkat</p>
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

      {/* Profile Details & Update */}
      <Card className="p-6 bg-white space-y-4">
        <h2 className="text-lg font-bold text-gray-800 border-b pb-2">Informasi Profil</h2>
        <form onSubmit={handleUpdateProfile} className="space-y-4">
          <Input
            label="Nama Lengkap"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
          />
          <Input
            label="Alamat Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
          <div className="flex justify-between items-center pt-2">
            <span className="text-xs text-gray-500">
              Role: <strong className="capitalize">{user?.global_role || 'User'}</strong>
            </span>
            <Button type="submit" disabled={profileSubmitting} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              {profileSubmitting ? 'Menyimpan...' : 'Simpan Perubahan'}
            </Button>
          </div>
        </form>
      </Card>

      {/* Change Password */}
      <Card className="p-6 bg-white space-y-4">
        <h2 className="text-lg font-bold text-gray-800 border-b pb-2">Ubah Kata Sandi</h2>
        <form onSubmit={handleChangePassword} className="space-y-4">
          <Input
            label="Kata Sandi Saat Ini"
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            error={passwordErrors.current_password?.[0]}
            required
          />
          <Input
            label="Kata Sandi Baru"
            type="password"
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            error={passwordErrors.new_password?.[0]}
            required
          />
          <Input
            label="Konfirmasi Kata Sandi Baru"
            type="password"
            value={newPasswordConfirmation}
            onChange={(e) => setNewPasswordConfirmation(e.target.value)}
            required
          />
          <div className="flex justify-end pt-2">
            <Button type="submit" disabled={passwordSubmitting} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              {passwordSubmitting ? 'Mengubah...' : 'Perbarui Password'}
            </Button>
          </div>
        </form>
      </Card>

      {/* Security Sessions */}
      <Card className="p-6 bg-white space-y-4">
        <h2 className="text-lg font-bold text-red-600 border-b pb-2">Sesi & Keamanan Perangkat</h2>
        <p className="text-sm text-gray-600">
          Jika Anda merasa akun Anda digunakan di perangkat tidak dikenal, Anda dapat mencabut seluruh sesi login Sanctum aktif di perangkat lain.
        </p>
        <div>
          <Button onClick={handleRevokeTokens} className="bg-red-600 hover:bg-red-700 text-white">
            Keluar dari Semua Sesi Perangkat
          </Button>
        </div>
      </Card>
    </div>
  );
}
