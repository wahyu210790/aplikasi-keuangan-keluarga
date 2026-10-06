import React, { useEffect, useState } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';

export default function Subscription() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  const [stats, setStats] = useState(null);
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);
  const [submittingId, setSubmittingId] = useState(null);

  const fetchData = async () => {
    if (!activeHouseholdId) return;
    setLoading(true);
    setError(null);
    try {
      const [subRes, plansRes] = await Promise.all([
        api.get(`/households/${activeHouseholdId}/subscription`),
        api.get('/plans')
      ]);
      setStats(subRes.data);
      setPlans(plansRes.data.plans || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat informasi langganan');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (activeHouseholdId) {
      fetchData();
    }
  }, [activeHouseholdId]);

  const handleSubscribe = async (planId) => {
    setSubmittingId(planId);
    setError(null);
    try {
      await api.post(`/households/${activeHouseholdId}/subscription`, { plan_id: planId });
      setSuccessMessage('Berhasil memperbarui paket langganan!');
      fetchData();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal mengubah paket langganan');
    } finally {
      setSubmittingId(null);
    }
  };

  const formatRupiah = (val) => {
    const num = Number(val);
    if (isNaN(num) || num === 0) return 'Gratis';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
  };

  if (contextLoading || loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/4"></div>
          <div className="h-48 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  const activeSub = stats?.subscription;
  const usage = stats?.usage;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Langganan & Kuota Fitur</h1>
        <p className="text-gray-600 text-sm">Kelola paket langganan household dan pantau penggunaan batas kuota</p>
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

      {/* Active Subscription Overview */}
      <Card className="p-6 bg-indigo-900 text-white shadow-lg">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="text-xs uppercase font-semibold text-indigo-300 tracking-wider">Paket Aktif</span>
            <h2 className="text-3xl font-extrabold mt-1">
              {stats?.plan ? stats.plan.name : 'Gratis / Tanpa Paket'}
            </h2>
            <p className="text-indigo-200 text-sm mt-1">
              {activeSub ? `Berlaku hingga: ${new Date(activeSub.expires_at).toLocaleDateString('id-ID')}` : 'Belum berlangganan paket khusus'}
            </p>
          </div>
          <div className="bg-indigo-800 px-4 py-2 rounded-lg border border-indigo-700">
            <span className="text-xs text-indigo-300 block">Status Langganan</span>
            <span className="text-lg font-bold capitalize text-emerald-400">{activeSub?.status || 'Active (Basic)'}</span>
          </div>
        </div>

        {/* Quota Usage Bars */}
        {usage && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8 pt-6 border-t border-indigo-800">
            <div>
              <div className="flex justify-between text-xs mb-1 font-medium">
                <span>Anggota Household</span>
                <span>{usage.members.current} / {usage.members.max ?? '∞'}</span>
              </div>
              <div className="w-full bg-indigo-950 rounded-full h-2">
                <div
                  className="bg-emerald-400 h-2 rounded-full"
                  style={{ width: `${usage.members.max ? Math.min(100, (usage.members.current / usage.members.max) * 100) : 100}%` }}
                ></div>
              </div>
            </div>

            <div>
              <div className="flex justify-between text-xs mb-1 font-medium">
                <span>Akun / Rekening</span>
                <span>{usage.accounts.current} / {usage.accounts.max ?? '∞'}</span>
              </div>
              <div className="w-full bg-indigo-950 rounded-full h-2">
                <div
                  className="bg-blue-400 h-2 rounded-full"
                  style={{ width: `${usage.accounts.max ? Math.min(100, (usage.accounts.current / usage.accounts.max) * 100) : 100}%` }}
                ></div>
              </div>
            </div>

            <div>
              <div className="flex justify-between text-xs mb-1 font-medium">
                <span>Transaksi Bulan Ini</span>
                <span>{usage.transactions_this_month.current} / {usage.transactions_this_month.max ?? '∞'}</span>
              </div>
              <div className="w-full bg-indigo-950 rounded-full h-2">
                <div
                  className="bg-amber-400 h-2 rounded-full"
                  style={{ width: `${usage.transactions_this_month.max ? Math.min(100, (usage.transactions_this_month.current / usage.transactions_this_month.max) * 100) : 100}%` }}
                ></div>
              </div>
            </div>
          </div>
        )}
      </Card>

      {/* Available Plans */}
      <div>
        <h3 className="text-xl font-bold text-gray-900 mb-4">Pilihan Paket Langganan</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {plans.map((p) => {
            const isCurrent = stats?.plan?.id === p.id;
            return (
              <Card key={p.id} className={`p-6 flex flex-col justify-between border-2 ${isCurrent ? 'border-indigo-600 bg-indigo-50/20' : 'border-gray-200'}`}>
                <div>
                  <div className="flex justify-between items-center mb-2">
                    <h4 className="text-lg font-bold text-gray-900">{p.name}</h4>
                    {isCurrent && (
                      <span className="bg-indigo-100 text-indigo-800 text-xs font-semibold px-2 py-0.5 rounded">
                        Paket Anda
                      </span>
                    )}
                  </div>
                  <div className="text-2xl font-extrabold text-gray-900 mb-2">
                    {formatRupiah(p.price)} <span className="text-xs font-normal text-gray-500">/ {p.duration_days} hari</span>
                  </div>
                  <p className="text-gray-600 text-sm mb-4">{p.description || 'Paket langganan fitur lengkap'}</p>

                  <ul className="text-xs text-gray-700 space-y-2 mb-6">
                    <li className="flex items-center">
                      <span className="text-green-500 mr-2">✓</span> Maksimal {p.max_members ?? 'Tak Terbatas'} Anggota
                    </li>
                    <li className="flex items-center">
                      <span className="text-green-500 mr-2">✓</span> Maksimal {p.max_accounts ?? 'Tak Terbatas'} Akun Rekening
                    </li>
                    <li className="flex items-center">
                      <span className="text-green-500 mr-2">✓</span> Maksimal {p.max_transactions_per_month ?? 'Tak Terbatas'} Transaksi / Bulan
                    </li>
                  </ul>
                </div>

                <Button
                  onClick={() => handleSubscribe(p.id)}
                  disabled={isCurrent || submittingId === p.id}
                  className={`w-full ${isCurrent ? 'bg-gray-300 text-gray-600 cursor-default' : 'bg-indigo-600 hover:bg-indigo-700 text-white'}`}
                >
                  {isCurrent ? 'Paket Aktif' : submittingId === p.id ? 'Memproses...' : 'Pilih Paket Ini'}
                </Button>
              </Card>
            );
          })}
        </div>
      </div>
    </div>
  );
}
